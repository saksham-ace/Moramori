<?php
function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]
            );
        } catch (PDOException $e) {
            if (basename($_SERVER['SCRIPT_NAME']) !== 'install.php') {
                http_response_code(500);
                $msg = APP_DEBUG ? htmlspecialchars($e->getMessage()) : 'Database unavailable.';
                exit('<p style="font-family:sans-serif;padding:40px">Store is not set up yet. '
                    . 'Run <a href="' . BASE_URL . '/install.php">install.php</a>.<br><small>' . $msg . '</small></p>');
            }
            throw $e;
        }
    }
    return $pdo;
}

function q(string $sql, array $params = []): PDOStatement
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st;
}

function q_one(string $sql, array $params = []): ?array
{
    $row = q($sql, $params)->fetch();
    return $row === false ? null : $row;
}

function q_all(string $sql, array $params = []): array
{
    return q($sql, $params)->fetchAll();
}

function q_val(string $sql, array $params = [])
{
    $v = q($sql, $params)->fetchColumn();
    return $v === false ? null : $v;
}
