<?php
session_start();
include "../includes/auth_check.php";
include "../config/db.php";
include "../includes/audit_helper.php";
include "../includes/history_retention_helper.php";

ensure_audit_schema($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_system_activity'])) {
    if (!rbac_is_admin()) {
        header("Location: system_activity.php?denied=1");
        exit;
    }

    $result = history_retention_manual_clear(
        $conn,
        'system_activity',
        "a.event_type NOT IN ('approve', 'reopen')",
        'System activity manually cleared after archive'
    );

    $redirectFlag = ($result['deleted_rows'] ?? 0) > 0 ? 'cleared=1' : 'empty=1';
    header("Location: system_activity.php?{$redirectFlag}");
    exit;
}

$filter = trim((string) ($_GET['event_type'] ?? ''));
$safeFilter = mysqli_real_escape_string($conn, $filter);
$where = $filter !== '' ? "WHERE a.event_type = '$safeFilter'" : "";

$rows = mysqli_query($conn, "
    SELECT a.*, u.username AS user_name
    FROM audit_activity_log a
    LEFT JOIN users u ON u.id = a.performed_by
    " . ($filter !== '' ? "$where AND a.event_type NOT IN ('approve', 'reopen')" : "WHERE a.event_type NOT IN ('approve', 'reopen')") . "
    ORDER BY a.performed_at DESC
    LIMIT 200
");

$eventTypes = mysqli_query($conn, "SELECT DISTINCT event_type FROM audit_activity_log WHERE event_type NOT IN ('approve', 'reopen') ORDER BY event_type");
include "../includes/header.php";
?>

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div>
            <h3 class="mb-1">System Activity</h3>
            <div class="text-muted">Track edit, restore, backup, and cleanup events across the software.</div>
        </div>
        <?php if (rbac_is_admin()): ?>
        <form method="post" onsubmit="return confirm('Archive and clear all system activity records now?');">
            <button type="submit" name="clear_system_activity" class="btn btn-danger">Clear System Activity</button>
        </form>
        <?php endif; ?>
    </div>

    <?php if (isset($_GET['cleared']) && $_GET['cleared'] == '1'): ?>
    <div class="alert alert-warning">System activity was archived to a text file and then cleared.</div>
    <?php elseif (isset($_GET['empty']) && $_GET['empty'] == '1'): ?>
    <div class="alert alert-info">No system activity records were available to clear.</div>
    <?php endif; ?>

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-primary text-white"><h6 class="mb-0">Filters</h6></div>
        <div class="card-body">
            <form class="row g-3">
                <div class="col-md-4">
                    <label class="form-label fw-bold">Event Type</label>
                    <select name="event_type" class="form-select">
                        <option value="">All Events</option>
                        <?php while ($type = mysqli_fetch_assoc($eventTypes)) { ?>
                            <option value="<?php echo htmlspecialchars($type['event_type']); ?>" <?php echo $filter === $type['event_type'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($type['event_type']); ?>
                            </option>
                        <?php } ?>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button class="btn btn-primary w-100">Apply</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-primary text-white"><h6 class="mb-0">Recent Activity</h6></div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-bordered table-striped mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Event</th>
                            <th>Module</th>
                            <th>Record</th>
                            <th>Summary</th>
                            <th>User</th>
                            <th>Time</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php while ($row = mysqli_fetch_assoc($rows)) { ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['event_type']); ?></td>
                            <td><?php echo htmlspecialchars($row['module_key']); ?></td>
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

<?php include "../includes/footer.php"; ?>
