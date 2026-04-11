<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require 'auth.php';  // handles session_start() and login check
require 'db.php';
require 'layout.php';
require 'health.php';

$db     = getDB();
$userid = $SESSION_USERID;
$act    = $_GET['action'] ?? 'select';
$tab    = $_GET['tab']    ?? 'wardrobe';



// ── CONSTANTS ─────────────────────────────────────────────────────────────────
const MAX_BOOKS   = 20;
const MAX_FLOWERS = 120;
const FLOWERS_PER_BOOK = 15;

// ── HELPERS ───────────────────────────────────────────────────────────────────
function getCoins(PDO $db, int $uid): int {
    $r = $db->prepare("SELECT coins FROM user_coins WHERE userid=?");
    $r->execute([$uid]);
    return (int)($r->fetchColumn() ?: 0);
}

function addCoins(PDO $db, int $uid, int $amount): void {
    $db->prepare("INSERT INTO user_coins (userid,coins) VALUES (?,?)
                  ON DUPLICATE KEY UPDATE coins=coins+?")
       ->execute([$uid, $amount, $amount]);
}

function spendCoins(PDO $db, int $uid, int $amount): bool {
    $coins = getCoins($db, $uid);
    if ($coins < $amount) return false;
    $db->prepare("UPDATE user_coins SET coins=coins-? WHERE userid=?")
       ->execute([$amount, $uid]);
    return true;
}

function getBowtieCount(PDO $db, int $uid): int {
    $r = $db->prepare("SELECT COUNT(*) FROM inventory i
        JOIN weapons w ON w.id=i.weaponid
        WHERE i.userid=? AND w.name LIKE '%bowtie%'");
    $r->execute([$uid]);
    return (int)$r->fetchColumn();
}

function getTotalScrolls(PDO $db, int $uid): int {
    $r = $db->prepare("SELECT COALESCE(SUM(quantity),0) FROM user_scrolls WHERE userid=?");
    $r->execute([$uid]);
    return (int)$r->fetchColumn();
}

function getTotalFlowers(PDO $db, int $uid): int {
    $r = $db->prepare("SELECT COALESCE(SUM(quantity),0) FROM user_flowers WHERE userid=?");
    $r->execute([$uid]);
    return (int)$r->fetchColumn();
}
// ── BUY HEALTH PACK ───────────────────────────────────────────────────────────
if ($act === 'buy_health' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once 'health.php';
    $result = buyHealthPack($db, $userid);
    redirect("wardrobe.php?tab=shop",
        $result['msg'],
        $result['success'] ? 'success' : 'error');
}
// ── SELL BOWTIE ───────────────────────────────────────────────────────────────
if ($act === 'sell_bowtie' && $userid) {
    $weaponid = (int)($_GET['weaponid'] ?? 0);
    if ($weaponid) {
        $check = $db->prepare("SELECT i.id FROM inventory i
            JOIN weapons w ON w.id=i.weaponid
            WHERE i.userid=? AND i.weaponid=? AND w.name LIKE '%bowtie%'");
        $check->execute([$userid, $weaponid]);
        if ($check->fetch()) {
            $db->prepare("DELETE FROM inventory WHERE userid=? AND weaponid=?")
               ->execute([$userid, $weaponid]);
            $db->prepare("INSERT INTO bowtie_market (weaponid,sold_by_userid,coins_earned)
                          VALUES (?,?,10)")
               ->execute([$weaponid, $userid]);
            addCoins($db, $userid, 10);
            redirect("wardrobe.php?tab=bowties",
                "Bowtie sold for 🪙 10 coins!");
        }
    }
}

// ── SELL BOOK ─────────────────────────────────────────────────────────────────
if ($act === 'sell_scroll' && $userid && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $colour_id = (int)($_POST['colour_id'] ?? 0);
    $qty       = max(1, (int)($_POST['quantity'] ?? 1));

    $owned = $db->prepare("SELECT quantity FROM user_scrolls
        WHERE userid=? AND colour_id=?");
    $owned->execute([$userid, $colour_id]);
    $ownedQty = (int)($owned->fetchColumn() ?: 0);

    if ($ownedQty < $qty) {
        redirect("wardrobe.php?tab=scrolls",
            "You don't have that many scrolls.", 'error');
    }

    $db->prepare("UPDATE user_scrolls SET quantity=quantity-?
        WHERE userid=? AND colour_id=?")
       ->execute([$qty, $userid, $colour_id]);

    $coins = $qty; // 1 coin per scroll
    addCoins($db, $userid, $coins);

    $db->prepare("INSERT INTO scroll_sales (userid,colour_id,quantity_sold,coins_earned)
                  VALUES (?,?,?,?)")
       ->execute([$userid, $colour_id, $qty, $coins]);

    redirect("wardrobe.php?tab=scrolls",
        "Sold {$qty} scroll(s) for 🪙 {$coins} coin(s)!");
}

// ── CONVERT FLOWERS TO BOOK ───────────────────────────────────────────────────
if ($act === 'flowers_to_scroll' && $userid && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $colour_id = (int)($_POST['colour_id'] ?? 0);

    // Check flowers
    $fStmt = $db->prepare("SELECT quantity FROM user_flowers
        WHERE userid=? AND colour_id=?");
    $fStmt->execute([$userid, $colour_id]);
    $flowerQty = (int)($fStmt->fetchColumn() ?: 0);

    if ($flowerQty < FLOWERS_PER_BOOK) {
        redirect("wardrobe.php?tab=scrolls",
            "You need 15 flowers of this colour to convert to a scroll.", 'error');
    }

    // Check scroll limit
    $totalScrolls = getTotalScrolls($db, $userid);
    if ($totalScrolls >= MAX_BOOKS) {
        redirect("wardrobe.php?tab=scrolls",
            "You already have the maximum 20 scrolls. Sell some first!", 'error');
    }

    // Deduct 15 flowers
    $db->prepare("UPDATE user_flowers SET quantity=quantity-?
        WHERE userid=? AND colour_id=?")
       ->execute([FLOWERS_PER_BOOK, $userid, $colour_id]);

    // Add 1 scroll
    $db->prepare("INSERT INTO user_scrolls (userid,colour_id,quantity) VALUES (?,?,1)
                  ON DUPLICATE KEY UPDATE quantity=quantity+1")
       ->execute([$userid, $colour_id]);

    // Log conversion
    $db->prepare("INSERT INTO flower_conversions (userid,colour_id,flowers_spent,scrolls_gained)
                  VALUES (?,?,15,1)")
       ->execute([$userid, $colour_id]);

    $cname = $db->prepare("SELECT name FROM colours WHERE id=?");
    $cname->execute([$colour_id]);
    $cname = $cname->fetchColumn();

    redirect("wardrobe.php?tab=scrolls",
        "Converted 15 {$cname} flowers into 1 {$cname} scroll!");
}

// ── CRAFT DYE ─────────────────────────────────────────────────────────────────
if ($act === 'craft' && $userid && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $colour_id = (int)($_POST['colour_id'] ?? 0);

    $scrolls = $db->prepare("SELECT quantity FROM user_scrolls
        WHERE userid=? AND colour_id=?");
    $scrolls->execute([$userid, $colour_id]);
    $scrollQty = (int)($scrolls->fetchColumn() ?: 0);

    if ($scrollQty < 5) {
        redirect("wardrobe.php?tab=craft",
            "You need at least 5 scrolls of this colour to craft a dye.", 'error');
    }

    $flowers = $db->prepare("SELECT quantity FROM user_flowers
        WHERE userid=? AND colour_id=?");
    $flowers->execute([$userid, $colour_id]);
    $flowerQty  = min(10, (int)($flowers->fetchColumn() ?: 0));
    $shadeLevel = max(1, $flowerQty);

    $shade = $db->prepare("SELECT id FROM colour_shades
        WHERE colour_id=? AND shade_level=?");
    $shade->execute([$colour_id, $shadeLevel]);
    $shadeId = $shade->fetchColumn();

    if (!$shadeId) {
        redirect("wardrobe.php?tab=craft",
            "Could not determine shade level.", 'error');
    }

    $db->prepare("UPDATE user_scrolls SET quantity=quantity-5
        WHERE userid=? AND colour_id=?")
       ->execute([$userid, $colour_id]);

    $db->prepare("INSERT INTO user_dyes (userid,colour_shade_id,quantity) VALUES (?,?,1)
                  ON DUPLICATE KEY UPDATE quantity=quantity+1")
       ->execute([$userid, $shadeId]);

    $cname = $db->prepare("SELECT name FROM colours WHERE id=?");
    $cname->execute([$colour_id]);
    $cname = $cname->fetchColumn();

    redirect("wardrobe.php?tab=craft",
        "Crafted a shade {$shadeLevel} {$cname} dye!");
}

// ── DYE CLOTHING ──────────────────────────────────────────────────────────────
if ($act === 'dye' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $clothing_id     = (int)($_POST['clothing_id']     ?? 0);
    $colour_shade_id = (int)($_POST['colour_shade_id'] ?? 0);

    if (!$clothing_id || !$colour_shade_id) {
        redirect("wardrobe.php?tab=wardrobe",
            "Please select both a clothing item and a dye.", 'error');
    }

    // Verify user owns the dye and has quantity > 0
    $dye = $db->prepare("SELECT ud.*, cs.shade_level, c.name as colour_name,
        cs.skill_bonus, c.element
        FROM user_dyes ud
        JOIN colour_shades cs ON cs.id=ud.colour_shade_id
        JOIN colours c ON c.id=cs.colour_id
        WHERE ud.userid=? AND ud.colour_shade_id=? AND ud.quantity > 0");
    $dye->execute([$userid, $colour_shade_id]);
    $dye = $dye->fetch();

    if (!$dye) {
        redirect("wardrobe.php?tab=wardrobe",
            "You don't have that dye or it has already been used.", 'error');
    }

    // Verify user owns the clothing item
    $cloth = $db->prepare("SELECT uc.*, ct.name as type_name, ct.slot
        FROM user_clothing uc
        JOIN clothing_types ct ON ct.id=uc.clothing_type_id
        WHERE uc.id=? AND uc.userid=?");
    $cloth->execute([$clothing_id, $userid]);
    $cloth = $cloth->fetch();

    if (!$cloth) {
        redirect("wardrobe.php?tab=wardrobe",
            "You don't own that clothing item.", 'error');
    }

    // Apply dye to clothing
    $db->prepare("UPDATE user_clothing SET colour_shade_id=? WHERE id=? AND userid=?")
       ->execute([$colour_shade_id, $clothing_id, $userid]);

    // Decrement dye quantity
    $db->prepare("UPDATE user_dyes SET quantity=quantity-1
        WHERE userid=? AND colour_shade_id=?")
       ->execute([$userid, $colour_shade_id]);

    // Delete dye record if quantity hits zero
    $db->prepare("DELETE FROM user_dyes
        WHERE userid=? AND colour_shade_id=? AND quantity <= 0")
       ->execute([$userid, $colour_shade_id]);

    redirect("wardrobe.php?tab=wardrobe",
        "✓ {$cloth['type_name']} dyed with {$dye['colour_name']} Shade {$dye['shade_level']} — +{$dye['skill_bonus']} {$dye['element']} power! Dye consumed.");
}

// ── EQUIP CLOTHING ────────────────────────────────────────────────────────────
if ($act === 'equip') {
    $clothing_id = (int)($_GET['clothing_id'] ?? 0);

    if (!$clothing_id) {
        redirect("wardrobe.php?tab=wardrobe", "No clothing item selected.", 'error');
    }

    // Get the clothing item and verify ownership
    $cloth = $db->prepare("SELECT uc.*, ct.slot, ct.name as type_name
        FROM user_clothing uc
        JOIN clothing_types ct ON ct.id=uc.clothing_type_id
        WHERE uc.id=? AND uc.userid=?");
    $cloth->execute([$clothing_id, $userid]);
    $cloth = $cloth->fetch();

    if (!$cloth) {
        redirect("wardrobe.php?tab=wardrobe",
            "Clothing item not found.", 'error');
    }

    $slot = $cloth['slot'];

    // Unequip everything in this slot first
    $db->prepare("UPDATE user_clothing
        SET is_equipped=0
        WHERE userid=? AND slot=?")
       ->execute([$userid, $slot]);

    // Equip the selected item
    $db->prepare("UPDATE user_clothing
        SET is_equipped=1
        WHERE id=? AND userid=?")
       ->execute([$clothing_id, $userid]);

    // Verify it actually updated
    $check = $db->prepare("SELECT is_equipped FROM user_clothing WHERE id=?");
    $check->execute([$clothing_id]);
    $equipped = (int)$check->fetchColumn();

    if ($equipped) {
        redirect("wardrobe.php?tab=wardrobe",
            "✓ {$cloth['type_name']} equipped in {$slot} slot!");
    } else {
        redirect("wardrobe.php?tab=wardrobe",
            "Something went wrong equipping that item.", 'error');
    }
}

// ── BUY CLOTHING ──────────────────────────────────────────────────────────────
if ($act === 'buy' && $userid && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $clothing_type_id = (int)($_POST['clothing_type_id'] ?? 0);

    $ct = $db->prepare("SELECT * FROM clothing_types WHERE id=?");
    $ct->execute([$clothing_type_id]);
    $ct = $ct->fetch();

    if (!$ct) {
        redirect("wardrobe.php?tab=shop", "Item not found.", 'error');
    }

    if (!spendCoins($db, $userid, $ct['base_price'])) {
        redirect("wardrobe.php?tab=shop",
            "Not enough coins. You need {$ct['base_price']} coins.", 'error');
    }

    $db->prepare("INSERT INTO user_clothing (userid,clothing_type_id,slot) VALUES (?,?,?)")
       ->execute([$userid, $clothing_type_id, $ct['slot']]);

    redirect("wardrobe.php?tab=wardrobe", "Purchased {$ct['name']}!");
}

// ── USER SELECT SCREEN ────────────────────────────────────────────────────────
//removed to only see your profile.

// ── FETCH USER ────────────────────────────────────────────────────────────────
$user = $db->prepare("SELECT u.*, COALESCE(uc.coins,0) as coins
    FROM users u LEFT JOIN user_coins uc ON uc.userid=u.id WHERE u.id=?");
$user->execute([$userid]);
$user = $user->fetch();
if (!$user) {
    session_destroy();
    header('Location: login.php');
    exit;
}

$coins      = (int)$user['coins'];
$totalScrolls = getTotalScrolls($db, $userid);
$totalFlowers = getTotalFlowers($db, $userid);
$bowtieCount  = getBowtieCount($db, $userid);

// ── FETCH ALL DATA ────────────────────────────────────────────────────────────

// Equipped clothing
$equippedStmt = $db->prepare("
    SELECT uc.*, ct.name as type_name, ct.slot, ct.shield_element, ct.shield_value,
           COALESCE(cs.hex_value,'') as hex_value,
           COALESCE(cs.shade_level,0) as shade_level,
           COALESCE(cs.skill_bonus,0) as skill_bonus,
           COALESCE(c.name,'') as colour_name,
           COALESCE(c.element,'') as colour_element
    FROM user_clothing uc
    JOIN clothing_types ct ON ct.id=uc.clothing_type_id
    LEFT JOIN colour_shades cs ON cs.id=uc.colour_shade_id
    LEFT JOIN colours c ON c.id=cs.colour_id
    WHERE uc.userid=? AND uc.is_equipped=1");
$equippedStmt->execute([$userid]);
$equippedItems = [];
foreach ($equippedStmt->fetchAll() as $e) {
    $equippedItems[$e['slot']] = $e;
}

// All clothing
$allClothingStmt = $db->prepare("
    SELECT uc.*, ct.name as type_name, ct.slot, ct.shield_element, ct.shield_value,
           COALESCE(cs.hex_value,'') as hex_value,
           COALESCE(cs.shade_level,0) as shade_level,
           COALESCE(cs.skill_bonus,0) as skill_bonus,
           COALESCE(c.name,'') as colour_name,
           COALESCE(c.element,'') as colour_element
    FROM user_clothing uc
    JOIN clothing_types ct ON ct.id=uc.clothing_type_id
    LEFT JOIN colour_shades cs ON cs.id=uc.colour_shade_id
    LEFT JOIN colours c ON c.id=cs.colour_id
    WHERE uc.userid=?
    ORDER BY ct.slot, ct.name");
$allClothingStmt->execute([$userid]);
$allClothing = $allClothingStmt->fetchAll();
$slots = ['shirt'=>[],'pants'=>[],'socks'=>[]];
foreach ($allClothing as $c) {
    if (isset($slots[$c['slot']])) $slots[$c['slot']][] = $c;
}

// Scrolls
$scrollStmt = $db->prepare("SELECT ub.*, c.name as colour_name, c.hex_base, c.element, c.id as colour_id
    FROM user_scrolls ub JOIN colours c ON c.id=ub.colour_id
    WHERE ub.userid=? ORDER BY c.name");
$scrollStmt->execute([$userid]);
$userScrolls = $scrollStmt->fetchAll();
$scrollMap   = [];
foreach ($userScrolls as $b) $scrollMap[$b['colour_id']] = $b['quantity'];

// Flowers
$flowerStmt = $db->prepare("SELECT uf.*, c.name as colour_name, c.hex_base, c.id as colour_id
    FROM user_flowers uf JOIN colours c ON c.id=uf.colour_id
    WHERE uf.userid=? ORDER BY c.name");
$flowerStmt->execute([$userid]);
$userFlowers = $flowerStmt->fetchAll();
$flowerMap   = [];
foreach ($userFlowers as $f) $flowerMap[$f['colour_id']] = $f['quantity'];

// Dyes
$dyeStmt = $db->prepare("SELECT ud.*, cs.shade_level, cs.hex_value, cs.skill_bonus,
    c.name as colour_name, c.element, c.id as colour_id
    FROM user_dyes ud
    JOIN colour_shades cs ON cs.id=ud.colour_shade_id
    JOIN colours c ON c.id=cs.colour_id
    WHERE ud.userid=? AND ud.quantity > 0
    ORDER BY c.name, cs.shade_level");
$dyeStmt->execute([$userid]);
$userDyes = $dyeStmt->fetchAll();

// All colours
$colours = $db->query("SELECT * FROM colours ORDER BY name")->fetchAll();

// Bowties
$bowtieStmt = $db->prepare("SELECT i.id as inv_id, w.*
    FROM inventory i JOIN weapons w ON w.id=i.weaponid
    WHERE i.userid=? AND w.name LIKE '%bowtie%'");
$bowtieStmt->execute([$userid]);
$bowties = $bowtieStmt->fetchAll();

// Shop items
$shopItems = $db->query("SELECT * FROM clothing_types ORDER BY slot, base_price")->fetchAll();

// Bowtie market
$market = $db->query("SELECT bm.*, w.name as weapon_name, w.weapon_description,
    u.name as sold_by FROM bowtie_market bm
    JOIN weapons w ON w.id=bm.weaponid
    JOIN users u ON u.id=bm.sold_by_userid
    ORDER BY bm.sold_at DESC")->fetchAll();


// All shades for chart
$allShades = $db->query("SELECT cs.*, c.name as colour_name, c.element, c.id as colour_id
    FROM colour_shades cs JOIN colours c ON c.id=cs.colour_id
    ORDER BY c.name, cs.shade_level")->fetchAll();
$shadesByColour = [];
foreach ($allShades as $s) $shadesByColour[$s['colour_id']][] = $s;

$elIcons   = ['ice'=>'❄','ground'=>'🌍','fire'=>'🔥','water'=>'💧','dark'=>'🌑'];
$slotIcons = ['shirt'=>'👕','pants'=>'👖','socks'=>'🧦'];

pageHeader('Wardrobe — ' . htmlspecialchars($user['name']), 'wardrobe.php');
echo flash();
?>

<!-- PAGE HEADER -->
<div style="display:flex; align-items:center; gap:1rem; margin-bottom:1rem; flex-wrap:wrap;">
  <h1 class="page-title" style="margin-bottom:0;">
    👕 <?= htmlspecialchars($user['name']) ?>'s Wardrobe
  </h1>
  <a class="btn btn-outline btn-sm" href="wardrobe.php">← All Users</a>
  <div style="margin-left:auto; display:flex; gap:1.25rem; align-items:center; flex-wrap:wrap;">
    <span style="font-family:'Cinzel',serif; color:var(--gold); font-size:1.1rem;">🪙 <?= $coins ?></span>
    <span style="font-size:.85rem; color:<?= $totalScrolls >= MAX_BOOKS ? 'var(--danger)' : 'var(--muted)' ?>">
      📚 <?= $totalScrolls ?>/<?= MAX_BOOKS ?>
    </span>
    <span style="font-size:.85rem; color:<?= $totalFlowers >= MAX_FLOWERS ? 'var(--danger)' : 'var(--muted)' ?>">
      🌸 <?= $totalFlowers ?>/<?= MAX_FLOWERS ?>
    </span>
    <span style="font-size:.85rem; color:<?= $bowtieCount > 4 ? 'var(--danger)' : 'var(--muted)' ?>">
      🎀 <?= $bowtieCount ?>/4
    </span>
  </div>
</div>

<!-- LIMIT WARNINGS -->
<?php if ($totalScrolls >= MAX_BOOKS): ?>
<div class="alert alert-error">
  📚 Book bag full (<?= MAX_BOOKS ?>/<?= MAX_BOOKS ?>)! Sell scrolls on the Scrolls tab to make room.
</div>
<?php endif; ?>
<?php if ($totalFlowers >= MAX_FLOWERS): ?>
<div class="alert alert-error">
  🌸 Flower bag full (<?= MAX_FLOWERS ?>/<?= MAX_FLOWERS ?>)! Convert flowers to scrolls or they will be lost.
</div>
<?php endif; ?>

<!-- TABS -->
<div style="display:flex; gap:0; border-bottom:1px solid var(--border); margin-bottom:1.5rem; flex-wrap:wrap;">
  <?php foreach ([
    'wardrobe' => '👕 Wardrobe',
    'craft'    => '⚗ Craft',
    'shop'     => '🛒 Shop',
    'scrolls'    => '📚 Scrolls & Flowers',
    'bowties'  => '🎀 Bowties',
  ] as $t => $label): ?>
  <a href="wardrobe.php?tab="tab=<?= $t ?>"
     style="text-decoration:none; font-family:'Cinzel',serif; font-size:.72rem;
            letter-spacing:.08em; padding:.7rem 1rem; white-space:nowrap;
            border-bottom:2px solid <?= $tab===$t ? 'var(--gold)' : 'transparent' ?>;
            color:<?= $tab===$t ? 'var(--gold)' : 'var(--muted)' ?>">
    <?= $label ?>
  </a>
  <?php endforeach; ?>
</div>

<?php if ($tab === 'wardrobe'): ?>
<!-- ══════════════════════════════════════════════════════════════════════════ -->
<!-- WARDROBE TAB                                                              -->
<!-- ══════════════════════════════════════════════════════════════════════════ -->

<div class="card">
  <div class="card-title">Current Outfit</div>
  <div style="display:flex; gap:2rem; flex-wrap:wrap; justify-content:center; padding:1rem 0;">
    <?php foreach (['shirt','pants','socks'] as $slot):
      $item = $equippedItems[$slot] ?? null;
      $bg   = ($item && !empty($item['hex_value'])) ? $item['hex_value'] : 'var(--surface)';
    ?>
    <div style="text-align:center;">
      <div style="width:80px; height:80px; border-radius:12px; background:<?= $bg ?>;
           border:2px solid var(--border); display:flex; align-items:center;
           justify-content:center; font-size:2.5rem; margin:0 auto .5rem;">
        <?= $slotIcons[$slot] ?>
      </div>
      <div style="font-family:'Cinzel',serif; font-size:.78rem; color:var(--text);">
        <?= $item ? htmlspecialchars($item['type_name']) : 'None equipped' ?>
      </div>
      <?php if ($item && !empty($item['colour_name'])): ?>
        <div style="font-size:.7rem; color:var(--muted);">
          <?= htmlspecialchars($item['colour_name']) ?> · Shade <?= $item['shade_level'] ?>
        </div>
      <?php endif; ?>
      <?php if ($item && !empty($item['shield_element'])): ?>
        <span class="badge badge-<?= $item['shield_element'] ?>" style="margin-top:.25rem; display:inline-block;">
          <?= $elIcons[$item['shield_element']] ?> -<?= $item['shield_value'] ?>
        </span>
      <?php endif; ?>
      <?php if ($item && $item['skill_bonus'] > 0): ?>
        <div style="font-size:.7rem; color:var(--gold); margin-top:.2rem;">
          +<?= $item['skill_bonus'] ?>
          <?= !empty($item['colour_element']) ? ($elIcons[$item['colour_element']] ?? '') : '' ?>
        </div>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<?php foreach (['shirt','pants','socks'] as $slot): ?>
<h2 class="page-title" style="font-size:1rem; margin-bottom:.75rem;">
  <?= $slotIcons[$slot] ?> <?= ucfirst($slot) ?>s
</h2>
<?php if (!empty($slots[$slot])): ?>
<div class="grid-3" style="margin-bottom:1.5rem;">
  <?php foreach ($slots[$slot] as $item):
    $isEquipped = (bool)$item['is_equipped'];
    $hasDye     = !empty($item['hex_value']);
    $bg         = $hasDye ? $item['hex_value'] : 'var(--surface)';
  ?>
  <div class="card" style="margin-bottom:0; padding:1rem;
       border-color:<?= $isEquipped ? 'var(--gold)' : 'var(--border)' ?>;">
    <div style="display:flex; align-items:center; gap:.75rem; margin-bottom:.75rem;">
      <div style="width:2.5rem; height:2.5rem; border-radius:8px; background:<?= $bg ?>;
           border:1px solid var(--border); display:flex; align-items:center;
           justify-content:center; font-size:1.2rem; flex-shrink:0;">
        <?= $slotIcons[$slot] ?>
      </div>
      <div style="flex:1; min-width:0;">
        <div style="font-family:'Cinzel',serif; font-size:.85rem; color:var(--text);">
          <?= htmlspecialchars($item['type_name']) ?>
          <?php if ($isEquipped): ?>
            <span style="color:var(--gold); font-size:.65rem;"> ✓</span>
          <?php endif; ?>
        </div>
        <?php if ($hasDye && !empty($item['colour_name'])): ?>
          <div style="font-size:.72rem; color:var(--muted);">
            <?= htmlspecialchars($item['colour_name']) ?> · S<?= $item['shade_level'] ?>
            <?php if ($item['skill_bonus'] > 0): ?>
              · <span style="color:var(--gold);">+<?= $item['skill_bonus'] ?>
                  <?= $elIcons[$item['colour_element']] ?? '' ?></span>
            <?php endif; ?>
          </div>
        <?php else: ?>
          <div style="font-size:.72rem; color:var(--muted); font-style:italic;">Undyed</div>
        <?php endif; ?>
        <?php if (!empty($item['shield_element'])): ?>
          <span class="badge badge-<?= $item['shield_element'] ?>" style="margin-top:.15rem;">
            <?= $elIcons[$item['shield_element']] ?> -<?= $item['shield_value'] ?>
          </span>
        <?php endif; ?>
      </div>
    </div>
    <div style="display:flex; gap:.4rem; flex-wrap:wrap; align-items:center;">
      <?php if (!$isEquipped): ?>
        <a class="btn btn-outline btn-sm"
           href="wardrobe.php?tab=wardrobe&"action=equip&clothing_id=<?= $item['id'] ?>">
          Equip
        </a>
      <?php endif; ?>
      <?php if (!empty($userDyes)): ?>
      <form method="post"
            action="wardrobe.php?tab=wardrobe&action=dye"
            style="display:flex; gap:.3rem; align-items:center; flex-wrap:wrap;">
        <input type="hidden" name="clothing_id" value="<?= $item['id'] ?>">
        <select name="colour_shade_id" style="font-size:.72rem; padding:.25rem .4rem; max-width:150px;">
          <?php foreach ($userDyes as $dye): ?>
            <option value="<?= $dye['colour_shade_id'] ?>">
              <?= htmlspecialchars($dye['colour_name']) ?> S<?= $dye['shade_level'] ?>
              (+<?= $dye['skill_bonus'] ?>) ×<?= $dye['quantity'] ?>
            </option>
          <?php endforeach; ?>
        </select>
        <button class="btn btn-outline btn-sm" type="submit">Dye</button>
      </form>
      <?php else: ?>
        <span style="font-size:.72rem; color:var(--muted); font-style:italic;">No dyes</span>
      <?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php else: ?>
  <p style="color:var(--muted); font-style:italic; margin-bottom:1.5rem;">
    No <?= $slot ?>s owned.
    <a href="wardrobe.php?tab=shop" style="color:var(--gold)">Visit Shop →</a>
  </p>
<?php endif; ?>
<?php endforeach; ?>


<?php elseif ($tab === 'craft'): ?>
<!-- ══════════════════════════════════════════════════════════════════════════ -->
<!-- CRAFT TAB                                                                 -->
<!-- ══════════════════════════════════════════════════════════════════════════ -->
<div class="grid-2">
  <div>
    <div class="card">
      <div class="card-title">⚗ Craft a Dye</div>
      <p style="color:var(--muted); font-size:.88rem; margin-bottom:1.25rem; line-height:1.7;">
        Need <strong style="color:var(--gold)">5 scrolls</strong> of a colour to craft.<br>
        Flowers set the shade level (max 10) → darker = stronger bonus.<br>
        Max <strong style="color:var(--gold)">20 scrolls</strong> total.
      </p>
      <?php
      $craftableExists = false;
      foreach ($colours as $c) {
          if (($scrollMap[$c['id']] ?? 0) >= 5) { $craftableExists = true; break; }
      }
      ?>
      <?php if ($craftableExists): ?>
      <form method="post" action="wardrobe.php?tab=craft&action=craft">
        <div class="form-group" style="margin-bottom:1.25rem;">
          <label>Choose Colour</label>
          <select name="colour_id">
            <?php foreach ($colours as $c):
              $qty     = $scrollMap[$c['id']] ?? 0;
              $flowers = $flowerMap[$c['id']] ?? 0;
              $shade   = min(10, max(1, $flowers));
              if ($qty < 5) continue;
            ?>
            <option value="<?= $c['id'] ?>">
              <?= htmlspecialchars($c['name']) ?>
              — 📚<?= $qty ?> 🌸<?= $flowers ?> → Shade <?= $shade ?> (+<?= $shade * 2 ?>
              <?= $elIcons[$c['element']] ?? '' ?>)
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <button class="btn btn-primary" type="submit">⚗ Craft Dye</button>
      </form>
      <?php else: ?>
        <div class="alert alert-error">
          Need 5+ scrolls of any colour to craft. Visit Scrolls tab to convert flowers or sell scrolls.
        </div>
      <?php endif; ?>
    </div>

    <!-- BOOK PROGRESS -->
    <div class="card">
      <div class="card-title">📚 Book Progress (<?= $totalScrolls ?>/<?= MAX_BOOKS ?>)</div>
      <div style="display:flex; flex-direction:column; gap:.45rem;">
        <?php foreach ($colours as $c):
          $qty  = $scrollMap[$c['id']] ?? 0;
          $pct  = min(100, round(($qty / 5) * 100));
          if ($qty === 0) continue;
        ?>
        <div style="padding:.45rem .6rem; background:var(--surface);
             border-radius:var(--radius); border:1px solid var(--border);">
          <div style="display:flex; align-items:center; gap:.5rem; margin-bottom:.25rem;">
            <div style="width:.9rem; height:.9rem; border-radius:2px;
                 background:<?= htmlspecialchars($c['hex_base']) ?>; flex-shrink:0;"></div>
            <span style="flex:1; font-size:.85rem; color:var(--text);">
              <?= htmlspecialchars($c['name']) ?>
            </span>
            <span style="font-family:'Cinzel',serif; font-size:.82rem; color:var(--gold);">
              ×<?= $qty ?>
            </span>
            <?php if ($qty >= 5): ?>
              <span style="font-size:.7rem; color:var(--success);">✓ Craft ready</span>
            <?php endif; ?>
          </div>
          <div style="background:var(--border); border-radius:10px; height:4px; overflow:hidden;">
            <div style="background:<?= htmlspecialchars($c['hex_base']) ?>;
                 width:<?= $pct ?>%; height:100%;"></div>
          </div>
        </div>
        <?php endforeach; ?>
        <?php if (!$userScrolls): ?>
          <p style="color:var(--muted); font-style:italic; font-size:.85rem;">No scrolls yet.</p>
        <?php endif; ?>
      </div>
    </div>

    <!-- DYE INVENTORY -->
    <?php if ($userDyes): ?>
    <div class="card">
      <div class="card-title">Your Dyes</div>
      <div style="display:flex; flex-direction:column; gap:.4rem;">
        <?php foreach ($userDyes as $dye): ?>
        <div style="display:flex; align-items:center; gap:.75rem; padding:.4rem .6rem;
             background:var(--surface); border-radius:var(--radius); border:1px solid var(--border);">
          <div style="width:1.5rem; height:1.5rem; border-radius:4px;
               background:<?= htmlspecialchars($dye['hex_value']) ?>; flex-shrink:0;"></div>
          <div style="flex:1; font-size:.85rem;">
            <?= htmlspecialchars($dye['colour_name']) ?> · Shade <?= $dye['shade_level'] ?>
          </div>
          <span class="badge badge-<?= $dye['element'] ?>">
            +<?= $dye['skill_bonus'] ?> <?= $elIcons[$dye['element']] ?? '' ?>
          </span>
          <span style="font-family:'Cinzel',serif; font-size:.85rem; color:var(--gold);">
            ×<?= $dye['quantity'] ?>
          </span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <!-- SHADE CHART -->
  <div class="card">
    <div class="card-title">Colour & Shade Chart</div>
    <p style="font-size:.82rem; color:var(--muted); margin-bottom:1.25rem;">
      Darker shades give higher skill bonuses. Flowers = shade level when crafting.
    </p>
    <?php foreach ($colours as $c):
      $shades = $shadesByColour[$c['id']] ?? [];
    ?>
    <div style="margin-bottom:1.1rem;">
      <div style="display:flex; align-items:center; gap:.4rem; margin-bottom:.3rem;">
        <div style="width:.8rem; height:.8rem; border-radius:2px;
             background:<?= htmlspecialchars($c['hex_base']) ?>;"></div>
        <span style="font-family:'Cinzel',serif; font-size:.75rem; color:var(--text);">
          <?= htmlspecialchars($c['name']) ?>
        </span>
        <span style="font-size:.7rem; color:var(--muted);">
          <?= $elIcons[$c['element']] ?? '' ?>
        </span>
      </div>
      <div style="display:flex; gap:2px;">
        <?php foreach ($shades as $sh): ?>
        <div style="flex:1;" title="Shade <?= $sh['shade_level'] ?> · +<?= $sh['skill_bonus'] ?>">
          <div style="height:1.2rem; background:<?= $sh['hex_value'] ?>; border-radius:2px;"></div>
          <div style="text-align:center; font-size:.42rem; color:var(--muted); margin-top:.1rem;">
            <?= $sh['skill_bonus'] ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>


<?php elseif ($tab === 'shop'): ?>
<!-- ══════════════════════════════════════════════════════════════════════════ -->
<!-- SHOP TAB                                                                  -->
<!-- ══════════════════════════════════════════════════════════════════════════ -->
<!-- HEALTH PACK -->
<?php
require_once 'health.php';
$healthData = getHealth($db, $userid);
$healthPct  = round(($healthData['health'] / $healthData['max_health']) * 100);
$coinsStmt  = $db->prepare("SELECT COALESCE(coins,0) FROM user_coins WHERE userid=?");
$coinsStmt->execute([$userid]);
$currentCoins = (int)$coinsStmt->fetchColumn();
?>
<div class="card" style="margin-bottom:1.5rem;
     border-color:<?= $healthData['health'] <= 25 ? 'var(--danger)' : 'var(--border)' ?>;">
  <div class="card-title">🧪 Health</div>
  <div style="margin-bottom:.75rem;">
    <?= healthBarHtml($healthData) ?>
  </div>
  <div class="grid-2" style="gap:.75rem; align-items:center;">
    <div>
      <div style="font-size:.85rem; color:var(--text); margin-bottom:.25rem;">
        🧪 Health Pack
      </div>
      <div style="font-size:.78rem; color:var(--muted); margin-bottom:.25rem;">
        Restores <strong style="color:var(--success);">+50 HP</strong> instantly
      </div>
      <div style="font-size:.78rem; color:var(--muted);">
        Auto-heals <strong style="color:var(--success);">+10 HP</strong> every minute
      </div>
    </div>
    <div style="text-align:right;">
      <?php if ($healthData['health'] >= $healthData['max_health']): ?>
        <div style="font-size:.82rem; color:var(--success); margin-bottom:.5rem;">
          ❤ Full health!
        </div>
      <?php else: ?>
        <div style="font-family:'Cinzel',serif; font-size:1rem; color:var(--gold);
             margin-bottom:.5rem;">🪙 50 coins</div>
        <?php if ($currentCoins >= 50): ?>
          <form method="post" action="wardrobe.php?action=buy_health&tab=shop">
            <button class="btn btn-primary btn-sm" type="submit">
              🧪 Buy Health Pack
            </button>
          </form>
        <?php else: ?>
          <div style="font-size:.75rem; color:var(--danger);">
            Need <?= 50 - $currentCoins ?> more coins
          </div>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
</div>
<div style="display:flex; align-items:center; gap:1rem; margin-bottom:1.25rem; flex-wrap:wrap;">
  <h2 class="page-title" style="font-size:1.1rem; margin-bottom:0;">🛒 Clothing Shop</h2>
  <span style="font-family:'Cinzel',serif; color:var(--gold);">🪙 <?= $coins ?> coins</span>
</div>
<?php foreach (['shirt','pants','socks'] as $slot): ?>
<h3 style="font-family:'Cinzel',serif; font-size:.8rem; letter-spacing:.1em;
    color:var(--muted); margin-bottom:.75rem; margin-top:1rem;">
  <?= $slotIcons[$slot] ?> <?= strtoupper($slot) ?>S
</h3>
<div class="grid-3" style="margin-bottom:1rem;">
  <?php foreach ($shopItems as $item):
    if ($item['slot'] !== $slot) continue;
    $canAfford = $coins >= $item['base_price'];
    $isFree    = $item['base_price'] === 0;
  ?>
  <div class="card" style="margin-bottom:0; padding:1rem;
       opacity:<?= (!$canAfford && !$isFree) ? '.6' : '1' ?>;">
    <div style="font-family:'Cinzel',serif; font-size:.85rem; color:var(--text); margin-bottom:.4rem;">
      <?= $slotIcons[$slot] ?> <?= htmlspecialchars($item['name']) ?>
    </div>
    <?php if (!empty($item['shield_element'])): ?>
      <span class="badge badge-<?= $item['shield_element'] ?>" style="margin-bottom:.4rem; display:inline-block;">
        <?= $elIcons[$item['shield_element']] ?> Shield -<?= $item['shield_value'] ?>
      </span>
    <?php else: ?>
      <div style="font-size:.75rem; color:var(--muted); font-style:italic; margin-bottom:.4rem;">No shield</div>
    <?php endif; ?>
    <div style="font-family:'Cinzel',serif; font-size:.95rem; margin-bottom:.75rem;
         color:<?= $canAfford || $isFree ? 'var(--gold)' : 'var(--danger)' ?>;">
      <?= $isFree ? 'Free' : '🪙 '.$item['base_price'] ?>
    </div>
    <?php if ($canAfford || $isFree): ?>
    <form method="post" action="wardrobe.php?tab=shop&action=buy">
      <input type="hidden" name="clothing_type_id" value="<?= $item['id'] ?>">
      <button class="btn btn-primary btn-sm" type="submit">Buy</button>
    </form>
    <?php else: ?>
      <div style="font-size:.75rem; color:var(--danger);">
        Need <?= $item['base_price'] - $coins ?> more coins
      </div>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>
</div>
<?php endforeach; ?>


<?php elseif ($tab === 'scrolls'): ?>
<!-- ══════════════════════════════════════════════════════════════════════════ -->
<!-- BOOKS & FLOWERS TAB                                                       -->
<!-- ══════════════════════════════════════════════════════════════════════════ -->

<div class="grid-2">

  <!-- BOOKS -->
  <div>
    <div class="card">
      <div class="card-title">
        📚 Scrolls (<?= $totalScrolls ?>/<?= MAX_BOOKS ?>)
        <?php if ($totalScrolls >= MAX_BOOKS): ?>
          <span style="color:var(--danger); font-size:.75rem;"> — FULL</span>
        <?php endif; ?>
      </div>
      <p style="font-size:.85rem; color:var(--muted); margin-bottom:1rem;">
        5 scrolls = 1 dye. Max <?= MAX_BOOKS ?> scrolls total.
        Sell scrolls for 🪙 1 coin each when full.
      </p>

      <?php if ($userScrolls): ?>
      <div style="display:flex; flex-direction:column; gap:.5rem;">
        <?php foreach ($userScrolls as $b):
          if ($b['quantity'] <= 0) continue;
        ?>
        <div style="padding:.5rem .6rem; background:var(--surface);
             border-radius:var(--radius); border:1px solid var(--border);">
          <div style="display:flex; align-items:center; gap:.6rem; margin-bottom:.4rem;">
            <div style="width:1rem; height:1rem; border-radius:3px;
                 background:<?= htmlspecialchars($b['hex_base']) ?>; flex-shrink:0;"></div>
            <span style="flex:1; font-size:.88rem; color:var(--text);">
              <?= htmlspecialchars($b['colour_name']) ?>
            </span>
            <span style="font-family:'Cinzel',serif; color:var(--gold);">
              ×<?= $b['quantity'] ?>
            </span>
          </div>
          <!-- SELL BOOK FORM -->
          <form method="post"
                action="wardrobe.php?tab=scrolls&action=sell_scroll"
                style="display:flex; gap:.4rem; align-items:center; flex-wrap:wrap;">
            <input type="hidden" name="colour_id" value="<?= $b['colour_id'] ?>">
            <label style="font-size:.72rem; color:var(--muted); font-family:'Crimson Pro',serif;">
              Sell
            </label>
            <select name="quantity" style="font-size:.72rem; padding:.2rem .35rem; width:60px;">
              <?php for ($i = 1; $i <= $b['quantity']; $i++): ?>
                <option value="<?= $i ?>"><?= $i ?></option>
              <?php endfor; ?>
            </select>
            <button class="btn btn-danger btn-sm" type="submit"
                    onclick="return confirm('Sell these scrolls for coins?')">
              Sell 🪙<?= 1 ?>/ea
            </button>
          </form>
        </div>
        <?php endforeach; ?>
      </div>
      <?php else: ?>
        <p style="color:var(--muted); font-style:italic;">
          No scrolls yet. Walk to collect them or convert flowers below.
        </p>
      <?php endif; ?>
    </div>
  </div>

  <!-- FLOWERS -->
  <div>
    <div class="card">
      <div class="card-title">
        🌸 Flowers (<?= $totalFlowers ?>/<?= MAX_FLOWERS ?>)
        <?php if ($totalFlowers >= MAX_FLOWERS): ?>
          <span style="color:var(--danger); font-size:.75rem;"> — FULL</span>
        <?php endif; ?>
      </div>
      <p style="font-size:.85rem; color:var(--muted); margin-bottom:1rem;">
        Gathered from every tile you land on.
        Convert <strong style="color:var(--gold)">15 flowers</strong> of one colour
        into 1 scroll of the same colour.
        Max <?= MAX_FLOWERS ?> flowers total.
      </p>

      <?php if ($userFlowers): ?>
      <div style="display:flex; flex-direction:column; gap:.5rem;">
        <?php foreach ($userFlowers as $f):
          if ($f['quantity'] <= 0) continue;
          $canConvert  = $f['quantity'] >= FLOWERS_PER_BOOK && $totalScrolls < MAX_BOOKS;
          $scrollsFull   = $totalScrolls >= MAX_BOOKS;
        ?>
        <div style="padding:.5rem .6rem; background:var(--surface);
             border-radius:var(--radius); border:1px solid var(--border);">
          <div style="display:flex; align-items:center; gap:.6rem; margin-bottom:.4rem;">
            <div style="width:1rem; height:1rem; border-radius:50%;
                 background:<?= htmlspecialchars($f['hex_base']) ?>; flex-shrink:0;"></div>
            <span style="flex:1; font-size:.88rem; color:var(--text);">
              <?= htmlspecialchars($f['colour_name']) ?>
            </span>
            <span style="font-family:'Cinzel',serif; color:var(--gold);">
              ×<?= $f['quantity'] ?>
            </span>
            <span style="font-size:.72rem; color:var(--muted);">
              → Shade <?= min(10, $f['quantity']) ?>
            </span>
          </div>

          <!-- FLOWER PROGRESS BAR -->
          <?php $flowerPct = min(100, round(($f['quantity'] / FLOWERS_PER_BOOK) * 100)); ?>
          <div style="background:var(--border); border-radius:10px; height:4px;
               overflow:hidden; margin-bottom:.4rem;">
            <div style="background:<?= htmlspecialchars($f['hex_base']) ?>;
                 width:<?= $flowerPct ?>%; height:100%;"></div>
          </div>
          <div style="font-size:.68rem; color:var(--muted); margin-bottom:.4rem;">
            <?= $f['quantity'] ?>/<?= FLOWERS_PER_BOOK ?> for next scroll conversion
            <?php if ($f['quantity'] >= FLOWERS_PER_BOOK): ?>
              <span style="color:var(--success);">✓ Ready!</span>
            <?php endif; ?>
          </div>

          <!-- CONVERT FORM -->
          <?php if ($canConvert): ?>
          <form method="post"
                action="wardrobe.php?tab=scrolls&action=flowers_to_scroll">
            <input type="hidden" name="colour_id" value="<?= $f['colour_id'] ?>">
            <button class="btn btn-outline btn-sm" type="submit">
              🌸×15 → 📚×1
            </button>
          </form>
          <?php elseif ($scrollsFull): ?>
            <div style="font-size:.72rem; color:var(--danger);">
              Book bag full — sell scrolls first
            </div>
          <?php else: ?>
            <div style="font-size:.72rem; color:var(--muted);">
              Need <?= max(0, FLOWERS_PER_BOOK - $f['quantity']) ?> more flowers
            </div>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
      <?php else: ?>
        <p style="color:var(--muted); font-style:italic;">
          No flowers yet. Walk to gather them!
        </p>
      <?php endif; ?>
    </div>
  </div>

</div>


<?php elseif ($tab === 'bowties'): ?>
<!-- ══════════════════════════════════════════════════════════════════════════ -->
<!-- BOWTIES TAB                                                               -->
<!-- ══════════════════════════════════════════════════════════════════════════ -->
<div class="grid-2">

  <div>
    <div class="card">
      <div class="card-title">🎀 Your Bowties (<?= $bowtieCount ?>/4)</div>
      <p style="font-size:.85rem; color:var(--muted); margin-bottom:1rem;">
        Max 4 bowties. Sell one for 🪙 10 coins — it returns to the world.
      </p>
      <?php if ($bowtieCount > 4): ?>
      <div class="alert alert-error">
        ⚠ Over the 4 bowtie limit! Sell one to continue.
      </div>
      <?php endif; ?>
      <?php if ($bowties): ?>
      <div style="display:flex; flex-direction:column; gap:.5rem;">
        <?php foreach ($bowties as $b): ?>
        <div style="display:flex; align-items:center; gap:.75rem; padding:.6rem .8rem;
             background:var(--surface); border-radius:var(--radius); border:1px solid var(--border);">
          <div style="font-size:1.5rem;">🎀</div>
          <div style="flex:1;">
            <div style="font-family:'Cinzel',serif; font-size:.88rem; color:var(--text);">
              <?= htmlspecialchars($b['name']) ?>
            </div>
            <?php if (!empty($b['weapon_description'])): ?>
            <div style="font-size:.75rem; color:var(--muted);">
              <?= htmlspecialchars($b['weapon_description']) ?>
            </div>
            <?php endif; ?>
          </div>
          <a class="btn btn-outline btn-sm"
             href="wardrobe.php?tab=bowties&action=sell_bowtie&weaponid=<?= $b['id'] ?>"
             onclick="return confirm('Sell this bowtie for 🪙 10 coins?')">
            Sell 🪙10
          </a>
        </div>
        <?php endforeach; ?>
      </div>
      <?php else: ?>
        <p style="color:var(--muted); font-style:italic;">
          No bowties. Walk and land on a magic ground tile (x=y) to find one!
        </p>
      <?php endif; ?>
    </div>

    <div class="card">
      <div class="card-title">🪙 Coins</div>
      <div style="text-align:center; padding:1rem;">
        <div style="font-family:'Cinzel',serif; font-size:3rem; color:var(--gold); font-weight:700;">
          <?= $coins ?>
        </div>
        <div style="font-size:.75rem; font-family:'Cinzel',serif; letter-spacing:.1em; color:var(--muted);">
          COINS AVAILABLE
        </div>
        <div style="font-size:.82rem; color:var(--muted); margin-top:.5rem;">
          Sell bowties (🪙10) or scrolls (🪙1) · Spend in Shop
        </div>
      </div>
    </div>
  </div>

  <!-- BOWTIE MARKET -->
  <div class="card">
    <div class="card-title">🌍 Bowtie Market</div>
    <p style="font-size:.85rem; color:var(--muted); margin-bottom:1rem;">
      Sold bowties return here and can be found again on ✨ magic ground tiles.
    </p>
    <?php if ($market): ?>
    <div class="tbl-wrap">
      <table>
        <thead><tr><th>Bowtie</th><th>Sold By</th><th>When</th></tr></thead>
        <tbody>
        <?php foreach ($market as $m): ?>
          <tr>
            <td>🎀 <?= htmlspecialchars($m['weapon_name']) ?></td>
            <td style="color:var(--muted); font-size:.82rem;">
              <?= htmlspecialchars($m['sold_by']) ?>
            </td>
            <td style="color:var(--muted); font-size:.75rem;"><?= $m['sold_at'] ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php else: ?>
      <p style="color:var(--muted); font-style:italic;">No bowties in the market yet.</p>
    <?php endif; ?>
  </div>

</div>
<?php endif; ?>

<?php pageFooter(); ?>