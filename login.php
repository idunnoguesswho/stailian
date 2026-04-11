<?php
if (session_status() === PHP_SESSION_NONE) session_start();

if (!empty($_SESSION['userid'])) {
    header('Location: walk.php');
    exit();
}

require 'db.php';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password']      ?? '';
    $db       = getDB();

    $stmt = $db->prepare("SELECT * FROM users WHERE username=?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['userid']   = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['name']     = $user['name'];
        $_SESSION['is_admin'] = (int)$user['is_admin'];
        header("Location: walk.php");
        exit();
    } else {
        $error = 'Invalid username or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login · Stailian</title>
<link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;600;900&family=Crimson+Pro:ital,wght@0,300;0,400;1,300&display=swap" rel="stylesheet">
<link rel="stylesheet" href="stailian.css">
<style>
  .auth-wrap {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 2rem;
  }
  .auth-card {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 2.5rem;
    width: 100%;
    max-width: 380px;
  }
  .auth-logo {
    text-align: center;
    font-family: 'Cinzel', serif;
    font-size: 2rem;
    font-weight: 900;
    color: var(--gold);
    letter-spacing: .12em;
    margin-bottom: .2rem;
  }
  .auth-sub {
    text-align: center;
    color: var(--muted);
    font-style: italic;
    font-size: .9rem;
    margin-bottom: 2rem;
  }
</style>
</head>
<body>
<div class="auth-wrap">
  <div class="auth-card">
    <div class="auth-logo">⚜ STAILIAN</div>
    <div class="auth-sub">Enter the world</div>

    <?php if ($error): ?>
      <div class="alert alert-error" style="margin-bottom:1.25rem;">
        <?= htmlspecialchars($error) ?>
      </div>
    <?php endif; ?>

    <form method="post" action="login.php">
      <div class="form-group" style="margin-bottom:1rem;">
        <label>Username</label>
        <input type="text" name="username" required autofocus
               value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
      </div>
      <div class="form-group" style="margin-bottom:1.5rem;">
        <label>Password</label>
        <input type="password" name="password" required>
      </div>
      <button class="btn btn-primary" type="submit" style="width:100%;">
        Enter the World →
      </button>
    </form>

    <div style="text-align:center; margin-top:1rem;">
      <a href="forgot.php"
         style="font-size:.82rem; color:var(--muted); text-decoration:none;">
        Forgot your password?
      </a>
    </div>

    <div style="font-size:.75rem; color:var(--muted); text-align:center;
         margin-top:.5rem;">
      Default password:
      <code style="background:var(--surface); padding:.1rem .35rem;
           border-radius:3px; color:var(--gold); font-family:monospace;">password</code>
    </div>

    <hr style="border-color:var(--border); margin:1.5rem 0;">

    <div style="text-align:center;">
      <div style="font-size:.82rem; color:var(--muted); margin-bottom:.75rem;">
        New to Stailian?
      </div>
      <a href="register.php" class="btn btn-outline" style="width:100%;">
        ⚜ Create an Account
      </a>
    </div>

  </div>
</div>
</body>
</html>