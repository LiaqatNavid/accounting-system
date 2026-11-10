<?php
session_start();
include __DIR__ . "/../../includes/auth_check.php";
include __DIR__ . "/../../config/db.php";
include __DIR__ . "/../../includes/header.php";
?>

<link rel="stylesheet" href="<?php echo $base_url; ?>assets/css/jquery.dataTables.min.css">

<style>
.dashboard-page {
    position: relative;
}

.dashboard-page::before {
    content: "";
    position: absolute;
    inset: 0 0 auto 0;
    height: 260px;
    background:
        radial-gradient(circle at top left, rgba(13,110,253,0.18), transparent 42%),
        radial-gradient(circle at top right, rgba(25,135,84,0.14), transparent 36%),
        linear-gradient(180deg, rgba(255,255,255,0.92), rgba(244,246,249,0));
    pointer-events: none;
}

.dashboard-hero {
    position: relative;
    z-index: 1;
    background: linear-gradient(135deg, #0d6efd 0%, #0b5ed7 48%, #198754 100%);
    color: #fff;
    border-radius: 24px;
    padding: 28px 28px 24px;
    margin-bottom: 24px;
    overflow: hidden;
    box-shadow: 0 22px 55px rgba(13,110,253,0.18);
}

.dashboard-hero::after {
    content: "";
    position: absolute;
    width: 240px;
    height: 240px;
    right: -70px;
    top: -90px;
    border-radius: 50%;
    background: rgba(255,255,255,0.10);
}

.dashboard-hero .eyebrow {
    display: inline-block;
    font-size: 12px;
    letter-spacing: 1.2px;
    text-transform: uppercase;
    font-weight: 700;
    color: rgba(255,255,255,0.82);
    margin-bottom: 10px;
}

.dashboard-hero h3 {
    margin: 0 0 8px;
    font-size: 2rem;
    font-weight: 800;
}

.dashboard-hero p {
    margin: 0;
    max-width: 720px;
    color: rgba(255,255,255,0.88);
}

.widget-grid {
    position: relative;
    z-index: 1;
}

.widget-card {
    position: relative;
    border: 0;
    border-radius: 22px;
    overflow: hidden;
    min-height: 220px;
    box-shadow: 0 18px 40px rgba(15,23,42,0.10);
    transition: transform 180ms ease, box-shadow 180ms ease;
}

.widget-card:hover {
    transform: translateY(-6px);
    box-shadow: 0 26px 55px rgba(15,23,42,0.16);
}

.widget-card .card-body {
    position: relative;
    padding: 22px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    gap: 16px;
}

.widget-card .widget-top {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 14px;
}

.widget-card .widget-name {
    font-size: 0.82rem;
    text-transform: uppercase;
    letter-spacing: 1px;
    font-weight: 700;
    color: rgba(255,255,255,0.78);
    margin-bottom: 10px;
}

.widget-card .counter {
    font-size: 2.35rem;
    line-height: 1;
    font-weight: 800;
    margin: 0;
}

.widget-card .widget-note {
    font-size: 0.95rem;
    color: rgba(255,255,255,0.84);
    margin: 0;
}

.widget-card .widget-icon {
    width: 58px;
    height: 58px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 18px;
    background: rgba(255,255,255,0.14);
    color: #fff;
    font-size: 1.35rem;
    box-shadow: inset 0 0 0 1px rgba(255,255,255,0.10);
}

.widget-card::after {
    content: "";
    position: absolute;
    width: 160px;
    height: 160px;
    right: -45px;
    bottom: -55px;
    border-radius: 50%;
    background: rgba(255,255,255,0.08);
}

.widget-card.bg-warning .widget-name,
.widget-card.bg-warning .widget-note {
    color: rgba(33,37,41,0.74);
}

.widget-card.bg-warning .counter,
.widget-card.bg-warning .widget-icon {
    color: #212529;
}

.widget-card.bg-warning .widget-icon {
    background: rgba(255,255,255,0.35);
}

.widget-card .view-widget {
    align-self: flex-start;
}

@media (max-width: 767.98px) {
    .dashboard-hero {
        padding: 22px 18px;
        border-radius: 18px;
    }

    .dashboard-hero h3 {
        font-size: 1.5rem;
    }

    .widget-card {
        min-height: auto;
    }
}
</style>

<?php
$colors = [
    'bg-primary',
    'bg-success',
    'bg-warning',
    'bg-danger',
    'bg-info',
    'bg-dark'
];
$icons = [
    'bi-bar-chart-line',
    'bi-wallet2',
    'bi-receipt-cutoff',
    'bi-box-seam',
    'bi-journal-check',
    'bi-cash-stack'
];
$colorIndex = 0;
$iconIndex = 0;
?>

<div class="container-fluid dashboard-page mt-4">
    <div class="dashboard-hero">
        <div class="eyebrow">Control Center</div>
        <h3>Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?></h3>
        <p>Track live totals, open detailed widget views, and keep the accounting system status visible at a glance.</p>
    </div>

    <div class="row widget-grid g-4">
    <?php
    $widgets = mysqli_query($conn, "SELECT * FROM dashboard_widgets WHERE status=1");

    while ($w = mysqli_fetch_assoc($widgets)) {
        $total = 0;

        if (!empty($w['count_query'])) {
            $count_result = mysqli_query($conn, $w['count_query']);
            if ($count_result) {
                $count_row = mysqli_fetch_assoc($count_result);
                if ($count_row) {
                    $total = array_values($count_row)[0];
                }
            }
        }

        $cardColor = $colors[$colorIndex];
        $cardIcon = $icons[$iconIndex];
        $colorIndex++;
        $iconIndex++;

        if ($colorIndex >= count($colors)) {
            $colorIndex = 0;
        }
        if ($iconIndex >= count($icons)) {
            $iconIndex = 0;
        }
    ?>
        <div class="col-xl-3 col-lg-4 col-md-6">
            <div class="card widget-card text-white <?php echo $cardColor; ?>">
                <div class="card-body">
                    <div class="widget-top">
                        <div>
                            <div class="widget-name"><?php echo htmlspecialchars($w['widget_name']); ?></div>
                            <h2 class="counter" data-target="<?php echo (float) $total; ?>">0</h2>
                            <p class="widget-note">Live total from the configured dashboard widget query.</p>
                        </div>
                        <div class="widget-icon">
                            <i class="bi <?php echo $cardIcon; ?>"></i>
                        </div>
                    </div>
                    <button class="btn btn-light btn-sm view-widget" data-id="<?php echo htmlspecialchars($w['id']); ?>">
                        View Details
                    </button>
                </div>
            </div>
        </div>
    <?php } ?>
    </div>
</div>

<script src="<?php echo $base_url; ?>assets/js/jquery-3.7.1.min.js"></script>
<script src="<?php echo $base_url; ?>assets/js/jquery.dataTables.min.js"></script>
<script src="<?php echo $base_url; ?>assets/js/bootstrap.bundle.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const counters = document.querySelectorAll('.counter');

    counters.forEach(counter => {
        const updateCount = () => {
            const target = Number(counter.getAttribute('data-target'));
            const current = Number(counter.innerText.replace(/,/g, ''));
            const increment = Math.max(target / 80, 1);

            if (current < target) {
                counter.innerText = Math.ceil(current + increment).toLocaleString();
                setTimeout(updateCount, 20);
            } else {
                counter.innerText = target.toLocaleString();
            }
        };

        updateCount();
    });
});

$(document).on('click', '.view-widget', function () {
    var widgetId = $(this).data('id');
    var popupUrl = 'widget_detail_popup.php?id=' + encodeURIComponent(widgetId);
    var features = [
        'width=1280',
        'height=820',
        'left=90',
        'top=50',
        'resizable=yes',
        'scrollbars=yes',
        'toolbar=no',
        'menubar=no',
        'location=no',
        'status=no'
    ].join(',');

    var popup = window.open(popupUrl, 'dashboard_widget_popup', features);
    if (popup) {
        popup.focus();
    }
});
</script>

<?php include __DIR__ . "/../../includes/footer.php"; ?>
