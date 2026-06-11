-- PDH CEO finance governance migration
-- Run once with a database user that has CREATE/ALTER privileges.
-- The web runtime user (for example webtomdb@localhost) only needs SELECT/INSERT/UPDATE/DELETE after this migration.

CREATE TABLE IF NOT EXISTS finance_trial_balance_imports (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    month_year CHAR(7) NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    row_count INT UNSIGNED NOT NULL DEFAULT 0,
    total_month_debit DECIMAL(18,2) NOT NULL DEFAULT 0,
    total_month_credit DECIMAL(18,2) NOT NULL DEFAULT 0,
    total_net_debit DECIMAL(18,2) NOT NULL DEFAULT 0,
    total_net_credit DECIMAL(18,2) NOT NULL DEFAULT 0,
    imported_by VARCHAR(100) NOT NULL,
    imported_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_finance_trial_balance_month (month_year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS finance_trial_balance_rows (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    import_id BIGINT UNSIGNED NOT NULL,
    account_code VARCHAR(50) NOT NULL,
    account_name VARCHAR(500) NOT NULL,
    month_debit DECIMAL(18,2) NOT NULL DEFAULT 0,
    month_credit DECIMAL(18,2) NOT NULL DEFAULT 0,
    net_debit DECIMAL(18,2) NOT NULL DEFAULT 0,
    net_credit DECIMAL(18,2) NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    KEY idx_finance_trial_balance_import (import_id),
    KEY idx_finance_trial_balance_account (account_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS finance_planfin (
    month_year CHAR(7) NOT NULL,
    revenue_target DECIMAL(18,2) NOT NULL DEFAULT 0,
    expense_budget DECIMAL(18,2) NOT NULL DEFAULT 0,
    investment_budget DECIMAL(18,2) NOT NULL DEFAULT 0,
    note VARCHAR(500) NULL,
    updated_by VARCHAR(100) NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (month_year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS finance_claim_quality (
    month_year CHAR(7) NOT NULL,
    claim_count INT UNSIGNED NOT NULL DEFAULT 0,
    claim_lag_days DECIMAL(10,2) NOT NULL DEFAULT 0,
    denial_count INT UNSIGNED NOT NULL DEFAULT 0,
    denial_rate DECIMAL(7,2) NOT NULL DEFAULT 0,
    updated_by VARCHAR(100) NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (month_year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO finance_monthly_data (month_year, updated_by)
SELECT month_year, imported_by
FROM finance_trial_balance_imports;
