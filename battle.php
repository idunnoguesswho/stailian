<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require 'db.php';
require 'layout.php';

$db = getDB();

// ── FETCH ALL USERS AND CHARS FOR THE FORM ────────────────────────────────────
$users = $db->query("SELECT id, charname FROM characters where role='user' or role='admin' ORDER BY name")->fetchAll();
$chars = $db->query("SELECT id, charname FROM characters where role='npc' ORDER BY name")->fetchAll();

// ── CALCULATE SCORES HELPER ───────────────────────────────────────────────────
function getUserScores(PDO $db, int $id): array {
    $totals = ['ice'=>0,'ground'=>0,'fire'=>0,'water'=>0,'dark'=>0];

    // Skills from userAttributes
    $s = $db->prepare("SELECT e.elementName, inv.score, inv.quantityOnHand FROM inventory inv inner join ... WHERE inv.userid=?");
    $s->execute([$id]);
    foreach ($s->fetchAll() as $r) {
        $totals['ice']    += $r['iceScore'];
        }
        }


// ── PROCESS BATTLE ────────────────────────────────────────────────────────────
$battle       = false;
$userid       = (int)($_POST['userid'] ?? 0);
$charid       = (int)($_POST['charid'] ?? 0);
$userName     = '';
$charName     = '';
$userScores   = [];
$charScores   = [];
$elementWins  = [];
$userWins     = 0;
$charWins     = 0;
$draws        = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $userid && $charid) {
    $battle = true;

    $u = $db->prepare("SELECT name FROM users WHERE id=?");
    $u->execute([$userid]); 
    $userName = $u->fetchColumn();

    $c = $db->prepare("SELECT name FROM charbase WHERE id=?");
    $c->execute([$charid]); 
    $charName = $c->fetchColumn();

    $userScores = getUserScores($db, $userid);
    $charScores = getCharScores($db, $charid);

    $elements = ['ice','ground','fire','water','dark'];
    foreach ($elements as $el) {
        $us = $userScores[$el];
        $cs = $charScores[$el];
        if ($us > $cs)       { $winner = 'user'; $userWins++; }
        elseif ($cs > $us)   { $winner = 'char'; $charWins++; }
        else                 { $winner = 'draw'; $draws++; }
        $elementWins[$el] = ['user' => $us, 'char' => $cs, 'winner' => $winner];
    }
}

$elements = [
    'ice'    => ['❄',  'Ice',    'ice'],
    'ground' => ['🌍', 'Ground', 'ground'],
    'fire'   => ['🔥', 'Fire',   'fire'],
    'water'  => ['💧', 'Water',  'water'],
    'dark'   => ['🌑', 'Dark',   'dark'],
];

pageHeader('Battle Arena', 'battle.php');
?>

<h1 class="page-title">⚔ Battle Arena</h1>
<p class="page-sub">Pit a user against a character and see who wins</p>

<!-- SELECTION FORM -->
<div class="card">
  <div class="card-title">Choose Your Combatants</div>
  <form method="post" action="battle.php">
    <div class="grid-2">
      <div class="form-group">
        <label>👤 User</label>
        <select name="userid">
          <option value="">— Select User —</option>
          <?php foreach ($users as $u): ?>
            <option value="<?= $u['id'] ?>" <?= $userid == $u['id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($u['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>🧙 Character</label>
        <select name="charid">
          <option value="">— Select Character —</option>
          <?php foreach ($chars as $c): ?>
            <option value="<?= $c['id'] ?>" <?= $charid == $c['id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($c['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="btn-row">
      <button class="btn btn-primary" type="submit">⚔ Fight!</button>
    </div>
  </form>
</div>

<?php if ($battle): 

  $userTotal = array_sum($userScores);
  $charTotal = array_sum($charScores);

  if ($userWins > $charWins)       { $overallWinner = 'user'; $overallLabel = '👤 ' . $userName . ' Wins!'; $winColor = 'var(--gold)'; }
  elseif ($charWins > $userWins)   { $overallWinner = 'char'; $overallLabel = '🧙 ' . $charName . ' Wins!'; $winColor = 'var(--ice)'; }
  else                             { $overallWinner = 'draw'; $overallLabel = '⚖ It\'s a Draw!';             $winColor = 'var(--muted)'; }
?>

<!-- OVERALL RESULT BANNER -->
<div style="text-align:center; padding:2rem; margin-bottom:1.5rem; background:var(--card);
     border:2px solid <?= $winColor ?>; border-radius:var(--radius); position:relative; overflow:hidden;">
  <div style="font-size:.75rem; font-family:'Cinzel',serif; letter-spacing:.15em; color:var(--muted); margin-bottom:.5rem;">BATTLE RESULT</div>
  <div style="font-family:'Cinzel',serif; font-size:2rem; font-weight:900; color:<?= $winColor ?>; margin-bottom:.5rem;">
    <?= $overallLabel ?>
  </div>
  <div style="font-family:'Cinzel',serif; font-size:.8rem; letter-spacing:.1em; color:var(--muted);">
    <?= $userName ?> <?= $userWins ?>W — <?= $draws ?>D — <?= $charWins ?>W <?= $charName ?>
  </div>
</div>

<!-- VS HEADER -->
<div style="display:grid; grid-template-columns:1fr auto 1fr; align-items:center; gap:1rem; margin-bottom:1.5rem;">
  <div class="card" style="text-align:center; margin-bottom:0;
       border-color:<?= $overallWinner === 'user' ? 'var(--gold)' : 'var(--border)' ?>">
    <div style="font-size:2.5rem;">👤</div>
    <div style="font-family:'Cinzel',serif; font-size:1.2rem; color:var(--gold);"><?= htmlspecialchars($userName) ?></div>
    <div style="font-family:'Cinzel',serif; font-size:1.8rem; color:var(--text); margin:.25rem 0;"><?= $userTotal ?></div>
    <div style="font-size:.75rem; color:var(--muted); font-family:'Cinzel',serif; letter-spacing:.08em;">TOTAL POWER</div>
    <div style="margin-top:.75rem; display:flex; justify-content:center; gap:.5rem; flex-wrap:wrap;">
      <span style="font-family:'Cinzel',serif; font-size:.75rem; color:var(--success);"><?= $userWins ?>W</span>
      <span style="font-family:'Cinzel',serif; font-size:.75rem; color:var(--muted);"><?= $draws ?>D</span>
      <span style="font-family:'Cinzel',serif; font-size:.75rem; color:var(--danger);"><?= $charWins ?>L</span>
    </div>
  </div>

  <div style="font-family:'Cinzel',serif; font-size:1.5rem; color:var(--muted); text-align:center;">VS</div>

  <div class="card" style="text-align:center; margin-bottom:0;
       border-color:<?= $overallWinner === 'char' ? 'var(--ice)' : 'var(--border)' ?>">
    <div style="font-size:2.5rem;">🧙</div>
    <div style="font-family:'Cinzel',serif; font-size:1.2rem; color:var(--ice);"><?= htmlspecialchars($charName) ?></div>
    <div style="font-family:'Cinzel',serif; font-size:1.8rem; color:var(--text); margin:.25rem 0;"><?= $charTotal ?></div>
    <div style="font-size:.75rem; color:var(--muted); font-family:'Cinzel',serif; letter-spacing:.08em;">TOTAL POWER</div>
    <div style="margin-top:.75rem; display:flex; justify-content:center; gap:.5rem; flex-wrap:wrap;">
      <span style="font-family:'Cinzel',serif; font-size:.75rem; color:var(--success);"><?= $charWins ?>W</span>
      <span style="font-family:'Cinzel',serif; font-size:.75rem; color:var(--muted);"><?= $draws ?>D</span>
      <span style="font-family:'Cinzel',serif; font-size:.75rem; color:var(--danger);"><?= $userWins ?>L</span>
    </div>
  </div>
</div>

<!-- ELEMENTAL BREAKDOWN -->
<h2 class="page-title" style="font-size:1.1rem; margin-bottom:1rem;">Elemental Breakdown</h2>
<div style="display:flex; flex-direction:column; gap:.75rem; margin-bottom:1.5rem;">
<?php foreach ($elements as $key => [$icon, $label, $cls]):
  $row    = $elementWins[$key];
  $us     = $row['user'];
  $cs     = $row['char'];
  $winner = $row['winner'];
  $total  = $us + $cs;
  $uPct   = $total > 0 ? round(($us / $total) * 100) : 50;
  $cPct   = 100 - $uPct;
?>
<div class="card" style="margin-bottom:0; padding:1rem 1.25rem;">
  <div style="display:flex; align-items:center; gap:1rem;">

    <!-- User score -->
    <div style="width:60px; text-align:right;">
      <span style="font-family:'Cinzel',serif; font-size:1.1rem;
        color:<?= $winner === 'user' ? 'var(--gold)' : 'var(--muted)' ?>;
        font-weight:<?= $winner === 'user' ? '600' : '400' ?>;">
        <?= $us ?>
        <?= $winner === 'user' ? ' ✓' : '' ?>
      </span>
    </div>

    <!-- Bar -->
    <div style="flex:1;">
      <div style="display:flex; align-items:center; gap:.5rem; margin-bottom:.35rem;">
        <span style="font-size:1rem;"><?= $icon ?></span>
        <span style="font-family:'Cinzel',serif; font-size:.75rem; letter-spacing:.08em; color:var(--<?= $cls ?>);"><?= strtoupper($label) ?></span>
        <?php if ($winner === 'draw'): ?>
          <span style="font-family:'Cinzel',serif; font-size:.65rem; color:var(--muted); margin-left:auto;">DRAW</span>
        <?php endif; ?>
      </div>
      <div style="background:var(--border); border-radius:20px; height:10px; overflow:hidden; display:flex;">
        <div style="background:var(--gold); width:<?= $uPct ?>%; height:100%; border-radius:20px 0 0 20px; transition:width .4s;"></div>
        <div style="background:var(--ice);  width:<?= $cPct ?>%; height:100%; border-radius:0 20px 20px 0; transition:width .4s;"></div>
      </div>
    </div>

    <!-- Char score -->
    <div style="width:60px; text-align:left;">
      <span style="font-family:'Cinzel',serif; font-size:1.1rem;
        color:<?= $winner === 'char' ? 'var(--ice)' : 'var(--muted)' ?>;
        font-weight:<?= $winner === 'char' ? '600' : '400' ?>;">
        <?= $winner === 'char' ? '✓ ' : '' ?>
        <?= $cs ?>
      </span>
    </div>

  </div>
</div>
<?php endforeach; ?>
</div>

<!-- FULL SCORE TABLE -->
<h2 class="page-title" style="font-size:1.1rem; margin-bottom:1rem;">Full Score Table</h2>
<div class="card">
  <div class="tbl-wrap">
    <table>
      <thead>
        <tr>
          <th>Element</th>
          <th>👤 <?= htmlspecialchars($userName) ?></th>
          <th>🧙 <?= htmlspecialchars($charName) ?></th>
          <th>Winner</th>
          <th>Margin</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($elements as $key => [$icon, $label, $cls]):
        $row    = $elementWins[$key];
        $us     = $row['user'];
        $cs     = $row['char'];
        $winner = $row['winner'];
        $margin = abs($us - $cs);
      ?>
        <tr>
          <td><span class="badge badge-<?= $cls ?>"><?= $icon ?> <?= $label ?></span></td>
          <td style="color:<?= $winner === 'user' ? 'var(--gold)' : 'var(--text)' ?>; font-weight:<?= $winner === 'user' ? '600' : '400' ?>">
            <?= $us ?><?= $winner === 'user' ? ' ✓' : '' ?>
          </td>
          <td style="color:<?= $winner === 'char' ? 'var(--ice)' : 'var(--text)' ?>; font-weight:<?= $winner === 'char' ? '600' : '400' ?>">
            <?= $winner === 'char' ? '✓ ' : '' ?><?= $cs ?>
          </td>
          <td>
            <?php if ($winner === 'user'): ?>
              <span style="color:var(--gold); font-family:'Cinzel',serif; font-size:.8rem;">👤 <?= htmlspecialchars($userName) ?></span>
            <?php elseif ($winner === 'char'): ?>
              <span style="color:var(--ice); font-family:'Cinzel',serif; font-size:.8rem;">🧙 <?= htmlspecialchars($charName) ?></span>
            <?php else: ?>
              <span style="color:var(--muted); font-family:'Cinzel',serif; font-size:.8rem;">⚖ Draw</span>
            <?php endif; ?>
          </td>
          <td style="color:var(--muted)">
            <?= $margin > 0 ? '+' . $margin : '—' ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr style="border-top:2px solid var(--border);">
          <td style="font-family:'Cinzel',serif; font-size:.75rem; letter-spacing:.08em; color:var(--muted);">TOTAL</td>
          <td style="font-family:'Cinzel',serif; color:var(--gold); font-weight:600;"><?= $userTotal ?></td>
          <td style="font-family:'Cinzel',serif; color:var(--ice);  font-weight:600;"><?= $charTotal ?></td>
          <td colspan="2" style="font-family:'Cinzel',serif; font-size:.85rem; color:<?= $winColor ?>; font-weight:600;"><?= $overallLabel ?></td>
        </tr>
      </tfoot>
    </table>
  </div>
</div>

<?php endif; ?>

<?php pageFooter(); ?>