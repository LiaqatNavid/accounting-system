<?php
session_start();
include "../../includes/auth_check.php";
include "../../config/db.php";
include "../../includes/branding.php";
if (!rbac_can_do('print_records')) { header("Location: purchase_grid_view.php?denied=1"); exit; }

$voucher_no = intval($_GET['voucher_no'] ?? 0);
if ($voucher_no <= 0) {
    die("Invalid Voucher");
}

$master = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT p.*, c.ACCOUNT_DESCRIPTION AS account_description
    FROM purchase_issue_return p
    LEFT JOIN all_chart_of_accounts c ON p.account_no = c.account_no
    WHERE p.voucher_no = $voucher_no
"));

if (!$master) {
    die("Voucher Not Found");
}

$detail = mysqli_query($conn, "SELECT * FROM purchase_issue_bill WHERE voucher_no = $voucher_no");
$branding = get_branding_settings();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Purchase Voucher Print</title>
<link rel="stylesheet" href="../../assets/css/bootstrap.min.css">
<style>
@page { margin: 14mm; }
body{margin:0;background:#eef3f9;color:#17324d;font:13px/1.45 "Segoe UI",Tahoma,Arial,sans-serif;-webkit-print-color-adjust:exact;print-color-adjust:exact;}
.print-shell{max-width:1000px;margin:24px auto;padding:0 12px;}
.print-card{background:#fff;border:1px solid #d9e5f2;border-radius:18px;overflow:hidden;box-shadow:0 18px 40px rgba(15,64,120,.08);}
.print-band{background:linear-gradient(135deg,#0d6efd,#0b4fa8);color:#fff;padding:18px 24px;}
.print-band h1{margin:0;font-size:24px;letter-spacing:.06em;}
.print-band p{margin:4px 0 0;color:rgba(255,255,255,.85);font-size:12px;}
.print-body{padding:22px 24px 24px;}
.meta-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px;margin-bottom:18px;}
.meta-box{border:1px solid #d9e5f2;border-radius:14px;padding:12px 14px;background:#f9fbfe;}
.meta-label{display:block;color:#5b7795;font-size:11px;text-transform:uppercase;letter-spacing:.06em;margin-bottom:3px;}
.meta-value{color:#123355;font-weight:600;}
.table thead th{background:#e8f1ff;color:#14416f;border-color:#cfe0f5;font-size:12px;}
.table td,.table th{padding:9px 10px !important;vertical-align:middle;border-color:#d9e5f2;}
.summary-grid{display:grid;grid-template-columns:repeat(1,minmax(0,1fr));gap:12px;margin-top:18px;}
.summary-box{border:1px solid #d9e5f2;border-radius:14px;padding:12px 14px;background:#f8fbff;}
.summary-label{color:#5b7795;font-size:11px;text-transform:uppercase;letter-spacing:.05em;}
.summary-value{color:#123355;font-size:18px;font-weight:700;}
.action-bar{padding:16px 24px 24px;text-align:center;}
@media print{body{background:#fff}.print-shell{max-width:none;margin:0;padding:0}.print-card{box-shadow:none;border:none}.no-print{display:none !important}}
</style>
</head>
<body>
<div class="print-shell">
    <div class="print-card">
        <div class="print-band">
            <h1><?= htmlspecialchars($master['issue_or_return']) ?></h1>
            <p><?= htmlspecialchars($branding['company_name']) ?> Print View</p>
        </div>
        <div class="print-body">
            <div class="meta-grid">
                <div class="meta-box"><span class="meta-label">Voucher No</span><div class="meta-value"><?= htmlspecialchars($master['display_voucher_no'] ?: $voucher_no) ?></div></div>
                <div class="meta-box"><span class="meta-label">Voucher Date</span><div class="meta-value"><?= date('d-M-Y', strtotime($master['record_date'])) ?></div></div>
                <div class="meta-box" style="grid-column:1 / -1;"><span class="meta-label">Account</span><div class="meta-value"><?= htmlspecialchars($master['account_no'] . ' - ' . $master['account_description']) ?></div></div>
            </div>
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th width="6%">#</th>
                        <th width="46%">Item</th>
                        <th width="12%" class="text-end">Qty</th>
                        <th width="18%" class="text-end">Rate</th>
                        <th width="18%" class="text-end">Total</th>
                    </tr>
                </thead>
                <tbody>
                <?php $sr = 1; while ($d = mysqli_fetch_assoc($detail)) { ?>
                    <tr>
                        <td><?= $sr++ ?></td>
                        <td><?= htmlspecialchars($d['item_name']) ?></td>
                        <td class="text-end"><?= number_format($d['qty'], 2) ?></td>
                        <td class="text-end"><?= number_format($d['net_amount'], 2) ?></td>
                        <td class="text-end"><?= number_format($d['item_total'], 2) ?></td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
            <div class="summary-grid">
                <div class="summary-box">
                    <div class="summary-label">Net Amount</div>
                    <div class="summary-value"><?= number_format($master['net_amount'], 2) ?></div>
                </div>
            </div>
        </div>
        <div class="action-bar no-print">
            <button class="btn btn-primary" onclick="window.print()">Print</button>
            <button class="btn btn-outline-secondary" onclick="window.close()">Close</button>
        </div>
    </div>
</div>
</body>
</html>
