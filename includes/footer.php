<?php
require_once __DIR__ . "/branding.php";
$branding = get_branding_settings();
?>
</div> <!-- content end -->

<?php if (empty($minimal_layout)): ?>
<footer style="margin-left:0;padding:0 25px 25px;">
    <div style="background:#0d6efd;border-radius:14px;padding:18px 22px;box-shadow:0 6px 24px rgba(13,110,253,0.18);display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
        <div style="display:flex;align-items:center;gap:12px;color:#ffffff;font-weight:700;">
            <?php if (!empty($branding['logo_url'])): ?>
                <img src="<?php echo htmlspecialchars($branding['logo_url']); ?>" alt="Logo" style="width:38px;height:38px;object-fit:contain;border-radius:10px;background:#ffffff;padding:5px;">
            <?php endif; ?>
            <span><?php echo htmlspecialchars($branding['company_name']); ?></span>
        </div>
        <div style="color:rgba(255,255,255,0.92);font-size:14px;">
            &copy; <?php echo date("Y"); ?> <?php echo htmlspecialchars($branding['tagline']); ?>. All rights reserved.
        </div>
        <div style="color:#ffffff;font-weight:600;font-size:14px;">
            Contact: <?php echo htmlspecialchars($branding['contact']); ?>
        </div>
    </div>
</footer>
<?php endif; ?>

</body>
</html>
