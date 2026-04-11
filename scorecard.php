<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require 'auth.php';
require 'db.php';
require 'layout.php';

$db     = getDB();
$userid = $SESSION_USERID;

// Fetch user
$user = $db->prepare("SELECT u.*, COALESCE(uc.coins,0) as coins
    FROM users u LEFT JOIN user_coins uc ON uc.userid=u.id WHERE u.id=?");
$user->execute([$userid]);
$user = $user->fetch();

// Fetch or init stats
$stats = $db->prepare("SELECT * FROM user_stats WHERE userid=?");
$stats->execute([$userid]);
$stats = $stats->fetch();
if (!$stats) {
    $db->prepare("INSERT INTO user_stats (userid) VALUES (?)")->execute([$userid]);
    $stats = ['battles_fought'=>0,'battles_won'=>0,'tiles_visited'=>0,
              'weapons_crafted'=>0,'scrolls_found'=>0,'bowties_found'=>0,'clothing_found'=>0];
}

// Equipped clothing
$equipped = $db->prepare("SELECT uc.*, ct.name as type_name, ct.slot, ct.shield_element,
    ct.shield_value, COALESCE(cs.hex_value,'') as hex_value,
    COALESCE(cs.skill_bonus,0) as skill_bonus,
    COALESCE(c.name,'') as colour_name, COALESCE(c.element,'') as colour_element
    FROM user_clothing uc
    JOIN clothing_types ct ON ct.id=uc.clothing_type_id
    LEFT JOIN colour_shades cs ON cs.id=uc.colour_shade_id
    LEFT JOIN colours c ON c.id=cs.colour_id
    WHERE uc.userid=? AND uc.is_equipped=1");
$equipped->execute([$userid]);
$equippedItems = [];
foreach ($equipped->fetchAll() as $e) $equippedItems[$e['slot']] = $e;

// Element scores
function getUserScoresFull(PDO $db, int $id): array {
    $totals = ['ice'=>0,'ground'=>0,'fire'=>0,'water'=>0,'dark'=>0];
    $s = $db->prepare("SELECT s.iceScore,s.groundScore,s.fireScore,s.waterScore,s.darkScore
        FROM userAttributes ua JOIN skills s ON s.id=ua.skillid WHERE ua.userid=?");
    $s->execute([$id]);
    foreach ($s->fetchAll() as $r) {
        foreach (['ice','ground','fire','water','dark'] as $el)
            $totals[$el] += $r[$el.'Score'];
    }
    $w = $db->prepare("SELECT s.iceScore,s.groundScore,s.fireScore,s.waterScore,s.darkScore
        FROM inventory i
        JOIN weaponAttributes wa ON wa.weaponid=i.weaponid
        JOIN skills s ON s.id=wa.skillid WHERE i.userid=?");
    $w->execute([$id]);
    foreach ($w->fetchAll() as $r) {
        foreach (['ice','ground','fire','water','dark'] as $el)
            $totals[$el] += $r[$el.'Score'];
    }
    // Add clothing dye bonuses
    $c = $db->prepare("SELECT cs.skill_bonus, c.element
        FROM user_clothing uc
        JOIN colour_shades cs ON cs.id=uc.colour_shade_id
        JOIN colours c ON c.id=cs.colour_id
        WHERE uc.userid=? AND uc.is_equipped=1 AND cs.skill_bonus > 0");
    $c->execute([$id]);
    foreach ($c->fetchAll() as $r) {
        if (isset($totals[$r['element']])) $totals[$r['element']] += $r['skill_bonus'];
    }
    return $totals;
}

$scores   = getUserScoresFull($db, $userid);
$total    = array_sum($scores);
$maxScore = max(1, max($scores));

// Inventory
$weapons = $db->prepare("SELECT w.name FROM inventory i JOIN weapons w ON w.id=i.weaponid
    WHERE i.userid=? ORDER BY w.name");
$weapons->execute([$userid]);
$weapons = $weapons->fetchAll(PDO::FETCH_COLUMN);

// Position
$pos = $db->prepare("SELECT * FROM user_positions WHERE userid=?");
$pos->execute([$userid]);
$pos = $pos->fetch();

// Recent battles
$battles = $db->prepare("SELECT bl.*, c.name as char_name,
    CASE WHEN bl.winner_type='user' THEN 'Won' ELSE 'Lost' END as result
    FROM battle_log bl
    JOIN charbase c ON c.id=bl.defender_id
    WHERE bl.attacker_id=?
    ORDER BY bl.fought_at DESC LIMIT 5");
$battles->execute([$userid]);
$battles = $battles->fetchAll();

// Move trail
$trail = $db->prepare("SELECT * FROM user_move_trail WHERE userid=?
    ORDER BY moved_at DESC LIMIT 5");
$trail->execute([$userid]);
$trail = $trail->fetchAll();

$elIcons  = ['ice'=>'❄','ground'=>'🌍','fire'=>'🔥','water'=>'💧','dark'=>'🌑'];
$elColors = ['ice'=>'var(--ice)','ground'=>'var(--ground)','fire'=>'var(--fire)',
             'water'=>'var(--water)','dark'=>'var(--dark)'];
$winRate  = $stats['battles_fought'] > 0
    ? round(($stats['battles_won'] / $stats['battles_fought']) * 100)
    : 0;

// Dominant element
$dominant = array_keys($scores, max($scores))[0] ?? 'ice';

pageHeader('Scorecard', 'scorecard.php');
?>

<h1 class="page-title">🃏 Scorecard</h1>
<p class="page-sub">Your Stailian player profile</p>

<div class="grid-2">

  <!-- POKEMON-STYLE CARD -->
  <div>
    <div style="background:linear-gradient(135deg, var(--card) 60%, <?= $elColors[$dominant] ?>22);
         border:2px solid <?= $elColors[$dominant] ?>; border-radius:16px;
         padding:1.5rem; position:relative; overflow:hidden; max-width:380px;">

      <!-- CARD HEADER -->
      <div style="display:flex; justify-content:space-between; align-items:flex-start;
           margin-bottom:1rem;">
        <div>
          <div style="font-family:'Cinzel',serif; font-size:.65rem; letter-spacing:.15em;
               color:var(--muted); margin-bottom:.15rem;">STAILIAN PLAYER</div>
          <div style="font-family:'Cinzel',serif; font-size:1.4rem; font-weight:900;
               color:var(--gold); line-height:1;">
            <?= htmlspecialchars($user['name']) ?>
          </div>
          <div style="font-size:.8rem; color:var(--muted); margin-top:.15rem;">
            <?= htmlspecialchars($user['email']) ?>
          </div>
        </div>
        <div style="text-align:right;">
          <div style="font-family:'Cinzel',serif; font-size:1.8rem;
               color:<?= $elColors[$dominant] ?>; font-weight:700; line-height:1;">
            <?= $total ?>
          </div>
          <div style="font-size:.65rem; color:var(--muted); letter-spacing:.08em;">TOTAL POWER</div>
        </div>
      </div>

      <!-- AVATAR / OUTFIT DISPLAY -->
      <div style="background:rgba(0,0,0,.3); border-radius:12px; padding:1rem;
           margin-bottom:1rem; display:flex; align-items:center; gap:1rem;">
        <div style="position:relative; width:70px; height:70px; flex-shrink:0;">
          <!-- Body layers -->
          <div style="position:absolute; inset:0; display:flex; flex-direction:column;
               align-items:center; justify-content:center; font-size:2.8rem;">🧑</div>
          <?php if (!empty($equippedItems['shirt']['hex_value'])): ?>
          <div style="position:absolute; bottom:18px; left:50%; transform:translateX(-50%);
               width:32px; height:20px; background:<?= $equippedItems['shirt']['hex_value'] ?>;
               border-radius:4px; opacity:.8;"></div>
          <?php endif; ?>
          <?php if (!empty($equippedItems['pants']['hex_value'])): ?>
          <div style="position:absolute; bottom:4px; left:50%; transform:translateX(-50%);
               width:30px; height:14px; background:<?= $equippedItems['pants']['hex_value'] ?>;
               border-radius:3px; opacity:.8;"></div>
          <?php endif; ?>
        </div>
        <div style="flex:1;">
          <?php foreach (['shirt','pants','socks'] as $slot):
            $item = $equippedItems[$slot] ?? null;
            $icons = ['shirt'=>'👕','pants'=>'👖','socks'=>'🧦'];
          ?>
          <div style="font-size:.78rem; color:var(--muted); margin-bottom:.15rem;">
            <?= $icons[$slot] ?>
            <?php if ($item): ?>
              <span style="color:var(--text);"><?= htmlspecialchars($item['type_name']) ?></span>
              <?php if (!empty($item['colour_name'])): ?>
                <span style="color:<?= !empty($item['hex_value']) ? $item['hex_value'] : 'var(--muted)' ?>">
                  · <?= htmlspecialchars($item['colour_name']) ?>
                </span>
              <?php endif; ?>
            <?php else: ?>
              <span style="font-style:italic;">None</span>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
          <div style="font-size:.78rem; color:var(--gold); margin-top:.25rem;">
            🪙 <?= $user['coins'] ?> coins
          </div>
        </div>
      </div>

      <!-- ELEMENT BARS -->
      <div style="margin-bottom:1rem;">
        <div style="font-family:'Cinzel',serif; font-size:.65rem; letter-spacing:.1em;
             color:var(--muted); margin-bottom:.5rem;">ELEMENTAL POWER</div>
        <?php foreach ($scores as $el => $score): ?>
        <div style="display:flex; align-items:center; gap:.5rem; margin-bottom:.35rem;">
          <div style="width:1.2rem; text-align:center; font-size:.85rem; flex-shrink:0;">
            <?= $elIcons[$el] ?>
          </div>
          <div style="flex:1; background:var(--border); border-radius:10px;
               height:8px; overflow:hidden;">
            <div style="background:<?= $elColors[$el] ?>;
                 width:<?= $maxScore > 0 ? round(($score/$maxScore)*100) : 0 ?>%;
                 height:100%; border-radius:10px; transition:width .4s;"></div>
          </div>
          <div style="width:2rem; text-align:right; font-family:'Cinzel',serif;
               font-size:.78rem; color:<?= $elColors[$el] ?>;">
            <?= $score ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>

      <!-- BATTLE RECORD -->
      <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:.5rem;
           margin-bottom:1rem; text-align:center;">
        <?php foreach ([
          ['Battles', $stats['battles_fought'], 'var(--text)'],
          ['Wins',    $stats['battles_won'],    'var(--success)'],
          ['Win Rate',$winRate.'%',             'var(--gold)'],
        ] as [$label,$val,$col]): ?>
        <div style="background:rgba(0,0,0,.3); border-radius:8px; padding:.5rem;">
          <div style="font-family:'Cinzel',serif; font-size:1.1rem;
               color:<?= $col ?>; font-weight:600;"><?= $val ?></div>
          <div style="font-size:.65rem; color:var(--muted); letter-spacing:.06em;"><?= $label ?></div>
        </div>
        <?php endforeach; ?>
      </div>

      <!-- POSITION -->
      <?php if ($pos): ?>
      <div style="background:rgba(0,0,0,.3); border-radius:8px; padding:.5rem .75rem;
           display:flex; justify-content:space-between; align-items:center;
           margin-bottom:1rem; font-size:.82rem;">
        <span style="color:var(--muted);">📍 Current Position</span>
        <span style="font-family:'Cinzel',serif; color:var(--gold);">
          (<?= $pos['coord_x'] ?>, <?= $pos['coord_y'] ?>)
        </span>
      </div>
      <?php endif; ?>

      <!-- ACHIEVEMENTS ROW -->
      <div style="display:flex; gap:.5rem; justify-content:space-between; flex-wrap:wrap;">
        <?php foreach ([
          ['🗡', $stats['weapons_crafted'], 'Crafted'],
          ['📜', $stats['scrolls_found'],  'Scrolls'],
          ['🎀', $stats['bowties_found'],  'Bowties'],
          ['👕', $stats['clothing_found'], 'Clothing'],
          ['🌍', $stats['tiles_visited'],  'Tiles'],
        ] as [$icon,$val,$label]): ?>
        <div style="text-align:center; flex:1; min-width:50px;">
          <div style="font-size:1rem;"><?= $icon ?></div>
          <div style="font-family:'Cinzel',serif; font-size:.9rem; color:var(--gold);">
            <?= $val ?>
          </div>
          <div style="font-size:.58rem; color:var(--muted); letter-spacing:.04em;">
            <?= $label ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>

      <!-- CARD WATERMARK -->
      <div style="position:absolute; bottom:-20px; right:-10px; font-size:6rem;
           opacity:.04; user-select:none; pointer-events:none;">
        <?= $elIcons[$dominant] ?>
      </div>
    </div>
  </div>

  <!-- RIGHT: STATS & HISTORY -->
  <div>

    <!-- WEAPONS -->
    <div class="card">
      <div class="card-title">🗡 Inventory</div>
      <?php if ($weapons): ?>
        <div style="display:flex; flex-wrap:wrap; gap:.4rem;">
          <?php foreach ($weapons as $w): ?>
          <span style="padding:.25rem .6rem; background:var(--surface); border:1px solid var(--border);
               border-radius:var(--radius); font-size:.82rem;">
            🗡 <?= htmlspecialchars($w) ?>
          </span>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <p style="color:var(--muted); font-style:italic; font-size:.88rem;">No weapons yet.</p>
      <?php endif; ?>
    </div>

    <!-- RECENT BATTLES -->
    <div class="card">
      <div class="card-title">⚔ Recent Battles</div>
      <?php if ($battles): ?>
      <div class="tbl-wrap">
        <table>
          <thead><tr><th>vs Character</th><th>Result</th><th>When</th></tr></thead>
          <tbody>
          <?php foreach ($battles as $b): ?>
            <tr>
              <td>🧙 <?= htmlspecialchars($b['char_name']) ?></td>
              <td style="color:<?= $b['result']==='Won' ? 'var(--success)' : 'var(--danger)' ?>;
                   font-family:'Cinzel',serif; font-size:.82rem;">
                <?= $b['result'] === 'Won' ? '🏆 Won' : '💀 Lost' ?>
              </td>
              <td style="color:var(--muted); font-size:.75rem;"><?= $b['fought_at'] ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php else: ?>
        <p style="color:var(--muted); font-style:italic; font-size:.88rem;">No battles yet.</p>
      <?php endif; ?>
    </div>

    <!-- MOVE TRAIL -->
    <div class="card">
      <div class="card-title">👣 Last 5 Moves</div>
      <?php if ($trail): ?>
      <div style="display:flex; flex-direction:column; gap:.4rem;">
        <?php foreach ($trail as $i => $t): ?>
        <div style="display:flex; align-items:center; gap:.75rem; padding:.4rem .6rem;
             background:var(--surface); border-radius:var(--radius);
             border:1px solid <?= $i===0 ? 'var(--gold)' : 'var(--border)' ?>;">
          <div style="font-size:1.2rem;"><?= $t['tile_icon'] ?? '🗺' ?></div>
          <div style="flex:1;">
            <div style="font-size:.85rem; color:var(--text);">
              <?= htmlspecialchars($t['tile_type_name'] ?? 'Unknown') ?>
            </div>
            <div style="font-size:.72rem; color:var(--muted);"><?= $t['moved_at'] ?></div>
          </div>
          <div style="font-family:'Cinzel',serif; font-size:.82rem; color:var(--gold);">
            (<?= $t['coord_x'] ?>, <?= $t['coord_y'] ?>)
          </div>
          <?php if ($i === 0): ?>
            <span style="font-size:.65rem; color:var(--gold); font-family:'Cinzel',serif;">
              HERE
            </span>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
      <?php else: ?>
        <p style="color:var(--muted); font-style:italic; font-size:.88rem;">
          No moves yet. Start walking!
        </p>
      <?php endif; ?>
    </div>

  </div>
</div>

<?php pageFooter(); ?>