<?php

require_once __DIR__ . "/audit_helper.php";

function backup_storage_dir(): string
{
    return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'backups';
}

function backup_ensure_storage_dir(): string
{
    $dir = backup_storage_dir();
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    return $dir;
}

function backup_tool_paths(): array
{
    return [
        'mysqldump' => "C:/xampp_new/mysql/bin/mysqldump.exe",
        'mysql' => "C:/xampp_new/mysql/bin/mysql.exe",
    ];
}

function backup_create_file(string $host, string $username, string $password, string $database, ?string $customPrefix = null): array
{
    $paths = backup_tool_paths();
    $mysqldump = $paths['mysqldump'];

    if (!file_exists($mysqldump)) {
        return ['ok' => false, 'message' => 'mysqldump not found. Check XAMPP path.'];
    }

    $dir = backup_ensure_storage_dir();
    $fileName = ($customPrefix ?: $database . "_backup") . "_" . date("Y-m-d_H-i-s") . ".sql";
    $filePath = $dir . DIRECTORY_SEPARATOR . $fileName;

    $commandParts = [
        '"' . $mysqldump . '"',
        '--host=' . escapeshellarg($host),
        '--user=' . escapeshellarg($username),
    ];

    if ($password !== '') {
        $commandParts[] = '--password=' . escapeshellarg($password);
    }

    $commandParts[] = '--routines';
    $commandParts[] = '--triggers';
    $commandParts[] = '--events';
    $commandParts[] = '--add-drop-database';
    $commandParts[] = '--add-drop-table';
    $commandParts[] = '--skip-extended-insert';
    $commandParts[] = '--single-transaction';
    $commandParts[] = '--databases ' . escapeshellarg($database);

    $command = implode(' ', $commandParts) . ' > ' . escapeshellarg($filePath) . ' 2>&1';
    exec($command, $output, $exitCode);

    if ($exitCode !== 0 || !file_exists($filePath) || filesize($filePath) === 0) {
        if (file_exists($filePath)) {
            @unlink($filePath);
        }
        return ['ok' => false, 'message' => trim(implode("\n", $output)) ?: 'Backup command failed.'];
    }

    return [
        'ok' => true,
        'file_name' => $fileName,
        'file_path' => $filePath,
        'file_size' => filesize($filePath),
    ];
}

function backup_restore_file(string $host, string $username, string $password, string $database, string $filePath): array
{
    $paths = backup_tool_paths();
    $mysql = $paths['mysql'];

    if (!file_exists($mysql)) {
        return ['ok' => false, 'message' => 'mysql client not found. Check XAMPP path.'];
    }

    if (!file_exists($filePath)) {
        return ['ok' => false, 'message' => 'Backup file not found.'];
    }

    $command = 'cmd /c ""' . $mysql . '" --host=' . escapeshellarg($host) . ' --user=' . escapeshellarg($username);
    if ($password !== '') {
        $command .= ' --password=' . escapeshellarg($password);
    }
    $command .= ' ' . escapeshellarg($database) . ' < ' . escapeshellarg($filePath) . '" 2>&1';

    exec($command, $output, $exitCode);

    if ($exitCode !== 0) {
        return ['ok' => false, 'message' => trim(implode("\n", $output)) ?: 'Restore command failed.'];
    }

    return ['ok' => true];
}

function backup_list_files(): array
{
    $dir = backup_ensure_storage_dir();
    $files = glob($dir . DIRECTORY_SEPARATOR . '*.sql') ?: [];
    rsort($files);

    return array_map(static function (string $path): array {
        return [
            'name' => basename($path),
            'path' => $path,
            'size' => file_exists($path) ? filesize($path) : 0,
            'modified_at' => file_exists($path) ? date('Y-m-d H:i:s', filemtime($path)) : null,
        ];
    }, $files);
}
