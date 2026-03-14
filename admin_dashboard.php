<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Speedly | Admin Dashboard (100% responsive)</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Boxicons (optional but used) -->
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <!-- Tailwind (light usage) -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <link rel="stylesheet" href="./CSS/admin_dashbboard.css">
</head>

<body>
    <div class="dashboard-container">
        <div class="desktop-view">
            <!-- SIDEBAR -->
            <div class="desktop-sidebar">

                <div class="desktop-nav">
                    <a href="#" class="desktop-nav-item active" data-page="dashboard"><i
                            class="fas fa-home desktop-nav-icon"></i><span>Dashboard</span></a>
                    <a href="#" class="desktop-nav-item" data-page="users"><i
                            class="fas fa-users desktop-nav-icon"></i><span>Users</span></a>
                    <a href="#" class="desktop-nav-item" data-page="drivers"><i
                            class="fas fa-id-card desktop-nav-icon"></i><span>Drivers</span></a>
                    <a href="#" class="desktop-nav-item" data-page="rides"><i
                            class="fas fa-car desktop-nav-icon"></i><span>Rides</span></a>
                    <a href="#" class="desktop-nav-item" data-page="payments"><i
                            class="fas fa-credit-card desktop-nav-icon"></i><span>Payments</span></a>
                    <a href="#" class="desktop-nav-item" data-page="wallets"><i
                            class="fas fa-wallet desktop-nav-icon"></i><span>Wallets</span></a>
                    <a href="#" class="desktop-nav-item" data-page="reports"><i
                            class="fas fa-chart-line desktop-nav-icon"></i><span>Reports</span></a>
                    <a href="#" class="desktop-nav-item" data-page="settings"><i
                            class="fas fa-cog desktop-nav-icon"></i><span>Settings</span></a>
                    <a href="#" class="desktop-nav-item" data-page="disputes"><i
                            class="fas fa-exclamation-triangle desktop-nav-icon"></i><span>Disputes</span></a>
                </div>
                <div class="user-profile">
                    <div class="profile-avatar">A</div>
                    <div class="profile-info">
                        <h3>Admin User</h3>
                        <p>Super Administrator</p>
                    </div>
                    <button class="logout-btn" id="logoutBtn"><i class="fas fa-sign-out-alt"></i></button>
                </div>
            </div>

            <!-- MAIN CONTENT (all pages) -->
            <div class="desktop-main" id="mainContent">
                <div class="desktop-header">
                    <div class="desktop-title" id="pageTitle">
                        <h1>Dashboard Overview</h1>
                        <p>Welcome back, Admin! Here's what's happening today.</p>
                    </div>
                    <div class="desktop-actions">
                        <div class="session-timer" id="sessionTimer"><i class="fas fa-clock"></i><span>Session:
                                29:45</span></div>
                        <button class="notification-btn"><i class="fas fa-bell"></i><span
                                class="notification-badge">5</span></button>
                    </div>
                </div>

                <!-- DASHBOARD PAGE -->
                <div id="dashboard-page" class="page active-page">
                    <div class="stats-grid"><!-- stats cards shortened for brevity, but complete in code -->
                        <div class="stat-card">
                            <div class="stat-icon users-icon"><i class="fas fa-users"></i></div>
                            <div class="stat-details">
                                <h3>Total Users</h3>
                                <div class="stat-value">1,284</div>
                                <div class="stat-change positive"><i class="fas fa-arrow-up"></i> +12%</div>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon drivers-icon"><i class="fas fa-id-card"></i></div>
                            <div class="stat-details">
                                <h3>Total Drivers</h3>
                                <div class="stat-value">342</div>
                                <div class="stat-change positive"><i class="fas fa-arrow-up"></i> +8%</div>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon active-rides-icon"><i class="fas fa-car"></i></div>
                            <div class="stat-details">
                                <h3>Active Rides</h3>
                                <div class="stat-value">24</div>
                                <div class="stat-change"><i class="fas fa-minus"></i> 0%</div>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon completed-icon"><i class="fas fa-check-circle"></i></div>
                            <div class="stat-details">
                                <h3>Completed Rides</h3>
                                <div class="stat-value">1,892</div>
                                <div class="stat-change positive"><i class="fas fa-arrow-up"></i> +15%</div>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon revenue-icon"><i class="fas fa-naira-sign"></i></div>
                            <div class="stat-details">
                                <h3>Total Revenue</h3>
                                <div class="stat-value">₦2.4M</div>
                                <div class="stat-change positive"><i class="fas fa-arrow-up"></i> +22%</div>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon pending-icon"><i class="fas fa-clock"></i></div>
                            <div class="stat-details">
                                <h3>Pending Withdrawals</h3>
                                <div class="stat-value">₦128K</div>
                                <div class="stat-change negative"><i class="fas fa-arrow-down"></i> 8 requests</div>
                            </div>
                        </div>
                    </div>
                    <div class="dashboard-grid">
                        <div class="desktop-card large">
                            <div class="card-header">
                                <h2>Revenue Overview</h2>
                                <div class="chart-filters"><button class="filter-btn active">Daily</button><button
                                        class="filter-btn">Weekly</button><button class="filter-btn">Monthly</button>
                                </div>
                            </div>
                            <div class="chart-container"><canvas id="revenueChart"></canvas></div>
                        </div>
                        <div class="desktop-card">
                            <div class="card-header">
                                <h2>Recent Withdrawals</h2><button class="see-all-btn filter-btn">View All</button>
                            </div>
                            <div class="withdrawal-list" id="recentWithdrawals"></div>
                        </div>
                        <div class="desktop-card">
                            <div class="card-header">
                                <h2>Top Performers</h2>
                            </div>
                            <div class="performer-list" id="topPerformers"></div>
                        </div>
                    </div>
                </div>

                <!-- USERS PAGE (simplified but full table) -->
                <div id="users-page" class="page">
                    <div class="management-header">
                        <h2>User Management</h2>
                        <div class="search-bar"><i class="fas fa-search"></i><input type="text"
                                placeholder="Search users..." id="userSearch"></div>
                    </div>
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>Rides</th>
                                    <th>Status</th>
                                    <th>Joined</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="usersTableBody">
                                <?php for($i=1;$i<=3;$i++): ?>
                                <tr>
                                    <td>#100
                                        <?php echo $i; ?>
                                    </td>
                                    <td>User
                                        <?php echo $i; ?>
                                    </td>
                                    <td>user
                                        <?php echo $i; ?>@mail.com
                                    </td>
                                    <td>+234 800 000 000</td>
                                    <td>
                                        <?php echo rand(5,50); ?>
                                    </td>
                                    <td><span class="status-badge active">Active</span></td>
                                    <td>2024-0
                                        <?php echo $i; ?>-10
                                    </td>
                                    <td class="actions-cell"><button class="action-icon-btn view-btn"><i
                                                class="fas fa-eye"></i></button><button
                                            class="action-icon-btn suspend-btn"><i class="fas fa-ban"></i></button></td>
                                </tr>
                                <?php endfor; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <!-- DRIVERS PAGE stub (to keep code shorter, we include minimal) -->
                <div id="drivers-page" class="page">
                    <div class="management-header">
                        <h2>Driver Management</h2>
                        <div class="filter-tabs"><button class="filter-tab active" data-filter="all">All</button><button
                                class="filter-tab" data-filter="pending">Pending</button></div>
                    </div>
                    <div class="table-container">...</div>
                </div>
                <div id="rides-page" class="page">Rides content</div>
                <div id="payments-page" class="page">Payments content</div>
                <div id="wallets-page" class="page">Wallets</div>
                <div id="reports-page" class="page">Reports</div>
                <div id="settings-page" class="page">Settings</div>
                <div id="disputes-page" class="page">Disputes</div>
            </div>
        </div>
        
    </div>
    
    <script src="./JS/admin_dashboard.js"></script>
</body>

</html>  