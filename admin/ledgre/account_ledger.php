<?php
session_start();
include "../../includes/auth_check.php";
include "../../config/db.php";
include "../../includes/header.php";

$account_no = $_GET['account_no'] ?? '';
$from_date  = $_GET['from_date'] ?? '';
$to_date    = $_GET['to_date'] ?? '';

$data = [];
$opening_balance = 0;

$total_debit  = 0;
$total_credit = 0;
$total_payable = 0;
$total_receivable = 0;

/* ================= LOAD ACCOUNT LIST ================= */
$account_list = mysqli_query($conn,"
    SELECT ACCOUNT_NO as account_no,
           ACCOUNT_DESCRIPTION as account_description 
    FROM all_chart_of_accounts
    ORDER BY account_description ASC
");

/* ================= OPENING BALANCE (ONLY IF SINGLE ACCOUNT) ================= */
if($account_no && $from_date){

    $ob_q = mysqli_query($conn,"
        SELECT IFNULL(SUM(credit - debit),0) AS opening
        FROM Ind_ledgre_running_balance
        WHERE account_no='$account_no'
        AND DATE(record_date) < '$from_date'
    ");

    if($ob_q){
        $ob = mysqli_fetch_assoc($ob_q);
        $opening_balance = $ob['opening'] ?? 0;
    }
}

/* ================= BUILD WHERE ================= */
$where = " WHERE 1=1 ";

if($account_no){
    $where .= " AND account_no='$account_no' ";
}

if($from_date){
    $where .= " AND DATE(record_date) >= '$from_date' ";
}

if($to_date){
    $where .= " AND DATE(record_date) <= '$to_date' ";
}

/* ================= FETCH DATA ================= */
$sql = "SELECT * FROM ind_ledgre_running_balance
        $where
        ORDER BY account_no ASC, DATE(record_date) ASC, voucher_print ASC";

$result = mysqli_query($conn,$sql);


/* ================= PREVIOUS BALANCE ROW (ONLY SINGLE ACCOUNT) ================= */
if($account_no && $from_date){

    $data[] = [
        'account_no' => $account_no,
        'account_description' => '',
        'voucher_print' => 'Previous Balance',
        'record_date' => $from_date,
        'voucher_type' => 'Opening',
        'debit' => 0,
        'credit' => 0,
        'running_balance' => $opening_balance,
        'balance_type' => ($opening_balance > 0 ? 'Credit' : ($opening_balance < 0 ? 'Debit' : 'Balanced')),
        'remarks' => 'Opening before selected date'
    ];
}


/* ================= LOOP DATA ================= */

$last_account = '';
$running = 0;

if($result){
    while($row = mysqli_fetch_assoc($result)){

        $row['debit']  = $row['debit'] ?? 0;
        $row['credit'] = $row['credit'] ?? 0;

        /* ===== RESET RUNNING WHEN ACCOUNT CHANGES ===== */
        if($last_account != $row['account_no']){

            if($account_no){
                $running = $opening_balance;
            } else {
                $running = 0;
            }

            $last_account = $row['account_no'];
        }

        $running += ($row['credit'] - $row['debit']);
        $row['running_balance'] = $running;
        $row['voucher_type'] = $row['voucher_type'] ?? 'Ledger';

        $total_debit  += $row['debit'];
        $total_credit += $row['credit'];

        $row['balance_type'] = (
            $running > 0 ? 'Credit' :
            ($running < 0 ? 'Debit' : 'Balanced')
        );

        $data[] = $row;
    }
}

$closing_balance = $running;

if($closing_balance > 0){
    $total_payable = $closing_balance;
} elseif($closing_balance < 0){
    $total_receivable = abs($closing_balance);
}
?>

<link rel="stylesheet" href="<?php echo $base_url; ?>assets/css/jquery.dataTables.min.css">
<link rel="stylesheet" href="<?php echo $base_url; ?>assets/css/select2.min.css">

<script src="<?php echo $base_url; ?>assets/js/jquery-3.7.1.min.js"></script>
<script src="<?php echo $base_url; ?>assets/js/jquery.dataTables.min.js"></script>
<script src="<?php echo $base_url; ?>assets/js/select2.min.js"></script>

<div class="container-fluid mt-4">

<h4>Account Ledger View</h4>

<form method="GET" class="row g-3 mb-3">

<div class="col-md-4">
<label>Account</label>
<select name="account_no" class="form-control select2">
    <option value="">All Accounts</option>

    <?php
    if($account_list && mysqli_num_rows($account_list) > 0){
        while($acc = mysqli_fetch_assoc($account_list)){

            $acc_no  = $acc['account_no'] ?? '';
            $acc_desc = $acc['account_description'] ?? '';

            $selected = ($account_no == $acc_no) ? 'selected' : '';
    ?>
        <option value="<?= htmlspecialchars($acc_no) ?>" <?= $selected ?>>
            <?= htmlspecialchars($acc_no) ?> - <?= htmlspecialchars($acc_desc) ?>
        </option>
    <?php 
        }
    }
    ?>
</select>
</div>

<div class="col-md-3">
<label>From Date</label>
<input type="date" name="from_date" class="form-control" value="<?= $from_date ?>">
</div>

<div class="col-md-3">
<label>To Date</label>
<input type="date" name="to_date" class="form-control" value="<?= $to_date ?>">
</div>

<div class="col-md-2 align-self-end">
<button class="btn btn-primary w-100 mb-2">Filter</button>
<a href="account_ledger.php" class="btn btn-secondary w-100 mb-2">Clear</a>
<a href="account_ledger_print.php?account_no=<?= $account_no ?>&from_date=<?= $from_date ?>&to_date=<?= $to_date ?>"
class="btn btn-success w-100"
onclick="return openPrintPopup(this.href)">Print</a>
</div>

</form>

<!-- ================= SUMMARY WIDGETS ================= -->

<div class="row mb-4">

<div class="col-md-2">
<div class="card p-3 text-center bg-danger text-white">
<h6>Total Debit</h6>
<strong><?= number_format($total_debit,2) ?></strong>
</div></div>

<div class="col-md-2">
<div class="card p-3 text-center bg-success text-white">
<h6>Total Credit</h6>
<strong><?= number_format($total_credit,2) ?></strong>
</div></div>

<div class="col-md-2">
<div class="card p-3 text-center bg-primary text-white">
<h6>Closing Balance</h6>
<strong><?= number_format($closing_balance,2) ?></strong>
</div></div>

<div class="col-md-3">
<div class="card p-3 text-center bg-danger text-white">
<h6>Total Payable</h6>
<strong><?= number_format($total_payable,2) ?></strong>
</div></div>

<div class="col-md-3">
<div class="card p-3 text-center bg-success text-white">
<h6>Total Receivable</h6>
<strong><?= number_format($total_receivable,2) ?></strong>
</div></div>

</div>

<!-- ================= TABLE ================= -->

<table id="ledgerTable" class="table table-bordered table-striped">
<thead>
<tr>
<th>Date</th>
<th>Voucher</th>
<th>Remarks</th>
<th>Account No</th>
<th>Description</th>
<th>Debit</th>
<th>Credit</th>
<th>Running Balance</th>
<th>Status</th>
</tr>
</thead>
<tbody>

<?php foreach($data as $row): 
$status_class = '';
if(($row['balance_type'] ?? '') == 'Payable') $status_class = 'text-danger fw-bold';
if(($row['balance_type'] ?? '') == 'Receivable') $status_class = 'text-success fw-bold';
?>

<tr class="<?= (($row['voucher_print'] ?? '')=='Previous Balance') ? 'table-warning fw-bold' : '' ?>">
<td><?= $row['record_date'] ?? '' ?></td>
<td><?= htmlspecialchars($row['voucher_print'] ?? '') ?></td>
<td><?= htmlspecialchars($row['remarks'] ?? '') ?></td>
<td><?= htmlspecialchars($row['account_no'] ?? '') ?></td>
<td><?= htmlspecialchars($row['account_description'] ?? '') ?></td>
<td><?= number_format($row['debit'] ?? 0,2) ?></td>
<td><?= number_format($row['credit'] ?? 0,2) ?></td>
<td><?= number_format($row['running_balance'] ?? 0,2) ?></td>
<td class="<?= $status_class ?>"><?= htmlspecialchars($row['balance_type'] ?? '') ?></td>
</tr>

<?php endforeach; ?>

</tbody>
</table>

</div>

<script>
$(document).ready(function(){
$('.select2').select2();
$('#ledgerTable').DataTable({
pageLength: 10,
ordering: false
});
});
</script>

<?php include "../../includes/footer.php"; ?>

