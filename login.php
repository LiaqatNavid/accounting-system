<?php
session_start();
include "config/db.php";

if (isset($_POST['login'])) {

    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $password = $_POST['password'];

    $query = "SELECT * FROM users WHERE username='$username' LIMIT 1";
    $result = mysqli_query($conn, $query);

    if (mysqli_num_rows($result) == 1) {

        $user = mysqli_fetch_assoc($result);

        // Plain password check
        if ($password === $user['password']) {

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['username'];
            $_SESSION['last_activity'] = time();

            header("Location: admin/dashboard/dashboard.php");
            exit();

        } else {
            $error = "Invalid Password!";
        }

    } else {
        $error = "User not found!";
    }
}
?>

<?php $minimal_layout = true; include "includes/header.php"; ?>
<?php
$loginBranding = function_exists('get_branding_settings') ? get_branding_settings() : ['logo_url' => ''];
$loginLogoUrl = !empty($loginBranding['logo_url']) ? $loginBranding['logo_url'] : '/accounting-system/images/logo.png';
?>

<style>
/* Lighten login page background */
body {
    background:
        radial-gradient(circle at top left, rgba(13,110,253,0.10), transparent 26%),
        linear-gradient(180deg, #f7f9fc 0%, #edf3fb 100%);
    min-height: 100vh;
}
.card.shadow { background-color: #ffffff; border: 1px solid #e6eefb; }
#loginBtn { background-color: #0d6efd; border-color: #0d6efd; color: #fff; }
#loginBtn:hover, #loginBtn:focus { background-color: #0b5ed7; border-color: #0b5ed7; color: #fff; }
#clock { font-weight:700; font-size:0.98rem; text-align:center; color:#ffffff; }

/* Brand heading */
.brand-title {
    color: #0d6efd;
    font-weight: 800;
    font-size: 1.25rem;
    margin-bottom: 0.25rem;
    text-shadow: 0 1px 0 rgba(13,110,253,0.06);
    font-family: 'Segoe UI', system-ui, -apple-system, 'Helvetica Neue', Arial;
}
.brand-sub {
    color: #6c757d;
    font-size: 0.9rem;
    margin-bottom: 0.6rem;
}

/* Enhanced card visuals: animated gradient, soft shadow and lift */
.card.shadow {
    position: relative;
    overflow: hidden;
    border-radius: 18px;
    background: linear-gradient(160deg, #0a2a66 0%, #0d47a1 50%, #0d6efd 100%);
    border: 1px solid rgba(255,255,255,0.10);
    box-shadow: 0 18px 42px rgba(3,37,76,0.18);
    transition: transform 220ms cubic-bezier(.2,.9,.2,1), box-shadow 220ms;
}
.card.shadow::before{
    content: '';
    position: absolute;
    inset: -50% -50% -40% -50%;
    background: radial-gradient(circle at 10% 10%, rgba(255,255,255,0.16), transparent 14%),
                radial-gradient(circle at 90% 90%, rgba(255,255,255,0.08), transparent 14%);
    z-index: 0;
    pointer-events: none;
    transform: translateZ(0);
}
.card.shadow:hover{ transform: translateY(-8px); box-shadow: 0 24px 52px rgba(3,37,76,0.22); }
.card.shadow .card-body{ position: relative; z-index: 2; background: transparent; }

.card.shadow .card-body h4,
.card.shadow .card-body label,
.card.shadow .card-body .form-text {
    color: #ffffff;
}

.card.shadow .form-control {
    border-radius: 12px;
    border: 1px solid rgba(255,255,255,0.14);
    background: rgba(255,255,255,0.96);
}

.card.shadow .alert-warning {
    background: rgba(255,193,7,0.94);
    border: 0;
    color: #3b2a00;
}

.card.shadow .alert-danger {
    background: rgba(220,53,69,0.95);
    border: 0;
    color: #ffffff;
}

/* small accent under the header */
.brand-wrapper{ margin-top: 18px; margin-bottom: 4px; }

.login-page-wrap {
    max-width: 1180px;
    margin: 0 auto;
    padding: 28px 16px 40px;
}

.login-hero {
    border-radius: 24px;
    overflow: hidden;
    border: 1px solid rgba(13,110,253,0.10);
    box-shadow: 0 24px 60px rgba(15, 52, 98, 0.10);
    background: rgba(255,255,255,0.75);
}

.login-visual {
    min-height: 620px;
    padding: 28px;
    background:
        radial-gradient(circle at top left, rgba(255,255,255,0.22), transparent 25%),
        linear-gradient(160deg, #0a2a66 0%, #0d47a1 42%, #0d6efd 100%);
    color: #fff;
    position: relative;
}

.login-visual h1 {
    font-size: 2.1rem;
    font-weight: 800;
    line-height: 1.1;
    margin-bottom: 10px;
}

.login-visual p {
    color: rgba(255,255,255,0.86);
    max-width: 520px;
    margin-bottom: 22px;
}

.visual-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 9px 14px;
    border-radius: 999px;
    background: rgba(255,255,255,0.12);
    border: 1px solid rgba(255,255,255,0.14);
    font-weight: 700;
    margin-bottom: 18px;
}

.showcase-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 14px;
    margin-top: 20px;
}

.showcase-card {
    position: relative;
    overflow: hidden;
    border-radius: 18px;
    min-height: 190px;
    border: 1px solid rgba(255,255,255,0.12);
    box-shadow: 0 14px 30px rgba(6, 24, 64, 0.16);
}

.showcase-card img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}

.showcase-card::after {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(180deg, transparent 35%, rgba(5,18,46,0.68) 100%);
}

.showcase-label {
    position: absolute;
    left: 14px;
    bottom: 12px;
    z-index: 2;
    font-size: 0.95rem;
    font-weight: 700;
    letter-spacing: 0.2px;
}

.login-form-col {
    min-height: 620px;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 28px 20px;
    background: linear-gradient(180deg, rgba(255,255,255,0.92), rgba(248,251,255,0.98));
}

.login-panel {
    width: 100%;
    max-width: 420px;
}

.login-card-clock {
    margin: -4px auto 18px;
    max-width: 240px;
    padding: 9px 14px;
    border-radius: 999px;
    background: rgba(255,255,255,0.12);
    border: 1px solid rgba(255,255,255,0.14);
    box-shadow: inset 0 1px 0 rgba(255,255,255,0.08);
}

.login-card-logo {
    width: 74px;
    height: 74px;
    object-fit: contain;
    display: block;
    margin: 0 auto 14px;
    border-radius: 18px;
    background: rgba(255,255,255,0.96);
    padding: 10px;
    box-shadow: 0 16px 34px rgba(7, 26, 70, 0.20);
}

@media (max-width: 991.98px) {
    .login-visual {
        min-height: auto;
    }

    .login-form-col {
        min-height: auto;
    }
}

@media (max-width: 575.98px) {
    .showcase-grid {
        grid-template-columns: 1fr;
    }
}

</style>

<div class="login-page-wrap">
    <div class="row justify-content-center mb-3">
        <div class="col-12 text-center">
            <div class="brand-wrapper">
                <h2 id="brand" class="brand-title" aria-hidden="true"></h2>
                <div id="brand-sub" class="brand-sub" aria-hidden="true"></div>
            </div>
        </div>
    </div>

    <div class="login-hero">
        <div class="row g-0">
            <div class="col-lg-7">
                <div class="login-visual">
                    <div class="visual-badge">
                        <i class="bi bi-stars"></i>
                        Furniture Showcase
                    </div>
                    <h1>Furniture business accounting with a stronger first impression.</h1>
                    <p>Login screen now uses your local showroom images from the software folder, so the product feels tied to the business instead of looking generic.</p>

                    <div class="showcase-grid">
                        <div class="showcase-card">
                            <img src="images/sofa.jpg" alt="Sofa">
                            <div class="showcase-label">Sofa Collection</div>
                        </div>
                        <div class="showcase-card">
                            <img src="images/dining.jpg" alt="Dining">
                            <div class="showcase-label">Dining Sets</div>
                        </div>
                        <div class="showcase-card">
                            <img src="images/bed.jpg" alt="Bed">
                            <div class="showcase-label">Bedroom Range</div>
                        </div>
                        <div class="showcase-card">
                            <img src="images/dressing.jpg" alt="Dressing">
                            <div class="showcase-label">Premium Dressing</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="login-form-col">
                    <div class="login-panel">
                        <div class="card shadow">
                            <div class="card-body p-4">
                                <img src="<?php echo htmlspecialchars($loginLogoUrl); ?>" alt="Logo" class="login-card-logo">
                                <h4 class="mb-3">Login</h4>
                                <div class="login-card-clock">
                                    <div id="clock" aria-live="polite"></div>
                                </div>

                                <?php if (isset($_GET['timeout']) && $_GET['timeout'] == '1') echo "<div class='alert alert-warning'>Session expired after 10 minutes of inactivity. Please login again.</div>"; ?>
                                <?php if (isset($error)) echo "<div class='alert alert-danger'>$error</div>"; ?>

                                <form method="POST">
                                    <div class="mb-3">
                                        <label>Username</label>
                                        <input type="text" name="username" class="form-control" required>
                                    </div>

                                    <div class="mb-3">
                                        <label>Password</label>
                                        <input type="password" name="password" class="form-control" required>
                                    </div>

                                    <button type="submit" id="loginBtn" name="login" class="btn w-100">Login</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include "includes/footer.php"; ?>

<script>
// Simple running clock for the login page
(function(){
    function updateClock(){
        const now = new Date();
        const dateText = now.toLocaleDateString(undefined, {
            year: 'numeric',
            month: 'short',
            day: '2-digit'
        });
        const h = String(now.getHours()).padStart(2,'0');
        const m = String(now.getMinutes()).padStart(2,'0');
        const s = String(now.getSeconds()).padStart(2,'0');
        const el = document.getElementById('clock');
        if(el) el.textContent = dateText + ' | ' + h + ':' + m + ':' + s;
    }
    updateClock();
    setInterval(updateClock, 1000);
})();
</script>

<script>
// Typewriter effect for brand heading
(function(){
    const text = 'Jaladin and Son Furniture Experts';
    const sub = 'Quality · Craftsmanship · Comfort';
    const el = document.getElementById('brand');
    const subEl = document.getElementById('brand-sub');
    if(!el) return;
    let i = 0;
    function type(){
        if(i <= text.length){
            el.textContent = text.slice(0, i);
            i++;
            setTimeout(type, 40);
        } else {
            // reveal subtext with slight fade
            if(subEl){
                subEl.textContent = sub;
                subEl.style.opacity = 0;
                subEl.style.transition = 'opacity 400ms ease-in-out';
                requestAnimationFrame(()=> subEl.style.opacity = 1);
            }
        }
    }
    // start after short delay so clock and layout settle
    setTimeout(type, 250);
})();
</script>
