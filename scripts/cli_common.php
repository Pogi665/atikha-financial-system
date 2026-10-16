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
    // Explicit disposable-test compatibility mode; never used for working migrations.
    $inline = getenv('ATIKHA_TEST_INLINE_ROUTINES') === '1'; $procedures = [];
    if ($inline) {
        cli_require(defined('ATIKHA_ISOLATED_TEST') && ATIKHA_ISOLATED_TEST === true,
            'Inline routines require explicit isolated-test mode.');
        $testDatabase = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
        cli_require((bool)preg_match('/\Aatikha_test_[a-z0-9_]+\z/', $testDatabase),
            'Inline routines require a disposable database.');
    }
    foreach (file($path) as $line) {
        if (preg_match('/^DELIMITER\s+(\S+)/i', trim($line), $m)) { $delimiter = $m[1]; continue; }
        if (str_starts_with(ltrim($line), '--') || trim($line) === '') { continue; }
        $buffer .= $line;
        if (str_ends_with(rtrim($buffer), $delimiter)) {
            $sql = substr(rtrim($buffer), 0, -strlen($delimiter));
            // Old migrations hardcode the live USE; the caller already selected the target.
            if (!preg_match('/^\s*(USE\s|CREATE DATABASE\s)/i', $sql)) {
                if ($inline) {
                    cli_require($pdo->query('SELECT DATABASE()')->fetchColumn() === $testDatabase,
                        'Disposable SQL target changed.');
                    if (preg_match('/\ACREATE PROCEDURE\s+(\w+)\s*\(\s*\)\s+BEGIN\b(.*)END\s*\z/is', trim($sql), $m)) {
                        $procedures[$m[1]] = 'BEGIN NOT ATOMIC'.$m[2].'END'; $buffer = ''; continue;
                    }
                    if (preg_match('/\ADROP PROCEDURE\s+(?:IF EXISTS\s+)?(\w+)\s*\z/i', trim($sql), $m)) {
                        unset($procedures[$m[1]]); $buffer = ''; continue;
                    }
                    if (preg_match('/\ACALL\s+(\w+)\s*\(\s*\)\s*\z/i', trim($sql), $m)) {
                        cli_require(isset($procedures[$m[1]]), 'Unknown isolated procedure body.');
                        $sql = $procedures[$m[1]];
                    } elseif (preg_match('/\A(?:CREATE\s+(?:PROCEDURE|FUNCTION)|CALL)\b/i', trim($sql))) {
                        throw new RuntimeException('Unsupported isolated routine shape.');
                    }
                }
                // Migrations may include verification SELECTs or CALL result sets.
                // Drain them before executing the next statement on this connection.
                $statement = $pdo->query($sql);
                $statement->closeCursor();
            }
            $buffer = '';
        }
    }
    cli_require(trim($buffer) === '', 'Unterminated migration SQL.');
}
