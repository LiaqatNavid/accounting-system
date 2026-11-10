<?php
session_start();
include "../includes/auth_check.php";
include "../config/db.php";
include "../includes/audit_helper.php";
include "../includes/history_retention_helper.php";

ensure_audit_schema($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_approval_history'])) {
    if (!rbac_is_admin()) {
        header("Location: approval_history.php?denied=1");
        exit;
    }

    $result = history_retention_manual_clear(
        $conn,
        'approval_history',
        "a.event_type IN ('approve', 'reopen')",
        'Approval history manually cleared after archive'
    );

    $redirectFlag = ($result['deleted_rows'] ?? 0) > 0 ? 'cleared=1' : 'empty=1';
    header("Location: approval_history.php?{$redirectFlag}");
    exit;
}

$rows = mysqli_query($conn, "
    SELECT a.*, u.username AS user_name
    FROM audit_activity_log a
    LEFT JOIN users u ON u.id = a.performed_by
    WHERE a.event_type IN ('approve', 'reopen')
    ORDER BY a.performed_at DESC
    LIMIT 200
");

include "../includes/header.php";
?>

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div>
            <h3 class="mb-1">Approval History</h3>
            <div class="text-muted">Review who approved or reopened vouchers, and clear them only when an admin decides.</div>
        </div>
        <?php if (rbac_is_admin()): ?>
        <form method="post" onsubmit="return confirm('Archive and clear all approval history records now?');">
            <button type="submit" name="clear_approval_history" class="btn btn-danger">Clear Approval History</button>
        </form>
        <?php endif; ?>
    </div>

    <?php if (isset($_GET['cleared']) && $_GET['cleared'] == '1'): ?>
    <div class="alert alert-warning">Approval history was archived to a text file and then cleared.</div>
    <?php elseif (isset($_GET['empty']) && $_GET['empty'] == '1'): ?>
    <div class="alert alert-info">No approval history records were available to clear.</div>
    <?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-header bg-primary text-white"><h6 class="mb-0">Voucher Approval Trail</h6></div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-bordered table-striped mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Action</th>
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
                            <td><?php echo htmlspecialchars(ucfirst($row['event_type'])); ?></td>
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
