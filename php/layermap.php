<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require 'admin_check.php';
require 'db.php';
require 'layout.php';

$db = getDB();

// Update tile layer assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tile_id'])) {
    $tileId = (int)$_POST['tile_id'];
    $layer  = $_POST['world_layer'];
    $db->prepare("UPDATE map_tiles SET world_layer=? WHERE id=?")
       ->execute([$layer, $tileId]);
    redirect('layermap.php', "Tile updated to {$layer}.");
}

// Update tile type allowed layers
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['type_id'])) {
    $typeId  = (int)$_POST['type_id'];
    $allowed = implode(',', $_POST['allowed_layers'] ?? ['surface']);
    $db->prepare("UPDATE tile_types SET allowed_layers=? WHERE id=?")
       ->execute([$allowed, $typeId]);
    redirect('layermap.php?tab=types', "Tile type updated.");
}

// Randomize respecting layer rules
if (isset($_GET['randomize'])) {
    $tiles = $db->query("SELECT id, world_layer FROM map_tiles")->fetchAll();
    foreach ($tiles as $tile) {
        $layer = $tile['world_layer'];
        // Get tile types allowed on this layer
        $allowed = $db->query("SELECT id FROM tile_types
            WHERE FIND_IN_SET('$layer', allowed_layers) > 0
            ORDER BY RAND() LIMIT 1")->fetchColumn();
        if ($allowed) {
            $db->prepare("UPDATE map_tiles SET tile_type_id=? WHERE id=?")
               ->execute([$allowed, $tile['id']]);
        }
    }
    redirect('layermap.php', "Map randomized respecting layer rules.");
}

$tab       = $_GET['tab'] ?? 'tiles';
$tileTypes = $db->query("SELECT * FROM tile_types ORDER BY name")->fetchAll();

// Tiles grouped by layer
$layers = ['sky', 'surface', 'underworld'];
$tiles  = $db->query("SELECT m.*, t.name as type_name, t.icon, t.color
    FROM map_tiles m JOIN tile_types t ON t.id=m.tile_type_id
    ORDER BY m.world_layer, m.coord_y, m.coord_x")->fetchAll();
$tilesByLayer = ['sky'=>[], 'surface'=>[], 'underworld'=>[]];
foreach ($tiles as $t) $tilesByLayer[$t['world_layer']][] = $t;

$layerColors = ['sky'=>'var(--ice)','surface'=>'var(--ground)','underworld'=>'var(--fire)'];
$layerIcons  = ['sky'=>'🌤','surface'=>'🌍','underworld'=>'🔥'];

pageHeader('Layer Map Admin', 'layermap.php');
echo flash();
?>

<h1 class="page-title">🌐 Layer Map</h1>
<p class="page-sub">Manage which tiles appear on each world layer</p>

<div style="display:flex; gap:.75rem; margin-bottom:1.5rem; flex-wrap:wrap;">
  <a href="layermap.php?tab=tiles" class="btn <?= $tab==='tiles'?'btn-primary':'btn-outline' ?>">
    🗺 Tile Assignments
  </a>
  <a href="layermap.php?tab=types" class="btn <?= $tab==='types'?'btn-primary':'btn-outline' ?>">
    🎨 Tile Type Rules
  </a>
  <a href="layermap.php?randomize=1" class="btn btn-outline"
     onclick="return confirm('Randomize all tiles respecting layer rules?')">
    🔀 Layer-Aware Randomize
  </a>
</div>

<?php if ($tab === 'tiles'): ?>

<?php foreach ($layers as $layer): ?>
<h2 style="font-family:'Cinzel',serif; font-size:1rem; color:<?= $layerColors[$layer] ?>;
    margin-bottom:.75rem;">
  <?= $layerIcons[$layer] ?> <?= ucfirst($layer) ?>
  (<?= count($tilesByLayer[$layer]) ?> tiles)
</h2>
<div class="card" style="margin-bottom:1.5rem;">
  <div class="tbl-wrap">
    <table>
      <thead>
        <tr><th>Coords</th><th>Current Type</th><th>Change Layer</th><th>Change Type</th></tr>
      </thead>
      <tbody>
      <?php foreach ($tilesByLayer[$layer] as $tile): ?>
      <tr>
        <td style="font-family:'Cinzel',serif; font-size:.78rem; color:var(--muted);">
          (<?= $tile['coord_x'] ?>,<?= $tile['coord_y'] ?>)
        </td>
        <td>
          <span style="background:<?= htmlspecialchars($tile['color']) ?>;
               padding:.15rem .4rem; border-radius:3px; font-size:.78rem;">
            <?= $tile['icon'] ?> <?= htmlspecialchars($tile['type_name']) ?>
          </span>
        </td>
        <td>
          <form method="post" style="display:flex; gap:.3rem;">
            <input type="hidden" name="tile_id" value="<?= $tile['id'] ?>">
            <select name="world_layer" style="font-size:.72rem; padding:.2rem .3rem;">
              <?php foreach ($layers as $l): ?>
              <option value="<?= $l ?>" <?= $l===$layer?'selected':'' ?>>
                <?= $layerIcons[$l] ?> <?= ucfirst($l) ?>
              </option>
              <?php endforeach; ?>
            </select>
            <button class="btn btn-outline btn-sm" type="submit"
                    style="font-size:.65rem; padding:.2rem .5rem;">Move</button>
          </form>
        </td>
        <td>
          <form method="post" style="display:flex; gap:.3rem;">
            <input type="hidden" name="tile_id" value="<?= $tile['id'] ?>">
            <select name="world_layer" style="display:none;">
              <option value="<?= $layer ?>" selected></option>
            </select>
            <select name="tile_type_id_change" style="font-size:.72rem; padding:.2rem .3rem;">
              <?php foreach ($tileTypes as $tt): ?>
              <option value="<?= $tt['id'] ?>"><?= $tt['icon'] ?> <?= htmlspecialchars($tt['name']) ?></option>
              <?php endforeach; ?>
            </select>
            <button class="btn btn-outline btn-sm" type="submit"
                    style="font-size:.65rem; padding:.2rem .5rem;">Set</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endforeach; ?>

<?php else: // types tab ?>

<div class="card">
  <div class="card-title">Tile Type Layer Rules</div>
  <p style="font-size:.85rem; color:var(--muted); margin-bottom:1rem;">
    Set which layers each tile type is allowed to appear on.
    The layer-aware randomizer uses these rules.
  </p>
  <div class="tbl-wrap">
    <table>
      <thead>
        <tr><th>Tile Type</th><th>Allowed Layers</th><th>Save</th></tr>
      </thead>
      <tbody>
      <?php foreach ($tileTypes as $tt):
        $allowed = explode(',', $tt['allowed_layers']);
      ?>
      <tr>
        <td>
          <span style="background:<?= htmlspecialchars($tt['color']) ?>;
               padding:.15rem .4rem; border-radius:3px; font-size:.82rem;">
            <?= $tt['icon'] ?> <?= htmlspecialchars($tt['name']) ?>
          </span>
        </td>
        <td>
          <form method="post" id="typeform<?= $tt['id'] ?>">
            <input type="hidden" name="type_id" value="<?= $tt['id'] ?>">
            <div style="display:flex; gap:.5rem; flex-wrap:wrap;">
              <?php foreach ($layers as $l): ?>
              <label style="display:flex; align-items:center; gap:.3rem; font-size:.8rem;
                     cursor:pointer; color:<?= $layerColors[$l] ?>;">
                <input type="checkbox" name="allowed_layers[]" value="<?= $l ?>"
                       <?= in_array($l, $allowed) ? 'checked' : '' ?>
                       style="accent-color:var(--gold);">
                <?= $layerIcons[$l] ?> <?= ucfirst($l) ?>
              </label>
              <?php endforeach; ?>
            </div>
        </td>
        <td>
            <button class="btn btn-outline btn-sm" type="submit"
                    style="font-size:.65rem;">Save</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php endif; ?>

<?php pageFooter(); ?>