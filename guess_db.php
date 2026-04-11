<?php
// guess_db.php — Database connection & shared helpers

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'guess_who');
define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('UPLOAD_URL', 'uploads/');

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
            ]);
        } catch (PDOException $e) {
            $isApi = isset($_POST['action']) || isset($_GET['action']);
            if ($isApi) {
                header('Content-Type: application/json');
                die(json_encode(['error' => 'DB connection failed: ' . $e->getMessage()]));
            }
            die('<div style="color:#ff6b6b;font-family:Georgia;padding:2rem;background:#0d0b12;">
                <h2>⚠️ Database Connection Failed</h2>
                <p>' . htmlspecialchars($e->getMessage()) . '</p>
                <p>Make sure MySQL is running and you have run <code>guess_setup.sql</code>.</p>
            </div>');
        }
    }
    return $pdo;
}

if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);

/** Load all character sets (id, name, description, character_count). */
function loadSets(PDO $db): array {
    return $db->query("
        SELECT cs.*, COUNT(c.id) AS character_count
        FROM character_sets cs
        LEFT JOIN characters c ON c.set_id = cs.id
        GROUP BY cs.id
        ORDER BY cs.name
    ")->fetchAll();
}

/** Load all characters for a set, with their traits keyed by label. */
function loadCharactersBySet(PDO $db, int $setId): array {
    $chars = $db->prepare("SELECT * FROM characters WHERE set_id = ? ORDER BY name");
    $chars->execute([$setId]);
    $chars = $chars->fetchAll();
    if (empty($chars)) return [];

    $ids = array_column($chars, 'id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $db->prepare("
        SELECT ct.character_id, td.label, ct.value
        FROM character_traits ct
        JOIN trait_definitions td ON ct.trait_def_id = td.id
        WHERE ct.character_id IN ($placeholders)
        ORDER BY td.sort_order, td.label
    ");
    $stmt->execute($ids);
    $traitMap = [];
    foreach ($stmt->fetchAll() as $t) {
        $traitMap[$t['character_id']][$t['label']] = $t['value'];
    }
    foreach ($chars as &$c) {
        $c['traits'] = $traitMap[$c['id']] ?? [];
    }
    return $chars;
}

/** Load trait definitions for a set. */
function loadTraitDefs(PDO $db, int $setId): array {
    $stmt = $db->prepare("SELECT * FROM trait_definitions WHERE set_id = ? ORDER BY sort_order, label");
    $stmt->execute([$setId]);
    return $stmt->fetchAll();
}
