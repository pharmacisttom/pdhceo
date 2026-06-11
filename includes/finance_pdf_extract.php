<?php
declare(strict_types=1);

function finance_pdf_schema(PDO $pdo): void
{
    if (function_exists('finance_schema_auto_migrate_enabled') && !finance_schema_auto_migrate_enabled()) {
        try {
            $pdo->query('SELECT 1 FROM finance_statement_pdf_extracts LIMIT 1');
        } catch (PDOException $e) {
            if (($e->errorInfo[1] ?? null) === 1146 || str_contains($e->getMessage(), 'Base table or view not found')) {
                throw new RuntimeException(
                    'ตาราง finance_statement_pdf_extracts ยังไม่ได้ติดตั้ง '
                    . 'กรุณารัน migrations/2026_06_08_finance_statement_pdf_extracts.sql ด้วย user ฐานข้อมูลที่มีสิทธิ์ CREATE TABLE'
                );
            }
            throw $e;
        }
        return;
    }

    $pdo->exec("
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

function finance_pdf_unicode_from_hex(string $hex): string
{
    $out = '';
    for ($i = 0; $i + 3 < strlen($hex); $i += 4) {
        $cp = hexdec(substr($hex, $i, 4));
        $out .= html_entity_decode('&#' . $cp . ';', ENT_NOQUOTES, 'UTF-8');
    }
    return $out;
}

function finance_pdf_inflate(string $stream): string
{
    $decoded = @gzuncompress($stream);
    if ($decoded !== false) return $decoded;
    $decoded = @gzdecode($stream);
    return $decoded !== false ? $decoded : $stream;
}

function finance_pdf_cmap(string $pdf, int $objectId): array
{
    if (!preg_match('/' . $objectId . '\s+0\s+obj.*?stream\r?\n(.*?)\r?\nendstream/s', $pdf, $match)) {
        return [];
    }

    $cmap = finance_pdf_inflate($match[1]);
    $map = [];

    preg_match_all('/<([0-9A-F]{4})>\s+<([0-9A-F]{4})>\s+<([0-9A-F]+)>/i', $cmap, $ranges, PREG_SET_ORDER);
    foreach ($ranges as $range) {
        $start = hexdec($range[1]);
        $end = hexdec($range[2]);
        $unicode = hexdec(substr($range[3], 0, 4));
        for ($code = $start; $code <= $end; $code++) {
            $map[strtoupper(sprintf('%04X', $code))] = finance_pdf_unicode_from_hex(sprintf('%04X', $unicode + ($code - $start)));
        }
    }

    preg_match_all('/<([0-9A-F]{4})>\s+<([0-9A-F]+)>/i', $cmap, $chars, PREG_SET_ORDER);
    foreach ($chars as $char) {
        $map[strtoupper($char[1])] = finance_pdf_unicode_from_hex($char[2]);
    }

    preg_match_all('/<([0-9A-F]{4})>\s+<([0-9A-F]{4})>\s+\[(.*?)\]/is', $cmap, $arrayRanges, PREG_SET_ORDER);
    foreach ($arrayRanges as $range) {
        $code = hexdec($range[1]);
        preg_match_all('/<([0-9A-F]+)>/i', $range[3], $values);
        foreach ($values[1] as $value) {
            $map[strtoupper(sprintf('%04X', $code++))] = finance_pdf_unicode_from_hex($value);
        }
    }

    return $map;
}

function finance_pdf_font_maps(string $pdf): array
{
    $fontObjects = [];
    preg_match_all('/(\d+)\s+0\s+obj(.*?)endobj/s', $pdf, $objects, PREG_SET_ORDER);
    foreach ($objects as $object) {
        if (preg_match('/\/ToUnicode\s+(\d+)\s+0\s+R/', $object[2], $toUnicode)) {
            $fontObjects[(int)$object[1]] = finance_pdf_cmap($pdf, (int)$toUnicode[1]);
        }
    }

    $maps = [];
    preg_match_all('/\/(F\d+)\s+(\d+)\s+0\s+R/', $pdf, $refs, PREG_SET_ORDER);
    foreach ($refs as $ref) {
        $fontObject = (int)$ref[2];
        if (isset($fontObjects[$fontObject])) {
            $maps[$ref[1]] = $fontObjects[$fontObject];
        }
    }

    return $maps;
}

function finance_pdf_decode_hex_text(string $hex, array $map): string
{
    $text = '';
    for ($i = 0; $i + 3 < strlen($hex); $i += 4) {
        $code = strtoupper(substr($hex, $i, 4));
        $text .= $map[$code] ?? '';
    }
    return $text;
}

function finance_pdf_decode_literal(string $text): string
{
    return stripcslashes(str_replace(['\\(', '\\)'], ['(', ')'], $text));
}

function finance_pdf_extract_text(string $filePath): array
{
    $pdf = (string)file_get_contents($filePath);
    $fontMaps = finance_pdf_font_maps($pdf);
    $chunks = [];
    $pageCount = preg_match_all('/\/Type\s*\/Page\b/', $pdf);

    preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $pdf, $streams);
    foreach ($streams[1] as $stream) {
        $content = finance_pdf_inflate($stream);
        if (!str_contains($content, 'BT')) continue;

        preg_match_all('/BT(.*?)ET/s', $content, $blocks);
        foreach ($blocks[1] as $block) {
            $font = 'F1';
            $x = 0.0;
            $y = 0.0;
            if (preg_match('/\/(F\d+)\s+[0-9.]+\s+Tf/', $block, $fontMatch)) {
                $font = $fontMatch[1];
            }
            if (preg_match('/1\s+0\s+0\s+1\s+(-?[0-9.]+)\s+(-?[0-9.]+)\s+Tm/', $block, $tm)) {
                $x = (float)$tm[1];
                $y = (float)$tm[2];
            }

            $text = '';
            preg_match_all('/<([0-9A-F]+)>\s*Tj|\((.*?)\)\s*Tj|\[(.*?)\]\s*TJ/is', $block, $parts, PREG_SET_ORDER);
            foreach ($parts as $part) {
                if (!empty($part[1])) {
                    $text .= finance_pdf_decode_hex_text($part[1], $fontMaps[$font] ?? []);
                } elseif (isset($part[2]) && $part[2] !== '') {
                    $text .= finance_pdf_decode_literal($part[2]);
                } elseif (isset($part[3])) {
                    preg_match_all('/<([0-9A-F]+)>|\((.*?)\)/is', $part[3], $items, PREG_SET_ORDER);
                    foreach ($items as $item) {
                        $text .= !empty($item[1])
                            ? finance_pdf_decode_hex_text($item[1], $fontMaps[$font] ?? [])
                            : finance_pdf_decode_literal($item[2] ?? '');
                    }
                }
            }

            $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');
            if ($text !== '') {
                $chunks[] = ['x' => $x, 'y' => $y, 'text' => $text];
            }
        }
    }

    usort($chunks, static fn(array $a, array $b): int => ($b['y'] <=> $a['y']) ?: ($a['x'] <=> $b['x']));
    $linesByY = [];
    foreach ($chunks as $chunk) {
        $linesByY[(string)round($chunk['y'])][] = $chunk;
    }

    $lines = [];
    foreach ($linesByY as $lineChunks) {
        usort($lineChunks, static fn(array $a, array $b): int => $a['x'] <=> $b['x']);
        $line = trim(implode(' ', array_column($lineChunks, 'text')));
        if ($line !== '') {
            $lines[] = $line;
        }
    }

    return ['page_count' => (int)$pageCount, 'text' => implode("\n", $lines), 'lines' => $lines];
}

function finance_pdf_number(string $value): float
{
    $clean = str_replace([',', ' '], '', $value);
    return is_numeric($clean) ? (float)$clean : 0.0;
}

function finance_pdf_metrics(array $lines): array
{
    $metrics = [];
    foreach ($lines as $line) {
        if (!preg_match_all('/-?\d{1,3}(?:,\d{3})*(?:\.\d+)?|-?\d+(?:\.\d+)?/', $line, $numbers)) {
            continue;
        }

        $label = trim(preg_replace('/-?\d{1,3}(?:,\d{3})*(?:\.\d+)?|-?\d+(?:\.\d+)?/u', ' ', $line) ?? '');
        $label = trim(preg_replace('/\s+/u', ' ', $label) ?? '');
        if ($label === '' || mb_strlen($label) < 2) {
            continue;
        }

        foreach ($numbers[0] as $number) {
            $metrics[] = [
                'label' => mb_substr($label, 0, 180),
                'value' => finance_pdf_number($number),
                'raw_value' => $number,
                'line' => mb_substr($line, 0, 500),
            ];
        }
    }

    return array_slice($metrics, 0, 120);
}

function finance_pdf_reconcile(PDO $pdo, string $month, array $metrics): array
{
    $stmt = $pdo->prepare('SELECT * FROM finance_monthly_auto WHERE month_year = :month');
    $stmt->execute([':month' => $month]);
    $auto = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $excelValues = [
        'total_revenue' => (float)($auto['total_revenue'] ?? 0),
        'total_expense' => (float)($auto['total_expense'] ?? 0),
        'cash_balance' => (float)($auto['cash_balance'] ?? 0),
        'inventory_balance' => (float)($auto['inventory_balance'] ?? 0),
    ];

    $checks = [];
    foreach ($excelValues as $key => $excelValue) {
        if ($excelValue == 0.0) continue;
        $best = null;
        foreach ($metrics as $metric) {
            $diff = abs((float)$metric['value'] - $excelValue);
            if ($best === null || $diff < $best['diff']) {
                $best = ['pdf_label' => $metric['label'], 'pdf_value' => (float)$metric['value'], 'diff' => $diff, 'line' => $metric['line']];
            }
        }
        if ($best !== null) {
            $checks[] = [
                'field' => $key,
                'excel_value' => round($excelValue, 2),
                'pdf_label' => $best['pdf_label'],
                'pdf_value' => round($best['pdf_value'], 2),
                'difference' => round($best['diff'], 2),
                'status' => $best['diff'] <= 1 ? 'verified' : ($best['diff'] <= max(100, abs($excelValue) * 0.01) ? 'near' : 'review'),
                'source_line' => $best['line'],
            ];
        }
    }

    return $checks;
}

function finance_pdf_extract_document(PDO $pdo, int $documentId, string $month, string $filePath): array
{
    finance_pdf_schema($pdo);
    try {
        $extracted = finance_pdf_extract_text($filePath);
        $metrics = finance_pdf_metrics($extracted['lines']);
        $reconcile = finance_pdf_reconcile($pdo, $month, $metrics);
        $status = $extracted['text'] !== '' && count($metrics) > 0 ? 'success' : 'partial';

        $stmt = $pdo->prepare("
            INSERT INTO finance_statement_pdf_extracts
                (document_id, month_year, extract_status, page_count, raw_text, metrics_json, reconcile_json, error_message)
            VALUES
                (:document_id, :month_year, :extract_status, :page_count, :raw_text, :metrics_json, :reconcile_json, NULL)
            ON DUPLICATE KEY UPDATE
                month_year=VALUES(month_year),
                extract_status=VALUES(extract_status),
                page_count=VALUES(page_count),
                raw_text=VALUES(raw_text),
                metrics_json=VALUES(metrics_json),
                reconcile_json=VALUES(reconcile_json),
                error_message=NULL,
                extracted_at=CURRENT_TIMESTAMP
        ");
        $stmt->execute([
            ':document_id' => $documentId,
            ':month_year' => $month,
            ':extract_status' => $status,
            ':page_count' => $extracted['page_count'],
            ':raw_text' => $extracted['text'],
            ':metrics_json' => json_encode($metrics, JSON_UNESCAPED_UNICODE),
            ':reconcile_json' => json_encode($reconcile, JSON_UNESCAPED_UNICODE),
        ]);

        return ['status' => $status, 'metrics' => $metrics, 'reconcile' => $reconcile];
    } catch (Throwable $e) {
        $stmt = $pdo->prepare("
            INSERT INTO finance_statement_pdf_extracts
                (document_id, month_year, extract_status, error_message)
            VALUES
                (:document_id, :month_year, 'failed', :error_message)
            ON DUPLICATE KEY UPDATE
                extract_status='failed',
                error_message=VALUES(error_message),
                extracted_at=CURRENT_TIMESTAMP
        ");
        $stmt->execute([
            ':document_id' => $documentId,
            ':month_year' => $month,
            ':error_message' => mb_substr($e->getMessage(), 0, 1000),
        ]);
        return ['status' => 'failed', 'metrics' => [], 'reconcile' => [], 'error' => $e->getMessage()];
    }
}
