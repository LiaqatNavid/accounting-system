<?php

function approval_column_exists($conn, $tableName, $columnName)
{
    $tableName = mysqli_real_escape_string($conn, $tableName);
    $columnName = mysqli_real_escape_string($conn, $columnName);
    $query = mysqli_query($conn, "SHOW COLUMNS FROM `$tableName` LIKE '$columnName'");
    return $query && mysqli_num_rows($query) > 0;
}

function approval_ensure_table_columns($conn, $tableName, $userColumn = null)
{
    if (!approval_column_exists($conn, $tableName, 'approval_status')) {
        mysqli_query($conn, "
            ALTER TABLE `$tableName`
            ADD COLUMN approval_status VARCHAR(20) NOT NULL DEFAULT 'Draft'
        ");
    }

    if (!approval_column_exists($conn, $tableName, 'approved_at')) {
        mysqli_query($conn, "
            ALTER TABLE `$tableName`
            ADD COLUMN approved_at DATETIME NULL DEFAULT NULL
        ");
    }

    if ($userColumn !== null && !approval_column_exists($conn, $tableName, $userColumn)) {
        mysqli_query($conn, "
            ALTER TABLE `$tableName`
            ADD COLUMN `$userColumn` INT NULL DEFAULT NULL
        ");
    }

    mysqli_query($conn, "
        UPDATE `$tableName`
        SET approval_status = 'Draft'
        WHERE approval_status IS NULL OR approval_status = ''
    ");
}

function approval_ensure_schema($conn)
{
    approval_ensure_table_columns($conn, 'payment_voucher', 'approved_by');
    approval_ensure_table_columns($conn, 'receipt_voucher', 'approved_by');
    approval_ensure_table_columns($conn, 'ONLINE_GENERAL_VOUCHER', 'APPROVED_BY');
    approval_ensure_table_columns($conn, 'purchase_issue_return', 'approved_by');
    approval_ensure_table_columns($conn, 'sale_issue_return', 'approved_by');
    approval_ensure_table_columns($conn, 'stock_adjustment_master', 'approved_by');
}

function approval_normalize_status($status)
{
    $status = strtolower(trim((string) $status));
    if ($status === 'approved') {
        return 'Approved';
    }
    return 'Draft';
}

function approval_is_locked($status)
{
    return approval_normalize_status($status) === 'Approved';
}

function approval_get_status($conn, $tableName, $idColumn, $idValue)
{
    approval_ensure_schema($conn);
    $tableName = mysqli_real_escape_string($conn, $tableName);
    $idColumn = mysqli_real_escape_string($conn, $idColumn);
    $idValue = mysqli_real_escape_string($conn, (string) $idValue);
    $query = mysqli_query($conn, "
        SELECT approval_status
        FROM `$tableName`
        WHERE `$idColumn` = '$idValue'
        LIMIT 1
    ");

    if (!$query || mysqli_num_rows($query) === 0) {
        return null;
    }

    $row = mysqli_fetch_assoc($query);
    return approval_normalize_status($row['approval_status'] ?? 'Draft');
}

function approval_update_status($conn, $tableName, $idColumn, $idValue, $newStatus, $approvedByColumn = null)
{
    approval_ensure_schema($conn);
    $tableName = mysqli_real_escape_string($conn, $tableName);
    $idColumn = mysqli_real_escape_string($conn, $idColumn);
    $idValue = mysqli_real_escape_string($conn, (string) $idValue);
    $status = approval_normalize_status($newStatus);
    $approvedByValue = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 'NULL';

    $setParts = ["approval_status = '" . mysqli_real_escape_string($conn, $status) . "'"];

    if ($status === 'Approved') {
        $setParts[] = "approved_at = NOW()";
        if ($approvedByColumn !== null) {
            $approvedByColumn = mysqli_real_escape_string($conn, $approvedByColumn);
            $setParts[] = "`$approvedByColumn` = " . $approvedByValue;
        }
    } else {
        $setParts[] = "approved_at = NULL";
        if ($approvedByColumn !== null) {
            $approvedByColumn = mysqli_real_escape_string($conn, $approvedByColumn);
            $setParts[] = "`$approvedByColumn` = NULL";
        }
    }

    return mysqli_query($conn, "
        UPDATE `$tableName`
        SET " . implode(', ', $setParts) . "
        WHERE `$idColumn` = '$idValue'
    ");
}
