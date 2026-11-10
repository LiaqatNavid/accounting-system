<?php

require_once __DIR__ . "/audit_helper.php";

if (!function_exists('history_retention_archive_directory')) {
    function history_retention_archive_directory(): string
    {
        return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'history_archives';
    }
}

if (!function_exists('history_retention_relative_archive_path')) {
    function history_retention_relative_archive_path(string $fileName): string
    {
        return 'storage/history_archives/' . $fileName;
    }
}

if (!function_exists('history_retention_write_archive')) {
    function history_retention_write_archive(string $bucketKey, array $rows, array $meta = []): ?string
    {
        if (empty($rows)) {
            return null;
        }

        $directory = history_retention_archive_directory();
        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            return null;
        }

        $fileName = $bucketKey . '_' . date('Y-m-d_H-i-s') . '.txt';
        $absolutePath = $directory . DIRECTORY_SEPARATOR . $fileName;

        $lines = [];
        $lines[] = strtoupper(str_replace('_', ' ', $bucketKey)) . ' ARCHIVE';
        $lines[] = 'Created At: ' . date('Y-m-d H:i:s');
        foreach ($meta as $label => $value) {
            $lines[] = ucwords(str_replace('_', ' ', (string) $label)) . ': ' . (string) $value;
        }
        $lines[] = 'Rows: ' . count($rows);
        $lines[] = '';

        foreach ($rows as $row) {
            $lines[] = str_repeat('=', 80);
            $lines[] = 'ID: ' . (string) ($row['id'] ?? '');
            $lines[] = 'Event Type: ' . (string) ($row['event_type'] ?? '');
            $lines[] = 'Module: ' . (string) ($row['module_key'] ?? '');
            $lines[] = 'Record: ' . (string) ($row['record_key'] ?? '');
            $lines[] = 'Summary: ' . (string) ($row['summary'] ?? '');
            $lines[] = 'User: ' . (string) ($row['user_name'] ?? 'System');
            $lines[] = 'Time: ' . (string) ($row['performed_at'] ?? '');
            $lines[] = 'Before JSON: ' . (string) ($row['before_json'] ?? '');
            $lines[] = 'After JSON: ' . (string) ($row['after_json'] ?? '');
            $lines[] = 'Meta JSON: ' . (string) ($row['meta_json'] ?? '');
            $lines[] = '';
        }

        return file_put_contents($absolutePath, implode(PHP_EOL, $lines) . PHP_EOL) !== false
            ? history_retention_relative_archive_path($fileName)
            : null;
    }
}

if (!function_exists('history_retention_manual_clear')) {
    function history_retention_manual_clear(mysqli $conn, string $bucketKey, string $eventConditionSql, string $summary): array
    {
        $rows = [];
        $result = mysqli_query($conn, "
            SELECT a.*, u.username AS user_name
            FROM audit_activity_log a
            LEFT JOIN users u ON u.id = a.performed_by
            WHERE {$eventConditionSql}
            ORDER BY a.performed_at ASC, a.id ASC
        ");

        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $rows[] = $row;
            }
        }

        if (empty($rows)) {
            return [
                'deleted_rows' => 0,
                'archive_file' => null,
            ];
        }

        $archiveFile = history_retention_write_archive($bucketKey, $rows, [
            'trigger' => 'manual',
            'cleared_by' => (string) ($_SESSION['user_name'] ?? 'System'),
        ]);
        if ($archiveFile === null) {
            return [
                'deleted_rows' => 0,
                'archive_file' => null,
            ];
        }

        $ids = array_map(static function (array $row): int {
            return (int) ($row['id'] ?? 0);
        }, $rows);
        $ids = array_values(array_filter($ids));

        if (empty($ids)) {
            return [
                'deleted_rows' => 0,
                'archive_file' => $archiveFile,
            ];
        }

        $idList = implode(',', $ids);
        mysqli_query($conn, "DELETE FROM audit_activity_log WHERE id IN ({$idList})");

        audit_log_event(
            $conn,
            'history_cleanup',
            $bucketKey,
            (string) count($ids),
            $summary,
            [],
            [],
            [
                'mode' => 'manual',
                'archive_file' => $archiveFile,
                'deleted_rows' => count($ids),
            ]
        );

        return [
            'deleted_rows' => count($ids),
            'archive_file' => $archiveFile,
        ];
    }
}

if (!function_exists('history_retention_write_delete_archive')) {
    function history_retention_write_delete_archive(string $bucketKey, array $rows, array $meta = []): ?string
    {
        if (empty($rows)) {
            return null;
        }

        $directory = history_retention_archive_directory();
        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            return null;
        }

        $fileName = $bucketKey . '_' . date('Y-m-d_H-i-s') . '.txt';
        $absolutePath = $directory . DIRECTORY_SEPARATOR . $fileName;

        $lines = [];
        $lines[] = strtoupper(str_replace('_', ' ', $bucketKey)) . ' ARCHIVE';
        $lines[] = 'Created At: ' . date('Y-m-d H:i:s');
        foreach ($meta as $label => $value) {
            $lines[] = ucwords(str_replace('_', ' ', (string) $label)) . ': ' . (string) $value;
        }
        $lines[] = 'Rows: ' . count($rows);
        $lines[] = '';

        foreach ($rows as $row) {
            $lines[] = str_repeat('=', 80);
            $lines[] = 'ID: ' . (string) ($row['id'] ?? '');
            $lines[] = 'Entity Type: ' . (string) ($row['entity_type'] ?? '');
            $lines[] = 'Record Key: ' . (string) ($row['record_key'] ?? '');
            $lines[] = 'Record Label: ' . (string) ($row['record_label'] ?? '');
            $lines[] = 'Deleted By: ' . (string) ($row['deleted_by_name'] ?? 'System');
            $lines[] = 'Deleted At: ' . (string) ($row['deleted_at'] ?? '');
            $lines[] = 'Meta JSON: ' . (string) ($row['meta_json'] ?? '');
            $lines[] = '';
        }

        return file_put_contents($absolutePath, implode(PHP_EOL, $lines) . PHP_EOL) !== false
            ? history_retention_relative_archive_path($fileName)
            : null;
    }
}

if (!function_exists('history_retention_manual_clear_delete_log')) {
    function history_retention_manual_clear_delete_log(mysqli $conn, string $bucketKey, string $summary): array
    {
        $rows = [];
        $result = mysqli_query($conn, "
            SELECT d.*, u.username AS deleted_by_name
            FROM audit_delete_log d
            LEFT JOIN users u ON u.id = d.deleted_by
            ORDER BY d.deleted_at ASC, d.id ASC
        ");

        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $rows[] = $row;
            }
        }

        if (empty($rows)) {
            return [
                'deleted_rows' => 0,
                'archive_file' => null,
            ];
        }

        $archiveFile = history_retention_write_delete_archive($bucketKey, $rows, [
            'trigger' => 'manual',
            'cleared_by' => (string) ($_SESSION['user_name'] ?? 'System'),
        ]);
        if ($archiveFile === null) {
            return [
                'deleted_rows' => 0,
                'archive_file' => null,
            ];
        }

        $ids = array_map(static function (array $row): int {
            return (int) ($row['id'] ?? 0);
        }, $rows);
        $ids = array_values(array_filter($ids));

        if (empty($ids)) {
            return [
                'deleted_rows' => 0,
                'archive_file' => $archiveFile,
            ];
        }

        $idList = implode(',', $ids);
        mysqli_query($conn, "DELETE FROM audit_delete_log WHERE id IN ({$idList})");

        audit_log_event(
            $conn,
            'history_cleanup',
            $bucketKey,
            (string) count($ids),
            $summary,
            [],
            [],
            [
                'mode' => 'manual',
                'archive_file' => $archiveFile,
                'deleted_rows' => count($ids),
            ]
        );

        return [
            'deleted_rows' => count($ids),
            'archive_file' => $archiveFile,
        ];
    }
}
