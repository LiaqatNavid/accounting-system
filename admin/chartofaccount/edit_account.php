<?php
session_start();
include "../../includes/auth_check.php";
include "../../config/db.php";

$id = isset($_GET['id']) ? trim($_GET['id']) : '';
$acc_no_param = isset($_GET['account_no']) ? trim($_GET['account_no']) : '';

if ($id === '' && $acc_no_param === '') {
    header("Location: chartofaccount.php");
    exit();
}

$account = null;
if ($acc_no_param !== '') {
    $safe_acc_no = mysqli_real_escape_string($conn, $acc_no_param);
    $account = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM chart_of_account WHERE ACCOUNT_NO='$safe_acc_no' LIMIT 1"));
}

if (!$account && $id !== '') {
    $safe_id = mysqli_real_escape_string($conn, $id);
    if (is_numeric($safe_id) && intval($safe_id) > 0) {
        $account = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM chart_of_account WHERE RECORD_ID='$safe_id' LIMIT 1"));
    }
    if (!$account) {
        $account = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM chart_of_account WHERE ACCOUNT_NO='$safe_id' LIMIT 1"));
    }
}

if (!$account) {
    header("Location: chartofaccount.php");
    exit();
}

// UPDATE ACCOUNT LOGIC
if (isset($_POST['update_account'])) {
    $account_desc  = mysqli_real_escape_string($conn, trim($_POST['account_description']));
    $class         = mysqli_real_escape_string($conn, trim($_POST['class']));
    $status        = mysqli_real_escape_string($conn, trim($_POST['status']));
    $class_code    = mysqli_real_escape_string($conn, trim($_POST['class_code']));
    $control_level = intval($_POST['control_level']);
    $acc_status    = mysqli_real_escape_string($conn, trim($_POST['acc_status']));
    $target_acc_no = mysqli_real_escape_string($conn, $account['ACCOUNT_NO']);

    $update = "UPDATE chart_of_account SET 
               ACCOUNT_DESCRIPTION='$account_desc', 
               CLASS='$class', 
               STATUS='$status', 
               CLASS_CODE='$class_code', 
               CONTROL_LEVEL='$control_level', 
               ACC_STATUS='$acc_status' 
               WHERE ACCOUNT_NO='$target_acc_no'";

    if (mysqli_query($conn, $update)) {
        $message = "<div class='alert alert-success d-flex align-items-center gap-2'><i class='bi bi-check-circle-fill'></i> Account Updated Successfully!</div>";
        $account['ACCOUNT_DESCRIPTION'] = $account_desc;
        $account['CLASS'] = $class;
        $account['STATUS'] = $status;
        $account['CLASS_CODE'] = $class_code;
        $account['CONTROL_LEVEL'] = $control_level;
        $account['ACC_STATUS'] = $acc_status;
    } else {
        $message = "<div class='alert alert-danger'>Error: " . mysqli_error($conn) . "</div>";
    }
}

include "../../includes/header.php";
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-pencil-square"></i> Edit Account: <?php echo htmlspecialchars($account['ACCOUNT_NO']); ?></h5>
                    <a href="chartofaccount.php" class="btn btn-sm btn-light text-primary fw-semibold"><i class="bi bi-arrow-left"></i> Back to Accounts</a>
                </div>
                <div class="card-body p-4">
                    
                    <?php if (isset($message)) echo $message; ?>

                    <form method="POST">
                        <input type="hidden" name="account_no" value="<?php echo htmlspecialchars($account['ACCOUNT_NO']); ?>">

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Account No (Read Only)</label>
                                    <input type="text" class="form-control bg-light" value="<?php echo htmlspecialchars($account['ACCOUNT_NO']); ?>" readonly>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Description</label>
                                    <input type="text" name="account_description" class="form-control" value="<?php echo htmlspecialchars($account['ACCOUNT_DESCRIPTION']); ?>" required>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Class</label>
                                    <select name="class" class="form-select" required>
                                        <option value="Assets" <?php echo ($account['CLASS'] == 'Assets') ? 'selected' : ''; ?>>Assets</option>
                                        <option value="Expense" <?php echo ($account['CLASS'] == 'Expense') ? 'selected' : ''; ?>>Expense</option>
                                        <option value="Liability" <?php echo ($account['CLASS'] == 'Liability') ? 'selected' : ''; ?>>Liability</option>
                                        <option value="Revenue" <?php echo ($account['CLASS'] == 'Revenue') ? 'selected' : ''; ?>>Revenue</option>
                                    </select>
                                </div>
                            </div>

                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Status</label>
                                    <select name="status" class="form-select" required>
                                        <option value="Control" <?php echo ($account['STATUS'] == 'Control') ? 'selected' : ''; ?>>Control</option>
                                        <option value="Detail" <?php echo ($account['STATUS'] == 'Detail') ? 'selected' : ''; ?>>Detail</option>
                                    </select>
                                </div>
                            </div>

                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Class Code</label>
                                    <input type="text" name="class_code" class="form-control" value="<?php echo htmlspecialchars($account['CLASS_CODE']); ?>" required>
                                </div>
                            </div>

                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Control Level</label>
                                    <select name="control_level" class="form-select" required>
                                        <option value="1" <?php echo ($account['CONTROL_LEVEL'] == '1') ? 'selected' : ''; ?>>1</option>
                                        <option value="2" <?php echo ($account['CONTROL_LEVEL'] == '2') ? 'selected' : ''; ?>>2</option>
                                        <option value="3" <?php echo ($account['CONTROL_LEVEL'] == '3') ? 'selected' : ''; ?>>3</option>
                                        <option value="4" <?php echo ($account['CONTROL_LEVEL'] == '4') ? 'selected' : ''; ?>>4</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Account Status</label>
                                    <select name="acc_status" class="form-select" required>
                                        <option value="ACTIVE" <?php echo ($account['ACC_STATUS'] == 'ACTIVE') ? 'selected' : ''; ?>>ACTIVE</option>
                                        <option value="PASSIVE" <?php echo ($account['ACC_STATUS'] == 'PASSIVE') ? 'selected' : ''; ?>>PASSIVE</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex gap-2 mt-3">
                            <button type="submit" name="update_account" class="btn btn-primary px-4">
                                <i class="bi bi-save"></i> Save Changes
                            </button>
                            <a href="chartofaccount.php" class="btn btn-secondary px-4">Cancel</a>
                        </div>
                    </form>

                </div>
            </div>
        </div>
    </div>
</div>

<?php include "../../includes/footer.php"; ?>
