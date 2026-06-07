<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require 'admin_check.php';
require 'db.php';
require 'layout.php';

$db  = getDB();
$act = $_GET['action'] ?? 'list';
$id  = (int)($_GET['id'] ?? 0);

// ── HANDLE POSTS ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name  = trim($_POST['name']  ?? '');
    $email = trim($_POST['email'] ?? '');

    if ($act === 'create') {
        $s = $db->prepare("INSERT INTO users (name,email) VALUES (?,?)");
        $s->execute([$name, $email]);
        redirect('users.php', "User '{$name}' created.");
    }
    if ($act === 'edit') {
        $s = $db->prepare("UPDATE users SET name=?, email=? WHERE id=?");
        $s->execute([$name, $email, $id]);
        redirect('users.php', "User updated.");
    }
}

// ── DELETE ───────────────────────────────────────────────────────────────────
if ($act === 'delete' && $id) {
    $db->prepare("DELETE FROM userAttributes WHERE userid=?")->execute([$id]);
    $db->prepare("DELETE FROM inventory WHERE userid=?")->execute([$id]);
    $db->prepare("DELETE FROM users WHERE id=?")->execute([$id]);
    redirect('users.php', "User deleted.", 'error');
}

pageHeader('Users', 'users.php');
echo flash();

// ── EDIT FORM ────────────────────────────────────────────────────────────────
if ($act === 'edit' && $id) {
    $user = $db->prepare("SELECT * FROM users WHERE id=?");
    $user->execute([$id]);
    $user = $user->fetch();
    if (!$user) redirect('users.php', 'User not found.', 'error');
    ?>
    <h1 class="page-title">Edit User</h1>
    <p class="page-sub">Modify player account details</p>
    <div class="card">
      <form method="post" action="users.php?action=edit&id=<?= $id ?>">
        <div class="form-grid">
          <div class="form-group">
            <label for="name">Name</label>
            <input type="text" id="name" name="name" value="<?= htmlspecialchars($user['name']) ?>" required>
          </div>
          <div class="form-group">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" value="<?= htmlspecialchars($user['email']) ?>">
          </div>
        </div>
        <div class="btn-row">
          <button class="btn btn-primary" type="submit">Save Changes</button>
          <a class="btn btn-outline" href="users.php">Cancel</a>
        </div>
      </form>
    </div>
    <?php
    pageFooter(); exit;
}

// ── CREATE FORM ──────────────────────────────────────────────────────────────
if ($act === 'create') {
    ?>
    <h1 class="page-title">New User</h1>
    <p class="page-sub">Register a new player account</p>
    <div class="card">
      <form method="post" action="users.php?action=create">
        <div class="form-grid">
          <div class="form-group">
            <label for="name">Name</label>
            <input type="text" id="name" name="name" required>
          </div>
          <div class="form-group">
            <label for="email">Email</label>
            <input type="email" id="email" name="email">
          </div>
        </div>
        <div class="btn-row">
          <button class="btn btn-primary" type="submit">Create User</button>
          <a class="btn btn-outline" href="users.php">Cancel</a>
        </div>
      </form>
    </div>
    <?php
    pageFooter(); exit;
}

// ── LIST ─────────────────────────────────────────────────────────────────────
$users = $db->query("SELECT u.*, 
  (SELECT COUNT(*) FROM inventory WHERE userid=u.id) as weapon_count,
  (SELECT COUNT(*) FROM userAttributes WHERE userid=u.id) as skill_count
  FROM users u ORDER BY u.id")->fetchAll();
?>
<h1 class="page-title">Users</h1>
<p class="page-sub">All registered player accounts</p>
<a class="btn btn-primary" href="users.php?action=create">+ Add User</a>
<div class="card" style="margin-top:1.25rem;">
  <div class="tbl-wrap">
  <table>
    <thead>
      <tr><th>ID</th><th>Name</th><th>Email</th><th>Weapons</th><th>Skills</th><th>Created</th><th>Actions</th></tr>
    </thead>
    <tbody>
    <?php foreach ($users as $u): ?>
    <tr>
      <td style="color:var(--muted)"><?= $u['id'] ?></td>
      <td><strong><?= htmlspecialchars($u['name']) ?></strong></td>
      <td><?= htmlspecialchars($u['email']) ?></td>
      <td><?= $u['weapon_count'] ?></td>
      <td><?= $u['skill_count'] ?></td>
      <td style="color:var(--muted); font-size:.85rem"><?= $u['created_at'] ?></td>
      <td>
        <a class="btn btn-outline btn-sm" href="users.php?action=edit&id=<?= $u['id'] ?>">Edit</a>
        <a class="btn btn-danger btn-sm" href="users.php?action=delete&id=<?= $u['id'] ?>"
           onclick="return confirm('Delete this user and all their data?')">Delete</a>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>
<?php pageFooter(); ?>
