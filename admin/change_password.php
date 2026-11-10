<?php
session_start();
include "../includes/auth_check.php";
include "../config/db.php";
include "../includes/header.php";

if (!isset($_GET['id'])) {
    header("Location: users.php");
    exit();
}

$id = intval($_GET['id']);
$user = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM users WHERE id='$id'"));

if (!$user) {
    header("Location: users.php");
    exit();
}

// CHANGE PASSWORD LOGIC
if (isset($_POST['change_password'])) {
    $new_password = mysqli_real_escape_string($conn, $_POST['new_password']);
    $confirm_password = $_POST['confirm_password'];
    
    if ($new_password != $confirm_password) {
        $message = "<div class='alert alert-danger'>Passwords do not match!</div>";
    } else if (strlen($new_password) < 1) {
        $message = "<div class='alert alert-danger'>Password cannot be empty!</div>";
    } else {
        mysqli_query($conn, "UPDATE users SET password='$new_password' WHERE id='$id'");
        $message = "<div class='alert alert-success'>Password Changed Successfully!</div>";
    }
}
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0">Change Password for <?php echo $user['username']; ?></h5>
                </div>
                <div class="card-body">
                    
                    <?php if (isset($message)) echo $message; ?>

                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label">New Password</label>
                            <input type="password" name="new_password" class="form-control" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Confirm Password</label>
                            <input type="password" name="confirm_password" class="form-control" required>
                        </div>

                        <button type="submit" name="change_password" class="btn btn-dark w-100">Change Password</button>
                    </form>

                    <a href="users.php" class="btn btn-secondary w-100 mt-2">Back to Users</a>

                </div>
            </div>
        </div>
    </div>
</div>

<?php include "../includes/footer.php"; ?>
