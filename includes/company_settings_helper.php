<?php

if (!function_exists('company_settings_defaults')) {
    function company_settings_defaults(): array
    {
        return [
            'company_name' => 'Accounting ERP',
            'tagline' => 'Small Business Solution Accounting System',
            'contact' => '0321-5881663',
            'address' => '',
            'email' => '',
            'logo_path' => '',
            'prepared_by_label' => 'Prepared By',
            'checked_by_label' => 'Checked By',
            'approved_by_label' => 'Approved By',
            'print_footer' => 'Thank you for using Accounting ERP.',
            'system_activity_retention_months' => '0',
            'approval_history_retention_months' => '0',
        ];
    }
}

if (!function_exists('company_settings_column_exists')) {
    function company_settings_column_exists(mysqli $conn, string $columnName): bool
    {
        $safeColumn = mysqli_real_escape_string($conn, $columnName);
        $result = mysqli_query($conn, "
            SELECT 1
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'company_settings'
              AND COLUMN_NAME = '{$safeColumn}'
            LIMIT 1
        ");

        return $result && mysqli_num_rows($result) > 0;
    }
}

if (!function_exists('company_settings_ensure_schema')) {
    function company_settings_ensure_schema(mysqli $conn): void
    {
        static $initialized = false;
        if ($initialized) {
            return;
        }

        mysqli_query($conn, "
            CREATE TABLE IF NOT EXISTS company_settings (
                id INT NOT NULL PRIMARY KEY,
                company_name VARCHAR(255) NOT NULL,
                tagline VARCHAR(255) NOT NULL,
                contact VARCHAR(100) NOT NULL,
                address VARCHAR(255) NOT NULL,
                email VARCHAR(255) NOT NULL,
                logo_path VARCHAR(255) NOT NULL,
                prepared_by_label VARCHAR(100) NOT NULL,
                checked_by_label VARCHAR(100) NOT NULL,
                approved_by_label VARCHAR(100) NOT NULL,
                print_footer VARCHAR(255) NOT NULL,
                system_activity_retention_months INT NOT NULL DEFAULT 0,
                approval_history_retention_months INT NOT NULL DEFAULT 0,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");

        if (!company_settings_column_exists($conn, 'system_activity_retention_months')) {
            mysqli_query($conn, "
                ALTER TABLE company_settings
                ADD COLUMN system_activity_retention_months INT NOT NULL DEFAULT 0
                AFTER print_footer
            ");
        }

        if (!company_settings_column_exists($conn, 'approval_history_retention_months')) {
            mysqli_query($conn, "
                ALTER TABLE company_settings
                ADD COLUMN approval_history_retention_months INT NOT NULL DEFAULT 0
                AFTER system_activity_retention_months
            ");
        }

        $row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM company_settings"));
        if ((int) ($row['total'] ?? 0) === 0) {
            $defaults = company_settings_defaults();
            $stmt = $conn->prepare("
                INSERT INTO company_settings
                (id, company_name, tagline, contact, address, email, logo_path, prepared_by_label, checked_by_label, approved_by_label, print_footer, system_activity_retention_months, approval_history_retention_months)
                VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $systemActivityRetentionMonths = (int) $defaults['system_activity_retention_months'];
            $approvalHistoryRetentionMonths = (int) $defaults['approval_history_retention_months'];
            $stmt->bind_param(
                "ssssssssssii",
                $defaults['company_name'],
                $defaults['tagline'],
                $defaults['contact'],
                $defaults['address'],
                $defaults['email'],
                $defaults['logo_path'],
                $defaults['prepared_by_label'],
                $defaults['checked_by_label'],
                $defaults['approved_by_label'],
                $defaults['print_footer'],
                $systemActivityRetentionMonths,
                $approvalHistoryRetentionMonths
            );
            $stmt->execute();
        }

        $initialized = true;
    }
}

if (!function_exists('company_settings_get')) {
    function company_settings_get(?mysqli $conn = null): array
    {
        static $cached = null;
        if (!empty($GLOBALS['__company_settings_cache_reset'])) {
            $cached = null;
            unset($GLOBALS['__company_settings_cache_reset']);
        }

        if ($cached !== null) {
            return $cached;
        }

        $settings = company_settings_defaults();

        if ($conn instanceof mysqli) {
            company_settings_ensure_schema($conn);
            $result = mysqli_query($conn, "SELECT * FROM company_settings WHERE id = 1 LIMIT 1");
            if ($result && mysqli_num_rows($result) > 0) {
                $row = mysqli_fetch_assoc($result);
                foreach ($settings as $key => $value) {
                    if (isset($row[$key])) {
                        $settings[$key] = (string) $row[$key];
                    }
                }
            }
        }

        $cached = $settings;
        return $settings;
    }
}

if (!function_exists('company_settings_save')) {
    function company_settings_save(mysqli $conn, array $data): bool
    {
        company_settings_ensure_schema($conn);

        $current = company_settings_get($conn);
        $merged = array_merge($current, array_intersect_key($data, $current));

        $stmt = $conn->prepare("
            UPDATE company_settings
            SET company_name = ?,
                tagline = ?,
                contact = ?,
                address = ?,
                email = ?,
                logo_path = ?,
                prepared_by_label = ?,
                checked_by_label = ?,
                approved_by_label = ?,
                print_footer = ?,
                system_activity_retention_months = ?,
                approval_history_retention_months = ?
            WHERE id = 1
        ");

        $systemActivityRetentionMonths = max(0, (int) $merged['system_activity_retention_months']);
        $approvalHistoryRetentionMonths = max(0, (int) $merged['approval_history_retention_months']);

        $stmt->bind_param(
            "ssssssssssii",
            $merged['company_name'],
            $merged['tagline'],
            $merged['contact'],
            $merged['address'],
            $merged['email'],
            $merged['logo_path'],
            $merged['prepared_by_label'],
            $merged['checked_by_label'],
            $merged['approved_by_label'],
            $merged['print_footer'],
            $systemActivityRetentionMonths,
            $approvalHistoryRetentionMonths
        );

        $ok = $stmt->execute();
        if ($ok) {
            $GLOBALS['__company_settings_cache_reset'] = true;
        }
        return $ok;
    }
}
