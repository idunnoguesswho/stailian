<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require 'db.php';
require 'layout.php';

$db    = getDB();
$token = trim($_GET['token'] ?? '');
$msg   = '';
$error = '';
$user  = null;
$valid = false;

// Validate token
if ($token) {
    $stmt = $db->prepare("SELECT * FROM users
        WHERE reset_token=?
        AND reset_token_expires > NOW()");
    $stmt->execute([$token]);
    $user = $stmt->fetch();
    $valid = (bool)$user;
}

if (!$valid && !$msg) {
    $error = 'This reset link is invalid or has expired. Please request a new one.';
}

// Handle password reset submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $valid) {
    $password  = $_POST['password']  ?? '';
    $password2 = $_POST['password2'] ?? '';

    if (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($password !== $password2) {
        $error = 'Passwords do not match.';
    } else {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $db->prepare("UPDATE users SET password_hash=?, reset_token=NULL,
                      reset_token_expires=NULL WHERE id=?")
           ->execute([$hash, $user['id']]);

        // Auto-login
        $_SESSION['userid']   = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['name']     = $user['name'];

        $msg = 'Password updated successfully! Redirecting...';
        header("Refresh: 2; url=walk.php");
    }
}
?>
<?php authHeader('Reset Password', 'Reset your password'); ?>

    <?php if ($msg): ?>
      <div class="alert alert-success"><?= htmlspecialchars($msg) ?></div>
      <div style="text-align:center; margin-top:1rem;">
        <a href="walk.php" class="btn btn-primary">Enter the World →</a>
      </div>

    <?php elseif ($error && !$valid): ?>
      <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
      <div style="text-align:center; margin-top:1.25rem;">
        <a href="forgot.php" class="btn btn-outline">Request New Link</a>
        &nbsp;
        <a href="login.php" class="btn btn-outline">← Login</a>
      </div>

    <?php else: ?>
      <?php if ($error): ?>
        <div class="alert alert-error" style="margin-bottom:1rem;">
          <?= htmlspecialchars($error) ?>
        </div>
      <?php endif; ?>

      <p style="font-size:.88rem; color:var(--muted); margin-bottom:1.5rem; line-height:1.7;">
        Resetting password for
        <strong style="color:var(--gold)"><?= htmlspecialchars($user['name']) ?></strong>.
        Choose a new password of at least 6 characters.
      </p>

      <form method="post" action="reset.php?token=<?= htmlspecialchars($token) ?>">

        <div class="form-group" style="margin-bottom:1rem;">
          <label>New Password</label>
          <input type="password" name="password" id="password"
                 required minlength="6" autofocus
                 oninput="checkStrength(this.value)">
          <div class="strength-bar">
            <div class="strength-fill" id="strengthFill"></div>
          </div>
          <div id="strengthLabel"
               style="font-size:.7rem; color:var(--muted); margin-top:.2rem;"></div>
        </div>

        <div class="form-group" style="margin-bottom:1.5rem;">
          <label>Confirm Password</label>
          <input type="password" name="password2" id="password2"
                 required minlength="6"
                 oninput="checkMatch()">
          <div id="matchLabel"
               style="font-size:.7rem; margin-top:.2rem;"></div>
        </div>

        <button class="btn btn-primary" type="submit" style="width:100%;">
          Set New Password
        </button>
      </form>

      <div style="text-align:center; margin-top:1rem;">
        <a href="login.php" style="font-size:.82rem; color:var(--muted); text-decoration:none;">
          ← Back to Login
        </a>
      </div>

    <?php endif; ?>
  </div>
</div>

<script>
function checkStrength(pw) {
  const fill  = document.getElementById('strengthFill');
  const label = document.getElementById('strengthLabel');
  let score = 0;
  if (pw.length >= 6)  score++;
  if (pw.length >= 10) score++;
  if (/[A-Z]/.test(pw)) score++;
  if (/[0-9]/.test(pw)) score++;
  if (/[^A-Za-z0-9]/.test(pw)) score++;

  const levels = [
    { pct:'10%', color:'var(--danger)',  text:'Too short' },
    { pct:'25%', color:'var(--danger)',  text:'Weak' },
    { pct:'50%', color:'var(--fire)',    text:'Fair' },
    { pct:'75%', color:'var(--gold)',    text:'Good' },
    { pct:'100%',color:'var(--success)','text':'Strong' },
  ];
  const lvl = levels[Math.min(score, 4)];
  fill.style.width      = lvl.pct;
  fill.style.background = lvl.color;
  label.style.color     = lvl.color;
  label.textContent     = pw.length ? lvl.text : '';
}

function checkMatch() {
  const pw  = document.getElementById('password').value;
  const pw2 = document.getElementById('password2').value;
  const lbl = document.getElementById('matchLabel');
  if (!pw2) { lbl.textContent = ''; return; }
  if (pw === pw2) {
    lbl.style.color   = 'var(--success)';
    lbl.textContent   = '✓ Passwords match';
  } else {
    lbl.style.color   = 'var(--danger)';
    lbl.textContent   = '✗ Passwords do not match';
  }
}
</script>
<?php authFooter(); ?>