-- Add management accounting dimensions for hospital finance governance mapping.
-- Safe for MariaDB/MySQL versions that support ADD COLUMN IF NOT EXISTS.

ALTER TABLE finance_account_mapping
    ADD COLUMN IF NOT EXISTS is_ar_sss TINYINT(1) NOT NULL DEFAULT 0 AFTER is_ar_csmbs,
    ADD COLUMN IF NOT EXISTS is_ar_other TINYINT(1) NOT NULL DEFAULT 0 AFTER is_ar_sss,
    ADD COLUMN IF NOT EXISTS is_current_asset TINYINT(1) NOT NULL DEFAULT 0 AFTER is_inventory,
    ADD COLUMN IF NOT EXISTS is_fixed_asset TINYINT(1) NOT NULL DEFAULT 0 AFTER is_current_asset,
    ADD COLUMN IF NOT EXISTS is_current_liability TINYINT(1) NOT NULL DEFAULT 0 AFTER is_fixed_asset,
    ADD COLUMN IF NOT EXISTS is_longterm_liability TINYINT(1) NOT NULL DEFAULT 0 AFTER is_current_liability,
    ADD COLUMN IF NOT EXISTS is_equity_fund TINYINT(1) NOT NULL DEFAULT 0 AFTER is_longterm_liability,
    ADD COLUMN IF NOT EXISTS is_revenue_operating TINYINT(1) NOT NULL DEFAULT 0 AFTER is_revenue,
    ADD COLUMN IF NOT EXISTS is_revenue_non_operating TINYINT(1) NOT NULL DEFAULT 0 AFTER is_revenue_operating,
    ADD COLUMN IF NOT EXISTS is_depreciation TINYINT(1) NOT NULL DEFAULT 0 AFTER is_cc,
    ADD COLUMN IF NOT EXISTS is_finance_cost TINYINT(1) NOT NULL DEFAULT 0 AFTER is_depreciation,
    ADD COLUMN IF NOT EXISTS is_project_grant TINYINT(1) NOT NULL DEFAULT 0 AFTER is_finance_cost;
