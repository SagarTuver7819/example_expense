<?php
$notificationCount = unreadNotificationCount($conn, (int)$_SESSION["user_id"]);
$role = $_SESSION["role_name"];
$notificationStmt = $conn->prepare(
    "SELECT id, title, message, created_at
     FROM notifications
     WHERE user_id = :user_id
     ORDER BY id DESC
     LIMIT 5"
);
$notificationStmt->execute(["user_id" => (int)$_SESSION["user_id"]]);
$latestNotifications = $notificationStmt->fetchAll();

$sidebarMark = "assets/img/ocean-mark.svg";
if (file_exists(__DIR__ . "/../assets/img/ocean-mark.png")) {
    $sidebarMark = "assets/img/ocean-mark.png";
} elseif (file_exists(__DIR__ . "/../assets/img/ocean-logo-mark.png")) {
    $sidebarMark = "assets/img/ocean-logo-mark.png";
}
?>
<div class="wrapper">
    <aside class="sidebar">
        <div class="sidebar-header">
            <a href="dashboard.php" class="brand-mark" title="Ocean Infotech">
                <img src="<?php echo clean_input($sidebarMark); ?>" alt="Ocean Infotech" />
            </a>
            <button class="sidebar-close d-lg-none" type="button" id="sidebarCloseBtn" title="Close menu">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <nav class="nav flex-column">
            <a class="nav-link" href="dashboard.php"><i class="fa-solid fa-chart-line"></i> Dashboard</a>

            <?php if (hasPermission($conn, "add_expense") || hasPermission($conn, "view_own_expense")): ?>
                <a class="nav-link" href="expenses.php"><i class="fa-solid fa-receipt"></i> My Expenses</a>
            <?php endif; ?>

            <?php if (hasPermission($conn, "request_backdate")): ?>
                <a class="nav-link" href="backdate_requests.php"><i class="fa-regular fa-calendar-xmark"></i> Backdate Requests</a>
            <?php endif; ?>

            <?php if (hasPermission($conn, "approve_expense")): ?>
                <a class="nav-link" href="approvals.php"><i class="fa-solid fa-check-double"></i> Expense Approvals</a>
            <?php endif; ?>

            <?php if (hasPermission($conn, "approve_backdate")): ?>
                <a class="nav-link" href="backdate_approvals.php"><i class="fa-solid fa-clock-rotate-left"></i> Backdate Approvals</a>
            <?php endif; ?>

            <?php if (hasPermission($conn, "manage_users")): ?>
                <a class="nav-link" href="users.php"><i class="fa-solid fa-users"></i> Users</a>
            <?php endif; ?>

            <?php if (hasPermission($conn, "manage_roles")): ?>
                <a class="nav-link" href="roles.php"><i class="fa-solid fa-user-shield"></i> Roles</a>
            <?php endif; ?>

            <?php if (hasPermission($conn, "view_reports")): ?>
                <a class="nav-link" href="reports.php"><i class="fa-solid fa-file-lines"></i> Reports</a>
            <?php endif; ?>

            <a class="nav-link" href="logout.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
        </nav>
    </aside>

    <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

    <main class="main-content">
        <header class="header">
            <div class="header-left">
                <button class="btn-sidebar-toggle d-lg-none" type="button" id="sidebarToggleBtn" aria-label="Toggle menu">
                    <i class="fa-solid fa-bars"></i>
                </button>
                <h5 class="m-0 text-truncate"><?php echo clean_input($pageTitle); ?></h5>
            </div>
            <div class="user-profile">
                <div class="dropdown">
                    <button class="btn btn-notify position-relative" data-bs-toggle="dropdown" aria-expanded="false" title="Notifications">
                        <i class="fa-regular fa-bell"></i>
                        <?php if ($notificationCount > 0): ?>
                            <span class="notification-dot"><?php echo $notificationCount; ?></span>
                        <?php endif; ?>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end shadow notification-menu p-0">
                        <div class="notification-head d-flex justify-content-between align-items-center px-3 py-2">
                            <strong>Notifications</strong>
                            <a href="notifications.php" class="small">View all</a>
                        </div>
                        <div class="notification-list">
                            <?php foreach ($latestNotifications as $item): ?>
                                <a href="notifications.php" class="dropdown-item py-2">
                                    <div class="fw-semibold small"><?php echo clean_input($item["title"]); ?></div>
                                    <div class="text-muted tiny-text"><?php echo clean_input(strlen($item["message"]) > 70 ? substr($item["message"], 0, 67) . "..." : $item["message"]); ?></div>
                                </a>
                            <?php endforeach; ?>
                            <?php if (!$latestNotifications): ?>
                                <div class="px-3 py-3 text-muted small">No notifications yet.</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <span class="text-muted"><?php echo clean_input($role); ?></span>
                <div class="user-avatar"><?php echo strtoupper(substr($_SESSION["name"], 0, 1)); ?></div>
            </div>
        </header>
