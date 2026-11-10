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

/* ================= ACCOUNT LIST (Same as Ledger Page) ================= */
$account_list = mysqli_query($conn,"
    SELECT account_no, account_description
    FROM chart_of_account
    ORDER BY account_no ASC
");

if(!$account_list){
    die("Account List Query Error: " . mysqli_error($conn));
}

/* ================= GET ACCOUNT DESCRIPTION ================= */
if($account_no != ''){
    $acc_query = mysqli_query($conn,"
        SELECT account_description 
        FROM chart_of_account
        WHERE account_no='$account_no'
        LIMIT 1
    ");

    if($acc_query && mysqli_num_rows($acc_query) > 0){
        $acc_data = mysqli_fetch_assoc($acc_query);
        $account_description = $acc_data['account_description'] ?? '';
    }
}

/* ================= OPENING BALANCE ================= */
if($account_no != '' && $from_date != ''){
    $ob_query = mysqli_query($conn,"
        SELECT IFNULL(SUM(debit - credit),0) AS opening
        FROM cash_book
        WHERE account_no='$account_no'
        AND DATE(record_date) < '$from_date'
    ");

    if($ob_query){
        $ob_data = mysqli_fetch_assoc($ob_query);
        $opening_balance = $ob_data['opening'] ?? 0;
    }
}

/* ================= WHERE CONDITION ================= */
$where = " WHERE 1=1 ";

if($account_no != ''){
    $where .= " AND account_no='$account_no' ";
}

if($from_date != ''){
    $where .= " AND DATE(record_date) >= '$from_date' ";
}

if($to_date != ''){
    $where .= " AND DATE(record_date) <= '$to_date' ";
}

/* ================= FETCH CASH BOOK DATA ================= */
$sql = "SELECT * FROM cash_book
        $where
        ORDER BY DATE(record_date) ASC, voucher_no ASC";

$result = mysqli_query($conn,$sql);

if(!$result){
    die("Cash Book Query Error: " . mysqli_error($conn));
}

$running = $opening_balance;

/* ================= ADD PREVIOUS BALANCE ROW ================= */
if($account_no != '' && $from_date != ''){

    $prev_row = [
        'print_voucher' => 'Previous Balance',
        'record_date' => $from_date,
        'account_no' => $account_no,
        'account_description' => $account_description,
        'remarks' => 'Balance Before ' . $from_date,
        'voucher_type' => 'Opening',
        'debit' => ($opening_balance > 0 ? $opening_balance : 0),
        'credit' => ($opening_balance < 0 ? abs($opening_balance) : 0),
        'running_balance' => $opening_balance,
        'balance_type' => ($opening_balance > 0 ? 'Debit' : ($opening_balance < 0 ? 'Credit' : 'Balanced'))
    ];

    if($opening_balance > 0){
        $total_debit += $opening_balance;
    } elseif($opening_balance < 0){
        $total_credit += abs($opening_balance);
    }

    $data[] = $prev_row;
}

/* ================= LOOP DATA ================= */
while($row = mysqli_fetch_assoc($result)){

    $debit = $row['debit'] ?? 0;
    $credit = $row['credit'] ?? 0;

    $running += ($debit - $credit);

    $row['running_balance'] = $running;
    $row['voucher_type'] = $row['voucher_type'] ?? 'Cash';
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

<h4>Cash Book</h4>

<form method="GET" class="row g-3 mb-3">

<div class="col-md-3">
    <label>Account</label>
    <select name="account_no" id="account_no" class="form-control select2">
        <option value="">Select Account</option>
        <?php while($account_row = mysqli_fetch_assoc($account_list)){ ?>
        <option value="<?= $account_row['account_no'] ?>"
            data-desc="<?= htmlspecialchars($account_row['account_description']) ?>"
            <?= ($account_no == $account_row['account_no']) ? 'selected' : '' ?>>
            <?= $account_row['account_no'] ?> - <?= $account_row['account_description'] ?>
        </option>
        <?php } ?>
    </select>
</div>

<div class="col-md-3">
    <label>Description</label>
    <input type="text" id="account_desc"
           class="form-control"
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

    <a href="cash_book.php" 
       class="btn btn-secondary w-100 mb-2">
       Clear
    </a>

    <a href="cash_book_print.php?account_no=<?= urlencode($account_no ?? '') ?>&from_date=<?= urlencode($from_date ?? '') ?>&to_date=<?= urlencode($to_date ?? '') ?>"
   class="btn btn-success w-100"
   onclick="return openPrintPopup(this.href)">
   Print
    </a>

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

<table id="cashTable" class="table table-bordered table-striped">
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
<tr class="<?= (($row['print_voucher'] ?? '') == 'Previous Balance') ? 'table-warning fw-bold' : '' ?>">
    <td><?= $row['record_date'] ?? '' ?></td>
    <td><?= $row['print_voucher'] ?? '' ?></td>
    <td><?= htmlspecialchars($row['remarks'] ?? '') ?></td>
    <td><?= htmlspecialchars($row['voucher_type'] ?? 'Cash') ?></td>
    <td><?= number_format($row['debit'] ?? 0,2) ?></td>
    <td><?= number_format($row['credit'] ?? 0,2) ?></td>
    <td><?= number_format($row['running_balance'] ?? 0,2) ?></td>
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

$('#cashTable').DataTable({
    pageLength: 15,
    ordering: false
});

});
</script>

<?php include "../../includes/footer.php"; ?>

