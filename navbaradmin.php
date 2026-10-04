<?php
$currentAdminRole = $_SESSION['role'] ?? '';
$canMaintainSystem = in_array($currentAdminRole, ['SuperAdmin', 'AssistantSuperAdmin'], true);
$currentPage = basename($_SERVER['PHP_SELF'] ?? '');
?>
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
<link href="assets/css/app-theme.css?v=20260928-2" rel="stylesheet">
<header class="app-topbar">
  <a class="app-logo" href="admindashboard.php"><i class="bi bi-shield-lock"></i><span>FixTrack Admin</span></a>
  <span class="app-account-dot" aria-hidden="true"></span>
</header>
<aside class="app-sidebar" aria-label="Admin navigation">
  <nav class="app-sidebar-nav">
    <a class="app-side-button <?= $currentPage === 'admindashboard.php' ? 'is-active' : '' ?>" href="admindashboard.php"><i class="bi bi-speedometer2"></i><span>Dashboard</span></a>
    <?php if ($canMaintainSystem): ?>
    <a class="app-side-button <?= $currentPage === 'adminmanageusers.php' ? 'is-active' : '' ?>" href="adminmanageusers.php"><i class="bi bi-people"></i><span>Users</span></a>
    <?php endif; ?>
    <a class="app-side-button <?= $currentPage === 'adminrepairs.php' ? 'is-active' : '' ?>" href="adminrepairs.php"><i class="bi bi-tools"></i><span>Repairs</span></a>
    <a class="app-side-button <?= $currentPage === 'adminwarranties.php' ? 'is-active' : '' ?>" href="adminwarranties.php"><i class="bi bi-shield-check"></i><span>Warranty</span></a>
    <a class="app-side-button <?= $currentPage === 'adminmessages.php' ? 'is-active' : '' ?>" href="adminmessages.php"><i class="bi bi-chat-dots"></i><span>Messages</span></a>
    <a class="app-side-button <?= $currentPage === 'adminanalytics.php' ? 'is-active' : '' ?>" href="adminanalytics.php"><i class="bi bi-graph-up-arrow"></i><span>Analytics</span></a>
    <?php if ($canMaintainSystem): ?>
    <a class="app-side-button <?= $currentPage === 'activitylogs.php' ? 'is-active' : '' ?>" href="activitylogs.php"><i class="bi bi-clock-history"></i><span>Audit Trail</span></a>
    <?php endif; ?>
    <a class="app-side-button app-logout" href="logout.php"><i class="bi bi-box-arrow-right"></i><span>Logout</span></a>
  </nav>
</aside>
