<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

function cli_db(string $database): PDO
{
    if (!preg_match('/\Aatikha_(?:finance|test_[a-z0-9_]+)\z/', $database)) {
        throw new RuntimeException('Database must be atikha_finance or an atikha_test_* database.');
    }
    return new PDO('mysql:host=' . (getenv('ATIKHA_DB_HOST') ?: '127.0.0.1') . ';dbname=' . $database . ';charset=utf8mb4',
        getenv('ATIKHA_DB_USER') ?: 'root', getenv('ATIKHA_DB_PASSWORD') ?: '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
         PDO::ATTR_EMULATE_PREPARES => false]);
}

function cli_require(bool $condition, string $message): void
{
    if (!$condition) { throw new RuntimeException($message); }
}

function cli_sql_file(PDO $pdo, string $path): void
{
    // Supports mysql DELIMITER directives. Migrations contain no embedded delimiters in literals.
    $delimiter = ';'; $buffer = '';
    foreach (file($path) as $line) {
        if (preg_match('/^DELIMITER\s+(\S+)/i', trim($line), $m)) { $delimiter = $m[1]; continue; }
        if (str_starts_with(ltrim($line), '--') || trim($line) === '') { continue; }
        $buffer .= $line;
        if (str_ends_with(rtrim($buffer), $delimiter)) {
            $sql = substr(rtrim($buffer), 0, -strlen($delimiter));
            // Old migrations hardcode the live USE; the caller already selected the target.
            if (!preg_match('/^\s*(USE\s|CREATE DATABASE\s)/i', $sql)) { $pdo->exec($sql); }
            $buffer = '';
        }
    }
    cli_require(trim($buffer) === '', 'Unterminated migration SQL.');
}
