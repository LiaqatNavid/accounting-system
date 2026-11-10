<?php

require_once __DIR__ . "/approval_helper.php";

function soft_delete_ensure_columns(mysqli $conn, string $tableName): void
{
    if (!approval_column_exists($conn, $tableName, 'deleted_at')) {
        mysqli_query($conn, "ALTER TABLE `$tableName` ADD COLUMN deleted_at DATETIME NULL DEFAULT NULL");
    }
    if (!approval_column_exists($conn, $tableName, 'deleted_by')) {
        mysqli_query($conn, "ALTER TABLE `$tableName` ADD COLUMN deleted_by INT NULL DEFAULT NULL");
    }
}

function soft_delete_ensure_schema(mysqli $conn): void
{
    static $initialized = false;
    if ($initialized) {
        return;
    }

    $tables = [
        'payment_voucher',
        'receipt_voucher',
        'ONLINE_GENERAL_VOUCHER',
        'purchase_issue_return',
        'sale_issue_return',
        'stock_adjustment_master'
    ];

    foreach ($tables as $table) {
        soft_delete_ensure_columns($conn, $table);
    }

    $initialized = true;
}

function soft_delete_mark(mysqli $conn, string $tableName, string $idColumn, $idValue): bool
{
    soft_delete_ensure_schema($conn);
    $tableName = mysqli_real_escape_string($conn, $tableName);
    $idColumn = mysqli_real_escape_string($conn, $idColumn);
    $idValue = mysqli_real_escape_string($conn, (string) $idValue);
    $userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 'NULL';

    return mysqli_query($conn, "
        UPDATE `$tableName`
        SET deleted_at = NOW(),
            deleted_by = $userId
        WHERE `$idColumn` = '$idValue'
          AND deleted_at IS NULL
    ");
}

function soft_delete_restore(mysqli $conn, string $tableName, string $idColumn, $idValue): bool
{
    soft_delete_ensure_schema($conn);
    $tableName = mysqli_real_escape_string($conn, $tableName);
    $idColumn = mysqli_real_escape_string($conn, $idColumn);
    $idValue = mysqli_real_escape_string($conn, (string) $idValue);

    return mysqli_query($conn, "
        UPDATE `$tableName`
        SET deleted_at = NULL,
            deleted_by = NULL
        WHERE `$idColumn` = '$idValue'
    ");
}
