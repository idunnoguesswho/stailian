<?php
// health.php — shared health functions

function getHealth(PDO $db, int $uid): array {
    // Initialise if missing
    $db->prepare("INSERT INTO user_health (userid, health, max_health, last_heal)
                  VALUES (?,100,100,NOW())
                  ON DUPLICATE KEY UPDATE userid=userid")
       ->execute([$uid]);

    // Apply passive healing — 10 HP per minute since last_heal
    $db->prepare("UPDATE user_health SET
                  health = LEAST(max_health,
                      health + (TIMESTAMPDIFF(SECOND, last_heal, NOW()) DIV 60) * 10
                  ),
                  last_heal = DATE_ADD(last_heal,
                      INTERVAL ((TIMESTAMPDIFF(SECOND, last_heal, NOW()) DIV 60) * 60) SECOND
                  )
                  WHERE userid=?")
       ->execute([$uid]);

    $r = $db->prepare("SELECT * FROM user_health WHERE userid=?");
    $r->execute([$uid]);
    return $r->fetch();
}

function applyDamage(PDO $db, int $uid, int $charTotal): void {
    // Lose 5 HP for every 10 points the character had
    $damage = (int)floor($charTotal / 10) * 5;
    $damage = max(5, $damage); // minimum 5 damage

    $db->prepare("UPDATE user_health
                  SET health = GREATEST(0, health - ?)
                  WHERE userid=?")
       ->execute([$damage, $uid]);
}

function buyHealthPack(PDO $db, int $uid): array {
    $health = getHealth($db, $uid);
    if ($health['health'] >= $health['max_health']) {
        return ['success'=>false, 'msg'=>'Your health is already full!'];
    }

    // Check coins
    $coins = $db->prepare("SELECT COALESCE(coins,0) FROM user_coins WHERE userid=?");
    $coins->execute([$uid]);
    $coins = (int)$coins->fetchColumn();

    if ($coins < 50) {
        return ['success'=>false, 'msg'=>"Not enough coins. Need 50, have {$coins}."];
    }

    // Deduct coins
    $db->prepare("UPDATE user_coins SET coins=coins-50 WHERE userid=?")
       ->execute([$uid]);

    // Restore health
    $db->prepare("UPDATE user_health
                  SET health = LEAST(max_health, health+50)
                  WHERE userid=?")
       ->execute([$uid]);

    // Log it
    $db->prepare("INSERT INTO health_pack_log (userid, health_restored, coins_spent)
                  VALUES (?,50,50)")
       ->execute([$uid]);

    $newHealth = getHealth($db, $uid);
    return [
        'success' => true,
        'msg'     => "Health pack used! Restored to {$newHealth['health']}/{$newHealth['max_health']} HP.",
        'health'  => $newHealth,
    ];
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