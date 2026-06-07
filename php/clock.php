<?php
// clock.php — game clock and layer functions

const MINS_PER_ROLL = 30;

// Z level definitions
const Z_UNDERWORLD = -1;
const Z_SURFACE    =  0;
const Z_SKY        =  1;

function zLabel(int $z): string {
    return match($z) {
        Z_SKY        => 'Sky',
        Z_SURFACE    => 'Surface',
        Z_UNDERWORLD => 'Underworld',
        default      => 'Surface',
    };
}

function zIcon(int $z): string {
    return match($z) {
        Z_SKY        => '🌤',
        Z_SURFACE    => '🌍',
        Z_UNDERWORLD => '🔥',
        default      => '🌍',
    };
}

function zColor(int $z): string {
    return match($z) {
        Z_SKY        => 'var(--ice)',
        Z_SURFACE    => 'var(--ground)',
        Z_UNDERWORLD => 'var(--fire)',
        default      => 'var(--ground)',
    };
}

function getClock(PDO $db, int $uid): array {
    $db->prepare("INSERT INTO game_clock (userid, game_hour, game_day, turn_count)
                  VALUES (?,8,1,0)
                  ON DUPLICATE KEY UPDATE userid=userid")
       ->execute([$uid]);
    $r = $db->prepare("SELECT * FROM game_clock WHERE userid=?");
    $r->execute([$uid]);
    return $r->fetch();
}

function advanceClock(PDO $db, int $uid, int $minutes = MINS_PER_ROLL): array {
    $clock     = getClock($db, $uid);
    $totalMins = ($clock['game_hour'] * 60) + $minutes;
    $newHour   = $totalMins / 60;
    $newDay    = $clock['game_day'] + (int)floor($newHour / 24);
    $newHour   = (int)$newHour % 24;

    $db->prepare("UPDATE game_clock
                  SET game_hour=?, game_day=?, turn_count=turn_count+1,
                      last_updated=NOW()
                  WHERE userid=?")
       ->execute([$newHour, $newDay, $uid]);

    return getClock($db, $uid);
}

function sleepUser(PDO $db, int $uid): array {
    $clock = advanceClock($db, $uid, 480);
    $db->prepare("UPDATE user_health SET health=max_health WHERE userid=?")
       ->execute([$uid]);
    return $clock;
}

function getTimePeriod(int $hour): string {
    if ($hour >= 5  && $hour < 8)  return 'dawn';
    if ($hour >= 8  && $hour < 12) return 'morning';
    if ($hour >= 12 && $hour < 17) return 'afternoon';
    if ($hour >= 17 && $hour < 20) return 'dusk';
    if ($hour >= 20 && $hour < 24) return 'night';
    return 'midnight';
}

function getTimeIcon(string $period): string {
    return match($period) {
        'dawn'      => '🌅',
        'morning'   => '☀️',
        'afternoon' => '🌤',
        'dusk'      => '🌇',
        'night'     => '🌙',
        'midnight'  => '🌑',
        default     => '☀️',
    };
}

function getLayerMultipliersByZ(PDO $db, int $z, string $period): array {
    $r = $db->prepare("SELECT element, multiplier FROM layer_multipliers
        WHERE coord_z=? AND time_period=?");
    $r->execute([$z, $period]);
    return $r->fetchAll(PDO::FETCH_KEY_PAIR);
}

function clockDisplay(array $clock): string {
    $hour   = (int)$clock['game_hour'];
    $ampm   = $hour >= 12 ? 'PM' : 'AM';
    $h12    = $hour % 12 ?: 12;
    $period = getTimePeriod($hour);
    $icon   = getTimeIcon($period);
    return "{$icon} Day {$clock['game_day']} · {$h12}:00 {$ampm} · " . ucfirst($period);
}