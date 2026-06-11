-- PDH CEO user/login/access-log migration
-- Run once with a database user that has CREATE/ALTER privileges.

CREATE TABLE IF NOT EXISTS access_logs (
    id BIGINT NOT NULL AUTO_INCREMENT,
    user_id INT NULL,
    username VARCHAR(50) NULL,
    action VARCHAR(100) NOT NULL,
    detail TEXT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP PROCEDURE IF EXISTS pdh_add_column_if_missing;
DROP PROCEDURE IF EXISTS pdh_add_index_if_missing;

DELIMITER $$

CREATE PROCEDURE pdh_add_column_if_missing(
    IN p_table_name VARCHAR(64),
    IN p_column_name VARCHAR(64),
    IN p_column_sql TEXT
)
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = p_table_name
          AND COLUMN_NAME = p_column_name
    ) THEN
        SET @ddl = CONCAT('ALTER TABLE `', p_table_name, '` ADD ', p_column_sql);
        PREPARE stmt FROM @ddl;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END$$

CREATE PROCEDURE pdh_add_index_if_missing(
    IN p_table_name VARCHAR(64),
    IN p_index_name VARCHAR(64),
    IN p_index_sql TEXT
)
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM INFORMATION_SCHEMA.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = p_table_name
          AND INDEX_NAME = p_index_name
    ) THEN
        SET @ddl = CONCAT('ALTER TABLE `', p_table_name, '` ADD ', p_index_sql);
        PREPARE stmt FROM @ddl;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END$$

DELIMITER ;

CALL pdh_add_column_if_missing('users', 'approval_status', "approval_status ENUM('pending','approved','rejected') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending' AFTER is_active");
CALL pdh_add_column_if_missing('users', 'approved_by', 'approved_by INT NULL DEFAULT NULL AFTER approval_status');
CALL pdh_add_column_if_missing('users', 'approved_at', 'approved_at DATETIME NULL DEFAULT NULL AFTER approved_by');
CALL pdh_add_column_if_missing('users', 'rejected_reason', 'rejected_reason VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL AFTER approved_at');
CALL pdh_add_column_if_missing('users', 'updated_at', 'updated_at DATETIME NULL DEFAULT NULL AFTER created_at');

ALTER TABLE users
    MODIFY role ENUM('admin','ceo','manager','executive','finance','inventory','staff')
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'staff';

UPDATE users
SET approval_status = 'approved',
    is_active = 1,
    approved_at = COALESCE(approved_at, NOW())
WHERE role = 'admin';

UPDATE users
SET approval_status = 'approved',
    approved_at = COALESCE(approved_at, created_at)
WHERE approval_status = 'pending'
  AND is_active = 1
  AND last_login IS NOT NULL;

CALL pdh_add_index_if_missing('users', 'idx_users_login_status', 'INDEX idx_users_login_status (username, is_active, approval_status)');
CALL pdh_add_index_if_missing('access_logs', 'idx_access_logs_created_at', 'INDEX idx_access_logs_created_at (created_at)');
CALL pdh_add_index_if_missing('access_logs', 'idx_access_logs_user_created', 'INDEX idx_access_logs_user_created (user_id, created_at)');
CALL pdh_add_index_if_missing('access_logs', 'idx_access_logs_action_created', 'INDEX idx_access_logs_action_created (action, created_at)');

DROP PROCEDURE IF EXISTS pdh_add_column_if_missing;
DROP PROCEDURE IF EXISTS pdh_add_index_if_missing;
