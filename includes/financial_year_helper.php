<?php

require_once __DIR__ . "/approval_helper.php";

function financial_year_ensure_schema(mysqli $conn): void
{
    static $initialized = false;
    if ($initialized) {
        return;
    }

    mysqli_query($conn, "
        CREATE TABLE IF NOT EXISTS financial_years (
            id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            year_name VARCHAR(50) NOT NULL,
            year_code VARCHAR(20) NOT NULL,
            start_date DATE NOT NULL,
            end_date DATE NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");

    mysqli_query($conn, "
        CREATE TABLE IF NOT EXISTS voucher_series (
            id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            financial_year_id INT NOT NULL,
            module_key VARCHAR(50) NOT NULL,
            last_no INT NOT NULL DEFAULT 0,
            UNIQUE KEY uniq_year_module (financial_year_id, module_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");

    $initialized = true;
}

function financial_year_get_active(mysqli $conn): ?array
{
    financial_year_ensure_schema($conn);
    $result = mysqli_query($conn, "SELECT * FROM financial_years WHERE is_active = 1 ORDER BY start_date DESC LIMIT 1");
    return ($result && mysqli_num_rows($result) > 0) ? mysqli_fetch_assoc($result) : null;
}

function financial_year_get_for_date(mysqli $conn, string $date): ?array
{
    financial_year_ensure_schema($conn);
    $safeDate = mysqli_real_escape_string($conn, $date);
    $result = mysqli_query($conn, "
        SELECT *
        FROM financial_years
        WHERE '$safeDate' BETWEEN start_date AND end_date
        ORDER BY is_active DESC, start_date DESC
        LIMIT 1
    ");
    return ($result && mysqli_num_rows($result) > 0) ? mysqli_fetch_assoc($result) : null;
}

function financial_year_create(mysqli $conn, string $name, string $code, string $startDate, string $endDate, bool $activate = false): bool
{
    financial_year_ensure_schema($conn);
    if ($activate) {
        mysqli_query($conn, "UPDATE financial_years SET is_active = 0");
    }

    $stmt = $conn->prepare("
        INSERT INTO financial_years (year_name, year_code, start_date, end_date, is_active)
        VALUES (?, ?, ?, ?, ?)
    ");
    $isActive = $activate ? 1 : 0;
    $stmt->bind_param("ssssi", $name, $code, $startDate, $endDate, $isActive);
    return $stmt->execute();
}

function financial_year_activate(mysqli $conn, int $yearId): void
{
    financial_year_ensure_schema($conn);
    mysqli_query($conn, "UPDATE financial_years SET is_active = 0");
    mysqli_query($conn, "UPDATE financial_years SET is_active = 1 WHERE id = " . (int) $yearId);
}

function voucher_series_next(mysqli $conn, string $moduleKey, string $date): array
{
    financial_year_ensure_schema($conn);
    $year = financial_year_get_for_date($conn, $date);
    if (!$year) {
        $year = financial_year_get_active($conn);
    }
    if (!$year) {
        return ['financial_year_id' => null, 'series_no' => null, 'display_no' => null];
    }

    $yearId = (int) $year['id'];
    $safeModule = mysqli_real_escape_string($conn, $moduleKey);
    mysqli_query($conn, "
        INSERT INTO voucher_series (financial_year_id, module_key, last_no)
        VALUES ($yearId, '$safeModule', 0)
        ON DUPLICATE KEY UPDATE module_key = VALUES(module_key)
    ");

    mysqli_query($conn, "
        UPDATE voucher_series
        SET last_no = last_no + 1
        WHERE financial_year_id = $yearId
          AND module_key = '$safeModule'
    ");

    $row = mysqli_fetch_assoc(mysqli_query($conn, "
        SELECT last_no
        FROM voucher_series
        WHERE financial_year_id = $yearId
          AND module_key = '$safeModule'
        LIMIT 1
    "));

    $seriesNo = (int) ($row['last_no'] ?? 0);
    return [
        'financial_year_id' => $yearId,
        'series_no' => $seriesNo,
        'display_no' => strtoupper($moduleKey) . '-' . $year['year_code'] . '-' . str_pad((string) $seriesNo, 4, '0', STR_PAD_LEFT),
    ];
}

function voucher_series_preview(mysqli $conn, string $moduleKey, ?string $date = null): ?string
{
    financial_year_ensure_schema($conn);
    $date = $date ?: date('Y-m-d');
    $year = financial_year_get_for_date($conn, $date);
    if (!$year) {
        $year = financial_year_get_active($conn);
    }
    if (!$year) {
        return null;
    }

    $yearId = (int) $year['id'];
    $safeModule = mysqli_real_escape_string($conn, $moduleKey);
    $row = mysqli_fetch_assoc(mysqli_query($conn, "
        SELECT last_no
        FROM voucher_series
        WHERE financial_year_id = $yearId
          AND module_key = '$safeModule'
        LIMIT 1
    "));
    $next = ((int) ($row['last_no'] ?? 0)) + 1;
    return strtoupper($moduleKey) . '-' . $year['year_code'] . '-' . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
}

function voucher_series_ensure_columns(mysqli $conn, string $tableName): void
{
    if (!approval_column_exists($conn, $tableName, 'financial_year_id')) {
        mysqli_query($conn, "ALTER TABLE `$tableName` ADD COLUMN financial_year_id INT NULL DEFAULT NULL");
    }
    if (!approval_column_exists($conn, $tableName, 'series_no')) {
        mysqli_query($conn, "ALTER TABLE `$tableName` ADD COLUMN series_no INT NULL DEFAULT NULL");
    }
    if (!approval_column_exists($conn, $tableName, 'display_voucher_no')) {
        mysqli_query($conn, "ALTER TABLE `$tableName` ADD COLUMN display_voucher_no VARCHAR(50) NULL DEFAULT NULL");
    }
}

function voucher_series_ensure_transaction_columns(mysqli $conn): void
{
    financial_year_ensure_schema($conn);
    voucher_series_ensure_columns($conn, 'payment_voucher');
    voucher_series_ensure_columns($conn, 'receipt_voucher');
    voucher_series_ensure_columns($conn, 'ONLINE_GENERAL_VOUCHER');
    voucher_series_ensure_columns($conn, 'purchase_issue_return');
    voucher_series_ensure_columns($conn, 'sale_issue_return');
    voucher_series_ensure_columns($conn, 'stock_adjustment_master');
}
