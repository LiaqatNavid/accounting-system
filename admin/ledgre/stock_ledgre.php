<?php
session_start();
include "../../includes/auth_check.php";
include "../../config/db.php";
include "../../includes/header.php";

$item_code = $_GET['item_code'] ?? '';
$from_date = $_GET['from_date'] ?? '';
$to_date   = $_GET['to_date'] ?? '';

$data = [];
$opening_balance = 0;
$running = 0;

$total_purchase = 0;
$total_sale = 0;
$total_pvoucher = 0;
$total_svoucher = 0;
$total_adj_in = 0;
$total_adj_out = 0;

/* ================= LOAD ITEM LIST ================= */
$item_list = mysqli_query($conn,"
    SELECT item_code, item_name 
    FROM all_inventory
    ORDER BY item_name ASC
");

/* ================= OPENING BALANCE ================= */
if($item_code != '' && $from_date != ''){

    $ob_q = mysqli_query($conn,"
        SELECT IFNULL(SUM(purchase_qty - sale_qty),0) AS opening
        FROM STOCK_LEDGRE
        WHERE item_code='$item_code'
        AND DATE(record_date) < '$from_date'
    ");

    if($ob_q){
        $ob = mysqli_fetch_assoc($ob_q);
        $opening_balance = $ob['opening'];
    }
}

/* ================= BUILD WHERE ================= */
$where = " WHERE 1=1 ";

if($item_code != ''){
    $where .= " AND item_code='$item_code' ";
}

if($from_date != ''){
    $where .= " AND DATE(record_date) >= '$from_date' ";
}

if($to_date != ''){
    $where .= " AND DATE(record_date) <= '$to_date' ";
}

/* ================= FETCH DATA ================= */
$sql = "SELECT * FROM STOCK_LEDGRE
        $where
        ORDER BY DATE(record_date) ASC, voucher_no ASC";

$result = mysqli_query($conn,$sql);

$running = $opening_balance;

/* ================= ADD PREVIOUS BALANCE ROW ================= */
if($item_code != '' && $from_date != ''){

    $data[] = [
        'print_voucher' => 'Previous Balance',
        'record_date' => $from_date,
        'voucher_type' => 'Opening',
        'item_code' => $item_code,
        'item_name' => '',
        'main_category' => '',
        'remarks' => 'Stock Before ' . $from_date,
        'purchase_qty' => ($opening_balance > 0 ? $opening_balance : 0),
        'sale_qty' => ($opening_balance < 0 ? abs($opening_balance) : 0),
        'running_balance' => $opening_balance,
        'balance_type' => ($opening_balance > 0 ? 'In Stock' : ($opening_balance < 0 ? 'Short' : 'Balanced'))
    ];
}

/* ================= LOOP DATA ================= */
if($result){
    while($row = mysqli_fetch_assoc($result)){

        $running += ($row['purchase_qty'] - $row['sale_qty']);
        $row['running_balance'] = $running;
        $row['remarks'] = $row['remarks'] ?? (($row['item_name'] ?? '') !== '' ? $row['item_name'] : '');
        $row['balance_type'] = $running > 0 ? 'In Stock' : ($running < 0 ? 'Short' : 'Balanced');

        $total_purchase += $row['purchase_qty'];
        $total_sale     += $row['sale_qty'];

        /* ===== Voucher Type Totals ===== */
        if($row['voucher_type'] == 'PURCHASE VOUCHER')
            $total_pvoucher += $row['purchase_qty'];

        if($row['voucher_type'] == 'SALE VOUCHER')
            $total_svoucher += $row['sale_qty'];

        if($row['voucher_type'] == 'STOCK ADJUSTMENT'){
            $total_adj_in  += $row['purchase_qty'];
            $total_adj_out += $row['sale_qty'];
        }

        $data[] = $row;
    }
}

$closing_balance = $running;
?>

<link rel="stylesheet" href="<?php echo $base_url; ?>assets/css/jquery.dataTables.min.css">
<link rel="stylesheet" href="<?php echo $base_url; ?>assets/css/select2.min.css">

<script src="<?php echo $base_url; ?>assets/js/jquery-3.7.1.min.js"></script>
<script src="<?php echo $base_url; ?>assets/js/jquery.dataTables.min.js"></script>
<script src="<?php echo $base_url; ?>assets/js/select2.min.js"></script>

<div class="container-fluid mt-4">

<h4>Stock Ledger View</h4>

<form method="GET" class="row g-3 mb-3">

<div class="col-md-4">
    <label>Item</label>
    <select name="item_code" class="form-control select2">
        <option value="">All Items</option>
        <?php while($item = mysqli_fetch_assoc($item_list)){ 
            $selected = ($item_code == $item['item_code']) ? 'selected' : ''; ?>
            <option value="<?= $item['item_code'] ?>" <?= $selected ?>>
                <?= $item['item_code'] ?> - <?= $item['item_name'] ?>
            </option>
        <?php } ?>
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

    <button class="btn btn-primary w-100 mb-2">
        Filter
    </button>

    <a href="stock_ledgre.php" 
       class="btn btn-secondary w-100 mb-2">
       Clear
    </a>

    <a href="stock_ledgre_print.php?item_code=<?= $item_code ?>&from_date=<?= $from_date ?>&to_date=<?= $to_date ?>"
       class="btn btn-success w-100"
       onclick="return openPrintPopup(this.href)">
       Print
    </a>

</div>


</form>

<!-- ================= WIDGETS ================= -->

<div class="row mb-4">

<div class="col-md-2">
<div class="card p-3 text-center bg-success text-white">
<h6>Total Purchase</h6>
<strong><?= number_format($total_purchase,2) ?></strong>
</div></div>

<div class="col-md-2">
<div class="card p-3 text-center bg-danger text-white">
<h6>Total Sale</h6>
<strong><?= number_format($total_sale,2) ?></strong>
</div></div>

<div class="col-md-2">
<div class="card p-3 text-center bg-info text-white">
<h6>Purchase Voucher</h6>
<strong><?= number_format($total_pvoucher,2) ?></strong>
</div></div>

<div class="col-md-2">
<div class="card p-3 text-center bg-warning text-dark">
<h6>Sale Voucher</h6>
<strong><?= number_format($total_svoucher,2) ?></strong>
</div></div>

<div class="col-md-2">
<div class="card p-3 text-center bg-primary text-white">
<h6>Adj In</h6>
<strong><?= number_format($total_adj_in,2) ?></strong>
</div></div>

<div class="col-md-2">
<div class="card p-3 text-center bg-secondary text-white">
<h6>Adj Out</h6>
<strong><?= number_format($total_adj_out,2) ?></strong>
</div></div>

</div>

<!-- ================= TABLE ================= -->

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
<?php $statusClass = (($row['balance_type'] ?? '') === 'In Stock') ? 'text-success fw-bold' : ((($row['balance_type'] ?? '') === 'Short') ? 'text-danger fw-bold' : 'text-muted fw-bold'); ?>
<tr class="<?= ($row['print_voucher']=='Previous Balance') ? 'table-warning fw-bold' : '' ?>">
<td><?= $row['record_date'] ?></td>
<td><?= $row['print_voucher'] ?></td>
<td><?= htmlspecialchars($row['remarks'] ?? '') ?></td>
<td><?= $row['voucher_type'] ?></td>
<td><?= number_format($row['purchase_qty'],2) ?></td>
<td><?= number_format($row['sale_qty'],2) ?></td>
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
$('#ledgerTable').DataTable({
    pageLength: 10,
    ordering: false
});
});
</script>

<?php include "../../includes/footer.php"; ?>

