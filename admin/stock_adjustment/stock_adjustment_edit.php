<?php
session_start();
include "../../includes/auth_check.php";
include "../../config/db.php";
if (!rbac_can_do('edit_records')) { header("Location: stock_adjustment_list.php?denied=1"); exit; }
include "../../includes/approval_helper.php";

if (!isset($_GET['id'])) {
    die("Invalid Request");
}

$id = (int) $_GET['id'];
$master = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM stock_adjustment_master WHERE id = '$id' AND deleted_at IS NULL"));
if (!$master) {
    die("Record not found");
}
if (approval_is_locked($master['approval_status'] ?? 'Draft')) {
    header("Location: stock_adjustment_list.php?locked=1");
    exit;
}
include "../../includes/header.php";
$details = mysqli_query($conn, "SELECT * FROM stock_adjustment_detail WHERE adjustment_id = '$id'");
?>

<link rel="stylesheet" href="<?php echo $base_url; ?>assets/css/jquery.dataTables.min.css">

<style>
.stock-adjust-edit-page .card { border: 0; border-radius: 14px; }
.stock-adjust-edit-page .card-header { border-radius: 14px 14px 0 0; }
.stock-adjust-edit-page .summary-card .card-body { border-radius: 14px; }
</style>

<div class="container-fluid stock-adjust-edit-page mt-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div>
            <h3 class="mb-1">Edit Stock Adjustment</h3>
            <div class="text-muted">Update stock adjustment details and line items.</div>
        </div>
        <a href="<?php echo $base_url; ?>admin/stock_adjustment/stock_adjustment_list.php" class="btn btn-outline-primary">Back To List</a>
    </div>

    <form method="post" action="stock_adjustment_update.php" id="adjForm">
        <input type="hidden" name="id" value="<?php echo htmlspecialchars($id); ?>">

        <div class="card shadow-sm mb-4">
            <div class="card-header bg-primary text-white"><h6 class="mb-0">Adjustment Details</h6></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Adjustment Date</label>
                        <input type="date" name="adjustment_date" class="form-control" value="<?php echo htmlspecialchars($master['adjustment_date']); ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Adjustment Type</label>
                        <input type="text" name="adjustment_type" class="form-control" value="<?php echo htmlspecialchars($master['adjustment_type']); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Master Remarks</label>
                        <input type="text" name="master_remarks" class="form-control" value="<?php echo htmlspecialchars($master['master_remarks']); ?>">
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm mb-4">
            <div class="card-header bg-success text-white d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h6 class="mb-0">Adjustment Items</h6>
                <button type="button" class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#itemModal">+ Add Item</button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered table-sm mb-0" id="detailTable">
                        <thead class="table-dark">
                            <tr>
                                <th style="width:60px;">#</th>
                                <th style="width:140px;">Item Code</th>
                                <th>Item Name</th>
                                <th style="width:120px;">Adjust Qty</th>
                                <th style="width:140px;">Per Item Price</th>
                                <th style="width:140px;">Total Price</th>
                                <th style="width:220px;">Remarks</th>
                                <th style="width:80px;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php
                        $count = 1;
                        $grandTotal = 0;
                        while ($row = mysqli_fetch_assoc($details)) {
                            $grandTotal += (float) $row['total_price'];
                        ?>
                            <tr>
                                <td><?php echo $count++; ?></td>
                                <td><?php echo htmlspecialchars($row['item_code']); ?><input type="hidden" name="item_code[]" value="<?php echo htmlspecialchars($row['item_code']); ?>"></td>
                                <td><?php echo htmlspecialchars($row['item_name']); ?><input type="hidden" name="item_name[]" value="<?php echo htmlspecialchars($row['item_name']); ?>"></td>
                                <td><input type="number" step="0.01" name="adjust_qty[]" class="form-control form-control-sm qty" value="<?php echo htmlspecialchars($row['adjust_qty']); ?>"></td>
                                <td><input type="number" step="0.01" name="per_item_price[]" class="form-control form-control-sm price" value="<?php echo htmlspecialchars($row['per_item_price']); ?>"></td>
                                <td><input type="text" name="total_price[]" class="form-control form-control-sm total" value="<?php echo number_format((float) $row['total_price'], 2, '.', ''); ?>" readonly></td>
                                <td><input type="text" name="remarks[]" class="form-control form-control-sm" value="<?php echo htmlspecialchars($row['remarks']); ?>"></td>
                                <td class="text-center"><button type="button" class="btn btn-danger btn-sm remove">X</button></td>
                            </tr>
                        <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card summary-card shadow-sm mb-4">
            <div class="card-body bg-primary text-white">
                <div class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Grand Total</label>
                        <input type="text" id="grand_total" class="form-control fw-bold" value="<?php echo number_format($grandTotal, 2, '.', ''); ?>" readonly>
                    </div>
                    <div class="col-md-8 text-md-end fw-bold">
                        <div>Total Items: <span id="rowCount"><?php echo $count - 1; ?></span></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="text-end">
            <button type="submit" class="btn btn-success btn-lg">Update</button>
            <a href="stock_adjustment_list.php" class="btn btn-secondary">Back</a>
        </div>
    </form>
</div>

<div class="modal fade" id="itemModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">Select Item</h5>
                <button class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <table class="table table-bordered table-sm w-100" id="itemTable">
                    <thead class="table-dark"><tr><th>Item Code</th><th>Item Name</th><th style="width:110px;">Action</th></tr></thead>
                    <tbody>
                    <?php
                    $q = mysqli_query($conn, "SELECT item_code,item_name FROM inventory ORDER BY item_code");
                    while ($a = mysqli_fetch_assoc($q)) {
                    ?>
                        <tr>
                            <td><?php echo htmlspecialchars($a['item_code']); ?></td>
                            <td><?php echo htmlspecialchars($a['item_name']); ?></td>
                            <td><button type="button" class="btn btn-sm btn-success select-item" data-code="<?php echo htmlspecialchars($a['item_code']); ?>" data-name="<?php echo htmlspecialchars($a['item_name'], ENT_QUOTES); ?>">Add</button></td>
                        </tr>
                    <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="<?php echo $base_url; ?>assets/js/jquery.dataTables.min.js"></script>
<script>
$(document).ready(function () {
    $('#itemTable').DataTable({ pageLength: 8 });

    function calculate() {
        let grand = 0;
        $('#detailTable tbody tr').each(function (index) {
            $(this).find('td:first').text(index + 1);
            let qty = parseFloat($(this).find('.qty').val()) || 0;
            let price = parseFloat($(this).find('.price').val()) || 0;
            let total = qty * price;
            $(this).find('.total').val(total.toFixed(2));
            grand += total;
        });
        $('#grand_total').val(grand.toFixed(2));
        $('#rowCount').text($('#detailTable tbody tr').length);
    }

    $(document).on('click', '.select-item', function () {
        let code = $(this).data('code');
        let name = $(this).data('name');
        let row = $('#detailTable tbody tr').length + 1;
        $('#detailTable tbody').append(`
            <tr>
                <td>${row}</td>
                <td>${code}<input type="hidden" name="item_code[]" value="${code}"></td>
                <td>${name}<input type="hidden" name="item_name[]" value="${name}"></td>
                <td><input type="number" step="0.01" name="adjust_qty[]" class="form-control form-control-sm qty" min="0"></td>
                <td><input type="number" step="0.01" name="per_item_price[]" class="form-control form-control-sm price" min="0"></td>
                <td><input type="text" name="total_price[]" class="form-control form-control-sm total" readonly></td>
                <td><input type="text" name="remarks[]" class="form-control form-control-sm"></td>
                <td class="text-center"><button type="button" class="btn btn-danger btn-sm remove">X</button></td>
            </tr>
        `);
        const modal = bootstrap.Modal.getInstance(document.getElementById('itemModal'));
        if (modal) { modal.hide(); }
        calculate();
    });

    $(document).on('input', '.qty, .price', calculate);

    $(document).on('click', '.remove', function () {
        $(this).closest('tr').remove();
        calculate();
    });

    calculate();
});
</script>

<?php include "../../includes/footer.php"; ?>
