<?php

function rbac_str_ends_with(string $haystack, string $needle): bool
{
    if ($needle === '') {
        return true;
    }

    $needleLength = strlen($needle);
    if ($needleLength > strlen($haystack)) {
        return false;
    }

    return substr($haystack, -$needleLength) === $needle;
}

function rbac_str_contains(string $haystack, string $needle): bool
{
    if ($needle === '') {
        return true;
    }

    return strpos($haystack, $needle) !== false;
}

function rbac_table_exists(mysqli $conn, string $tableName): bool
{
    $safeTable = mysqli_real_escape_string($conn, $tableName);
    $result = mysqli_query($conn, "
        SELECT 1
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = '{$safeTable}'
        LIMIT 1
    ");

    return $result && mysqli_num_rows($result) > 0;
}

function rbac_base_path(): string
{
    $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $adminPos = strpos($scriptName, '/admin/');

    if ($adminPos !== false) {
        return substr($scriptName, 0, $adminPos);
    }

    $dir = str_replace('\\', '/', dirname($scriptName));
    if ($dir === '/' || $dir === '\\' || $dir === '.') {
        return '';
    }

    return rtrim($dir, '/');
}

function rbac_redirect_path(string $path): string
{
    $basePath = rbac_base_path();
    return $basePath . $path;
}

function rbac_permission_catalog(): array
{
    return [
        'dashboard_home' => 'Dashboard',
        'dashboard_ai' => 'AI Dashboard',
        'database_backup' => 'Backup',
        'company_settings_page' => 'Company Settings',
        'period_closing_page' => 'Period Closing',
        'recycle_bin_page' => 'Recycle Bin',
        'system_activity_page' => 'System Activity',
        'approval_history_page' => 'Approval History',
        'users_page' => 'Users',
        'roles_page' => 'Roles',
        'user_roles_page' => 'User Roles',
        'role_permissions_page' => 'Role Permissions',
        'add_records' => 'Add Records',
        'edit_records' => 'Edit Records',
        'approve_records' => 'Approve Records',
        'delete_records' => 'Delete Records',
        'restore_records' => 'Restore Records',
        'print_records' => 'Print Records',
        'backup_restore' => 'Restore Database Backup',
        'account_ledger' => 'Account Ledger',
        'trial_balance' => 'Trial Balance',
        'chart_of_accounts' => 'Chart of Accounts',
        'opening_balance' => 'Opening Balance',
        'payment_voucher_add' => 'Payment Voucher',
        'payment_voucher_list' => 'Payment Voucher List',
        'receipt_voucher_add' => 'Receipt Voucher',
        'receipt_voucher_list' => 'Receipt Voucher List',
        'general_voucher_add' => 'General Voucher',
        'general_voucher_list' => 'General Voucher List',
        'cash_book' => 'Cash Book',
        'bank_book' => 'Bank Book',
        'categories_page' => 'Categories',
        'inventory_page' => 'Inventory',
        'stock_adjustment_add' => 'Stock Adjustment',
        'stock_adjustment_list' => 'Adjusted Stock List',
        'stock_ledger' => 'Stock Ledger',
        'purchase_add' => 'Purchase Voucher',
        'purchase_list' => 'Purchase List',
        'purchase_ledger' => 'Purchase Ledger',
        'sale_add' => 'Sale Voucher',
        'sale_list' => 'Sale List',
        'sale_ledger' => 'Sale Ledger'
    ];
}

function rbac_resolve_route_key(string $scriptPath): ?string
{
    $scriptPath = str_replace('\\', '/', $scriptPath);

    $map = [
        '/admin/dashboard/database_backup.php' => 'database_backup',
        '/admin/company_settings.php' => 'company_settings_page',
        '/admin/period_closing.php' => 'period_closing_page',
        '/admin/recycle_bin.php' => 'recycle_bin_page',
        '/admin/restore_record.php' => 'restore_records',
        '/admin/system_activity.php' => 'system_activity_page',
        '/admin/approval_history.php' => 'approval_history_page',
        '/admin/dashboard/account_ai_dashboard.php' => 'dashboard_ai',
        '/admin/dashboard/dashboard.php' => 'dashboard_home',
        '/admin/dashboard/widget_detail_popup.php' => 'dashboard_home',
        '/admin/users.php' => 'users_page',
        '/admin/roles.php' => 'roles_page',
        '/admin/user_roles.php' => 'user_roles_page',
        '/admin/role_permissions.php' => 'role_permissions_page',
        '/admin/edit_user.php' => 'users_page',
        '/admin/edit_role.php' => 'roles_page',
        '/admin/change_password.php' => 'users_page',
        '/admin/ledgre/account_ledger.php' => 'account_ledger',
        '/admin/ledgre/trail_balance_new.php' => 'trial_balance',
        '/admin/ledgre/cash_book.php' => 'cash_book',
        '/admin/ledgre/bank_book.php' => 'bank_book',
        '/admin/ledgre/stock_ledgre.php' => 'stock_ledger',
        '/admin/ledgre/purchase_ledger_view.php' => 'purchase_ledger',
        '/admin/ledgre/sale_ledger.php' => 'sale_ledger'
    ];

    foreach ($map as $needle => $routeKey) {
        if (rbac_str_ends_with($scriptPath, $needle)) {
            return $routeKey;
        }
    }

    $prefixes = [
        '/admin/chartofaccount/' => 'chart_of_accounts',
        '/admin/openingbalance/' => 'opening_balance',
        '/admin/payment_voucher/payment_voucher.php' => 'payment_voucher_add',
        '/admin/payment_voucher/payment_voucher_edit.php' => 'payment_voucher_add',
        '/admin/payment_voucher/payment_voucher_save.php' => 'payment_voucher_add',
        '/admin/payment_voucher/payment_voucher_update.php' => 'payment_voucher_add',
        '/admin/payment_voucher/payment_voucher_grid.php' => 'payment_voucher_list',
        '/admin/payment_voucher/payment_voucher_print.php' => 'payment_voucher_list',
        '/admin/payment_voucher/payment_voucher_delete.php' => 'payment_voucher_list',
        '/admin/payment_voucher/payment_voucher_approval.php' => 'payment_voucher_list',
        '/admin/receipt_voucher/receipt_voucher.php' => 'receipt_voucher_add',
        '/admin/receipt_voucher/receipt_voucher_edit.php' => 'receipt_voucher_add',
        '/admin/receipt_voucher/receipt_voucher_save.php' => 'receipt_voucher_add',
        '/admin/receipt_voucher/receipt_voucher_update.php' => 'receipt_voucher_add',
        '/admin/receipt_voucher/receipt_voucher_grid.php' => 'receipt_voucher_list',
        '/admin/receipt_voucher/receipt_voucher_print.php' => 'receipt_voucher_list',
        '/admin/receipt_voucher/receipt_voucher_delete.php' => 'receipt_voucher_list',
        '/admin/receipt_voucher/receipt_voucher_approval.php' => 'receipt_voucher_list',
        '/admin/general_voucher/general_voucher.php' => 'general_voucher_add',
        '/admin/general_voucher/general_voucher_edit.php' => 'general_voucher_add',
        '/admin/general_voucher/general_voucher_save.php' => 'general_voucher_add',
        '/admin/general_voucher/general_voucher_update.php' => 'general_voucher_add',
        '/admin/general_voucher/general_voucher_list.php' => 'general_voucher_list',
        '/admin/general_voucher/general_voucher_print.php' => 'general_voucher_list',
        '/admin/general_voucher/general_voucher_delete.php' => 'general_voucher_list',
        '/admin/general_voucher/general_voucher_approval.php' => 'general_voucher_list',
        '/admin/inventory/category.php' => 'categories_page',
        '/admin/inventory/inventory.php' => 'inventory_page',
        '/admin/inventory/edit_inventory.php' => 'inventory_page',
        '/admin/stock_adjustment/stock_adjustment_add.php' => 'stock_adjustment_add',
        '/admin/stock_adjustment/stock_adjustment_edit.php' => 'stock_adjustment_add',
        '/admin/stock_adjustment/stock_adjustment_save.php' => 'stock_adjustment_add',
        '/admin/stock_adjustment/stock_adjustment_update.php' => 'stock_adjustment_add',
        '/admin/stock_adjustment/stock_adjustment_list.php' => 'stock_adjustment_list',
        '/admin/stock_adjustment/stock_adjustment_delete.php' => 'stock_adjustment_list',
        '/admin/stock_adjustment/stock_adjustment_print.php' => 'stock_adjustment_list',
        '/admin/stock_adjustment/stock_adjustment_approval.php' => 'stock_adjustment_list',
        '/admin/purchase/purchase.php' => 'purchase_add',
        '/admin/purchase/purchase_edit.php' => 'purchase_add',
        '/admin/purchase/purchase_voucher_save.php' => 'purchase_add',
        '/admin/purchase/purchase_update.php' => 'purchase_add',
        '/admin/purchase/purchase_grid_view.php' => 'purchase_list',
        '/admin/purchase/purchase_delete.php' => 'purchase_list',
        '/admin/purchase/purchase_print.php' => 'purchase_list',
        '/admin/purchase/purchase_approval.php' => 'purchase_list',
        '/admin/sale/sale.php' => 'sale_add',
        '/admin/sale/sale_voucher_save.php' => 'sale_add',
        '/admin/sale/sale_voucher_update.php' => 'sale_add',
        '/admin/sale/sale_view.php' => 'sale_list',
        '/admin/sale/sale_delete.php' => 'sale_list',
        '/admin/sale/sale_print.php' => 'sale_list',
        '/admin/sale/sale_approval.php' => 'sale_list'
    ];

    foreach ($prefixes as $needle => $routeKey) {
        if (rbac_str_contains($scriptPath, $needle)) {
            return $routeKey;
        }
    }

    return null;
}

function rbac_default_permissions_by_role_name(string $roleName): array
{
    $roleName = strtolower(trim($roleName));

    $all = array_keys(rbac_permission_catalog());

    $defaults = [
        'admin' => $all,
        'power' => [],
        'manager' => [],
        'user' => []
    ];

    return $defaults[$roleName] ?? [];
}

function rbac_sync_admin_permissions(mysqli $conn): void
{
    $adminRoleResult = mysqli_query($conn, "SELECT id FROM roles WHERE LOWER(role_name) = 'admin' LIMIT 1");
    $adminRole = $adminRoleResult ? mysqli_fetch_assoc($adminRoleResult) : null;

    if (!$adminRole) {
        return;
    }

    $adminRoleId = (int) $adminRole['id'];
    $catalog = rbac_permission_catalog();

    foreach (array_keys($catalog) as $routeKey) {
        $safeRoute = mysqli_real_escape_string($conn, $routeKey);
        mysqli_query($conn, "
            INSERT INTO role_permissions (role_id, route_key, allow_access)
            VALUES ($adminRoleId, '$safeRoute', 1)
            ON DUPLICATE KEY UPDATE allow_access = 1
        ");
    }
}

function rbac_ensure_schema(mysqli $conn): void
{
    static $initialized = false;

    if ($initialized) {
        return;
    }

    mysqli_query($conn, "
        CREATE TABLE IF NOT EXISTS role_permissions (
            id INT(11) NOT NULL AUTO_INCREMENT,
            role_id INT(11) NOT NULL,
            route_key VARCHAR(100) NOT NULL,
            allow_access TINYINT(1) NOT NULL DEFAULT 1,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_role_route (role_id, route_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");

    $countRow = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM role_permissions"));
    $hasPermissions = (int) ($countRow['total'] ?? 0) > 0;

    if (!rbac_table_exists($conn, 'roles')) {
        return;
    }

    if (!$hasPermissions) {
        $roles = mysqli_query($conn, "SELECT id, role_name FROM roles");
        if ($roles) {
            while ($role = mysqli_fetch_assoc($roles)) {
                $allowed = rbac_default_permissions_by_role_name((string) $role['role_name']);
                foreach ($allowed as $routeKey) {
                    $roleId = (int) $role['id'];
                    $safeRoute = mysqli_real_escape_string($conn, $routeKey);
                    mysqli_query($conn, "
                        INSERT INTO role_permissions (role_id, route_key, allow_access)
                        VALUES ($roleId, '$safeRoute', 1)
                    ");
                }
            }
        }
    }

    rbac_sync_admin_permissions($conn);

    $initialized = true;
}

function rbac_get_user_role(mysqli $conn, int $userId): ?array
{
    rbac_ensure_schema($conn);

    if (!rbac_table_exists($conn, 'users') || !rbac_table_exists($conn, 'roles')) {
        return null;
    }

    $userId = (int) $userId;
    $result = mysqli_query($conn, "
        SELECT u.role_id, r.role_name
        FROM users u
        LEFT JOIN roles r ON r.id = u.role_id
        WHERE u.id = $userId
        LIMIT 1
    ");

    return $result ? mysqli_fetch_assoc($result) : null;
}

function rbac_get_role_permissions(mysqli $conn, int $roleId): array
{
    rbac_ensure_schema($conn);

    $permissions = [];
    $roleId = (int) $roleId;
    $result = mysqli_query($conn, "
        SELECT route_key, allow_access
        FROM role_permissions
        WHERE role_id = $roleId
    ");

    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $permissions[$row['route_key']] = (int) $row['allow_access'] === 1;
        }
    }

    return $permissions;
}

function rbac_bootstrap_user_permissions(mysqli $conn): void
{
    if (!isset($_SESSION['user_id'])) {
        return;
    }

    $userId = (int) $_SESSION['user_id'];
    $role = rbac_get_user_role($conn, $userId);

    $_SESSION['role_id'] = isset($role['role_id']) ? (int) $role['role_id'] : null;
    $_SESSION['role_name'] = $role['role_name'] ?? 'No Role';
    $_SESSION['rbac_permissions'] = $_SESSION['role_id']
        ? rbac_get_role_permissions($conn, (int) $_SESSION['role_id'])
        : [];
}

function rbac_is_admin(): bool
{
    if (!isset($_SESSION['user_id'])) {
        return false;
    }

    $username = strtolower(trim((string) ($_SESSION['user_name'] ?? '')));
    $roleName = strtolower(trim((string) ($_SESSION['role_name'] ?? '')));

    return $username === 'admin' || $roleName === 'admin';
}

function rbac_can_access(string $routeKey): bool
{
    if (!isset($_SESSION['user_id'])) {
        return false;
    }

    if ($routeKey === 'dashboard_home') {
        return true;
    }

    if (!isset($_SESSION['rbac_permissions'])) {
        return false;
    }

    if (rbac_is_admin()) {
        return true;
    }

    return !empty($_SESSION['rbac_permissions'][$routeKey]);
}

function rbac_can_do(string $actionKey): bool
{
    return rbac_can_access($actionKey);
}

function rbac_require_current_route(mysqli $conn): void
{
    rbac_bootstrap_user_permissions($conn);

    $scriptPath = $_SERVER['PHP_SELF'] ?? '';
    $routeKey = rbac_resolve_route_key($scriptPath);

    if ($routeKey === null) {
        return;
    }

    if (in_array($routeKey, ['users_page', 'roles_page', 'user_roles_page', 'role_permissions_page'], true) && !rbac_is_admin()) {
        if (!headers_sent()) {
            header("Location: " . rbac_redirect_path('/admin/dashboard/dashboard.php?denied=1'));
        }
        exit();
    }

    if (!rbac_can_access($routeKey)) {
        http_response_code(403);

        $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower((string) $_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Access denied']);
            exit();
        }

        if (!headers_sent()) {
            header("Location: " . rbac_redirect_path('/admin/dashboard/dashboard.php?denied=1'));
        }
        exit();
    }
}
