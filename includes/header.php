<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!ob_get_level()) {
    ob_start();
}

require_once __DIR__ . "/rbac.php";
require_once __DIR__ . "/branding.php";

$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
$base_url = (strpos($scriptName, '/accounting-system/') !== false) ? '/accounting-system/' : '/';
$branding = get_branding_settings();

// When a page sets $minimal_layout = true before including this header,
// the topbar and footer will be suppressed for that page (e.g., login).
$minimal_layout = isset($minimal_layout) && $minimal_layout;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Accounting ERP</title>

<!-- =======================
        CSS FILES
======================= -->
<link href="<?php echo $base_url; ?>assets/css/bootstrap.min.css" rel="stylesheet">
<link href="<?php echo $base_url; ?>assets/bootstrap-icons-1.13.1/font/bootstrap-icons.css" rel="stylesheet">

<script src="<?php echo $base_url; ?>assets/js/jquery-3.7.1.min.js"></script>
<script src="<?php echo $base_url; ?>assets/js/bootstrap.bundle.min.js"></script>

<!-- =======================
        CUSTOM STYLES
======================= -->
<style>
body {
    margin: 0;
    background:
        radial-gradient(circle at top left, rgba(13,110,253,0.08), transparent 24%),
        linear-gradient(180deg, #f6f8fc 0%, #eef3f9 100%);
    font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
    color: #0f172a;
}

/* ================= Top Fixed Navbar ================= */
.fixed-top-nav {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    width: 100%;
    z-index: 1040;
    background: linear-gradient(135deg, #0a2558 0%, #0d47a1 55%, #1565c0 100%);
    box-shadow: 0 4px 18px rgba(8, 23, 58, 0.22);
    border-bottom: 1px solid rgba(255, 255, 255, 0.12);
}

.navbar-brand-erp {
    display: flex;
    align-items: center;
    gap: 10px;
    color: #ffffff !important;
    font-weight: 800;
    font-size: 16px;
    text-decoration: none;
    padding: 6px 0;
    margin-right: 14px;
}

.brand-logo-img {
    width: 34px;
    height: 34px;
    object-fit: contain;
    border-radius: 8px;
    background: #ffffff;
    padding: 3px;
    box-shadow: 0 4px 10px rgba(0,0,0,0.18);
}

.fixed-top-nav .nav-link {
    color: rgba(255, 255, 255, 0.92) !important;
    font-weight: 600;
    font-size: 13.5px;
    padding: 14px 11px !important;
    display: flex;
    align-items: center;
    gap: 6px;
    border-bottom: 3px solid transparent;
    transition: background-color 150ms ease, border-color 150ms ease, color 150ms ease;
    white-space: nowrap;
}

.fixed-top-nav .nav-link:hover,
.fixed-top-nav .nav-link:focus,
.fixed-top-nav .nav-item.show > .nav-link {
    color: #ffffff !important;
    background: rgba(255, 255, 255, 0.12);
    border-bottom-color: #38bdf8;
}

.fixed-top-nav .nav-item.active > .nav-link {
    color: #ffffff !important;
    background: rgba(255, 255, 255, 0.18);
    border-bottom-color: #38bdf8;
}

/* Dropdown Menus */
.fixed-top-nav .dropdown-menu {
    border: 1px solid rgba(15, 23, 42, 0.08);
    border-radius: 12px;
    box-shadow: 0 12px 30px rgba(8, 23, 58, 0.18);
    padding: 8px;
    min-width: 220px;
    margin-top: 0;
    animation: fadeInMenu 140ms ease-out;
}

@keyframes fadeInMenu {
    from { opacity: 0; transform: translateY(6px); }
    to { opacity: 1; transform: translateY(0); }
}

.fixed-top-nav .dropdown-item {
    font-size: 13.5px;
    font-weight: 500;
    padding: 8px 14px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    gap: 10px;
    color: #1e293b;
    transition: all 120ms ease;
}

.fixed-top-nav .dropdown-item i {
    font-size: 15px;
    width: 18px;
    text-align: center;
    color: #0d6efd;
    transition: transform 120ms ease;
}

.fixed-top-nav .dropdown-item:hover,
.fixed-top-nav .dropdown-item:focus {
    background-color: #eef4ff;
    color: #0d6efd;
    font-weight: 600;
}

.fixed-top-nav .dropdown-item:hover i {
    transform: scale(1.15);
}

.fixed-top-nav .dropdown-item.active {
    background: linear-gradient(135deg, #0d6efd, #0b5ed7);
    color: #ffffff !important;
    font-weight: 600;
}

.fixed-top-nav .dropdown-item.active i {
    color: #ffffff !important;
}

/* Hover dropdowns on desktop (992px and up) */
@media (min-width: 992px) {
    .fixed-top-nav .nav-item.dropdown:hover > .dropdown-menu {
        display: block;
        margin-top: 0;
    }
}

/* User Badge in Top Nav */
.user-badge-nav {
    background: rgba(255, 255, 255, 0.16);
    border: 1px solid rgba(255, 255, 255, 0.22);
    color: #ffffff;
    padding: 6px 14px;
    border-radius: 999px;
    font-size: 13px;
    font-weight: 600;
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.15);
    white-space: nowrap;
}

/* Buttons on colored cards (dashboard widgets) */
.card.text-white .view-widget,
.card.text-white .btn {
    background: rgba(255, 255, 255, 0.96);
    color: #0d6efd;
    font-weight: 700;
    border: 1px solid rgba(255, 255, 255, 0.9);
    border-radius: 8px;
    padding: .38rem .7rem;
    box-shadow: 0 8px 22px rgba(3,37,76,0.06);
    transition: transform 180ms ease, box-shadow 180ms ease, opacity 180ms;
}

.card.text-white .view-widget:hover,
.card.text-white .btn:hover {
    transform: translateY(-5px);
    box-shadow: 0 18px 40px rgba(3,37,76,0.12);
    opacity: 1;
}

.card.text-white .btn-sm { padding: .32rem .6rem; font-size: .88rem; }

/* Global button improvements */
.btn {
    border-radius: 8px;
    box-shadow: 0 6px 18px rgba(3,37,76,0.04);
    transition: transform 150ms ease, box-shadow 150ms ease, opacity 150ms;
}

.btn:focus { box-shadow: 0 8px 22px rgba(3,37,76,0.08); }

.btn-primary {
    background-color: #0d6efd;
    border-color: #0d6efd;
    color: #ffffff;
}
.btn-primary:hover, .btn-primary:focus { background-color: #0b5ed7; border-color: #0b5ed7; }

.bg-primary .btn-light, .card.bg-primary .btn-light {
    background: rgba(255,255,255,0.96) !important;
    color: #0d6efd !important;
    font-weight: 700;
}

/* ================= Content Area ================= */
.content {
    margin-left: 0 !important;
    padding: <?php echo $minimal_layout ? '24px 28px' : '76px 28px 24px'; ?>;
    min-height: calc(100vh - 80px);
}

.global-status-alert {
    border: 0;
    border-radius: 12px;
    box-shadow: 0 10px 26px rgba(15,23,42,0.06);
}

.prev-balance-row {
    background-color: #ea8657 !important;
    font-weight: bold;
}

@media (max-width: 991.98px) {
    .content {
        padding: <?php echo $minimal_layout ? '18px' : '72px 16px 18px'; ?> !important;
    }
    .fixed-top-nav .navbar-collapse {
        background: #09214e;
        padding: 12px;
        border-radius: 12px;
        margin-top: 8px;
        max-height: 80vh;
        overflow-y: auto;
    }
    .fixed-top-nav .nav-link {
        padding: 10px 12px !important;
    }
    .fixed-top-nav .dropdown-menu {
        background: #0d3275;
        border-color: rgba(255, 255, 255, 0.15);
    }
    .fixed-top-nav .dropdown-item {
        color: rgba(255, 255, 255, 0.9);
    }
    .fixed-top-nav .dropdown-item i {
        color: #93c5fd;
    }
    .fixed-top-nav .dropdown-item:hover {
        background-color: rgba(255, 255, 255, 0.16);
        color: #fff;
    }
}
</style>
</head>

<body>

<?php if (!$minimal_layout): ?>
<!-- =======================
    FIXED TOP NAVBAR
======================= -->
<nav class="navbar navbar-expand-lg fixed-top-nav navbar-dark">
    <div class="container-fluid px-3">
        <!-- Brand / Logo -->
        <a class="navbar-brand-erp" href="<?php echo $base_url; ?>admin/dashboard/dashboard.php">
            <?php if (!empty($branding['logo_url'])): ?>
                <img src="<?php echo htmlspecialchars($branding['logo_url']); ?>" alt="Logo" class="brand-logo-img">
            <?php endif; ?>
            <span class="d-none d-sm-inline"><?php echo htmlspecialchars($branding['company_name']); ?></span>
        </a>

        <!-- Mobile Toggler -->
        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#topNavbarCollapse" aria-controls="topNavbarCollapse" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="topNavbarCollapse">
            <?php if (isset($_SESSION['user_id'])): ?>
            <?php
            $canDashboard          = rbac_can_access('dashboard_home');
            $canAiDashboard        = rbac_can_access('dashboard_ai');
            $canBackup             = rbac_can_access('database_backup');
            $canCompanySettings    = rbac_can_access('company_settings_page');
            $canPeriodClosing      = rbac_can_access('period_closing_page');
            $canRecycleBin         = rbac_can_access('recycle_bin_page');
            $canSystemActivity     = rbac_can_access('system_activity_page');
            $canApprovalHistory    = rbac_can_access('approval_history_page');
            $canUsersPage          = rbac_can_access('users_page');
            $canRolesPage          = rbac_can_access('roles_page');
            $canUserRolesPage      = rbac_can_access('user_roles_page');
            $canRolePermissionsPage= rbac_can_access('role_permissions_page');
            $canAccountLedger      = rbac_can_access('account_ledger');
            $canTrialBalance       = rbac_can_access('trial_balance');
            $canChartOfAccounts    = rbac_can_access('chart_of_accounts');
            $canOpeningBalance     = rbac_can_access('opening_balance');
            $canPaymentVoucherAdd  = rbac_can_access('payment_voucher_add');
            $canPaymentVoucherList = rbac_can_access('payment_voucher_list');
            $canReceiptVoucherAdd  = rbac_can_access('receipt_voucher_add');
            $canReceiptVoucherList = rbac_can_access('receipt_voucher_list');
            $canGeneralVoucherAdd  = rbac_can_access('general_voucher_add');
            $canGeneralVoucherList = rbac_can_access('general_voucher_list');
            $canCashBook           = rbac_can_access('cash_book');
            $canBankBook           = rbac_can_access('bank_book');
            $canCategoriesPage     = rbac_can_access('categories_page');
            $canInventoryPage      = rbac_can_access('inventory_page');
            $canStockAdjustmentAdd = rbac_can_access('stock_adjustment_add');
            $canStockAdjustmentList= rbac_can_access('stock_adjustment_list');
            $canStockLedger        = rbac_can_access('stock_ledger');
            $canPurchaseAdd        = rbac_can_access('purchase_add');
            $canPurchaseList       = rbac_can_access('purchase_list');
            $canPurchaseLedger     = rbac_can_access('purchase_ledger');
            $canSaleAdd            = rbac_can_access('sale_add');
            $canSaleList           = rbac_can_access('sale_list');
            $canSaleLedger         = rbac_can_access('sale_ledger');
            ?>

            <!-- Top Menus -->
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <!-- 1. DASHBOARD -->
                <?php if ($canDashboard || $canAiDashboard): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="navDashboard" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-speedometer2"></i> Dashboard
                    </a>
                    <ul class="dropdown-menu shadow" aria-labelledby="navDashboard">
                        <?php if ($canDashboard): ?>
                        <li><a class="dropdown-item" href="<?php echo $base_url; ?>admin/dashboard/dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
                        <?php endif; ?>
                        <?php if ($canAiDashboard): ?>
                        <li><a class="dropdown-item" href="<?php echo $base_url; ?>admin/dashboard/account_ai_dashboard.php"><i class="bi bi-stars"></i> AI Dashboard</a></li>
                        <?php endif; ?>
                    </ul>
                </li>
                <?php endif; ?>

                <!-- 2. ACCOUNTS & VOUCHERS -->
                <?php if ($canChartOfAccounts || $canOpeningBalance || $canPaymentVoucherAdd || $canPaymentVoucherList || $canReceiptVoucherAdd || $canReceiptVoucherList || $canGeneralVoucherAdd || $canGeneralVoucherList): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="navAccounts" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-journal-text"></i> Accounts &amp; Vouchers
                    </a>
                    <ul class="dropdown-menu shadow" aria-labelledby="navAccounts">
                        <?php if ($canChartOfAccounts): ?>
                        <li><a class="dropdown-item" href="<?php echo $base_url; ?>admin/chartofaccount/chartofaccount.php"><i class="bi bi-journal-text"></i> Chart of Accounts</a></li>
                        <?php endif; ?>
                        <?php if ($canOpeningBalance): ?>
                        <li><a class="dropdown-item" href="<?php echo $base_url; ?>admin/openingbalance/openingbalance.php"><i class="bi bi-cash-stack"></i> Opening Balance</a></li>
                        <?php endif; ?>
                        <?php if ($canPaymentVoucherAdd): ?>
                        <li><a class="dropdown-item" href="<?php echo $base_url; ?>admin/payment_voucher/payment_voucher.php"><i class="bi bi-credit-card"></i> Payment Voucher</a></li>
                        <?php endif; ?>
                        <?php if ($canPaymentVoucherList): ?>
                        <li><a class="dropdown-item" href="<?php echo $base_url; ?>admin/payment_voucher/payment_voucher_grid.php"><i class="bi bi-card-checklist"></i> Payment Voucher List</a></li>
                        <?php endif; ?>
                        <?php if ($canReceiptVoucherAdd): ?>
                        <li><a class="dropdown-item" href="<?php echo $base_url; ?>admin/receipt_voucher/receipt_voucher.php"><i class="bi bi-wallet2"></i> Receipt Voucher</a></li>
                        <?php endif; ?>
                        <?php if ($canReceiptVoucherList): ?>
                        <li><a class="dropdown-item" href="<?php echo $base_url; ?>admin/receipt_voucher/receipt_voucher_grid.php"><i class="bi bi-card-checklist"></i> Receipt Voucher List</a></li>
                        <?php endif; ?>
                        <?php if ($canGeneralVoucherAdd): ?>
                        <li><a class="dropdown-item" href="<?php echo $base_url; ?>admin/general_voucher/general_voucher.php"><i class="bi bi-receipt"></i> General Voucher</a></li>
                        <?php endif; ?>
                        <?php if ($canGeneralVoucherList): ?>
                        <li><a class="dropdown-item" href="<?php echo $base_url; ?>admin/general_voucher/general_voucher_list.php"><i class="bi bi-list-check"></i> General Voucher List</a></li>
                        <?php endif; ?>
                    </ul>
                </li>
                <?php endif; ?>

                <!-- 3. SALES & PURCHASES -->
                <?php if ($canSaleAdd || $canSaleList || $canPurchaseAdd || $canPurchaseList): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="navSales" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-cart-check"></i> Sales &amp; Purchases
                    </a>
                    <ul class="dropdown-menu shadow" aria-labelledby="navSales">
                        <?php if ($canSaleAdd): ?>
                        <li><a class="dropdown-item" href="<?php echo $base_url; ?>admin/sale/sale.php"><i class="bi bi-cart-check"></i> Sale Voucher</a></li>
                        <?php endif; ?>
                        <?php if ($canSaleList): ?>
                        <li><a class="dropdown-item" href="<?php echo $base_url; ?>admin/sale/sale_view.php"><i class="bi bi-receipt-cutoff"></i> Sale List</a></li>
                        <?php endif; ?>
                        <?php if ($canPurchaseAdd): ?>
                        <li><a class="dropdown-item" href="<?php echo $base_url; ?>admin/purchase/purchase.php"><i class="bi bi-cart-plus"></i> Purchase Voucher</a></li>
                        <?php endif; ?>
                        <?php if ($canPurchaseList): ?>
                        <li><a class="dropdown-item" href="<?php echo $base_url; ?>admin/purchase/purchase_grid_view.php"><i class="bi bi-bag-check"></i> Purchase List</a></li>
                        <?php endif; ?>
                    </ul>
                </li>
                <?php endif; ?>

                <!-- 4. INVENTORY -->
                <?php if ($canInventoryPage || $canCategoriesPage || $canStockAdjustmentAdd || $canStockAdjustmentList): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="navInventory" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-box-seam"></i> Inventory
                    </a>
                    <ul class="dropdown-menu shadow" aria-labelledby="navInventory">
                        <?php if ($canInventoryPage): ?>
                        <li><a class="dropdown-item" href="<?php echo $base_url; ?>admin/inventory/inventory.php"><i class="bi bi-box-seam"></i> Inventory Items</a></li>
                        <?php endif; ?>
                        <?php if ($canCategoriesPage): ?>
                        <li><a class="dropdown-item" href="<?php echo $base_url; ?>admin/inventory/category.php"><i class="bi bi-tags"></i> Categories</a></li>
                        <?php endif; ?>
                        <?php if ($canStockAdjustmentAdd): ?>
                        <li><a class="dropdown-item" href="<?php echo $base_url; ?>admin/stock_adjustment/stock_adjustment_add.php"><i class="bi bi-sliders"></i> Stock Adjustment</a></li>
                        <?php endif; ?>
                        <?php if ($canStockAdjustmentList): ?>
                        <li><a class="dropdown-item" href="<?php echo $base_url; ?>admin/stock_adjustment/stock_adjustment_list.php"><i class="bi bi-list-ul"></i> Adjusted Stock List</a></li>
                        <?php endif; ?>
                    </ul>
                </li>
                <?php endif; ?>

                <!-- 5. LEDGERS & REPORTS -->
                <?php if ($canTrialBalance || $canAccountLedger || $canCashBook || $canBankBook || $canSaleLedger || $canPurchaseLedger || $canStockLedger): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="navReports" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-journal-bookmark"></i> Ledgers &amp; Reports
                    </a>
                    <ul class="dropdown-menu shadow" aria-labelledby="navReports">
                        <?php if ($canTrialBalance): ?>
                        <li><a class="dropdown-item" href="<?php echo $base_url; ?>admin/ledgre/trail_balance_new.php"><i class="bi bi-scale"></i> Trial Balance</a></li>
                        <?php endif; ?>
                        <?php if ($canAccountLedger): ?>
                        <li><a class="dropdown-item" href="<?php echo $base_url; ?>admin/ledgre/account_ledger.php"><i class="bi bi-journal-bookmark"></i> Account Ledger</a></li>
                        <?php endif; ?>
                        <?php if ($canCashBook): ?>
                        <li><a class="dropdown-item" href="<?php echo $base_url; ?>admin/ledgre/cash_book.php"><i class="bi bi-cash"></i> Cash Book</a></li>
                        <?php endif; ?>
                        <?php if ($canBankBook): ?>
                        <li><a class="dropdown-item" href="<?php echo $base_url; ?>admin/ledgre/bank_book.php"><i class="bi bi-bank"></i> Bank Book</a></li>
                        <?php endif; ?>
                        <?php if ($canSaleLedger): ?>
                        <li><a class="dropdown-item" href="<?php echo $base_url; ?>admin/ledgre/sale_ledger.php"><i class="bi bi-journal-text"></i> Sale Ledger</a></li>
                        <?php endif; ?>
                        <?php if ($canPurchaseLedger): ?>
                        <li><a class="dropdown-item" href="<?php echo $base_url; ?>admin/ledgre/purchase_ledger_view.php"><i class="bi bi-journal"></i> Purchase Ledger</a></li>
                        <?php endif; ?>
                        <?php if ($canStockLedger): ?>
                        <li><a class="dropdown-item" href="<?php echo $base_url; ?>admin/ledgre/stock_ledgre.php"><i class="bi bi-clipboard-data"></i> Stock Ledger</a></li>
                        <?php endif; ?>
                    </ul>
                </li>
                <?php endif; ?>

                <!-- 6. USER MANAGEMENT -->
                <?php if ($canUsersPage || $canRolesPage || $canUserRolesPage || $canRolePermissionsPage): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="navUsers" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-people"></i> Users
                    </a>
                    <ul class="dropdown-menu shadow" aria-labelledby="navUsers">
                        <?php if ($canUsersPage): ?>
                        <li><a class="dropdown-item" href="<?php echo $base_url; ?>admin/users.php"><i class="bi bi-people"></i> Users</a></li>
                        <?php endif; ?>
                        <?php if ($canRolesPage): ?>
                        <li><a class="dropdown-item" href="<?php echo $base_url; ?>admin/roles.php"><i class="bi bi-shield-lock"></i> Roles</a></li>
                        <?php endif; ?>
                        <?php if ($canUserRolesPage): ?>
                        <li><a class="dropdown-item" href="<?php echo $base_url; ?>admin/user_roles.php"><i class="bi bi-person-badge"></i> User Roles</a></li>
                        <?php endif; ?>
                        <?php if ($canRolePermissionsPage): ?>
                        <li><a class="dropdown-item" href="<?php echo $base_url; ?>admin/role_permissions.php"><i class="bi bi-sliders2"></i> Role Permissions</a></li>
                        <?php endif; ?>
                    </ul>
                </li>
                <?php endif; ?>

                <!-- 7. SYSTEM & SETTINGS -->
                <?php if ($canCompanySettings || $canPeriodClosing || $canApprovalHistory || $canSystemActivity || $canRecycleBin || $canBackup): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="navSettings" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-gear"></i> Settings
                    </a>
                    <ul class="dropdown-menu shadow dropdown-menu-end" aria-labelledby="navSettings">
                        <?php if ($canCompanySettings): ?>
                        <li><a class="dropdown-item" href="<?php echo $base_url; ?>admin/company_settings.php"><i class="bi bi-building-gear"></i> Company Settings</a></li>
                        <?php endif; ?>
                        <?php if ($canPeriodClosing): ?>
                        <li><a class="dropdown-item" href="<?php echo $base_url; ?>admin/period_closing.php"><i class="bi bi-calendar2-x"></i> Period Closing</a></li>
                        <?php endif; ?>
                        <?php if ($canApprovalHistory): ?>
                        <li><a class="dropdown-item" href="<?php echo $base_url; ?>admin/approval_history.php"><i class="bi bi-check2-square"></i> Approval History</a></li>
                        <?php endif; ?>
                        <?php if ($canSystemActivity): ?>
                        <li><a class="dropdown-item" href="<?php echo $base_url; ?>admin/system_activity.php"><i class="bi bi-clock-history"></i> System Activity</a></li>
                        <?php endif; ?>
                        <?php if ($canRecycleBin): ?>
                        <li><a class="dropdown-item" href="<?php echo $base_url; ?>admin/recycle_bin.php"><i class="bi bi-recycle"></i> Recycle Bin</a></li>
                        <?php endif; ?>
                        <?php if ($canBackup): ?>
                        <li><a class="dropdown-item" href="<?php echo $base_url; ?>admin/dashboard/database_backup.php"><i class="bi bi-database-down"></i> Backup Database</a></li>
                        <?php endif; ?>
                    </ul>
                </li>
                <?php endif; ?>
            </ul>

            <!-- Right: User info & Logout -->
            <div class="d-flex align-items-center gap-2 py-2 py-lg-0">
                <span class="user-badge-nav d-none d-md-inline-flex align-items-center">
                    <i class="bi bi-person-circle me-1"></i>
                    <span><?php echo htmlspecialchars($_SESSION['user_name'] ?? ''); ?></span>
                    <?php if (!empty($_SESSION['role_name'])): ?>
                        <span class="opacity-75 ms-1">| <?php echo htmlspecialchars($_SESSION['role_name']); ?></span>
                    <?php endif; ?>
                </span>
                <a href="<?php echo $base_url; ?>logout.php" class="btn btn-sm btn-danger d-inline-flex align-items-center gap-1 text-white fw-bold shadow-sm" style="border-radius: 8px; padding: 6px 12px;">
                    <i class="bi bi-box-arrow-right"></i> <span>Logout</span>
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>
</nav>
<?php endif; ?>

<!-- =======================
    CONTENT AREA
======================= -->
<div class="content">

<?php if (!$minimal_layout): ?>
<?php
$globalStatusAlert = null;

if (isset($_GET['updated']) && $_GET['updated'] == '1') {
    $globalStatusAlert = ['type' => 'success', 'message' => 'Record updated successfully.'];
} elseif ((isset($_GET['deleted']) && $_GET['deleted'] == '1') || (isset($_GET['msg']) && $_GET['msg'] === 'deleted')) {
    $globalStatusAlert = ['type' => 'warning', 'message' => 'Record deleted successfully.'];
} elseif (isset($_GET['saved']) && $_GET['saved'] == '1') {
    $globalStatusAlert = ['type' => 'success', 'message' => 'Record saved successfully.'];
} elseif (isset($_GET['timeout']) && $_GET['timeout'] == '1') {
    $globalStatusAlert = ['type' => 'warning', 'message' => 'Session expired after 10 minutes of inactivity.'];
} elseif (isset($_GET['denied']) && $_GET['denied'] == '1') {
    $globalStatusAlert = ['type' => 'danger', 'message' => 'Access denied for your current role.'];
} elseif (isset($_GET['approved']) && $_GET['approved'] == '1') {
    $globalStatusAlert = ['type' => 'success', 'message' => 'Voucher approved successfully.'];
} elseif (isset($_GET['reopened']) && $_GET['reopened'] == '1') {
    $globalStatusAlert = ['type' => 'warning', 'message' => 'Voucher moved back to draft successfully.'];
} elseif (isset($_GET['locked']) && $_GET['locked'] == '1') {
    $globalStatusAlert = ['type' => 'danger', 'message' => 'Approved voucher is locked for edit or delete.'];
} elseif (isset($_GET['period_locked']) && $_GET['period_locked'] == '1') {
    $globalStatusAlert = ['type' => 'danger', 'message' => 'The selected transaction date belongs to a closed accounting period.'];
} elseif (isset($_GET['restored']) && $_GET['restored'] == '1') {
    $globalStatusAlert = ['type' => 'success', 'message' => 'Record restored successfully.'];
} elseif (isset($_GET['backup_restored']) && $_GET['backup_restored'] == '1') {
    $globalStatusAlert = ['type' => 'success', 'message' => 'Database backup restored successfully.'];
}
?>
<?php if ($globalStatusAlert): ?>
<div class="alert alert-<?php echo htmlspecialchars($globalStatusAlert['type']); ?> global-status-alert alert-dismissible fade show" role="alert">
    <?php echo htmlspecialchars($globalStatusAlert['message']); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>
<?php endif; ?>

<?php if (!$minimal_layout): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var currentPath = window.location.pathname.replace(/\/+$/, '');
    
    // Highlight active link and parent dropdown in top navbar
    document.querySelectorAll('.fixed-top-nav .dropdown-item').forEach(function (link) {
        var linkPath = new URL(link.href, window.location.origin).pathname.replace(/\/+$/, '');
        if (linkPath === currentPath) {
            link.classList.add('active');
            var parentDropdown = link.closest('.nav-item.dropdown');
            if (parentDropdown) {
                parentDropdown.classList.add('active');
            }
        }
    });

    // 10 minutes auto-logout
    var idleLimitMs = 10 * 60 * 1000;
    var logoutUrl = '<?php echo $base_url; ?>logout.php?timeout=1';
    var idleTimer = null;

    function scheduleAutoLogout() {
        window.clearTimeout(idleTimer);
        idleTimer = window.setTimeout(function () {
            window.location.href = logoutUrl;
        }, idleLimitMs);
    }

    ['click', 'mousemove', 'keydown', 'scroll', 'touchstart'].forEach(function (eventName) {
        document.addEventListener(eventName, scheduleAutoLogout, { passive: true });
    });

    scheduleAutoLogout();

    // Auto-dismiss status alerts after 4 seconds
    window.setTimeout(function () {
        document.querySelectorAll('.global-status-alert').forEach(function (alertEl) {
            if (window.bootstrap && bootstrap.Alert) {
                bootstrap.Alert.getOrCreateInstance(alertEl).close();
            } else {
                alertEl.remove();
            }
        });
    }, 4000);
});

function openPrintPopup(url) {
    var features = [
        'width=1100',
        'height=750',
        'left=120',
        'top=80',
        'resizable=yes',
        'scrollbars=yes',
        'toolbar=no',
        'menubar=no',
        'location=no',
        'status=no'
    ].join(',');

    var popup = window.open(url, 'print_popup', features);
    if (popup) {
        popup.focus();
    }
    return false;
}
</script>
<?php endif; ?>
