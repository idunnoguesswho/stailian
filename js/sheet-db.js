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
    return Object.fromEntries(this.table(name).map(row => [String(row.id), row]));
  },

  async append(table, row) {
    const payload = { table, row, createdAt: new Date().toISOString() };
    if (!SHEET_WRITE_WEBAPP_URL) {
      const queued = JSON.parse(localStorage.getItem("stailian_sheet_write_queue") || "[]");
      queued.push(payload);
      localStorage.setItem("stailian_sheet_write_queue", JSON.stringify(queued));
      return { queued: true };
    }
    await fetch(SHEET_WRITE_WEBAPP_URL, {
      method: "POST",
      mode: "no-cors",
      headers: { "Content-Type": "text/plain;charset=utf-8" },
      body: JSON.stringify(payload)
    });
    return { sent: true };
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

  inventoryFor(userId) {
    const items = this.byId("items");
    return this.table("inventory")
      .filter(row => String(row.userId) === String(userId) && toNumber(row.quantityOnHand) !== 0)
      .map(row => ({ ...row, item: items[String(row.itemId)] || {} }));
  },

  inventoryCoins(userId) {
    const coin = this.inventoryFor(userId).find(row => /coin/i.test(row.item.itemName || ""));
    return coin ? toNumber(coin.quantityOnHand) : 0;
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
      .map(row => ({ ...row, element: elements[String(row.elementId)] || {} }));
  },

  recentRolls(userId, limit = 8) {
    return this.table("rolldata")
      .filter(row => String(row.userid) === String(userId))
      .slice()
      .reverse()
      .slice(0, limit);
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
