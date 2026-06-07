<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require 'admin_check.php';
require 'db.php';
require 'layout.php';

$db  = getDB();
$act = $_GET['action'] ?? 'map';
$id  = (int)($_GET['id'] ?? 0);

$tileTypes = $db->query("SELECT * FROM tile_types ORDER BY name")->fetchAll();
$typeMap   = [];
foreach ($tileTypes as $t) $typeMap[$t['id']] = $t;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tile_type_id  = (int)$_POST['tile_type_id'];
    $coord_x       = (int)$_POST['coord_x'];
    $coord_y       = (int)$_POST['coord_y'];
    $dice          = $_POST['dice_number'] !== '' ? (int)$_POST['dice_number'] : null;
    $has_port      = isset($_POST['has_port']) ? 1 : 0;
    $port_resource = trim($_POST['port_resource'] ?? '') ?: null;
    $notes         = trim($_POST['notes'] ?? '') ?: null;

    if ($act === 'create') {
        try {
            $db->prepare("INSERT INTO map_tiles (tile_type_id,coord_x,coord_y,dice_number,has_port,port_resource,notes)
                          VALUES (?,?,?,?,?,?,?)")
               ->execute([$tile_type_id,$coord_x,$coord_y,$dice,$has_port,$port_resource,$notes]);
            redirect('map.php', "Tile placed at ({$coord_x},{$coord_y}).");
        } catch (PDOException $e) {
            $error = $e->getCode() === '23000' ? "A tile already exists at ({$coord_x},{$coord_y})." : $e->getMessage();
        }
    }
    if ($act === 'edit') {
        try {
            $db->prepare("UPDATE map_tiles SET tile_type_id=?,coord_x=?,coord_y=?,dice_number=?,has_port=?,port_resource=?,notes=? WHERE id=?")
               ->execute([$tile_type_id,$coord_x,$coord_y,$dice,$has_port,$port_resource,$notes,$id]);
            redirect('map.php', "Tile updated.");
        } catch (PDOException $e) {
            $error = $e->getCode() === '23000' ? "A tile already exists at ({$coord_x},{$coord_y})." : $e->getMessage();
        }
    }
}

if ($act === 'delete' && $id) {
    $db->prepare("DELETE FROM map_tiles WHERE id=?")->execute([$id]);
    redirect('map.php', "Tile removed.", 'error');
}

pageHeader('Map', 'map.php');
echo flash();
if (!empty($error)) echo '<div class="alert alert-error">' . htmlspecialchars($error) . '</div>';

function tileForm(array $tile = [], array $tileTypes = [], string $action = 'create', int $id = 0): void { ?>
    <div class="card">
      <form method="post" action="map.php?action=<?= $action ?>&id=<?= $id ?>">
        <div class="form-grid">
          <div class="form-group">
            <label>Terrain Type</label>
            <select name="tile_type_id">
              <?php foreach ($tileTypes as $t): ?>
                <option value="<?= $t['id'] ?>" <?= ($tile['tile_type_id'] ?? 0) == $t['id'] ? 'selected' : '' ?>>
                  <?= $t['icon'] ?> <?= htmlspecialchars($t['name']) ?>
                  <?= $t['resource'] ? '(' . $t['resource'] . ')' : '(No resource)' ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Dice Number (2–12, blank for desert)</label>
            <input type="number" name="dice_number" min="2" max="12"
                   value="<?= $tile['dice_number'] ?? '' ?>" placeholder="blank = no number">
          </div>
          <div class="form-group">
            <label>X Coordinate</label>
            <input type="number" name="coord_x" value="<?= $tile['coord_x'] ?? 0 ?>" required>
          </div>
          <div class="form-group">
            <label>Y Coordinate</label>
            <input type="number" name="coord_y" value="<?= $tile['coord_y'] ?? 0 ?>" required>
          </div>
          <div class="form-group full">
            <label>Notes</label>
            <input type="text" name="notes" value="<?= htmlspecialchars($tile['notes'] ?? '') ?>">
          </div>
        </div>
        <div style="margin-top:1rem;">
          <div class="checkbox-row">
            <input type="checkbox" id="has_port" name="has_port" value="1" <?= !empty($tile['has_port']) ? 'checked' : '' ?>>
            <label for="has_port">⚓ Has Port</label>
          </div>
        </div>
        <div class="form-group" style="margin-top:1rem; max-width:300px;">
          <label>Port Resource (blank = any resource)</label>
          <input type="text" name="port_resource" value="<?= htmlspecialchars($tile['port_resource'] ?? '') ?>"
                 placeholder="Timber, Ore… or blank for 3:1">
        </div>
        <div class="btn-row">
          <button class="btn btn-primary" type="submit"><?= $action === 'create' ? '+ Place Tile' : 'Save Changes' ?></button>
          <a class="btn btn-outline" href="map.php">Cancel</a>
        </div>
      </form>
    </div>
<?php }

if ($act === 'edit' && $id) {
    $tile = $db->prepare("SELECT * FROM map_tiles WHERE id=?");
    $tile->execute([$id]); $tile = $tile->fetch();
    if (!$tile) redirect('map.php', 'Tile not found.', 'error');
    echo '<h1 class="page-title">Edit Tile</h1><p class="page-sub">Modify tile at (' . $tile['coord_x'] . ',' . $tile['coord_y'] . ')</p>';
    tileForm($tile, $tileTypes, 'edit', $id);
    pageFooter(); exit;
}

if ($act === 'create') {
    $cx = (int)($_GET['x'] ?? 0);
    $cy = (int)($_GET['y'] ?? 0);
    echo '<h1 class="page-title">Place Tile</h1><p class="page-sub">Add a new tile to the map</p>';
    tileForm(['coord_x'=>$cx,'coord_y'=>$cy], $tileTypes, 'create');
    pageFooter(); exit;
}

// ── MAP VIEW ──────────────────────────────────────────────────────────────────
$tiles = $db->query("SELECT m.*, t.name as type_name, t.icon, t.color, t.resource
    FROM map_tiles m JOIN tile_types t ON t.id=m.tile_type_id")->fetchAll();

// Build coordinate lookup
$tileGrid = [];
$minX = $minY = PHP_INT_MAX;
$maxX = $maxY = PHP_INT_MIN;
foreach ($tiles as $tile) {
    $tileGrid[$tile['coord_x']][$tile['coord_y']] = $tile;
    $minX = min($minX, $tile['coord_x']);
    $maxX = max($maxX, $tile['coord_x']);
    $minY = min($minY, $tile['coord_y']);
    $maxY = max($maxY, $tile['coord_y']);
}
// Default grid range if no tiles yet
if (!$tiles) { $minX=$minY=0; $maxX=$maxY=6; }
$minX--; $minY--; $maxX++; $maxY++;
?>

<h1 class="page-title">Map</h1>
<p class="page-sub">Click any empty cell to place a tile &mdash; click a tile to edit it</p>

<div style="display:flex; gap:1rem; margin-bottom:1.5rem; flex-wrap:wrap; align-items:center;">
  <a class="btn btn-primary" href="map.php?action=create">+ Place Tile</a>
  <a class="btn btn-outline" href="map.php?action=list">📋 List View</a>
  <!-- LEGEND -->
  <div style="display:flex; gap:.5rem; flex-wrap:wrap; margin-left:auto;">
    <?php foreach ($tileTypes as $t): ?>
    <div style="display:flex; align-items:center; gap:.3rem; font-size:.78rem; color:var(--muted);">
      <span style="display:inline-block; width:.8rem; height:.8rem; border-radius:2px; background:<?= htmlspecialchars($t['color']) ?>"></span>
      <?= $t['icon'] ?> <?= htmlspecialchars($t['name']) ?>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- HEX-STYLE GRID MAP -->
<div class="card" style="overflow-x:auto; padding:1rem;">
  <div style="display:inline-block; min-width:100%;">
    <?php for ($y = $minY; $y <= $maxY; $y++): ?>
    <div style="display:flex; gap:4px; margin-bottom:4px;
         margin-left:<?= ($y % 2 !== 0) ? '38px' : '0' ?>;">
      <?php for ($x = $minX; $x <= $maxX; $x++):
        $tile = $tileGrid[$x][$y] ?? null;
        $size = 72;
      ?>
      <?php if ($tile): ?>
        <!-- PLACED TILE -->
        <a href="map.php?action=edit&id=<?= $tile['id'] ?>"
           title="<?= htmlspecialchars($tile['type_name']) ?> (<?= $x ?>,<?= $y ?>)<?= $tile['dice_number'] ? ' — Roll: '.$tile['dice_number'] : '' ?><?= $tile['has_port'] ? ' ⚓' : '' ?>"
           style="text-decoration:none; display:flex; flex-direction:column; align-items:center; justify-content:center;
                  width:<?= $size ?>px; height:<?= $size ?>px; flex-shrink:0;
                  background:<?= htmlspecialchars($tile['color']) ?>;
                  border-radius:8px; border:2px solid rgba(255,255,255,.15);
                  transition:all .15s; position:relative;"
           onmouseenter="this.style.borderColor='var(--gold)'; this.style.transform='scale(1.05)'"
           onmouseleave="this.style.borderColor='rgba(255,255,255,.15)'; this.style.transform='scale(1)'">
          <div style="font-size:1.4rem; line-height:1;"><?= $tile['icon'] ?></div>
          <?php if ($tile['dice_number']): ?>
            <div style="font-family:'Cinzel',serif; font-size:.65rem; font-weight:600;
                 background:rgba(0,0,0,.5); border-radius:10px; padding:.05rem .35rem;
                 color:<?= in_array($tile['dice_number'],[6,8]) ? '#e8622a' : 'white' ?>; margin-top:.15rem;">
              <?= $tile['dice_number'] ?>
            </div>
          <?php endif; ?>
          <?php if ($tile['has_port']): ?>
            <div style="position:absolute; top:2px; right:4px; font-size:.65rem;">⚓</div>
          <?php endif; ?>
          <div style="font-size:.5rem; color:rgba(255,255,255,.6); margin-top:.1rem;"><?= $x ?>,<?= $y ?></div>
          <!-- DELETE -->
          <a href="map.php?action=delete&id=<?= $tile['id'] ?>"
             onclick="event.stopPropagation(); return confirm('Remove this tile?')"
             style="position:absolute; top:2px; left:3px; font-size:.6rem; color:rgba(255,255,255,.5);
                    text-decoration:none; line-height:1;"
             title="Remove tile">✕</a>
        </a>
      <?php else: ?>
        <!-- EMPTY CELL -->
        <a href="map.php?action=create&x=<?= $x ?>&y=<?= $y ?>"
           title="Place tile at (<?= $x ?>,<?= $y ?>)"
           style="text-decoration:none; display:flex; align-items:center; justify-content:center;
                  width:<?= $size ?>px; height:<?= $size ?>px; flex-shrink:0;
                  background:var(--surface); border-radius:8px;
                  border:2px dashed var(--border); color:var(--border);
                  font-size:1.2rem; transition:all .15s;"
           onmouseenter="this.style.borderColor='var(--gold-dim)'; this.style.color='var(--gold-dim)'"
           onmouseleave="this.style.borderColor='var(--border)'; this.style.color='var(--border)'">
          +
        </a>
      <?php endif; ?>
      <?php endfor; ?>
    </div>
    <?php endfor; ?>
  </div>
</div>

<!-- LIST VIEW -->
<?php if ($act === 'list'): ?>
<h2 class="page-title" style="font-size:1.1rem; margin:1.5rem 0 1rem;">All Placed Tiles</h2>
<div class="card">
  <div class="tbl-wrap">
    <table>
      <thead>
        <tr><th>Coords</th><th>Terrain</th><th>Resource</th><th>Dice</th><th>Port</th><th>Notes</th><th>Actions</th></tr>
      </thead>
      <tbody>
      <?php
      $allTiles = $db->query("SELECT m.*, t.name as type_name, t.icon, t.color, t.resource
          FROM map_tiles m JOIN tile_types t ON t.id=m.tile_type_id
          ORDER BY m.coord_y, m.coord_x")->fetchAll();
      foreach ($allTiles as $tile): ?>
        <tr>
          <td style="font-family:'Cinzel',serif; font-size:.8rem; color:var(--muted);">(<?= $tile['coord_x'] ?>, <?= $tile['coord_y'] ?>)</td>
          <td>
            <span style="display:inline-flex; align-items:center; gap:.4rem;">
              <span style="display:inline-block; width:.7rem; height:.7rem; border-radius:2px; background:<?= htmlspecialchars($tile['color']) ?>"></span>
              <?= $tile['icon'] ?> <?= htmlspecialchars($tile['type_name']) ?>
            </span>
          </td>
          <td><?= $tile['resource'] ? htmlspecialchars($tile['resource']) : '<span style="color:var(--muted)">—</span>' ?></td>
          <td>
            <?php if ($tile['dice_number']): ?>
              <span style="font-family:'Cinzel',serif; color:<?= in_array($tile['dice_number'],[6,8]) ? 'var(--fire)' : 'var(--text)' ?>">
                <?= $tile['dice_number'] ?>
              </span>
            <?php else: echo '<span style="color:var(--muted)">—</span>'; endif; ?>
          </td>
          <td><?= $tile['has_port'] ? '⚓ ' . htmlspecialchars($tile['port_resource'] ?? '3:1') : '<span style="color:var(--muted)">—</span>' ?></td>
          <td style="color:var(--muted); font-size:.85rem"><?= htmlspecialchars($tile['notes'] ?? '') ?></td>
          <td>
            <a class="btn btn-outline btn-sm" href="map.php?action=edit&id=<?= $tile['id'] ?>">Edit</a>
            <a class="btn btn-danger btn-sm" href="map.php?action=delete&id=<?= $tile['id'] ?>"
               onclick="return confirm('Remove this tile?')">Remove</a>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$allTiles): ?>
        <tr><td colspan="7" style="text-align:center; color:var(--muted); font-style:italic;">No tiles placed yet</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php pageFooter(); ?> 