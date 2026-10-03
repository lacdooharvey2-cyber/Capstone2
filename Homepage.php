<?php
session_start();

if (isset($_SESSION['role'])) {
    $dashboards = [
        'AssistantAdmin' => 'admindashboard.php',
        'Admin' => 'admindashboard.php',
        'AssistantSuperAdmin' => 'admindashboard.php',
        'SuperAdmin' => 'admindashboard.php',
        'HeadTechnician' => 'techniciandashboard.php',
        'Technician' => 'techniciandashboard.php',
        'Customer' => 'customerdashboard.php',
        'Cashier' => 'cashierdashboard.php',
    ];

    if (isset($dashboards[$_SESSION['role']])) {
        header('Location: ' . $dashboards[$_SESSION['role']]);
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>FixTrack | E-bike Repair Management</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    :root {
      --forest: #003b16;
      --green: #198754;
      --green-dark: #146c43;
      --mint: #e5f8e9;
      --mint-strong: #b8e4c2;
      --ink: #17221a;
      --muted: #647068;
      --line: #e4e9e5;
      --surface: #ffffff;
      --canvas: #f7f8f7;
    }

    * { box-sizing: border-box; }
    html { scroll-behavior: smooth; }
    body { margin: 0; background: var(--canvas); color: var(--ink); font-family: system-ui, "Segoe UI", Roboto, sans-serif; }
    a { color: inherit; text-decoration: none; }

    .site-shell { width: 100%; margin: 0; background: var(--surface); min-height: 100vh; }
    .site-nav { height: 76px; padding: 0 clamp(24px, 6vw, 88px); display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid var(--line); background: rgba(255,255,255,.96); }
    .brand { display: inline-flex; align-items: center; gap: 10px; color: var(--forest); font-size: 1.25rem; font-weight: 800; }
    .brand-mark { width: 31px; height: 31px; display: grid; place-items: center; background: var(--forest); color: #fff; border-radius: 6px; font-size: 1rem; }
    .nav-links { display: flex; align-items: center; gap: 30px; color: #4a554d; font-size: .88rem; font-weight: 600; }
    .nav-links a:hover { color: var(--green); }
    .nav-actions { display: flex; align-items: center; gap: 10px; }
    .btn { min-height: 40px; padding: 9px 17px; border-radius: 6px; font-size: .82rem; font-weight: 700; }
    .btn-outline { border: 1px solid var(--line); background: #fff; color: var(--forest); }
    .btn-outline:hover { border-color: var(--green); background: var(--mint); color: var(--forest); }
    .btn-primary { border: 1px solid var(--forest); background: var(--forest); color: #fff; }
    .btn-primary:hover { border-color: var(--green-dark); background: var(--green-dark); color: #fff; }

    .hero { min-height: calc(100vh - 76px); min-height: calc(100svh - 76px); padding: clamp(48px, 7vw, 100px) clamp(28px, 7vw, 116px); position: relative; overflow: hidden; display: grid; align-items: center; }
    .hero-grid { display: grid; grid-template-columns: minmax(300px, .92fr) minmax(470px, 1.08fr); align-items: center; gap: clamp(42px, 6vw, 92px); width: 100%; max-width: 1520px; margin: 0 auto; }
    .eyebrow { display: inline-flex; align-items: center; gap: 7px; padding: 6px 9px; background: var(--mint); color: var(--green-dark); font-size: .66rem; font-weight: 800; letter-spacing: .18em; }
    h1 { max-width: 600px; margin: 20px 0 20px; font-size: clamp(2.75rem, 5vw, 4.85rem); line-height: 1.04; letter-spacing: 0; font-weight: 800; }
    .hero-copy { max-width: 520px; color: var(--muted); font-size: 1rem; line-height: 1.65; }
    .hero-actions { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 30px; }
    .hero-actions .btn { min-height: 47px; padding-inline: 21px; }
    .trust-line { margin-top: 33px; color: var(--muted); font-size: .78rem; font-weight: 600; }
    .trust-line i { color: var(--green); margin-right: 6px; }

    .product-preview { position: relative; min-height: 448px; }
    .preview-card { position: absolute; inset: 18px 0 0 0; background: #fff; border: 1px solid var(--line); box-shadow: 0 22px 55px rgba(0,59,22,.12); overflow: hidden; }
    .preview-topbar { height: 52px; display: flex; align-items: center; justify-content: space-between; padding: 0 18px; border-bottom: 1px solid var(--line); }
    .preview-brand { color: var(--forest); font-size: .78rem; font-weight: 800; }
    .preview-user { width: 25px; height: 25px; border-radius: 50%; background: var(--mint-strong); }
    .location-map { height: calc(100% - 52px); background: var(--canvas); }
    .location-map iframe { display: block; width: 100%; height: 100%; border: 0; }
    .preview-body { display: grid; grid-template-columns: 112px 1fr; min-height: 394px; }
    .preview-sidebar { padding: 16px 10px; background: var(--forest); }
    .preview-sidebar span { display: flex; align-items: center; gap: 7px; padding: 9px 8px; margin-bottom: 5px; color: rgba(255,255,255,.72); font-size: .62rem; font-weight: 700; }
    .preview-sidebar span.active { background: rgba(255,255,255,.16); color: #fff; }
    .preview-main { padding: 21px; background: #fbfcfb; }
    .preview-heading { display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px; }
    .preview-heading strong { font-size: 1rem; }
    .preview-heading button { border: 0; background: var(--green); color: #fff; padding: 7px 10px; font-size: .65rem; font-weight: 700; border-radius: 4px; }
    .metric-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-bottom: 16px; }
    .metric { padding: 11px; border: 1px solid var(--line); background: #fff; }
    .metric label { display: block; color: var(--muted); font-size: .58rem; font-weight: 700; }
    .metric b { display: block; margin-top: 7px; color: var(--forest); font-size: 1.1rem; }
    .work-list { border: 1px solid var(--line); background: #fff; }
    .work-row { display: grid; grid-template-columns: 30px 1fr auto; gap: 9px; align-items: center; min-height: 56px; padding: 8px 11px; border-bottom: 1px solid var(--line); }
    .work-row:last-child { border-bottom: 0; }
    .motor-icon { width: 28px; height: 28px; display: grid; place-items: center; background: var(--mint); color: var(--green-dark); border-radius: 5px; font-size: .82rem; }
    .work-row b { display: block; font-size: .68rem; }
    .work-row small { color: var(--muted); font-size: .56rem; }
    .status { padding: 4px 6px; background: var(--mint); color: var(--green-dark); border-radius: 999px; font-size: .53rem; font-weight: 800; }
    .status.progress { background: #eef5ed; color: #637064; }
    .floating-note { position: absolute; right: -28px; top: 0; width: 148px; padding: 16px; background: var(--mint-strong); box-shadow: 0 16px 30px rgba(0,59,22,.1); }
    .floating-note i { color: var(--forest); font-size: 1.15rem; }
    .floating-note p { margin: 10px 0 0; color: var(--forest); font-size: .68rem; font-weight: 800; line-height: 1.35; }

    .proof { display: grid; grid-template-columns: repeat(3, 1fr); border-top: 1px solid var(--line); border-bottom: 1px solid var(--line); }
    .proof-item { padding: 29px clamp(20px, 4vw, 70px); border-right: 1px solid var(--line); }
    .proof-item:last-child { border-right: 0; }
    .proof-item strong { display: block; color: var(--forest); font-size: 1.55rem; font-weight: 800; }
    .proof-item span { color: var(--muted); font-size: .78rem; }
    .features { padding: 88px clamp(24px, 8vw, 116px); background: var(--canvas); }
    .features-inner { max-width: 1220px; margin: 0 auto; }
    .section-label { color: var(--green-dark); font-size: .69rem; font-weight: 800; letter-spacing: .14em; }
    .features h2 { max-width: 650px; margin: 12px 0 38px; color: var(--forest); font-size: clamp(2rem, 3.7vw, 3.2rem); font-weight: 800; }
    .feature-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1px; background: var(--line); border: 1px solid var(--line); }
    .feature { min-height: 220px; padding: 27px; background: #fff; }
    .feature i { color: var(--green); font-size: 1.35rem; }
    .feature h3 { margin: 28px 0 9px; color: var(--forest); font-size: 1rem; font-weight: 800; }
    .feature p { margin: 0; color: var(--muted); font-size: .83rem; line-height: 1.55; }
    .footer { display: flex; justify-content: space-between; gap: 20px; padding: 28px clamp(24px, 8vw, 116px); color: var(--muted); font-size: .75rem; }

    @media (max-width: 900px) {
      .nav-links { display: none; }
      .hero { padding-top: 60px; }
      .hero-grid { grid-template-columns: 1fr; }
      .hero-copy { max-width: 620px; }
      .product-preview { max-width: 650px; width: calc(100% - 25px); margin: 10px auto 0; }
      .floating-note { right: -25px; }
    }
    @media (max-width: 600px) {
      .site-nav { height: 66px; padding-inline: 18px; }
      .nav-actions .btn-outline { display: none; }
      .brand { font-size: 1.08rem; }
      .hero { min-height: 0; padding: 52px 20px 54px; }
      h1 { font-size: 2.62rem; }
      .product-preview { min-height: 380px; width: 100%; }
      .preview-card { inset: 16px 0 0; }
      .preview-body { grid-template-columns: 75px 1fr; min-height: 330px; }
      .preview-sidebar { padding-inline: 6px; }
      .preview-sidebar span { justify-content: center; font-size: 0; }
      .preview-sidebar i { font-size: .85rem; }
      .preview-main { padding: 14px; }
      .metric-grid { grid-template-columns: repeat(2, 1fr); }
      .metric:last-child { display: none; }
      .floating-note { width: 114px; right: -10px; padding: 11px; }
      .floating-note p { font-size: .57rem; }
      .proof { grid-template-columns: 1fr; }
      .proof-item { border-right: 0; border-bottom: 1px solid var(--line); padding: 21px 24px; }
      .proof-item:last-child { border-bottom: 0; }
      .features { padding: 58px 20px; }
      .feature-grid { grid-template-columns: 1fr; }
      .footer { flex-direction: column; padding: 22px 20px; }
    }
  </style>
</head>
<body>
  <div class="site-shell">
    <header class="site-nav">
      <a class="brand" href="Homepage.php"><span class="brand-mark"><i class="bi bi-wrench-adjustable"></i></span>FixTrack</a>
      <nav class="nav-links" aria-label="Primary navigation">
        <a href="#platform">Platform</a>
        <a href="#features">Features</a>
        <a href="#about">About</a>
      </nav>
      <div class="nav-actions">
        <a class="btn btn-outline" href="login.php">Get started</a>
        <a class="btn btn-primary" href="login.php">Login</a>
      </div>
    </header>

    <main>
      <section class="hero" id="platform">
        <div class="hero-grid">
          <div>
            <h1>Built for smoother</h1>
            <h1>E-bike service.</h1>
            <div class="hero-actions">
              <a class="btn btn-primary" href="login.php">Get started</a>
              <a class="btn btn-outline" href="login.php">Login</a>
            </div>
          </div>

          <div class="product-preview" aria-label="KDA, KUDA, and NWOW store locations in Laguna">
            <div class="preview-card">
              <div class="preview-topbar"><span class="preview-brand"><i class="bi bi-geo-alt-fill me-1"></i>STORE LOCATIONS IN LAGUNA</span><span class="preview-user"></span></div>
              <div class="location-map">
                <iframe
                  src="https://www.google.com/maps?q=NWOW%20KUDA%20KDA%20E-bike%20Laguna%20Philippines&output=embed"
                  title="KDA, KUDA, and NWOW stores in Laguna"
                  allowfullscreen=""
                  loading="lazy">
                </iframe>
              </div>
            </div>
          </div>
        </div>
      </section>
    </main>

    <footer class="footer"><span>&copy; <?= date('Y') ?> FixTrack</span><span></span></footer>
  </div>
</body>
</html>

