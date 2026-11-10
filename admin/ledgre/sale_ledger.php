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

/* ================= LOAD ACCOUNT LIST ================= */
$account_list = mysqli_query($conn,"
    SELECT account_no, account_description
    FROM chart_of_account
    ORDER BY account_no ASC
");

/* ================= GET ACCOUNT DESCRIPTION ================= */
if($account_no != ''){
    $acc_q = mysqli_query($conn,"
        SELECT account_description 
        FROM chart_of_account
        WHERE account_no='$account_no'
        LIMIT 1
    ");

    if($acc_q && mysqli_num_rows($acc_q) > 0){
        $acc = mysqli_fetch_assoc($acc_q);
        $account_description = $acc['account_description'] ?? '';
    }
}

/* ================= OPENING BALANCE ================= */
if($account_no != '' && $from_date != ''){
    $ob_q = mysqli_query($conn,"
        SELECT IFNULL(SUM(debit - credit),0) AS opening
        FROM sale_ledger
        WHERE account_no='$account_no'
        AND DATE(record_date) < '$from_date'
    ");

    if($ob_q){
        $ob = mysqli_fetch_assoc($ob_q);
        $opening_balance = $ob['opening'];
    }
}

/* ================= BUILD WHERE ================= */
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

/* ================= FETCH DATA ================= */
$sql = "SELECT * FROM sale_ledger
        $where
        ORDER BY DATE(record_date) ASC, voucher_no ASC";

$result = mysqli_query($conn,$sql);

$running = $opening_balance;

/* ================= ADD PREVIOUS BALANCE ROW ================= */
if($account_no != '' && $from_date != ''){

    $prev_row = [
        'voucher_print' => 'Previous Balance',
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

/* ================= LOOP ACTUAL DATA ================= */
if($result){
    while($row = mysqli_fetch_assoc($result)){

        $running += ($row['debit'] - $row['credit']);
        $row['running_balance'] = $running;
        $row['voucher_type'] = $row['voucher_type'] ?? 'Sale';
        $row['balance_type'] = $running > 0 ? 'Debit' : ($running < 0 ? 'Credit' : 'Balanced');

        $total_debit  += $row['debit'];
        $total_credit += $row['credit'];

        $data[] = $row;
    }
}

$closing_balance = $running;
?>

<link rel="stylesheet" href="/accounting-system/assets/css/jquery.dataTables.min.css">
<link rel="stylesheet" href="/accounting-system/assets/css/select2.min.css">

<script src="/accounting-system/assets/js/jquery-3.7.1.min.js"></script>
<script src="/accounting-system/assets/js/jquery.dataTables.min.js"></script>
<script src="/accounting-system/assets/js/select2.min.js"></script>

<div class="container-fluid mt-4">

<h4>Sale Ledger View</h4>

<form method="GET" class="row g-3 mb-3">

    <div class="col-md-3">
        <label>Account No</label>
        <select name="account_no" id="account_no" class="form-control select2">
            <option value="">All Accounts</option>
            <?php 
            while($acc = mysqli_fetch_assoc($account_list)){
                $selected = ($account_no == $acc['account_no']) ? 'selected' : '';
            ?>
            <option value="<?= $acc['account_no'] ?>" 
                    data-desc="<?= htmlspecialchars($acc['account_description']) ?>" 
                    <?= $selected ?>>
                <?= $acc['account_no'] ?> - <?= $acc['account_description'] ?>
            </option>
            <?php } ?>
        </select>
    </div>

    <div class="col-md-3">
        <label>Account Description</label>
        <input type="text" id="account_desc" 
               class="form-control" 
               value="<?= htmlspecialchars($account_description) ?>" readonly>
    </div>

    <div class="col-md-2">
        <label>From Date</label>
        <input type="date" name="from_date" 
               class="form-control" value="<?= $from_date ?>">
    </div>

    <div class="col-md-2">
        <label>To Date</label>
        <input type="date" name="to_date" 
               class="form-control" value="<?= $to_date ?>">
    </div>

<div class="col-md-2 align-self-end">
    <button class="btn btn-primary w-100 mb-2">Filter</button>

    <a href="sale_ledger.php" 
       class="btn btn-secondary w-100 mb-2">
       Clear
    </a>

    <a href="sale_ledger_print.php?account_no=<?= $account_no ?>&from_date=<?= $from_date ?>&to_date=<?= $to_date ?>"
       class="btn btn-success w-100"
       onclick="return openPrintPopup(this.href)">
       Print
    </a>
</div>

</form>

<!-- WIDGETS -->
<div class="row mb-4">

    <div class="col-md-3">
        <div class="card p-3 text-center bg-light">
            <h6>Account Description</h6>
            <strong id="widget_desc"><?= htmlspecialchars($account_description) ?></strong>
        </div>
    </div>

    <div class="col-md-2">
        <div class="card p-3 text-center bg-success text-white">
            <h6>Debit</h6>
            <strong><?= number_format($total_debit,2) ?></strong>
        </div>
    </div>

    <div class="col-md-2">
        <div class="card p-3 text-center bg-danger text-white">
            <h6>Credit</h6>
            <strong><?= number_format($total_credit,2) ?></strong>
        </div>
    </div>

    <div class="col-md-2">
        <div class="card p-3 text-center bg-info text-white">
            <h6>Opening</h6>
            <strong><?= number_format($opening_balance,2) ?></strong>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card p-3 text-center bg-primary text-white">
            <h6>Closing</h6>
            <strong><?= number_format($closing_balance,2) ?></strong>
        </div>
    </div>

</div>

<table id="ledgerTable" class="table table-bordered table-striped">
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
        <tr class="<?= ($row['voucher_print'] == 'Previous Balance') ? 'table-warning fw-bold' : '' ?>">
            <td><?= $row['record_date'] ?></td>
            <td><?= $row['voucher_print'] ?></td>
            <td><?= htmlspecialchars($row['remarks']) ?></td>
            <td><?= htmlspecialchars($row['voucher_type'] ?? 'Sale') ?></td>
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

    function updateDesc(){
        var desc = $('#account_no option:selected').data('desc');
        $('#account_desc').val(desc ? desc : '');
        $('#widget_desc').text(desc ? desc : '');
    }

    updateDesc();

    $('#account_no').on('change', function(){
        updateDesc();
    });

    $('#ledgerTable').DataTable({
        pageLength: 10,
        ordering: false
    });

});
</script>

<?php include "../../includes/footer.php"; ?>
