<?php
declare(strict_types=1);

function db(): mysqli
{
    static $mysqli = null;
    if ($mysqli instanceof mysqli) {
        return $mysqli;
    }
    $cfg = require ROOT_PATH . '/config/database.php';
    $mysqli = mysqli_init();
    if ($mysqli === false) {
        http_response_code(500);
        echo 'Cannot start MySQL.';
        exit;
    }
    $mysqli->options(MYSQLI_OPT_CONNECT_TIMEOUT, 2);
    if (defined('MYSQLI_OPT_READ_TIMEOUT')) {
        $mysqli->options(MYSQLI_OPT_READ_TIMEOUT, 5);
    }
    if (defined('MYSQLI_OPT_INT_AND_FLOAT_NATIVE')) {
        $mysqli->options(MYSQLI_OPT_INT_AND_FLOAT_NATIVE, 1);
    }
    $ok = @$mysqli->real_connect($cfg['host'], $cfg['user'], $cfg['pass'], $cfg['name']);
    if (!$ok) {
        $ok = @$mysqli->real_connect($cfg['host'], $cfg['user'], $cfg['pass']);
        if (!$ok) {
            http_response_code(500);
            echo 'Cannot connect to MySQL. Start MySQL in XAMPP and check config/database.php.';
            exit;
        }
        if (!isset($_GET['installing'])) {
            header('Location: ' . url('install.php'));
            exit;
        }
    }
    $mysqli->set_charset('utf8mb4');
    if ($mysqli->select_db($cfg['name'] ?? '')) {
        require_once ROOT_PATH . '/includes/migrate.php';
        try {
            folio_migrate($mysqli);
        } catch (Throwable $e) {
            // Schema not ready until install.php runs.
        }
    }
    return $mysqli;
}

function db_prepare(string $sql, string $types = '', array $params = []): mysqli_stmt
{
    $sql = trim($sql);
    if ($sql === '' || str_contains($sql, ';')) {
        throw new InvalidArgumentException('Invalid SQL.');
    }
    $placeholders = substr_count($sql, '?');
    if ($placeholders !== strlen($types) || $placeholders !== count($params)) {
        throw new InvalidArgumentException('SQL parameter mismatch.');
    }
    $stmt = db()->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException(db()->error);
    }
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    return $stmt;
}

function db_one(string $sql, string $types = '', array $params = []): ?array
{
    $row = db_all($sql, $types, $params);
    return $row[0] ?? null;
}

function db_all(string $sql, string $types = '', array $params = []): array
{
    $stmt = db_prepare($sql, $types, $params);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
    return $rows;
}

function db_exec(string $sql, string $types = '', array $params = []): int
{
    $stmt = db_prepare($sql, $types, $params);
    $stmt->execute();
    $id = $stmt->insert_id;
    $stmt->close();
    return (int) $id;
}
