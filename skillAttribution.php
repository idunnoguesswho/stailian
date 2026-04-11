<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require 'admin_check.php';
require 'db.php';
require 'layout.php';

$db  = getDB();
$act = $_GET['action'] ?? 'list';
$id  = (int)($_GET['id'] ?? 0);
$tab = $_GET['tab'] ?? 'user';

// ── POST HANDLER ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tab     = $_POST['tab'] ?? 'user';
    $skillid = (int)$_POST['skillid'];

    if ($tab === 'user') {
        $userid = (int)$_POST['ownerid'];

        // Validate userCarry
        $check = $db->prepare("SELECT userCarry FROM skills WHERE id=?");
        $check->execute([$skillid]);
        $skill = $check->fetch();
        if (!$skill || !$skill['userCarry']) {
            redirect("attributes.php?tab=user", "This skill cannot be used by users.", 'error');
        }

        if ($act === 'create') {
            $db->prepare("INSERT INTO userAttributes (userid,skillid) VALUES (?,?)")->execute([$userid,$skillid]);
            redirect("attributes.php?tab=user", "Skill assigned to user.");
        }
        if ($act === 'edit') {
            $db->prepare("UPDATE userAttributes SET userid=?,skillid=? WHERE id=?")->execute([$userid,$skillid,$id]);
            redirect("attributes.php?tab=user", "Updated.");
        }

    } elseif ($tab === 'char') {
        $charid = (int)$_POST['ownerid'];

        // Validate charCarry
        $check = $db->prepare("SELECT charCarry FROM skills WHERE id=?");
        $check->execute([$skillid]);
        $skill = $check->fetch();
        if (!$skill || !$skill['charCarry']) {
            redirect("attributes.php?tab=char", "This skill cannot be used by characters.", 'error');
        }

        if ($act === 'create') {
            $db->prepare("INSERT INTO charAttributes (charid,skillid) VALUES (?,?)")->execute([$charid,$skillid]);
            redirect("attributes.php?tab=char", "Skill assigned to character.");
        }
        if ($act === 'edit') {
            $db->prepare("UPDATE charAttributes SET charid=?,skillid=? WHERE id=?")->execute([$charid,$skillid,$id]);
            redirect("attributes.php?tab=char", "Updated.");
        }

    } else {
        // Weapons can use any skill — no carry restriction
        $weaponid = (int)$_POST['ownerid'];
        if ($act === 'create') {
            $db->prepare("INSERT INTO weaponAttributes (weaponid,skillid) VALUES (?,?)")->execute([$weaponid,$skillid]);
            redirect("attributes.php?tab=weapon", "Skill assigned to weapon.");
        }
        if ($act === 'edit') {
            $db->prepare("UPDATE weaponAttributes SET weaponid=?,skillid=? WHERE id=?")->execute([$weaponid,$skillid,$id]);
            redirect("attributes.php?tab=weapon", "Updated.");
        }
    }
}

if ($act === 'delete' && $id) {
    $tables = ['user'=>'userAttributes','char'=>'charAttributes','weapon'=>'weaponAttributes'];
    $t = $tables[$tab] ?? 'userAttributes';
    $db->prepare("DELETE FROM `$t` WHERE id=?")->execute([$id]);
    redirect("attributes.php?tab=$tab", "Attribute removed.", 'error');
}

// Helper data — filtered by carry flags
$users        = $db->query("SELECT id,name FROM users ORDER BY name")->fetchAll();
$chars        = $db->query("SELECT id,name FROM charbase ORDER BY name")->fetchAll();
$weapons      = $db->query("SELECT id,name FROM weapons ORDER BY name")->fetchAll();
$allSkills    = $db->query("SELECT * FROM skills ORDER BY name")->fetchAll();
$userSkills   = $db->query("SELECT * FROM skills WHERE userCarry=1 ORDER BY name")->fetchAll();
$charSkills   = $db->query("SELECT * FROM skills WHERE charCarry=1 ORDER BY name")->fetchAll();

pageHeader('Attributes', 'attributes.php');
echo flash();

// ── EDIT ─────────────────────────────────────────────────────────────────────
if ($act === 'edit' && $id) {
    $tables = [
        'user'   => ['userAttributes',   'userid',   $users,   $userSkills,  'User',      'userCarry'],
        'char'   => ['charAttributes',   'charid',   $chars,   $charSkills,  'Character', 'charCarry'],
        'weapon' => ['weaponAttributes', 'weaponid', $weapons, $allSkills,   'Weapon',    null],
    ];
    [$tbl, $col, $owners, $filteredSkills, $label, $carryFlag] = $tables[$tab];
    $row = $db->prepare("SELECT * FROM `$tbl` WHERE id=?");
    $row->execute([$id]);
    $row = $row->fetch();
    ?>
    <h1 class="page-title">Edit Attribute</h1>
    <p class="page-sub">
      <?= $carryFlag ? 'Only skills with ' . $label . ' carry enabled are shown' : 'All skills available for weapons' ?>
    </p>
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
            <label>Skill <?= $carryFlag ? '<span style="color:var(--muted); font-size:.8rem">(' . $label . ' Carry only)</span>' : '' ?></label>
            <select name="skillid">
              <?php foreach ($filteredSkills as $sk): ?>
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
    <p class="page-sub">Only eligible skills are shown per carrier type</p>
    <div class="grid-3">

      <!-- User -->
      <div class="card">
        <div class="card-title">👤 To User</div>
        <?php if ($userSkills): ?>
        <form method="post" action="attributes.php?action=create&tab=user">
          <input type="hidden" name="tab" value="user">
          <div class="form-group" style="margin-bottom:1rem">
            <label>User</label>
            <select name="ownerid">
              <?php foreach ($users as $u): ?>
                <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Skill <span style="color:var(--muted); font-size:.8rem">(User Carry only)</span></label>
            <select name="skillid">
              <?php foreach ($userSkills as $sk): ?>
                <option value="<?= $sk['id'] ?>"><?= htmlspecialchars($sk['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="btn-row"><button class="btn btn-primary" type="submit">Assign</button></div>
        </form>
        <?php else: ?>
          <p style="color:var(--muted); font-style:italic; font-size:.9rem">No skills with User Carry enabled.
            <a href="skills.php" style="color:var(--gold)">Go to Skills</a> to enable carry flags.
          </p>
        <?php endif; ?>
      </div>

      <!-- Character -->
      <div class="card">
        <div class="card-title">🧙 To Character</div>
        <?php if ($charSkills): ?>
        <form method="post" action="attributes.php?action=create&tab=char">
          <input type="hidden" name="tab" value="char">
          <div class="form-group" style="margin-bottom:1rem">
            <label>Character</label>
            <select name="ownerid">
              <?php foreach ($chars as $c): ?>
                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Skill <span style="color:var(--muted); font-size:.8rem">(Char Carry only)</span></label>
            <select name="skillid">
              <?php foreach ($charSkills as $sk): ?>
                <option value="<?= $sk['id'] ?>"><?= htmlspecialchars($sk['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="btn-row"><button class="btn btn-primary" type="submit">Assign</button></div>
        </form>
        <?php else: ?>
          <p style="color:var(--muted); font-style:italic; font-size:.9rem">No skills with Char Carry enabled.
            <a href="skills.php" style="color:var(--gold)">Go to Skills</a> to enable carry flags.
          </p>
        <?php endif; ?>
      </div>

      <!-- Weapon -->
      <div class="card">
        <div class="card-title">🗡 To Weapon</div>
        <form method="post" action="attributes.php?action=create&tab=weapon">
          <input type="hidden" name="tab" value="weapon">
          <div class="form-group" style="margin-bottom:1rem">
            <label>Weapon</label>
            <select name="ownerid">
              <?php foreach ($weapons as $w): ?>
                <option value="<?= $w['id'] ?>"><?= htmlspecialchars($w['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Skill <span style="color:var(--muted); font-size:.8rem">(All skills available)</span></label>
            <select name="skillid">
              <?php foreach ($allSkills as $sk): ?>
                <option value="<?= $sk['id'] ?>"><?= htmlspecialchars($sk['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="btn-row"><button class="btn btn-primary" type="submit">Assign</button></div>
        </form>
      </div>

    </div>
    <a class="btn btn-outline" href="attributes.php">← Back</a>
    <?php pageFooter(); exit;
}

// ── LIST ─────────────────────────────────────────────────────────────────────
$userAttr   = $db->query("SELECT ua.id, u.name as owner, s.name as skill,
    s.iceScore, s.groundScore, s.fireScore, s.waterScore, s.darkScore, s.userCarry as carryOk
    FROM userAttributes ua JOIN users u ON u.id=ua.userid JOIN skills s ON s.id=ua.skillid ORDER BY u.name")->fetchAll();

$charAttr   = $db->query("SELECT ca.id, c.name as owner, s.name as skill,
    s.iceScore, s.groundScore, s.fireScore, s.waterScore, s.darkScore, s.charCarry as carryOk
    FROM charAttributes ca JOIN charbase c ON c.id=ca.charid JOIN skills s ON s.id=ca.skillid ORDER BY c.name")->fetchAll();

$weaponAttr = $db->query("SELECT wa.id, w.name as owner, s.name as skill,
    s.iceScore, s.groundScore, s.fireScore, s.waterScore, s.darkScore, 1 as carryOk
    FROM weaponAttributes wa JOIN weapons w ON w.id=wa.weaponid JOIN skills s ON s.id=wa.skillid ORDER BY w.name")->fetchAll();

$tabData   = ['user' => $userAttr, 'char' => $charAttr, 'weapon' => $weaponAttr];
$tabLabels = [
    'user'   => ['👤 Users ('   . count($userAttr)   . ')', 'User'],
    'char'   => ['🧙 Characters (' . count($charAttr) . ')', 'Character'],
    'weapon' => ['🗡 Weapons ('  . count($weaponAttr) . ')', 'Weapon'],
];
?>
<h1 class="page-title">Attributes</h1>
<p class="page-sub">Skill assignments across all entities</p>
<a class="btn btn-primary" href="attributes.php?action=create">+ Assign Skill</a>

<!-- CARRY FLAG LEGEND -->
<div style="display:flex; gap:1rem; margin-top:1rem; flex-wrap:wrap;">
  <div style="font-size:.85rem; color:var(--muted);"><span style="color:var(--success)">✓</span> Carry allowed</div>
  <div style="font-size:.85rem; color:var(--muted);"><span style="color:var(--danger)">⚠</span> Carry flag mismatch — should be removed</div>
</div>

<div style="display:flex; gap:0; margin-top:1rem; border-bottom:1px solid var(--border);">
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
          <th>❄</th><th>🌍</th><th>🔥</th><th>💧</th><th>🌑</th>
          <th>Carry</th>
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
            <?php if ($r['carryOk']): ?>
              <span style="color:var(--success)">✓</span>
            <?php else: ?>
              <span style="color:var(--danger)">⚠</span>
            <?php endif; ?>
          </td>
          <td>
            <a class="btn btn-outline btn-sm" href="attributes.php?action=edit&tab=<?= $tab ?>&id=<?= $r['id'] ?>">Edit</a>
            <a class="btn btn-danger btn-sm" href="attributes.php?action=delete&tab=<?= $tab ?>&id=<?= $r['id'] ?>"
               onclick="return confirm('Remove this attribute?')">Remove</a>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$tabData[$tab]): ?>
        <tr><td colspan="10" style="text-align:center; color:var(--muted); font-style:italic">No skills assigned</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php pageFooter(); ?>