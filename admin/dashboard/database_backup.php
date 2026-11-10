<?php
session_start();
include __DIR__ . "/../../includes/auth_check.php";
include __DIR__ . "/../../config/db.php";
include __DIR__ . "/../../includes/backup_helper.php";
include __DIR__ . "/../../includes/audit_helper.php";

ensure_audit_schema($conn);
$files = backup_list_files();

if (isset($_GET['download'])) {
    $fileName = basename((string) $_GET['download']);
    $filePath = backup_storage_dir() . DIRECTORY_SEPARATOR . $fileName;

    if (!file_exists($filePath)) {
        header("Location: database_backup.php?denied=1");
        exit;
    }

    audit_log_event($conn, 'backup_download', 'database_backup', $fileName, 'Database backup downloaded', [], [], [
        'file_name' => $fileName,
        'file_size' => filesize($filePath),
    ]);

    header("Content-Type: application/octet-stream");
    header("Content-Disposition: attachment; filename=\"$fileName\"");
    header("Content-Length: " . filesize($filePath));
    header("Pragma: no-cache");
    header("Expires: 0");
    readfile($filePath);
    exit;
}

if (isset($_POST['take_backup'])) {
    $result = backup_create_file($host, $username, $password, $database);

    audit_log_event(
        $conn,
        'backup_create',
        'database_backup',
        $result['file_name'] ?? null,
        $result['ok'] ? 'Database backup created' : 'Database backup failed',
        [],
        [],
        $result
    );

    header("Location: database_backup.php?" . ($result['ok'] ? 'saved=1' : 'denied=1'));
    exit;
}

if (isset($_POST['restore_backup'])) {
    if (!rbac_can_do('backup_restore')) {
        header("Location: database_backup.php?denied=1");
        exit;
    }

    $fileName = basename((string) ($_POST['backup_file'] ?? ''));
    $filePath = backup_storage_dir() . DIRECTORY_SEPARATOR . $fileName;
    $result = backup_restore_file($host, $username, $password, $database, $filePath);

    audit_log_event(
        $conn,
        'backup_restore',
        'database_backup',
        $fileName ?: null,
        $result['ok'] ? 'Database backup restored' : 'Database restore failed',
        [],
        [],
        $result + ['file_name' => $fileName]
    );

    header("Location: database_backup.php?" . ($result['ok'] ? 'backup_restored=1' : 'denied=1'));
    exit;
}

$files = backup_list_files();
$history = mysqli_query($conn, "
    SELECT a.*, u.username AS user_name
    FROM audit_activity_log a
    LEFT JOIN users u ON u.id = a.performed_by
    WHERE a.module_key = 'database_backup'
    ORDER BY a.performed_at DESC
    LIMIT 50
");

include __DIR__ . "/../../includes/header.php";
?>

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div>
            <h3 class="mb-1">Database Backup</h3>
            <div class="text-muted">Create, download, and restore database backups with full activity history.</div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-lg-4">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-primary text-white"><h6 class="mb-0">Create Backup</h6></div>
                <div class="card-body">
                    <p class="text-muted small mb-3">Create a server-side SQL backup file before period closing, restore, or major updates.</p>
                    <form method="post">
                        <button type="submit" name="take_backup" class="btn btn-success">Create Backup Now</button>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-primary text-white"><h6 class="mb-0">Restore Backup</h6></div>
                <div class="card-body">
                    <p class="text-muted small mb-3">Restore only from a trusted SQL backup. This action should be restricted to senior admin users.</p>
                    <form method="post">
                        <div class="mb-3">
                            <select name="backup_file" class="form-select" required>
                                <option value="">Select Backup File</option>
                                <?php foreach ($files as $file): ?>
                                    <option value="<?php echo htmlspecialchars($file['name']); ?>"><?php echo htmlspecialchars($file['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" name="restore_backup" class="btn btn-danger" onclick="return confirm('Restore this database backup now?');">Restore Backup</button>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-primary text-white"><h6 class="mb-0">Backup Folder</h6></div>
                <div class="card-body">
                    <div class="small text-muted mb-2">Stored Path</div>
                    <div class="fw-bold"><?php echo htmlspecialchars(backup_storage_dir()); ?></div>
                    <div class="small text-muted mt-3">Available Files</div>
                    <div class="fw-bold"><?php echo count($files); ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-primary text-white"><h6 class="mb-0">Available Backup Files</h6></div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-bordered table-striped mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>File Name</th>
                            <th>Modified At</th>
                            <th>Size</th>
                            <th style="width:120px;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($files)): ?>
                        <tr><td colspan="4" class="text-center text-muted">No backup files found yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($files as $file): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($file['name']); ?></td>
                            <td><?php echo htmlspecialchars((string) $file['modified_at']); ?></td>
                            <td><?php echo number_format(((float) $file['size']) / 1024, 2); ?> KB</td>
                            <td><a href="database_backup.php?download=<?php echo urlencode($file['name']); ?>" class="btn btn-sm btn-primary">Download</a></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-primary text-white"><h6 class="mb-0">Backup And Restore History</h6></div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-bordered table-striped mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Event</th>
                            <th>File</th>
                            <th>Summary</th>
                            <th>User</th>
                            <th>Time</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php while ($row = mysqli_fetch_assoc($history)) { ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['event_type']); ?></td>
                            <td><?php echo htmlspecialchars((string) $row['record_key']); ?></td>
                            <td><?php echo htmlspecialchars((string) $row['summary']); ?></td>
                            <td><?php echo htmlspecialchars((string) ($row['user_name'] ?? 'System')); ?></td>
                            <td><?php echo htmlspecialchars((string) $row['performed_at']); ?></td>
                        </tr>
                    <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . "/../../includes/footer.php"; ?>
