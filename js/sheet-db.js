const SHEET_DATABASE_ID = "1VWosrMFHAl5WtzonzgaT-3B9bHU0mPFJeIS0FWV86KU";
const SHEET_WRITE_WEBAPP_URL = "";

const SHEET_TABLES = [
  "battle_log",
  "characters",
  "charactertraits",
  "constval",
  "context_hints",
  "element",
  "inventory",
  "items",
  "map_tiles",
  "market",
  "nav_menu",
  "rolldata",
  "slots",
  "trait_definitions"
];

const INVENTORY_OVERLAY_KEY = "stailian_sheet_inventory_overlay";
const LOCAL_ROLLS_KEY = "stailian_local_rolls";
const WRITE_QUEUE_KEY = "stailian_sheet_write_queue";

function escHtml(str) {
  return String(str ?? "")
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#39;");
}

function toNumber(value, fallback = 0) {
  if (value === "" || value == null) return fallback;
  const n = Number(value);
  return Number.isFinite(n) ? n : fallback;
}

function normalizeKey(key) {
  return String(key || "").trim();
}

function normText(value) {
  return String(value ?? "").trim().toLowerCase();
}

function canonicalId(value) {
  const text = String(value ?? "").trim();
  if (!text) return "";
  const number = Number(text);
  return Number.isFinite(number) && Number.isInteger(number) ? String(number) : text;
}

function nowIso() {
  return new Date().toISOString();
}

const SheetDB = {
  cache: null,

  async load(force = false) {
    if (this.cache && !force) return this.cache;
    const entries = await Promise.all(SHEET_TABLES.map(async table => [table, await this.fetchTable(table)]));
    this.cache = Object.fromEntries(entries);
    return this.cache;
  },

  async fetchTable(sheetName) {
    const url = `https://docs.google.com/spreadsheets/d/${SHEET_DATABASE_ID}/gviz/tq?sheet=${encodeURIComponent(sheetName)}&tqx=out:json`;
    const response = await fetch(url);
    if (!response.ok) throw new Error(`Could not read ${sheetName}: ${response.status}`);
    const text = await response.text();
    const json = JSON.parse(text.replace(/^[\s\S]*?setResponse\(/, "").replace(/\);\s*$/, ""));
    const labels = json.table.cols.map((col, index) => normalizeKey(col.label || col.id || `col_${index + 1}`));
    return json.table.rows.map(row => {
      const out = {};
      labels.forEach((label, index) => {
        const cell = row.c[index];
        out[label] = cell ? (cell.f ?? cell.v ?? "") : "";
      });
      return out;
    });
  },

  table(name) {
    if (!this.cache) throw new Error("SheetDB.load() must be called first.");
    return this.cache[name] || [];
  },

  byId(name) {
    return Object.fromEntries(this.table(name).map(row => [canonicalId(row.id), row]));
  },

  findCharacterByIdentity(identity) {
    const wanted = normText(identity);
    return this.table("characters").find(character =>
      normText(character.id) === wanted ||
      normText(character.charName) === wanted ||
      normText(character.email) === wanted ||
      normText(this.characterLabel(character)) === wanted
    ) || null;
  },

  characterLabel(character) {
    return `${character.charName}${character.email ? " - " + character.email : ""}${character.role ? " (" + character.role + ")" : ""}`;
  },

  async write(payload) {
    const body = { createdAt: nowIso(), ...payload };
    if (!SHEET_WRITE_WEBAPP_URL) {
      const queued = JSON.parse(localStorage.getItem(WRITE_QUEUE_KEY) || "[]");
      queued.push(body);
      localStorage.setItem(WRITE_QUEUE_KEY, JSON.stringify(queued));
      return { queued: true };
    }
    await fetch(SHEET_WRITE_WEBAPP_URL, {
      method: "POST",
      mode: "no-cors",
      headers: { "Content-Type": "text/plain;charset=utf-8" },
      body: JSON.stringify(body)
    });
    return { sent: true };
  },

  async append(table, row) {
    return this.write({ op: "append", table, row });
  },

  getActiveCharacterId() {
    return localStorage.getItem("stailian_active_character_id");
  },

  setActiveCharacterId(id) {
    localStorage.setItem("stailian_active_character_id", String(id));
  },

  getLocalState(id) {
    const all = JSON.parse(localStorage.getItem("stailian_sheet_state") || "{}");
    return all[String(id)] || {};
  },

  setLocalState(id, patch) {
    const all = JSON.parse(localStorage.getItem("stailian_sheet_state") || "{}");
    const key = String(id);
    all[key] = { ...(all[key] || {}), ...patch };
    localStorage.setItem("stailian_sheet_state", JSON.stringify(all));
    return all[key];
  },

  async hashPassword(password) {
    if (window.dcodeIO?.bcrypt) {
      return new Promise((resolve, reject) => {
        window.dcodeIO.bcrypt.hash(password, 10, (err, hash) => err ? reject(err) : resolve(hash));
      });
    }
    return `sha256$${await this.sha256(password)}`;
  },

  async sha256(value) {
    const bytes = new TextEncoder().encode(value);
    const digest = await crypto.subtle.digest("SHA-256", bytes);
    return Array.from(new Uint8Array(digest)).map(byte => byte.toString(16).padStart(2, "0")).join("");
  },

  async verifyPassword(password, storedHash) {
    const hash = String(storedHash || "").trim();
    if (!hash) return { ok: false, reason: "missing" };
    if (hash.startsWith("sha256$")) {
      return { ok: `sha256$${await this.sha256(password)}` === hash, reason: "sha256" };
    }
    if (/^\$2[aby]\$/.test(hash) && window.dcodeIO?.bcrypt) {
      const normalizedHash = hash.replace(/^\$2y\$/, "$2a$");
      const ok = await new Promise(resolve => {
        window.dcodeIO.bcrypt.compare(password, normalizedHash, (err, same) => resolve(!err && same));
      });
      return { ok, reason: "bcrypt" };
    }
    return { ok: false, reason: "unsupported" };
  },

  async setCharacterPassword(characterId, password) {
    const password_hash = await this.hashPassword(password);
    await this.write({
      op: "update",
      table: "characters",
      key: { id: String(characterId) },
      row: { id: String(characterId), password_hash }
    });
    return password_hash;
  },

  async createCharacter({ charName, email, password, role = "Player" }) {
    const ids = this.table("characters").map(row => toNumber(row.id));
    const id = Math.max(0, ...ids) + 1;
    const createdAt = nowIso();
    const row = {
      id,
      charName,
      charDescription: "",
      email,
      password_hash: await this.hashPassword(password),
      resetToken: "",
      resetTokenExpires: "",
      imagePath: "",
      createdAt,
      coordX: 0,
      coordY: 0,
      coordZ: 0,
      placedAt: createdAt,
      CurrentHealth: 100,
      role
    };
    await this.append("characters", row);
    if (this.cache?.characters) this.cache.characters.push(row);
    this.setActiveCharacterId(id);
    return row;
  },

  getInventoryOverlay() {
    return JSON.parse(localStorage.getItem(INVENTORY_OVERLAY_KEY) || "{}");
  },

  setInventoryOverlay(overlay) {
    localStorage.setItem(INVENTORY_OVERLAY_KEY, JSON.stringify(overlay));
  },

  inventoryKey(userId, itemId) {
    return `${String(userId)}:${String(itemId)}`;
  },

  setInventoryLocal(userId, itemId, patch) {
    const overlay = this.getInventoryOverlay();
    const key = this.inventoryKey(userId, itemId);
    overlay[key] = { ...(overlay[key] || {}), ...patch };
    this.setInventoryOverlay(overlay);
    return overlay[key];
  },

  getCurrentCharacter() {
    const id = this.getActiveCharacterId();
    if (!id) return null;
    const character = this.byId("characters")[String(id)];
    if (!character) return null;
    const state = this.getLocalState(id);
    return {
      ...character,
      id: String(character.id),
      name: character.charName,
      email: character.email,
      role: character.role,
      coins: toNumber(state.coins, this.inventoryCoins(character.id)),
      health: toNumber(state.health, character.CurrentHealth || 100),
      max_health: 100,
      position_x: toNumber(state.coordX, character.coordX),
      position_y: toNumber(state.coordY, character.coordY),
      coordZ: toNumber(state.coordZ, character.coordZ),
      is_admin: /admin/i.test(character.role || "")
    };
  },

  elementsById() {
    return this.byId("element");
  },

  slotsByName() {
    return Object.fromEntries(this.table("slots").map(row => [normText(row.slot_name), row]));
  },

  itemByName(name) {
    const wanted = normText(name);
    return this.table("items").find(item => normText(item.itemName) === wanted) || null;
  },

  itemDisplayName(row) {
    return row?.item?.itemName || row?.itemName || "Unlinked inventory item";
  },

  inventoryFor(userId) {
    return this.getInventoryRows(userId);
  },

  getInventoryRows(userId) {
    const items = this.byId("items");
    const elements = this.elementsById();
    const slots = this.slotsByName();
    const rowsByItem = new Map();

    this.table("inventory")
      .filter(row => canonicalId(row.userId ?? row.userid) === canonicalId(userId))
      .forEach(row => {
        const itemId = canonicalId(row.itemId ?? row.itemid);
        if (!itemId) return;
        const existing = rowsByItem.get(itemId) || {
          userId: canonicalId(userId),
          itemId,
          quantityOnHand: 0,
          wearing: 0
        };
        existing.quantityOnHand += toNumber(row.quantityOnHand);
        existing.wearing = Math.max(toNumber(existing.wearing), toNumber(row.wearing));
        rowsByItem.set(itemId, existing);
      });

    const overlay = this.getInventoryOverlay();
    Object.entries(overlay).forEach(([key, value]) => {
      const [overlayUserId, itemId] = key.split(":");
      if (String(overlayUserId) !== String(userId)) return;
      const existing = rowsByItem.get(itemId) || {
        userId: String(userId),
        itemId,
        quantityOnHand: 0,
        wearing: 0
      };
      if (value.quantityOnHand != null) existing.quantityOnHand = toNumber(value.quantityOnHand);
      if (value.wearing != null) existing.wearing = toNumber(value.wearing);
      rowsByItem.set(itemId, existing);
    });

    return Array.from(rowsByItem.values())
      .map(row => {
        const item = items[canonicalId(row.itemId)] || {
          id: row.itemId,
          itemName: row.itemName || "Unlinked inventory item",
          slot: "",
          basePrice: 0,
          elementId: "",
          elementMultiplier: 0,
          missingItem: true
        };
        const element = elements[canonicalId(item.elementId)] || {};
        const slotInfo = slots[normText(item.slot)] || {};
        const qoh = toNumber(row.quantityOnHand);
        return {
          ...row,
          item,
          element,
          slotInfo,
          quantityOnHand: qoh,
          qoh,
          wearing: toNumber(row.wearing),
          baseValue: qoh * toNumber(item.basePrice),
          battleScore: toNumber(item.elementMultiplier)
        };
      })
      .filter(row => row.qoh > 0)
      .sort((a, b) =>
        String(a.item.slot || "").localeCompare(String(b.item.slot || "")) ||
        String(a.element.name || "").localeCompare(String(b.element.name || "")) ||
        String(a.item.itemName || "").localeCompare(String(b.item.itemName || ""))
      );
  },

  inventoryCoins(userId) {
    const coin = this.inventoryFor(userId).find(row => /coin/i.test(row.item.itemName || ""));
    return coin ? toNumber(coin.quantityOnHand) : 0;
  },

  inventoryValue(userId) {
    const rows = this.inventoryFor(userId);
    const itemValue = rows
      .filter(row => !/coin/i.test(row.item.itemName || ""))
      .reduce((sum, row) => sum + row.baseValue, 0);
    const coins = this.inventoryCoins(userId);
    return { itemValue, coins, total: itemValue + coins };
  },

  battleScores(userId) {
    const scores = new Map();
    this.inventoryFor(userId)
      .filter(row => !/coin/i.test(row.item.itemName || ""))
      .forEach(row => {
        const key = row.element.name || "Unaligned";
        const existing = scores.get(key) || {
          element: key,
          icon: row.element.icon || "",
          score: 0
        };
        existing.score += row.battleScore;
        scores.set(key, existing);
      });
    const byElement = Array.from(scores.values()).sort((a, b) => b.score - a.score);
    return {
      total: byElement.reduce((sum, row) => sum + row.score, 0),
      byElement
    };
  },

  topInventory(userId, limit = 5) {
    return this.inventoryFor(userId)
      .filter(row => !/coin/i.test(row.item.itemName || ""))
      .sort((a, b) => toNumber(b.item.basePrice) - toNumber(a.item.basePrice))
      .slice(0, limit);
  },

  characterTraits(charId) {
    const defs = this.byId("trait_definitions");
    return this.table("charactertraits")
      .filter(row => String(row.charid) === String(charId))
      .map(row => ({ ...row, definition: defs[String(row.traitid)] || {} }));
  },

  mapTilesForZ(z) {
    const elements = this.byId("element");
    return this.table("map_tiles")
      .filter(row => toNumber(row.coordZ) === toNumber(z))
      .map(row => ({ ...row, element: elements[canonicalId(row.elementId)] || {} }));
  },

  currentTileFor(user) {
    const elements = this.elementsById();
    const tile = this.table("map_tiles").find(row =>
      toNumber(row.coordX) === toNumber(user.position_x ?? user.coordX) &&
      toNumber(row.coordY) === toNumber(user.position_y ?? user.coordY) &&
      toNumber(row.coordZ) === toNumber(user.coordZ)
    );
    return tile ? { ...tile, element: elements[canonicalId(tile.elementId)] || {} } : null;
  },

  recentRolls(userId, limit = 8) {
    const local = JSON.parse(localStorage.getItem(LOCAL_ROLLS_KEY) || "[]")
      .filter(row => String(row.userid) === String(userId));
    const sheetRows = this.table("rolldata")
      .filter(row => String(row.userid) === String(userId))
      .slice()
      .reverse();
    return [...local.slice().reverse(), ...sheetRows].slice(0, limit);
  },

  async addInventoryItemByName(userId, itemName, quantity = 1, wearing = 0) {
    const item = this.itemByName(itemName);
    if (!item) return { found: false, itemName };
    const current = this.inventoryFor(userId).find(row => canonicalId(row.itemId) === canonicalId(item.id));
    const nextQty = toNumber(current?.quantityOnHand) + toNumber(quantity, 1);
    const nextWearing = current ? toNumber(current.wearing) : toNumber(wearing);
    this.setInventoryLocal(userId, item.id, {
      quantityOnHand: nextQty,
      wearing: nextWearing
    });
    await this.write({
      op: "upsert",
      table: "inventory",
      key: { userId: String(userId), itemId: String(item.id) },
      row: {
        userId: String(userId),
        itemId: String(item.id),
        quantityOnHand: nextQty,
        wearing: nextWearing
      }
    });
    return { found: true, item, quantityOnHand: nextQty };
  },

  async setWearing(userId, itemId, shouldWear) {
    const rows = this.inventoryFor(userId);
    const target = rows.find(row => canonicalId(row.itemId) === canonicalId(itemId));
    if (!target) return { ok: false, error: "Item not found" };
    const updates = [];
    if (shouldWear) {
      rows
        .filter(row => String(row.item.slot || "") === String(target.item.slot || "") && toNumber(row.wearing) === 1)
        .forEach(row => {
          this.setInventoryLocal(userId, row.itemId, { quantityOnHand: row.qoh, wearing: 0 });
          updates.push({
            userId: String(userId),
            itemId: String(row.itemId),
            quantityOnHand: row.qoh,
            wearing: 0
          });
        });
    }
    this.setInventoryLocal(userId, itemId, { quantityOnHand: target.qoh, wearing: shouldWear ? 1 : 0 });
    updates.push({
      userId: String(userId),
      itemId: String(itemId),
      quantityOnHand: target.qoh,
      wearing: shouldWear ? 1 : 0
    });
    await this.write({ op: "batchUpsert", table: "inventory", rows: updates, keyFields: ["userId", "itemId"] });
    return { ok: true };
  },

  async sellItem(userId, itemId, quantity = 1) {
    const row = this.inventoryFor(userId).find(inv => canonicalId(inv.itemId) === canonicalId(itemId));
    if (!row || /coin/i.test(row.item.itemName || "")) return { ok: false, totalGold: 0 };
    const sellQty = Math.min(toNumber(quantity, 1), row.qoh);
    const nextQty = row.qoh - sellQty;
    const totalGold = sellQty * toNumber(row.item.basePrice);
    this.setInventoryLocal(userId, itemId, { quantityOnHand: nextQty, wearing: nextQty > 0 ? row.wearing : 0 });

    const coinItem = this.table("items").find(item => /coin/i.test(item.itemName || ""));
    const updates = [{
      userId: String(userId),
      itemId: String(itemId),
      quantityOnHand: nextQty,
      wearing: nextQty > 0 ? row.wearing : 0
    }];
    if (coinItem) {
      const coinRow = this.inventoryFor(userId).find(inv => canonicalId(inv.itemId) === canonicalId(coinItem.id));
      const nextCoins = toNumber(coinRow?.quantityOnHand) + totalGold;
      this.setInventoryLocal(userId, coinItem.id, { quantityOnHand: nextCoins, wearing: 0 });
      updates.push({
        userId: String(userId),
        itemId: String(coinItem.id),
        quantityOnHand: nextCoins,
        wearing: 0
      });
    }
    await this.write({ op: "batchUpsert", table: "inventory", rows: updates, keyFields: ["userId", "itemId"] });
    return { ok: true, totalGold };
  },

  async sellAll(userId, slotName = "All") {
    const rows = this.inventoryFor(userId).filter(row =>
      !/coin/i.test(row.item.itemName || "") &&
      toNumber(row.wearing) !== 1 &&
      (slotName === "All" || String(row.item.slot || "") === String(slotName))
    );
    let totalGold = 0;
    for (const row of rows) {
      const result = await this.sellItem(userId, row.itemId, row.qoh);
      totalGold += result.totalGold || 0;
    }
    return { ok: true, totalGold };
  },

  async rollCharacter(userId, coordX, coordY, coordZ) {
    const user = this.getCurrentCharacter();
    const tile = this.table("map_tiles").find(row =>
      toNumber(row.coordX) === toNumber(coordX) &&
      toNumber(row.coordY) === toNumber(coordY) &&
      toNumber(row.coordZ) === toNumber(coordZ)
    );
    const element = tile ? (this.elementsById()[canonicalId(tile.elementId)] || {}) : {};
    const elementName = element.name || "";
    const drops = ["scroll", "flower", "dye", "flower", "flower", "flower", "flower", "flower", "scroll", "scroll"];
    const clothes = ["shirt", "socks", "pants", "shirt", "socks", "pants", "shirt", "socks", "pants", "bowtie"];
    const rewards = [];

    if (elementName) {
      const dropName = `${elementName} ${drops[Math.floor(Math.random() * drops.length)]}`;
      const drop = await this.addInventoryItemByName(userId, dropName, 1, 0);
      if (drop.found) rewards.push(dropName);
    }
    if (toNumber(coordX) === toNumber(coordY) && elementName) {
      const clothingName = `${elementName} ${clothes[Math.floor(Math.random() * clothes.length)]}`;
      const clothing = await this.addInventoryItemByName(userId, clothingName, 1, 0);
      if (clothing.found) rewards.push(clothingName);
    }

    this.setLocalState(userId, {
      coordX: toNumber(coordX),
      coordY: toNumber(coordY),
      coordZ: toNumber(coordZ),
      placedAt: nowIso()
    });

    await this.write({
      op: "update",
      table: "characters",
      key: { id: String(userId) },
      row: {
        id: String(userId),
        coordX: toNumber(coordX),
        coordY: toNumber(coordY),
        coordZ: toNumber(coordZ),
        placedAt: nowIso()
      }
    });

    const roll = {
      x: toNumber(coordX),
      y: toNumber(coordY),
      z: toNumber(coordZ),
      userid: String(userId),
      rewards: rewards.join(", "),
      rollTimeStamp: nowIso()
    };
    const local = JSON.parse(localStorage.getItem(LOCAL_ROLLS_KEY) || "[]");
    local.push(roll);
    localStorage.setItem(LOCAL_ROLLS_KEY, JSON.stringify(local));
    const writeResult = await this.append("rolldata", roll);
    return {
      user: this.getCurrentCharacter() || user,
      tile: tile ? { ...tile, element } : null,
      rewards,
      writeResult
    };
  }
};

async function requireSheetUser(callback) {
  await SheetDB.load();
  const user = SheetDB.getCurrentCharacter();
  if (!user) {
    window.location.href = "login.html";
    return;
  }
  callback(user);
}

function logout() {
  localStorage.removeItem("stailian_active_character_id");
  window.location.href = "login.html";
}

function renderSheetError(targetId, err) {
  const target = document.getElementById(targetId);
  if (!target) return;
  target.innerHTML = `<div class="alert alert-error">Could not load the Google Sheet database. ${escHtml(err.message)}</div>`;
}
