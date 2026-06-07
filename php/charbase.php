<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require 'admin_check.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['userid'])) {
    header('Location: login.php');
    exit();
}
$SESSION_USERID = (int)$_SESSION['userid'];

require 'db.php';
require 'layout.php';


$db  = getDB();
$act = $_GET['action'] ?? 'list';
$id  = (int)($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $desc = trim($_POST['chardescription'] ?? '');

    if ($act === 'create') {
        $s = $db->prepare("INSERT INTO charbase (name,chardescription) VALUES (?,?)");
        $s->execute([$name, $desc]);
        redirect('charbase.php', "Character '{$name}' created.");
    }
    if ($act === 'edit') {
        $s = $db->prepare("UPDATE charbase SET name=?, chardescription=? WHERE id=?");
        $s->execute([$name, $desc, $id]);
        redirect('charbase.php', "Character updated.");
    }
}

if ($act === 'delete' && $id) {
    $db->prepare("DELETE FROM charAttributes WHERE charid=?")->execute([$id]);
    $db->prepare("DELETE FROM charinventory WHERE charid=?")->execute([$id]);
    $db->prepare("DELETE FROM charbase WHERE id=?")->execute([$id]);
    redirect('charbase.php', "Character deleted.", 'error');
}

pageHeader('Characters', 'charbase.php');
echo flash();

// ── EDIT ─────────────────────────────────────────────────────────────────────
if ($act === 'edit' && $id) {
    $char = $db->prepare("SELECT * FROM charbase WHERE id=?");
    $char->execute([$id]);
    $char = $char->fetch();
    if (!$char) redirect('charbase.php', 'Character not found.', 'error');
    ?>
    <h1 class="page-title">Edit Character</h1>
    <p class="page-sub">Update character details</p>
    <div class="card">
      <form method="post" action="charbase.php?action=edit&id=<?= $id ?>">
        <div class="form-grid">
          <div class="form-group">
            <label>Name</label>
            <input type="text" name="name" value="<?= htmlspecialchars($char['name']) ?>" required>
          </div>
          <div class="form-group">
            <label>Description</label>
            <input type="text" name="chardescription" value="<?= htmlspecialchars($char['chardescription']) ?>">
          </div>
        </div>
        <div class="btn-row">
          <button class="btn btn-primary" type="submit">Save</button>
          <a class="btn btn-outline" href="charbase.php">Cancel</a>
        </div>
      </form>
    </div>
    <?php pageFooter(); exit;
}

// ── CREATE ────────────────────────────────────────────────────────────────────
if ($act === 'create') { ?>
    <h1 class="page-title">New Character</h1>
    <p class="page-sub">Add a character to the world</p>
    <div class="card">
      <form method="post" action="charbase.php?action=create">
        <div class="form-grid">
          <div class="form-group">
            <label>Name</label>
            <input type="text" name="name" required>
          </div>
          <div class="form-group">
            <label>Description</label>
            <input type="text" name="chardescription">
          </div>
        </div>
        <div class="btn-row">
          <button class="btn btn-primary" type="submit">Create Character</button>
          <a class="btn btn-outline" href="charbase.php">Cancel</a>
        </div>
      </form>
    </div>
    <?php pageFooter(); exit;
}

// ── LIST ─────────────────────────────────────────────────────────────────────
$chars = $db->query("
  SELECT c.*,
    (SELECT COUNT(*) FROM charinventory WHERE charid=c.id) as weapon_count,
    (SELECT COUNT(*) FROM charAttributes WHERE charid=c.id) as skill_count
  FROM charbase c ORDER BY c.id")->fetchAll();
?>
<h1 class="page-title">Characters</h1>
<p class="page-sub">All characters in the Stailian universe</p>
<a class="btn btn-primary" href="charbase.php?action=create">+ Add Character</a>
<div class="card" style="margin-top:1.25rem;">
  <div class="tbl-wrap">
  <table>
    <thead><tr><th>ID</th><th>Name</th><th>Description</th><th>Weapons</th><th>Skills</th><th>Created</th><th>Actions</th></tr></thead>
    <tbody>
    <?php foreach ($chars as $c): ?>
    <tr>
      <td style="color:var(--muted)"><?= $c['id'] ?></td>
      <td><strong><?= htmlspecialchars($c['name']) ?></strong></td>
      <td style="font-style:italic; color:var(--muted)"><?= htmlspecialchars($c['chardescription']) ?></td>
      <td><?= $c['weapon_count'] ?></td>
      <td><?= $c['skill_count'] ?></td>
      <td style="color:var(--muted); font-size:.85rem"><?= $c['created_at'] ?></td>
      <td>
        <a class="btn btn-outline btn-sm" href="charbase.php?action=edit&id=<?= $c['id'] ?>">Edit</a>
        <a class="btn btn-danger btn-sm" href="charbase.php?action=delete&id=<?= $c['id'] ?>"
           onclick="return confirm('Delete this character and all their data?')">Delete</a>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>
<?php pageFooter(); ?>
