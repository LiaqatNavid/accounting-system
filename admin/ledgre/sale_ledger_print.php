<?php
session_start();
include "../../includes/auth_check.php";
include "../../config/db.php";
if (!rbac_can_do('print_records')) { header("Location: sale_ledger.php?denied=1"); exit; }

$account_no = $_GET['account_no'] ?? '';
$from_date = $_GET['from_date'] ?? '';
$to_date = $_GET['to_date'] ?? '';

$where = " WHERE 1=1 ";
if ($account_no != '') {
    $where .= " AND account_no='$account_no' ";
}
if ($from_date != '') {
    $where .= " AND DATE(record_date) >= '$from_date' ";
}
if ($to_date != '') {
    $where .= " AND DATE(record_date) <= '$to_date' ";
}

$account_description = '';
if ($account_no != '') {
    $acc_q = mysqli_query($conn, "SELECT account_description FROM chart_of_account WHERE account_no='$account_no' LIMIT 1");
    if ($acc_q && mysqli_num_rows($acc_q) > 0) {
        $acc = mysqli_fetch_assoc($acc_q);
        $account_description = $acc['account_description'] ?? '';
    }
}

$opening_balance = 0;
if ($account_no != '' && $from_date != '') {
    $ob_q = mysqli_query($conn, "
        SELECT IFNULL(SUM(debit - credit),0) AS opening
        FROM sale_ledger
        WHERE account_no='$account_no'
        AND DATE(record_date) < '$from_date'
    ");
    if ($ob_q) {
        $ob = mysqli_fetch_assoc($ob_q);
        $opening_balance = $ob['opening'] ?? 0;
    }
}

$sql = "SELECT * FROM sale_ledger $where ORDER BY DATE(record_date) ASC, voucher_no ASC";
$result = mysqli_query($conn, $sql);
$running = $opening_balance;
$rows = [];

if ($account_no != '' && $from_date != '') {
    $rows[] = [
        'voucher_print' => 'Previous Balance',
        'record_date' => $from_date,
        'remarks' => 'Balance Before ' . $from_date,
        'voucher_type' => 'Opening',
        'debit' => 0,
        'credit' => 0,
        'running_balance' => $opening_balance,
        'balance_type' => ($opening_balance > 0 ? 'Debit' : ($opening_balance < 0 ? 'Credit' : 'Balanced'))
    ];
}

while ($result && $row = mysqli_fetch_assoc($result)) {
    $debit = $row['debit'] ?? 0;
    $credit = $row['credit'] ?? 0;
    $running += ($debit - $credit);
    $row['voucher_type'] = $row['voucher_type'] ?? 'Sale';
    $row['running_balance'] = $running;
    $row['balance_type'] = ($running > 0 ? 'Debit' : ($running < 0 ? 'Credit' : 'Balanced'));
    $rows[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Sale Ledger Print</title>
<link rel="stylesheet" href="../../assets/css/bootstrap.min.css">
<style>
@page { margin: 12mm; }
body{margin:0;background:#eef3f9;color:#17324d;font:12px/1.4 "Segoe UI",Tahoma,Arial,sans-serif;-webkit-print-color-adjust:exact;print-color-adjust:exact;}
.print-shell{max-width:1180px;margin:24px auto;padding:0 12px;}
.print-card{background:#fff;border:1px solid #d9e5f2;border-radius:18px;overflow:hidden;box-shadow:0 18px 40px rgba(15,64,120,.08);}
.print-band{background:linear-gradient(135deg,#0d6efd,#0b4fa8);color:#fff;padding:16px 22px;}
.print-band h1{margin:0;font-size:22px;}
.print-body{padding:20px 22px 22px;}
.meta-grid{display:grid;grid-template-columns:2fr 1fr 1fr;gap:12px;margin-bottom:16px;}
.meta-box{border:1px solid #d9e5f2;border-radius:14px;padding:10px 12px;background:#f9fbfe;}
.meta-label{display:block;color:#5b7795;font-size:10px;text-transform:uppercase;letter-spacing:.06em;margin-bottom:3px;}
.meta-value{font-weight:600;color:#123355;}
.table thead th{background:#e8f1ff;color:#14416f;border-color:#cfe0f5;font-size:11px;white-space:nowrap;}
.table td,.table th{padding:8px !important;vertical-align:middle;border-color:#d9e5f2;}
.text-credit{color:#c0392b;font-weight:700;}
.text-debit{color:#198754;font-weight:700;}
.text-balanced{color:#6c757d;font-weight:700;}
.action-bar{padding:14px 22px 22px;text-align:center;}
@media print{body{background:#fff}.print-shell{max-width:none;margin:0;padding:0}.print-card{box-shadow:none;border:none}.no-print{display:none !important}}
</style>
</head>
<body onload="window.print()">
<div class="print-shell">
    <div class="print-card">
        <div class="print-band"><h1>SALE LEDGER</h1></div>
        <div class="print-body">
            <div class="meta-grid">
                <div class="meta-box"><span class="meta-label">Account</span><div class="meta-value"><?= htmlspecialchars(trim($account_no . ' - ' . $account_description, ' -')) ?></div></div>
                <div class="meta-box"><span class="meta-label">From</span><div class="meta-value"><?= htmlspecialchars($from_date ?: 'Beginning') ?></div></div>
                <div class="meta-box"><span class="meta-label">To</span><div class="meta-value"><?= htmlspecialchars($to_date ?: 'Today') ?></div></div>
            </div>
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Voucher Date</th>
                        <th>Voucher</th>
                        <th>Remarks</th>
                        <th>Type</th>
                        <th class="text-end">Debit</th>
                        <th class="text-end">Credit</th>
                        <th class="text-end">Running Balance</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $row) { $status = $row['balance_type'] ?? 'Balanced'; $statusClass = $status === 'Debit' ? 'text-debit' : ($status === 'Credit' ? 'text-credit' : 'text-balanced'); ?>
                    <tr>
                        <td><?= htmlspecialchars($row['record_date'] ?? '') ?></td>
                        <td><?= htmlspecialchars($row['voucher_print'] ?? '') ?></td>
                        <td><?= htmlspecialchars($row['remarks'] ?? '') ?></td>
                        <td><?= htmlspecialchars($row['voucher_type'] ?? 'Sale') ?></td>
                        <td class="text-end"><?= number_format($row['debit'] ?? 0, 2) ?></td>
                        <td class="text-end"><?= number_format($row['credit'] ?? 0, 2) ?></td>
                        <td class="text-end"><?= number_format($row['running_balance'] ?? 0, 2) ?></td>
                        <td class="<?= $statusClass ?>"><?= htmlspecialchars($status) ?></td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
        <div class="action-bar no-print">
            <button class="btn btn-primary" onclick="window.print()">Print</button>
            <button class="btn btn-outline-secondary" onclick="window.close()">Close</button>
        </div>
    </div>
</div>
</body>
</html>
