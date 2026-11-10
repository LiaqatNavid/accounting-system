<?php
session_start();
include "../includes/auth_check.php";
include "../config/db.php";
include "../includes/header.php";

$edit_user_id = isset($_GET['edit_user_id']) ? intval($_GET['edit_user_id']) : 0;
$edit_user = null;
if ($edit_user_id > 0) {
    $edit_user = mysqli_fetch_assoc(mysqli_query($conn, "
        SELECT u.id, u.username, u.role_id
        FROM users u
        WHERE u.id = $edit_user_id
    "));
}

// ASSIGN ROLE TO USER LOGIC
if (isset($_POST['assign_role'])) {
    $user_id = intval($_POST['user_id']);
    $role_id = intval($_POST['role_id']);

    mysqli_query($conn, "UPDATE users SET role_id='$role_id' WHERE id='$user_id'");

    if (isset($_SESSION['user_id']) && (int) $_SESSION['user_id'] === $user_id) {
        rbac_bootstrap_user_permissions($conn);
    }

    $message = "<div class='alert alert-success'>Role Assigned Successfully!</div>";
}
?>

<div class="container mt-5">
    <h3 class="mb-4">Assign Roles to Users</h3>

    <?php if (isset($message)) echo $message; ?>

    <!-- Assign Role Form -->
    <div class="card shadow mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><?php echo $edit_user ? 'Edit User Role' : 'Assign Role to User'; ?></h5>
        </div>
        <div class="card-body">

            <form method="POST">
                <div class="row">
                    <div class="col-md-5">
                        <label class="form-label">Select User</label>
                        <select name="user_id" class="form-select" required>
                            <option value="">-- Choose User --</option>
                            <?php
                            $users = mysqli_query($conn, "SELECT * FROM users");
                            while ($user = mysqli_fetch_assoc($users)) {
                                $uid = intval($user['id']);
                                $uname = htmlspecialchars($user['username']);
                                $selected = ($edit_user && (int) $edit_user['id'] === $uid) ? 'selected' : '';
                                echo "<option value='{$uid}' {$selected}>{$uname}</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <div class="col-md-5">
                        <label class="form-label">Select Role</label>
                        <select name="role_id" class="form-select" required>
                            <option value="">-- Choose Role --</option>
                            <?php
                            $roles = mysqli_query($conn, "SELECT * FROM roles");
                            while ($role = mysqli_fetch_assoc($roles)) {
                                $rid = intval($role['id']);
                                $rname = htmlspecialchars($role['role_name']);
                                $selected = ($edit_user && (int) ($edit_user['role_id'] ?? 0) === $rid) ? 'selected' : '';
                                echo "<option value='{$rid}' {$selected}>{$rname}</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" name="assign_role" class="btn btn-primary w-100"><?php echo $edit_user ? 'Update' : 'Assign'; ?></button>
                    </div>
                </div>
            </form>

        </div>
    </div>

    <!-- User Roles List -->
    <div class="card shadow">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">User Roles</h5>
        </div>
        <div class="card-body">

            <table class="table table-striped table-bordered">
                <thead class="table-primary text-white">
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Role</th>
                        <th style="width: 120px;">Action</th>
                    </tr>
                </thead>
                <tbody>

                    <?php
                    $user_roles = mysqli_query($conn, "SELECT u.id, u.username, u.role_id, r.role_name FROM users u LEFT JOIN roles r ON u.role_id = r.id");

                    if (mysqli_num_rows($user_roles) > 0) {
                        while ($row = mysqli_fetch_assoc($user_roles)) {
                            $id = intval($row['id']);
                            $username = htmlspecialchars($row['username']);
                            $role_name = $row['role_name'] ? htmlspecialchars($row['role_name']) : '<span class="text-muted">No Role Assigned</span>';
                            echo "<tr>
                                    <td>{$id}</td>
                                    <td>{$username}</td>
                                    <td>{$role_name}</td>
                                    <td><a href='user_roles.php?edit_user_id={$id}' class='btn btn-sm btn-primary'>Edit</a></td>
                                  </tr>";
                        }
                    } else {
                        echo "<tr><td colspan='4' class='text-center text-muted'>No users found</td></tr>";
                    }
                    ?>

                </tbody>
            </table>

        </div>
    </div>
</div>

<?php include "../includes/footer.php"; ?>
