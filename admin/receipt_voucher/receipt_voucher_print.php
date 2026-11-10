<?php
session_start();
include "../../includes/auth_check.php";
include "../../config/db.php";
include "../../includes/branding.php";
if (!rbac_can_do('print_records')) { header("Location: receipt_voucher_grid.php?denied=1"); exit; }

if (!isset($_GET['vno'])) {
    die("Voucher Missing");
}

$voucher_no = intval($_GET['vno']);

$m = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT rv.*, coa.account_description
    FROM receipt_voucher rv
    LEFT JOIN chart_of_account coa ON rv.account_no = coa.account_no
    WHERE rv.voucher_no = '$voucher_no'
"));

if (!$m) {
    die("Voucher Not Found");
}

$dq = mysqli_query($conn, "
    SELECT *
    FROM vw_receipt_voucher_full
    WHERE voucher_no = '$voucher_no'
    ORDER BY detail_id
");

$total = 0;
$branding = get_branding_settings();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Receipt Voucher Print</title>
<link rel="stylesheet" href="../../assets/css/bootstrap.min.css">
<style>
@page { margin: 14mm; }
body{margin:0;background:#eef3f9;color:#17324d;font:13px/1.45 "Segoe UI",Tahoma,Arial,sans-serif;-webkit-print-color-adjust:exact;print-color-adjust:exact;}
.print-shell{max-width:980px;margin:24px auto;padding:0 12px;}
.print-card{background:#fff;border:1px solid #d9e5f2;border-radius:18px;overflow:hidden;box-shadow:0 18px 40px rgba(15,64,120,.08);}
.print-band{background:linear-gradient(135deg,#0d6efd,#0b4fa8);color:#fff;padding:18px 24px;}
.print-band h1{margin:0;font-size:24px;letter-spacing:.08em;}
.print-band p{margin:4px 0 0;color:rgba(255,255,255,.85);font-size:12px;}
.print-body{padding:22px 24px 24px;}
.meta-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px;margin-bottom:18px;}
.meta-box{border:1px solid #d9e5f2;border-radius:14px;padding:12px 14px;background:#f9fbfe;}
.meta-label{display:block;color:#5b7795;font-size:11px;text-transform:uppercase;letter-spacing:.06em;margin-bottom:3px;}
.meta-value{color:#123355;font-weight:600;}
.table thead th{background:#e8f1ff;color:#14416f;border-color:#cfe0f5;font-size:12px;}
.table td,.table th{padding:9px 10px !important;vertical-align:middle;border-color:#d9e5f2;}
.table tfoot th{background:#f3f8ff;color:#123355;}
.signature-row{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:24px;margin-top:34px;}
.signature-box{text-align:center;color:#486684;padding-top:28px;border-top:1px dashed #9eb8d6;}
.action-bar{padding:16px 24px 24px;text-align:center;}
@media print{body{background:#fff}.print-shell{max-width:none;margin:0;padding:0}.print-card{box-shadow:none;border:none}.no-print{display:none !important}}
</style>
</head>
<body>
<div class="print-shell">
    <div class="print-card">
        <div class="print-band">
            <h1>RECEIPT VOUCHER</h1>
            <p><?= htmlspecialchars($branding['company_name']) ?> Print View</p>
        </div>
        <div class="print-body">
            <div class="meta-grid">
                <div class="meta-box"><span class="meta-label">Voucher No</span><div class="meta-value"><?= htmlspecialchars($m['display_voucher_no'] ?: $m['voucher_no']) ?></div></div>
                <div class="meta-box"><span class="meta-label">Voucher Date</span><div class="meta-value"><?= date('d-m-Y', strtotime($m['voucher_date'])) ?></div></div>
                <div class="meta-box"><span class="meta-label">Voucher Type</span><div class="meta-value"><?= htmlspecialchars($m['voucher_type']) ?></div></div>
                <div class="meta-box"><span class="meta-label">Main Account</span><div class="meta-value"><?= htmlspecialchars($m['account_no'] . ' - ' . $m['account_description']) ?></div></div>
            </div>
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th width="6%">#</th>
                        <th width="18%">Account No</th>
                        <th width="30%">Account Name</th>
                        <th width="26%">Remarks</th>
                        <th width="20%" class="text-end">Credit</th>
                    </tr>
                </thead>
                <tbody>
                <?php $i = 1; while ($row = mysqli_fetch_assoc($dq)) { $total += $row['credit']; ?>
                    <tr>
                        <td><?= $i++ ?></td>
                        <td><?= htmlspecialchars($row['detail_account_no']) ?></td>
                        <td><?= htmlspecialchars($row['detail_account_description']) ?></td>
                        <td><?= htmlspecialchars($row['remarks']) ?></td>
                        <td class="text-end"><?= number_format($row['credit'], 2) ?></td>
                    </tr>
                <?php } ?>
                </tbody>
                <tfoot>
                    <tr>
                        <th colspan="4" class="text-end">Total Credit</th>
                        <th class="text-end"><?= number_format($total, 2) ?></th>
                    </tr>
                </tfoot>
            </table>
            <div class="signature-row">
                <div class="signature-box"><?= htmlspecialchars($branding['prepared_by_label']) ?></div>
                <div class="signature-box"><?= htmlspecialchars($branding['approved_by_label']) ?></div>
            </div>
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
