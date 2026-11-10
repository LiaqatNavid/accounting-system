<?php
session_start();
include "../../includes/auth_check.php";
include "../../config/db.php";

if (!isset($_GET['record_id'])) {
    header("Location: openingbalance.php");
    exit();
}

$id = intval($_GET['record_id']);

$query = "
    SELECT ob.*, a.ACCOUNT_DESCRIPTION
    FROM OPENING_BALANCE ob
    LEFT JOIN ALL_CHART_OF_ACCOUNTS a 
        ON ob.ACCOUNT_NO = a.ACCOUNT_NO
    WHERE ob.RECORD_ID = $id
    LIMIT 1
";

$result = mysqli_query($conn, $query);

if (!$result || mysqli_num_rows($result) == 0) {
    include "../../includes/header.php";
    echo "<div class='container mt-5'>
            <div class='alert alert-danger'>Record not found.</div>
            <a href='openingbalance.php' class='btn btn-secondary'>Back</a>
          </div>";
    include "../../includes/footer.php";
    exit();
}

$row = mysqli_fetch_assoc($result);

if (isset($_POST['update_opening'])) {

    $debit        = floatval($_POST['debit']);
    $credit       = floatval($_POST['credit']);
    $balance_date = mysqli_real_escape_string($conn, $_POST['balance_date']);

    $update = "
        UPDATE OPENING_BALANCE
        SET DEBIT = '$debit',
            CREDIT = '$credit',
            BALANCE_DATE = '$balance_date'
        WHERE RECORD_ID = $id
    ";

    if (mysqli_query($conn, $update)) {
        $message = "<div class='alert alert-success'>Updated successfully.</div>";
        $result = mysqli_query($conn, $query);
        $row = mysqli_fetch_assoc($result);
    } else {
        $message = "<div class='alert alert-danger'>Error: " . mysqli_error($conn) . "</div>";
    }
}

include "../../includes/header.php";
?>

<div class="container mt-4">
    <div class="card shadow">
        <div class="card-header bg-primary text-white">
            <h5>Edit Opening Balance</h5>
        </div>
        <div class="card-body">

            <?php if (isset($message)) echo $message; ?>

            <form method="POST">

                <div class="mb-3">
                    <label class="form-label">Account</label>
                    <input type="text" 
                           class="form-control" 
                           value="<?php echo htmlspecialchars($row['ACCOUNT_NO'] . ' - ' . $row['ACCOUNT_DESCRIPTION']); ?>" 
                           readonly>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Debit</label>
                        <input type="number" 
                               step="0.01" 
                               name="debit" 
                               class="form-control" 
                               value="<?php echo htmlspecialchars($row['DEBIT']); ?>">
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Credit</label>
                        <input type="number" 
                               step="0.01" 
                               name="credit" 
                               class="form-control" 
                               value="<?php echo htmlspecialchars($row['CREDIT']); ?>">
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Balance Date</label>
                    <input type="date" 
                           name="balance_date" 
                           class="form-control" 
                           value="<?php echo htmlspecialchars($row['BALANCE_DATE']); ?>" 
                           required>
                </div>

                <button type="submit" 
                        name="update_opening" 
                        class="btn btn-primary">
                    Update
                </button>

                <a href="openingbalance.php" 
                   class="btn btn-secondary">
                    Back
                </a>

            </form>

        </div>
    </div>
</div>

<?php include "../../includes/footer.php"; ?>
