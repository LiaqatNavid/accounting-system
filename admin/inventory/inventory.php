<?php
session_start();
include "../../includes/auth_check.php";
include "../../config/db.php";
include "../../includes/account_lookup_helper.php";
include "../../includes/audit_helper.php";

/* ===============================
   INSERT RECORD
================================ */
if(isset($_POST['save_inventory'])){

    $main_category   = mysqli_real_escape_string($conn, trim($_POST['main_category']));
    $item_code       = mysqli_real_escape_string($conn, trim($_POST['item_code']));
    $item_name       = mysqli_real_escape_string($conn, trim($_POST['item_name']));
    $stock_account   = mysqli_real_escape_string($conn, trim($_POST['stock_account']));
    $revenue_account = mysqli_real_escape_string($conn, trim($_POST['revenue_account']));
    $expense_account = mysqli_real_escape_string($conn, trim($_POST['expense_account']));
    $purchase_rate   = floatval($_POST['purchase_rate']);
    $whole_sale_rate = floatval($_POST['whole_sale_rate']);
    $retail_rate     = floatval($_POST['retail_rate']);
    $record_date     = date('Y-m-d');

    mysqli_query($conn,"
        INSERT INTO inventory
        (main_category,item_code,item_name,
         stock_account,revenue_account,expense_account,
         purchase_rate,whole_sale_rate,retail_rate,record_date)
        VALUES
        ('$main_category','$item_code','$item_name',
         '$stock_account','$revenue_account','$expense_account',
         '$purchase_rate','$whole_sale_rate','$retail_rate','$record_date')
    ");

    header("Location: inventory.php");
    exit();
}

/* ===============================
   DELETE RECORD
================================ */
if(isset($_GET['delete_code'])){

    $code = mysqli_real_escape_string($conn, $_GET['delete_code']);
    $auditRow = mysqli_fetch_assoc(mysqli_query($conn,"
        SELECT item_code, item_name, main_category
        FROM inventory
        WHERE item_code='$code'
    "));

    mysqli_query($conn,"
        DELETE FROM inventory
        WHERE item_code='$code'
    ");

    if($auditRow){
        audit_log_delete(
            $conn,
            'inventory_item',
            (string) $code,
            'Inventory Item ' . $code,
            [
                'item_name' => $auditRow['item_name'] ?? '',
                'main_category' => $auditRow['main_category'] ?? ''
            ]
        );
    }

    header("Location: inventory.php");
    exit();
}

/* ===============================
   LOAD DATA
================================ */
$categories = mysqli_query($conn,
    "SELECT category_name FROM category ORDER BY category_name");

$accounts = mysqli_query($conn,
    "SELECT ACCOUNT_NO, ACCOUNT_DESCRIPTION
     FROM all_chart_of_accounts
     ORDER BY ACCOUNT_NO");
$accountLookupRows = get_account_lookup_rows($conn);

include "../../includes/header.php";
?>

<link rel="stylesheet" href="<?php echo $base_url; ?>assets/css/jquery.dataTables.min.css">

<style>
.inventory-page .card {
    border: 0;
    border-radius: 14px;
}

.inventory-page .card-header {
    border-radius: 14px 14px 0 0;
}
</style>

<div class="container-fluid inventory-page mt-4">
<div class="mb-3">
<h3 class="mb-1">Inventory Entry</h3>
<div class="text-muted">Create inventory items and manage stock account mappings.</div>
</div>

<!-- ================= ENTRY FORM ================= -->
<div class="card shadow-sm mb-4">
<div class="card-header bg-primary text-white">
<h6 class="mb-0">Add Inventory Item</h6>
</div>
<div class="card-body">

<form method="POST">
<div class="row g-3">

<!-- CATEGORY -->
<div class="col-md-4">
<label>Main Category</label>
<div class="input-group input-group-sm">
<input type="hidden" name="main_category" id="main_category">
<input type="text" id="selectedCategory"
       class="form-control"
       placeholder="Click Search"
       readonly required>
<button type="button"
        class="btn btn-outline-secondary"
        id="btnSearchCategory">
Search
</button>
</div>
</div>

<!-- ITEM CODE -->
<div class="col-md-4">
<label>Item Code</label>
<input type="text" name="item_code"
       class="form-control form-control-sm" required>
</div>

<!-- ITEM NAME -->
<div class="col-md-4">
<label>Item Name</label>
<input type="text" name="item_name"
       class="form-control form-control-sm">
</div>

<!-- STOCK ACCOUNT -->
<div class="col-md-4">
<label>Stock Account</label>
<div class="input-group input-group-sm">
<input type="hidden" name="stock_account" id="stock_account">
<input type="text" id="selectedStock"
       class="form-control"
       readonly required>
<button type="button"
        class="btn btn-outline-secondary searchAccount"
        data-target="stock">
Search
</button>
</div>
</div>

<!-- REVENUE ACCOUNT -->
<div class="col-md-4">
<label>Revenue Account</label>
<div class="input-group input-group-sm">
<input type="hidden" name="revenue_account" id="revenue_account">
<input type="text" id="selectedRevenue"
       class="form-control"
       readonly required>
<button type="button"
        class="btn btn-outline-secondary searchAccount"
        data-target="revenue">
Search
</button>
</div>
</div>

<!-- EXPENSE ACCOUNT -->
<div class="col-md-4">
<label>Expense Account</label>
<div class="input-group input-group-sm">
<input type="hidden" name="expense_account" id="expense_account">
<input type="text" id="selectedExpense"
       class="form-control"
       readonly required>
<button type="button"
        class="btn btn-outline-secondary searchAccount"
        data-target="expense">
Search
</button>
</div>
</div>

<!-- RATES -->
<div class="col-md-2">
<label>Purchase</label>
<input type="number" step="0.01"
       name="purchase_rate"
       class="form-control">
</div>

<div class="col-md-2">
<label>Whole</label>
<input type="number" step="0.01"
       name="whole_sale_rate"
       class="form-control">
</div>

<div class="col-md-2">
<label>Retail</label>
<input type="number" step="0.01"
       name="retail_rate"
       class="form-control">
</div>

<div class="col-md-2 d-flex align-items-end">
<button type="submit"
        name="save_inventory"
        class="btn btn-primary w-100">
Save
</button>
</div>

</div>
</form>
</div>
</div>

<!-- ================= GRID ================= -->
<div class="card shadow-sm mt-4">
<div class="card-header bg-primary text-white">
<h6 class="mb-0">All Inventory</h6>
</div>
<div class="card-body">

<table id="inventoryTable"
class="table table-sm table-bordered table-striped w-100">
<thead>
<tr>
<th>Category</th>
<th>Code</th>
<th>Name</th>
<th>Purchase</th>
<th>Whole</th>
<th>Retail</th>
<th>Whole Profit</th>
<th>Retail Profit</th>
<th>Actions</th>
</tr>
</thead>
<tbody>

<?php
$data = mysqli_query($conn,
"SELECT * FROM all_inventory
 ORDER BY main_category,item_code DESC");

while($r = mysqli_fetch_assoc($data)){

echo "<tr>
<td>".$r['main_category']."</td>
<td>".$r['item_code']."</td>
<td>".$r['item_name']."</td>
<td>".$r['purchase_rate']."</td>
<td>".$r['whole_sale_rate']."</td>
<td>".$r['retail_rate']."</td>
<td>".$r['whole_sale_profit']."</td>
<td>".$r['retail_profit']."</td>
<td>
    <a href='edit_inventory.php?item_code=".urlencode($r['item_code'])."'
       class='btn btn-sm btn-primary me-1'>Edit</a>

    <a href='inventory.php?delete_code=".urlencode($r['item_code'])."'
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

<!-- ================= MODALS ================= -->

<div class="modal fade" id="categoryModal" tabindex="-1">
<div class="modal-dialog modal-lg">
<div class="modal-content">
<div class="modal-header">
<h5>Select Category</h5>
<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>
<div class="modal-body">

<table id="categoryTable" class="table table-sm table-bordered w-100">
<thead><tr><th>Category</th></tr></thead>
<tbody>
<?php while($c=mysqli_fetch_assoc($categories)){ ?>
<tr data-cat="<?= $c['category_name'] ?>">
<td><?= $c['category_name'] ?></td>
</tr>
<?php } ?>
</tbody>
</table>

</div>
</div>
</div>
</div>

<div class="modal fade" id="accountModal" tabindex="-1">
<div class="modal-dialog modal-lg">
<div class="modal-content">
<div class="modal-header">
<h5>Select Account</h5>
<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>
<div class="modal-body">

<table id="accountTable" class="table table-sm table-bordered w-100">
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
<?php foreach($accountLookupRows as $a){ ?>
<tr>
<td><?= htmlspecialchars($a['account_no']) ?></td>
<td><?= htmlspecialchars($a['account_description']) ?></td>
<td class="text-end"><?= number_format((float) $a['last_balance'], 2) ?></td>
<td class="<?= $a['balance_label'] === 'Payable' ? 'text-danger fw-bold' : ($a['balance_label'] === 'Receivable' ? 'text-success fw-bold' : 'text-muted fw-bold') ?>"><?= htmlspecialchars($a['balance_label']) ?></td>
<td><button type="button" class="btn btn-sm btn-success select-account" data-no="<?= htmlspecialchars($a['account_no']) ?>" data-desc="<?= htmlspecialchars($a['account_description']) ?>">Select</button></td>
</tr>
<?php } ?>
</tbody>
</table>

</div>
</div>
</div>
</div>

<script src="<?php echo $base_url; ?>assets/js/jquery-3.7.0.min.js"></script>
<script src="<?php echo $base_url; ?>assets/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo $base_url; ?>assets/js/jquery.dataTables.min.js"></script>

<script>
$(document).ready(function(){

$('#inventoryTable').DataTable({pageLength:15});
$('#categoryTable').DataTable({pageLength:5});
$('#accountTable').DataTable({pageLength:5});

/* CATEGORY */
$('#btnSearchCategory').click(function(){
    new bootstrap.Modal(document.getElementById('categoryModal')).show();
});

$('#categoryTable tbody').on('click','tr',function(){
    let cat = $(this).data('cat');
    $('#main_category').val(cat);
    $('#selectedCategory').val(cat);
    bootstrap.Modal.getInstance(document.getElementById('categoryModal')).hide();
});

/* ACCOUNT */
let activeField = '';

$('.searchAccount').click(function(){
    activeField = $(this).data('target');
    new bootstrap.Modal(document.getElementById('accountModal')).show();
});

$(document).on('click','.select-account',function(){

    let acc  = $(this).data('no');
    let desc = $(this).data('desc');

    if(activeField === 'stock'){
        $('#stock_account').val(acc);
        $('#selectedStock').val(acc + ' - ' + desc);
    }
    if(activeField === 'revenue'){
        $('#revenue_account').val(acc);
        $('#selectedRevenue').val(acc + ' - ' + desc);
    }
    if(activeField === 'expense'){
        $('#expense_account').val(acc);
        $('#selectedExpense').val(acc + ' - ' + desc);
    }

    bootstrap.Modal.getInstance(document.getElementById('accountModal')).hide();
});

/* DELETE */
$(document).on('click','.del-rec',function(e){
e.preventDefault();
if(confirm('Delete this record?')){
window.location.href=$(this).attr('href');
}
});

});
</script>

<?php include "../../includes/footer.php"; ?>
