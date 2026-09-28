<?php $currentPage = basename($_SERVER['PHP_SELF'] ?? ''); ?>
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
<link href="assets/css/app-theme.css?v=20260928-2" rel="stylesheet">
<header class="app-topbar">
  <a class="app-logo" href="techniciandashboard.php"><i class="bi bi-wrench-adjustable"></i><span>FixTrack Technician</span></a>
  <span class="app-account-dot" aria-hidden="true"></span>
</header>
<aside class="app-sidebar" aria-label="Technician navigation">
  <nav class="app-sidebar-nav">
    <a class="app-side-button <?= $currentPage === 'techniciandashboard.php' ? 'is-active' : '' ?>" href="techniciandashboard.php"><i class="bi bi-speedometer2"></i><span>Dashboard</span></a>
    <a class="app-side-button <?= $currentPage === 'technicianrepair.php' ? 'is-active' : '' ?>" href="technicianrepair.php"><i class="bi bi-clipboard2-check"></i><span>Repairs</span></a>
    <a class="app-side-button <?= $currentPage === 'technicianmessage.php' ? 'is-active' : '' ?>" href="technicianmessage.php"><i class="bi bi-chat-dots"></i><span>Messages</span></a>
    <a class="app-side-button <?= $currentPage === 'activitylogs.php' ? 'is-active' : '' ?>" href="activitylogs.php"><i class="bi bi-clock-history"></i><span>Audit Trail</span></a>
    <a class="app-side-button app-logout" href="logout.php"><i class="bi bi-box-arrow-right"></i><span>Logout</span></a>
  </nav>
</aside>
