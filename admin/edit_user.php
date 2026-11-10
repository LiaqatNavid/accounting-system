<?php
session_start();
include "../includes/auth_check.php";
include "../config/db.php";

if (!isset($_GET['id'])) {
    header("Location: users.php");
    exit();
}

$id = intval($_GET['id']);
$user = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM users WHERE id='$id'"));
$roles = mysqli_query($conn, "SELECT id, role_name FROM roles ORDER BY role_name");

if (!$user) {
    header("Location: users.php");
    exit();
}

// UPDATE USER LOGIC
if (isset($_POST['update_user'])) {
    $username = mysqli_real_escape_string($conn, trim($_POST['username']));
    $role_id = isset($_POST['role_id']) && $_POST['role_id'] !== '' ? intval($_POST['role_id']) : "NULL";
    
    // Check if username already exists (excluding current user)
    $check = mysqli_query($conn, "SELECT * FROM users WHERE username='$username' AND id!='$id'");
    
    if (mysqli_num_rows($check) > 0) {
        $message = "<div class='alert alert-danger'>Username already exists!</div>";
    } else {
        mysqli_query($conn, "UPDATE users SET username='$username', role_id=" . ($role_id === "NULL" ? "NULL" : "'" . $role_id . "'") . " WHERE id='$id'");
        $message = "<div class='alert alert-success'>User Updated Successfully!</div>";
        $user['username'] = $username;
        $user['role_id'] = $role_id === "NULL" ? null : $role_id;
    }
}

include "../includes/header.php";
?>

<style>
.edit-user-page .card {
    border: 0;
    border-radius: 14px;
}

.edit-user-page .card-header {
    border-radius: 14px 14px 0 0;
}
</style>

<div class="container-fluid edit-user-page mt-4">
    <div class="mb-3">
        <h3 class="mb-1">Edit User</h3>
        <div class="text-muted">Update the selected user's username and role.</div>
    </div>

    <div class="row justify-content-center">
        <div class="col-lg-6 col-md-8">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Edit User</h5>
                </div>
                <div class="card-body">
                    
                    <?php if (isset($message)) echo $message; ?>

                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label">Username</label>
                            <input type="text" name="username" class="form-control" value="<?php echo htmlspecialchars($user['username']); ?>" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Role</label>
                            <select name="role_id" class="form-select">
                                <option value="">Select Role</option>
                                <?php
                                if ($roles) {
                                    while ($role = mysqli_fetch_assoc($roles)) {
                                        $selected = ((int) ($user['role_id'] ?? 0) === (int) $role['id']) ? 'selected' : '';
                                        echo "<option value='" . (int) $role['id'] . "' {$selected}>" . htmlspecialchars($role['role_name']) . "</option>";
                                    }
                                }
                                ?>
                            </select>
                        </div>

                        <button type="submit" name="update_user" class="btn btn-primary w-100">Update User</button>
                    </form>

                    <a href="users.php" class="btn btn-secondary w-100 mt-2">Back to Users</a>

                </div>
            </div>
        </div>
    </div>
</div>

<?php include "../includes/footer.php"; ?>
