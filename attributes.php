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
$tab = $_GET['tab'] ?? 'user'; // user | char | weapon

// ── POST HANDLER ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tab     = $_POST['tab'] ?? 'user';
    $skillid = (int)$_POST['skillid'];

    if ($tab === 'user') {
        $userid = (int)$_POST['ownerid'];
        if ($act === 'create') {
            $db->prepare("INSERT INTO userAttributes (userid,skillid) VALUES (?,?)")->execute([$userid,$skillid]);
            redirect("attributes.php?tab=user","Skill assigned to user.");
        }
        if ($act === 'edit') {
            $db->prepare("UPDATE userAttributes SET userid=?,skillid=? WHERE id=?")->execute([$userid,$skillid,$id]);
            redirect("attributes.php?tab=user","Updated.");
        }
    } elseif ($tab === 'char') {
        $charid = (int)$_POST['ownerid'];
        if ($act === 'create') {
            $db->prepare("INSERT INTO charAttributes (charid,skillid) VALUES (?,?)")->execute([$charid,$skillid]);
            redirect("attributes.php?tab=char","Skill assigned to character.");
        }
        if ($act === 'edit') {
            $db->prepare("UPDATE charAttributes SET charid=?,skillid=? WHERE id=?")->execute([$charid,$skillid,$id]);
            redirect("attributes.php?tab=char","Updated.");
        }
    } else {
        $weaponid = (int)$_POST['ownerid'];
        if ($act === 'create') {
            $db->prepare("INSERT INTO weaponAttributes (weaponid,skillid) VALUES (?,?)")->execute([$weaponid,$skillid]);
            redirect("attributes.php?tab=weapon","Skill assigned to weapon.");
        }
        if ($act === 'edit') {
            $db->prepare("UPDATE weaponAttributes SET weaponid=?,skillid=? WHERE id=?")->execute([$weaponid,$skillid,$id]);
            redirect("attributes.php?tab=weapon","Updated.");
        }
    }
}

if ($act === 'delete' && $id) {
    $tables = ['user'=>'userAttributes','char'=>'charAttributes','weapon'=>'weaponAttributes'];
    $t = $tables[$tab] ?? 'userAttributes';
    $db->prepare("DELETE FROM `$t` WHERE id=?")->execute([$id]);
    redirect("attributes.php?tab=$tab","Attribute removed.",'error');
}

// Helper data
$users   = $db->query("SELECT id,name FROM users ORDER BY name")->fetchAll();
$chars   = $db->query("SELECT id,name FROM charbase ORDER BY name")->fetchAll();
$weapons = $db->query("SELECT id,name FROM weapons ORDER BY name")->fetchAll();
$skills  = $db->query("SELECT * FROM skills ORDER BY name")->fetchAll();

pageHeader('Attributes', 'attributes.php');
echo flash();

// ── EDIT ─────────────────────────────────────────────────────────────────────
if ($act === 'edit' && $id) {
    $tables = [
        'user'   => ['userAttributes',   'userid',   $users],
        'char'   => ['charAttributes',   'charid',   $chars],
        'weapon' => ['weaponAttributes', 'weaponid', $weapons],
    ];
    [$tbl, $col, $owners] = $tables[$tab];
    $row = $db->prepare("SELECT * FROM `$tbl` WHERE id=?");
    $row->execute([$id]);
    $row = $row->fetch();
    $label = ['user'=>'User','char'=>'Character','weapon'=>'Weapon'][$tab];
    ?>
    <h1 class="page-title">Edit Attribute</h1>
    <div class="card">
      <form method="post" action="attributes.php?action=edit&id=<?= $id ?>&tab=<?= $tab ?>">
        <input type="hidden" name="tab" value="<?= $tab ?>">
        <div class="form-grid">
          <div class="form-group">
            <label><?= $label ?></label>
            <select name="ownerid">
              <?php foreach ($owners as $o): ?>
                <option value="<?= $o['id'] ?>" <?= $row[$col] == $o['id'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($o['name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Skill</label>
            <select name="skillid">
              <?php foreach ($skills as $sk): ?>
                <option value="<?= $sk['id'] ?>" <?= $row['skillid'] == $sk['id'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($sk['name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="btn-row">
          <button class="btn btn-primary" type="submit">Save</button>
          <a class="btn btn-outline" href="attributes.php?tab=<?= $tab ?>">Cancel</a>
        </div>
      </form>
    </div>
    <?php
    pageFooter(); exit;
}

// ── CREATE ────────────────────────────────────────────────────────────────────
if ($act === 'create') { ?>
    <h1 class="page-title">Assign Skill</h1>
    <p class="page-sub">Attach an elemental skill to a user, character, or weapon</p>
    <div class="grid-3">
      <?php
      $panels = [
        ['user',   '👤 To User',      $users,   'User'],
        ['char',   '🧙 To Character', $chars,   'Character'],
        ['weapon', '🗡 To Weapon',    $weapons, 'Weapon'],
      ];
      foreach ($panels as [$t, $title, $owners, $lbl]): ?>
      <div class="card">
        <div class="card-title"><?= $title ?></div>
        <form method="post" action="attributes.php?action=create&tab=<?= $t ?>">
          <input type="hidden" name="tab" value="<?= $t ?>">
          <div class="form-group" style="margin-bottom:1rem">
            <label><?= $lbl ?></label>
            <select name="ownerid">
              <?php foreach ($owners as $o): ?>
                <option value="<?= $o['id'] ?>"><?= htmlspecialchars($o['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Skill</label>
            <select name="skillid">
              <?php foreach ($skills as $sk): ?>
                <option value="<?= $sk['id'] ?>"><?= htmlspecialchars($sk['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="btn-row">
            <button class="btn btn-primary" type="submit">Assign</button>
          </div>
        </form>
      </div>
      <?php endforeach; ?>
    </div>
    <a class="btn btn-outline" href="attributes.php">← Back</a>
    <?php pageFooter(); exit;
}

// ── LIST ─────────────────────────────────────────────────────────────────────
$userAttr   = $db->query("SELECT ua.id, u.name as owner, s.name as skill, s.iceScore, s.groundScore, s.fireScore, s.waterScore, s.darkScore
    FROM userAttributes ua JOIN users u ON u.id=ua.userid JOIN skills s ON s.id=ua.skillid ORDER BY u.name")->fetchAll();
$charAttr   = $db->query("SELECT ca.id, c.name as owner, s.name as skill, s.iceScore, s.groundScore, s.fireScore, s.waterScore, s.darkScore
    FROM charAttributes ca JOIN charbase c ON c.id=ca.charid JOIN skills s ON s.id=ca.skillid ORDER BY c.name")->fetchAll();
$weaponAttr = $db->query("SELECT wa.id, w.name as owner, s.name as skill, s.iceScore, s.groundScore, s.fireScore, s.waterScore, s.darkScore
    FROM weaponAttributes wa JOIN weapons w ON w.id=wa.weaponid JOIN skills s ON s.id=wa.skillid ORDER BY w.name")->fetchAll();

$tabData   = ['user' => $userAttr, 'char' => $charAttr, 'weapon' => $weaponAttr];
$tabLabels = [
    'user'   => ['👤 Users ('   . count($userAttr)   . ')', 'User'],
    'char'   => ['🧙 Characters (' . count($charAttr)   . ')', 'Character'],
    'weapon' => ['🗡 Weapons ('  . count($weaponAttr) . ')', 'Weapon'],
];
?>
<h1 class="page-title">Attributes</h1>
<p class="page-sub">Skill assignments across all entities</p>
<a class="btn btn-primary" href="attributes.php?action=create">+ Assign Skill</a>

<div style="display:flex; gap:0; margin-top:1.5rem; border-bottom:1px solid var(--border);">
  <?php foreach ($tabLabels as $t => [$label, $_]): ?>
  <a href="attributes.php?tab=<?= $t ?>" style="text-decoration:none; font-family:'Cinzel',serif; font-size:.72rem; letter-spacing:.08em;
     padding:.7rem 1.25rem; border-bottom:2px solid <?= $tab===$t ? 'var(--gold)' : 'transparent' ?>;
     color:<?= $tab===$t ? 'var(--gold)' : 'var(--muted)' ?>">
    <?= $label ?>
  </a>
  <?php endforeach; ?>
</div>

<div class="card" style="border-top:none; border-radius:0 0 var(--radius) var(--radius);">
  <div class="tbl-wrap">
    <table>
      <thead>
        <tr>
          <th>ID</th>
          <th><?= $tabLabels[$tab][1] ?></th>
          <th>Skill</th>
          <th>❄ Ice</th>
          <th>🌍 Ground</th>
          <th>🔥 Fire</th>
          <th>💧 Water</th>
          <th>🌑 Dark</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($tabData[$tab] as $r): ?>
        <tr>
          <td style="color:var(--muted)"><?= $r['id'] ?></td>
          <td><strong><?= htmlspecialchars($r['owner']) ?></strong></td>
          <td><?= htmlspecialchars($r['skill']) ?></td>
          <?php foreach (['iceScore','groundScore','fireScore','waterScore','darkScore'] as $i => $sc):
            $els = ['ice','ground','fire','water','dark']; ?>
            <td><?= $r[$sc] > 0 ? '<span class="badge badge-'.$els[$i].'">'.$r[$sc].'</span>' : '—' ?></td>
          <?php endforeach; ?>
          <td>
            <a class="btn btn-outline btn-sm" href="attributes.php?action=edit&tab=<?= $tab ?>&id=<?= $r['id'] ?>">Edit</a>
            <a class="btn btn-danger btn-sm" href="attributes.php?action=delete&tab=<?= $tab ?>&id=<?= $r['id'] ?>"
               onclick="return confirm('Remove this attribute?')">Remove</a>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$tabData[$tab]): ?>
        <tr><td colspan="9" style="text-align:center; color:var(--muted); font-style:italic">No skills assigned</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php pageFooter(); ?>