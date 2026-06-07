<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require 'admin_check.php';
require 'db.php';
require 'layout.php';

$db  = getDB();
$act = $_GET['action'] ?? 'list';
$id  = (int)($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = trim($_POST['name'] ?? '');
    $score   = (int)($_POST['score'] ?? 0);
    $desc    = trim($_POST['weapon_description'] ?? '');
    $charC   = isset($_POST['charCarry'])  ? 1 : 0;
    $userC   = isset($_POST['userCarry'])  ? 1 : 0;

    if ($act === 'create') {
        $s = $db->prepare("INSERT INTO weapons (name,score,weapon_description,charCarry,userCarry) VALUES (?,?,?,?,?)");
        $s->execute([$name, $score, $desc, $charC, $userC]);
        redirect('weapons.php', "Weapon '{$name}' added to armoury.");
    }
    if ($act === 'edit') {
        $s = $db->prepare("UPDATE weapons SET name=?,score=?,weapon_description=?,charCarry=?,userCarry=? WHERE id=?");
        $s->execute([$name, $score, $desc, $charC, $userC, $id]);
        redirect('weapons.php', "Weapon updated.");
    }
}

if ($act === 'delete' && $id) {
    $db->prepare("DELETE FROM weaponAttributes WHERE weaponid=?")->execute([$id]);
    $db->prepare("DELETE FROM charinventory WHERE weaponid=?")->execute([$id]);
    $db->prepare("DELETE FROM inventory WHERE weaponid=?")->execute([$id]);
    $db->prepare("DELETE FROM weapons WHERE id=?")->execute([$id]);
    redirect('weapons.php', "Weapon deleted.", 'error');
}

pageHeader('Weapons', 'weapons.php');
echo flash();

// ── FORM HELPER ───────────────────────────────────────────────────────────────
function weaponForm(array $w = [], string $action = 'create', int $id = 0): void { ?>
    <div class="card">
      <form method="post" action="weapons.php?action=<?= $action ?>&id=<?= $id ?>">
        <div class="form-grid">
          <div class="form-group">
            <label>Weapon Name</label>
            <input type="text" name="name" value="<?= htmlspecialchars($w['name'] ?? '') ?>" required>
          </div>
          <div class="form-group">
            <label>Score</label>
            <input type="number" name="score" value="<?= $w['score'] ?? 0 ?>">
          </div>
          <div class="form-group full">
            <label>Description</label>
            <input type="text" name="weapon_description" value="<?= htmlspecialchars($w['weapon_description'] ?? '') ?>">
          </div>
        </div>
        <div style="margin-top:1rem; display:flex; gap:2rem;">
          <div class="checkbox-row">
            <input type="checkbox" id="charCarry" name="charCarry" <?= !empty($w['charCarry']) ? 'checked' : '' ?>>
            <label for="charCarry">Character Can Carry</label>
          </div>
          <div class="checkbox-row">
            <input type="checkbox" id="userCarry" name="userCarry" <?= !empty($w['userCarry']) ? 'checked' : '' ?>>
            <label for="userCarry">User Can Carry</label>
          </div>
        </div>
        <div class="btn-row">
          <button class="btn btn-primary" type="submit"><?= $action === 'create' ? 'Add Weapon' : 'Save Changes' ?></button>
          <a class="btn btn-outline" href="weapons.php">Cancel</a>
        </div>
      </form>
    </div>
<?php }

if ($act === 'edit' && $id) {
    $w = $db->prepare("SELECT * FROM weapons WHERE id=?");
    $w->execute([$id]); $w = $w->fetch();
    if (!$w) redirect('weapons.php','Not found.','error');
    echo '<h1 class="page-title">Edit Weapon</h1><p class="page-sub">Modify weapon properties</p>';
    weaponForm($w, 'edit', $id);
    pageFooter(); exit;
}

if ($act === 'create') {
    echo '<h1 class="page-title">New Weapon</h1><p class="page-sub">Forge a new weapon</p>';
    weaponForm([], 'create');
    pageFooter(); exit;
}

// ── LIST ─────────────────────────────────────────────────────────────────────
$weapons = $db->query("
  SELECT w.*,
    (SELECT COUNT(*) FROM weaponAttributes WHERE weaponid=w.id) as skill_count
  FROM weapons w ORDER BY w.id")->fetchAll();
?>
<h1 class="page-title">Weapons</h1>
<p class="page-sub">The full armoury</p>
<a class="btn btn-primary" href="weapons.php?action=create">+ Forge Weapon</a>
<div class="card" style="margin-top:1.25rem;">
  <div class="tbl-wrap">
  <table>
    <thead><tr><th>ID</th><th>Name</th><th>Score</th><th>Description</th><th>Char Carry</th><th>User Carry</th><th>Skills</th><th>Actions</th></tr></thead>
    <tbody>
    <?php foreach ($weapons as $w): ?>
    <tr>
      <td style="color:var(--muted)"><?= $w['id'] ?></td>
      <td><strong><?= htmlspecialchars($w['name']) ?></strong></td>
      <td><span style="color:var(--gold)"><?= $w['score'] ?></span></td>
      <td style="color:var(--muted); font-size:.9rem"><?= htmlspecialchars($w['weapon_description'] ?? '') ?></td>
      <td><?= $w['charCarry'] ? '✓' : '—' ?></td>
      <td><?= $w['userCarry'] ? '✓' : '—' ?></td>
      <td><?= $w['skill_count'] ?></td>
      <td>
        <a class="btn btn-outline btn-sm" href="weapons.php?action=edit&id=<?= $w['id'] ?>">Edit</a>
        <a class="btn btn-danger btn-sm" href="weapons.php?action=delete&id=<?= $w['id'] ?>"
           onclick="return confirm('Delete this weapon?')">Delete</a>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>
<?php pageFooter(); ?>
