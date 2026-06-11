-- Store extracted text, metrics, and reconciliation checks from monthly finance PDFs.

CREATE TABLE IF NOT EXISTS finance_statement_pdf_extracts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    document_id BIGINT UNSIGNED NOT NULL,
    month_year CHAR(7) NOT NULL,
    extract_status ENUM('success','partial','failed') NOT NULL DEFAULT 'partial',
    page_count INT UNSIGNED NOT NULL DEFAULT 0,
    raw_text LONGTEXT NULL,
    metrics_json JSON NULL,
    reconcile_json JSON NULL,
    error_message VARCHAR(1000) NULL,
    extracted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_finance_pdf_extract_document (document_id),
    KEY idx_finance_pdf_extract_month (month_year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
