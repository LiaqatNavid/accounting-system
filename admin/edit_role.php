<?php
session_start();
include "../includes/auth_check.php";
include "../config/db.php";

if (!isset($_GET['id'])) {
    header("Location: roles.php");
    exit();
}

$id = intval($_GET['id']);
$role = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM roles WHERE id='$id'"));

if (!$role) {
    header("Location: roles.php");
    exit();
}

// UPDATE ROLE LOGIC
if (isset($_POST['update_role'])) {
    $role_name = mysqli_real_escape_string($conn, trim($_POST['role_name']));
    $description = mysqli_real_escape_string($conn, trim($_POST['description']));
    
    // Check if role name already exists (excluding current role)
    $check = mysqli_query($conn, "SELECT * FROM roles WHERE role_name='$role_name' AND id!='$id'");
    
    if (mysqli_num_rows($check) > 0) {
        $message = "<div class='alert alert-danger'>Role name already exists!</div>";
    } else {
        mysqli_query($conn, "UPDATE roles SET role_name='$role_name', description='$description' WHERE id='$id'");
        $message = "<div class='alert alert-success'>Role Updated Successfully!</div>";
        $role['role_name'] = $role_name;
        $role['description'] = $description;
    }
}

include "../includes/header.php";
?>

<style>
.edit-role-page .card {
    border: 0;
    border-radius: 14px;
}

.edit-role-page .card-header {
    border-radius: 14px 14px 0 0;
}
</style>

<div class="container-fluid edit-role-page mt-4">
    <div class="mb-3">
        <h3 class="mb-1">Edit Role</h3>
        <div class="text-muted">Update role details and permissions scope.</div>
    </div>

    <div class="row justify-content-center">
        <div class="col-lg-6 col-md-8">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Edit Role</h5>
                </div>
                <div class="card-body">
                    
                    <?php if (isset($message)) echo $message; ?>

                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label">Role Name</label>
                            <input type="text" name="role_name" class="form-control" value="<?php echo htmlspecialchars($role['role_name']); ?>" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="3"><?php echo htmlspecialchars($role['description']); ?></textarea>
                        </div>

                        <button type="submit" name="update_role" class="btn btn-primary w-100">Update Role</button>
                    </form>

                    <a href="roles.php" class="btn btn-secondary w-100 mt-2">Back to Roles</a>

                </div>
            </div>
        </div>
    </div>
</div>

<?php include "../includes/footer.php"; ?>
