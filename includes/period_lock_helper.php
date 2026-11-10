<?php

function period_lock_ensure_schema(mysqli $conn): void
{
    static $initialized = false;
    if ($initialized) {
        return;
    }

    mysqli_query($conn, "
        CREATE TABLE IF NOT EXISTS accounting_period_locks (
            id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            from_date DATE NOT NULL,
            to_date DATE NOT NULL,
            remarks VARCHAR(255) NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_by INT NULL DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");

    $initialized = true;
}

function period_lock_find(mysqli $conn, string $date): ?array
{
    period_lock_ensure_schema($conn);
    $safeDate = mysqli_real_escape_string($conn, $date);
    $result = mysqli_query($conn, "
        SELECT *
        FROM accounting_period_locks
        WHERE is_active = 1
          AND '$safeDate' BETWEEN from_date AND to_date
        ORDER BY from_date DESC
        LIMIT 1
    ");

    return ($result && mysqli_num_rows($result) > 0) ? mysqli_fetch_assoc($result) : null;
}

function period_lock_is_locked(mysqli $conn, string $date): bool
{
    return period_lock_find($conn, $date) !== null;
}

function period_lock_guard_or_redirect(mysqli $conn, string $date, string $redirectUrl): void
{
    if (period_lock_is_locked($conn, $date)) {
        header("Location: " . $redirectUrl . (str_contains($redirectUrl, '?') ? '&' : '?') . "period_locked=1");
        exit;
    }
}
