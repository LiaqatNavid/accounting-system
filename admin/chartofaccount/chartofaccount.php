<?php
session_start();
include "../../includes/auth_check.php";
include "../../config/db.php";
include "../../includes/audit_helper.php";

/* ===============================
   TOGGLE ACTIVE / PASSIVE STATUS
================================ */
if (isset($_GET['toggle_status'])) {
    $id = intval($_GET['id'] ?? 0);
    $acc_no = isset($_GET['account_no']) ? mysqli_real_escape_string($conn, trim($_GET['account_no'])) : '';
    $new_status = (isset($_GET['new_status']) && strtoupper($_GET['new_status']) === 'PASSIVE') ? 'PASSIVE' : 'ACTIVE';

    $whereClause = ($id > 0) ? "RECORD_ID = $id" : "ACCOUNT_NO = '$acc_no'";

    if ($id > 0 || $acc_no !== '') {
        $update = mysqli_query($conn, "UPDATE chart_of_account SET ACC_STATUS='$new_status' WHERE $whereClause");
        if ($update) {
            $statusBadge = ($new_status === 'ACTIVE') ? 'Active' : 'Passive';
            $msgType = ($new_status === 'ACTIVE') ? 'success' : 'warning';
            $message = "<div class='alert alert-{$msgType} alert-dismissible fade show d-flex align-items-center gap-2'>
                <i class='bi bi-info-circle-fill'></i> Account <strong>" . htmlspecialchars($acc_no ?: $id) . "</strong> is now <strong>{$statusBadge}</strong>.
                <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
            </div>";
        } else {
            $message = "<div class='alert alert-danger'>Error updating account status: " . mysqli_error($conn) . "</div>";
        }
    }
}

/* ===============================
   ADD ACCOUNT
================================ */
if (isset($_POST['add_account'])) {

    $account_no   = mysqli_real_escape_string($conn, trim($_POST['account_no']));
    $account_desc = mysqli_real_escape_string($conn, trim($_POST['account_description']));
    $class        = mysqli_real_escape_string($conn, trim($_POST['class']));
    $status       = mysqli_real_escape_string($conn, trim($_POST['status']));
    $class_code   = mysqli_real_escape_string($conn, trim($_POST['class_code']));
    $control_level= intval($_POST['control_level']);
    $acc_status   = mysqli_real_escape_string($conn, trim($_POST['acc_status'] ?? 'ACTIVE'));
    $recorddate   = date('Y-m-d H:i:s');

    $check = mysqli_query($conn,
        "SELECT * FROM chart_of_account WHERE ACCOUNT_NO='$account_no'");

    if (mysqli_num_rows($check) > 0) {
        $message = "<div class='alert alert-danger'>Account Number already exists!</div>";
    } else {

        $max_id_res = mysqli_query($conn, "SELECT MAX(RECORD_ID) AS max_id FROM chart_of_account");
        $max_id_row = mysqli_fetch_assoc($max_id_res);
        $next_record_id = intval($max_id_row['max_id'] ?? 0) + 1;

        $insert = "INSERT INTO chart_of_account
                   (RECORD_ID, ACCOUNT_NO, ACCOUNT_DESCRIPTION, CLASS, STATUS,
                    RECORDDATE, CLASS_CODE, CONTROL_LEVEL, ACC_STATUS)
                   VALUES
                   ('$next_record_id', '$account_no','$account_desc','$class','$status',
                    '$recorddate','$class_code','$control_level','$acc_status')";

        if (mysqli_query($conn, $insert)) {
            $message = "<div class='alert alert-success'>Account Added Successfully!</div>";
        } else {
            $message = "<div class='alert alert-danger'>Error: " . mysqli_error($conn) . "</div>";
        }
    }
}

/* ===============================
   DELETE ACCOUNT
================================ */
if (isset($_GET['delete'])) {

    $id = intval($_GET['delete']);
    $del_acc_no = isset($_GET['account_no']) ? mysqli_real_escape_string($conn, $_GET['account_no']) : '';

    $whereClause = ($id > 0) ? "RECORD_ID = $id" : "ACCOUNT_NO = '$del_acc_no'";

    $auditRow = mysqli_fetch_assoc(mysqli_query($conn, "
        SELECT RECORD_ID, ACCOUNT_NO, ACCOUNT_DESCRIPTION, CLASS, ACC_STATUS
        FROM chart_of_account
        WHERE $whereClause
    "));

    mysqli_query($conn, "DELETE FROM chart_of_account WHERE $whereClause");

    if ($auditRow) {
        audit_log_delete(
            $conn,
            'chart_of_account',
            (string) ($auditRow['RECORD_ID'] ?? $id),
            'Account ' . ($auditRow['ACCOUNT_NO'] ?? $id),
            [
                'account_no' => $auditRow['ACCOUNT_NO'] ?? '',
                'account_description' => $auditRow['ACCOUNT_DESCRIPTION'] ?? '',
                'class' => $auditRow['CLASS'] ?? '',
                'acc_status' => $auditRow['ACC_STATUS'] ?? ''
            ]
        );
    }

    header("Location: chartofaccount.php");
    exit();
}

// Fetch Active and Passive stats
$statsRes = mysqli_query($conn, "SELECT 
    COUNT(*) AS total,
    SUM(CASE WHEN UPPER(ACC_STATUS) = 'ACTIVE' THEN 1 ELSE 0 END) AS active_count,
    SUM(CASE WHEN UPPER(ACC_STATUS) = 'PASSIVE' THEN 1 ELSE 0 END) AS passive_count
    FROM chart_of_account");
$stats = mysqli_fetch_assoc($statsRes);
$totalCount = intval($stats['total'] ?? 0);
$activeCount = intval($stats['active_count'] ?? 0);
$passiveCount = intval($stats['passive_count'] ?? 0);

include "../../includes/header.php";
?>

<link rel="stylesheet" href="<?php echo $base_url; ?>assets/css/jquery.dataTables.min.css">

<div class="container-fluid mt-4">
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h3 class="mb-1"><i class="bi bi-journal-text text-primary"></i> Chart of Accounts</h3>
        <div class="text-muted small">Manage account codes, control levels, categories, and active/passive status.</div>
    </div>
</div>

<?php if (isset($message)) echo $message; ?>

<!-- ===================== ADD FORM ===================== -->
<div class="card shadow mb-4">
<div class="card-header bg-primary text-white">
<h5 class="mb-0"><i class="bi bi-plus-circle"></i> Add New Account</h5>
</div>
<div class="card-body">

<form method="POST">

<div class="row g-2 mb-2">
<div class="col-md-2">
<label class="form-label small fw-bold mb-1">Account No</label>
<input type="text" name="account_no"
       class="form-control form-control-sm"
       placeholder="e.g. 01-01-01-001" required>
</div>

<div class="col-md-3">
<label class="form-label small fw-bold mb-1">Description</label>
<input type="text" name="account_description"
       class="form-control form-control-sm"
       placeholder="Account Description" required>
</div>

<div class="col-md-2">
<label class="form-label small fw-bold mb-1">Class</label>
<select name="class" class="form-select form-select-sm" required>
<option value="">Select Class</option>
<option value="Assets">Assets</option>
<option value="Expense">Expense</option>
<option value="Liability">Liability</option>
<option value="Revenue">Revenue</option>
</select>
</div>

<div class="col-md-2">
<label class="form-label small fw-bold mb-1">Status</label>
<select name="status" class="form-select form-select-sm" required>
<option value="">Select Status</option>
<option value="Control">Control</option>
<option value="Detail">Detail</option>
</select>
</div>

<div class="col-md-3">
<label class="form-label small fw-bold mb-1">Class Code</label>
<input type="text" name="class_code"
       class="form-control form-control-sm"
       placeholder="Class Code" required>
</div>
</div>

<div class="row g-2 align-items-end">
<div class="col-md-2">
<label class="form-label small fw-bold mb-1">Control Level</label>
<select name="control_level" class="form-select form-select-sm" required>
<option value="">Select Level</option>
<option value="1">1</option>
<option value="2">2</option>
<option value="3">3</option>
<option value="4">4</option>
</select>
</div>

<div class="col-md-3">
<label class="form-label small fw-bold mb-1">Account Status</label>
<div class="btn-group btn-group-sm w-100" role="group">
    <input type="radio" class="btn-check" name="acc_status" id="add_status_active" value="ACTIVE" checked>
    <label class="btn btn-outline-success" for="add_status_active"><i class="bi bi-check-circle"></i> Active</label>

    <input type="radio" class="btn-check" name="acc_status" id="add_status_passive" value="PASSIVE">
    <label class="btn btn-outline-warning text-dark" for="add_status_passive"><i class="bi bi-pause-circle"></i> Passive</label>
</div>
</div>

<div class="col-md-7">
<button type="submit"
    name="add_account"
    class="btn btn-primary btn-sm w-100">
    <i class="bi bi-plus-lg"></i> Add Account
</button>
</div>
</div>

</form>
</div>
</div>

<!-- ===================== TABLE ===================== -->
<div class="card shadow">
<div class="card-header bg-primary text-white d-flex justify-content-between align-items-center flex-wrap gap-2">
    <h5 class="mb-0"><i class="bi bi-list-ul"></i> Accounts List</h5>
    
    <!-- Active / Passive Filter Buttons -->
    <div class="btn-group btn-group-sm" role="group" aria-label="Status Filter">
        <button type="button" class="btn btn-light active fw-semibold status-filter-btn" data-filter="" title="Show All Accounts">
            <i class="bi bi-collection"></i> All <span class="badge bg-secondary ms-1"><?php echo $totalCount; ?></span>
        </button>
        <button type="button" class="btn btn-outline-light fw-semibold status-filter-btn" data-filter="ACTIVE" title="Show Active Accounts Only">
            <i class="bi bi-check-circle-fill"></i> Active <span class="badge bg-success ms-1"><?php echo $activeCount; ?></span>
        </button>
        <button type="button" class="btn btn-outline-light fw-semibold status-filter-btn" data-filter="PASSIVE" title="Show Passive Accounts Only">
            <i class="bi bi-pause-circle-fill"></i> Passive <span class="badge bg-warning text-dark ms-1"><?php echo $passiveCount; ?></span>
        </button>
    </div>
</div>
<div class="card-body p-2">

<table id="accountsTable"
       class="table table-striped table-hover table-sm table-bordered">

<thead class="table-primary text-white">
<tr>
<th>ID</th>
<th>Account No</th>
<th>Description</th>
<th>Class</th>
<th>Status</th>
<th>Class Code</th>
<th>Level</th>
<th>Acc Status</th>
<th>Date</th>
<th style="min-width:210px; width:210px;">Actions</th>
</tr>
</thead>

<tbody>

<?php
$result = mysqli_query($conn,
    "SELECT * FROM chart_of_account ORDER BY ACCOUNT_NO ASC");

while ($row = mysqli_fetch_assoc($result)) {

    $id = intval($row['RECORD_ID']);
    $record_date = !empty($row['RECORDDATE']) ? date('Y-m-d', strtotime($row['RECORDDATE'])) : '';
    $acc_no = htmlspecialchars($row['ACCOUNT_NO']);
    $acc_desc = htmlspecialchars($row['ACCOUNT_DESCRIPTION']);
    $class = htmlspecialchars($row['CLASS']);
    $status = htmlspecialchars($row['STATUS']);
    $class_code = htmlspecialchars($row['CLASS_CODE']);
    $control = htmlspecialchars($row['CONTROL_LEVEL']);
    $acc_status = strtoupper(trim($row['ACC_STATUS'] ?? 'ACTIVE'));

    $isActive = ($acc_status === 'ACTIVE');
    $badgeClass = $isActive ? 'bg-success' : 'bg-warning text-dark';
    $statusIcon = $isActive ? 'bi-check-circle-fill' : 'bi-pause-circle-fill';

    if ($isActive) {
        $toggleBtn = "<a href='?toggle_status=1&id={$id}&account_no=" . urlencode($row['ACCOUNT_NO']) . "&new_status=PASSIVE'
            class='btn btn-sm btn-outline-warning text-dark me-1'
            onclick=\"return confirm('Set account {$acc_no} to PASSIVE?')\"
            title='Set to Passive'>
            <i class='bi bi-pause-circle'></i> Passive
        </a>";
    } else {
        $toggleBtn = "<a href='?toggle_status=1&id={$id}&account_no=" . urlencode($row['ACCOUNT_NO']) . "&new_status=ACTIVE'
            class='btn btn-sm btn-outline-success me-1'
            onclick=\"return confirm('Set account {$acc_no} to ACTIVE?')\"
            title='Set to Active'>
            <i class='bi bi-play-circle'></i> Active
        </a>";
    }

    echo "<tr>
        <td>{$id}</td>
        <td>{$acc_no}</td>
        <td>{$acc_desc}</td>
        <td><span class='badge bg-info text-dark'>{$class}</span></td>
        <td><span class='badge bg-primary'>{$status}</span></td>
        <td>{$class_code}</td>
        <td>{$control}</td>
        <td><span class='badge {$badgeClass}'><i class='bi {$statusIcon}'></i> {$acc_status}</span></td>
        <td>{$record_date}</td>
        <td style='white-space:nowrap;'>

            <a href='" . $base_url . "admin/chartofaccount/edit_account.php?id={$id}&account_no=" . urlencode($row['ACCOUNT_NO']) . "'
               class='btn btn-sm btn-primary me-1' title='Edit Account'>
               <i class='bi bi-pencil-square'></i> Edit
            </a>

            {$toggleBtn}

            <a href='" . $base_url . "admin/chartofaccount/chartofaccount.php?delete={$id}&account_no=" . urlencode($row['ACCOUNT_NO']) . "'
                class='btn btn-sm btn-danger'
                onclick=\"return confirm('Are you sure you want to delete account {$acc_no}?')\"
                title='Delete Account'>
                <i class='bi bi-trash'></i>
            </a>

        </td>
    </tr>";
}
?>
</tbody>
</table>
</div>
</div>
</div>

<script src="<?php echo $base_url; ?>assets/js/jquery-3.7.0.min.js"></script>
<script src="<?php echo $base_url; ?>assets/js/jquery.dataTables.min.js"></script>

<script>
$(document).ready(function() {
    var table = $('#accountsTable').DataTable({
        pageLength: 15,
        columnDefs: [{ orderable: false, targets: 9 }]
    });

    // Active / Passive Filter Buttons
    $('.status-filter-btn').on('click', function() {
        $('.status-filter-btn').removeClass('btn-light active').addClass('btn-outline-light');
        $(this).removeClass('btn-outline-light').addClass('btn-light active');

        var filterValue = $(this).data('filter');
        if (filterValue === '') {
            table.column(7).search('').draw();
        } else {
            table.column(7).search(filterValue).draw();
        }
    });
});
</script>

<?php include "../../includes/footer.php"; ?>
