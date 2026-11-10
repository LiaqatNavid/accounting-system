<?php
session_start();
include "../../includes/auth_check.php";
include "../../config/db.php";
include "../../includes/header.php";

$account_no = $_GET['account_no'] ?? '';
$from_date  = $_GET['from_date'] ?? '';
$to_date    = $_GET['to_date'] ?? '';

$account_description = '';
$data = [];
$total_debit = 0;
$total_credit = 0;
$opening_balance = 0;

/* ================= BANK ACCOUNT LIST ================= */
$account_list = mysqli_query($conn,"
    SELECT account_no, account_description
    FROM chart_of_account
    WHERE account_no BETWEEN '01-01-02-001' AND '01-01-02-999'
    ORDER BY account_no ASC
");

/* ================= ACCOUNT DESCRIPTION ================= */
if($account_no != ''){
    $acc = mysqli_fetch_assoc(mysqli_query($conn,"
        SELECT account_description 
        FROM chart_of_account
        WHERE account_no='$account_no'
        LIMIT 1
    "));
    $account_description = $acc['account_description'] ?? '';
}

/* ================= OPENING BALANCE ================= */
/* Opening = Opening Table + Transactions before from_date */

if($account_no != '' && $from_date != ''){

    // 1️⃣ Opening Table
    $ob = mysqli_fetch_assoc(mysqli_query($conn,"
        SELECT IFNULL(SUM(debit-credit),0) ob
        FROM ALL_OPENING_BALANCE
        WHERE account_no='$account_no'
    "));
    $opening_balance = $ob['ob'] ?? 0;

    // 2️⃣ Previous Transactions
    $prev = mysqli_fetch_assoc(mysqli_query($conn,"
        SELECT IFNULL(SUM(debit-credit),0) prev
        FROM BANK_BOOK
        WHERE account_no='$account_no'
        AND DATE(record_date) < '$from_date'
    "));
    $opening_balance += $prev['prev'] ?? 0;
}

/* ================= FILTER CONDITION ================= */
$where = " WHERE 1=1 ";

if($account_no != '')
    $where .= " AND account_no='$account_no' ";

if($from_date != '')
    $where .= " AND DATE(record_date) >= '$from_date' ";

if($to_date != '')
    $where .= " AND DATE(record_date) <= '$to_date' ";

/* ================= FETCH BANK BOOK ================= */
$sql = "SELECT * FROM BANK_BOOK
        $where
        ORDER BY DATE(record_date) ASC, voucher_no ASC";

$result = mysqli_query($conn,$sql);

$running = $opening_balance;

/* ================= PREVIOUS BALANCE ROW ================= */
if($account_no != '' && $from_date != ''){

    $data[] = [
        'voucher_no' => 'Previous Balance',
        'record_date' => $from_date,
        'account_no' => $account_no,
        'account_description' => $account_description,
        'remarks' => 'Balance Before '.$from_date,
        'voucher_type' => 'Opening',
        'debit' => ($opening_balance > 0 ? $opening_balance : 0),
        'credit' => ($opening_balance < 0 ? abs($opening_balance) : 0),
        'running_balance' => $opening_balance,
        'balance_type' => ($opening_balance > 0 ? 'Debit' : ($opening_balance < 0 ? 'Credit' : 'Balanced'))
    ];

    if($opening_balance > 0)
        $total_debit += $opening_balance;
    else
        $total_credit += abs($opening_balance);
}

/* ================= LOOP DATA ================= */
while($row = mysqli_fetch_assoc($result)){

    $debit  = $row['debit'] ?? 0;
    $credit = $row['credit'] ?? 0;

    $running += ($debit - $credit);

    $row['running_balance'] = $running;
    $row['account_description'] = $account_description;
    $row['voucher_type'] = $row['voucher_type'] ?? 'Bank';
    $row['balance_type'] = $running > 0 ? 'Debit' : ($running < 0 ? 'Credit' : 'Balanced');

    $total_debit  += $debit;
    $total_credit += $credit;

    $data[] = $row;
}

$closing_balance = $running;
?>

<link rel="stylesheet" href="<?php echo $base_url; ?>assets/css/jquery.dataTables.min.css">
<link rel="stylesheet" href="<?php echo $base_url; ?>assets/css/select2.min.css">

<script src="<?php echo $base_url; ?>assets/js/jquery-3.7.1.min.js"></script>
<script src="<?php echo $base_url; ?>assets/js/jquery.dataTables.min.js"></script>
<script src="<?php echo $base_url; ?>assets/js/select2.min.js"></script>

<div class="container-fluid mt-4">

<h4>Bank Book</h4>

<form method="GET" class="row g-3 mb-3">

<div class="col-md-3">
<label>Bank Account</label>
<select name="account_no" id="account_no" class="form-control select2">
<option value="">Select Account</option>
<?php while($acc = mysqli_fetch_assoc($account_list)){ ?>
<option value="<?= $acc['account_no'] ?>"
data-desc="<?= htmlspecialchars($acc['account_description']) ?>"
<?= ($account_no==$acc['account_no'])?'selected':'' ?>>
<?= $acc['account_no'] ?> - <?= $acc['account_description'] ?>
</option>
<?php } ?>
</select>
</div>

<div class="col-md-3">
<label>Description</label>
<input type="text" id="account_desc" class="form-control"
value="<?= htmlspecialchars($account_description) ?>" readonly>
</div>

<div class="col-md-2">
<label>From</label>
<input type="date" name="from_date" class="form-control" value="<?= $from_date ?>">
</div>

<div class="col-md-2">
<label>To</label>
<input type="date" name="to_date" class="form-control" value="<?= $to_date ?>">
</div>

<div class="col-md-2 align-self-end">

<button class="btn btn-primary w-100 mb-2">Filter</button>

<a href="bank_book.php" class="btn btn-secondary w-100 mb-2">Clear</a>

<a href="bank_book_print.php?account_no=<?= urlencode($account_no) ?>&from_date=<?= urlencode($from_date) ?>&to_date=<?= urlencode($to_date) ?>"
class="btn btn-success w-100"
onclick="return openPrintPopup(this.href)">Print</a>

</div>
</form>

<!-- ================= WIDGETS ================= -->

<div class="row mb-4">

<div class="col-md-3">
<div class="card p-3 text-center bg-info text-white">
<h6>Opening</h6>
<strong><?= number_format($opening_balance,2) ?></strong>
</div>
</div>

<div class="col-md-3">
<div class="card p-3 text-center bg-success text-white">
<h6>Total Debit</h6>
<strong><?= number_format($total_debit,2) ?></strong>
</div>
</div>

<div class="col-md-3">
<div class="card p-3 text-center bg-danger text-white">
<h6>Total Credit</h6>
<strong><?= number_format($total_credit,2) ?></strong>
</div>
</div>

<div class="col-md-3">
<div class="card p-3 text-center bg-primary text-white">
<h6>Closing</h6>
<strong><?= number_format($closing_balance,2) ?></strong>
</div>
</div>

</div>

<table id="bankTable" class="table table-bordered table-striped">
<thead>
<tr>
<th>Date</th>
<th>Voucher</th>
<th>Remarks</th>
<th>Type</th>
<th>Debit</th>
<th>Credit</th>
<th>Running Balance</th>
<th>Status</th>
</tr>
</thead>

<tbody>
<?php foreach($data as $row): ?>
<?php $statusClass = (($row['balance_type'] ?? '') === 'Debit') ? 'text-success fw-bold' : ((($row['balance_type'] ?? '') === 'Credit') ? 'text-danger fw-bold' : 'text-muted fw-bold'); ?>
<tr class="<?= ($row['voucher_no']=='Previous Balance') ? 'table-warning fw-bold' : '' ?>">
<td><?= $row['record_date'] ?></td>
<td><?= $row['voucher_no'] ?></td>
<td><?= htmlspecialchars($row['remarks']) ?></td>
<td><?= htmlspecialchars($row['voucher_type'] ?? 'Bank') ?></td>
<td><?= number_format($row['debit'],2) ?></td>
<td><?= number_format($row['credit'],2) ?></td>
<td><?= number_format($row['running_balance'],2) ?></td>
<td class="<?= $statusClass ?>"><?= htmlspecialchars($row['balance_type'] ?? '') ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>

</div>

<script>
$(document).ready(function(){

$('.select2').select2();

$('#account_no').on('change', function(){
    var desc = $('#account_no option:selected').data('desc');
    $('#account_desc').val(desc ? desc : '');
});

$('#bankTable').DataTable({
    pageLength: 15,
    ordering: false
});

});
</script>

<?php include "../../includes/footer.php"; ?>

