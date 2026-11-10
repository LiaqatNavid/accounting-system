<?php

if (!function_exists('audit_column_exists')) {
    function audit_column_exists(mysqli $conn, string $tableName, string $columnName): bool
    {
        $tableName = mysqli_real_escape_string($conn, $tableName);
        $columnName = mysqli_real_escape_string($conn, $columnName);

        $result = mysqli_query($conn, "
            SELECT 1
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = '{$tableName}'
              AND COLUMN_NAME = '{$columnName}'
            LIMIT 1
        ");

        return $result && mysqli_num_rows($result) > 0;
    }
}

if (!function_exists('ensure_purchase_sale_created_at_columns')) {
    function ensure_purchase_sale_created_at_columns(mysqli $conn): void
    {
        static $checked = false;

        if ($checked) {
            return;
        }

        if (!audit_column_exists($conn, 'purchase_issue_return', 'created_at')) {
            mysqli_query($conn, "
                ALTER TABLE purchase_issue_return
                ADD COLUMN created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
            ");
            mysqli_query($conn, "
                UPDATE purchase_issue_return
                SET created_at = CONCAT(record_date, ' 00:00:00')
                WHERE created_at IS NULL
            ");
        }

        if (!audit_column_exists($conn, 'sale_issue_return', 'created_at')) {
            mysqli_query($conn, "
                ALTER TABLE sale_issue_return
                ADD COLUMN created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
            ");
            mysqli_query($conn, "
                UPDATE sale_issue_return
                SET created_at = CONCAT(record_date, ' 00:00:00')
                WHERE created_at IS NULL
            ");
        }

        $checked = true;
    }
}

if (!function_exists('ensure_audit_delete_log_table')) {
    function ensure_audit_delete_log_table(mysqli $conn): void
    {
        static $initialized = false;

        if ($initialized) {
            return;
        }

        $sql = "
            CREATE TABLE IF NOT EXISTS audit_delete_log (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                entity_type VARCHAR(100) NOT NULL,
                record_key VARCHAR(150) NOT NULL,
                record_label VARCHAR(255) DEFAULT NULL,
                meta_json LONGTEXT DEFAULT NULL,
                deleted_by INT(11) DEFAULT NULL,
                deleted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_entity_type (entity_type),
                KEY idx_deleted_at (deleted_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ";

        mysqli_query($conn, $sql);
        $initialized = true;
    }
}

if (!function_exists('ensure_audit_activity_log_table')) {
    function ensure_audit_activity_log_table(mysqli $conn): void
    {
        static $initialized = false;

        if ($initialized) {
            return;
        }

        mysqli_query($conn, "
            CREATE TABLE IF NOT EXISTS audit_activity_log (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                event_type VARCHAR(50) NOT NULL,
                module_key VARCHAR(100) NOT NULL,
                record_key VARCHAR(150) DEFAULT NULL,
                summary VARCHAR(255) DEFAULT NULL,
                before_json LONGTEXT DEFAULT NULL,
                after_json LONGTEXT DEFAULT NULL,
                meta_json LONGTEXT DEFAULT NULL,
                performed_by INT(11) DEFAULT NULL,
                performed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_event_type (event_type),
                KEY idx_module_key (module_key),
                KEY idx_performed_at (performed_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");

        $initialized = true;
    }
}

if (!function_exists('ensure_audit_schema')) {
    function ensure_audit_schema(mysqli $conn): void
    {
        ensure_purchase_sale_created_at_columns($conn);
        ensure_audit_delete_log_table($conn);
        ensure_audit_activity_log_table($conn);
    }
}

if (!function_exists('audit_log_delete')) {
    function audit_log_delete(mysqli $conn, string $entityType, string $recordKey, string $recordLabel = '', array $meta = []): bool
    {
        ensure_audit_schema($conn);

        $deletedBy = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
        $metaJson = empty($meta) ? null : json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $stmt = $conn->prepare("\n        INSERT INTO audit_delete_log\n        (entity_type, record_key, record_label, meta_json, deleted_by)\n        VALUES (?, ?, ?, ?, ?)\n    ");

        if (!$stmt) {
            return false;
        }

        $stmt->bind_param("ssssi", $entityType, $recordKey, $recordLabel, $metaJson, $deletedBy);
        $ok = $stmt->execute();
        $stmt->close();

        return $ok;
    }
}

if (!function_exists('audit_log_event')) {
    function audit_log_event(
        mysqli $conn,
        string $eventType,
        string $moduleKey,
        ?string $recordKey = null,
        string $summary = '',
        array $before = [],
        array $after = [],
        array $meta = []
    ): bool {
        ensure_audit_schema($conn);

        $performedBy = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
        $beforeJson = empty($before) ? null : json_encode($before, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $afterJson = empty($after) ? null : json_encode($after, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $metaJson = empty($meta) ? null : json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $stmt = $conn->prepare("\n        INSERT INTO audit_activity_log\n        (event_type, module_key, record_key, summary, before_json, after_json, meta_json, performed_by)\n        VALUES (?, ?, ?, ?, ?, ?, ?, ?)\n    ");

        if (!$stmt) {
            return false;
        }

        $stmt->bind_param(
            "sssssssi",
            $eventType,
            $moduleKey,
            $recordKey,
            $summary,
            $beforeJson,
            $afterJson,
            $metaJson,
            $performedBy
        );

        $ok = $stmt->execute();
        $stmt->close();

        return $ok;
    }
}
