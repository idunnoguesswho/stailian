<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// layout.php — shared header/footer helpers

function getNavItems(): array {
    try {
        $db      = getDB();
        $isAdmin = !empty($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1;
        $isLoggedIn = !empty($_SESSION['userid']);

        if ($isAdmin) {
            // Admin sees everything
            return $db->query("SELECT * FROM nav_menu
                WHERE is_active = 1
                ORDER BY sort_order ASC")->fetchAll();
        } elseif ($isLoggedIn) {
            // Logged-in regular user sees non-admin pages only
            return $db->query("SELECT * FROM nav_menu
                WHERE is_active = 1
                AND admin_only = 0
                ORDER BY sort_order ASC")->fetchAll();
        } else {
            // Not logged in — only show login and rules
            return $db->query("SELECT * FROM nav_menu
                WHERE is_active = 1
                AND url IN ('login.php','rules.php')
                ORDER BY sort_order ASC")->fetchAll();
        }
    } catch (Exception $e) {
        return [
            ['url' => 'walk.php',      'icon' => '🎲', 'label' => 'Walk'],
            ['url' => 'scorecard.php', 'icon' => '🃏', 'label' => 'Scorecard'],
            ['url' => 'wardrobe.php',  'icon' => '👕', 'label' => 'Wardrobe'],
            ['url' => 'logout.php',    'icon' => '🚪', 'label' => 'Logout'],
        ];
    }
}

function pageHeader(string $title, string $active = ''): void {
    $navItems   = getNavItems();
    $isAdmin    = !empty($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1;
    $isLoggedIn = !empty($_SESSION['userid']);
    $userName   = $_SESSION['name'] ?? '';

    echo '<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>' . htmlspecialchars($title) . ' · Stailian</title>
<link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;600;900&family=Crimson+Pro:ital,wght@0,300;0,400;1,300&display=swap" rel="stylesheet">
<link rel="stylesheet" href="stailian.css">
</head>
<body>
<nav>
  <a href="' . ($isLoggedIn ? 'walk.php' : 'login.php') . '" class="nav-brand">⚜ STAILIAN</a>';

    foreach ($navItems as $item) {
        $cls = ($active === $item['url']) ? ' class="active"' : '';
        echo '<a href="' . htmlspecialchars($item['url']) . '"' . $cls . '>'
            . $item['icon'] . ' '
            . htmlspecialchars($item['label'])
            . '</a>';
    }

    // Right side of nav — user info + admin badge
    echo '<div style="margin-left:auto; display:flex; align-items:center; gap:.75rem;
          padding:0 .5rem;">';

    if ($isLoggedIn) {
		// Add this inside the right side of nav in pageHeader(), before the username link
if ($isLoggedIn) {
    try {
        $dbNav   = getDB();
        $hStmt   = $dbNav->prepare("SELECT health, max_health FROM user_health WHERE userid=?");
        $hStmt->execute([$_SESSION['userid']]);
        $hRow    = $hStmt->fetch();
        if ($hRow) {
            $hPct   = round(($hRow['health'] / $hRow['max_health']) * 100);
            $hColor = $hPct > 60 ? '#27ae60' : ($hPct > 25 ? '#e8622a' : '#c0392b');
            $hPulse = $hPct <= 25 ? 'animation:navpulse 1s infinite;' : '';
            echo '
            <style>
              @keyframes navpulse { 0%,100%{opacity:1} 50%{opacity:.4} }
            </style>
            <div style="display:flex; align-items:center; gap:.4rem;" title="'
                .$hRow['health'].'/'.$hRow['max_health'].' HP">
              <span style="font-size:.65rem; color:#c0392b;">❤</span>
              <div style="width:60px; background:rgba(255,255,255,.1);
                   border-radius:10px; height:6px; overflow:hidden;">
                <div style="background:'.$hColor.'; width:'.$hPct.'%; height:100%;
                     border-radius:10px; '.$hPulse.'"></div>
              </div>
              <span style="font-size:.65rem; color:'.$hColor.'; font-family:\'Cinzel\',serif;">
                '.$hRow['health'].'
              </span>
            </div>';
        }
    } catch (Exception $e) {
        // Health bar not available yet
    }
}
        if ($isAdmin) {
            echo '<span style="font-family:\'Cinzel\',serif; font-size:.6rem;
                  letter-spacing:.1em; color:var(--gold); background:rgba(200,153,58,.15);
                  border:1px solid var(--gold-dim); border-radius:20px; padding:.15rem .5rem;">
                  ⚜ ADMIN</span>';
        }
        echo '<a href="changepassword.php" style="font-size:.75rem; color:var(--muted);
				  text-decoration:none; transition:color .15s;"
				  onmouseenter="this.style.color=\'var(--gold)\'"
				  onmouseleave="this.style.color=\'var(--muted)\'">
				  👤 ' . htmlspecialchars($userName) . '</a>';
        echo '<a href="logout.php" style="font-size:.7rem; color:var(--muted);
              text-decoration:none; font-family:\'Cinzel\',serif; letter-spacing:.06em;"
              onmouseenter="this.style.color=\'var(--gold)\'"
              onmouseleave="this.style.color=\'var(--muted)\'">
              🚪 Logout</a>';
    }

    echo '</div>';
    echo '</nav><main>';
}

function pageFooter(): void {
    echo '</main>
<footer>Stailian Game Database &mdash; Admin Panel</footer>
</body></html>';
}

function flash(): string {
    if (!empty($_GET['msg'])) {
        $type = ($_GET['type'] ?? 'success') === 'error' ? 'alert-error' : 'alert-success';
        return '<div class="alert ' . $type . '">' . htmlspecialchars($_GET['msg']) . '</div>';
    }
    return '';
}

function redirect(string $to, string $msg, string $type = 'success'): void {
    $sep = str_contains($to, '?') ? '&' : '?';
    header("Location: {$to}{$sep}msg=" . urlencode($msg) . "&type={$type}");
    exit();
}