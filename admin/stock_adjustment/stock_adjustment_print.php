<?php
session_start();
include "../../includes/auth_check.php";
include "../../config/db.php";
include "../../includes/branding.php";
if (!rbac_can_do('print_records')) { header("Location: stock_adjustment_list.php?denied=1"); exit; }

if (!isset($_GET['id'])) {
    die("Invalid Request");
}

$id = intval($_GET['id']);
$master = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM stock_adjustment_master WHERE id = '$id'"));

if (!$master) {
    die("Record not found");
}

$details = mysqli_query($conn, "SELECT * FROM stock_adjustment_detail WHERE adjustment_id = '$id'");
$grandTotal = 0;
$branding = get_branding_settings();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Stock Adjustment Print</title>
<link rel="stylesheet" href="../../assets/css/bootstrap.min.css">
<style>
@page { margin: 14mm; }
body{margin:0;background:#eef3f9;color:#17324d;font:13px/1.45 "Segoe UI",Tahoma,Arial,sans-serif;-webkit-print-color-adjust:exact;print-color-adjust:exact;}
.print-shell{max-width:1040px;margin:24px auto;padding:0 12px;}
.print-card{background:#fff;border:1px solid #d9e5f2;border-radius:18px;overflow:hidden;box-shadow:0 18px 40px rgba(15,64,120,.08);}
.print-band{background:linear-gradient(135deg,#0d6efd,#0b4fa8);color:#fff;padding:18px 24px;}
.print-band h1{margin:0;font-size:24px;letter-spacing:.06em;}
.print-band p{margin:4px 0 0;color:rgba(255,255,255,.85);font-size:12px;}
.print-body{padding:22px 24px 24px;}
.meta-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px;margin-bottom:16px;}
.meta-box,.remarks-box{border:1px solid #d9e5f2;border-radius:14px;padding:12px 14px;background:#f9fbfe;}
.remarks-box{margin-bottom:18px;}
.meta-label{display:block;color:#5b7795;font-size:11px;text-transform:uppercase;letter-spacing:.06em;margin-bottom:3px;}
.meta-value{color:#123355;font-weight:600;}
.table thead th{background:#e8f1ff;color:#14416f;border-color:#cfe0f5;font-size:12px;}
.table td,.table th{padding:9px 10px !important;vertical-align:middle;border-color:#d9e5f2;}
.table tfoot th{background:#f3f8ff;color:#123355;}
.action-bar{padding:16px 24px 24px;text-align:center;}
@media print{body{background:#fff}.print-shell{max-width:none;margin:0;padding:0}.print-card{box-shadow:none;border:none}.no-print{display:none !important}}
</style>
</head>
<body>
<div class="print-shell">
    <div class="print-card">
        <div class="print-band">
            <h1>STOCK ADJUSTMENT</h1>
            <p><?= htmlspecialchars($branding['company_name']) ?> Print View</p>
        </div>
        <div class="print-body">
            <div class="meta-grid">
                <div class="meta-box"><span class="meta-label">Adjustment No</span><div class="meta-value"><?= htmlspecialchars($master['display_voucher_no'] ?: $master['adjustment_no']) ?></div></div>
                <div class="meta-box"><span class="meta-label">Adjustment Date</span><div class="meta-value"><?= date('d-m-Y', strtotime($master['adjustment_date'])) ?></div></div>
                <div class="meta-box"><span class="meta-label">Adjustment Type</span><div class="meta-value"><?= htmlspecialchars($master['adjustment_type']) ?></div></div>
            </div>
            <div class="remarks-box">
                <span class="meta-label">Remarks</span>
                <div class="meta-value"><?= nl2br(htmlspecialchars($master['master_remarks'])) ?></div>
            </div>
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th width="5%">#</th>
                        <th width="12%">Item Code</th>
                        <th width="23%">Item Name</th>
                        <th width="12%" class="text-end">Adjust Qty</th>
                        <th width="14%" class="text-end">Per Item Price</th>
                        <th width="14%" class="text-end">Total Price</th>
                        <th width="20%">Remarks</th>
                    </tr>
                </thead>
                <tbody>
                <?php $counter = 1; while ($row = mysqli_fetch_assoc($details)) { $grandTotal += $row['total_price']; ?>
                    <tr>
                        <td><?= $counter++ ?></td>
                        <td><?= htmlspecialchars($row['item_code']) ?></td>
                        <td><?= htmlspecialchars($row['item_name']) ?></td>
                        <td class="text-end"><?= number_format($row['adjust_qty'], 2) ?></td>
                        <td class="text-end"><?= number_format($row['per_item_price'], 2) ?></td>
                        <td class="text-end"><?= number_format($row['total_price'], 2) ?></td>
                        <td><?= htmlspecialchars($row['remarks']) ?></td>
                    </tr>
                <?php } ?>
                </tbody>
                <tfoot>
                    <tr>
                        <th colspan="5" class="text-end">Grand Total</th>
                        <th class="text-end"><?= number_format($grandTotal, 2) ?></th>
                        <th></th>
                    </tr>
                </tfoot>
            </table>
        </div>
        <div class="action-bar no-print">
            <button class="btn btn-primary" onclick="window.print()">Print</button>
            <button class="btn btn-outline-secondary" onclick="window.close()">Close</button>
        </div>
    </div>
</div>
<script>
window.onload = function () { window.print(); };
</script>
</body>
</html>
