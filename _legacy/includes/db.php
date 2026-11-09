<?php
/**
 * includes/db.php — MySQL connection and query helpers.
 *
 * Every statement in e-RESERVE goes through here as a prepared statement.
 * No page ever concatenates a value into SQL, so user input cannot alter a
 * query no matter what it contains.
 */

declare(strict_types=1);

/** Shared PDO handle. */
function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        DB_HOST,
        DB_PORT,
        DB_NAME,
        DB_CHARSET
    );

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,   // real prepared statements
            PDO::ATTR_STRINGIFY_FETCHES  => false,
        ]);
    } catch (PDOException $ex) {
        db_fail($ex);
    }

    $pdo->exec("SET time_zone = '+08:00'");

    return $pdo;
}

/** Is the database reachable and installed? */
function db_ready(): bool
{
    static $ready = null;

    if ($ready !== null) {
        return $ready;
    }

    try {
        $dsn = sprintf('mysql:host=%s;port=%d;charset=%s', DB_HOST, DB_PORT, DB_CHARSET);
        $tmp = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

        $found = $tmp->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?');
        $found->execute([DB_NAME, 'documents']);

        $ready = (int) $found->fetchColumn() === 1;
    } catch (PDOException $ex) {
        $ready = false;
    }

    return $ready;
}

/** Run a prepared statement and return the statement. */
function db_run(string $sql, array $params = []): PDOStatement
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

/** All matching rows. */
function db_all(string $sql, array $params = []): array
{
    return db_run($sql, $params)->fetchAll();
}

/** First matching row, or null. */
function db_one(string $sql, array $params = []): ?array
{
    $row = db_run($sql, $params)->fetch();
    return $row === false ? null : $row;
}

/** First column of the first row. */
function db_value(string $sql, array $params = [], $default = null)
{
    $val = db_run($sql, $params)->fetchColumn();
    return $val === false ? $default : $val;
}

/** Insert and return the new id. */
function db_insert(string $sql, array $params = []): int
{
    db_run($sql, $params);
    return (int) db()->lastInsertId();
}

/** Rows affected by an UPDATE / DELETE. */
function db_affected(string $sql, array $params = []): int
{
    return db_run($sql, $params)->rowCount();
}

/** Run a closure inside a transaction, rolling back on any exception. */
function db_transaction(callable $work)
{
    $pdo = db();
    $pdo->beginTransaction();

    try {
        $result = $work($pdo);
        $pdo->commit();
        return $result;
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $ex;
    }
}

/**
 * Next number in a sequence, allocated inside the caller's transaction so two
 * students reserving at the same instant can never receive the same code.
 */
function db_next_sequence(string $key): int
{
    db_run(
        'INSERT INTO sequences (seq_key, current_value) VALUES (?, 1)
         ON DUPLICATE KEY UPDATE current_value = current_value + 1',
        [$key]
    );

    return (int) db_value('SELECT current_value FROM sequences WHERE seq_key = ?', [$key], 1);
}

/** Friendly failure page instead of a stack trace with credentials in it. */
function db_fail(Throwable $ex): void
{
    error_log('[e-RESERVE] database: ' . $ex->getMessage());

    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, 'Database error: ' . $ex->getMessage() . PHP_EOL);
        exit(1);
    }

    http_response_code(503);
    $setup = rtrim(BASE_URL, '/') . '/install.php';
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Database Unavailable · e-RESERVE</title>
        <style>
            body { font-family: 'Segoe UI', system-ui, sans-serif; background: #F6FAF8; color: #10241D;
                   display: grid; place-items: center; min-height: 100vh; margin: 0; padding: 1.5rem; }
            .box { background: #fff; border: 1px solid #E3EDE8; border-radius: 20px; max-width: 560px;
                   padding: 2.5rem; box-shadow: 0 14px 40px rgba(11,59,44,.09); }
            h1 { margin: 0 0 .6rem; font-size: 1.4rem; color: #06251B; }
            p { line-height: 1.7; color: #4A5F58; }
            code { background: #EAF4EF; padding: .15rem .4rem; border-radius: 6px; font-size: .9em; }
            a.btn { display: inline-block; margin-top: 1rem; background: #E9A93A; color: #3B2405;
                    font-weight: 800; text-decoration: none; padding: .7rem 1.4rem; border-radius: 99px; }
        </style>
    </head>
    <body>
        <div class="box">
            <h1>e-RESERVE cannot reach its database</h1>
            <p>Start <strong>MySQL</strong> in the XAMPP Control Panel, then make sure the
               <code><?= htmlspecialchars(DB_NAME, ENT_QUOTES, 'UTF-8') ?></code> database exists.</p>
            <p>If this is a fresh copy of the project, run the installer once — it creates the
               database, every table and the demo records.</p>
            <a class="btn" href="<?= htmlspecialchars($setup, ENT_QUOTES, 'UTF-8') ?>">Run the installer</a>
        </div>
    </body>
    </html>
    <?php
    exit;
}
