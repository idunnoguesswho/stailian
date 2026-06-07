<?php
// health.php — shared health functions

function getHealth(PDO $db, int $uid): array {

    $r = $db->prepare("SELECT currentHealth FROM characters WHERE id=?");
    $r->execute([$uid]);
    return $r->fetch();
}

function applyDamage(PDO $db, int $uid, int $damage): void {
    //remove health points
	$currentHealth = getHealth($db,$uid);
	$appliedHealth = $currentHealth - $damage;
	if($appliedHealth<0) { $appliedHealth = 0;}
    $db->prepare("UPDATE characters set currentHealth =? Where id=?")
       ->execute([$appliedHealth, $uid]);
}
function applyHealth(PDO $db, int $uid, int $damage): void {
    //remove health points
	$currentHealth = getHealth($db,$uid);
	$appliedHealth = $currentHealth + $damage;
	if($appliedHealth>100) { $appliedHealth = 100;}
    $db->prepare("UPDATE characters set currentHealth =? Where id=?")
       ->execute([$appliedHealth, $uid]);
}

function healthBarHtml(array $health, bool $showLabel = true): string {
    $pct    = $health['max_health'] > 0
        ? round(($health['health'] / $health['max_health']) * 100)
        : 0;
    $color  = $pct > 60 ? 'var(--success)'
            : ($pct > 25 ? 'var(--fire)' : 'var(--danger)');
    $pulse  = $pct <= 25
        ? 'animation:pulse 1s infinite;' : '';

    // Seconds until next 10HP heal
    $secsLeft = 60 - (time() % 60);

    $label = $showLabel
        ? '<div style="display:flex; justify-content:space-between; align-items:center;
               margin-bottom:.3rem;">
             <span style="font-family:\'Cinzel\',serif; font-size:.65rem;
                  letter-spacing:.08em; color:var(--muted);">❤ HEALTH</span>
             <span style="font-family:\'Cinzel\',serif; font-size:.72rem;
                  color:'.$color.';">'.$health['health'].'/'.$health['max_health'].'</span>
           </div>'
        : '';

    return '
    <style>
      @keyframes pulse {
        0%,100% { opacity:1; }
        50%      { opacity:.5; }
      }
    </style>
    '.$label.'
    <div style="background:var(--border); border-radius:10px; height:10px;
         overflow:hidden; position:relative;">
      <div style="background:'.$color.'; width:'.$pct.'%; height:100%;
           border-radius:10px; transition:width .4s; '.$pulse.'"></div>
    </div>
    <div style="display:flex; justify-content:space-between; margin-top:.2rem;
         font-size:.62rem; color:var(--muted);">
      <span>+10 HP in '.$secsLeft.'s</span>
      <span>'.$pct.'%</span>
    </div>';
}
