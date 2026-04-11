<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require 'admin_check.php';
require 'db.php';
require 'layout.php';

$db  = getDB();
$id  = (int)($_GET['id'] ?? 0);

// If no ID given, show a selection list
if (!$id) {
    pageHeader('User Profiles', 'userprofile.php');
    echo flash();
    $users = $db->query("SELECT * FROM users ORDER BY name")->fetchAll();
    ?>
    <h1 class="page-title">User Profiles</h1>
    <p class="page-sub">Select a user to view their full profile</p>
    <div class="grid-3">
      <?php foreach ($users as $u): ?>
      <a href="userprofile.php?id=<?= $u['id'] ?>" style="text-decoration:none;">
        <div class="card" style="text-align:center; cursor:pointer; transition: border-color .2s;"
             onmouseenter="this.style.borderColor='var(--gold-dim)'"
             onmouseleave="this.style.borderColor='var(--border)'">
          <div style="font-size:2.5rem; margin-bottom:.5rem;">👤</div>
          <div style="font-family:'Cinzel',serif; font-size:1.1rem; color:var(--gold);"><?= htmlspecialchars($u['name']) ?></div>
          <div style="font-size:.85rem; color:var(--muted); font-style:italic; margin-top:.25rem;"><?= htmlspecialchars($u['email']) ?></div>
        </div>
      </a>
      <?php endforeach; ?>
    </div>
    <?php
    pageFooter(); exit;
}

// ── FETCH USER ────────────────────────────────────────────────────────────────
$stmt = $db->prepare("SELECT * FROM users WHERE id=?");
$stmt->execute([$id]);
$user = $stmt->fetch();
if (!$user) {
    pageHeader('Not Found', '');
    echo '<h1 class="page-title">User not found.</h1><a class="btn btn-outline" href="userprofile.php">← Back</a>';
    pageFooter(); exit;
}

// ── FETCH WEAPONS ─────────────────────────────────────────────────────────────
$weaponStmt = $db->prepare("
    SELECT w.name, w.score, w.weapon_description
    FROM inventory i
    JOIN weapons w ON w.id = i.weaponid
    WHERE i.userid = ?
    ORDER BY w.name
");
$weaponStmt->execute([$id]);
$weapons = $weaponStmt->fetchAll();

// ── FETCH SKILLS/ATTRIBUTES ───────────────────────────────────────────────────
$skillStmt = $db->prepare("
    SELECT s.name, s.iceScore, s.groundScore, s.fireScore, s.waterScore, s.darkScore
    FROM userAttributes ua
    JOIN skills s ON s.id = ua.skillid
    WHERE ua.userid = ?
    ORDER BY s.name
");
$skillStmt->execute([$id]);
$skills = $skillStmt->fetchAll();

// ── FETCH WEAPON SKILL BONUSES ────────────────────────────────────────────────
$weaponSkillStmt = $db->prepare("
    SELECT s.name as skillname, w.name as weaponname,
           s.iceScore, s.groundScore, s.fireScore, s.waterScore, s.darkScore
    FROM inventory i
    JOIN weapons w ON w.id = i.weaponid
    JOIN weaponAttributes wa ON wa.weaponid = w.id
    JOIN skills s ON s.id = wa.skillid
    WHERE i.userid = ?
");
$weaponSkillStmt->execute([$id]);
$weaponSkills = $weaponSkillStmt->fetchAll();

// ── CALCULATE TOTALS ──────────────────────────────────────────────────────────
$totals = ['ice' => 0, 'ground' => 0, 'fire' => 0, 'water' => 0, 'dark' => 0];

foreach ($skills as $s) {
    $totals['ice']    += $s['iceScore'];
    $totals['ground'] += $s['groundScore'];
    $totals['fire']   += $s['fireScore'];
    $totals['water']  += $s['waterScore'];
    $totals['dark']   += $s['darkScore'];
}
foreach ($weaponSkills as $ws) {
    $totals['ice']    += $ws['iceScore'];
    $totals['ground'] += $ws['groundScore'];
    $totals['fire']   += $ws['fireScore'];
    $totals['water']  += $ws['waterScore'];
    $totals['dark']   += $ws['darkScore'];
}

$grandTotal = array_sum($totals);
$elements = [
    'ice'    => ['❄',  'Ice',    'ice'],
    'ground' => ['🌍', 'Ground', 'ground'],
    'fire'   => ['🔥', 'Fire',   'fire'],
    'water'  => ['💧', 'Water',  'water'],
    'dark'   => ['🌑', 'Dark',   'dark'],
];

pageHeader('User Profile — ' . htmlspecialchars($user['name']), 'userprofile.php');
?>

<div style="display:flex; align-items:center; gap:1rem; margin-bottom:.25rem;">
  <a class="btn btn-outline btn-sm" href="userprofile.php">← All Users</a>
  <a class="btn btn-outline btn-sm" href="users.php?action=edit&id=<?= $id ?>">Edit User</a>
</div>

<!-- PROFILE HEADER -->
<div class="card" style="margin-top:1.25rem; display:flex; align-items:center; gap:2rem; flex-wrap:wrap;">
  <div style="font-size:5rem; line-height:1;">👤</div>
  <div style="flex:1;">
    <h1 class="page-title" style="margin-bottom:.1rem;"><?= htmlspecialchars($user['name']) ?></h1>
    <p style="color:var(--muted); font-size:1rem;">✉ <?= htmlspecialchars($user['email']) ?></p>
    <p style="color:var(--muted); font-size:.8rem; margin-top:.5rem;">Member since <?= $user['created_at'] ?></p>
  </div>
  <div style="text-align:center; padding:1rem 1.5rem; border:1px solid var(--border); border-radius:var(--radius); background:var(--surface);">
    <div style="font-family:'Cinzel',serif; font-size:2rem; color:var(--gold); font-weight:600;"><?= $grandTotal ?></div>
    <div style="font-family:'Cinzel',serif; font-size:.7rem; letter-spacing:.1em; color:var(--muted);">TOTAL POWER</div>
  </div>
</div>

<!-- ELEMENTAL SCORES -->
<h2 class="page-title" style="font-size:1.1rem; margin-bottom:1rem;">Elemental Power</h2>
<div class="grid-3" style="margin-bottom:1.5rem;">
  <?php foreach ($elements as $key => [$icon, $label, $cls]):
    $score = $totals[$key];
    $pct   = $grandTotal > 0 ? round(($score / $grandTotal) * 100) : 0;
  ?>
  <div class="card" style="text-align:center; padding:1.25rem;">
    <div style="font-size:1.8rem;"><?= $icon ?></div>
    <div style="font-family:'Cinzel',serif; font-size:1.6rem; color:var(--<?= $cls ?>); font-weight:600; margin:.25rem 0;"><?= $score ?></div>
    <div style="font-family:'Cinzel',serif; font-size:.7rem; letter-spacing:.1em; color:var(--muted); margin-bottom:.75rem;"><?= strtoupper($label) ?></div>
    <div style="background:var(--border); border-radius:20px; height:6px; overflow:hidden;">
      <div style="background:var(--<?= $cls ?>); width:<?= $pct ?>%; height:100%; border-radius:20px; transition:width .4s;"></div>
    </div>
    <div style="font-size:.75rem; color:var(--muted); margin-top:.35rem;"><?= $pct ?>%</div>
  </div>
  <?php endforeach; ?>
</div>

<div class="grid-2">

  <!-- WEAPONS -->
  <div>
    <h2 class="page-title" style="font-size:1.1rem; margin-bottom:1rem;">🗡 Weapons Carried</h2>
    <div class="card">
      <?php if ($weapons): ?>
      <div class="tbl-wrap">
        <table>
          <thead><tr><th>Weapon</th><th>Score</th><th>Description</th></tr></thead>
          <tbody>
          <?php foreach ($weapons as $w): ?>
            <tr>
              <td><strong><?= htmlspecialchars($w['name']) ?></strong></td>
              <td><span style="color:var(--gold)"><?= $w['score'] ?></span></td>
              <td style="color:var(--muted); font-size:.9rem"><?= htmlspecialchars($w['weapon_description'] ?? '') ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php else: ?>
        <p style="color:var(--muted); font-style:italic; text-align:center;">No weapons assigned</p>
      <?php endif; ?>
    </div>
  </div>

  <!-- SKILLS -->
  <div>
    <h2 class="page-title" style="font-size:1.1rem; margin-bottom:1rem;">✨ Skills & Attributes</h2>
    <div class="card">
      <?php if ($skills): ?>
      <div class="tbl-wrap">
        <table>
          <thead><tr><th>Skill</th><th>❄</th><th>🌍</th><th>🔥</th><th>💧</th><th>🌑</th></tr></thead>
          <tbody>
          <?php foreach ($skills as $s): ?>
            <tr>
              <td><strong><?= htmlspecialchars($s['name']) ?></strong></td>
              <?php foreach (['iceScore','groundScore','fireScore','waterScore','darkScore'] as $i => $sc):
                $els = ['ice','ground','fire','water','dark']; ?>
                <td><?= $s[$sc] > 0 ? '<span class="badge badge-'.$els[$i].'">'.$s[$sc].'</span>' : '—' ?></td>
              <?php endforeach; ?>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php else: ?>
        <p style="color:var(--muted); font-style:italic; text-align:center;">No skills assigned</p>
      <?php endif; ?>
    </div>
  </div>

</div>

<!-- WEAPON SKILLS BREAKDOWN -->
<?php if ($weaponSkills): ?>
<h2 class="page-title" style="font-size:1.1rem; margin-bottom:1rem;">⚔ Weapon Skill Bonuses</h2>
<div class="card">
  <div class="tbl-wrap">
    <table>
      <thead><tr><th>Weapon</th><th>Skill</th><th>❄</th><th>🌍</th><th>🔥</th><th>💧</th><th>🌑</th></tr></thead>
      <tbody>
      <?php foreach ($weaponSkills as $ws): ?>
        <tr>
          <td style="color:var(--muted)"><?= htmlspecialchars($ws['weaponname']) ?></td>
          <td><strong><?= htmlspecialchars($ws['skillname']) ?></strong></td>
          <?php foreach (['iceScore','groundScore','fireScore','waterScore','darkScore'] as $i => $sc):
            $els = ['ice','ground','fire','water','dark']; ?>
            <td><?= $ws[$sc] > 0 ? '<span class="badge badge-'.$els[$i].'">'.$ws[$sc].'</span>' : '—' ?></td>
          <?php endforeach; ?>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php pageFooter(); ?>