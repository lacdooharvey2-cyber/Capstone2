<?php $currentPage = basename($_SERVER['PHP_SELF'] ?? ''); ?>
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
<link href="assets/css/app-theme.css?v=20260928-2" rel="stylesheet">
<header class="app-topbar">
  <a class="app-logo" href="cashierdashboard.php"><i class="bi bi-cash-coin"></i><span>FixTrack Cashier</span></a>
  <span class="app-account-dot" aria-hidden="true"></span>
</header>
<aside class="app-sidebar" aria-label="Cashier navigation">
  <nav class="app-sidebar-nav">
    <a class="app-side-button <?= $currentPage === 'cashierdashboard.php' ? 'is-active' : '' ?>" href="cashierdashboard.php"><i class="bi bi-speedometer2"></i><span>Dashboard</span></a>
    <a class="app-side-button <?= $currentPage === 'cashiertransactions.php' ? 'is-active' : '' ?>" href="cashiertransactions.php"><i class="bi bi-credit-card"></i><span>Payments</span></a>
    <a class="app-side-button <?= $currentPage === 'cashierreports.php' ? 'is-active' : '' ?>" href="cashierreports.php"><i class="bi bi-file-earmark-bar-graph"></i><span>Reports</span></a>
    <a class="app-side-button <?= $currentPage === 'cashiermessage.php' ? 'is-active' : '' ?>" href="cashiermessage.php"><i class="bi bi-chat-dots"></i><span>Messages</span></a>
    <a class="app-side-button <?= $currentPage === 'activitylogs.php' ? 'is-active' : '' ?>" href="activitylogs.php"><i class="bi bi-clock-history"></i><span>Audit Trail</span></a>
    <a class="app-side-button app-logout" href="logout.php"><i class="bi bi-box-arrow-right"></i><span>Logout</span></a>
  </nav>
</aside>
