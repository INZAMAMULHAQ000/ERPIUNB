<?php

/**
 * Database Configuration
 * Automatically detects local vs production environment.
 * Production credentials loaded from this file (non-public server).
 */

define('DB_CHARSET', 'utf8mb4');

/**
 * Lightweight PDO compatibility layer for legacy MySQLi-style AI services.
 *
 * The AI communications stack still uses bind_param(), get_result(),
 * fetch_assoc(), insert_id, and affected_rows semantics. The rest of the
 * application expects PDO. This wrapper keeps both call styles working.
 */
class CompatPDOStatement extends PDOStatement
{
    private array $compatParams = [];

    protected function __construct(...$args)
    {
        // PDO instantiates the custom statement class with constructor args.
        // The wrapper does not need to do anything special here.
    }

    public function bind_param(string $types, ...$vars): bool
    {
        $this->compatParams = $vars;
        return true;
    }

    public function execute(?array $params = null): bool
    {
        if ($params === null && !empty($this->compatParams)) {
            $params = $this->compatParams;
        }

        $this->compatParams = [];
        return $params === null ? parent::execute() : parent::execute($params);
    }

    public function get_result(): self
    {
        return $this;
    }

    public function fetch_assoc(): array|false
    {
        $row = parent::fetch(PDO::FETCH_ASSOC);
        return $row === false ? false : $row;
    }

    public function close(): void
    {
        $this->closeCursor();
    }

    public function __get(string $name)
    {
        return match ($name) {
            'insert_id' => function_exists('getDB') ? (int) getDB()->lastInsertId() : 0,
            'affected_rows' => $this->rowCount(),
            'num_rows' => $this->rowCount(),
            default => null,
        };
    }
}

class CompatPDO extends PDO
{
    public function __construct(string $dsn, string $username, string $password, array $options = [])
    {
        parent::__construct($dsn, $username, $password, $options);
        $this->setAttribute(PDO::ATTR_STATEMENT_CLASS, [CompatPDOStatement::class, []]);
    }
}

// ——— Environment Detection ———
// Credentials come from config/db.local.php when that file exists. Deciding by
// file rather than by host or SAPI is what makes this safe on a real server:
// cron and CLI entry points used to satisfy php_sapi_name() === 'cli' and would
// silently connect to the local development database instead of production.
$isLocal = (php_sapi_name() === 'cli')
        || in_array($_SERVER['SERVER_NAME'] ?? '', ['localhost', '127.0.0.1', '::1'])
        || in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1', '::1']);

$localConfig = __DIR__ . '/db.local.php';

if (is_file($localConfig)) {
    $config = require $localConfig;
    foreach (['host' => 'DB_HOST', 'name' => 'DB_NAME', 'user' => 'DB_USER', 'pass' => 'DB_PASS'] as $key => $const) {
        if (!array_key_exists($key, (array) $config)) {
            die("Database configuration error: config/db.local.php is missing the '$key' entry.");
        }
    }
    define('DB_HOST', $config['host']);
    define('DB_NAME', $config['name']);
    define('DB_USER', $config['user']);
    define('DB_PASS', $config['pass']);
    define('IS_PRODUCTION', !empty($config['production']));
} elseif ($isLocal) {
    // Development default. XAMPP's root account has no password; a production
    // host must ship config/db.local.php and never rely on this branch.
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'erp_db');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    define('IS_PRODUCTION', false);
} else {
    // Refusing to start is the only safe response: guessing credentials here is
    // how a deployment ends up writing into the wrong database.
    http_response_code(500);
    error_log('config/db.local.php is absent — cannot resolve production database credentials.');
    die('Application is not configured. Copy config/db.local.example.php to config/db.local.php.');
}

// ——— Production Error Handling ———
if (defined('IS_PRODUCTION') && IS_PRODUCTION) {
    error_reporting(0);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

function getDB() {
    static $pdo = null;

    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        try {
            $pdo = new CompatPDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log("Database Connection Error: " . $e->getMessage());
            if (defined('IS_PRODUCTION') && IS_PRODUCTION) {
                die("Database connection failed. Please contact administrator.");
            } else {
                die("DB Error: " . $e->getMessage());
            }
        }
    }

    return $pdo;
}
