<?php
session_start();
include "../../includes/auth_check.php";
include "../../config/db.php";
include "../../includes/header.php";

$from_date  = $_GET['from_date'] ?? '';
$to_date    = $_GET['to_date'] ?? '';

$where = " WHERE 1=1 ";

if($from_date){
    $where .= " AND DATE(record_date) >= '$from_date' ";
}

if($to_date){
    $where .= " AND DATE(record_date) <= '$to_date' ";
}

/* ================= FETCH DATA ================= */

$sql = "SELECT * FROM trail_balance_new
        $where
        ORDER BY record_date ASC, voucher_print ASC";

$result = mysqli_query($conn,$sql);

$data = [];
$total_debit  = 0;
$total_credit = 0;

if($result){
    while($row = mysqli_fetch_assoc($result)){

        $row['debit']  = $row['debit'] ?? 0;
        $row['credit'] = $row['credit'] ?? 0;

        $total_debit  += $row['debit'];
        $total_credit += $row['credit'];

        $data[] = $row;
    }
}

$difference = $total_debit - $total_credit;
$status = ($difference == 0) ? "Balanced" : "Not Balanced";
$status_class = ($difference == 0) ? "bg-success" : "bg-danger";
?>

<link rel="stylesheet" href="<?php echo $base_url; ?>assets/css/jquery.dataTables.min.css">
<script src="<?php echo $base_url; ?>assets/js/jquery-3.7.1.min.js"></script>
<script src="<?php echo $base_url; ?>assets/js/jquery.dataTables.min.js"></script>

<div class="container-fluid mt-4">

<h4>Trial Balance View</h4>

<form method="GET" class="row g-3 mb-4">

<div class="col-md-3">
<label>From Date</label>
<input type="date" name="from_date" class="form-control" value="<?= $from_date ?>">
</div>

<div class="col-md-3">
<label>To Date</label>
<input type="date" name="to_date" class="form-control" value="<?= $to_date ?>">
</div>

<div class="col-md-2 align-self-end">
<button class="btn btn-primary w-100">Filter</button>
</div>

<div class="col-md-2 align-self-end">
<a href="trail_balance_new.php" class="btn btn-secondary w-100">Clear</a>
</div>

</form>

<!-- ================= SUMMARY WIDGETS ================= -->

<div class="row mb-4">

<div class="col-md-3">
<div class="card p-3 text-center bg-danger text-white">
<h6>Total Debit</h6>
<strong><?= number_format($total_debit,2) ?></strong>
</div>
</div>

<div class="col-md-3">
<div class="card p-3 text-center bg-success text-white">
<h6>Total Credit</h6>
<strong><?= number_format($total_credit,2) ?></strong>
</div>
</div>

<div class="col-md-3">
<div class="card p-3 text-center <?= $status_class ?> text-white">
<h6>Difference</h6>
<strong><?= number_format($difference,2) ?></strong>
</div>
</div>

<div class="col-md-3">
<div class="card p-3 text-center <?= $status_class ?> text-white">
<h6>Status</h6>
<strong><?= $status ?></strong>
</div>
</div>

</div>

<!-- ================= TABLE ================= -->

<table id="trialTable" class="table table-bordered table-striped">
<thead>
<tr>
<th>Date</th>
<th>Voucher</th>
<th>Account No</th>
<th>Description</th>
<th>Debit</th>
<th>Credit</th>
<th>Running Balance</th>
<th>Flag</th>
</tr>
</thead>
<tbody>

<?php foreach($data as $row): 
$row_class = ($row['runningbalance'] != 0) ? 'table-warning' : '';
?>

<tr class="<?= $row_class ?>">
<td><?= $row['record_date'] ?></td>
<td><?= $row['voucher_print'] ?></td>
<td><?= $row['account_no'] ?></td>
<td><?= $row['account_description'] ?></td>
<td><?= number_format($row['debit'],2) ?></td>
<td><?= number_format($row['credit'],2) ?></td>
<td><?= number_format($row['runningbalance'],2) ?></td>
<td><?= $row['flag_2'] ?></td>
</tr>

<?php endforeach; ?>

</tbody>
</table>

</div>

<script>
$(document).ready(function(){
$('#trialTable').DataTable({
pageLength: 25,
ordering: false
});
});
</script>

<?php include "../../includes/footer.php"; ?>

