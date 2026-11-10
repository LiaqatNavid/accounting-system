<?php
session_start();
include "../../includes/auth_check.php";
include "../../config/db.php";

/* ===============================
   VALIDATE PARAM
================================ */
if(!isset($_GET['item_code'])){
    header("Location: inventory.php");
    exit();
}

$item_code = mysqli_real_escape_string($conn, trim($_GET['item_code']));

/* ===============================
   LOAD RECORD
================================ */
$res = mysqli_query($conn,"
    SELECT * FROM inventory
    WHERE item_code='$item_code'
");

if(!$res || mysqli_num_rows($res) == 0){
    header("Location: inventory.php");
    exit();
}

$data = mysqli_fetch_assoc($res);

/* ===============================
   UPDATE RECORD
================================ */
if(isset($_POST['update_inventory'])){

    $item_name       = mysqli_real_escape_string($conn, trim($_POST['item_name']));
    $stock_account   = mysqli_real_escape_string($conn, trim($_POST['stock_account']));
    $revenue_account = mysqli_real_escape_string($conn, trim($_POST['revenue_account']));
    $expense_account = mysqli_real_escape_string($conn, trim($_POST['expense_account']));
    $purchase_rate   = floatval($_POST['purchase_rate']);
    $whole_sale_rate = floatval($_POST['whole_sale_rate']);
    $retail_rate     = floatval($_POST['retail_rate']);

    mysqli_query($conn,"
        UPDATE inventory SET
            item_name='$item_name',
            stock_account='$stock_account',
            revenue_account='$revenue_account',
            expense_account='$expense_account',
            purchase_rate='$purchase_rate',
            whole_sale_rate='$whole_sale_rate',
            retail_rate='$retail_rate'
        WHERE item_code='$item_code'
    ");

    header("Location: inventory.php");
    exit();
}

include "../../includes/header.php";
?>

<style>
.edit-inventory-page .card {
    border: 0;
    border-radius: 14px;
}

.edit-inventory-page .card-header {
    border-radius: 14px 14px 0 0;
}
</style>

<div class="container-fluid edit-inventory-page mt-4">
<div class="mb-3">
<h3 class="mb-1">Edit Inventory</h3>
<div class="text-muted">Update inventory item details and account mappings.</div>
</div>

<div class="card shadow-sm">
<div class="card-header bg-primary text-white">
<h6 class="mb-0">Inventory Details</h6>
</div>
<div class="card-body">

<form method="POST">
<div class="row g-3">

<div class="col-md-4">
<label>Main Category</label>
<input type="text" class="form-control"
value="<?= htmlspecialchars($data['main_category']); ?>" readonly>
</div>

<div class="col-md-4">
<label>Item Code</label>
<input type="text" class="form-control"
value="<?= htmlspecialchars($data['item_code']); ?>" readonly>
</div>

<div class="col-md-4">
<label>Item Name</label>
<input type="text" name="item_name"
class="form-control"
value="<?= htmlspecialchars($data['item_name']); ?>">
</div>

<div class="col-md-3">
<label>Stock Account</label>
<input type="text" name="stock_account"
class="form-control"
value="<?= htmlspecialchars($data['stock_account']); ?>">
</div>

<div class="col-md-3">
<label>Revenue Account</label>
<input type="text" name="revenue_account"
class="form-control"
value="<?= htmlspecialchars($data['revenue_account']); ?>">
</div>

<div class="col-md-3">
<label>Expense Account</label>
<input type="text" name="expense_account"
class="form-control"
value="<?= htmlspecialchars($data['expense_account']); ?>">
</div>

<div class="col-md-3">
<label>Purchase</label>
<input type="number" step="0.01"
name="purchase_rate"
class="form-control"
value="<?= htmlspecialchars($data['purchase_rate']); ?>">
</div>

<div class="col-md-3">
<label>Whole</label>
<input type="number" step="0.01"
name="whole_sale_rate"
class="form-control"
value="<?= htmlspecialchars($data['whole_sale_rate']); ?>">
</div>

<div class="col-md-3">
<label>Retail</label>
<input type="number" step="0.01"
name="retail_rate"
class="form-control"
value="<?= htmlspecialchars($data['retail_rate']); ?>">
</div>

<div class="col-md-3 d-flex align-items-end">
<button type="submit"
name="update_inventory"
class="btn btn-primary w-100">
Update
</button>
</div>

</div>
</form>
</div>
</div>

<div class="mt-3">
<a href="inventory.php" class="btn btn-secondary">Back to Inventory</a>
</div>
</div>

<?php include "../../includes/footer.php"; ?>
