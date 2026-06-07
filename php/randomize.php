<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require 'admin_check.php';
require 'db.php';
require 'layout.php';

$db = getDB();

// ── HANDLE RANDOMIZE POST ─────────────────────────────────────────────────────
$result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mode = $_POST['mode'] ?? 'full';

    $tileTypes     = $db->query("SELECT id, name, icon FROM tile_types ORDER BY name")->fetchAll();
    $existingTiles = $db->query("SELECT id, coord_x, coord_y FROM map_tiles ORDER BY id")->fetchAll();

    if (!$tileTypes) {
        $result = ['type'=>'error', 'msg'=>'No tile types defined. Go to Tile Types first.'];
    } elseif (!$existingTiles) {
        $result = ['type'=>'error', 'msg'=>'No tiles on the map yet. Go to Map to place tiles first.'];
    } else {
        $count       = count($existingTiles);
        $diceNumbers = [2,3,3,4,4,5,5,6,6,8,8,9,9,10,10,11,11,12];
        $changes     = [];

        if ($mode === 'full') {
            // Completely random — every tile gets any type
            shuffle($diceNumbers);
            $i = 0;
            foreach ($existingTiles as $tile) {
                $newType = $tileTypes[array_rand($tileTypes)];
                $dice    = $diceNumbers[$i % count($diceNumbers)];
                $i++;
                $db->prepare("UPDATE map_tiles SET tile_type_id=?, dice_number=? WHERE id=?")
                   ->execute([$newType['id'], $dice, $tile['id']]);
                $changes[] = [
                    'x'    => $tile['coord_x'],
                    'y'    => $tile['coord_y'],
                    'type' => $newType['name'],
                    'icon' => $newType['icon'],
                    'dice' => $dice,
                ];
            }
            $result = ['type'=>'success', 'msg'=>"Fully randomized {$count} tiles.", 'changes'=>$changes];

        } elseif ($mode === 'balanced') {
            // Balanced — distribute tile types as evenly as possible
            $typeCount = count($tileTypes);
            $pool      = [];
            $perType   = (int)ceil($count / $typeCount);
            foreach ($tileTypes as $t) {
                for ($i = 0; $i < $perType; $i++) {
                    $pool[] = $t;
                }
            }
            shuffle($pool);
            shuffle($diceNumbers);
            $i = 0;
            foreach ($existingTiles as $tile) {
                $newType = $pool[$i % count($pool)];
                $dice    = $diceNumbers[$i % count($diceNumbers)];
                $db->prepare("UPDATE map_tiles SET tile_type_id=?, dice_number=? WHERE id=?")
                   ->execute([$newType['id'], $dice, $tile['id']]);
                $changes[] = [
                    'x'    => $tile['coord_x'],
                    'y'    => $tile['coord_y'],
                    'type' => $newType['name'],
                    'icon' => $newType['icon'],
                    'dice' => $dice,
                ];
                $i++;
            }
            $result = ['type'=>'success', 'msg'=>"Balanced randomization across {$count} tiles.", 'changes'=>$changes];

        } elseif ($mode === 'dice_only') {
            // Only shuffle dice numbers, keep tile types
            shuffle($diceNumbers);
            $i = 0;
            foreach ($existingTiles as $tile) {
                $dice = $diceNumbers[$i % count($diceNumbers)];
                $db->prepare("UPDATE map_tiles SET dice_number=? WHERE id=?")
                   ->execute([$dice, $tile['id']]);

                $type = $db->prepare("SELECT t.name, t.icon FROM map_tiles m
                    JOIN tile_types t ON t.id=m.tile_type_id WHERE m.id=?");
                $type->execute([$tile['id']]);
                $type = $type->fetch();

                $changes[] = [
                    'x'    => $tile['coord_x'],
                    'y'    => $tile['coord_y'],
                    'type' => $type['name'] ?? '?',
                    'icon' => $type['icon'] ?? '?',
                    'dice' => $dice,
                ];
                $i++;
            }
            $result = ['type'=>'success', 'msg'=>"Shuffled dice numbers on {$count} tiles.", 'changes'=>$changes];
        }
    }
}

// ── FETCH CURRENT STATE ───────────────────────────────────────────────────────
$tileTypes   = $db->query("SELECT t.*,
    (SELECT COUNT(*) FROM map_tiles WHERE tile_type_id=t.id) as on_map
    FROM tile_types t ORDER BY t.name")->fetchAll();

$currentTiles = $db->query("SELECT m.coord_x, m.coord_y, m.dice_number,
    t.name as type_name, t.icon, t.color
    FROM map_tiles m JOIN tile_types t ON t.id=m.tile_type_id
    ORDER BY m.coord_y, m.coord_x")->fetchAll();

// Build grid for preview
$tileGrid = [];
$minX = $minY = PHP_INT_MAX;
$maxX = $maxY = PHP_INT_MIN;
foreach ($currentTiles as $t) {
    $tileGrid[$t['coord_x']][$t['coord_y']] = $t;
    $minX = min($minX, $t['coord_x']);
    $maxX = max($maxX, $t['coord_x']);
    $minY = min($minY, $t['coord_y']);
    $maxY = max($maxY, $t['coord_y']);
}
if (!$currentTiles) { $minX=$minY=0; $maxX=$maxY=6; }

pageHeader('Randomize Map', 'randomize.php');
?>

<h1 class="page-title">🔀 Randomize Map</h1>
<p class="page-sub">Redistribute tile types and dice numbers across the map</p>

<div class="grid-2">

  <!-- ── LEFT: CONTROLS ────────────────────────────────────────────────────── -->
  <div>

    <!-- RESULT MESSAGE -->
    <?php if ($result): ?>
    <div class="alert alert-<?= $result['type'] === 'success' ? 'success' : 'error' ?>">
      <?= htmlspecialchars($result['msg']) ?>
    </div>
    <?php endif; ?>

    <!-- MODE FORM -->
    <div class="card">
      <div class="card-title">Randomize Options</div>
      <form method="post" action="randomize.php">

        <div style="display:flex; flex-direction:column; gap:.75rem; margin-bottom:1.5rem;">

          <!-- FULL RANDOM -->
          <label style="display:flex; align-items:flex-start; gap:.75rem; cursor:pointer;
                 padding:.75rem; border-radius:var(--radius); border:1px solid var(--border);
                 background:var(--surface); transition:border-color .2s;"
                 onmouseenter="this.style.borderColor='var(--gold-dim)'"
                 onmouseleave="this.style.borderColor='var(--border)'">
            <input type="radio" name="mode" value="full" checked
                   style="accent-color:var(--gold); margin-top:.2rem; flex-shrink:0;">
            <div>
              <div style="font-family:'Cinzel',serif; font-size:.85rem; color:var(--text);">
                🎲 Full Random
              </div>
              <div style="font-size:.82rem; color:var(--muted); margin-top:.2rem;">
                Every tile gets a completely random type and dice number. Pure chaos.
              </div>
            </div>
          </label>

          <!-- BALANCED -->
          <label style="display:flex; align-items:flex-start; gap:.75rem; cursor:pointer;
                 padding:.75rem; border-radius:var(--radius); border:1px solid var(--border);
                 background:var(--surface); transition:border-color .2s;"
                 onmouseenter="this.style.borderColor='var(--gold-dim)'"
                 onmouseleave="this.style.borderColor='var(--border)'">
            <input type="radio" name="mode" value="balanced"
                   style="accent-color:var(--gold); margin-top:.2rem; flex-shrink:0;">
            <div>
              <div style="font-family:'Cinzel',serif; font-size:.85rem; color:var(--text);">
                ⚖ Balanced Distribution
              </div>
              <div style="font-size:.82rem; color:var(--muted); margin-top:.2rem;">
                All tile types appear roughly equally. Better for fair gameplay.
              </div>
            </div>
          </label>

          <!-- DICE ONLY -->
          <label style="display:flex; align-items:flex-start; gap:.75rem; cursor:pointer;
                 padding:.75rem; border-radius:var(--radius); border:1px solid var(--border);
                 background:var(--surface); transition:border-color .2s;"
                 onmouseenter="this.style.borderColor='var(--gold-dim)'"
                 onmouseleave="this.style.borderColor='var(--border)'">
            <input type="radio" name="mode" value="dice_only"
                   style="accent-color:var(--gold); margin-top:.2rem; flex-shrink:0;">
            <div>
              <div style="font-family:'Cinzel',serif; font-size:.85rem; color:var(--text);">
                🎯 Dice Numbers Only
              </div>
              <div style="font-size:.82rem; color:var(--muted); margin-top:.2rem;">
                Keep tile terrain types but reshuffle dice numbers across the map.
              </div>
            </div>
          </label>

        </div>

        <button class="btn btn-primary" type="submit"
                onclick="return confirm('Randomize the map? This cannot be undone.')">
          🔀 Randomize Now
        </button>
        <a class="btn btn-outline" href="map.php" style="margin-left:.5rem;">View Full Map</a>
      </form>
    </div>

    <!-- TILE TYPE COUNTS -->
    <div class="card">
      <div class="card-title">Tile Type Distribution</div>
      <div style="display:flex; flex-direction:column; gap:.5rem;">
        <?php foreach ($tileTypes as $t): ?>
        <div style="display:flex; align-items:center; gap:.75rem;">
          <div style="width:1.5rem; height:1.5rem; background:<?= htmlspecialchars($t['color']) ?>;
               border-radius:4px; display:flex; align-items:center; justify-content:center;
               font-size:.8rem; flex-shrink:0;">
            <?= $t['icon'] ?>
          </div>
          <div style="flex:1; font-size:.88rem; color:var(--text);">
            <?= htmlspecialchars($t['name']) ?>
          </div>
          <div style="font-family:'Cinzel',serif; font-size:.85rem; color:var(--gold);">
            <?= $t['on_map'] ?>
          </div>
          <!-- Mini bar -->
          <?php $pct = count($currentTiles) > 0 ? round(($t['on_map']/count($currentTiles))*100) : 0; ?>
          <div style="width:80px; background:var(--border); border-radius:10px; height:6px; overflow:hidden;">
            <div style="background:<?= htmlspecialchars($t['color']) ?>; width:<?= $pct ?>%; height:100%;"></div>
          </div>
          <div style="font-size:.75rem; color:var(--muted); width:2rem; text-align:right;">
            <?= $pct ?>%
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- CHANGE LOG -->
    <?php if (!empty($result['changes'])): ?>
    <div class="card">
      <div class="card-title">Changes Made</div>
      <div class="tbl-wrap">
        <table>
          <thead>
            <tr><th>Coords</th><th>New Type</th><th>Dice</th></tr>
          </thead>
          <tbody>
          <?php foreach ($result['changes'] as $ch): ?>
            <tr>
              <td style="font-family:'Cinzel',serif; font-size:.8rem; color:var(--muted);">
                (<?= $ch['x'] ?>, <?= $ch['y'] ?>)
              </td>
              <td><?= $ch['icon'] ?> <?= htmlspecialchars($ch['type']) ?></td>
              <td>
                <span style="font-family:'Cinzel',serif;
                  color:<?= in_array($ch['dice'],[6,8]) ? 'var(--fire)' : 'var(--text)' ?>">
                  <?= $ch['dice'] ?>
                </span>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>

  </div>

  <!-- ── RIGHT: MAP PREVIEW ─────────────────────────────────────────────────── -->
  <div>
    <div class="card">
      <div class="card-title">Current Map</div>
      <div style="overflow-x:auto;">
        <?php for ($y = $minY; $y <= $maxY; $y++): ?>
        <div style="display:flex; gap:3px; margin-bottom:3px;
             margin-left:<?= ($y % 2 !== 0) ? '28px' : '0' ?>">
          <?php for ($x = $minX; $x <= $maxX; $x++):
            $tile = $tileGrid[$x][$y] ?? null;
          ?>
          <?php if ($tile): ?>
            <div style="width:56px; height:56px; flex-shrink:0;
                 background:<?= htmlspecialchars($tile['color']) ?>; border-radius:6px;
                 display:flex; flex-direction:column; align-items:center; justify-content:center;
                 border:2px solid rgba(255,255,255,.1); position:relative; font-size:.9rem;"
                 title="<?= htmlspecialchars($tile['type_name']) ?> (<?= $x ?>,<?= $y ?>)<?= ($x===$y) ? ' ✨' : '' ?>">
              <?= $tile['icon'] ?>
              <?php if ($tile['dice_number']): ?>
                <div style="font-family:'Cinzel',serif; font-size:.6rem; font-weight:600;
                     background:rgba(0,0,0,.5); border-radius:8px; padding:.02rem .3rem;
                     color:<?= in_array($tile['dice_number'],[6,8]) ? '#e8622a' : 'white' ?>; margin-top:.1rem;">
                  <?= $tile['dice_number'] ?>
                </div>
              <?php endif; ?>
              <?php if ($x === $y): ?>
                <div style="position:absolute; bottom:1px; right:2px; font-size:.45rem;">✨</div>
              <?php endif; ?>
              <div style="font-size:.4rem; color:rgba(255,255,255,.4);"><?= $x ?>,<?= $y ?></div>
            </div>
          <?php else: ?>
            <div style="width:56px; height:56px; flex-shrink:0;
                 background:var(--surface); border-radius:6px;
                 border:1px dashed var(--border);"></div>
          <?php endif; ?>
          <?php endfor; ?>
        </div>
        <?php endfor; ?>
      </div>
      <div style="margin-top:.75rem; font-size:.75rem; color:var(--muted);">
        ✨ = magic ground (x=y) &nbsp;|&nbsp;
        <span style="color:var(--fire);">red dice</span> = high probability rolls (6 & 8)
      </div>
    </div>
  </div>

</div>

<?php pageFooter(); ?>