<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require 'admin_check.php';
require 'db.php';
require 'layout.php';

$db  = getDB();
$act = $_GET['action'] ?? 'list';
$id  = (int)($_GET['id'] ?? 0);
$tab = $_GET['tab'] ?? 'user';

// ── HANDLE POSTS ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tab = $_POST['tab'] ?? 'user';

    if ($tab === 'user') {
        $userid   = (int)$_POST['userid'];
        $weaponid = (int)$_POST['weaponid'];

        // Validate userCarry
        $check = $db->prepare("SELECT userCarry FROM weapons WHERE id=?");
        $check->execute([$weaponid]);
        $weapon = $check->fetch();
        if (!$weapon || !$weapon['userCarry']) {
            redirect("inventory.php?tab=user", "This weapon cannot be carried by users.", 'error');
        }

        if ($act === 'create') {
            $db->prepare("INSERT INTO inventory (userid,weaponid) VALUES (?,?)")->execute([$userid,$weaponid]);
            redirect("inventory.php?tab=user", "Weapon added to user inventory.");
        }
        if ($act === 'edit') {
            $db->prepare("UPDATE inventory SET userid=?,weaponid=? WHERE id=?")->execute([$userid,$weaponid,$id]);
            redirect("inventory.php?tab=user", "Inventory entry updated.");
        }
    } else {
        $charid   = (int)$_POST['charid'];
        $weaponid = (int)$_POST['weaponid'];

        // Validate charCarry
        $check = $db->prepare("SELECT charCarry FROM weapons WHERE id=?");
        $check->execute([$weaponid]);
        $weapon = $check->fetch();
        if (!$weapon || !$weapon['charCarry']) {
            redirect("inventory.php?tab=char", "This weapon cannot be carried by characters.", 'error');
        }

        if ($act === 'create') {
            $db->prepare("INSERT INTO charinventory (charid,weaponid) VALUES (?,?)")->execute([$charid,$weaponid]);
            redirect("inventory.php?tab=char", "Weapon assigned to character.");
        }
        if ($act === 'edit') {
            $db->prepare("UPDATE charinventory SET charid=?,weaponid=? WHERE id=?")->execute([$charid,$weaponid,$id]);
            redirect("inventory.php?tab=char", "Character inventory updated.");
        }
    }
}

if ($act === 'delete' && $id) {
    if ($tab === 'user') {
        $db->prepare("DELETE FROM inventory WHERE id=?")->execute([$id]);
        redirect("inventory.php?tab=user", "Removed from inventory.", 'error');
    } else {
        $db->prepare("DELETE FROM charinventory WHERE id=?")->execute([$id]);
        redirect("inventory.php?tab=char", "Removed from character inventory.", 'error');
    }
}

// Helper data — filtered by carry flags
$users        = $db->query("SELECT id,name FROM users ORDER BY name")->fetchAll();
$chars        = $db->query("SELECT id,name FROM charbase ORDER BY name")->fetchAll();
$userWeapons  = $db->query("SELECT id,name FROM weapons WHERE userCarry=1 ORDER BY name")->fetchAll();
$charWeapons  = $db->query("SELECT id,name FROM weapons WHERE charCarry=1 ORDER BY name")->fetchAll();
$allWeapons   = $db->query("SELECT id,name,userCarry,charCarry FROM weapons ORDER BY name")->fetchAll();

pageHeader('Inventory', 'inventory.php');
echo flash();

// ── EDIT ─────────────────────────────────────────────────────────────────────
if ($act === 'edit' && $id) {
    if ($tab === 'user') {
        $row = $db->prepare("SELECT * FROM inventory WHERE id=?");
        $row->execute([$id]); $row = $row->fetch();
        echo '<h1 class="page-title">Edit User Inventory</h1>';
        echo '<p class="page-sub">Only weapons with User Carry enabled are shown</p>';
        echo '<div class="card"><form method="post" action="inventory.php?action=edit&id='.$id.'&tab=user">';
        echo '<input type="hidden" name="tab" value="user">';
        echo '<div class="form-grid">';
        echo '<div class="form-group"><label>User</label><select name="userid">';
        foreach ($users as $u) echo '<option value="'.$u['id'].'"'.($row['userid']==$u['id']?' selected':'').'>'.htmlspecialchars($u['name']).'</option>';
        echo '</select></div>';
        echo '<div class="form-group"><label>Weapon</label><select name="weaponid">';
        foreach ($userWeapons as $w) echo '<option value="'.$w['id'].'"'.($row['weaponid']==$w['id']?' selected':'').'>'.htmlspecialchars($w['name']).'</option>';
        echo '</select></div></div>';
        echo '<div class="btn-row"><button class="btn btn-primary" type="submit">Save</button><a class="btn btn-outline" href="inventory.php?tab=user">Cancel</a></div>';
        echo '</form></div>';
    } else {
        $row = $db->prepare("SELECT * FROM charinventory WHERE id=?");
        $row->execute([$id]); $row = $row->fetch();
        echo '<h1 class="page-title">Edit Character Inventory</h1>';
        echo '<p class="page-sub">Only weapons with Char Carry enabled are shown</p>';
        echo '<div class="card"><form method="post" action="inventory.php?action=edit&id='.$id.'&tab=char">';
        echo '<input type="hidden" name="tab" value="char">';
        echo '<div class="form-grid">';
        echo '<div class="form-group"><label>Character</label><select name="charid">';
        foreach ($chars as $c) echo '<option value="'.$c['id'].'"'.($row['charid']==$c['id']?' selected':'').'>'.htmlspecialchars($c['name']).'</option>';
        echo '</select></div>';
        echo '<div class="form-group"><label>Weapon</label><select name="weaponid">';
        foreach ($charWeapons as $w) echo '<option value="'.$w['id'].'"'.($row['weaponid']==$w['id']?' selected':'').'>'.htmlspecialchars($w['name']).'</option>';
        echo '</select></div></div>';
        echo '<div class="btn-row"><button class="btn btn-primary" type="submit">Save</button><a class="btn btn-outline" href="inventory.php?tab=char">Cancel</a></div>';
        echo '</form></div>';
    }
    pageFooter(); exit;
}

// ── CREATE ────────────────────────────────────────────────────────────────────
if ($act === 'create') {
    echo '<h1 class="page-title">Add to Inventory</h1>';
    echo '<p class="page-sub">Only eligible weapons are shown per carrier type</p>';
    ?>
    <div class="grid-2">
      <!-- User Inventory -->
      <div class="card">
        <div class="card-title">👤 Assign to User</div>
        <?php if ($userWeapons): ?>
        <form method="post" action="inventory.php?action=create&tab=user">
          <input type="hidden" name="tab" value="user">
          <div class="form-group" style="margin-bottom:1rem">
            <label>User</label>
            <select name="userid">
              <?php foreach ($users as $u): ?>
                <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Weapon <span style="color:var(--muted); font-size:.8rem">(User Carry only)</span></label>
            <select name="weaponid">
              <?php foreach ($userWeapons as $w): ?>
                <option value="<?= $w['id'] ?>"><?= htmlspecialchars($w['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="btn-row"><button class="btn btn-primary" type="submit">Assign</button></div>
        </form>
        <?php else: ?>
          <p style="color:var(--muted); font-style:italic;">No weapons with User Carry enabled. 
            <a href="weapons.php" style="color:var(--gold)">Go to Weapons</a> to enable carry flags.
          </p>
        <?php endif; ?>
      </div>

      <!-- Char Inventory -->
      <div class="card">
        <div class="card-title">🧙 Assign to Character</div>
        <?php if ($charWeapons): ?>
        <form method="post" action="inventory.php?action=create&tab=char">
          <input type="hidden" name="tab" value="char">
          <div class="form-group" style="margin-bottom:1rem">
            <label>Character</label>
            <select name="charid">
              <?php foreach ($chars as $c): ?>
                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Weapon <span style="color:var(--muted); font-size:.8rem">(Char Carry only)</span></label>
            <select name="weaponid">
              <?php foreach ($charWeapons as $w): ?>
                <option value="<?= $w['id'] ?>"><?= htmlspecialchars($w['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="btn-row"><button class="btn btn-primary" type="submit">Assign</button></div>
        </form>
        <?php else: ?>
          <p style="color:var(--muted); font-style:italic;">No weapons with Char Carry enabled.
            <a href="weapons.php" style="color:var(--gold)">Go to Weapons</a> to enable carry flags.
          </p>
        <?php endif; ?>
      </div>
    </div>
    <a class="btn btn-outline" href="inventory.php">← Back to Inventory</a>
    <?php pageFooter(); exit;
}

// ── LIST ─────────────────────────────────────────────────────────────────────
$userInv = $db->query("SELECT i.id, u.name as uname, w.name as wname, w.userCarry
    FROM inventory i
    JOIN users u ON u.id=i.userid
    JOIN weapons w ON w.id=i.weaponid
    ORDER BY u.name, w.name")->fetchAll();

$charInv = $db->query("SELECT i.id, c.name as cname, w.name as wname, w.charCarry
    FROM charinventory i
    JOIN charbase c ON c.id=i.charid
    JOIN weapons w ON w.id=i.weaponid
    ORDER BY c.name, w.name")->fetchAll();
?>
<h1 class="page-title">Inventory</h1>
<p class="page-sub">Weapon assignments across users and characters</p>
<a class="btn btn-primary" href="inventory.php?action=create">+ Assign Weapon</a>

<!-- CARRY FLAG LEGEND -->
<div style="display:flex; gap:1rem; margin-top:1rem; flex-wrap:wrap;">
  <div style="display:flex; align-items:center; gap:.4rem; font-size:.85rem; color:var(--muted);">
    <span style="color:var(--success)">✓</span> Carry allowed
  </div>
  <div style="display:flex; align-items:center; gap:.4rem; font-size:.85rem; color:var(--muted);">
    <span style="color:var(--danger)">⚠</span> Carry flag mismatch — should be removed
  </div>
</div>

<!-- TABS -->
<div style="display:flex; gap:0; margin-top:1rem; border-bottom:1px solid var(--border);">
  <a href="inventory.php?tab=user" style="text-decoration:none; font-family:'Cinzel',serif; font-size:.75rem; letter-spacing:.08em;
     padding:.7rem 1.25rem; border-bottom:2px solid <?= $tab==='user' ? 'var(--gold)' : 'transparent' ?>;
     color:<?= $tab==='user' ? 'var(--gold)' : 'var(--muted)' ?>">👤 User Inventory (<?= count($userInv) ?>)</a>
  <a href="inventory.php?tab=char" style="text-decoration:none; font-family:'Cinzel',serif; font-size:.75rem; letter-spacing:.08em;
     padding:.7rem 1.25rem; border-bottom:2px solid <?= $tab==='char' ? 'var(--gold)' : 'transparent' ?>;
     color:<?= $tab==='char' ? 'var(--gold)' : 'var(--muted)' ?>">🧙 Character Inventory (<?= count($charInv) ?>)</a>
</div>

<?php if ($tab === 'user'): ?>
<div class="card" style="border-top:none; border-radius:0 0 var(--radius) var(--radius);">
  <div class="tbl-wrap">
    <table>
      <thead><tr><th>ID</th><th>User</th><th>Weapon</th><th>Carry Flag</th><th>Actions</th></tr></thead>
      <tbody>
      <?php foreach ($userInv as $r): ?>
        <tr>
          <td style="color:var(--muted)"><?= $r['id'] ?></td>
          <td><?= htmlspecialchars($r['uname']) ?></td>
          <td>🗡 <?= htmlspecialchars($r['wname']) ?></td>
          <td>
            <?php if ($r['userCarry']): ?>
              <span style="color:var(--success)">✓ Allowed</span>
            <?php else: ?>
              <span style="color:var(--danger)">⚠ Not allowed</span>
            <?php endif; ?>
          </td>
          <td>
            <a class="btn btn-outline btn-sm" href="inventory.php?action=edit&tab=user&id=<?= $r['id'] ?>">Edit</a>
            <a class="btn btn-danger btn-sm" href="inventory.php?action=delete&tab=user&id=<?= $r['id'] ?>"
               onclick="return confirm('Remove this weapon?')">Remove</a>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$userInv): ?>
        <tr><td colspan="5" style="text-align:center; color:var(--muted); font-style:italic">No weapons assigned to users</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php else: ?>
<div class="card" style="border-top:none; border-radius:0 0 var(--radius) var(--radius);">
  <div class="tbl-wrap">
    <table>
      <thead><tr><th>ID</th><th>Character</th><th>Weapon</th><th>Carry Flag</th><th>Actions</th></tr></thead>
      <tbody>
      <?php foreach ($charInv as $r): ?>
        <tr>
          <td style="color:var(--muted)"><?= $r['id'] ?></td>
          <td><?= htmlspecialchars($r['cname']) ?></td>
          <td>🗡 <?= htmlspecialchars($r['wname']) ?></td>
          <td>
            <?php if ($r['charCarry']): ?>
              <span style="color:var(--success)">✓ Allowed</span>
            <?php else: ?>
              <span style="color:var(--danger)">⚠ Not allowed</span>
            <?php endif; ?>
          </td>
          <td>
            <a class="btn btn-outline btn-sm" href="inventory.php?action=edit&tab=char&id=<?= $r['id'] ?>">Edit</a>
            <a class="btn btn-danger btn-sm" href="inventory.php?action=delete&tab=char&id=<?= $r['id'] ?>"
               onclick="return confirm('Remove this weapon?')">Remove</a>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$charInv): ?>
        <tr><td colspan="5" style="text-align:center; color:var(--muted); font-style:italic">No weapons assigned to characters</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php pageFooter(); ?>