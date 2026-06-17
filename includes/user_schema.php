<?php
declare(strict_types=1);

function user_schema_auto_migrate_enabled(): bool
{
    return getenv('PDH_AUTO_MIGRATE') === '1'
        || (defined('PDH_AUTO_MIGRATE') && PDH_AUTO_MIGRATE === true);
}

function ensure_user_approval_schema(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $dbName = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT COLUMN_TYPE
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = :db
        AND TABLE_NAME = 'users'
        AND COLUMN_NAME = 'role'
        LIMIT 1
    ");
    $stmt->execute([':db' => $dbName]);
    $roleType = (string)($stmt->fetchColumn() ?: '');

    $columns = [];
    $stmt = $pdo->prepare("
        SELECT COLUMN_NAME
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = :db
        AND TABLE_NAME = 'users'
    ");
    $stmt->execute([':db' => $dbName]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $columns[$row['COLUMN_NAME']] = true;
    }

    if (!user_schema_auto_migrate_enabled()) {
        $requiredColumns = ['approval_status', 'approved_by', 'approved_at', 'rejected_reason', 'updated_at'];
        $missingColumns = array_values(array_filter(
            $requiredColumns,
            static fn(string $column): bool => !isset($columns[$column])
        ));
        if ($roleType !== '' && strpos($roleType, 'finance') === false) {
            $missingColumns[] = 'role enum finance';
        }
        if ($missingColumns) {
            throw new RuntimeException(
                'User approval schema is not installed: ' . implode(', ', $missingColumns)
                . '. Please run the user/access log migration with a database user that has ALTER privileges.'
            );
        }
        return;
    }

    if ($roleType !== '' && strpos($roleType, 'finance') === false) {
        $pdo->exec("
            ALTER TABLE users
            MODIFY role ENUM('admin','ceo','manager','executive','finance','inventory','staff')
            CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'staff'
        ");
    }

    if (!isset($columns['approval_status'])) {
        $pdo->exec("
            ALTER TABLE users
            ADD approval_status ENUM('pending','approved','rejected')
            CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending'
            AFTER is_active
        ");
    }

    if (!isset($columns['approved_by'])) {
        $pdo->exec('ALTER TABLE users ADD approved_by INT NULL DEFAULT NULL AFTER approval_status');
    }

    if (!isset($columns['approved_at'])) {
        $pdo->exec('ALTER TABLE users ADD approved_at DATETIME NULL DEFAULT NULL AFTER approved_by');
    }

    if (!isset($columns['rejected_reason'])) {
        $pdo->exec("
            ALTER TABLE users
            ADD rejected_reason VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL
            AFTER approved_at
        ");
    }

    if (!isset($columns['updated_at'])) {
        $pdo->exec('ALTER TABLE users ADD updated_at DATETIME NULL DEFAULT NULL AFTER created_at');
    }

    $pdo->exec("
        UPDATE users
        SET approval_status = 'approved',
            is_active = 1,
            approved_at = COALESCE(approved_at, NOW())
        WHERE role = 'admin'
    ");

    $pdo->exec("
        UPDATE users
        SET approval_status = 'approved',
            approved_at = COALESCE(approved_at, created_at)
        WHERE approval_status = 'pending'
        AND is_active = 1
        AND last_login IS NOT NULL
    ");
}
