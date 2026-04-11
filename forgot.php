<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require 'db.php';
require 'layout.php';

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
<?php authHeader('Forgot Password', 'Forgot your password?'); ?>

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
<?php authFooter(); ?>