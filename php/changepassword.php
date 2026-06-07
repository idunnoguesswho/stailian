<?php
require 'auth.php';
require 'db.php';
require 'layout.php';

$db     = getDB();
$userid = $SESSION_USERID;
$error  = '';
$msg    = '';

// Fetch user
$stmt = $db->prepare("SELECT * FROM users WHERE id=?");
$stmt->execute([$userid]);
$user = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current  = $_POST['current_password']  ?? '';
    $new      = $_POST['new_password']      ?? '';
    $confirm  = $_POST['confirm_password']  ?? '';

    // Validate current password
    if (!password_verify($current, $user['password_hash'])) {
        $error = 'Your current password is incorrect.';

    } elseif (strlen($new) < 6) {
        $error = 'New password must be at least 6 characters.';

    } elseif ($new !== $confirm) {
        $error = 'New passwords do not match.';

    } elseif ($new === $current) {
        $error = 'New password must be different from your current password.';

    } else {
        // All good — update password
        $hash = password_hash($new, PASSWORD_BCRYPT);
        $db->prepare("UPDATE users SET password_hash=? WHERE id=?")
           ->execute([$hash, $userid]);
        $msg = 'Password changed successfully!';
    }
}

pageHeader('Change Password', '');
?>

<div style="max-width:460px; margin:2rem auto;">

  <div style="display:flex; align-items:center; gap:1rem; margin-bottom:1.5rem;">
    <h1 class="page-title" style="margin-bottom:0;">🔑 Change Password</h1>
    <a class="btn btn-outline btn-sm" href="walk.php">← Back</a>
  </div>

  <!-- USER BADGE -->
  <div style="display:flex; align-items:center; gap:.75rem; padding:.75rem 1rem;
       background:var(--card); border:1px solid var(--border); border-radius:var(--radius);
       margin-bottom:1.5rem;">
    <div style="font-size:1.8rem;">👤</div>
    <div>
      <div style="font-family:'Cinzel',serif; font-size:.95rem; color:var(--gold);">
        <?= htmlspecialchars($user['name']) ?>
      </div>
      <div style="font-size:.8rem; color:var(--muted);">
        <?= htmlspecialchars($user['email']) ?>
        &nbsp;·&nbsp;
        @<?= htmlspecialchars($user['username']) ?>
      </div>
    </div>
  </div>

  <?php if ($msg): ?>
    <div class="alert alert-success" style="margin-bottom:1.25rem;">
      ✓ <?= htmlspecialchars($msg) ?>
      <div style="margin-top:.5rem;">
        <a href="walk.php" class="btn btn-outline btn-sm">Continue Playing →</a>
      </div>
    </div>

  <?php else: ?>

    <?php if ($error): ?>
      <div class="alert alert-error" style="margin-bottom:1.25rem;">
        <?= htmlspecialchars($error) ?>
      </div>
    <?php endif; ?>

    <div class="card">
      <div class="card-title">Set New Password</div>

      <form method="post" action="changepassword.php" id="pwForm">

        <!-- CURRENT PASSWORD -->
        <div class="form-group" style="margin-bottom:1rem;">
          <label>Current Password</label>
          <div style="position:relative;">
            <input type="password" name="current_password" id="currentPw"
                   required autocomplete="current-password"
                   style="padding-right:2.5rem;">
            <button type="button" onclick="toggleVis('currentPw', this)"
                    style="position:absolute; right:.6rem; top:50%; transform:translateY(-50%);
                           background:none; border:none; cursor:pointer; color:var(--muted);
                           font-size:.85rem; padding:0;">👁</button>
          </div>
        </div>

        <hr style="border-color:var(--border); margin:1.25rem 0;">

        <!-- NEW PASSWORD -->
        <div class="form-group" style="margin-bottom:1rem;">
          <label>New Password</label>
          <div style="position:relative;">
            <input type="password" name="new_password" id="newPw"
                   required minlength="6" autocomplete="new-password"
                   oninput="checkStrength(this.value); checkMatch();"
                   style="padding-right:2.5rem;">
            <button type="button" onclick="toggleVis('newPw', this)"
                    style="position:absolute; right:.6rem; top:50%; transform:translateY(-50%);
                           background:none; border:none; cursor:pointer; color:var(--muted);
                           font-size:.85rem; padding:0;">👁</button>
          </div>
          <!-- STRENGTH BAR -->
          <div style="background:var(--border); border-radius:4px; height:4px;
               overflow:hidden; margin-top:.35rem;">
            <div id="strengthFill"
                 style="height:100%; width:0%; border-radius:4px; transition:width .3s, background .3s;">
            </div>
          </div>
          <div id="strengthLabel"
               style="font-size:.68rem; margin-top:.2rem; color:var(--muted);"></div>
        </div>

        <!-- CONFIRM PASSWORD -->
        <div class="form-group" style="margin-bottom:1.5rem;">
          <label>Confirm New Password</label>
          <div style="position:relative;">
            <input type="password" name="confirm_password" id="confirmPw"
                   required minlength="6" autocomplete="new-password"
                   oninput="checkMatch()"
                   style="padding-right:2.5rem;">
            <button type="button" onclick="toggleVis('confirmPw', this)"
                    style="position:absolute; right:.6rem; top:50%; transform:translateY(-50%);
                           background:none; border:none; cursor:pointer; color:var(--muted);
                           font-size:.85rem; padding:0;">👁</button>
          </div>
          <div id="matchLabel"
               style="font-size:.68rem; margin-top:.2rem;"></div>
        </div>

        <!-- PASSWORD RULES -->
        <div style="background:var(--surface); border-radius:var(--radius);
             padding:.65rem .85rem; margin-bottom:1.25rem; font-size:.75rem;
             color:var(--muted); line-height:1.8; border:1px solid var(--border);">
          <div style="font-family:'Cinzel',serif; font-size:.65rem; letter-spacing:.08em;
               color:var(--gold-dim); margin-bottom:.25rem;">PASSWORD RULES</div>
          <div id="rule1"  class="rule">◦ At least 6 characters</div>
          <div id="rule2"  class="rule">◦ At least one uppercase letter</div>
          <div id="rule3"  class="rule">◦ At least one number</div>
          <div id="rule4"  class="rule">◦ Different from current password</div>
        </div>

        <button class="btn btn-primary" type="submit" id="submitBtn"
                style="width:100%;">
          🔑 Change Password
        </button>

      </form>
    </div>

    <!-- DEFAULT PASSWORD REMINDER -->
    <div style="margin-top:1rem; font-size:.78rem; color:var(--muted);
         text-align:center; font-style:italic;">
      Default password is
      <code style="background:var(--surface); padding:.1rem .35rem; border-radius:3px;
           color:var(--gold); font-family:monospace; font-style:normal;">password</code>
      — change it if you haven't already.
    </div>

  <?php endif; ?>

</div>

<script>
function toggleVis(inputId, btn) {
  const input = document.getElementById(inputId);
  if (input.type === 'password') {
    input.type = 'text';
    btn.textContent = '🙈';
  } else {
    input.type = 'password';
    btn.textContent = '👁';
  }
}

function checkStrength(pw) {
  const fill  = document.getElementById('strengthFill');
  const label = document.getElementById('strengthLabel');
  const r1    = document.getElementById('rule1');
  const r2    = document.getElementById('rule2');
  const r3    = document.getElementById('rule3');

  let score = 0;
  const hasLength  = pw.length >= 6;
  const hasUpper   = /[A-Z]/.test(pw);
  const hasNumber  = /[0-9]/.test(pw);
  const hasSpecial = /[^A-Za-z0-9]/.test(pw);
  const hasLong    = pw.length >= 10;

  if (hasLength)  score++;
  if (hasUpper)   score++;
  if (hasNumber)  score++;
  if (hasSpecial) score++;
  if (hasLong)    score++;

  // Update rule indicators
  r1.style.color = hasLength ? 'var(--success)' : 'var(--muted)';
  r1.textContent = (hasLength ? '✓' : '◦') + ' At least 6 characters';
  r2.style.color = hasUpper  ? 'var(--success)' : 'var(--muted)';
  r2.textContent = (hasUpper ? '✓' : '◦') + ' At least one uppercase letter';
  r3.style.color = hasNumber ? 'var(--success)' : 'var(--muted)';
  r3.textContent = (hasNumber? '✓' : '◦') + ' At least one number';

  const levels = [
    { pct:'10%',  color:'var(--danger)',  text:'Too short'  },
    { pct:'25%',  color:'var(--danger)',  text:'Weak'       },
    { pct:'50%',  color:'var(--fire)',    text:'Fair'       },
    { pct:'75%',  color:'var(--gold)',    text:'Good'       },
    { pct:'100%', color:'var(--success)', text:'Strong'     },
  ];
  const lvl = levels[Math.min(score, 4)];
  fill.style.width      = lvl.pct;
  fill.style.background = lvl.color;
  label.style.color     = lvl.color;
  label.textContent     = pw.length ? lvl.text : '';
}

function checkMatch() {
  const pw   = document.getElementById('newPw').value;
  const pw2  = document.getElementById('confirmPw').value;
  const lbl  = document.getElementById('matchLabel');
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

<style>
  .rule { transition: color .2s; }
</style>

<?php pageFooter(); ?>