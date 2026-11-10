<?php
session_start();
include "../includes/auth_check.php";
include "../config/db.php";
include "../includes/company_settings_helper.php";
include "../includes/audit_helper.php";

company_settings_ensure_schema($conn);
ensure_audit_schema($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $before = company_settings_get($conn);
    company_settings_save($conn, [
        'company_name' => trim($_POST['company_name'] ?? ''),
        'tagline' => trim($_POST['tagline'] ?? ''),
        'contact' => trim($_POST['contact'] ?? ''),
        'address' => trim($_POST['address'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'logo_path' => trim($_POST['logo_path'] ?? ''),
        'prepared_by_label' => trim($_POST['prepared_by_label'] ?? ''),
        'checked_by_label' => trim($_POST['checked_by_label'] ?? ''),
        'approved_by_label' => trim($_POST['approved_by_label'] ?? ''),
        'print_footer' => trim($_POST['print_footer'] ?? ''),
    ]);
    $after = company_settings_get($conn);
    audit_log_event($conn, 'update', 'company_settings', '1', 'Company settings updated', $before, $after);

    header("Location: company_settings.php?updated=1");
    exit;
}

$settings = company_settings_get($conn);
include "../includes/header.php";
?>

<div class="container-fluid mt-4">
    <div class="mb-3">
        <h3 class="mb-1">Company Settings</h3>
        <div class="text-muted">Manage branding, contact details, print footer text, and signature labels.</div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-primary text-white">
            <h6 class="mb-0">Branding And Print Setup</h6>
        </div>
        <div class="card-body">
            <form method="post">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Company Name</label>
                        <input type="text" name="company_name" class="form-control" value="<?php echo htmlspecialchars($settings['company_name']); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Tagline</label>
                        <input type="text" name="tagline" class="form-control" value="<?php echo htmlspecialchars($settings['tagline']); ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Contact</label>
                        <input type="text" name="contact" class="form-control" value="<?php echo htmlspecialchars($settings['contact']); ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Email</label>
                        <input type="text" name="email" class="form-control" value="<?php echo htmlspecialchars($settings['email']); ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Logo Path</label>
                        <input type="text" name="logo_path" class="form-control" value="<?php echo htmlspecialchars($settings['logo_path']); ?>" placeholder="images/logo.png">
                    </div>
                    <div class="col-md-12">
                        <label class="form-label fw-bold">Address</label>
                        <input type="text" name="address" class="form-control" value="<?php echo htmlspecialchars($settings['address']); ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Prepared Label</label>
                        <input type="text" name="prepared_by_label" class="form-control" value="<?php echo htmlspecialchars($settings['prepared_by_label']); ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Checked Label</label>
                        <input type="text" name="checked_by_label" class="form-control" value="<?php echo htmlspecialchars($settings['checked_by_label']); ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Approved Label</label>
                        <input type="text" name="approved_by_label" class="form-control" value="<?php echo htmlspecialchars($settings['approved_by_label']); ?>">
                    </div>
                    <div class="col-md-12">
                        <label class="form-label fw-bold">Print Footer</label>
                        <input type="text" name="print_footer" class="form-control" value="<?php echo htmlspecialchars($settings['print_footer']); ?>">
                    </div>
                </div>

                <div class="mt-4">
                    <button type="submit" class="btn btn-primary">Save Settings</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include "../includes/footer.php"; ?>
