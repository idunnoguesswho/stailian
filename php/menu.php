<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require 'db.php';
require 'layout.php';
$db = getDB();
$navItems = getNavItems();
pageHeader('Dashboard', 'index.php');
?>
<h1 class="page-title">Game Database Dashboard</h1>
<p class="page-sub">Manage all entities in the Stailian universe</p>
<?php
foreach ($db->query("SELECT * FROM nav_menu WHERE is_active = 1 ORDER BY sort_order ASC") as $r) {
    echo '<ul>
      <li>
        <a href="' . htmlspecialchars($r['url']) . '">'
        . $r['icon'] . ' '
        . htmlspecialchars($r['label']) . '</a>
      </li>
    </ul>';
}
?>
<?php pageFooter(); ?>