<?php
// guess_game.php — AJAX API (set-aware)
session_start();
ob_start();
require_once 'guess_db.php';
header('Content-Type: application/json');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

function jsonOut(array $data): void {
    ob_end_clean();
    echo json_encode($data);
    exit;
}

function getSession(PDO $db): array {
    if (!isset($_SESSION['game_key'])) return ['error' => 'No active game.'];
    $stmt = $db->prepare("SELECT * FROM game_sessions WHERE session_key = ?");
    $stmt->execute([$_SESSION['game_key']]);
    $s = $stmt->fetch();
    return $s ?: ['error' => 'Session not found. Start a new game.'];
}

// ── get_sets ─────────────────────────────────────────────
if ($action === 'get_sets') {
    $db   = getDB();
    $sets = loadSets($db);
    jsonOut(['success' => true, 'sets' => $sets]);
}

// ── get_traits ───────────────────────────────────────────
if ($action === 'get_traits') {
    $setId = (int)($_GET['set_id'] ?? $_POST['set_id'] ?? 0);
    if (!$setId) jsonOut(['error' => 'set_id required']);
    $db   = getDB();
    $defs = loadTraitDefs($db, $setId);
    $out  = [];
    foreach ($defs as $d) {
        $out[] = ['label' => $d['label'], 'values' => json_decode($d['possible_values'], true) ?? []];
    }
    jsonOut(['success' => true, 'traits' => $out]);
}

// ── new_game ─────────────────────────────────────────────
if ($action === 'new_game') {
    $setId = (int)($_POST['set_id'] ?? 0);
    if (!$setId) jsonOut(['error' => 'set_id required']);

    $db = getDB();

    // Verify set exists
    $stmt = $db->prepare("SELECT * FROM character_sets WHERE id = ?");
    $stmt->execute([$setId]);
    $set = $stmt->fetch();
    if (!$set) jsonOut(['error' => 'Character set not found.']);

    $count = (int)$db->prepare("SELECT COUNT(*) FROM characters WHERE set_id = ?")->execute([$setId]) ? 
             $db->prepare("SELECT COUNT(*) FROM characters WHERE set_id = ?")->execute([$setId]) : 0;
    // Re-query properly
    $cntStmt = $db->prepare("SELECT COUNT(*) FROM characters WHERE set_id = ?");
    $cntStmt->execute([$setId]);
    $count = (int)$cntStmt->fetchColumn();
    if ($count < 2) jsonOut(['error' => 'This set needs at least 2 characters before you can play.']);

    // Pick random secret character from this set
    $stmt = $db->prepare("SELECT id FROM characters WHERE set_id = ? ORDER BY RAND() LIMIT 1");
    $stmt->execute([$setId]);
    $secretId = (int)$stmt->fetchColumn();

    // Clean up old session
    if (isset($_SESSION['game_key'])) {
        $db->prepare("DELETE FROM game_sessions WHERE session_key = ?")->execute([$_SESSION['game_key']]);
    }
    $key = bin2hex(random_bytes(16));
    $_SESSION['game_key'] = $key;

    $db->prepare("INSERT INTO game_sessions (session_key, set_id, secret_character_id, eliminated_ids, questions_asked, won)
                  VALUES (?, ?, ?, '', 0, 0)")
       ->execute([$key, $setId, $secretId]);

    $chars = loadCharactersBySet($db, $setId);
    jsonOut(['success' => true, 'characters' => $chars, 'set' => $set]);
}

// ── ask_question ─────────────────────────────────────────
if ($action === 'ask_question') {
    $db      = getDB();
    $session = getSession($db);
    if (isset($session['error'])) jsonOut($session);
    if ($session['won'])          jsonOut(['error' => 'Game already won!']);

    $traitLabel = trim($_POST['trait_label'] ?? '');
    $traitValue = trim($_POST['trait_value'] ?? '');
    if ($traitLabel === '' || $traitValue === '') jsonOut(['error' => 'trait_label and trait_value required.']);

    $setId = (int)$session['set_id'];

    // Find trait def scoped to this set
    $stmt = $db->prepare("SELECT id FROM trait_definitions WHERE set_id = ? AND label = ?");
    $stmt->execute([$setId, $traitLabel]);
    $def = $stmt->fetch();
    if (!$def) jsonOut(['error' => 'Unknown trait for this set: ' . $traitLabel]);
    $defId = $def['id'];

    // Secret character's value for this trait
    $stmt = $db->prepare("SELECT value FROM character_traits WHERE character_id = ? AND trait_def_id = ?");
    $stmt->execute([$session['secret_character_id'], $defId]);
    $row = $stmt->fetch();
    $secretVal = $row ? strtolower($row['value']) : null;

    $answer = ($secretVal !== null && $secretVal === strtolower($traitValue));

    if ($answer) {
        // Eliminate characters in this set whose value does NOT match
        $stmt2 = $db->prepare("
            SELECT c.id FROM characters c
            WHERE c.set_id = ?
            AND c.id NOT IN (
                SELECT character_id FROM character_traits
                WHERE trait_def_id = ? AND LOWER(value) = LOWER(?)
            )
        ");
        $stmt2->execute([$setId, $defId, $traitValue]);
    } else {
        // Eliminate characters in this set whose value DOES match
        $stmt2 = $db->prepare("
            SELECT ct.character_id AS id FROM character_traits ct
            JOIN characters c ON ct.character_id = c.id
            WHERE c.set_id = ? AND ct.trait_def_id = ? AND LOWER(ct.value) = LOWER(?)
        ");
        $stmt2->execute([$setId, $defId, $traitValue]);
    }
    $newIds = array_column($stmt2->fetchAll(), 'id');

    $existing = $session['eliminated_ids'] ? explode(',', $session['eliminated_ids']) : [];
    $combined = array_values(array_unique(array_merge($existing, $newIds)));

    $db->prepare("UPDATE game_sessions SET eliminated_ids = ?, questions_asked = questions_asked + 1 WHERE session_key = ?")
       ->execute([implode(',', $combined), $session['session_key']]);

    jsonOut([
        'success'          => true,
        'answer'           => $answer,
        'answer_text'      => $answer ? 'YES ✓' : 'NO ✗',
        'eliminated'       => array_map('intval', $combined),
        'newly_eliminated' => array_map('intval', $newIds),
        'questions_asked'  => $session['questions_asked'] + 1,
    ]);
}

// ── make_guess ───────────────────────────────────────────
if ($action === 'make_guess') {
    $db      = getDB();
    $session = getSession($db);
    if (isset($session['error'])) jsonOut($session);

    $guessId = (int)($_POST['character_id'] ?? 0);
    $correct = ($guessId === (int)$session['secret_character_id']);

    if ($correct) {
        $db->prepare("UPDATE game_sessions SET won = 1 WHERE session_key = ?")->execute([$session['session_key']]);
    }

    $stmt = $db->prepare("SELECT * FROM characters WHERE id = ?");
    $stmt->execute([$session['secret_character_id']]);
    $secret = $stmt->fetch();

    $stmt2 = $db->prepare("
        SELECT td.label, ct.value FROM character_traits ct
        JOIN trait_definitions td ON ct.trait_def_id = td.id
        WHERE ct.character_id = ? ORDER BY td.sort_order
    ");
    $stmt2->execute([$secret['id']]);
    $secret['traits'] = $stmt2->fetchAll(PDO::FETCH_KEY_PAIR);

    jsonOut([
        'success'   => true,
        'correct'   => $correct,
        'secret'    => $secret,
        'questions' => $session['questions_asked'],
    ]);
}

jsonOut(['error' => 'Unknown action: ' . htmlspecialchars($action)]);
