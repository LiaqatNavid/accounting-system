<?php
session_start();
include "../../includes/auth_check.php";
include "../../config/db.php";
include "../../includes/header.php";
include "../../includes/account_lookup_helper.php";
include "../../includes/audit_helper.php";

/* ===============================
   ADD OPENING BALANCE
================================ */
if (isset($_POST['add_opening'])) {

    $account_no   = mysqli_real_escape_string($conn, $_POST['account_no']);
    $debit        = floatval($_POST['debit']);
    $credit       = floatval($_POST['credit']);
    $balance_date = mysqli_real_escape_string($conn, $_POST['balance_date']);
    $record_date  = date('Y-m-d H:i:s');

    $insert = "INSERT INTO OPENING_BALANCE 
              (ACCOUNT_NO, DEBIT, CREDIT, BALANCE_DATE, RECORD_DATE)
               VALUES 
              ('$account_no', '$debit', '$credit', '$balance_date', '$record_date')";

    if (mysqli_query($conn, $insert)) {
        $message = "<div class='alert alert-success'>Opening balance added successfully.</div>";
    } else {
        $message = "<div class='alert alert-danger'>Error: " . mysqli_error($conn) . "</div>";
    }
}

/* ===============================
   DELETE RECORD
================================ */
if (isset($_GET['delete_id'])) {

    $id = intval($_GET['delete_id']);
    $auditRow = mysqli_fetch_assoc(mysqli_query($conn, "
        SELECT RECORD_ID, ACCOUNT_NO, BALANCE_DATE, DEBIT, CREDIT
        FROM OPENING_BALANCE
        WHERE RECORD_ID = $id
    "));

    if ($auditRow && mysqli_query($conn, "DELETE FROM OPENING_BALANCE WHERE RECORD_ID = $id")) {
        audit_log_delete(
            $conn,
            'opening_balance',
            (string) $id,
            'Opening Balance #' . $id,
            [
                'account_no' => $auditRow['ACCOUNT_NO'] ?? '',
                'balance_date' => $auditRow['BALANCE_DATE'] ?? '',
                'debit' => $auditRow['DEBIT'] ?? 0,
                'credit' => $auditRow['CREDIT'] ?? 0
            ]
        );
        header("Location: openingbalance.php");
        exit();
    } else {
        $message = "<div class='alert alert-danger'>Error deleting record.</div>";
    }
}
?>

<link rel="stylesheet" href="<?php echo $base_url; ?>assets/css/jquery.dataTables.min.css">

<?php $accountLookupRows = get_account_lookup_rows($conn); ?>

<div class="container-fluid mt-4">
    <h3 class="mb-3">Opening Balance</h3>

    <?php if (isset($message)) echo $message; ?>

    <!-- ===================== ADD FORM ===================== -->
    <div class="card shadow mb-4">
        <div class="card-header bg-primary text-white">
            <h6 class="mb-0">Add Opening Balance</h6>
        </div>
        <div class="card-body">

            <?php
            $accounts = mysqli_query($conn,
                "SELECT ACCOUNT_NO, ACCOUNT_DESCRIPTION 
                 FROM ALL_CHART_OF_ACCOUNTS 
                 ORDER BY ACCOUNT_NO ASC");
            ?>

            <form method="POST">
                <div class="row g-2">

                    <div class="col-md-4">
                        <label class="form-label">Account</label>
                        <div class="input-group input-group-sm">
                            <input type="hidden" name="account_no" id="account_no">

                            <input type="text" id="selectedAccount"
                                   class="form-control"
                                   placeholder="Click Search to choose account"
                                   required readonly>

                            <button type="button"
                                    class="btn btn-outline-secondary btn-sm"
                                    id="btnSearchAccount">
                                Search
                            </button>
                        </div>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label">Debit</label>
                        <input type="number" step="0.01"
                               name="debit"
                               class="form-control form-control-sm"
                               value="0">
                    </div>

                    <div class="col-md-2">
                        <label class="form-label">Credit</label>
                        <input type="number" step="0.01"
                               name="credit"
                               class="form-control form-control-sm"
                               value="0">
                    </div>

                    <div class="col-md-2">
                        <label class="form-label">Balance Date</label>
                        <input type="date"
                               name="balance_date"
                               class="form-control form-control-sm"
                               required>
                    </div>
                </div>

                <div class="row mt-2">
                    <div class="col-md-2">
                        <button type="submit"
                                name="add_opening"
                                class="btn btn-primary btn-sm w-100">
                            Add
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- ===================== DATA TABLE ===================== -->
    <div class="card shadow">
        <div class="card-header bg-primary text-white">
            <h6 class="mb-0">All Opening Balances</h6>
        </div>
        <div class="card-body p-2">

            <table id="openingTable"
                   class="table table-sm table-striped table-bordered w-100">

                <thead class="table-primary text-white">
                    <tr>
                        <th>Account No</th>
                        <th>Description</th>
                        <th>Class</th>
                        <th>Status</th>
                        <th>Control Level</th>
                        <th>Debit</th>
                        <th>Credit</th>
                        <th>Balance Date</th>
                        <th style="min-width:120px">Actions</th>
                    </tr>
                </thead>

                <tbody>
                <?php
                $rows = mysqli_query($conn, "
                    SELECT ob.RECORD_ID,
                           ob.ACCOUNT_NO,
                           ob.DEBIT,
                           ob.CREDIT,
                           ob.BALANCE_DATE,
                           a.ACCOUNT_DESCRIPTION,
                           a.CLASS,
                           a.STATUS,
                           a.CONTROL_LEVEL
                    FROM OPENING_BALANCE ob
                    LEFT JOIN ALL_CHART_OF_ACCOUNTS a
                        ON ob.ACCOUNT_NO = a.ACCOUNT_NO
                    ORDER BY ob.BALANCE_DATE DESC
                ");

                while ($r = mysqli_fetch_assoc($rows)) {

                    $recId = intval($r['RECORD_ID']);
                    $accNo = htmlspecialchars($r['ACCOUNT_NO']);
                    $accDesc = htmlspecialchars($r['ACCOUNT_DESCRIPTION']);
                    $class = htmlspecialchars($r['CLASS']);
                    $status = htmlspecialchars($r['STATUS']);
                    $control = htmlspecialchars($r['CONTROL_LEVEL']);
                    $debit = number_format(floatval($r['DEBIT']),2);
                    $credit = number_format(floatval($r['CREDIT']),2);
                    $balance_date = htmlspecialchars($r['BALANCE_DATE']);

                    echo "<tr>
                        <td>{$accNo}</td>
                        <td>{$accDesc}</td>
                        <td>{$class}</td>
                        <td>{$status}</td>
                        <td>{$control}</td>
                        <td>{$debit}</td>
                        <td>{$credit}</td>
                        <td>{$balance_date}</td>
                        <td>
                            <a href='" . $base_url . "admin/openingbalance/edit_opening.php?record_id={$recId}'
                               class='btn btn-sm btn-primary me-1'>Edit</a>

                            <a href='" . $base_url . "admin/openingbalance/openingbalance.php?delete_id={$recId}'
                               class='btn btn-sm btn-danger del-rec'>Delete</a>
                        </td>
                    </tr>";
                }
                ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ===================== ACCOUNT MODAL ===================== -->
<div class="modal fade" id="accountsModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Select Account</h5>
        <button type="button" class="btn-close"
                data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-2">
        <table id="accountsTable"
               class="table table-sm table-striped table-bordered w-100">
            <thead>
                <tr>
                    <th>Account No</th>
                    <th>Description</th>
                    <th>Last Balance</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php
            foreach ($accountLookupRows as $a) {
                $accNo = htmlspecialchars($a['account_no']);
                $accDesc = htmlspecialchars($a['account_description']);
                $lastBalance = number_format((float) $a['last_balance'], 2);
                $statusClass = $a['balance_label'] === 'Payable' ? 'text-danger fw-bold' : ($a['balance_label'] === 'Receivable' ? 'text-success fw-bold' : 'text-muted fw-bold');
                echo "<tr>
                        <td>{$accNo}</td>
                        <td>{$accDesc}</td>
                        <td class='text-end'>{$lastBalance}</td>
                        <td class='{$statusClass}'>" . htmlspecialchars($a['balance_label']) . "</td>
                        <td><button type='button' class='btn btn-sm btn-success select-account' data-no='{$accNo}' data-desc='{$accDesc}'>Select</button></td>
                      </tr>";
            }
            ?>
            </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- ===================== SCRIPTS ===================== -->
<script src="<?php echo $base_url; ?>assets/js/jquery-3.7.0.min.js"></script>
<script src="<?php echo $base_url; ?>assets/js/jquery.dataTables.min.js"></script>

<script>
$(document).ready(function(){

    $('#openingTable').DataTable({
        pageLength: 15,
        columnDefs: [{ orderable: false, targets: 8 }]
    });

    var acctTable = $('#accountsTable').DataTable({
        pageLength: 5
    });

    $('#btnSearchAccount').on('click', function(){
        new bootstrap.Modal(document.getElementById('accountsModal')).show();
    });

    $(document).on('click', '.select-account', function(){
        var acc = $(this).data('no');
        var desc = $(this).data('desc');

        $('#account_no').val(acc);
        $('#selectedAccount').val(acc + ' - ' + desc);

        bootstrap.Modal.getInstance(
            document.getElementById('accountsModal')
        ).hide();
    });

    $(document).on('click', '.del-rec', function(e){
        e.preventDefault();
        if (confirm('Delete this record?')) {
            window.location.href = $(this).attr('href');
        }
    });

});
</script>

<?php include "../../includes/footer.php"; ?>
