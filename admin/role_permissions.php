<?php
session_start();
include "../includes/auth_check.php";
include "../config/db.php";

rbac_ensure_schema($conn);

$catalog = rbac_permission_catalog();
$selectedRoleId = 0;
if (isset($_GET['role_id'])) {
    $selectedRoleId = (int) $_GET['role_id'];
} elseif (isset($_POST['role_id']) && !isset($_POST['save_permissions'])) {
    $selectedRoleId = (int) $_POST['role_id'];
}
$selectedRoleName = '';

if (isset($_POST['save_permissions'])) {
    $selectedRoleId = (int) ($_POST['role_id'] ?? 0);
    $selectedPermissions = $_POST['permissions'] ?? [];
    $roleRow = mysqli_fetch_assoc(mysqli_query($conn, "SELECT role_name FROM roles WHERE id = $selectedRoleId"));
    $selectedRoleName = $roleRow['role_name'] ?? '';

    if (strtolower($selectedRoleName) === 'admin') {
        header("Location: role_permissions.php?role_id=" . $selectedRoleId . "&updated=1");
        exit();
    }

    mysqli_query($conn, "DELETE FROM role_permissions WHERE role_id = $selectedRoleId");

    foreach ($catalog as $routeKey => $label) {
        if (in_array($routeKey, $selectedPermissions, true)) {
            $safeRoute = mysqli_real_escape_string($conn, $routeKey);
            mysqli_query($conn, "
                INSERT INTO role_permissions (role_id, route_key, allow_access)
                VALUES ($selectedRoleId, '$safeRoute', 1)
            ");
        }
    }

    if (isset($_SESSION['role_id']) && (int) $_SESSION['role_id'] === $selectedRoleId) {
        rbac_bootstrap_user_permissions($conn);
    }

    header("Location: role_permissions.php?role_id=" . $selectedRoleId . "&updated=1");
    exit();
}

$roles = mysqli_query($conn, "SELECT id, role_name, description FROM roles ORDER BY role_name");
$currentPermissions = $selectedRoleId > 0 ? rbac_get_role_permissions($conn, $selectedRoleId) : [];
if ($selectedRoleId > 0 && $selectedRoleName === '') {
    $selectedRoleRow = mysqli_fetch_assoc(mysqli_query($conn, "SELECT role_name FROM roles WHERE id = $selectedRoleId"));
    $selectedRoleName = $selectedRoleRow['role_name'] ?? '';
}

include "../includes/header.php";
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div>
            <h3 class="mb-1">Role Permissions</h3>
            <div class="text-muted">Admin decides which modules each role can access.</div>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">Permission Setup</h5>
        </div>
        <div class="card-body">
            <div class="alert alert-secondary">
                Admin role always has full access.
                All other roles only get the permissions selected here.
            </div>

            <form method="post">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Role</label>
                        <select name="role_id" class="form-select" onchange="this.form.submit()">
                            <option value="">Select Role</option>
                            <?php
                            mysqli_data_seek($roles, 0);
                            while ($role = mysqli_fetch_assoc($roles)) {
                                $roleId = (int) $role['id'];
                                $selected = $selectedRoleId === $roleId ? 'selected' : '';
                                echo "<option value='{$roleId}' {$selected}>" . htmlspecialchars($role['role_name']) . "</option>";
                            }
                            ?>
                        </select>
                    </div>
                </div>
            </form>

            <?php if ($selectedRoleId > 0): ?>
            <form method="post" class="mt-4">
                <input type="hidden" name="role_id" value="<?php echo $selectedRoleId; ?>">
                <?php if (strtolower($selectedRoleName) === 'admin'): ?>
                <div class="alert alert-info">Admin always has full access and cannot be restricted here.</div>
                <?php endif; ?>
                <div class="row g-3">
                    <?php foreach ($catalog as $routeKey => $label): ?>
                    <div class="col-md-6">
                        <label class="form-check border rounded p-3 d-flex align-items-center gap-2">
                            <input
                                type="checkbox"
                                class="form-check-input mt-0"
                                name="permissions[]"
                                value="<?php echo htmlspecialchars($routeKey); ?>"
                                <?php echo (strtolower($selectedRoleName) === 'admin' || !empty($currentPermissions[$routeKey])) ? 'checked' : ''; ?>
                                <?php echo strtolower($selectedRoleName) === 'admin' ? 'disabled' : ''; ?>
                            >
                            <span><?php echo htmlspecialchars($label); ?></span>
                        </label>
                    </div>
                    <?php endforeach; ?>
                </div>

                <?php if (strtolower($selectedRoleName) !== 'admin'): ?>
                <div class="mt-4">
                    <button type="submit" name="save_permissions" class="btn btn-primary">
                        Save Permissions
                    </button>
                </div>
                <?php endif; ?>
            </form>
            <?php endif; ?>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">Roles Overview</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-striped mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Role</th>
                            <th>Description</th>
                            <th>Allowed Modules</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        mysqli_data_seek($roles, 0);
                        while ($role = mysqli_fetch_assoc($roles)) {
                            $permissions = rbac_get_role_permissions($conn, (int) $role['id']);
                            $labels = [];
                            foreach ($catalog as $routeKey => $label) {
                                if (!empty($permissions[$routeKey])) {
                                    $labels[] = $label;
                                }
                            }
                            echo "<tr>";
                            echo "<td>" . htmlspecialchars($role['role_name']) . "</td>";
                            echo "<td>" . htmlspecialchars($role['description']) . "</td>";
                            echo "<td>" . htmlspecialchars(implode(', ', $labels)) . "</td>";
                            echo "</tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include "../includes/footer.php"; ?>
