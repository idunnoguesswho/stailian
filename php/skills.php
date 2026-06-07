<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require 'admin_check.php';
require 'db.php';
require 'layout.php';

$db  = getDB();
$act = $_GET['action'] ?? 'list';
$id  = (int)($_GET['id'] ?? 0);

// ── POST HANDLER ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name   = trim($_POST['name'] ?? '');
    $charC  = isset($_POST['charCarry']) ? 1 : 0;
    $userC  = isset($_POST['userCarry']) ? 1 : 0;
    $ice    = (int)($_POST['iceScore']    ?? 0);
    $ground = (int)($_POST['groundScore'] ?? 0);
    $fire   = (int)($_POST['fireScore']   ?? 0);
    $water  = (int)($_POST['waterScore']  ?? 0);
    $dark   = (int)($_POST['darkScore']   ?? 0);

    if ($act === 'create') {
        try {
            $db->prepare("INSERT INTO skills (name,charCarry,userCarry,iceScore,groundScore,fireScore,waterScore,darkScore)
                          VALUES (?,?,?,?,?,?,?,?)")
               ->execute([$name,$charC,$userC,$ice,$ground,$fire,$water,$dark]);
            redirect('skills.php', "Skill '{$name}' created.");
        } catch (PDOException $e) {
            $error = $e->getCode() === '23000'
                ? "A skill named '{$name}' already exists."
                : $e->getMessage();
        }
    }

    if ($act === 'edit') {
        try {
            $db->prepare("UPDATE skills SET name=?,charCarry=?,userCarry=?,iceScore=?,groundScore=?,fireScore=?,waterScore=?,darkScore=? WHERE id=?")
               ->execute([$name,$charC,$userC,$ice,$ground,$fire,$water,$dark,$id]);
            redirect('skills.php', "Skill '{$name}' updated.");
        } catch (PDOException $e) {
            $error = $e->getCode() === '23000'
                ? "A skill named '{$name}' already exists."
                : $e->getMessage();
        }
    }
}

// ── DELETE ────────────────────────────────────────────────────────────────────
if ($act === 'delete' && $id) {
    $db->prepare("DELETE FROM userAttributes  WHERE skillid=?")->execute([$id]);
    $db->prepare("DELETE FROM charAttributes  WHERE skillid=?")->execute([$id]);
    $db->prepare("DELETE FROM weaponAttributes WHERE skillid=?")->execute([$id]);
    $db->prepare("DELETE FROM skills WHERE id=?")->execute([$id]);
    redirect('skills.php', "Skill deleted.", 'error');
}

pageHeader('Skills', 'skills.php');
echo flash();

if (!empty($error)) {
    echo '<div class="alert alert-error">' . htmlspecialchars($error) . '</div>';
}

// ── FORM HELPER ───────────────────────────────────────────────────────────────
function skillForm(array $sk = [], string $action = 'create', int $id = 0): void {
    $elements = [
        'ice'    => ['❄',  'Ice',    'ice'],
        'ground' => ['🌍', 'Ground', 'ground'],
        'fire'   => ['🔥', 'Fire',   'fire'],
        'water'  => ['💧', 'Water',  'water'],
        'dark'   => ['🌑', 'Dark',   'dark'],
    ];
    ?>
    <div class="card">
      <form method="post" action="skills.php?action=<?= $action ?>&id=<?= $id ?>">

        <!-- NAME -->
        <div class="form-grid" style="margin-bottom:1.25rem;">
          <div class="form-group">
            <label>Skill Name</label>
            <input type="text" name="name" value="<?= htmlspecialchars($sk['name'] ?? '') ?>" required>
          </div>
          <div class="form-group" style="justify-content:flex-end;">
            <label>&nbsp;</label>
            <div style="display:flex; gap:2rem; align-items:center; height:100%;">
              <div class="checkbox-row">
                <input type="checkbox" id="charCarry" name="charCarry" value="1"
                  <?= !empty($sk['charCarry']) ? 'checked' : '' ?>>
                <label for="charCarry">🧙 Character Can Use</label>
              </div>
              <div class="checkbox-row">
                <input type="checkbox" id="userCarry" name="userCarry" value="1"
                  <?= !empty($sk['userCarry']) ? 'checked' : '' ?>>
                <label for="userCarry">👤 User Can Use</label>
              </div>
            </div>
          </div>
        </div>

        <!-- ELEMENTAL SCORES -->
        <div class="card-title">Elemental Scores</div>
        <div style="display:grid; grid-template-columns:repeat(5,1fr); gap:1rem; margin-bottom:1.25rem;">
          <?php foreach ($elements as $key => [$icon, $label, $cls]): ?>
          <div class="form-group">
            <label style="color:var(--<?= $cls ?>)"><?= $icon ?> <?= $label ?></label>
            <input type="number" name="<?= $key ?>Score"
                   value="<?= (int)($sk[$key.'Score'] ?? 0) ?>" min="0" max="999">
          </div>
          <?php endforeach; ?>
        </div>

        <div class="btn-row">
          <button class="btn btn-primary" type="submit">
            <?= $action === 'create' ? '+ Create Skill' : 'Save Changes' ?>
          </button>
          <a class="btn btn-outline" href="skills.php">Cancel</a>
        </div>
      </form>
    </div>
    <?php
}

// ── EDIT ─────────────────────────────────────────────────────────────────────
if ($act === 'edit' && $id) {
    $sk = $db->prepare("SELECT * FROM skills WHERE id=?");
    $sk->execute([$id]);
    $sk = $sk->fetch();
    if (!$sk) redirect('skills.php', 'Skill not found.', 'error');
    echo '<h1 class="page-title">Edit Skill</h1>';
    echo '<p class="page-sub">Modify elemental scores and carry permissions</p>';
    skillForm($sk, 'edit', $id);
    pageFooter(); exit;
}

// ── CREATE ────────────────────────────────────────────────────────────────────
if ($act === 'create') {
    echo '<h1 class="page-title">New Skill</h1>';
    echo '<p class="page-sub">Define a new elemental ability</p>';
    skillForm([], 'create');
    pageFooter(); exit;
}

// ── LIST ─────────────────────────────────────────────────────────────────────
$skills = $db->query("
    SELECT s.*,
        (SELECT COUNT(*) FROM userAttributes   WHERE skillid=s.id) as user_count,
        (SELECT COUNT(*) FROM charAttributes   WHERE skillid=s.id) as char_count,
        (SELECT COUNT(*) FROM weaponAttributes WHERE skillid=s.id) as weapon_count
    FROM skills s ORDER BY s.name
")->fetchAll();

$elements = [
    'ice'    => ['❄',  'ice'],
    'ground' => ['🌍', 'ground'],
    'fire'   => ['🔥', 'fire'],
    'water'  => ['💧', 'water'],
    'dark'   => ['🌑', 'dark'],
];
?>
<h1 class="page-title">Skills</h1>
<p class="page-sub">Elemental abilities available in the world</p>
<a class="btn btn-primary" href="skills.php?action=create">+ New Skill</a>

<div class="card" style="margin-top:1.25rem;">
  <div class="tbl-wrap">
    <table>
      <thead>
        <tr>
          <th>Name</th>
          <th>🧙 Char</th>
          <th>👤 User</th>
          <th>❄ Ice</th>
          <th>🌍 Ground</th>
          <th>🔥 Fire</th>
          <th>💧 Water</th>
          <th>🌑 Dark</th>
          <th>Used By</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($skills as $sk): ?>
        <tr>
          <td><strong><?= htmlspecialchars($sk['name']) ?></strong></td>
          <td><?= $sk['charCarry'] ? '<span style="color:var(--success)">✓</span>' : '<span style="color:var(--muted)">—</span>' ?></td>
          <td><?= $sk['userCarry'] ? '<span style="color:var(--success)">✓</span>' : '<span style="color:var(--muted)">—</span>' ?></td>
          <?php foreach ($elements as $key => [$icon, $cls]): ?>
            <td>
              <?php if ($sk[$key.'Score'] > 0): ?>
                <span class="badge badge-<?= $cls ?>"><?= $sk[$key.'Score'] ?></span>
              <?php else: ?>
                <span style="color:var(--muted)">—</span>
              <?php endif; ?>
            </td>
          <?php endforeach; ?>
          <td style="font-size:.85rem; color:var(--muted);">
            <?php
              $used = [];
              if ($sk['user_count'])   $used[] = $sk['user_count']   . ' user' .   ($sk['user_count']   > 1 ? 's' : '');
              if ($sk['char_count'])   $used[] = $sk['char_count']   . ' char' .   ($sk['char_count']   > 1 ? 's' : '');
              if ($sk['weapon_count']) $used[] = $sk['weapon_count'] . ' weapon' . ($sk['weapon_count'] > 1 ? 's' : '');
              echo $used ? implode(', ', $used) : '—';
            ?>
          </td>
          <td>
            <a class="btn btn-outline btn-sm" href="skills.php?action=edit&id=<?= $sk['id'] ?>">Edit</a>
            <a class="btn btn-danger btn-sm"
               href="skills.php?action=delete&id=<?= $sk['id'] ?>"
               onclick="return confirm('Delete this skill? It will be removed from all users, characters and weapons.')">
               Delete
            </a>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$skills): ?>
        <tr><td colspan="10" style="text-align:center; color:var(--muted); font-style:italic;">No skills defined yet</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php pageFooter(); ?>