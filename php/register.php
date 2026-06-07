<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require 'layout.php';

// Already logged in — go to walk
if (!empty($_SESSION['userid'])) {
    header('Location: walk.php');
    exit();
}

require 'db.php';

$error  = '';
$msg    = '';
$fields = [
    'name'     => '',
    'username' => '',
    'email'    => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name']     ?? '');
    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email']    ?? '');
    $password = $_POST['password']      ?? '';
    $confirm  = $_POST['confirm']       ?? '';

    // Keep fields populated on error
    $fields = compact('name', 'username', 'email');

    // ── VALIDATE ──────────────────────────────────────────────────────────────
    if (!$name) {
        $error = 'Please enter your name.';
    } elseif (!$username) {
        $error = 'Please choose a username.';
    } elseif (!preg_match('/^[a-zA-Z0-9_]{3,30}$/', $username)) {
        $error = 'Username must be 3–30 characters and contain only letters, numbers or underscores.';
    } elseif (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $db = getDB();

        // Check username taken
        $check = $db->prepare("SELECT id FROM users WHERE username=?");
        $check->execute([$username]);
        if ($check->fetch()) {
            $error = "Username '{$username}' is already taken. Please choose another.";
        } else {
            // Check email taken
            $check2 = $db->prepare("SELECT id FROM users WHERE email=?");
            $check2->execute([$email]);
            if ($check2->fetch()) {
                $error = "An account with that email already exists.";
            } else {
                // ── CREATE USER ───────────────────────────────────────────────
                $hash = password_hash($password, PASSWORD_BCRYPT);

                $db->prepare("INSERT INTO users (name, username, email, password_hash, is_admin)
                              VALUES (?,?,?,?,0)")
                   ->execute([$name, $username, $email, $hash]);

                $newUserId = (int)$db->lastInsertId();

                // Initialise health
                $db->prepare("INSERT INTO user_health (userid, health, max_health, last_heal)
                              VALUES (?,100,100,NOW())")
                   ->execute([$newUserId]);

                // Initialise coins
                $db->prepare("INSERT INTO user_coins (userid, coins) VALUES (?,0)")
                   ->execute([$newUserId]);

                // Initialise stats
                $db->prepare("INSERT INTO user_stats (userid) VALUES (?)")
                   ->execute([$newUserId]);

                // Auto login
                $_SESSION['userid']   = $newUserId;
                $_SESSION['username'] = $username;
                $_SESSION['name']     = $name;
                $_SESSION['is_admin'] = 0;

                header("Location: walk.php?msg=Welcome+to+Stailian,+"
                    . urlencode($name) . "!&type=success");
                exit();
            }
        }
    }
}
?>
<?php authHeader('Create Account', 'Create your account and enter the world'); ?>

    <?php if ($error): ?>
      <div class="alert alert-error" style="margin-bottom:1.25rem;">
        <?= htmlspecialchars($error) ?>
      </div>
    <?php endif; ?>

    <form method="post" action="register.php" id="regForm">

      <!-- NAME -->
      <div class="form-group" style="margin-bottom:1rem;">
        <label>Your Name</label>
        <input type="text" name="name" required autofocus maxlength="100"
               value="<?= htmlspecialchars($fields['name']) ?>"
               placeholder="e.g. Katie Snow">
        <div class="field-hint">Your display name shown in the game</div>
      </div>

      <!-- USERNAME -->
      <div class="form-group" style="margin-bottom:1rem;">
        <label>Username</label>
        <input type="text" name="username" required maxlength="50"
               pattern="[a-zA-Z0-9_]{3,30}"
               value="<?= htmlspecialchars($fields['username']) ?>"
               placeholder="e.g. katie_snow"
               oninput="checkUsername(this.value)">
        <div id="usernameHint" class="field-hint">
          3–30 characters, letters, numbers and underscores only
        </div>
      </div>

      <!-- EMAIL -->
      <div class="form-group" style="margin-bottom:1rem;">
        <label>Email Address</label>
        <input type="email" name="email" required maxlength="100"
               value="<?= htmlspecialchars($fields['email']) ?>"
               placeholder="e.g. katie@example.com">
        <div class="field-hint">Used for password reset only</div>
      </div>

      <hr style="border-color:var(--border); margin:1.25rem 0;">

      <!-- PASSWORD -->
      <div class="form-group" style="margin-bottom:1rem;">
        <label>Password</label>
        <div style="position:relative;">
          <input type="password" name="password" id="pw" required minlength="6"
                 oninput="checkStrength(this.value); checkMatch();"
                 style="padding-right:2.5rem;"
                 placeholder="Min. 6 characters">
          <button type="button" onclick="toggleVis('pw',this)"
                  style="position:absolute; right:.6rem; top:50%; transform:translateY(-50%);
                         background:none; border:none; cursor:pointer;
                         color:var(--muted); font-size:.85rem; padding:0;">👁</button>
        </div>
        <div class="strength-bar">
          <div class="strength-fill" id="strengthFill"></div>
        </div>
        <div id="strengthLabel" style="font-size:.68rem; color:var(--muted); margin-top:.2rem;"></div>
      </div>

      <!-- CONFIRM PASSWORD -->
      <div class="form-group" style="margin-bottom:1.5rem;">
        <label>Confirm Password</label>
        <div style="position:relative;">
          <input type="password" name="confirm" id="pw2" required minlength="6"
                 oninput="checkMatch()"
                 style="padding-right:2.5rem;"
                 placeholder="Repeat password">
          <button type="button" onclick="toggleVis('pw2',this)"
                  style="position:absolute; right:.6rem; top:50%; transform:translateY(-50%);
                         background:none; border:none; cursor:pointer;
                         color:var(--muted); font-size:.85rem; padding:0;">👁</button>
        </div>
        <div id="matchLabel" style="font-size:.68rem; margin-top:.2rem;"></div>
      </div>

      <!-- TERMS NOTE -->
      <div style="font-size:.75rem; color:var(--muted); margin-bottom:1.25rem;
           padding:.65rem .85rem; background:var(--surface); border-radius:var(--radius);
           border:1px solid var(--border); line-height:1.6;">
        By creating an account you agree to play fair and keep your
        login details safe. This is a private game — accounts are for
        authorised players only.
      </div>

      <button class="btn btn-primary" type="submit"
              style="width:100%; font-size:.85rem; padding:.7rem;">
        ⚜ Create Account & Enter the World
      </button>

    </form>

    <div style="text-align:center; margin-top:1.25rem; font-size:.85rem; color:var(--muted);">
      Already have an account?
      <a href="login.php" style="color:var(--gold); text-decoration:none;">Sign in →</a>
    </div>

  </div>

  <!-- FEATURE HIGHLIGHTS -->
  <div style="display:flex; gap:.75rem; flex-wrap:wrap; justify-content:center;
       max-width:440px;">
    <?php foreach ([
      ['🎲','Roll dice to explore a 10×10 elemental map'],
      ['⚔','Battle your character across terrain tiles'],
      ['🎀','Find bowties on magic ground (X=Y) tiles'],
      ['👕','Craft dyes and colour your wardrobe'],
      ['❤','Manage your health — heal or buy packs'],
      ['🃏','Build your Stailian player scorecard'],
    ] as [$icon,$text]): ?>
    <div style="display:flex; align-items:center; gap:.5rem; font-size:.78rem;
         color:var(--muted); background:var(--card); border:1px solid var(--border);
         border-radius:var(--radius); padding:.4rem .7rem; flex:1; min-width:180px;">
      <span style="font-size:1rem; flex-shrink:0;"><?= $icon ?></span>
      <?= $text ?>
    </div>
    <?php endforeach; ?>
  </div>

</div>

<script>
function toggleVis(id, btn) {
  const el = document.getElementById(id);
  el.type  = el.type === 'password' ? 'text' : 'password';
  btn.textContent = el.type === 'password' ? '👁' : '🙈';
}

function checkUsername(val) {
  const hint  = document.getElementById('usernameHint');
  const valid = /^[a-zA-Z0-9_]{3,30}$/.test(val);
  if (!val) {
    hint.style.color   = 'var(--muted)';
    hint.textContent   = '3–30 characters, letters, numbers and underscores only';
  } else if (valid) {
    hint.style.color   = 'var(--success)';
    hint.textContent   = '✓ Username looks good';
  } else {
    hint.style.color   = 'var(--danger)';
    hint.textContent   = '✗ Only letters, numbers and underscores (3–30 chars)';
  }
}

function checkStrength(pw) {
  const fill  = document.getElementById('strengthFill');
  const label = document.getElementById('strengthLabel');
  let score = 0;
  if (pw.length >= 6)              score++;
  if (pw.length >= 10)             score++;
  if (/[A-Z]/.test(pw))           score++;
  if (/[0-9]/.test(pw))           score++;
  if (/[^A-Za-z0-9]/.test(pw))   score++;
  const levels = [
    { w:'10%',  c:'var(--danger)',  t:'Too short'  },
    { w:'25%',  c:'var(--danger)',  t:'Weak'       },
    { w:'50%',  c:'var(--fire)',    t:'Fair'       },
    { w:'75%',  c:'var(--gold)',    t:'Good'       },
    { w:'100%', c:'var(--success)', t:'Strong'     },
  ];
  const lvl        = levels[Math.min(score, 4)];
  fill.style.width      = lvl.w;
  fill.style.background = lvl.c;
  label.style.color     = lvl.c;
  label.textContent     = pw.length ? lvl.t : '';
}

function checkMatch() {
  const pw   = document.getElementById('pw').value;
  const pw2  = document.getElementById('pw2').value;
  const lbl  = document.getElementById('matchLabel');
  if (!pw2) { lbl.textContent = ''; return; }
  if (pw === pw2) {
    lbl.style.color = 'var(--success)';
    lbl.textContent = '✓ Passwords match';
  } else {
    lbl.style.color = 'var(--danger)';
    lbl.textContent = '✗ Passwords do not match';
  }
}
</script>
</body>
</html>