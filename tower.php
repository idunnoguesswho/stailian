<?php
require 'auth.php';
require 'db.php';
require 'health.php';
require 'clock.php';
require 'layout.php';

$db     = getDB();
$userid = $SESSION_USERID;
$act    = $_GET['action'] ?? 'view';

// Get current position including z
$posStmt = $db->prepare("SELECT * FROM user_positions WHERE userid=?");
$posStmt->execute([$userid]);
$pos = $posStmt->fetch();

$currentZ  = (int)($pos['coord_z'] ?? 0);
$currentX  = (int)($pos['coord_x'] ?? 0);
$currentY  = (int)($pos['coord_y'] ?? 0);
$atTower   = ($currentX === 5 && $currentY === 5);

function getTowerScrolls(PDO $db, int $uid): array {
    $r = $db->prepare("SELECT scroll_type, quantity FROM tower_scrolls WHERE userid=?");
    $r->execute([$uid]);
    $result = ['climbing'=>0,'sliding'=>0];
    foreach ($r->fetchAll() as $row) $result[$row['scroll_type']] = $row['quantity'];
    return $result;
}

$scrolls = getTowerScrolls($db, $userid);
$health  = getHealth($db, $userid);

// Z navigation — can only move one level at a time
$canClimb = $currentZ < Z_SKY;
$canSlide = $currentZ > Z_UNDERWORLD;
$targetUp = $currentZ + 1;
$targetDn = $currentZ - 1;

$msg   = '';
$error = '';

// ── CLIMB ─────────────────────────────────────────────────────────────────────
if ($act === 'climb' && $atTower && $canClimb) {
    if ($scrolls['climbing'] > 0) {
        $db->prepare("UPDATE tower_scrolls SET quantity=quantity-1
            WHERE userid=? AND scroll_type='climbing'")->execute([$userid]);
        $db->prepare("UPDATE user_positions SET coord_z=? WHERE userid=?")
           ->execute([$targetUp, $userid]);
        $msg = "🧗 Used climbing scroll → now at z={$targetUp} ("
             . zLabel($targetUp) . ")!";
    } elseif ($health['health'] >= 50) {
        $db->prepare("UPDATE user_health SET health=health-50 WHERE userid=?")
           ->execute([$userid]);
        $db->prepare("UPDATE user_positions SET coord_z=? WHERE userid=?")
           ->execute([$targetUp, $userid]);
        $msg = "🧗 Climbed to z={$targetUp} (" . zLabel($targetUp)
             . "). Cost 50 HP. Remaining: " . ($health['health']-50) . " HP";
    } else {
        $error = "Need 50 HP or a climbing scroll to climb. "
               . "Current HP: {$health['health']}.";
    }
}

// ── SLIDE ─────────────────────────────────────────────────────────────────────
if ($act === 'slide' && $atTower && $canSlide) {
    if ($scrolls['sliding'] > 0) {
        $db->prepare("UPDATE tower_scrolls SET quantity=quantity-1
            WHERE userid=? AND scroll_type='sliding'")->execute([$userid]);
        $db->prepare("UPDATE user_positions SET coord_z=? WHERE userid=?")
           ->execute([$targetDn, $userid]);
        $msg = "🎿 Used sliding scroll → now at z={$targetDn} ("
             . zLabel($targetDn) . ")!";
    } elseif ($health['health'] >= 50) {
        $db->prepare("UPDATE user_health SET health=health-50 WHERE userid=?")
           ->execute([$userid]);
        $db->prepare("UPDATE user_positions SET coord_z=? WHERE userid=?")
           ->execute([$targetDn, $userid]);
        $msg = "🎿 Slid to z={$targetDn} (" . zLabel($targetDn)
             . "). Cost 50 HP. Remaining: " . ($health['health']-50) . " HP";
    } else {
        $error = "Need 50 HP or a sliding scroll to slide. "
               . "Current HP: {$health['health']}.";
    }
}

// Re-fetch after action
$posStmt->execute([$userid]);
$pos      = $posStmt->fetch();
$currentZ = (int)($pos['coord_z'] ?? 0);
$scrolls  = getTowerScrolls($db, $userid);
$health   = getHealth($db, $userid);
$clock    = getClock($db, $userid);
$period   = getTimePeriod((int)$clock['game_hour']);

pageHeader('The Tower', 'tower.php');
?>

<h1 class="page-title">🗼 The Tower</h1>
<p class="page-sub">
  Located at (5, 5) on every layer · Current position:
  <strong style="color:var(--gold);">
    (<?= $currentX ?>, <?= $currentY ?>, z=<?= $currentZ ?>) —
    <?= zIcon($currentZ) ?> <?= zLabel($currentZ) ?>
  </strong>
</p>

<?php if ($msg):  echo '<div class="alert alert-success">'.htmlspecialchars($msg).'</div>'; endif; ?>
<?php if ($error):echo '<div class="alert alert-error">'.htmlspecialchars($error).'</div>'; endif; ?>

<div class="grid-2">
  <div>

    <!-- TOWER VISUAL WITH Z COORDS -->
    <div class="card" style="text-align:center; padding:2rem;">
      <div style="font-family:'Cinzel',serif; font-size:.65rem; letter-spacing:.1em;
           color:var(--muted); margin-bottom:1rem;">Z COORDINATE LAYERS</div>

      <div style="display:inline-flex; flex-direction:column; align-items:center; gap:0;">
        <?php foreach ([Z_SKY, Z_SURFACE, Z_UNDERWORLD] as $z): ?>

        <!-- LAYER BLOCK -->
        <div style="width:240px; padding:1rem 1.5rem; position:relative;
             background:<?= $z===$currentZ ? 'rgba(200,153,58,.12)' : 'var(--surface)' ?>;
             border:2px solid <?= $z===$currentZ ? 'var(--gold)' : 'var(--border)' ?>;
             border-radius:var(--radius); transition:all .3s;">
          <div style="display:flex; align-items:center; gap:.75rem;">
            <div style="font-family:'Cinzel',serif; font-size:.7rem;
                 color:var(--muted); width:2.5rem; text-align:right; flex-shrink:0;">
              z=<?= $z >0?'+':''; echo $z ?>
            </div>
            <div style="font-size:1.5rem;"><?= zIcon($z) ?></div>
            <div style="text-align:left;">
              <div style="font-family:'Cinzel',serif; font-size:.85rem;
                   color:<?= zColor($z) ?>;">
                <?= zLabel($z) ?>
              </div>
              <?php
              $lMults = $db->query("SELECT element, multiplier
                  FROM layer_multipliers WHERE coord_z=$z AND time_period='$period'")->fetchAll();
              ?>
              <?php if ($lMults): ?>
              <div style="display:flex; gap:.25rem; flex-wrap:wrap; margin-top:.2rem;">
                <?php foreach ($lMults as $m): ?>
                <span style="font-size:.58rem; padding:.05rem .3rem; border-radius:8px;
                     background:var(--card); border:1px solid var(--border);
                     color:var(--muted);">
                  <?= $m['element'] ?> ×<?= $m['multiplier'] ?>
                </span>
                <?php endforeach; ?>
              </div>
              <?php endif; ?>
            </div>
          </div>
          <?php if ($z === $currentZ): ?>
          <div style="position:absolute; right:.6rem; top:50%; transform:translateY(-50%);
               font-family:'Cinzel',serif; font-size:.6rem; color:var(--gold);">
            ◀ HERE
          </div>
          <?php endif; ?>
        </div>

        <!-- LADDER/SLIDE CONNECTOR -->
        <?php if ($z !== Z_UNDERWORLD): ?>
        <div style="width:4px; height:36px; background:var(--border); position:relative;
             display:flex; align-items:center; justify-content:center;">
          <span style="font-size:.65rem; color:var(--muted); white-space:nowrap;
               transform:rotate(0deg);">🗼</span>
        </div>
        <?php endif; ?>

        <?php endforeach; ?>
      </div>

      <!-- Z COORD LEGEND -->
      <div style="margin-top:1.25rem; font-size:.72rem; color:var(--muted); line-height:2;">
        z=+1 · Sky &nbsp;|&nbsp; z=0 · Surface &nbsp;|&nbsp; z=-1 · Underworld
      </div>
    </div>

    <!-- SCROLL INVENTORY -->
    <div class="card">
      <div class="card-title">📜 Tower Scrolls</div>
      <div style="display:flex; gap:.75rem;">
        <?php foreach ([
          ['climbing','🧗','Climb z+1'],
          ['sliding', '🎿','Slide z-1'],
        ] as [$type,$icon,$label]): ?>
        <div style="flex:1; padding:.75rem; background:var(--surface);
             border-radius:var(--radius); border:1px solid var(--border); text-align:center;">
          <div style="font-size:1.5rem; margin-bottom:.25rem;"><?= $icon ?></div>
          <div style="font-size:.72rem; color:var(--muted); margin-bottom:.2rem;"><?= $label ?></div>
          <div style="font-family:'Cinzel',serif; font-size:1.3rem;
               color:<?= $scrolls[$type]>0?'var(--gold)':'var(--danger)' ?>;">
            ×<?= $scrolls[$type] ?>
          </div>
        </div>
        <?php endforeach; ?>
        <div style="flex:1; padding:.75rem; background:var(--surface);
             border-radius:var(--radius); border:1px solid var(--border); text-align:center;">
          <div style="font-size:1.5rem; margin-bottom:.25rem;">❤</div>
          <div style="font-size:.72rem; color:var(--muted); margin-bottom:.2rem;">Health</div>
          <div style="font-family:'Cinzel',serif; font-size:1.3rem;
               color:<?= $health['health']>=50?'var(--success)':'var(--danger)' ?>;">
            <?= $health['health'] ?>
          </div>
        </div>
      </div>
    </div>

  </div>

  <div>

    <!-- TRAVEL PANEL -->
    <?php if ($atTower): ?>
    <div class="card">
      <div class="card-title">
        🗼 Travel — Currently at z=<?= $currentZ > 0 ? '+' : '' ?><?= $currentZ ?>
        (<?= zLabel($currentZ) ?>)
      </div>

      <!-- CLIMB z+1 -->
      <?php if ($canClimb): ?>
      <div style="margin-bottom:.75rem; padding:.75rem; background:var(--surface);
           border-radius:var(--radius); border:1px solid var(--border);">
        <div style="display:flex; justify-content:space-between; align-items:center;
             margin-bottom:.5rem;">
          <div>
            <div style="font-family:'Cinzel',serif; font-size:.82rem; color:var(--text);">
              🧗 Climb to z=+<?= $targetUp ?> (<?= zLabel($targetUp) ?>)
            </div>
            <div style="font-size:.72rem; color:var(--muted); margin-top:.1rem;">
              <?= $scrolls['climbing']>0
                ? '✓ Uses 1 climbing scroll'
                : '⚠ Costs 50 HP — ' . ($health['health']>=50?'enough health':'not enough health!') ?>
            </div>
          </div>
          <span style="font-size:1.5rem;"><?= zIcon($targetUp) ?></span>
        </div>
        <a href="tower.php?action=climb"
           class="btn <?= $scrolls['climbing']>0||$health['health']>=50?'btn-outline':'btn-danger' ?>"
           style="width:100%; justify-content:center;"
           onclick="return confirm('<?= $scrolls['climbing']>0
               ? 'Use 1 climbing scroll to ascend to z=+'.($targetUp).'?'
               : 'Climb costs 50 HP. You have '.$health['health'].' HP. Continue?' ?>')">
          🧗 Climb z=<?= $currentZ ?> → z=+<?= $targetUp ?>
        </a>
      </div>
      <?php else: ?>
      <div style="padding:.65rem .85rem; background:var(--surface); border-radius:var(--radius);
           border:1px solid var(--border); font-size:.82rem; color:var(--muted);
           margin-bottom:.75rem; text-align:center;">
        ⬆ Already at maximum z=+<?= Z_SKY ?> (<?= zLabel(Z_SKY) ?>)
      </div>
      <?php endif; ?>

      <!-- SLIDE z-1 -->
      <?php if ($canSlide): ?>
      <div style="padding:.75rem; background:var(--surface);
           border-radius:var(--radius); border:1px solid var(--border);">
        <div style="display:flex; justify-content:space-between; align-items:center;
             margin-bottom:.5rem;">
          <div>
            <div style="font-family:'Cinzel',serif; font-size:.82rem; color:var(--text);">
              🎿 Slide to z=<?= $targetDn ?> (<?= zLabel($targetDn) ?>)
            </div>
            <div style="font-size:.72rem; color:var(--muted); margin-top:.1rem;">
              <?= $scrolls['sliding']>0
                ? '✓ Uses 1 sliding scroll'
                : '⚠ Costs 50 HP — ' . ($health['health']>=50?'enough health':'not enough health!') ?>
            </div>
          </div>
          <span style="font-size:1.5rem;"><?= zIcon($targetDn) ?></span>
        </div>
        <a href="tower.php?action=slide"
           class="btn <?= $scrolls['sliding']>0||$health['health']>=50?'btn-outline':'btn-danger' ?>"
           style="width:100%; justify-content:center;"
           onclick="return confirm('<?= $scrolls['sliding']>0
               ? 'Use 1 sliding scroll to descend to z='.($targetDn).'?'
               : 'Slide costs 50 HP. You have '.$health['health'].' HP. Continue?' ?>')">
          🎿 Slide z=<?= $currentZ ?> → z=<?= $targetDn ?>
        </a>
      </div>
      <?php else: ?>
      <div style="padding:.65rem .85rem; background:var(--surface); border-radius:var(--radius);
           border:1px solid var(--border); font-size:.82rem; color:var(--muted); text-align:center;">
        ⬇ Already at minimum z=<?= Z_UNDERWORLD ?> (<?= zLabel(Z_UNDERWORLD) ?>)
      </div>
      <?php endif; ?>

    </div>
    <?php else: ?>
    <div class="card">
      <div class="card-title">🗼 Not at the Tower</div>
      <p style="font-size:.9rem; color:var(--muted); line-height:1.7; margin-bottom:.75rem;">
        The tower is at <strong style="color:var(--gold)">(5, 5)</strong>
        on every z level. Navigate there to travel between layers.
      </p>
      <div style="padding:.65rem .85rem; background:var(--surface); border-radius:var(--radius);
           border:1px solid var(--border); font-size:.82rem; color:var(--muted);">
        Your position: (<?= $currentX ?>, <?= $currentY ?>, z=<?= $currentZ ?>)<br>
        Tower:         (5, 5, z=<?= $currentZ ?>)
      </div>
      <a href="walk.php" class="btn btn-outline" style="margin-top:.75rem;">
        🎲 Walk There
      </a>
    </div>
    <?php endif; ?>

    <!-- Z LAYER EFFECTS SUMMARY -->
    <div class="card">
      <div class="card-title">
        <?= getTimeIcon($period) ?> <?= ucfirst($period) ?> Multipliers by Layer
      </div>
      <div style="display:flex; flex-direction:column; gap:.5rem;">
        <?php foreach ([Z_SKY, Z_SURFACE, Z_UNDERWORLD] as $z):
          $mults = $db->query("SELECT element, multiplier FROM layer_multipliers
              WHERE coord_z=$z AND time_period='$period'
              ORDER BY multiplier DESC")->fetchAll();
        ?>
        <div style="padding:.5rem .7rem; background:var(--surface); border-radius:var(--radius);
             border:1px solid <?= $z===$currentZ?'var(--gold)':'var(--border)' ?>;">
          <div style="font-family:'Cinzel',serif; font-size:.72rem; color:<?= zColor($z) ?>;
               margin-bottom:.3rem;">
            <?= zIcon($z) ?> z=<?= $z>0?'+':'' ?><?= $z ?> · <?= zLabel($z) ?>
            <?= $z===$currentZ?' ◀':'' ?>
          </div>
          <?php if ($mults): ?>
          <div style="display:flex; gap:.3rem; flex-wrap:wrap;">
            <?php foreach ($mults as $m): ?>
            <span style="font-size:.65rem; padding:.08rem .35rem; border-radius:8px;
                 background:var(--card); border:1px solid var(--border); color:var(--muted);">
              <?= $m['element'] ?> ×<?= $m['multiplier'] ?>
            </span>
            <?php endforeach; ?>
          </div>
          <?php else: ?>
          <span style="font-size:.72rem; color:var(--muted); font-style:italic;">
            No active multipliers this period
          </span>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

  </div>
</div>

<?php pageFooter(); ?>