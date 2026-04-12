<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require 'auth.php';
require 'db.php';
require 'health.php';
require 'clock.php';
require 'layout.php';

$db     = getDB();
$userid = $SESSION_USERID;
$act    = $_GET['action'] ?? 'select';

// ── CONSTANTS ─────────────────────────────────────────────────────────────────
const MAX_SCROLLS = 30;
const MAX_FLOWERS = 120;
const MAX_BOWTIES = 4;

// ── FETCH SIGNED IN USER + THEIR CHARACTER ────────────────────────────────────
$userRow = $db->prepare("SELECT u.*, COALESCE(uc.coins,0) as coins,
    cb.id as charid, cb.name as charname, cb.chardescription
    FROM users u
    LEFT JOIN user_coins uc ON uc.userid=u.id
    LEFT JOIN charbase cb ON cb.id=u.charid
    WHERE u.id=?");
$userRow->execute([$userid]);
$userRow = $userRow->fetch();

$myCharId   = $userRow['charid']   ?? null;
$myCharName = $userRow['charname'] ?? null;

// ── FETCH CURRENT POSITION ────────────────────────────────────────────────────
$posStmt = $db->prepare("SELECT * FROM user_positions WHERE userid=?");
$posStmt->execute([$userid]);
$pos      = $posStmt->fetch();
$currentZ = (int)($pos['coord_z'] ?? 0);
$currentX = (int)($pos['coord_x'] ?? 0);
$currentY = (int)($pos['coord_y'] ?? 0);

// ── CLOCK ─────────────────────────────────────────────────────────────────────
$clock      = getClock($db, $userid);
$timePeriod = getTimePeriod((int)$clock['game_hour']);

// ── HELPERS ───────────────────────────────────────────────────────────────────
function getUserScores(PDO $db, int $id): array {
    $totals = ['ice'=>0,'ground'=>0,'fire'=>0,'water'=>0,'dark'=>0];
    $s = $db->prepare("SELECT s.iceScore,s.groundScore,s.fireScore,
        s.waterScore,s.darkScore
        FROM userAttributes ua JOIN skills s ON s.id=ua.skillid
        WHERE ua.userid=?");
    $s->execute([$id]);
    foreach ($s->fetchAll() as $r) {
        foreach (['ice','ground','fire','water','dark'] as $el)
            $totals[$el] += $r[$el.'Score'];
    }
    $w = $db->prepare("SELECT s.iceScore,s.groundScore,s.fireScore,
        s.waterScore,s.darkScore
        FROM inventory i
        JOIN weaponAttributes wa ON wa.weaponid=i.weaponid
        JOIN skills s ON s.id=wa.skillid WHERE i.userid=?");
    $w->execute([$id]);
    foreach ($w->fetchAll() as $r) {
        foreach (['ice','ground','fire','water','dark'] as $el)
            $totals[$el] += $r[$el.'Score'];
    }
    $c = $db->prepare("SELECT cs.skill_bonus, c.element
        FROM user_clothing uc
        JOIN colour_shades cs ON cs.id=uc.colour_shade_id
        JOIN colours c ON c.id=cs.colour_id
        WHERE uc.userid=? AND uc.is_equipped=1 AND cs.skill_bonus>0");
    $c->execute([$id]);
    foreach ($c->fetchAll() as $r) {
        if (isset($totals[$r['element']])) $totals[$r['element']] += $r['skill_bonus'];
    }
    return $totals;
}

function getCharScores(PDO $db, int $id): array {
    $totals = ['ice'=>0,'ground'=>0,'fire'=>0,'water'=>0,'dark'=>0];
    $s = $db->prepare("SELECT s.iceScore,s.groundScore,s.fireScore,
        s.waterScore,s.darkScore
        FROM charAttributes ca JOIN skills s ON s.id=ca.skillid
        WHERE ca.charid=?");
    $s->execute([$id]);
    foreach ($s->fetchAll() as $r) {
        foreach (['ice','ground','fire','water','dark'] as $el)
            $totals[$el] += $r[$el.'Score'];
    }
    $w = $db->prepare("SELECT s.iceScore,s.groundScore,s.fireScore,
        s.waterScore,s.darkScore
        FROM charinventory ci
        JOIN weaponAttributes wa ON wa.weaponid=ci.weaponid
        JOIN skills s ON s.id=wa.skillid WHERE ci.charid=?");
    $w->execute([$id]);
    foreach ($w->fetchAll() as $r) {
        foreach (['ice','ground','fire','water','dark'] as $el)
            $totals[$el] += $r[$el.'Score'];
    }
    return $totals;
}

function applyTileMultipliers(PDO $db, array $scores, int $tileTypeId): array {
    $mults = $db->prepare("SELECT element, multiplier
        FROM tile_multipliers WHERE tile_type_id=?");
    $mults->execute([$tileTypeId]);
    foreach ($mults->fetchAll() as $m) {
        $el = $m['element'];
        if (isset($scores[$el]))
            $scores[$el] = round($scores[$el] * $m['multiplier'], 1);
    }
    return $scores;
}

function getTileMultipliers(PDO $db, int $tileTypeId): array {
    $mults = $db->prepare("SELECT element, multiplier
        FROM tile_multipliers WHERE tile_type_id=?");
    $mults->execute([$tileTypeId]);
    return $mults->fetchAll(PDO::FETCH_KEY_PAIR);
}

function getBowtieCount(PDO $db, int $uid): int {
    $r = $db->prepare("SELECT COUNT(*) FROM inventory i
        JOIN weapons w ON w.id=i.weaponid
        WHERE i.userid=? AND w.name LIKE '%Bowtie%'");
    $r->execute([$uid]);
    return (int)$r->fetchColumn();
}

function addCoins(PDO $db, int $uid, int $amount): void {
    $db->prepare("INSERT INTO user_coins (userid,coins) VALUES (?,?)
                  ON DUPLICATE KEY UPDATE coins=coins+?")
       ->execute([$uid, $amount, $amount]);
}

function updateStats(PDO $db, int $uid, array $fields): void {
    $sets = implode(',', array_map(fn($f) => "$f=$f+1", $fields));
    $db->prepare("INSERT INTO user_stats (userid) VALUES (?)
                  ON DUPLICATE KEY UPDATE $sets")
       ->execute([$uid]);
}

function findNearestEmpty(PDO $db, int $targetX, int $targetY,
                          int $targetZ, int $excludeUserId): array {
    $tiles = $db->query("SELECT coord_x, coord_y FROM map_tiles
        WHERE coord_z=$targetZ
        ORDER BY ABS(coord_x-$targetX)+ABS(coord_y-$targetY)")->fetchAll();
    foreach ($tiles as $t) {
        $check = $db->prepare("SELECT COUNT(*) FROM user_positions
            WHERE coord_x=? AND coord_y=? AND coord_z=? AND userid!=?");
        $check->execute([$t['coord_x'],$t['coord_y'],$targetZ,$excludeUserId]);
        if ((int)$check->fetchColumn() === 0)
            return [$t['coord_x'],$t['coord_y']];
    }
    return [1,1];
}

function buildPie(array $data, float $total, int $size=70): string {
    if (!$data || $total<=0) return '';
    $cx=$cy=$size/2; $r=$size/2-4; $angle=-90; $paths='';
    foreach ($data as $seg) {
        $pct=$seg['quantity']/$total; $sweep=$pct*360;
        if ($sweep>=360) $sweep=359.99;
        $x1=$cx+$r*cos(deg2rad($angle));   $y1=$cy+$r*sin(deg2rad($angle));
        $x2=$cx+$r*cos(deg2rad($angle+$sweep)); $y2=$cy+$r*sin(deg2rad($angle+$sweep));
        $large=$sweep>180?1:0;
        $col=htmlspecialchars($seg['hex_base']);
        $paths.="<path d=\"M{$cx},{$cy} L{$x1},{$y1} A{$r},{$r} 0 {$large},1
            {$x2},{$y2} Z\" fill=\"{$col}\" stroke=\"#0a0b0f\" stroke-width=\"1\">
            <title>{$seg['name']}: {$seg['quantity']}</title></path>";
        $angle+=$sweep;
    }
    return "<svg width=\"{$size}\" height=\"{$size}\"
        viewBox=\"0 0 {$size} {$size}\">{$paths}</svg>";
}

// ── SLEEP ─────────────────────────────────────────────────────────────────────
if ($act === 'sleep') {
    sleepUser($db, $userid);
    $newClock = getClock($db, $userid);
    redirect("walk.php",
        "😴 Slept 8 hours — Day {$newClock['game_day']}, "
        . getTimeIcon(getTimePeriod($newClock['game_hour']))
        . " " . ucfirst(getTimePeriod($newClock['game_hour']))
        . ". Full health restored!");
}

// ── RANDOMIZE MAP ─────────────────────────────────────────────────────────────
if ($act === 'randomize') {
    foreach ([-1, 0, 1] as $z) {
        $tilesOnLayer = $db->query("SELECT id FROM map_tiles
            WHERE coord_z=$z")->fetchAll();
        $diceNumbers  = [2,3,3,4,4,5,5,6,6,8,8,9,9,10,10,11,11,12];
        shuffle($diceNumbers);
        $i = 0;
        foreach ($tilesOnLayer as $tile) {
            $newType = $db->query("SELECT id FROM tile_types
                WHERE FIND_IN_SET('$z', allowed_z)>0
                ORDER BY RAND() LIMIT 1")->fetchColumn();
            if ($newType) {
                $db->prepare("UPDATE map_tiles SET tile_type_id=?, dice_number=?
                    WHERE id=?")
                   ->execute([$newType, $diceNumbers[$i%count($diceNumbers)], $tile['id']]);
            }
            $i++;
        }
    }
    redirect("walk.php", "Map randomized across all z layers!");
}

// ── ROLL DICE ─────────────────────────────────────────────────────────────────
$rollData = null;

if ($act === 'roll') {
    $die1 = rand(1,12); // X
    $die2 = rand(1,12); // Y
    $newX = $die1;
    $newY = $die2;

    // Clamp to map bounds for current z level
    $bounds = $db->query("SELECT MIN(coord_x) as minX, MAX(coord_x) as maxX,
                                 MIN(coord_y) as minY, MAX(coord_y) as maxY
                          FROM map_tiles WHERE coord_z=$currentZ")->fetch();
    if ($bounds['minX'] !== null) {
        $newX = max($bounds['minX'], min($bounds['maxX'], $newX));
        $newY = max($bounds['minY'], min($bounds['maxY'], $newY));
    }

    // Find nearest tile on current Z level
    $nearest = $db->query("SELECT m.*, t.name as type_name, t.icon, t.color,
                                  t.resource, t.id as tile_type_id
                           FROM map_tiles m
                           JOIN tile_types t ON t.id=m.tile_type_id
                           WHERE m.coord_z=$currentZ
                           ORDER BY ABS(m.coord_x-$newX)+ABS(m.coord_y-$newY)
                           LIMIT 1")->fetch();
    if ($nearest) {
        $newX = $nearest['coord_x'];
        $newY = $nearest['coord_y'];
    }

    // Save user position with z
    $db->prepare("INSERT INTO user_positions (userid,coord_x,coord_y,coord_z)
                  VALUES (?,?,?,?)
                  ON DUPLICATE KEY UPDATE coord_x=?,coord_y=?,coord_z=?")
       ->execute([$userid,$newX,$newY,$currentZ,$newX,$newY,$currentZ]);

    // Save move trail with z
    $db->prepare("INSERT INTO user_move_trail
                  (userid,coord_x,coord_y,coord_z,tile_type_name,tile_icon)
                  VALUES (?,?,?,?,?,?)")
       ->execute([$userid,$newX,$newY,$currentZ,
                  $nearest['type_name']??'',$nearest['icon']??'']);

    // Advance clock
    $clock      = advanceClock($db,$userid);
    $timePeriod = getTimePeriod((int)$clock['game_hour']);

    updateStats($db,$userid,['tiles_visited']);

    // Combined tile + layer + time multipliers
    $tileMults  = $nearest ? getTileMultipliers($db,$nearest['tile_type_id']) : [];
    $layerMults = getLayerMultipliersByZ($db,$currentZ,$timePeriod);
    $multipliers = $tileMults;
    foreach ($layerMults as $el=>$mult) {
        if (!isset($multipliers[$el]) || $mult>$multipliers[$el])
            $multipliers[$el] = $mult;
    }

    // ── MOVE THIS USER'S CHARACTER RANDOMLY ON SAME Z ─────────────────────────
    $movedChars = [];
    if ($myCharId) {
        $allTileCoords = $db->query("SELECT coord_x,coord_y FROM map_tiles
            WHERE coord_z=$currentZ")->fetchAll();
        if ($allTileCoords) {
            $rt = $allTileCoords[array_rand($allTileCoords)];
            $db->prepare("INSERT INTO character_positions
                          (charid,coord_x,coord_y,coord_z)
                          VALUES (?,?,?,?)
                          ON DUPLICATE KEY UPDATE coord_x=?,coord_y=?,coord_z=?")
               ->execute([$myCharId,$rt['coord_x'],$rt['coord_y'],$currentZ,
                          $rt['coord_x'],$rt['coord_y'],$currentZ]);
            $movedChars[] = [
                'id'   => $myCharId,
                'name' => $myCharName,
                'x'    => $rt['coord_x'],
                'y'    => $rt['coord_y'],
                'z'    => $currentZ,
            ];
        }
    }

    // ── GATHER FLOWER & SCROLL ────────────────────────────────────────────────
    $flowerGathered   = null;
    $scrollGathered   = false;
    $capacityWarnings = [];
    $resourceGathered = null;

    if ($nearest) {
        $tileColour = $db->query("SELECT c.id,c.name,c.hex_base
            FROM tile_types tt JOIN colours c ON c.id=tt.colour_id
            WHERE tt.id={$nearest['tile_type_id']} LIMIT 1")->fetch();

        if ($tileColour) {
            // Flowers — cap at 120
            $fStmt = $db->prepare("SELECT COALESCE(SUM(quantity),0)
                FROM user_flowers WHERE userid=?");
            $fStmt->execute([$userid]);
            $totalFlowers = (int)$fStmt->fetchColumn();

            if ($totalFlowers < MAX_FLOWERS) {
                $db->prepare("INSERT INTO user_flowers
                              (userid,colour_id,quantity) VALUES (?,?,1)
                              ON DUPLICATE KEY UPDATE quantity=quantity+1")
                   ->execute([$userid,$tileColour['id']]);
                $flowerGathered = $tileColour;
            } else {
                $capacityWarnings[] =
                    '🌸 Flower bag full (120/120)! Convert flowers to scrolls.';
            }

            // Scrolls — cap at 30
            $sStmt = $db->prepare("SELECT COALESCE(SUM(quantity),0)
                FROM user_scrolls WHERE userid=?");
            $sStmt->execute([$userid]);
            $totalScrolls = (int)$sStmt->fetchColumn();

            if ($totalScrolls < MAX_SCROLLS) {
                $db->prepare("INSERT INTO user_scrolls
                              (userid,colour_id,quantity) VALUES (?,?,1)
                              ON DUPLICATE KEY UPDATE quantity=quantity+1")
                   ->execute([$userid,$tileColour['id']]);
                $scrollGathered = true;
            } else {
                $capacityWarnings[] =
                    '📜 Scroll bag full (30/30)! Sell or use scrolls.';
            }
        }

        // Gather tile resource
        if (!empty($nearest['resource'])) {
            $db->prepare("INSERT INTO user_resources
                          (userid,resource_name,quantity) VALUES (?,?,1)
                          ON DUPLICATE KEY UPDATE quantity=quantity+1")
               ->execute([$userid,$nearest['resource']]);
            $resourceGathered = $nearest['resource'];
        }

        // Weapon scroll on this tile + z
        $weaponScrollOnTile = $db->query("SELECT mws.*,
            ws.name as scroll_name, ws.weapon_name, ws.element,
            ws.description, ws.resource1, ws.resource2
            FROM map_weapon_scrolls mws
            JOIN weapon_scrolls ws ON ws.id=mws.weapon_scroll_id
            WHERE mws.coord_x=$newX AND mws.coord_y=$newY
            AND mws.coord_z=$currentZ
            AND mws.is_available=1 LIMIT 1")->fetch();

        $weaponScrollFound = null;
        if ($weaponScrollOnTile) {
            $hasScroll = $db->prepare("SELECT id FROM user_weapon_scrolls
                WHERE userid=? AND weapon_scroll_id=?");
            $hasScroll->execute([$userid,$weaponScrollOnTile['weapon_scroll_id']]);
            if (!$hasScroll->fetch()) {
                $db->prepare("INSERT INTO user_weapon_scrolls
                              (userid,weapon_scroll_id) VALUES (?,?)")
                   ->execute([$userid,$weaponScrollOnTile['weapon_scroll_id']]);
                $db->prepare("UPDATE map_weapon_scrolls SET is_available=0
                    WHERE id=?")->execute([$weaponScrollOnTile['id']]);
                updateStats($db,$userid,['scrolls_found']);
                $weaponScrollFound = $weaponScrollOnTile;
            }
        }

        // Clothing drop on this tile + z
        $clothingDrop = $db->query("SELECT mcd.*,
            ct.name as type_name, ct.slot, ct.shield_element, ct.shield_value,
            c.name as colour_name, cs.hex_value, cs.skill_bonus,
            c.element as colour_element
            FROM map_clothing_drops mcd
            JOIN clothing_types ct ON ct.id=mcd.clothing_type_id
            JOIN colours c ON c.id=mcd.colour_id
            JOIN colour_shades cs ON cs.id=mcd.colour_shade_id
            WHERE mcd.coord_x=$newX AND mcd.coord_y=$newY
            AND mcd.coord_z=$currentZ
            AND mcd.is_available=1 LIMIT 1")->fetch();

        $clothingFound = null;
        if ($clothingDrop) {
            $db->prepare("INSERT INTO user_clothing
                (userid,clothing_type_id,colour_shade_id,slot) VALUES (?,?,?,?)")
               ->execute([$userid,$clothingDrop['clothing_type_id'],
                          $clothingDrop['colour_shade_id'],$clothingDrop['slot']]);
            $db->prepare("UPDATE map_clothing_drops SET is_available=0 WHERE id=?")
               ->execute([$clothingDrop['id']]);
            updateStats($db,$userid,['clothing_found']);
            $clothingFound = $clothingDrop;
        }
    }

    // ── BOWTIE ON MAGIC GROUND (X===Y) ────────────────────────────────────────
    $pickupResult = null;
    $atTowerNow   = ($newX===5 && $newY===5);

    if ($newX===$newY && $nearest) {
        $tileTypeName = $nearest['type_name'];
        $bowtieWeapon = $db->query("SELECT w.* FROM weapons w
            WHERE w.name='{$tileTypeName} Bowtie'
            AND w.userCarry=1 LIMIT 1")->fetch();

        if ($bowtieWeapon) {
            $alreadyHas = $db->prepare("SELECT id FROM inventory
                WHERE userid=? AND weaponid=?");
            $alreadyHas->execute([$userid,$bowtieWeapon['id']]);
            if (!$alreadyHas->fetch()) {
                $db->prepare("INSERT INTO inventory (userid,weaponid) VALUES (?,?)")
                   ->execute([$userid,$bowtieWeapon['id']]);
                updateStats($db,$userid,['bowties_found']);
                $pickupResult = [
                    'weapon' => $bowtieWeapon,
                    'tile'   => $tileTypeName,
                ];
            }
        }
    }

    // Bowtie limit check
    $bowtieCount     = getBowtieCount($db,$userid);
    $bowtieOverLimit = $bowtieCount > MAX_BOWTIES;
    $userBowties     = [];
    if ($bowtieOverLimit) {
        $bStmt = $db->prepare("SELECT i.id as inv_id,w.*
            FROM inventory i JOIN weapons w ON w.id=i.weaponid
            WHERE i.userid=? AND w.name LIKE '%Bowtie%'");
        $bStmt->execute([$userid]);
        $userBowties     = $bStmt->fetchAll();
        $capacityWarnings[] = '🎀 Bowtie limit exceeded! Sell one in Wardrobe.';
    }

    // ── BATTLE — SAME TILE AND SAME Z ─────────────────────────────────────────
    $battleResults = [];
    $healthData    = getHealth($db,$userid);

    if ($myCharId) {
        $charPos = $db->prepare("SELECT coord_x,coord_y,coord_z
            FROM character_positions WHERE charid=?");
        $charPos->execute([$myCharId]);
        $charPos = $charPos->fetch();

        if ($charPos
            && $charPos['coord_x'] == $newX
            && $charPos['coord_y'] == $newY
            && (int)$charPos['coord_z'] === $currentZ
        ) {
            $userScores = getUserScores($db,$userid);
            $charScores = getCharScores($db,$myCharId);

            if ($nearest) {
                $userScores = applyTileMultipliers($db,$userScores,
                    $nearest['tile_type_id']);
                $charScores = applyTileMultipliers($db,$charScores,
                    $nearest['tile_type_id']);
            }
            // Apply layer+time multipliers
            foreach ($multipliers as $el=>$mult) {
                if (isset($userScores[$el]))
                    $userScores[$el] = round($userScores[$el]*$mult,1);
                if (isset($charScores[$el]))
                    $charScores[$el] = round($charScores[$el]*$mult,1);
            }

            $userTotal = array_sum($userScores);
            $charTotal = array_sum($charScores);

            $elems=$userW=$charW=0;
            $userW=0; $charW=0;
            $elementResults=[];
            foreach (['ice','ground','fire','water','dark'] as $el) {
                $us=$userScores[$el]; $cs=$charScores[$el];
                if ($us>$cs)     { $userW++; $winner='user'; }
                elseif ($cs>$us) { $charW++; $winner='char'; }
                else             { $winner='draw'; }
                $elementResults[$el]=['user'=>$us,'char'=>$cs,'winner'=>$winner];
            }

            $userWon    = $userW >= $charW;
            $winnerId   = $userWon ? $userid : $myCharId;
            $winnerType = $userWon ? 'user' : 'char';
            $winnerName = $userWon ? $userRow['name'] : $myCharName;

            // Damage if user lost
            $damageDealt  = 0;
            $healthBefore = $healthData['health'];
            if (!$userWon) {
                $damage      = max(5, (int)floor($charTotal/10)*5);
                $db->prepare("UPDATE user_health
                    SET health=GREATEST(0,health-?) WHERE userid=?")
                   ->execute([$damage,$userid]);
                $healthData  = getHealth($db,$userid);
                $damageDealt = $healthBefore - $healthData['health'];
                if ($healthData['health'] <= 25)
                    $capacityWarnings[] =
                        '❤ Critical health ('.$healthData['health'].'/100)!'
                        .' Buy a Health Pack or sleep to recover.';
            }

            // Redirect loser to nearest empty at (1,1) on same z
            $loserNewX=1; $loserNewY=1;
            if ($userWon) {
                [$loserNewX,$loserNewY]=findNearestEmpty($db,1,1,$currentZ,0);
                $db->prepare("UPDATE character_positions
                    SET coord_x=?,coord_y=? WHERE charid=?")
                   ->execute([$loserNewX,$loserNewY,$myCharId]);
            } else {
                [$loserNewX,$loserNewY]=findNearestEmpty($db,1,1,$currentZ,$userid);
                $db->prepare("UPDATE user_positions
                    SET coord_x=?,coord_y=? WHERE userid=?")
                   ->execute([$loserNewX,$loserNewY,$userid]);
                $db->prepare("INSERT INTO user_move_trail
                    (userid,coord_x,coord_y,coord_z,tile_type_name,tile_icon)
                    VALUES (?,?,?,?,'Redirected after battle','⚠')")
                   ->execute([$userid,$loserNewX,$loserNewY,$currentZ]);
            }

            // Log battle
            $db->prepare("INSERT INTO battle_log
                (attacker_id,defender_id,tile_id,attacker_roll,defender_roll,
                 winner_id,winner_type,loser_redirected,loser_new_x,loser_new_y,
                 battle_detail)
                VALUES (?,?,?,?,?,?,?,1,?,?,?)")
               ->execute([$userid,$myCharId,$nearest['id']??null,
                          $userTotal,$charTotal,$winnerId,$winnerType,
                          $loserNewX,$loserNewY,json_encode($elementResults)]);

            updateStats($db,$userid,['battles_fought']);
            if ($userWon) updateStats($db,$userid,['battles_won']);

            $battleResults[] = [
                'userName'       => $userRow['name'],
                'charName'       => $myCharName,
                'userScores'     => $userScores,
                'charScores'     => $charScores,
                'userTotal'      => $userTotal,
                'charTotal'      => $charTotal,
                'userWins'       => $userW,
                'charWins'       => $charW,
                'userWon'        => $userWon,
                'winnerName'     => $winnerName,
                'elementResults' => $elementResults,
                'loserNewX'      => $loserNewX,
                'loserNewY'      => $loserNewY,
                'damageDealt'    => $damageDealt,
                'healthBefore'   => $healthBefore,
                'healthAfter'    => $healthData['health'],
            ];
        }
    }

    // Dye warning
    $dyeStmt = $db->prepare("SELECT COALESCE(SUM(quantity),0)
        FROM user_dyes WHERE userid=?");
    $dyeStmt->execute([$userid]);
    if ((int)$dyeStmt->fetchColumn() >= 20)
        $capacityWarnings[] = '🎨 Dye inventory getting full! Use dyes in Wardrobe.';

    // Side game prompt every 20 turns
    $promptSideGame = ($clock['turn_count'] > 0 && $clock['turn_count'] % 20 === 0);

    $rollData = [
        'userid'            => $userid,
        'die1'              => $die1,
        'die2'              => $die2,
        'newX'              => $newX,
        'newY'              => $newY,
        'currentZ'          => $currentZ,
        'tile'              => $nearest,
        'multipliers'       => $multipliers,
        'pickup'            => $pickupResult,
        'battles'           => $battleResults,
        'movedChars'        => $movedChars,
        'flowerGathered'    => $flowerGathered,
        'scrollGathered'    => $scrollGathered,
        'resourceGathered'  => $resourceGathered,
        'capacityWarnings'  => $capacityWarnings,
        'bowtieOverLimit'   => $bowtieOverLimit,
        'userBowties'       => $userBowties,
        'bowtieCount'       => $bowtieCount,
        'weaponScrollFound' => $weaponScrollFound ?? null,
        'clothingFound'     => $clothingFound     ?? null,
        'clock'             => $clock,
        'timePeriod'        => $timePeriod,
        'promptSideGame'    => $promptSideGame,
        'atTower'           => $atTowerNow,
        'health'            => $healthData,
    ];
}

// ── FETCH PAGE DATA ───────────────────────────────────────────────────────────

// Re-fetch position after any action
$posStmt->execute([$userid]);
$pos      = $posStmt->fetch();
$currentZ = (int)($pos['coord_z'] ?? 0);
$currentX = (int)($pos['coord_x'] ?? 0);
$currentY = (int)($pos['coord_y'] ?? 0);
$atTower  = ($currentX===5 && $currentY===5);

// My character
$myChar = null;
if ($myCharId) {
    $myCharStmt = $db->prepare("SELECT c.*,cp.coord_x,cp.coord_y,cp.coord_z
        FROM charbase c
        LEFT JOIN character_positions cp ON cp.charid=c.id
        WHERE c.id=?");
    $myCharStmt->execute([$myCharId]);
    $myChar = $myCharStmt->fetch();
}

// Tiles for CURRENT Z level only
$allTiles = $db->query("SELECT m.*,t.name as type_name,t.icon,t.color
    FROM map_tiles m JOIN tile_types t ON t.id=m.tile_type_id
    WHERE m.coord_z=$currentZ")->fetchAll();

$tileGrid = [];
$minX=$minY=PHP_INT_MAX; $maxX=$maxY=PHP_INT_MIN;
foreach ($allTiles as $t) {
    $tileGrid[$t['coord_x']][$t['coord_y']] = $t;
    $minX=min($minX,$t['coord_x']); $maxX=max($maxX,$t['coord_x']);
    $minY=min($minY,$t['coord_y']); $maxY=max($maxY,$t['coord_y']);
}
if (!$allTiles) { $minX=$minY=0; $maxX=$maxY=12; }

// Move trail (last 5)
$trailStmt = $db->prepare("SELECT * FROM user_move_trail WHERE userid=?
    ORDER BY moved_at DESC LIMIT 5");
$trailStmt->execute([$userid]);
$trail       = $trailStmt->fetchAll();
$trailCoords = array_map(fn($t)=>[$t['coord_x'],$t['coord_y']],$trail);

// Char position
$myCharX = $myChar['coord_x'] ?? null;
$myCharY = $myChar['coord_y'] ?? null;
$myCharZ = isset($myChar['coord_z']) ? (int)$myChar['coord_z'] : 0;

// Left panel data
$sStmt = $db->prepare("SELECT COALESCE(SUM(quantity),0)
    FROM user_scrolls WHERE userid=?");
$sStmt->execute([$userid]);
$totalScrollsPanel = (int)$sStmt->fetchColumn();

$fStmt = $db->prepare("SELECT COALESCE(SUM(quantity),0)
    FROM user_flowers WHERE userid=?");
$fStmt->execute([$userid]);
$totalFlowersPanel = (int)$fStmt->fetchColumn();

$bowtieCount  = getBowtieCount($db,$userid);
$healthData   = getHealth($db,$userid);
$clock        = getClock($db,$userid);
$timePeriod   = getTimePeriod((int)$clock['game_hour']);

// Equipped clothing for panel
$epStmt = $db->prepare("SELECT uc.*,ct.slot,ct.name as type_name,
    COALESCE(cs.hex_value,'') as hex_value,
    COALESCE(c.name,'') as colour_name
    FROM user_clothing uc
    JOIN clothing_types ct ON ct.id=uc.clothing_type_id
    LEFT JOIN colour_shades cs ON cs.id=uc.colour_shade_id
    LEFT JOIN colours c ON c.id=cs.colour_id
    WHERE uc.userid=? AND uc.is_equipped=1");
$epStmt->execute([$userid]);
$equippedPanel=[];
foreach ($epStmt->fetchAll() as $e) $equippedPanel[$e['slot']]=$e;

// Weapons for panel
$invStmt = $db->prepare("SELECT w.name FROM inventory i
    JOIN weapons w ON w.id=i.weaponid
    WHERE i.userid=? ORDER BY w.name LIMIT 8");
$invStmt->execute([$userid]);
$invWeapons = $invStmt->fetchAll(PDO::FETCH_COLUMN);

// Stats
$statsStmt = $db->prepare("SELECT * FROM user_stats WHERE userid=?");
$statsStmt->execute([$userid]);
$userStats = $statsStmt->fetch()
    ?: ['battles_fought'=>0,'battles_won'=>0];

// Recent battles
$recentBattles = $db->prepare("SELECT bl.*,
    c.name as defender_name,
    CASE WHEN bl.winner_type='user' THEN ? ELSE c.name END as winner_name
    FROM battle_log bl
    JOIN charbase c ON c.id=bl.defender_id
    WHERE bl.attacker_id=?
    ORDER BY bl.fought_at DESC LIMIT 5");
$recentBattles->execute([$userRow['name'],$userid]);
$recentBattles = $recentBattles->fetchAll();

// Pie chart data
$scrollChartStmt = $db->prepare("SELECT c.name,c.hex_base,ub.quantity
    FROM user_scrolls ub JOIN colours c ON c.id=ub.colour_id
    WHERE ub.userid=? AND ub.quantity>0 ORDER BY ub.quantity DESC");
$scrollChartStmt->execute([$userid]);
$scrollChartData = $scrollChartStmt->fetchAll();

$flowerChartStmt = $db->prepare("SELECT c.name,c.hex_base,uf.quantity
    FROM user_flowers uf JOIN colours c ON c.id=uf.colour_id
    WHERE uf.userid=? AND uf.quantity>0 ORDER BY uf.quantity DESC");
$flowerChartStmt->execute([$userid]);
$flowerChartData = $flowerChartStmt->fetchAll();

$totalBC = array_sum(array_column($scrollChartData,  'quantity'));
$totalFC = array_sum(array_column($flowerChartData,'quantity'));

// Tower scrolls
$tsStmt = $db->prepare("SELECT scroll_type,quantity
    FROM tower_scrolls WHERE userid=?");
$tsStmt->execute([$userid]);
$towerScrolls = ['climbing'=>0,'sliding'=>0];
foreach ($tsStmt->fetchAll() as $r)
    $towerScrolls[$r['scroll_type']] = $r['quantity'];

$elements = [
    'ice'   =>['❄','ice'],  'ground'=>['🌍','ground'],
    'fire'  =>['🔥','fire'],'water' =>['💧','water'],
    'dark'  =>['🌑','dark'],
];
$elIcons = ['ice'=>'❄','ground'=>'🌍','fire'=>'🔥','water'=>'💧','dark'=>'🌑'];

pageHeader('Walk','walk.php');
echo flash();
?>

<style>
.walk-layout {
    display:grid;
    grid-template-columns:210px 1fr 1fr;
    gap:1rem;
    align-items:start;
}
@media(max-width:900px){ .walk-layout{grid-template-columns:1fr;} }
.side-panel{display:flex;flex-direction:column;gap:.6rem;}
.side-card{
    background:var(--card);border:1px solid var(--border);
    border-radius:var(--radius);padding:.7rem;
}
.side-title{
    font-family:'Cinzel',serif;font-size:.62rem;letter-spacing:.1em;
    color:var(--gold-dim);margin-bottom:.45rem;
}
</style>

<!-- PAGE HEADER -->
<div style="display:flex;align-items:center;gap:.75rem;flex-wrap:wrap;margin-bottom:.75rem;">
  <h1 class="page-title" style="margin-bottom:0;">🎲 Walk</h1>

  <!-- Z LAYER BADGE -->
  <div style="font-family:'Cinzel',serif;font-size:.75rem;padding:.25rem .7rem;
       border-radius:20px;border:1px solid <?= zColor($currentZ) ?>;
       color:<?= zColor($currentZ) ?>;">
    <?= zIcon($currentZ) ?> <?= zLabel($currentZ) ?>
    <span style="font-size:.6rem;opacity:.7;">
      z=<?= $currentZ>0?'+':'' ?><?= $currentZ ?>
    </span>
  </div>

  <!-- TIME -->
  <div style="font-family:'Cinzel',serif;font-size:.72rem;color:var(--muted);">
    <?= clockDisplay($clock) ?>
  </div>

  <!-- TOWER LINK when at (5,5) -->
  <?php if ($atTower): ?>
  <a href="tower.php" class="btn btn-outline btn-sm"
     style="font-size:.68rem;padding:.2rem .6rem;">
    🗼 Enter Tower (z=<?= $currentZ>0?'+':''?><?= $currentZ ?>)
  </a>
  <?php endif; ?>
</div>

<div class="walk-layout">

<!-- ══ LEFT PANEL ══════════════════════════════════════════════════════════ -->
<div class="side-panel">

  <!-- USER SUMMARY -->
  <div class="side-card" style="border-color:var(--gold);">
    <div class="side-title">👤 <?= htmlspecialchars($userRow['name']) ?></div>
    <div style="font-size:.75rem;color:var(--muted);line-height:1.9;">
      🪙 <span style="color:var(--gold)"><?= $userRow['coins'] ?></span> coins<br>
      ⚔ <?= $userStats['battles_won']?>/<?= $userStats['battles_fought']?> wins<br>
      <?php if ($pos): ?>
        📍 (<?= $currentX ?>,<?= $currentY ?>,z=<?= $currentZ>0?'+':''?><?= $currentZ ?>)
      <?php else: ?>
        <em>Not placed</em>
      <?php endif; ?>
    </div>
  </div>

  <!-- HEALTH -->
  <div class="side-card"
       style="border-color:<?= $healthData['health']<=25?'var(--danger)':'var(--border)' ?>">
    <div class="side-title">❤ HEALTH</div>
    <?= healthBarHtml($healthData,false) ?>
    <div style="display:flex;justify-content:space-between;align-items:center;
         margin-top:.5rem;">
      <span style="font-family:'Cinzel',serif;font-size:.75rem;
           color:<?= $healthData['health']<=25?'var(--danger)':'var(--gold)' ?>;">
        <?= $healthData['health'] ?>/<?= $healthData['max_health'] ?>
        <?php if ($healthData['health']<=25) echo ' ⚠'; ?>
      </span>
      <a href="wardrobe.php?tab=shop" class="btn btn-outline btn-sm"
         style="font-size:.6rem;padding:.15rem .45rem;">🧪 Pack</a>
    </div>
  </div>

  <!-- MY CHARACTER -->
  <?php if ($myChar): ?>
  <div class="side-card">
    <div class="side-title">🧙 MY CHARACTER</div>
    <div style="font-size:.78rem;color:var(--text);margin-bottom:.15rem;">
      <?= htmlspecialchars($myChar['name']) ?>
    </div>
    <div style="font-size:.68rem;color:var(--muted);font-style:italic;margin-bottom:.3rem;">
      <?= htmlspecialchars($myChar['chardescription']??'') ?>
    </div>
    <div style="font-size:.7rem;color:var(--muted);">
      <?php if ($myChar['coord_x']!==null): ?>
        📍 (<?= $myChar['coord_x'] ?>,<?= $myChar['coord_y'] ?>,z=<?= $myCharZ>0?'+':''?><?= $myCharZ ?>)
        <?php if ($myCharZ !== $currentZ): ?>
          <span style="color:var(--muted);font-style:italic;"> diff layer</span>
        <?php elseif ($myChar['coord_x']==$currentX && $myChar['coord_y']==$currentY): ?>
          <span style="color:var(--fire);"> ⚔ Same tile!</span>
        <?php endif; ?>
      <?php else: ?>
        Not placed yet
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- CAPACITY -->
  <div class="side-card">
    <div class="side-title">📦 CAPACITY</div>
    <?php foreach ([
      ['📜','Scrolls',$totalScrollsPanel,MAX_SCROLLS],
      ['🌸','Flowers',$totalFlowersPanel,MAX_FLOWERS],
      ['🎀','Bowties',$bowtieCount,       MAX_BOWTIES],
    ] as [$icon,$label,$cur,$max]):
      $pct=min(100,$max>0?round(($cur/$max)*100):0);
      $col=$pct>=100?'var(--danger)':($pct>=80?'var(--fire)':'var(--gold)');
    ?>
    <div style="margin-bottom:.45rem;">
      <div style="display:flex;justify-content:space-between;font-size:.68rem;
           color:var(--muted);margin-bottom:.12rem;">
        <span><?= $icon ?> <?= $label ?></span>
        <span style="color:<?= $col ?>"><?= $cur ?>/<?= $max ?></span>
      </div>
      <div style="background:var(--border);border-radius:8px;height:5px;overflow:hidden;">
        <div style="background:<?= $col ?>;width:<?= $pct ?>%;height:100%;"></div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- TOWER SCROLLS -->
  <div class="side-card">
    <div class="side-title">🗼 TOWER SCROLLS</div>
    <div style="display:flex;gap:.5rem;">
      <?php foreach (['climbing'=>'🧗','sliding'=>'🎿'] as $type=>$icon): ?>
      <div style="flex:1;text-align:center;padding:.4rem;background:var(--surface);
           border-radius:var(--radius);border:1px solid var(--border);">
        <div style="font-size:1rem;"><?= $icon ?></div>
        <div style="font-family:'Cinzel',serif;font-size:.9rem;
             color:<?= $towerScrolls[$type]>0?'var(--gold)':'var(--muted)' ?>;">
          ×<?= $towerScrolls[$type] ?>
        </div>
        <div style="font-size:.58rem;color:var(--muted);"><?= $type ?></div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php if ($atTower): ?>
    <a href="tower.php" class="btn btn-outline btn-sm"
       style="width:100%;margin-top:.5rem;font-size:.65rem;justify-content:center;">
      🗼 Use Tower
    </a>
    <?php endif; ?>
  </div>

  <!-- OUTFIT -->
  <div class="side-card">
    <div class="side-title">👕 OUTFIT</div>
    <?php foreach (['shirt'=>'👕','pants'=>'👖','socks'=>'🧦'] as $slot=>$icon):
      $item=$equippedPanel[$slot]??null;
      $bg=($item&&!empty($item['hex_value']))?$item['hex_value']:'var(--border)';
    ?>
    <div style="display:flex;align-items:center;gap:.4rem;margin-bottom:.3rem;">
      <div style="width:1.3rem;height:1.3rem;background:<?= $bg ?>;border-radius:3px;
           display:flex;align-items:center;justify-content:center;
           font-size:.65rem;flex-shrink:0;"><?= $icon ?></div>
      <div style="font-size:.68rem;color:var(--muted);overflow:hidden;
           text-overflow:ellipsis;white-space:nowrap;">
        <?= $item?htmlspecialchars($item['type_name']):'None' ?>
        <?php if ($item&&!empty($item['colour_name'])): ?>
          · <span style="color:<?= $bg!='var(--border)'?$bg:'var(--muted)' ?>">
            <?= htmlspecialchars($item['colour_name']) ?></span>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
    <a href="wardrobe.php" style="font-size:.62rem;color:var(--gold-dim);
       text-decoration:none;font-family:'Cinzel',serif;letter-spacing:.04em;">
      Manage →
    </a>
  </div>

  <!-- WEAPONS -->
  <div class="side-card">
    <div class="side-title">🗡 WEAPONS</div>
    <?php if ($invWeapons): ?>
      <?php foreach ($invWeapons as $w): ?>
      <div style="font-size:.68rem;color:var(--muted);padding:.1rem 0;
           white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
        🗡 <?= htmlspecialchars($w) ?>
      </div>
      <?php endforeach; ?>
    <?php else: ?>
      <div style="font-size:.68rem;color:var(--muted);font-style:italic;">None yet</div>
    <?php endif; ?>
  </div>

  <!-- MOVE TRAIL -->
  <div class="side-card">
    <div class="side-title">👣 LAST 5 MOVES</div>
    <?php if ($trail): ?>
      <?php foreach ($trail as $i=>$t): ?>
      <div style="display:flex;align-items:center;gap:.3rem;margin-bottom:.25rem;
           padding:.15rem .3rem;border-radius:3px;
           background:<?= $i===0?'rgba(200,153,58,.1)':'transparent' ?>;
           border-left:2px solid <?= $i===0?'var(--gold)':'transparent' ?>;">
        <span style="font-size:.75rem;"><?= $t['tile_icon']??'🗺' ?></span>
        <span style="font-size:.62rem;
             color:<?= $i===0?'var(--gold)':'var(--muted)' ?>;">
          (<?= $t['coord_x'] ?>,<?= $t['coord_y'] ?>
          <?php if (isset($t['coord_z'])): ?>
            ,z=<?= (int)$t['coord_z']>0?'+':''?><?= (int)$t['coord_z'] ?>
          <?php endif; ?>)
        </span>
        <span style="font-size:.55rem;color:var(--muted);margin-left:auto;">
          #<?= $i+1 ?>
        </span>
      </div>
      <?php endforeach; ?>
    <?php else: ?>
      <div style="font-size:.68rem;color:var(--muted);font-style:italic;">No moves yet</div>
    <?php endif; ?>
  </div>

  <!-- QUICK LINKS -->
  <div class="side-card">
    <div class="side-title">LINKS</div>
    <?php foreach ([
      ['scorecard.php',     '🃏 Scorecard'],
      ['wardrobe.php',      '👕 Wardrobe'],
      ['tower.php',         '🗼 Tower'],
      ['guesswho.php',      '🎭 Guess Who'],
      ['tictactoe.php',     '⭕ Tic Tac Toe'],
      ['masterbuilder.php', '🧱 Master Builder'],
      ['battle.php',        '⚔ Arena'],
      ['rules.php',         '📖 Rules'],
      ['changepassword.php','🔑 Password'],
      ['logout.php',        '🚪 Logout'],
    ] as [$url,$label]): ?>
    <a href="<?= $url ?>" style="display:block;font-size:.7rem;color:var(--muted);
         text-decoration:none;padding:.13rem 0;transition:color .15s;"
       onmouseenter="this.style.color='var(--gold)'"
       onmouseleave="this.style.color='var(--muted)'">
      <?= $label ?>
    </a>
    <?php endforeach; ?>
  </div>

</div>

<!-- ══ MIDDLE: ROLL & RESULTS ══════════════════════════════════════════════ -->
<div>

  <!-- ROLL BUTTON + SLEEP -->
  <div class="card" style="text-align:center;margin-bottom:.75rem;padding:1rem;">
    <div style="display:flex;gap:.5rem;justify-content:center;flex-wrap:wrap;
         margin-bottom:.5rem;">
      <a href="walk.php?action=roll" class="btn btn-primary"
         style="font-size:1rem;padding:.7rem 2rem;">
        🎲 Roll the Dice
      </a>
      <a href="walk.php?action=sleep" class="btn btn-outline"
         onclick="return confirm('Sleep 8 hours? Health fully restored but costs a turn.')">
        😴 Sleep
      </a>
      <a href="walk.php?action=randomize" class="btn btn-outline btn-sm"
         style="font-size:.7rem;align-self:center;"
         onclick="return confirm('Randomize all map tiles?')">
        🔀 Randomize
      </a>
    </div>
    <div style="font-size:.75rem;color:var(--muted);">
      <?php if ($pos): ?>
        At (<?= $currentX ?>,<?= $currentY ?>,z=<?= $currentZ>0?'+':''?><?= $currentZ ?>)
        · <?= zIcon($currentZ) ?> <?= zLabel($currentZ) ?>
        <?php if ($myChar&&$myChar['coord_x']!==null): ?>
          &nbsp;·&nbsp; 🧙 at
          (<?= $myChar['coord_x'] ?>,<?= $myChar['coord_y'] ?>,z=<?= $myCharZ>0?'+':''?><?= $myCharZ ?>)
        <?php endif; ?>
      <?php else: ?>
        Roll to enter the map
      <?php endif; ?>
    </div>
  </div>

  <?php if (!empty($rollData)): ?>

  <!-- CAPACITY WARNINGS -->
  <?php foreach ($rollData['capacityWarnings'] as $warn): ?>
  <div class="alert alert-error" style="margin-bottom:.6rem;"><?= $warn ?></div>
  <?php endforeach; ?>

  <!-- SIDE GAME PROMPT -->
  <?php if (!empty($rollData['promptSideGame'])): ?>
  <div class="card" style="border-color:var(--gold);margin-bottom:.65rem;
       text-align:center;padding:1rem;">
    <div style="font-size:1.8rem;margin-bottom:.3rem;">🎮</div>
    <div style="font-family:'Cinzel',serif;font-size:.88rem;color:var(--gold);
         margin-bottom:.4rem;">Time for a Side Game!</div>
    <div style="font-size:.78rem;color:var(--muted);margin-bottom:.75rem;">
      <?= $clock['turn_count'] ?> turns completed. Win a game for tower scrolls!
    </div>
    <div style="display:flex;gap:.4rem;justify-content:center;flex-wrap:wrap;">
      <a href="guesswho.php"      class="btn btn-primary btn-sm">🎭 Guess Who</a>
      <a href="tictactoe.php"     class="btn btn-outline btn-sm">⭕ Tic Tac Toe</a>
      <a href="masterbuilder.php" class="btn btn-outline btn-sm">🧱 Builder</a>
      <a href="walk.php"          class="btn btn-outline btn-sm">Skip</a>
    </div>
  </div>
  <?php endif; ?>

  <!-- DICE RESULT -->
  <div class="card" style="text-align:center;border-color:var(--gold);
       margin-bottom:.65rem;padding:.9rem;">
    <div style="font-family:'Cinzel',serif;font-size:.65rem;letter-spacing:.12em;
         color:var(--muted);margin-bottom:.6rem;">DICE ROLL</div>
    <div style="display:flex;justify-content:center;gap:1.5rem;margin-bottom:.65rem;">
      <?php foreach ([['die1','X','gold'],['die2','Y','ice']] as [$key,$axis,$col]): ?>
      <div style="text-align:center;">
        <div style="font-family:'Cinzel',serif;font-size:.58rem;
             color:var(--muted);margin-bottom:.15rem;">
          D<?= $axis==='X'?1:2 ?> — <?= $axis ?>
        </div>
        <div style="width:3.2rem;height:3.2rem;background:var(--surface);
             border:2px solid var(--<?= $col ?>);border-radius:10px;
             display:flex;align-items:center;justify-content:center;
             font-family:'Cinzel',serif;font-size:1.6rem;
             color:var(--<?= $col ?>);font-weight:700;">
          <?= $rollData[$key] ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <div style="font-family:'Cinzel',serif;font-size:1.2rem;font-weight:700;">
      <span style="color:var(--gold)"><?= $rollData['die1'] ?></span>
      <span style="color:var(--muted);font-size:.85rem;">,</span>
      <span style="color:var(--ice)"><?= $rollData['die2'] ?></span>
    </div>
    <div style="font-size:.75rem;color:var(--muted);margin-top:.3rem;">
      Landed on
      (<?= $rollData['newX'] ?>,<?= $rollData['newY'] ?>,z=<?= $rollData['currentZ']>0?'+':''?><?= $rollData['currentZ'] ?>)
      — <?= zIcon($rollData['currentZ']) ?> <?= zLabel($rollData['currentZ']) ?>
      <?php if ($rollData['newX']===$rollData['newY']): ?>
        &nbsp;<span style="color:var(--gold);font-family:'Cinzel',serif;">
          ✨ Magic Ground!
        </span>
      <?php endif; ?>
    </div>
    <!-- TIME OF DAY -->
    <div style="font-size:.7rem;color:var(--muted);margin-top:.25rem;">
      <?= clockDisplay($rollData['clock']) ?>
    </div>
  </div>

  <!-- TOWER PROMPT -->
  <?php if (!empty($rollData['atTower'])): ?>
  <div class="card" style="border-color:<?= zColor($currentZ) ?>;
       margin-bottom:.65rem;padding:.75rem;text-align:center;">
    <div style="font-size:1.5rem;margin-bottom:.25rem;">🗼</div>
    <div style="font-family:'Cinzel',serif;font-size:.85rem;
         color:<?= zColor($currentZ) ?>;margin-bottom:.4rem;">
      You are at the Tower (5,5,z=<?= $currentZ>0?'+':''?><?= $currentZ ?>)
    </div>
    <div style="font-size:.78rem;color:var(--muted);margin-bottom:.65rem;">
      Climb to <?= zIcon($currentZ+1) ?> <?= zLabel($currentZ+1) ?> (z=<?= $currentZ+1 ?>)
      or slide to <?= zIcon($currentZ-1) ?> <?= zLabel($currentZ-1) ?> (z=<?= $currentZ-1 ?>)
    </div>
    <a href="tower.php" class="btn btn-primary btn-sm">🗼 Enter Tower</a>
  </div>
  <?php endif; ?>

  <!-- CHARACTER MOVED -->
  <?php if (!empty($rollData['movedChars'])): ?>
  <div class="card" style="margin-bottom:.65rem;padding:.7rem;">
    <div style="font-family:'Cinzel',serif;font-size:.65rem;letter-spacing:.08em;
         color:var(--muted);margin-bottom:.35rem;">🧙 YOUR CHARACTER ROAMED</div>
    <?php foreach ($rollData['movedChars'] as $mc): ?>
    <div style="font-size:.8rem;color:var(--text);">
      🧙 <?= htmlspecialchars($mc['name']) ?>
      <span style="color:var(--muted);">
        → (<?= $mc['x'] ?>,<?= $mc['y'] ?>,z=<?= $mc['z']>0?'+':''?><?= $mc['z'] ?>)
      </span>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- TILE LANDED ON -->
  <?php if ($rollData['tile']): $tile=$rollData['tile']; ?>
  <div class="card" style="border-left:4px solid <?= htmlspecialchars($tile['color']) ?>;
       margin-bottom:.65rem;padding:.7rem;">
    <div style="display:flex;align-items:center;gap:.65rem;">
      <div style="width:2.8rem;height:2.8rem;
           background:<?= htmlspecialchars($tile['color']) ?>;
           border-radius:7px;display:flex;align-items:center;
           justify-content:center;font-size:1.3rem;flex-shrink:0;">
        <?= $tile['icon'] ?>
      </div>
      <div>
        <div style="font-family:'Cinzel',serif;font-size:.85rem;color:var(--text);">
          <?= htmlspecialchars($tile['type_name']) ?>
        </div>
        <?php if ($tile['resource']): ?>
          <div style="font-size:.72rem;color:var(--gold);">
            ⚙ <?= htmlspecialchars($tile['resource']) ?>
            <?php if (!empty($rollData['resourceGathered'])): ?>
              <span style="color:var(--success);"> — gathered!</span>
            <?php endif; ?>
          </div>
        <?php endif; ?>
        <?php if ($rollData['multipliers']): ?>
        <div style="display:flex;gap:.25rem;flex-wrap:wrap;margin-top:.2rem;">
          <?php foreach ($rollData['multipliers'] as $el=>$mult): ?>
          <span class="badge badge-<?= $el ?>"
                style="font-size:.6rem;padding:.08rem .3rem;">
            <?= $elIcons[$el] ?> ×<?= $mult ?>
          </span>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- FLOWER & SCROLL GATHERED -->
  <?php if ($rollData['flowerGathered']||$rollData['scrollGathered']): ?>
  <div class="card" style="margin-bottom:.65rem;padding:.65rem;">
    <div style="display:flex;gap:.5rem;align-items:center;flex-wrap:wrap;">
      <?php if ($rollData['flowerGathered']): $fg=$rollData['flowerGathered']; ?>
        <div style="width:.8rem;height:.8rem;border-radius:50%;
             background:<?= htmlspecialchars($fg['hex_base']) ?>;flex-shrink:0;"></div>
        <span style="font-size:.8rem;color:var(--text);">
          🌸 <?= htmlspecialchars($fg['name']) ?> flower
        </span>
      <?php endif; ?>
      <?php if ($rollData['scrollGathered']): ?>
        <span style="font-size:.8rem;color:var(--text);">· 📜 Scroll</span>
      <?php endif; ?>
      <a href="wardrobe.php?tab=scrolls"
         class="btn btn-outline btn-sm"
         style="margin-left:auto;font-size:.65rem;">
        View Scrolls
      </a>
    </div>
  </div>
  <?php endif; ?>

  <!-- WEAPON SCROLL FOUND -->
  <?php if (!empty($rollData['weaponScrollFound'])): $ws=$rollData['weaponScrollFound']; ?>
  <div class="card" style="border-color:var(--gold);margin-bottom:.65rem;padding:.7rem;">
    <div style="font-family:'Cinzel',serif;font-size:.75rem;color:var(--gold);
         margin-bottom:.3rem;">📜 Weapon Scroll Found!</div>
    <div style="font-size:.85rem;color:var(--text);margin-bottom:.15rem;">
      <strong><?= htmlspecialchars($ws['scroll_name']) ?></strong>
    </div>
    <div style="font-size:.75rem;color:var(--muted);margin-bottom:.15rem;">
      Crafts: <?= htmlspecialchars($ws['weapon_name']) ?> &nbsp;·&nbsp;
      Needs: <?= htmlspecialchars($ws['resource1']) ?>
      <?= $ws['resource2']?'+ '.htmlspecialchars($ws['resource2']):'' ?>
    </div>
    <div style="font-size:.72rem;color:var(--muted);font-style:italic;">
      "<?= htmlspecialchars($ws['description']) ?>"
    </div>
  </div>
  <?php endif; ?>

  <!-- CLOTHING FOUND -->
  <?php if (!empty($rollData['clothingFound'])): $cf=$rollData['clothingFound']; ?>
  <div class="card" style="border-color:var(--gold);margin-bottom:.65rem;padding:.7rem;">
    <div style="display:flex;align-items:center;gap:.65rem;">
      <div style="width:2.2rem;height:2.2rem;
           background:<?= htmlspecialchars($cf['hex_value']?:'var(--surface)') ?>;
           border-radius:6px;display:flex;align-items:center;
           justify-content:center;font-size:1rem;flex-shrink:0;">
        <?= ['shirt'=>'👕','pants'=>'👖','socks'=>'🧦'][$cf['slot']]??'👕' ?>
      </div>
      <div>
        <div style="font-family:'Cinzel',serif;font-size:.78rem;color:var(--gold);">
          Clothing Found!
        </div>
        <div style="font-size:.78rem;color:var(--text);">
          <?= htmlspecialchars($cf['type_name']) ?>
          · <?= htmlspecialchars($cf['colour_name']) ?>
        </div>
        <?php if (!empty($cf['shield_element'])): ?>
        <span class="badge badge-<?= $cf['shield_element'] ?>"
              style="font-size:.62rem;margin-top:.15rem;">
          <?= $elIcons[$cf['shield_element']] ?> -<?= $cf['shield_value'] ?>
        </span>
        <?php endif; ?>
      </div>
    </div>
    <div style="margin-top:.5rem;font-size:.72rem;color:var(--gold);
         font-family:'Cinzel',serif;">
      ✓ Added to wardrobe — equip on the Wardrobe screen
    </div>
  </div>
  <?php endif; ?>

  <!-- BOWTIE PICKUP -->
  <?php if (!empty($rollData['pickup'])): $pickup=$rollData['pickup']; ?>
  <div class="card" style="border-color:var(--gold);margin-bottom:.65rem;padding:.7rem;">
    <div style="display:flex;align-items:center;gap:.65rem;">
      <div style="font-size:1.8rem;">🎀</div>
      <div>
        <div style="font-family:'Cinzel',serif;font-size:.85rem;color:var(--gold);">
          <?= htmlspecialchars($pickup['tile']) ?> Bowtie Found!
        </div>
        <div style="font-size:.75rem;color:var(--muted);">
          <?= htmlspecialchars($pickup['weapon']['name']) ?>
          · 🎀 <?= $rollData['bowtieCount'] ?>/<?= MAX_BOWTIES ?>
        </div>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- BOWTIE OVER LIMIT -->
  <?php if (!empty($rollData['bowtieOverLimit'])): ?>
  <div class="card" style="border-color:var(--danger);margin-bottom:.65rem;padding:.7rem;">
    <div style="font-family:'Cinzel',serif;font-size:.75rem;color:var(--danger);
         margin-bottom:.4rem;">
      ⚠ Bowtie Limit! (<?= $rollData['bowtieCount'] ?>/4)
    </div>
    <?php foreach ($rollData['userBowties'] as $b): ?>
    <div style="display:flex;align-items:center;gap:.5rem;margin-bottom:.3rem;">
      <span style="font-size:.78rem;flex:1;color:var(--text);">
        🎀 <?= htmlspecialchars($b['name']) ?>
      </span>
      <a class="btn btn-danger btn-sm"
         style="font-size:.62rem;padding:.2rem .5rem;"
         href="wardrobe.php?action=sell_bowtie&weaponid=<?= $b['id'] ?>&tab=bowties"
         onclick="return confirm('Sell for 🪙10?')">Sell</a>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- BATTLE RESULT -->
  <?php foreach ($rollData['battles'] as $battle):
    $isWinner=$battle['userWon'];
  ?>
  <div class="card" style="border-color:<?= $isWinner?'var(--gold)':'var(--danger)' ?>;
       margin-bottom:.65rem;">
    <div style="text-align:center;margin-bottom:.75rem;">
      <div style="font-size:1.6rem;"><?= $isWinner?'🏆':'💀' ?></div>
      <div style="font-family:'Cinzel',serif;font-size:1rem;font-weight:600;
           color:<?= $isWinner?'var(--gold)':'var(--danger)' ?>;">
        <?= $isWinner?'Victory!':'Defeated!' ?>
      </div>
      <div style="font-size:.75rem;color:var(--muted);margin-top:.2rem;">
        👤 <?= htmlspecialchars($battle['userName']) ?>
        vs 🧙 <?= htmlspecialchars($battle['charName']) ?>
      </div>
      <div style="font-size:.68rem;color:var(--muted);margin-top:.15rem;font-style:italic;">
        <?= $isWinner
          ? '🧙 '.$battle['charName'].' sent to ('.$battle['loserNewX'].','.$battle['loserNewY'].')'
          : '👤 You sent to ('.$battle['loserNewX'].','.$battle['loserNewY'].')' ?>
      </div>
    </div>

    <!-- VS SCORES -->
    <div style="display:grid;grid-template-columns:1fr auto 1fr;gap:.4rem;
         align-items:center;margin-bottom:.65rem;">
      <div style="text-align:center;">
        <div style="font-size:.65rem;color:var(--gold);font-family:'Cinzel',serif;">
          👤 <?= htmlspecialchars($battle['userName']) ?>
        </div>
        <div style="font-family:'Cinzel',serif;font-size:1.3rem;
             color:var(--gold);font-weight:600;"><?= $battle['userTotal'] ?></div>
        <div style="font-size:.62rem;color:var(--success);"><?= $battle['userWins'] ?>W</div>
      </div>
      <div style="font-family:'Cinzel',serif;color:var(--muted);font-size:.8rem;">VS</div>
      <div style="text-align:center;">
        <div style="font-size:.65rem;color:var(--ice);font-family:'Cinzel',serif;">
          🧙 <?= htmlspecialchars($battle['charName']) ?>
        </div>
        <div style="font-family:'Cinzel',serif;font-size:1.3rem;
             color:var(--ice);font-weight:600;"><?= $battle['charTotal'] ?></div>
        <div style="font-size:.62rem;color:var(--success);"><?= $battle['charWins'] ?>W</div>
      </div>
    </div>

    <!-- ELEMENT BARS -->
    <?php foreach ($battle['elementResults'] as $el=>$res):
      [$icon,$cls]=$elements[$el];
      $uw=$res['winner']==='user'; $cw=$res['winner']==='char';
      $total=$res['user']+$res['char'];
      $uPct=$total>0?round(($res['user']/$total)*100):50;
      $mult=isset($rollData['multipliers'][$el])?' ×'.$rollData['multipliers'][$el]:'';
    ?>
    <div style="display:grid;grid-template-columns:2rem 1fr 2rem;gap:.3rem;
         align-items:center;margin-bottom:.2rem;">
      <div style="text-align:right;font-size:.75rem;
           color:<?= $uw?'var(--gold)':'var(--muted)' ?>;
           font-weight:<?= $uw?'600':'400' ?>;">
        <?= $res['user'] ?><?= $uw?'✓':'' ?>
      </div>
      <div>
        <div style="font-size:.58rem;color:var(--<?= $cls ?>);
             text-align:center;margin-bottom:.1rem;">
          <?= $icon ?><?= $mult ?>
        </div>
        <div style="background:var(--border);border-radius:8px;height:4px;
             overflow:hidden;display:flex;">
          <div style="background:var(--gold);width:<?= $uPct ?>%;height:100%;"></div>
          <div style="background:var(--ice);width:<?= 100-$uPct ?>%;height:100%;"></div>
        </div>
      </div>
      <div style="font-size:.75rem;
           color:<?= $cw?'var(--ice)':'var(--muted)' ?>;
           font-weight:<?= $cw?'600':'400' ?>;">
        <?= $cw?'✓':'' ?><?= $res['char'] ?>
      </div>
    </div>
    <?php endforeach; ?>

    <!-- DAMAGE -->
    <?php if (!$battle['userWon']&&$battle['damageDealt']>0): ?>
    <div style="margin-top:.6rem;padding:.45rem .6rem;
         background:rgba(192,57,43,.1);border:1px solid var(--danger);
         border-radius:var(--radius);text-align:center;">
      <div style="font-size:.78rem;color:var(--danger);margin-bottom:.3rem;">
        💔 Lost <?= $battle['damageDealt'] ?> HP
        (<?= $battle['healthBefore'] ?> → <?= $battle['healthAfter'] ?>)
      </div>
      <?= healthBarHtml(['health'=>$battle['healthAfter'],'max_health'=>100],false) ?>
      <?php if ($battle['healthAfter']<=25): ?>
      <a href="wardrobe.php?tab=shop" class="btn btn-danger btn-sm"
         style="margin-top:.4rem;font-size:.65rem;">
        🧪 Buy Health Pack
      </a>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <div style="text-align:center;margin-top:.5rem;font-family:'Cinzel',serif;
         font-size:.75rem;color:var(--gold);">
      ⚔ <?= htmlspecialchars($battle['winnerName']) ?> wins!
    </div>
  </div>
  <?php endforeach; ?>

  <?php endif; // end rollData ?>

  <!-- RECENT BATTLES -->
  <?php if ($recentBattles): ?>
  <div class="card" style="margin-top:.5rem;padding:.75rem;">
    <div style="font-family:'Cinzel',serif;font-size:.65rem;letter-spacing:.08em;
         color:var(--gold);margin-bottom:.5rem;">⚔ YOUR BATTLE HISTORY</div>
    <div class="tbl-wrap">
      <table>
        <thead>
          <tr><th>vs</th><th>Result</th><th>When</th></tr>
        </thead>
        <tbody>
        <?php foreach ($recentBattles as $b): ?>
          <tr>
            <td style="font-size:.75rem;">
              🧙 <?= htmlspecialchars($b['defender_name']) ?>
            </td>
            <td style="font-family:'Cinzel',serif;font-size:.7rem;
                 color:<?= $b['winner_type']==='user'?'var(--success)':'var(--danger)' ?>">
              <?= $b['winner_type']==='user'?'🏆 Won':'💀 Lost' ?>
            </td>
            <td style="color:var(--muted);font-size:.65rem;"><?= $b['fought_at'] ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>

</div>

<!-- ══ RIGHT: MAP + CHARTS ══════════════════════════════════════════════════ -->
<div>

  <!-- MAP -->
  <div class="card" style="padding:.75rem;">
    <div style="display:flex;justify-content:space-between;align-items:center;
         margin-bottom:.65rem;flex-wrap:wrap;gap:.4rem;">
      <div style="font-family:'Cinzel',serif;font-size:.7rem;letter-spacing:.08em;
           color:<?= zColor($currentZ) ?>;">
        🗺 MAP — <?= zIcon($currentZ) ?> <?= zLabel($currentZ) ?>
        (z=<?= $currentZ>0?'+':''?><?= $currentZ ?>)
      </div>
      <!-- Z SWITCHER HINT -->
      <div style="font-size:.62rem;color:var(--muted);">
        <?php if ($currentZ<Z_SKY): ?>
          ⬆ <?= zIcon(Z_SKY) ?> z=+1 via Tower
        <?php endif; ?>
        <?php if ($currentZ>Z_UNDERWORLD): ?>
          ⬇ <?= zIcon(Z_UNDERWORLD) ?> z=-1 via Tower
        <?php endif; ?>
      </div>
    </div>

    <div style="overflow-x:auto;">
      <?php
      $recentRollX = !empty($rollData) ? $rollData['newX'] : null;
      $recentRollY = !empty($rollData) ? $rollData['newY'] : null;
      for ($y=$minY;$y<=$maxY;$y++):
      ?>
      <div style="display:flex;gap:2px;margin-bottom:2px;
           margin-left:<?= ($y%2!==0)?'22px':'0' ?>">
        <?php for ($x=$minX;$x<=$maxX;$x++):
          $tile        = $tileGrid[$x][$y] ?? null;
          $isUserHere  = ($currentX==$x && $currentY==$y);
          $isCharHere  = ($myCharX===$x && $myCharY===$y && $myCharZ===$currentZ);
          $isLanded    = ($recentRollX===$x && $recentRollY===$y);
          $isDiag      = ($x===$y);
          $isTower     = ($x===5 && $y===5);
          $trailIdx    = -1;
          foreach ($trailCoords as $ti=>$tc) {
              if ($tc[0]==$x&&$tc[1]==$y){$trailIdx=$ti;break;}
          }
        ?>
        <?php if ($tile): ?>
          <div style="width:44px;height:44px;flex-shrink:0;
               background:<?= htmlspecialchars($tile['color']) ?>;
               border-radius:5px;
               display:flex;flex-direction:column;align-items:center;
               justify-content:center;
               border:2px solid <?= $isLanded?'var(--gold)':($trailIdx>=0?'rgba(200,153,58,.45)':($isTower?'rgba(255,255,255,.5)':($isDiag?'rgba(255,255,255,.2)':'rgba(255,255,255,.06)'))) ?>;
               position:relative;font-size:.75rem;cursor:default;"
               title="<?= htmlspecialchars($tile['type_name']) ?>
                      (<?= $x ?>,<?= $y ?>,z=<?= $currentZ ?>)
                      <?= $isTower?' 🗼 Tower':'' ?>
                      <?= $isDiag?' ✨':'' ?>">
            <?= $isTower ? '🗼' : $tile['icon'] ?>
            <?php if ($isUserHere): ?>
              <div style="position:absolute;top:1px;left:2px;
                   font-size:.52rem;line-height:1;">👤</div>
            <?php endif; ?>
            <?php if ($isCharHere): ?>
              <div style="position:absolute;top:1px;right:2px;
                   font-size:.52rem;line-height:1;">🧙</div>
            <?php endif; ?>
            <?php if ($isUserHere&&$isCharHere): ?>
              <div style="position:absolute;top:50%;left:50%;
                   transform:translate(-50%,-50%);
                   font-size:.45rem;color:var(--fire);
                   font-family:'Cinzel',serif;font-weight:700;">⚔</div>
            <?php endif; ?>
            <?php if ($isDiag&&!$isTower): ?>
              <div style="position:absolute;bottom:1px;right:1px;
                   font-size:.4rem;">✨</div>
            <?php endif; ?>
            <?php if ($trailIdx>=0): ?>
              <div style="position:absolute;bottom:0;left:0;
                   background:rgba(200,153,58,.75);
                   border-radius:0 3px 0 3px;font-size:.42rem;
                   color:#000;padding:0 2px;
                   font-family:'Cinzel',serif;line-height:1.5;">
                <?= $trailIdx+1 ?>
              </div>
            <?php endif; ?>
            <div style="font-size:.38rem;color:rgba(255,255,255,.4);line-height:1;">
              <?= $x ?>,<?= $y ?>
            </div>
          </div>
        <?php else: ?>
          <div style="width:44px;height:44px;flex-shrink:0;
               background:var(--surface);border-radius:5px;
               border:1px dashed <?= $isDiag?'rgba(255,255,255,.15)':'var(--border)' ?>;">
          </div>
        <?php endif; ?>
        <?php endfor; ?>
      </div>
      <?php endfor; ?>
    </div>

    <!-- MAP LEGEND -->
    <div style="margin-top:.6rem;display:flex;gap:.5rem;flex-wrap:wrap;
         font-size:.6rem;color:var(--muted);">
      <span>👤 you</span>
      <span>🧙 char</span>
      <span>🗼 tower (5,5)</span>
      <span style="border:2px solid var(--gold);padding:0 .2rem;border-radius:2px;">
        gold
      </span>=landed
      <span style="border:2px solid rgba(200,153,58,.45);padding:0 .2rem;border-radius:2px;">
        dim
      </span>=trail
      <span>✨=x=y magic</span>
      <span style="background:rgba(200,153,58,.75);color:#000;
           padding:0 .2rem;border-radius:2px;">1</span>=move#
      <span>⚔=same tile</span>
    </div>

    <!-- LAYER SWITCHER -->
    <div style="margin-top:.75rem;display:flex;gap:.4rem;flex-wrap:wrap;">
      <?php foreach ([-1,0,1] as $z): ?>
      <div style="flex:1;padding:.4rem;text-align:center;
           background:<?= $z===$currentZ?'rgba(200,153,58,.1)':'var(--surface)' ?>;
           border-radius:var(--radius);
           border:1px solid <?= $z===$currentZ?'var(--gold)':'var(--border)' ?>;
           font-size:.65rem;">
        <div style="color:<?= zColor($z) ?>;"><?= zIcon($z) ?></div>
        <div style="font-family:'Cinzel',serif;color:<?= zColor($z) ?>;font-size:.6rem;">
          z=<?= $z>0?'+':''?><?= $z ?>
        </div>
        <div style="color:var(--muted);font-size:.55rem;"><?= zLabel($z) ?></div>
        <?php if ($z===$currentZ): ?>
          <div style="color:var(--gold);font-size:.55rem;">← HERE</div>
        <?php else: ?>
          <div style="font-size:.55rem;color:var(--muted);">via 🗼</div>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- PIE CHARTS -->
  <?php if ($scrollChartData||$flowerChartData): ?>
  <div class="card" style="margin-top:.75rem;padding:.75rem;">
    <div style="font-family:'Cinzel',serif;font-size:.65rem;letter-spacing:.08em;
         color:var(--gold);margin-bottom:.65rem;">📊 SCROLLS & FLOWERS</div>
    <div style="display:flex;gap:1rem;flex-wrap:wrap;
         justify-content:center;align-items:flex-start;">

      <?php if ($scrollChartData): ?>
      <div style="text-align:center;">
        <div style="font-size:.6rem;color:var(--muted);margin-bottom:.25rem;">
          📜 Scrolls (<?= $totalBC ?>/30)
        </div>
        <?= buildPie($scrollChartData,$totalBC,70) ?>
        <div style="display:flex;flex-wrap:wrap;gap:.15rem;justify-content:center;
             margin-top:.25rem;max-width:110px;">
          <?php foreach ($scrollChartData as $s): ?>
          <div style="display:flex;align-items:center;gap:.12rem;
               font-size:.48rem;color:var(--muted);">
            <div style="width:.4rem;height:.4rem;border-radius:1px;
                 background:<?= htmlspecialchars($s['hex_base']) ?>;flex-shrink:0;">
            </div>
            <?= htmlspecialchars($s['name']) ?>(<?= $s['quantity'] ?>)
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <?php if ($flowerChartData): ?>
      <div style="text-align:center;">
        <div style="font-size:.6rem;color:var(--muted);margin-bottom:.25rem;">
          🌸 Flowers (<?= $totalFC ?>/120)
        </div>
        <?= buildPie($flowerChartData,$totalFC,70) ?>
        <div style="display:flex;flex-wrap:wrap;gap:.15rem;justify-content:center;
             margin-top:.25rem;max-width:110px;">
          <?php foreach ($flowerChartData as $s): ?>
          <div style="display:flex;align-items:center;gap:.12rem;
               font-size:.48rem;color:var(--muted);">
            <div style="width:.4rem;height:.4rem;border-radius:50%;
                 background:<?= htmlspecialchars($s['hex_base']) ?>;flex-shrink:0;">
            </div>
            <?= htmlspecialchars($s['name']) ?>(<?= $s['quantity'] ?>)
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <!-- CAPACITY BARS -->
      <div style="flex:1;min-width:90px;display:flex;flex-direction:column;
           justify-content:center;gap:.4rem;">
        <?php foreach ([
          ['📜','Scrolls',$totalBC,  30, 'var(--gold)'],
          ['🌸','Flowers',$totalFC, 120, 'var(--fire)'],
        ] as [$icon,$label,$cur,$max,$col]):
          $pct=min(100,round(($cur/$max)*100));
        ?>
        <div>
          <div style="display:flex;justify-content:space-between;font-size:.6rem;
               color:var(--muted);margin-bottom:.1rem;">
            <span><?= $icon ?> <?= $label ?></span>
            <span style="color:<?= $pct>=100?'var(--danger)':$col ?>">
              <?= $cur ?>/<?= $max ?>
            </span>
          </div>
          <div style="background:var(--border);border-radius:6px;height:5px;overflow:hidden;">
            <div style="background:<?= $pct>=100?'var(--danger)':$col ?>;
                 width:<?= $pct ?>%;height:100%;"></div>
          </div>
        </div>
        <?php endforeach; ?>
        <div style="font-size:.58rem;color:var(--muted);line-height:1.7;margin-top:.2rem;">
          📜×5 = ⚗ Dye<br>
          🌸×15 = 📜×1<br>
          ✨ = 🎀 Tile Bowtie<br>
          🗼(5,5) = Change z
        </div>
      </div>

    </div>
  </div>
  <?php endif; ?>

</div>

</div>

<?php pageFooter(); ?>