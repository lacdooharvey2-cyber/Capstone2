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
  <link href="assets/css/homepage.css?v=20261014" rel="stylesheet">
</head>
<body>
  <div class="site-shell">
    <header class="site-nav">
      <a class="brand" href="Homepage.php"><span class="brand-mark"><i class="bi bi-wrench-adjustable"></i></span>FixTrack</a>
      <div class="nav-actions">
        <a class="btn btn-primary" href="login.php">Login</a>
      </div>
    </header>

    <main>
      <section class="hero" id="platform"> 
        <div class="hero-grid">
          <div>
            <h1 class="hero-title">Built for smoother<br>E-bike service.</h1>
            <p class="hero-copy">A complete service management platform for e-bike owners, technicians, and cashiers. Book repairs, track service progress, and manage warranty coverage in one workspace.</p>
            <div class="hero-actions">
              <a class="btn btn-primary" href="login.php">Get started</a>
            </div>
            <p class="trust-line"><i class="bi bi-check-circle-fill"></i> Trusted by KDA, KUDA, and NWOW service centers</p>
          </div>

          <div class="product-preview" aria-label="Store locations map preview">
            <div class="preview-card">
              <div class="preview-topbar"><span class="preview-brand"><i class="bi bi-geo-alt-fill me-1"></i>STORE LOCATIONS IN LAGUNA</span><span class="preview-user"></span></div>
              <div class="location-map">
                <iframe
                  src="https://www.google.com/maps?q=NWOW%2C+KUDA%2C+KDA+E-bike+Laguna+Philippines&output=embed"
                  title="KDA, KUDA, and NWOW stores in Laguna"
                  loading="lazy"
                  referrerpolicy="no-referrer-when-downgrade">
                </iframe>
              </div>
            </div>
          </div>
        </div>
      </section>
    </main>

    <footer class="footer">
      <span>&copy; <?= date('Y') ?> FixTrack</span>
      <span class="text-muted">E-bike Repair Management System</span>
    </footer>
  </div>
</body>
</html>
