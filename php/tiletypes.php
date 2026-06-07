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
    $name     = trim($_POST['name']        ?? '');
    $icon     = trim($_POST['icon']        ?? '');
    $color    = trim($_POST['color']       ?? '#cccccc');
    $resource = trim($_POST['resource']    ?? '');
    $desc     = trim($_POST['description'] ?? '');

    if ($act === 'create') {
        try {
            $db->prepare("INSERT INTO tile_types (name,icon,color,resource,description) VALUES (?,?,?,?,?)")
               ->execute([$name,$icon,$color,$resource ?: null,$desc ?: null]);
            redirect('tiletypes.php', "Tile type '{$name}' created.");
        } catch (PDOException $e) {
            $error = $e->getCode() === '23000' ? "A tile type named '{$name}' already exists." : $e->getMessage();
        }
    }
    if ($act === 'edit') {
        try {
            $db->prepare("UPDATE tile_types SET name=?,icon=?,color=?,resource=?,description=? WHERE id=?")
               ->execute([$name,$icon,$color,$resource ?: null,$desc ?: null,$id]);
            redirect('tiletypes.php', "Tile type updated.");
        } catch (PDOException $e) {
            $error = $e->getCode() === '23000' ? "A tile type named '{$name}' already exists." : $e->getMessage();
        }
    }
}

if ($act === 'delete' && $id) {
    $inUse = $db->prepare("SELECT COUNT(*) FROM map_tiles WHERE tile_type_id=?");
    $inUse->execute([$id]);
    if ($inUse->fetchColumn() > 0) {
        redirect('tiletypes.php', "Cannot delete — this tile type is used on the map.", 'error');
    }
    $db->prepare("DELETE FROM tile_types WHERE id=?")->execute([$id]);
    redirect('tiletypes.php', "Tile type deleted.", 'error');
}

pageHeader('Tile Types', 'tiletypes.php');
echo flash();
if (!empty($error)) echo '<div class="alert alert-error">' . htmlspecialchars($error) . '</div>';

function tileTypeForm(array $t = [], string $action = 'create', int $id = 0): void { ?>
    <div class="card">
      <form method="post" action="tiletypes.php?action=<?= $action ?>&id=<?= $id ?>">
        <div class="form-grid">
          <div class="form-group">
            <label>Tile Name</label>
            <input type="text" name="name" value="<?= htmlspecialchars($t['name'] ?? '') ?>" required>
          </div>
          <div class="form-group">
            <label>Icon (emoji)</label>
            <input type="text" name="icon" value="<?= htmlspecialchars($t['icon'] ?? '') ?>" placeholder="🌲">
          </div>
          <div class="form-group">
            <label>Color</label>
            <div style="display:flex; gap:.5rem; align-items:center;">
              <input type="color" name="color" value="<?= htmlspecialchars($t['color'] ?? '#cccccc') ?>"
                     style="width:3rem; height:2.4rem; padding:.1rem; cursor:pointer;">
              <input type="text" name="color_text" value="<?= htmlspecialchars($t['color'] ?? '#cccccc') ?>"
                     style="flex:1" readonly id="colorText<?= $id ?>">
            </div>
          </div>
          <div class="form-group">
            <label>Resource Produced</label>
            <input type="text" name="resource" value="<?= htmlspecialchars($t['resource'] ?? '') ?>" placeholder="Timber, Ore, Grain…">
          </div>
          <div class="form-group full">
            <label>Description</label>
            <input type="text" name="description" value="<?= htmlspecialchars($t['description'] ?? '') ?>">
          </div>
        </div>
        <div class="btn-row">
          <button class="btn btn-primary" type="submit"><?= $action === 'create' ? '+ Create Type' : 'Save Changes' ?></button>
          <a class="btn btn-outline" href="tiletypes.php">Cancel</a>
        </div>
      </form>
      <script>
        document.querySelector('input[type=color]').addEventListener('input', function() {
          document.getElementById('colorText<?= $id ?>').value = this.value;
        });
      </script>
    </div>
<?php }

if ($act === 'edit' && $id) {
    $t = $db->prepare("SELECT * FROM tile_types WHERE id=?");
    $t->execute([$id]); $t = $t->fetch();
    if (!$t) redirect('tiletypes.php', 'Not found.', 'error');
    echo '<h1 class="page-title">Edit Tile Type</h1><p class="page-sub">Modify tile properties</p>';
    tileTypeForm($t, 'edit', $id);
    pageFooter(); exit;
}
if ($act === 'create') {
    echo '<h1 class="page-title">New Tile Type</h1><p class="page-sub">Define a new map terrain type</p>';
    tileTypeForm([], 'create');
    pageFooter(); exit;
}

$types = $db->query("SELECT t.*, (SELECT COUNT(*) FROM map_tiles WHERE tile_type_id=t.id) as tile_count
    FROM tile_types t ORDER BY t.name")->fetchAll();
?>
<h1 class="page-title">Tile Types</h1>
<p class="page-sub">Terrain types available for map placement</p>
<a class="btn btn-primary" href="tiletypes.php?action=create">+ New Tile Type</a>

<div class="grid-3" style="margin-top:1.25rem;">
<?php foreach ($types as $t): ?>
  <div class="card" style="border-left:4px solid <?= htmlspecialchars($t['color']) ?>; margin-bottom:0;">
    <div style="display:flex; align-items:center; gap:.75rem; margin-bottom:.75rem;">
      <div style="font-size:2rem; width:2.5rem; height:2.5rem; background:<?= htmlspecialchars($t['color']) ?>;
           border-radius:50%; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
        <?= $t['icon'] ?>
      </div>
      <div>
        <div style="font-family:'Cinzel',serif; font-size:1rem; color:var(--text);"><?= htmlspecialchars($t['name']) ?></div>
        <?php if ($t['resource']): ?>
          <div style="font-size:.8rem; color:var(--gold);">⚙ <?= htmlspecialchars($t['resource']) ?></div>
        <?php else: ?>
          <div style="font-size:.8rem; color:var(--muted); font-style:italic;">No resource</div>
        <?php endif; ?>
      </div>
    </div>
    <p style="font-size:.85rem; color:var(--muted); font-style:italic; margin-bottom:.75rem;">
      <?= htmlspecialchars($t['description'] ?? '') ?>
    </p>
    <div style="display:flex; justify-content:space-between; align-items:center;">
      <span style="font-size:.8rem; color:var(--muted);"><?= $t['tile_count'] ?> on map</span>
      <div style="display:flex; gap:.4rem;">
        <a class="btn btn-outline btn-sm" href="tiletypes.php?action=edit&id=<?= $t['id'] ?>">Edit</a>
        <a class="btn btn-danger btn-sm" href="tiletypes.php?action=delete&id=<?= $t['id'] ?>"
           onclick="return confirm('Delete this tile type?')">Delete</a>
      </div>
    </div>
  </div>
<?php endforeach; ?>
</div>
<?php pageFooter(); ?>