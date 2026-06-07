<?php
// ── ERROR DISPLAY (remove once everything is working) ─────────────────────────
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// Catches fatal errors and parse errors that display_errors misses
register_shutdown_function(function () {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        echo '<div style="position:fixed;top:0;left:0;right:0;z-index:9999;
              background:#c0392b;color:#fff;padding:1rem;font-family:monospace;font-size:.85rem;">
              <strong>FATAL:</strong> ' . htmlspecialchars($e['message'])
            . ' in <em>' . htmlspecialchars($e['file']) . '</em> line ' . $e['line']
            . '</div>';
    }
});

set_exception_handler(function (Throwable $e) {
    echo '<div style="position:fixed;top:0;left:0;right:0;z-index:9999;
          background:#8b0000;color:#fff;padding:1rem;font-family:monospace;font-size:.85rem;">
          <strong>' . get_class($e) . ':</strong> ' . htmlspecialchars($e->getMessage())
        . ' in <em>' . htmlspecialchars($e->getFile()) . '</em> line ' . $e->getLine()
        . '</div>';
});

// ── DATABASE ──────────────────────────────────────────────────────────────────
if (file_exists(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
} else {
    // Local development fallback (USBWebserver)
    define('DB_HOST', 'localhost');
    define('DB_USER', 'root');
    define('DB_PASS', 'usbw');
    define('DB_NAME', 'stailian');
}

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec("SET NAMES utf8mb4");
    }
    return $pdo;
}
