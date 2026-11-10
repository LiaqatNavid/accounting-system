<?php
session_start();
include "../includes/auth_check.php";
include "../config/db.php";
include "../includes/period_lock_helper.php";
include "../includes/audit_helper.php";

period_lock_ensure_schema($conn);
ensure_audit_schema($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fromDate = mysqli_real_escape_string($conn, $_POST['from_date'] ?? '');
    $toDate = mysqli_real_escape_string($conn, $_POST['to_date'] ?? '');
    $remarks = mysqli_real_escape_string($conn, $_POST['remarks'] ?? '');
    $createdBy = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 'NULL';

    mysqli_query($conn, "
        INSERT INTO accounting_period_locks (from_date, to_date, remarks, created_by)
        VALUES ('$fromDate', '$toDate', '$remarks', $createdBy)
    ");
    $lockId = mysqli_insert_id($conn);
    audit_log_event($conn, 'period_lock_add', 'period_closing', (string) $lockId, 'Accounting period lock created', [], [], [
        'from_date' => $fromDate,
        'to_date' => $toDate,
        'remarks' => $remarks,
    ]);

    header("Location: period_closing.php?saved=1");
    exit;
}

if (isset($_GET['toggle'])) {
    $id = (int) $_GET['toggle'];
    mysqli_query($conn, "
        UPDATE accounting_period_locks
        SET is_active = CASE WHEN is_active = 1 THEN 0 ELSE 1 END
        WHERE id = $id
    ");
    $row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT from_date, to_date, is_active FROM accounting_period_locks WHERE id = $id"));
    audit_log_event($conn, 'period_lock_toggle', 'period_closing', (string) $id, 'Accounting period lock status changed', [], $row ?: []);
    header("Location: period_closing.php?updated=1");
    exit;
}

$locks = mysqli_query($conn, "SELECT * FROM accounting_period_locks ORDER BY to_date DESC, id DESC");
include "../includes/header.php";
?>

<div class="container-fluid mt-4">
    <div class="mb-3">
        <h3 class="mb-1">Period Closing</h3>
        <div class="text-muted">Block save, update, and delete activity for closed accounting date ranges.</div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-primary text-white"><h6 class="mb-0">Close Period</h6></div>
        <div class="card-body">
            <form method="post">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label fw-bold">From Date</label>
                        <input type="date" name="from_date" class="form-control" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold">To Date</label>
                        <input type="date" name="to_date" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Remarks</label>
                        <input type="text" name="remarks" class="form-control" placeholder="Month closed">
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">Save</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-primary text-white"><h6 class="mb-0">Closed Periods</h6></div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-striped mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>From</th>
                            <th>To</th>
                            <th>Remarks</th>
                            <th>Status</th>
                            <th style="width:120px;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php while ($row = mysqli_fetch_assoc($locks)) { ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['from_date']); ?></td>
                            <td><?php echo htmlspecialchars($row['to_date']); ?></td>
                            <td><?php echo htmlspecialchars($row['remarks']); ?></td>
                            <td>
                                <span class="badge <?php echo (int) $row['is_active'] === 1 ? 'bg-danger' : 'bg-secondary'; ?>">
                                    <?php echo (int) $row['is_active'] === 1 ? 'Locked' : 'Inactive'; ?>
                                </span>
                            </td>
                            <td>
                                <a href="period_closing.php?toggle=<?php echo (int) $row['id']; ?>" class="btn btn-sm btn-warning">
                                    <?php echo (int) $row['is_active'] === 1 ? 'Unlock' : 'Activate'; ?>
                                </a>
                            </td>
                        </tr>
                    <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include "../includes/footer.php"; ?>
