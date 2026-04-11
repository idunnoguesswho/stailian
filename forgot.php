<?php
session_start();
require 'db.php';

$msg   = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $db    = getDB();

    $stmt = $db->prepare("SELECT * FROM users WHERE email=?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        // Generate token
        $token   = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

        $db->prepare("UPDATE users SET reset_token=?, reset_token_expires=? WHERE id=?")
           ->execute([$token, $expires, $user['id']]);

        // Build reset link
        $protocol  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host      = $_SERVER['HTTP_HOST'];
        $resetLink = "{$protocol}://{$host}/reset.php?token={$token}";

        // Send email
        $to      = $user['email'];
        $subject = 'Stailian — Password Reset';
        $body    = "Hello {$user['name']},\r\n\r\n"
                 . "A password reset was requested for your Stailian account.\r\n\r\n"
                 . "Click the link below to reset your password (valid for 1 hour):\r\n"
                 . "{$resetLink}\r\n\r\n"
                 . "If you did not request this, ignore this email.\r\n\r\n"
                 . "Your default password (if you have never changed it) is: password\r\n\r\n"
                 . "— The Stailian World";

        $headers = "From: noreply@stailian.local\r\n"
                 . "Reply-To: noreply@stailian.local\r\n"
                 . "X-Mailer: PHP/" . phpversion();

        $sent = mail($to, $subject, $body, $headers);

        // Always show success to prevent email enumeration
        $msg = "If that email is registered, a reset link has been sent. Check your inbox.";
    } else {
        // Same message to prevent enumeration
        $msg = "If that email is registered, a reset link has been sent. Check your inbox.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Forgot Password · Stailian</title>
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
    max-width: 400px;
  }
  .auth-logo {
    text-align: center;
    font-family: 'Cinzel', serif;
    font-size: 1.8rem;
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
    <div class="auth-sub">Forgot your password?</div>

    <?php if ($msg): ?>
      <div class="alert alert-success" style="margin-bottom:1.25rem;">
        <?= htmlspecialchars($msg) ?>
      </div>
      <div style="text-align:center; margin-top:1rem;">
        <a href="login.php" class="btn btn-outline">← Back to Login</a>
      </div>
    <?php else: ?>

      <p style="font-size:.9rem; color:var(--muted); margin-bottom:1.5rem; line-height:1.7;">
        Enter your registered email address and we will send you a link to reset your password.
        The link expires after <strong style="color:var(--gold)">1 hour</strong>.
      </p>

      <div class="alert alert-success" style="margin-bottom:1.25rem; font-size:.82rem;">
        💡 Your <strong>default password</strong> is: 
        <code style="background:var(--surface); padding:.1rem .4rem; border-radius:3px;
             font-family:monospace; color:var(--gold);">password</code>
        — change it after first login.
      </div>

      <form method="post" action="forgot.php">
        <div class="form-group" style="margin-bottom:1.25rem;">
          <label>Email Address</label>
          <input type="email" name="email" required autofocus
                 value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
        </div>
        <button class="btn btn-primary" type="submit" style="width:100%;">
          Send Reset Link
        </button>
      </form>

      <div style="text-align:center; margin-top:1.25rem;">
        <a href="login.php" style="font-size:.85rem; color:var(--muted); text-decoration:none;">
          ← Back to Login
        </a>
      </div>

    <?php endif; ?>
  </div>
</div>
</body>
</html>