<?php
require_once __DIR__ . "/includes/bootstrap.php";
requireLogin();
requirePermission($conn, "view_dashboard");

$pageTitle = "Dashboard";
require_once __DIR__ . "/includes/layout_top.php";
require_once __DIR__ . "/includes/sidebar.php";

$userId = (int)$_SESSION["user_id"];
$role = $_SESSION["role_name"];

if ($role === "Admin" || $role === "Manager") {
    // Admin/Manager Dashboard Queries
    $totals = $conn->query(
        "SELECT
            COALESCE(SUM(CASE WHEN expense_date = CURDATE() THEN amount ELSE 0 END), 0) AS today_total,
            COALESCE(SUM(CASE WHEN expense_date = DATE_SUB(CURDATE(), INTERVAL 1 DAY) THEN amount ELSE 0 END), 0) AS yesterday_total,
            COALESCE(SUM(CASE WHEN expense_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN amount ELSE 0 END), 0) AS weekly_total,
            COALESCE(SUM(CASE WHEN MONTH(expense_date) = MONTH(CURDATE()) AND YEAR(expense_date) = YEAR(CURDATE()) THEN amount ELSE 0 END), 0) AS month_total,
            COALESCE(SUM(CASE WHEN YEAR(expense_date) = YEAR(CURDATE()) THEN amount ELSE 0 END), 0) AS year_total,
            COALESCE(SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END), 0) AS pending_count,
            COALESCE(SUM(CASE WHEN status = 'Approved' THEN 1 ELSE 0 END), 0) AS approved_count,
            COALESCE(SUM(CASE WHEN status = 'Rejected' THEN 1 ELSE 0 END), 0) AS rejected_count
         FROM expenses"
    )->fetch();

    $categoryRows = $conn->query(
        "SELECT category, COALESCE(SUM(amount), 0) AS total
         FROM expenses
         WHERE YEAR(expense_date) = YEAR(CURDATE())
         GROUP BY category
         ORDER BY total DESC"
    )->fetchAll();

    $monthlyRows = $conn->query(
        "SELECT DATE_FORMAT(expense_date, '%b') AS month_name, MONTH(expense_date) AS month_no, COALESCE(SUM(amount),0) AS total
         FROM expenses
         WHERE YEAR(expense_date) = YEAR(CURDATE())
         GROUP BY MONTH(expense_date), DATE_FORMAT(expense_date, '%b')
         ORDER BY month_no"
    )->fetchAll();

    $employeeRows = $conn->query(
        "SELECT u.name, COALESCE(SUM(e.amount),0) AS total
         FROM users u
         LEFT JOIN expenses e ON e.user_id = u.id AND YEAR(e.expense_date) = YEAR(CURDATE())
         INNER JOIN roles r ON r.id = u.role_id AND r.name = 'Employee'
         GROUP BY u.id, u.name
         ORDER BY total DESC
         LIMIT 10"
    )->fetchAll();

    $quick = $conn->query(
        "SELECT
            COALESCE(SUM(CASE WHEN status = 'Approved' THEN amount ELSE 0 END),0) AS approved_amount,
            COALESCE(SUM(CASE WHEN status = 'Rejected' THEN amount ELSE 0 END),0) AS rejected_amount
         FROM expenses
         WHERE MONTH(expense_date) = MONTH(CURDATE()) AND YEAR(expense_date) = YEAR(CURDATE())"
    )->fetch();
    
    // Get daily expenses for the month for trend chart
    $dailyRows = $conn->query(
        "SELECT DAY(expense_date) AS day, COALESCE(SUM(amount), 0) AS total
         FROM expenses
         WHERE MONTH(expense_date) = MONTH(CURDATE()) AND YEAR(expense_date) = YEAR(CURDATE())
         GROUP BY DAY(expense_date)
         ORDER BY day"
    )->fetchAll();
} else {
    // Employee Dashboard Queries
    $stmt = $conn->prepare(
        "SELECT
            COALESCE(SUM(CASE WHEN expense_date = CURDATE() THEN amount ELSE 0 END), 0) AS today_total,
            COALESCE(SUM(CASE WHEN expense_date = DATE_SUB(CURDATE(), INTERVAL 1 DAY) THEN amount ELSE 0 END), 0) AS yesterday_total,
            COALESCE(SUM(CASE WHEN expense_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN amount ELSE 0 END), 0) AS weekly_total,
            COALESCE(SUM(CASE WHEN MONTH(expense_date) = MONTH(CURDATE()) AND YEAR(expense_date) = YEAR(CURDATE()) THEN amount ELSE 0 END), 0) AS month_total,
            COALESCE(SUM(CASE WHEN YEAR(expense_date) = YEAR(CURDATE()) THEN amount ELSE 0 END), 0) AS year_total,
            COALESCE(SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END), 0) AS pending_count,
            COALESCE(SUM(CASE WHEN status = 'Rejected' THEN 1 ELSE 0 END), 0) AS rejected_count,
            COALESCE(SUM(CASE WHEN status = 'Approved' THEN 1 ELSE 0 END), 0) AS approved_count
         FROM expenses
         WHERE user_id = :user_id"
    );
    $stmt->execute(["user_id" => $userId]);
    $totals = $stmt->fetch();

    $stmt = $conn->prepare(
        "SELECT status, COUNT(*) AS cnt
         FROM backdate_requests
         WHERE user_id = :user_id
         GROUP BY status"
    );
    $stmt->execute(["user_id" => $userId]);
    $backdateRows = $stmt->fetchAll();
    $backdateMap = ["Pending" => 0, "Approved" => 0, "Rejected" => 0];
    foreach ($backdateRows as $row) {
        $backdateMap[$row["status"]] = (int)$row["cnt"];
    }

    $weeklyStmt = $conn->prepare(
        "SELECT expense_date, COALESCE(SUM(amount),0) AS total
         FROM expenses
         WHERE user_id = :user_id
           AND expense_date BETWEEN DATE_SUB(CURDATE(), INTERVAL 6 DAY) AND CURDATE()
         GROUP BY expense_date
         ORDER BY expense_date"
    );
    $weeklyStmt->execute(["user_id" => $userId]);
    $weeklyRows = $weeklyStmt->fetchAll();
    $weeklyMap = [];
    foreach ($weeklyRows as $r) {
        $weeklyMap[$r["expense_date"]] = (float)$r["total"];
    }

    $weekDates = [];
    $weekLabels = [];
    $weekValues = [];
    for ($i = 6; $i >= 0; $i--) {
        $d = date("Y-m-d", strtotime("-" . $i . " days"));
        $weekDates[] = $d;
        $weekLabels[] = date("D d M", strtotime($d));
        $weekValues[] = (float)($weeklyMap[$d] ?? 0);
    }

    $recentStmt = $conn->prepare(
        "SELECT expense_date, category, amount, status
         FROM expenses
         WHERE user_id = :user_id
         ORDER BY id DESC
         LIMIT 6"
    );
    $recentStmt->execute(["user_id" => $userId]);
    $recentExpenses = $recentStmt->fetchAll();
    
    // Get monthly trend data for employee
    $monthlyTrendStmt = $conn->prepare(
        "SELECT DATE_FORMAT(expense_date, '%b') AS month_name, MONTH(expense_date) AS month_no, COALESCE(SUM(amount), 0) AS total
         FROM expenses
         WHERE user_id = :user_id AND YEAR(expense_date) = YEAR(CURDATE())
         GROUP BY MONTH(expense_date), DATE_FORMAT(expense_date, '%b')
         ORDER BY month_no"
    );
    $monthlyTrendStmt->execute(["user_id" => $userId]);
    $monthlyTrendRows = $monthlyTrendStmt->fetchAll();
}
?>

<?php if ($role === "Admin" || $role === "Manager"): ?>
    <!-- Admin/Manager Dashboard -->
    <section class="dashboard-hero mb-4">
        <div>
            <h4 class="mb-1">🎯 Expense Analytics Dashboard</h4>
            <p class="mb-0">Track live approvals, categories, and expense trends across your organization.</p>
        </div>
        <span class="hero-chip"><i class="fa-solid fa-chart-line"></i> Live Dashboard</span>
    </section>
    
    <!-- KPI Cards Row 1 - Time-based Expenses -->
    <div class="row g-3 mb-3">
        <div class="col-md-4 col-lg-2">
            <div class="card stat-card kpi-today">
                <div>
                    <p class="stat-label">Today's Expense</p>
                    <p class="stat-value counter" data-target="<?php echo (float)$totals["today_total"]; ?>">Rs 0.00</p>
                </div>
                <div class="stat-icon"><i class="fa-solid fa-calendar-day"></i></div>
            </div>
        </div>
        <div class="col-md-4 col-lg-2">
            <div class="card stat-card kpi-yesterday">
                <div>
                    <p class="stat-label">Yesterday</p>
                    <p class="stat-value counter" data-target="<?php echo (float)$totals["yesterday_total"]; ?>">Rs 0.00</p>
                </div>
                <div class="stat-icon"><i class="fa-solid fa-calendar-check"></i></div>
            </div>
        </div>
        <div class="col-md-4 col-lg-2">
            <div class="card stat-card kpi-weekly">
                <div>
                    <p class="stat-label">This Week</p>
                    <p class="stat-value counter" data-target="<?php echo (float)$totals["weekly_total"]; ?>">Rs 0.00</p>
                </div>
                <div class="stat-icon"><i class="fa-solid fa-calendar-week"></i></div>
            </div>
        </div>
        <div class="col-md-4 col-lg-2">
            <div class="card stat-card kpi-monthly">
                <div>
                    <p class="stat-label">This Month</p>
                    <p class="stat-value counter" data-target="<?php echo (float)$totals["month_total"]; ?>">Rs 0.00</p>
                </div>
                <div class="stat-icon"><i class="fa-solid fa-calendar"></i></div>
            </div>
        </div>
        <div class="col-md-4 col-lg-2">
            <div class="card stat-card kpi-yearly">
                <div>
                    <p class="stat-label">This Year</p>
                    <p class="stat-value counter" data-target="<?php echo (float)$totals["year_total"]; ?>">Rs 0.00</p>
                </div>
                <div class="stat-icon"><i class="fa-solid fa-calendar-days"></i></div>
            </div>
        </div>
        <div class="col-md-4 col-lg-2">
            <div class="card stat-card kpi-pending">
                <div>
                    <p class="stat-label">Pending</p>
                    <p class="stat-value counter-int" data-target="<?php echo (int)$totals["pending_count"]; ?>">0</p>
                </div>
                <div class="stat-icon"><i class="fa-solid fa-hourglass-half"></i></div>
            </div>
        </div>
    </div>
    
    <!-- KPI Cards Row 2 - Approval Status -->
    <div class="row g-3 mb-3">
        <div class="col-md-6">
            <div class="card stat-card kpi-approved">
                <div>
                    <p class="stat-label">Approved This Month</p>
                    <p class="stat-value counter" data-target="<?php echo (float)$quick["approved_amount"]; ?>">Rs 0.00</p>
                </div>
                <div class="stat-icon"><i class="fa-solid fa-circle-check"></i></div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card stat-card kpi-rejected">
                <div>
                    <p class="stat-label">Rejected This Month</p>
                    <p class="stat-value counter" data-target="<?php echo (float)$quick["rejected_amount"]; ?>">Rs 0.00</p>
                </div>
                <div class="stat-icon"><i class="fa-solid fa-circle-xmark"></i></div>
            </div>
        </div>
    </div>
    
    <!-- Charts Row -->
    <div class="row g-3 mb-3">
        <div class="col-lg-8">
            <div class="card p-3 chart-card">
                <h6><i class="fa-solid fa-chart-simple"></i> Monthly Expense Trend</h6>
                <div class="chart-wrap"><canvas id="monthlyChart"></canvas></div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card p-3 chart-card">
                <h6><i class="fa-solid fa-chart-pie"></i> Category Distribution</h6>
                <div class="chart-wrap chart-wrap-small"><canvas id="categoryChart"></canvas></div>
            </div>
        </div>
    </div>
    
    <!-- Employee Chart -->
    <div class="card p-3 chart-card">
        <h6><i class="fa-solid fa-users"></i> Top Employee Expenses</h6>
        <div class="chart-wrap chart-wrap-medium"><canvas id="employeeChart"></canvas></div>
    </div>
    
<?php else: ?>
    <!-- Employee Dashboard -->
    <section class="dashboard-hero mb-4">
        <div>
            <h4 class="mb-1">👋 Welcome to Your Expense Dashboard</h4>
            <p class="mb-0">Track your daily submissions, approval progress, and expense history.</p>
        </div>
        <div class="hero-actions">
            <a class="btn btn-sm btn-light" href="expenses.php"><i class="fa-solid fa-plus"></i> Add Expense</a>
            <a class="btn btn-sm btn-outline-light" href="backdate_requests.php"><i class="fa-regular fa-calendar-xmark"></i> Request Backdate</a>
        </div>
    </section>
    
    <!-- Employee KPI Cards -->
    <div class="row g-3 mb-3">
        <div class="col-md-4 col-lg-2">
            <div class="card stat-card kpi-today">
                <div>
                    <p class="stat-label">Today's Expense</p>
                    <p class="stat-value counter" data-target="<?php echo (float)$totals["today_total"]; ?>">Rs 0.00</p>
                </div>
                <div class="stat-icon"><i class="fa-solid fa-calendar-day"></i></div>
            </div>
        </div>
        <div class="col-md-4 col-lg-2">
            <div class="card stat-card kpi-yesterday">
                <div>
                    <p class="stat-label">Yesterday</p>
                    <p class="stat-value counter" data-target="<?php echo (float)$totals["yesterday_total"]; ?>">Rs 0.00</p>
                </div>
                <div class="stat-icon"><i class="fa-solid fa-calendar-check"></i></div>
            </div>
        </div>
        <div class="col-md-4 col-lg-2">
            <div class="card stat-card kpi-weekly">
                <div>
                    <p class="stat-label">This Week</p>
                    <p class="stat-value counter" data-target="<?php echo (float)$totals["weekly_total"]; ?>">Rs 0.00</p>
                </div>
                <div class="stat-icon"><i class="fa-solid fa-calendar-week"></i></div>
            </div>
        </div>
        <div class="col-md-4 col-lg-2">
            <div class="card stat-card kpi-monthly">
                <div>
                    <p class="stat-label">This Month</p>
                    <p class="stat-value counter" data-target="<?php echo (float)$totals["month_total"]; ?>">Rs 0.00</p>
                </div>
                <div class="stat-icon"><i class="fa-solid fa-calendar"></i></div>
            </div>
        </div>
        <div class="col-md-4 col-lg-2">
            <div class="card stat-card kpi-yearly">
                <div>
                    <p class="stat-label">This Year</p>
                    <p class="stat-value counter" data-target="<?php echo (float)$totals["year_total"]; ?>">Rs 0.00</p>
                </div>
                <div class="stat-icon"><i class="fa-solid fa-calendar-days"></i></div>
            </div>
        </div>
        <div class="col-md-4 col-lg-2">
            <div class="card stat-card kpi-pending">
                <div>
                    <p class="stat-label">Pending</p>
                    <p class="stat-value counter-int" data-target="<?php echo (int)$totals["pending_count"]; ?>">0</p>
                </div>
                <div class="stat-icon"><i class="fa-solid fa-hourglass-start"></i></div>
            </div>
        </div>
    </div>
    
    <!-- Status Cards Row -->
    <div class="row g-3 mb-3">
        <div class="col-md-4">
            <div class="card stat-card kpi-approved">
                <div>
                    <p class="stat-label">Approved Expenses</p>
                    <p class="stat-value counter-int" data-target="<?php echo (int)$totals["approved_count"]; ?>">0</p>
                </div>
                <div class="stat-icon"><i class="fa-solid fa-check-circle"></i></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card stat-card kpi-rejected">
                <div>
                    <p class="stat-label">Rejected Expenses</p>
                    <p class="stat-value counter-int" data-target="<?php echo (int)$totals["rejected_count"]; ?>">0</p>
                </div>
                <div class="stat-icon"><i class="fa-solid fa-times-circle"></i></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card p-3 chart-card">
                <h6><i class="fa-solid fa-clock-rotate-left"></i> Backdate Request Status</h6>
                <div class="d-flex gap-3 flex-wrap mt-3">
                    <span class="badge badge-pending"><span class="status-dot pending"></span>Pending: <?php echo (int)$backdateMap["Pending"]; ?></span>
                    <span class="badge badge-approved"><span class="status-dot approved"></span>Approved: <?php echo (int)$backdateMap["Approved"]; ?></span>
                    <span class="badge badge-rejected"><span class="status-dot rejected"></span>Rejected: <?php echo (int)$backdateMap["Rejected"]; ?></span>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Charts and Recent Expenses -->
    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card p-3 chart-card">
                <div class="d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="fa-solid fa-chart-line"></i> Last 7 Days Trend</h6>
                    <span class="text-muted small"><?php echo clean_input($weekDates[0]); ?> to <?php echo clean_input($weekDates[count($weekDates)-1]); ?></span>
                </div>
                <div class="chart-wrap chart-wrap-medium"><canvas id="employeeWeekChart"></canvas></div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card p-3 chart-card">
                <h6><i class="fa-solid fa-receipt"></i> Recent Expenses</h6>
                <div class="list-group list-group-flush">
                    <?php foreach ($recentExpenses as $re): ?>
                        <div class="list-group-item px-0 d-flex justify-content-between align-items-start">
                            <div>
                                <div class="fw-semibold small"><?php echo clean_input($re["category"]); ?></div>
                                <div class="text-muted tiny-text"><?php echo clean_input($re["expense_date"]); ?></div>
                            </div>
                            <div class="text-end">
                                <div class="fw-bold small">Rs <?php echo formatCurrency($re["amount"]); ?></div>
                                <span class="badge badge-<?php echo strtolower($re["status"]); ?>"><?php echo clean_input($re["status"]); ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <?php if (!$recentExpenses): ?>
                        <div class="list-group-item px-0 text-muted">No recent expenses.</div>
                    <?php endif; ?>
                </div>
                <a class="btn btn-sm btn-outline-primary mt-3" href="expenses.php"><i class="fa-solid fa-eye"></i> View all</a>
            </div>
        </div>
    </div>
    
    <!-- Monthly Trend for Employee -->
    <div class="row g-3 mt-1">
        <div class="col-12">
            <div class="card p-3 chart-card">
                <h6><i class="fa-solid fa-chart-area"></i> Your Monthly Expense History</h6>
                <div class="chart-wrap chart-wrap-medium"><canvas id="employeeMonthlyChart"></canvas></div>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Admin/Manager Chart Scripts -->
<?php if ($role === "Admin" || $role === "Manager"): ?>
<script>
document.addEventListener("DOMContentLoaded", function () {
    const monthlyLabels = <?php echo json_encode(array_column($monthlyRows, "month_name")); ?>;
    const monthlyValues = <?php echo json_encode(array_map("floatval", array_column($monthlyRows, "total"))); ?>;
    const categoryLabels = <?php echo json_encode(array_column($categoryRows, "category")); ?>;
    const categoryValues = <?php echo json_encode(array_map("floatval", array_column($categoryRows, "total"))); ?>;
    const employeeLabels = <?php echo json_encode(array_column($employeeRows, "name")); ?>;
    const employeeValues = <?php echo json_encode(array_map("floatval", array_column($employeeRows, "total"))); ?>;

    const fallbackMonthLabels = ["Jan","Feb","Mar","Apr","May","Jun","Jul","Aug","Sep","Oct","Nov","Dec"];
    const monthL = monthlyLabels.length ? monthlyLabels : fallbackMonthLabels;
    const monthV = monthlyValues.length ? monthlyValues : [0,0,0,0,0,0,0,0,0,0,0,0];
    const catL = categoryLabels.length ? categoryLabels : ["No Data"];
    const catV = categoryValues.length ? categoryValues : [1];
    const empL = employeeLabels.length ? employeeLabels : ["No Data"];
    const empV = employeeValues.length ? employeeValues : [0];

    if (!window.Chart) {
        return;
    }

    // Monthly Bar Chart with gradient
    const monthlyCtx = document.getElementById("monthlyChart").getContext("2d");
    const monthlyGradient = monthlyCtx.createLinearGradient(0, 0, 0, 300);
    monthlyGradient.addColorStop(0, '#4361ee');
    monthlyGradient.addColorStop(1, '#4895ef');
    
    new Chart(document.getElementById("monthlyChart"), {
        type: "bar",
        data: { 
            labels: monthL, 
            datasets: [{ 
                label: "Expense Amount",
                data: monthV, 
                backgroundColor: monthlyGradient,
                borderRadius: 8,
                borderSkipped: false,
                barThickness: 32
            }] 
        },
        options: { 
            responsive: true, 
            maintainAspectRatio: false, 
            plugins: { 
                legend: { display: false },
                tooltip: {
                    backgroundColor: 'rgba(15, 23, 42, 0.9)',
                    padding: 12,
                    cornerRadius: 8
                }
            }, 
            scales: { 
                y: { 
                    beginAtZero: true,
                    grid: { color: 'rgba(67, 97, 238, 0.1)' }
                },
                x: {
                    grid: { display: false }
                }
            } 
        }
    });

    // Category Pie Chart with vibrant colors
    const vibrantColors = [
        '#4361ee', '#f72585', '#06d6a0', '#ffd60a', '#7c3aed', 
        '#ff6b6b', '#4895ef', '#2ec4b6', '#4f46e5', '#8b5cf6'
    ];
    
    new Chart(document.getElementById("categoryChart"), {
        type: "doughnut",
        data: { 
            labels: catL, 
            datasets: [{ 
                data: catV, 
                backgroundColor: vibrantColors,
                borderWidth: 3,
                borderColor: '#fff',
                hoverOffset: 10
            }] 
        },
        options: { 
            responsive: true, 
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { padding: 15, usePointStyle: true }
                }
            },
            cutout: '60%'
        }
    });

    // Employee Line Chart with gradient fill
    const empCtx = document.getElementById("employeeChart").getContext("2d");
    const empGradient = empCtx.createLinearGradient(0, 0, 0, 300);
    empGradient.addColorStop(0, 'rgba(124, 58, 237, 0.3)');
    empGradient.addColorStop(1, 'rgba(124, 58, 237, 0)');
    
    new Chart(document.getElementById("employeeChart"), {
        type: "line",
        data: { 
            labels: empL, 
            datasets: [{ 
                label: "Expense Total",
                data: empV, 
                borderColor: '#7c3aed',
                backgroundColor: empGradient,
                fill: true, 
                tension: 0.4,
                pointBackgroundColor: '#7c3aed',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointRadius: 5,
                pointHoverRadius: 7
            }] 
        },
        options: { 
            responsive: true, 
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: { 
                y: { 
                    beginAtZero: true,
                    grid: { color: 'rgba(124, 58, 237, 0.1)' }
                },
                x: {
                    grid: { display: false }
                }
            } 
        }
    });
});
</script>
<?php endif; ?>

<!-- Employee Chart Scripts -->
<?php if ($role !== "Admin" && $role !== "Manager"): ?>
<script>
document.addEventListener("DOMContentLoaded", function () {
    if (!window.Chart) return;
    
    // Weekly Line Chart with gradient
    const weekCtx = document.getElementById("employeeWeekChart").getContext("2d");
    const weekGradient = weekCtx.createLinearGradient(0, 0, 0, 280);
    weekGradient.addColorStop(0, 'rgba(67, 97, 238, 0.25)');
    weekGradient.addColorStop(1, 'rgba(67, 97, 238, 0)');
    
    const labels = <?php echo json_encode($weekLabels); ?>;
    const values = <?php echo json_encode($weekValues); ?>;
    
    new Chart(document.getElementById("employeeWeekChart"), {
        type: "line",
        data: { 
            labels: labels, 
            datasets: [{ 
                label: "Daily Expense",
                data: values, 
                borderColor: '#4361ee',
                backgroundColor: weekGradient,
                fill: true, 
                tension: 0.4,
                pointBackgroundColor: '#4361ee',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointRadius: 4,
                pointHoverRadius: 6
            }] 
        },
        options: { 
            responsive: true, 
            maintainAspectRatio: false, 
            plugins: { 
                legend: { display: false },
                tooltip: {
                    backgroundColor: 'rgba(15, 23, 42, 0.9)',
                    padding: 12,
                    cornerRadius: 8
                }
            }, 
            scales: { 
                y: { 
                    beginAtZero: true,
                    grid: { color: 'rgba(67, 97, 238, 0.1)' }
                },
                x: {
                    grid: { display: false }
                }
            } 
        }
    });
    
    // Monthly Trend Chart for Employee
    const monthlyTrendLabels = <?php echo json_encode(array_column($monthlyTrendRows ?? [], "month_name")); ?>;
    const monthlyTrendValues = <?php echo json_encode(array_map("floatval", array_column($monthlyTrendRows ?? [], "total"))); ?>;
    
    const fallbackTrendLabels = ["Jan","Feb","Mar","Apr","May","Jun","Jul","Aug","Sep","Oct","Nov","Dec"];
    const trendL = monthlyTrendLabels.length ? monthlyTrendLabels : fallbackTrendLabels;
    const trendV = monthlyTrendValues.length ? monthlyTrendValues : [0,0,0,0,0,0,0,0,0,0,0,0];
    
    const trendCtx = document.getElementById("employeeMonthlyChart").getContext("2d");
    const trendGradient = trendCtx.createLinearGradient(0, 0, 0, 280);
    trendGradient.addColorStop(0, 'rgba(247, 37, 133, 0.3)');
    trendGradient.addColorStop(1, 'rgba(247, 37, 133, 0)');
    
    new Chart(document.getElementById("employeeMonthlyChart"), {
        type: "bar",
        data: { 
            labels: trendL, 
            datasets: [{ 
                label: "Monthly Expense",
                data: trendV, 
                backgroundColor: trendGradient,
                borderColor: '#f72585',
                borderWidth: 2,
                borderRadius: 8,
                borderSkipped: false,
                barThickness: 28
            }] 
        },
        options: { 
            responsive: true, 
            maintainAspectRatio: false,
            plugins: { 
                legend: { display: false }
            },
            scales: { 
                y: { 
                    beginAtZero: true,
                    grid: { color: 'rgba(247, 37, 133, 0.1)' }
                },
                x: {
                    grid: { display: false }
                }
            } 
        }
    });
});
</script>
<?php endif; ?>

<!-- Counter Animation Script -->
<script>
document.addEventListener("DOMContentLoaded", function () {
    const moneyNodes = document.querySelectorAll(".counter");
    const intNodes = document.querySelectorAll(".counter-int");

    function animateValue(node, target, asMoney) {
        const duration = 1200;
        const start = performance.now();
        
        // Easing function
        function easeOutQuart(t) {
            return 1 - Math.pow(1 - t, 4);
        }
        
        function frame(now) {
            const elapsed = now - start;
            const progress = Math.min(elapsed / duration, 1);
            const easedProgress = easeOutQuart(progress);
            const value = target * easedProgress;
            
            node.textContent = asMoney ? "Rs " + value.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) : Math.round(value).toString();
            
            if (progress < 1) {
                requestAnimationFrame(frame);
            }
        }
        requestAnimationFrame(frame);
    }

    moneyNodes.forEach((node) => {
        const target = parseFloat(node.dataset.target || "0");
        if (target > 0) {
            animateValue(node, target, true);
        } else {
            node.textContent = "Rs 0.00";
        }
    });
    
    intNodes.forEach((node) => {
        const target = parseInt(node.dataset.target || "0", 10);
        if (target > 0) {
            animateValue(node, target, false);
        } else {
            node.textContent = "0";
        }
    });
});
</script>

<?php require_once __DIR__ . "/includes/layout_bottom.php"; ?>
