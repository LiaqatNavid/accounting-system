<?php
session_start();
include "../../includes/auth_check.php";
include "../../config/db.php";
if (!rbac_can_do('add_records')) { header("Location: stock_adjustment_list.php?denied=1"); exit; }
include "../../includes/approval_helper.php";
include "../../includes/financial_year_helper.php";
include "../../includes/header.php";
approval_ensure_schema($conn);
financial_year_ensure_schema($conn);
voucher_series_ensure_transaction_columns($conn);

$r = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT IFNULL(MAX(adjustment_no),0) AS ano
    FROM stock_adjustment_master
    WHERE deleted_at IS NULL
"));
$lastNo = $r['ano'];
$previewVoucher = voucher_series_preview($conn, 'adj', date('Y-m-d'));
?>

<link rel="stylesheet" href="<?php echo $base_url; ?>assets/css/jquery.dataTables.min.css">

<style>
.stock-adjust-page .card { border: 0; border-radius: 14px; }
.stock-adjust-page .card-header { border-radius: 14px 14px 0 0; }
.stock-adjust-page .summary-card .card-body { border-radius: 14px; }
</style>

<div class="container-fluid stock-adjust-page mt-4">
    <div class="mb-3">
        <h3 class="mb-1">Stock Adjustment</h3>
        <div class="text-muted">Create a new stock adjustment entry.</div>
    </div>

    <div class="alert alert-info shadow-sm">
        <strong>Last Adjustment No:</strong> <?php echo htmlspecialchars($lastNo); ?>
        <?php if ($previewVoucher): ?>
            <div class="mt-1"><strong>Next Series No:</strong> <?php echo htmlspecialchars($previewVoucher); ?></div>
        <?php endif; ?>
    </div>

    <form method="post" action="stock_adjustment_save.php" id="adjForm">
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-primary text-white">
                <h6 class="mb-0">Adjustment Details</h6>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Adjustment Date</label>
                        <input type="date" name="adjustment_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Adjustment Type</label>
                        <input type="text" name="adjustment_type" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Master Remarks</label>
                        <input type="text" name="master_remarks" class="form-control">
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
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card summary-card shadow-sm mb-4">
            <div class="card-body bg-primary text-white">
                <div class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Grand Total</label>
                        <input type="text" id="grand_total" class="form-control fw-bold" readonly value="0.00">
                    </div>
                    <div class="col-md-8 text-md-end fw-bold">
                        <div>Total Items: <span id="rowCount">0</span></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="text-end">
            <button type="submit" class="btn btn-success btn-lg">Save</button>
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
                    <thead class="table-dark">
                        <tr>
                            <th>Item Code</th>
                            <th>Item Name</th>
                            <th style="width:110px;">Action</th>
                        </tr>
                    </thead>
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
        var grand = 0;
        $('.total').each(function () {
            var val = parseFloat($(this).val());
            if (!isNaN(val)) { grand += val; }
        });
        $('#grand_total').val(grand.toFixed(2));
        $('#rowCount').text($('#detailTable tbody tr').length);
    }

    function reIndex() {
        $('#detailTable tbody tr').each(function (index) {
            $(this).find('td:first').text(index + 1);
        });
    }

    $(document).on('click', '.select-item', function () {
        var code = $(this).data('code');
        var name = $(this).data('name');
        var row = $('#detailTable tbody tr').length + 1;

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

        var modalInstance = bootstrap.Modal.getInstance(document.getElementById('itemModal'));
        if (modalInstance) { modalInstance.hide(); }
        calculate();
    });

    $(document).on('input', '.qty, .price', function () {
        var row = $(this).closest('tr');
        var qty = parseFloat(row.find('.qty').val()) || 0;
        var price = parseFloat(row.find('.price').val()) || 0;
        row.find('.total').val((qty * price).toFixed(2));
        calculate();
    });

    $(document).on('click', '.remove', function () {
        $(this).closest('tr').remove();
        reIndex();
        calculate();
    });
});
</script>

<?php include "../../includes/footer.php"; ?>
