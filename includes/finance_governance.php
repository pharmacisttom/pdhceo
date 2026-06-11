<?php
declare(strict_types=1);

function finance_schema_auto_migrate_enabled(): bool
{
    return getenv('PDH_AUTO_MIGRATE') === '1'
        || (defined('PDH_AUTO_MIGRATE') && PDH_AUTO_MIGRATE === true);
}

function finance_assert_tables_exist(PDO $pdo, array $tables): void
{
    $missing = [];
    foreach ($tables as $table) {
        $safeTable = str_replace('`', '``', (string)$table);
        try {
            $pdo->query("SELECT 1 FROM `{$safeTable}` LIMIT 1");
        } catch (PDOException $e) {
            if (($e->errorInfo[1] ?? null) === 1146 || str_contains($e->getMessage(), 'Base table or view not found')) {
                $missing[] = (string)$table;
                continue;
            }
            throw $e;
        }
    }

    if ($missing) {
        throw new RuntimeException(
            'Finance governance tables are not installed: ' . implode(', ', $missing)
            . '. Please run migrations/2026_06_05_finance_governance.sql once with a database user that has CREATE/ALTER privileges.'
        );
    }
}

function finance_ensure_account_mapping_management_columns(PDO $pdo): void
{
    $mappingColumns = [
        'is_ar_sss' => "ALTER TABLE finance_account_mapping ADD COLUMN is_ar_sss TINYINT(1) NOT NULL DEFAULT 0 AFTER is_ar_csmbs",
        'is_ar_other' => "ALTER TABLE finance_account_mapping ADD COLUMN is_ar_other TINYINT(1) NOT NULL DEFAULT 0 AFTER is_ar_sss",
        'is_current_asset' => "ALTER TABLE finance_account_mapping ADD COLUMN is_current_asset TINYINT(1) NOT NULL DEFAULT 0 AFTER is_inventory",
        'is_fixed_asset' => "ALTER TABLE finance_account_mapping ADD COLUMN is_fixed_asset TINYINT(1) NOT NULL DEFAULT 0 AFTER is_current_asset",
        'is_current_liability' => "ALTER TABLE finance_account_mapping ADD COLUMN is_current_liability TINYINT(1) NOT NULL DEFAULT 0 AFTER is_fixed_asset",
        'is_longterm_liability' => "ALTER TABLE finance_account_mapping ADD COLUMN is_longterm_liability TINYINT(1) NOT NULL DEFAULT 0 AFTER is_current_liability",
        'is_equity_fund' => "ALTER TABLE finance_account_mapping ADD COLUMN is_equity_fund TINYINT(1) NOT NULL DEFAULT 0 AFTER is_longterm_liability",
        'is_revenue_operating' => "ALTER TABLE finance_account_mapping ADD COLUMN is_revenue_operating TINYINT(1) NOT NULL DEFAULT 0 AFTER is_revenue",
        'is_revenue_non_operating' => "ALTER TABLE finance_account_mapping ADD COLUMN is_revenue_non_operating TINYINT(1) NOT NULL DEFAULT 0 AFTER is_revenue_operating",
        'is_depreciation' => "ALTER TABLE finance_account_mapping ADD COLUMN is_depreciation TINYINT(1) NOT NULL DEFAULT 0 AFTER is_cc",
        'is_finance_cost' => "ALTER TABLE finance_account_mapping ADD COLUMN is_finance_cost TINYINT(1) NOT NULL DEFAULT 0 AFTER is_depreciation",
        'is_project_grant' => "ALTER TABLE finance_account_mapping ADD COLUMN is_project_grant TINYINT(1) NOT NULL DEFAULT 0 AFTER is_finance_cost",
    ];

    foreach ($mappingColumns as $column => $sql) {
        try {
            $pdo->query("SELECT {$column} FROM finance_account_mapping LIMIT 1");
        } catch (PDOException $e) {
            if (($e->errorInfo[1] ?? null) !== 1054 && !str_contains($e->getMessage(), 'Unknown column')) {
                throw $e;
            }
            try {
                $pdo->exec($sql);
            } catch (PDOException $alterError) {
                if (($alterError->errorInfo[1] ?? null) === 1142 || str_contains($alterError->getMessage(), 'ALTER command denied')) {
                    throw new RuntimeException(
                        'บัญชีฐานข้อมูลของเว็บไม่มีสิทธิ์ ALTER TABLE สำหรับเพิ่มหัวข้อ Mapping ใหม่ '
                        . 'กรุณารัน migrations/2026_06_08_finance_mapping_management_columns.sql ด้วย user ฐานข้อมูลที่มีสิทธิ์แก้โครงสร้างตารางก่อนใช้งานหน้านี้'
                    );
                }
                throw $alterError;
            }
        }
    }
}

function finance_assert_account_mapping_management_columns(PDO $pdo): void
{
    $columns = [
        'is_ar_sss',
        'is_ar_other',
        'is_current_asset',
        'is_fixed_asset',
        'is_current_liability',
        'is_longterm_liability',
        'is_equity_fund',
        'is_revenue_operating',
        'is_revenue_non_operating',
        'is_depreciation',
        'is_finance_cost',
        'is_project_grant',
    ];

    $missing = [];
    foreach ($columns as $column) {
        try {
            $pdo->query("SELECT {$column} FROM finance_account_mapping LIMIT 1");
        } catch (PDOException $e) {
            if (($e->errorInfo[1] ?? null) === 1054 || str_contains($e->getMessage(), 'Unknown column')) {
                $missing[] = $column;
                continue;
            }
            throw $e;
        }
    }

    if ($missing) {
        throw new RuntimeException(
            'ตาราง finance_account_mapping ยังขาดคอลัมน์ Mapping ใหม่: ' . implode(', ', $missing)
            . '. กรุณารัน migrations/2026_06_08_finance_mapping_management_columns.sql ด้วย user ฐานข้อมูลที่มีสิทธิ์ ALTER TABLE'
        );
    }
}

function finance_governance_schema(PDO $pdo): void
{
    $requiredTables = [
        'finance_account_mapping',
        'finance_monthly_auto',
        'finance_planfin',
        'finance_aging',
        'finance_claim_quality',
        'finance_cost_center',
        'finance_asset_register',
        'finance_inventory_usage',
        'finance_statement_documents',
    ];

    if (!finance_schema_auto_migrate_enabled()) {
        finance_assert_tables_exist($pdo, $requiredTables);
        finance_assert_account_mapping_management_columns($pdo);
        return;
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS finance_account_mapping (
            account_code VARCHAR(50) NOT NULL,
            account_name VARCHAR(500) NOT NULL,
            is_cash TINYINT(1) NOT NULL DEFAULT 0,
            is_ar_uc TINYINT(1) NOT NULL DEFAULT 0,
            is_ar_csmbs TINYINT(1) NOT NULL DEFAULT 0,
            is_ar_sss TINYINT(1) NOT NULL DEFAULT 0,
            is_ar_other TINYINT(1) NOT NULL DEFAULT 0,
            is_ap TINYINT(1) NOT NULL DEFAULT 0,
            is_inventory TINYINT(1) NOT NULL DEFAULT 0,
            is_current_asset TINYINT(1) NOT NULL DEFAULT 0,
            is_fixed_asset TINYINT(1) NOT NULL DEFAULT 0,
            is_current_liability TINYINT(1) NOT NULL DEFAULT 0,
            is_longterm_liability TINYINT(1) NOT NULL DEFAULT 0,
            is_equity_fund TINYINT(1) NOT NULL DEFAULT 0,
            is_revenue TINYINT(1) NOT NULL DEFAULT 0,
            is_revenue_operating TINYINT(1) NOT NULL DEFAULT 0,
            is_revenue_non_operating TINYINT(1) NOT NULL DEFAULT 0,
            is_lc TINYINT(1) NOT NULL DEFAULT 0,
            is_mc TINYINT(1) NOT NULL DEFAULT 0,
            is_cc TINYINT(1) NOT NULL DEFAULT 0,
            is_depreciation TINYINT(1) NOT NULL DEFAULT 0,
            is_finance_cost TINYINT(1) NOT NULL DEFAULT 0,
            is_project_grant TINYINT(1) NOT NULL DEFAULT 0,
            is_op TINYINT(1) NOT NULL DEFAULT 0,
            is_ip TINYINT(1) NOT NULL DEFAULT 0,
            auto_field VARCHAR(50) NULL,
            value_basis VARCHAR(20) NOT NULL DEFAULT 'month_debit',
            is_reviewed TINYINT(1) NOT NULL DEFAULT 0,
            note VARCHAR(500) NULL,
            updated_by VARCHAR(100) NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (account_code),
            KEY idx_finance_mapping_reviewed (is_reviewed),
            KEY idx_finance_mapping_auto_field (auto_field)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    finance_ensure_account_mapping_management_columns($pdo);

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS finance_monthly_auto (
            month_year CHAR(7) NOT NULL,
            treatment_income DECIMAL(18,2) NOT NULL DEFAULT 0,
            drug_income DECIMAL(18,2) NOT NULL DEFAULT 0,
            lab_income DECIMAL(18,2) NOT NULL DEFAULT 0,
            water_bill DECIMAL(18,2) NOT NULL DEFAULT 0,
            electric_bill DECIMAL(18,2) NOT NULL DEFAULT 0,
            compensation DECIMAL(18,2) NOT NULL DEFAULT 0,
            maintenance_fund DECIMAL(18,2) NOT NULL DEFAULT 0,
            inv_drug_value DECIMAL(18,2) NOT NULL DEFAULT 0,
            inv_medical_supply DECIMAL(18,2) NOT NULL DEFAULT 0,
            inv_science_material DECIMAL(18,2) NOT NULL DEFAULT 0,
            total_revenue DECIMAL(18,2) NOT NULL DEFAULT 0,
            total_expense DECIMAL(18,2) NOT NULL DEFAULT 0,
            cash_balance DECIMAL(18,2) NOT NULL DEFAULT 0,
            inventory_balance DECIMAL(18,2) NOT NULL DEFAULT 0,
            mapped_accounts INT UNSIGNED NOT NULL DEFAULT 0,
            total_accounts INT UNSIGNED NOT NULL DEFAULT 0,
            readiness_percent DECIMAL(5,2) NOT NULL DEFAULT 0,
            calculated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (month_year)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS finance_planfin (
            month_year CHAR(7) NOT NULL,
            revenue_target DECIMAL(18,2) NOT NULL DEFAULT 0,
            expense_budget DECIMAL(18,2) NOT NULL DEFAULT 0,
            investment_budget DECIMAL(18,2) NOT NULL DEFAULT 0,
            note VARCHAR(500) NULL,
            updated_by VARCHAR(100) NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (month_year)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS finance_aging (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            month_year CHAR(7) NOT NULL,
            aging_type ENUM('AR_UC','AR_CSMBS','AR_OTHER','AP') NOT NULL,
            bucket ENUM('0-30','31-60','61-90','OVER_90') NOT NULL,
            amount DECIMAL(18,2) NOT NULL DEFAULT 0,
            updated_by VARCHAR(100) NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_finance_aging (month_year, aging_type, bucket)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS finance_claim_quality (
            month_year CHAR(7) NOT NULL,
            claim_count INT UNSIGNED NOT NULL DEFAULT 0,
            claim_lag_days DECIMAL(10,2) NOT NULL DEFAULT 0,
            denial_count INT UNSIGNED NOT NULL DEFAULT 0,
            denial_rate DECIMAL(7,2) NOT NULL DEFAULT 0,
            updated_by VARCHAR(100) NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (month_year)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS finance_cost_center (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            month_year CHAR(7) NOT NULL,
            cost_center_code VARCHAR(50) NOT NULL,
            cost_center_name VARCHAR(255) NOT NULL,
            service_type ENUM('OP','IP','SHARED') NOT NULL DEFAULT 'SHARED',
            lc_cost DECIMAL(18,2) NOT NULL DEFAULT 0,
            mc_cost DECIMAL(18,2) NOT NULL DEFAULT 0,
            cc_cost DECIMAL(18,2) NOT NULL DEFAULT 0,
            updated_by VARCHAR(100) NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_finance_cost_center (month_year, cost_center_code)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS finance_asset_register (
            asset_code VARCHAR(100) NOT NULL,
            asset_name VARCHAR(255) NOT NULL,
            asset_group VARCHAR(100) NULL,
            cost_center_code VARCHAR(50) NULL,
            acquisition_date DATE NULL,
            acquisition_cost DECIMAL(18,2) NOT NULL DEFAULT 0,
            accumulated_depreciation DECIMAL(18,2) NOT NULL DEFAULT 0,
            monthly_depreciation DECIMAL(18,2) NOT NULL DEFAULT 0,
            status VARCHAR(30) NOT NULL DEFAULT 'active',
            updated_by VARCHAR(100) NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (asset_code)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS finance_inventory_usage (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            month_year CHAR(7) NOT NULL,
            inventory_type ENUM('DRUG','MEDICAL','SCIENCE','GENERAL') NOT NULL,
            beginning_balance DECIMAL(18,2) NOT NULL DEFAULT 0,
            purchases DECIMAL(18,2) NOT NULL DEFAULT 0,
            actual_issues DECIMAL(18,2) NOT NULL DEFAULT 0,
            ending_balance DECIMAL(18,2) NOT NULL DEFAULT 0,
            updated_by VARCHAR(100) NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_finance_inventory_usage (month_year, inventory_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS finance_statement_documents (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            month_year CHAR(7) NOT NULL,
            document_type VARCHAR(50) NOT NULL DEFAULT 'monthly_statement',
            original_filename VARCHAR(255) NOT NULL,
            stored_filename VARCHAR(255) NOT NULL,
            relative_path VARCHAR(500) NOT NULL,
            file_size BIGINT UNSIGNED NOT NULL DEFAULT 0,
            mime_type VARCHAR(120) NOT NULL DEFAULT 'application/pdf',
            note VARCHAR(500) NULL,
            uploaded_by VARCHAR(100) NOT NULL,
            uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_finance_statement_month_type (month_year, document_type),
            KEY idx_finance_statement_month (month_year)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

function finance_mapping_guess(string $code, string $name): array
{
    $nameLower = mb_strtolower($name);
    $contains = static fn(array $words): bool => array_reduce(
        $words,
        static fn(bool $found, string $word): bool => $found || mb_strpos($nameLower, mb_strtolower($word)) !== false,
        false
    );
    $flags = [
        'is_cash' => $contains(['เงินสด', 'เงินฝากธนาคาร', 'เงินฝากคลัง']) ? 1 : 0,
        'is_ar_uc' => $contains(['ลูกหนี้ค่ารักษา uc', 'ลูกหนี้ uc']) ? 1 : 0,
        'is_ar_csmbs' => $contains(['กรมบัญชีกลาง', 'ลูกหนี้ข้าราชการ']) ? 1 : 0,
        'is_ar_sss' => $contains(['ประกันสังคม', 'ลูกหนี้ sss']) ? 1 : 0,
        'is_ar_other' => $contains(['ลูกหนี้']) && !$contains(['uc', 'กรมบัญชีกลาง', 'ข้าราชการ', 'ประกันสังคม']) ? 1 : 0,
        'is_ap' => $contains(['เจ้าหนี้']) ? 1 : 0,
        'is_inventory' => str_starts_with($code, '1105') || $contains(['วัสดุคงคลัง', 'สินค้าคงเหลือ']) ? 1 : 0,
        'is_current_asset' => str_starts_with($code, '11') ? 1 : 0,
        'is_fixed_asset' => str_starts_with($code, '12') || $contains(['ครุภัณฑ์', 'อาคาร', 'สิ่งปลูกสร้าง', 'ที่ดิน']) ? 1 : 0,
        'is_current_liability' => str_starts_with($code, '21') || $contains(['เจ้าหนี้', 'เงินรับฝาก', 'ค้างจ่าย']) ? 1 : 0,
        'is_longterm_liability' => str_starts_with($code, '22') || $contains(['ระยะยาว', 'เงินกู้']) ? 1 : 0,
        'is_equity_fund' => str_starts_with($code, '3') || $contains(['ทุน', 'กองทุน', 'สะสม']) ? 1 : 0,
        'is_revenue' => str_starts_with($code, '4') ? 1 : 0,
        'is_revenue_operating' => str_starts_with($code, '4') && !$contains(['ดอกเบี้ย', 'บริจาค', 'เงินช่วยเหลือ', 'อุดหนุน']) ? 1 : 0,
        'is_revenue_non_operating' => str_starts_with($code, '4') && $contains(['ดอกเบี้ย', 'บริจาค', 'เงินช่วยเหลือ', 'อุดหนุน']) ? 1 : 0,
        'is_lc' => $contains(['เงินเดือน', 'ค่าตอบแทน', 'ค่าจ้าง']) ? 1 : 0,
        'is_mc' => $contains(['ยา', 'เวชภัณฑ์', 'วัสดุ']) ? 1 : 0,
        'is_cc' => str_starts_with($code, '5') && !$contains(['เงินเดือน', 'ค่าตอบแทน', 'ค่าจ้าง', 'ยา', 'เวชภัณฑ์', 'วัสดุ']) ? 1 : 0,
        'is_depreciation' => $contains(['ค่าเสื่อมราคา']) && !$contains(['สะสม']) ? 1 : 0,
        'is_finance_cost' => $contains(['ดอกเบี้ยจ่าย', 'ค่าธรรมเนียมธนาคาร', 'ค่าบริการธนาคาร']) ? 1 : 0,
        'is_project_grant' => $contains(['โครงการ', 'เงินอุดหนุน', 'เงินบริจาค', 'restricted', 'earmarked']) ? 1 : 0,
        'is_op' => $contains(['ผู้ป่วยนอก', 'opd', ' op', '-op']) ? 1 : 0,
        'is_ip' => $contains(['ผู้ป่วยใน', 'ipd', ' ip', '-ip']) ? 1 : 0,
    ];
    $autoField = null;
    $basis = 'month_debit';
    if ($flags['is_cash']) {
        $autoField = 'maintenance_fund';
        $basis = 'net_debit';
    } elseif ($flags['is_ar_uc'] || $flags['is_ar_csmbs'] || $flags['is_ar_sss'] || $flags['is_ar_other'] || $flags['is_current_asset'] || $flags['is_fixed_asset']) {
        $basis = 'net_debit';
    } elseif ($flags['is_ap'] || $flags['is_current_liability'] || $flags['is_longterm_liability'] || $flags['is_equity_fund']) {
        $basis = 'net_credit';
    } elseif ($flags['is_revenue']) {
        $autoField = $contains(['ยา', 'เวชภัณฑ์']) ? 'drug_income' : ($contains(['lab', 'x-ray', 'รังสี', 'ชันสูตร']) ? 'lab_income' : 'treatment_income');
        $basis = 'month_credit';
    } elseif ($contains(['ค่าไฟฟ้า'])) {
        $autoField = 'electric_bill';
    } elseif ($contains(['ค่าน้ำประปา'])) {
        $autoField = 'water_bill';
    } elseif ($flags['is_lc']) {
        $autoField = 'compensation';
    } elseif ($flags['is_inventory']) {
        $autoField = $contains(['ยา']) ? 'inv_drug_value' : ($contains(['วิทยาศาสตร์', 'ชันสูตร', 'lab']) ? 'inv_science_material' : 'inv_medical_supply');
        $basis = 'net_debit';
    }
    return $flags + ['auto_field' => $autoField, 'value_basis' => $basis];
}

function finance_sync_account_mappings(PDO $pdo): int
{
    finance_governance_schema($pdo);
    $rows = $pdo->query("
        SELECT r.account_code, MAX(r.account_name) AS account_name
        FROM finance_trial_balance_rows r
        GROUP BY r.account_code
    ")->fetchAll(PDO::FETCH_ASSOC);
    $stmt = $pdo->prepare("
        INSERT IGNORE INTO finance_account_mapping
            (account_code, account_name, is_cash, is_ar_uc, is_ar_csmbs, is_ar_sss, is_ar_other, is_ap, is_inventory,
             is_current_asset, is_fixed_asset, is_current_liability, is_longterm_liability, is_equity_fund,
             is_revenue, is_revenue_operating, is_revenue_non_operating, is_lc, is_mc, is_cc,
             is_depreciation, is_finance_cost, is_project_grant, is_op, is_ip, auto_field, value_basis)
        VALUES
            (:account_code, :account_name, :is_cash, :is_ar_uc, :is_ar_csmbs, :is_ar_sss, :is_ar_other, :is_ap, :is_inventory,
             :is_current_asset, :is_fixed_asset, :is_current_liability, :is_longterm_liability, :is_equity_fund,
             :is_revenue, :is_revenue_operating, :is_revenue_non_operating, :is_lc, :is_mc, :is_cc,
             :is_depreciation, :is_finance_cost, :is_project_grant, :is_op, :is_ip, :auto_field, :value_basis)
    ");
    $updateUnreviewed = $pdo->prepare("
        UPDATE finance_account_mapping SET
            account_name=:account_name,
            is_cash=:is_cash,
            is_ar_uc=:is_ar_uc,
            is_ar_csmbs=:is_ar_csmbs,
            is_ar_sss=:is_ar_sss,
            is_ar_other=:is_ar_other,
            is_ap=:is_ap,
            is_inventory=:is_inventory,
            is_current_asset=:is_current_asset,
            is_fixed_asset=:is_fixed_asset,
            is_current_liability=:is_current_liability,
            is_longterm_liability=:is_longterm_liability,
            is_equity_fund=:is_equity_fund,
            is_revenue=:is_revenue,
            is_revenue_operating=:is_revenue_operating,
            is_revenue_non_operating=:is_revenue_non_operating,
            is_lc=:is_lc,
            is_mc=:is_mc,
            is_cc=:is_cc,
            is_depreciation=:is_depreciation,
            is_finance_cost=:is_finance_cost,
            is_project_grant=:is_project_grant,
            is_op=:is_op,
            is_ip=:is_ip,
            auto_field=:auto_field,
            value_basis=:value_basis
        WHERE account_code=:account_code
          AND is_reviewed = 0
    ");
    $inserted = 0;
    foreach ($rows as $row) {
        $guess = finance_mapping_guess((string)$row['account_code'], (string)$row['account_name']);
        $params = [
            ':account_code' => $row['account_code'],
            ':account_name' => $row['account_name'],
        ] + array_combine(
            array_map(static fn(string $key): string => ':' . $key, array_keys($guess)),
            array_values($guess)
        );
        $stmt->execute($params);
        $inserted += $stmt->rowCount();
        $updateUnreviewed->execute($params);
    }
    $pdo->exec("
        UPDATE finance_account_mapping
        SET is_op = 1
        WHERE is_reviewed = 0 AND is_op = 0
          AND (LOWER(account_name) LIKE '% opd%' OR LOWER(account_name) LIKE '% op%' OR LOWER(account_name) LIKE '%-op%')
    ");
    $pdo->exec("
        UPDATE finance_account_mapping
        SET is_ip = 1
        WHERE is_reviewed = 0 AND is_ip = 0
          AND (LOWER(account_name) LIKE '% ipd%' OR LOWER(account_name) LIKE '% ip%' OR LOWER(account_name) LIKE '%-ip%')
    ");
    return $inserted;
}

function finance_mapping_audit(PDO $pdo, string $monthYear): array
{
    finance_governance_schema($pdo);
    $stmt = $pdo->prepare('SELECT id FROM finance_trial_balance_imports WHERE month_year = :month');
    $stmt->execute([':month' => $monthYear]);
    $importId = (int)$stmt->fetchColumn();
    if (!$importId) {
        return ['month' => $monthYear, 'total_accounts' => 0, 'mapped_accounts' => 0, 'reviewed_accounts' => 0, 'readiness_percent' => 0, 'new_codes' => [], 'missing_codes' => [], 'unmapped_codes' => []];
    }
    $previous = $pdo->prepare('SELECT id FROM finance_trial_balance_imports WHERE month_year < :month ORDER BY month_year DESC LIMIT 1');
    $previous->execute([':month' => $monthYear]);
    $previousId = (int)$previous->fetchColumn();
    $codes = static function (PDO $pdo, int $id): array {
        if (!$id) return [];
        $stmt = $pdo->prepare('SELECT account_code, account_name FROM finance_trial_balance_rows WHERE import_id = :id');
        $stmt->execute([':id' => $id]);
        $result = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) $result[(string)$row['account_code']] = (string)$row['account_name'];
        return $result;
    };
    $currentCodes = $codes($pdo, $importId);
    $previousCodes = $codes($pdo, $previousId);
    $mapStmt = $pdo->prepare("
        SELECT m.account_code, m.is_reviewed,
               (m.is_cash+m.is_ar_uc+m.is_ar_csmbs+m.is_ar_sss+m.is_ar_other+m.is_ap+m.is_inventory+
                m.is_current_asset+m.is_fixed_asset+m.is_current_liability+m.is_longterm_liability+m.is_equity_fund+
                m.is_revenue+m.is_revenue_operating+m.is_revenue_non_operating+m.is_lc+m.is_mc+m.is_cc+
                m.is_depreciation+m.is_finance_cost+m.is_project_grant+m.is_op+m.is_ip) AS tag_count
        FROM finance_account_mapping m
        WHERE m.account_code IN (SELECT account_code FROM finance_trial_balance_rows WHERE import_id = :id)
    ");
    $mapStmt->execute([':id' => $importId]);
    $mapped = [];
    $reviewed = 0;
    foreach ($mapStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if ((int)$row['tag_count'] > 0) $mapped[(string)$row['account_code']] = true;
        if ((int)$row['is_reviewed'] === 1) $reviewed++;
    }
    $unmapped = [];
    foreach ($currentCodes as $code => $name) if (!isset($mapped[$code])) $unmapped[] = ['account_code' => $code, 'account_name' => $name];
    $total = count($currentCodes);
    return [
        'month' => $monthYear,
        'total_accounts' => $total,
        'mapped_accounts' => count($mapped),
        'reviewed_accounts' => $reviewed,
        'readiness_percent' => $total > 0 ? round((count($mapped) / $total) * 100, 2) : 0,
        'new_codes' => array_map(static fn($code) => ['account_code' => $code, 'account_name' => $currentCodes[$code]], array_values(array_diff(array_keys($currentCodes), array_keys($previousCodes)))),
        'missing_codes' => array_map(static fn($code) => ['account_code' => $code, 'account_name' => $previousCodes[$code]], array_values(array_diff(array_keys($previousCodes), array_keys($currentCodes)))),
        'unmapped_codes' => $unmapped,
    ];
}

function finance_recalculate_month(PDO $pdo, string $monthYear): array
{
    finance_governance_schema($pdo);
    $allowedFields = ['treatment_income','drug_income','lab_income','water_bill','electric_bill','compensation','maintenance_fund','inv_drug_value','inv_medical_supply','inv_science_material'];
    $values = array_fill_keys($allowedFields, 0.0);
    $stmt = $pdo->prepare("
        SELECT r.*, m.auto_field, m.value_basis,
               m.is_cash, m.is_inventory, m.is_revenue
        FROM finance_trial_balance_rows r
        INNER JOIN finance_trial_balance_imports i ON i.id = r.import_id
        LEFT JOIN finance_account_mapping m ON m.account_code = r.account_code
        WHERE i.month_year = :month
    ");
    $stmt->execute([':month' => $monthYear]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $cash = $inventory = $revenue = $expense = 0.0;
    foreach ($rows as $row) {
        $basis = (string)($row['value_basis'] ?? 'month_debit');
        $amount = match ($basis) {
            'month_credit' => max((float)$row['month_credit'] - (float)$row['month_debit'], 0),
            'net_debit' => max((float)$row['net_debit'] - (float)$row['net_credit'], 0),
            'net_credit' => max((float)$row['net_credit'] - (float)$row['net_debit'], 0),
            default => max((float)$row['month_debit'] - (float)$row['month_credit'], 0),
        };
        $field = (string)($row['auto_field'] ?? '');
        if (in_array($field, $allowedFields, true)) $values[$field] += $amount;
        if ((int)($row['is_cash'] ?? 0)) $cash += max((float)$row['net_debit'] - (float)$row['net_credit'], 0);
        if ((int)($row['is_inventory'] ?? 0)) $inventory += max((float)$row['net_debit'] - (float)$row['net_credit'], 0);
        if ((int)($row['is_revenue'] ?? 0)) $revenue += max((float)$row['month_credit'] - (float)$row['month_debit'], 0);
        if (str_starts_with((string)$row['account_code'], '5')) $expense += max((float)$row['month_debit'] - (float)$row['month_credit'], 0);
    }
    $audit = finance_mapping_audit($pdo, $monthYear);
    $columns = array_merge($allowedFields, ['total_revenue','total_expense','cash_balance','inventory_balance','mapped_accounts','total_accounts','readiness_percent']);
    $payload = $values + [
        'total_revenue' => $revenue, 'total_expense' => $expense, 'cash_balance' => $cash, 'inventory_balance' => $inventory,
        'mapped_accounts' => $audit['mapped_accounts'], 'total_accounts' => $audit['total_accounts'], 'readiness_percent' => $audit['readiness_percent'],
    ];
    $updates = implode(', ', array_map(static fn(string $c): string => "{$c}=VALUES({$c})", $columns));
    $sql = 'INSERT INTO finance_monthly_auto (month_year,' . implode(',', $columns) . ') VALUES (:month_year,:' . implode(',:', $columns) . ") ON DUPLICATE KEY UPDATE {$updates}, calculated_at=NOW()";
    $pdo->prepare($sql)->execute([':month_year' => $monthYear] + array_combine(array_map(static fn($c) => ':' . $c, $columns), array_values($payload)));

    $pdo->prepare("INSERT IGNORE INTO finance_monthly_data (month_year, updated_by) VALUES (:month, 'Auto Mapping')")->execute([':month' => $monthYear]);
    foreach ($allowedFields as $field) {
        if ($values[$field] <= 0) continue;
        $pdo->prepare("UPDATE finance_monthly_data SET {$field} = IF(COALESCE({$field},0)=0,:value,{$field}) WHERE month_year=:month")
            ->execute([':value' => round($values[$field], 2), ':month' => $monthYear]);
    }
    return ['values' => $payload, 'audit' => $audit];
}
