<?php $currentPage = basename($_SERVER['PHP_SELF'] ?? ''); ?>
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
<link href="assets/css/app-theme.css?v=20260928-2" rel="stylesheet">
<header class="app-topbar">
  <a class="app-logo" href="customerdashboard.php"><i class="bi bi-lightning-charge-fill"></i><span>FixTrack Customer</span></a>
  <span class="app-account-dot" aria-hidden="true"></span>
</header>
<aside class="app-sidebar" aria-label="Customer navigation">
  <nav class="app-sidebar-nav">
    <a class="app-side-button <?= $currentPage === 'customerdashboard.php' ? 'is-active' : '' ?>" href="customerdashboard.php"><i class="bi bi-speedometer2"></i><span>Dashboard</span></a>
    <a class="app-side-button <?= $currentPage === 'customerbookrepair.php' ? 'is-active' : '' ?>" href="customerbookrepair.php"><i class="bi bi-tools"></i><span>Book Repair</span></a>
    <a class="app-side-button <?= $currentPage === 'customertrackrepiar.php' ? 'is-active' : '' ?>" href="customertrackrepiar.php"><i class="bi bi-search"></i><span>Track Repair</span></a>
    <a class="app-side-button <?= $currentPage === 'customermessage.php' ? 'is-active' : '' ?>" href="customermessage.php"><i class="bi bi-chat-dots"></i><span>Messages</span></a>
    <a class="app-side-button <?= $currentPage === 'activitylogs.php' ? 'is-active' : '' ?>" href="activitylogs.php"><i class="bi bi-clock-history"></i><span>Audit Trail</span></a>
    <a class="app-side-button app-logout" href="logout.php"><i class="bi bi-box-arrow-right"></i><span>Logout</span></a>
  </nav>
</aside>
