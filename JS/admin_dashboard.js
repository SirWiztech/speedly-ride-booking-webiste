// Admin Dashboard JavaScript - Fixed & Updated Version
(function() {
    'use strict';

    // ========== GLOBAL STATE ==========
    let loggedIn = localStorage.getItem('adminLoggedIn') === 'true';
    let timeLeft = 1800; // 30 minutes in seconds
    let timerInterval = null;
    let charts = {};

    // DOM Elements
    const loginModal = document.getElementById('loginModal');
    const timerSpan = document.querySelector('#sessionTimer span');
    const navItems = document.querySelectorAll('.desktop-nav-item');
    const pages = document.querySelectorAll('.page');
    const titleH1 = document.querySelector('#pageTitle h1');
    const subtitleP = document.querySelector('#pageTitle p');
    const logoutBtn = document.getElementById('logoutBtn');
    const loginForm = document.getElementById('adminLoginForm');

    // ========== INITIALIZATION ==========
    function init() {
        checkAuthStatus();
        populateDashboard();
        initializeAllCharts();
        setupNavigation();
        setupSearch();
        setupFilters();
        setupModals();
        setupActionButtons();
        setupSettings();
        setupWithdrawalFilter();
        setupReportSelects();
        setupExportButtons();
        setupNotificationButton();
        setupSeeAllButtons();
        setupDateFilter();
        setupChartFilters();
        
        if (loggedIn) {
            startSessionTimer();
        }
    }

    // ========== AUTHENTICATION ==========
    function checkAuthStatus() {
        if (!loggedIn) {
            loginModal?.classList.add('show');
        } else {
            loginModal?.classList.remove('show');
        }
    }

    // ========== SESSION TIMER ==========
    function startSessionTimer() {
        if (timerInterval) clearInterval(timerInterval);
        
        timerInterval = setInterval(() => {
            if (!loggedIn) {
                clearInterval(timerInterval);
                return;
            }
            
            timeLeft--;
            
            if (timeLeft <= 0) {
                clearInterval(timerInterval);
                logout();
                alert('Session expired. Please login again.');
                return;
            }
            
            const minutes = Math.floor(timeLeft / 60);
            const seconds = timeLeft % 60;
            if (timerSpan) {
                timerSpan.textContent = `Session: ${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
            }
        }, 1000);
    }

    // ========== LOGOUT ==========
    window.logout = function() {
        localStorage.removeItem('adminLoggedIn');
        loggedIn = false;
        loginModal?.classList.add('show');
        if (timerInterval) {
            clearInterval(timerInterval);
            timerInterval = null;
        }
        logActivity('Admin logged out');
    };

    if (logoutBtn) {
        logoutBtn.addEventListener('click', (e) => {
            e.preventDefault();
            if (confirm('Are you sure you want to logout?')) {
                logout();
            }
        });
    }

    // ========== LOGIN ==========
    if (loginForm) {
        loginForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const username = document.getElementById('username')?.value;
            const password = document.getElementById('password')?.value;
            
            // Simple validation (in real app, check against server)
            if (username === 'admin' && password === 'admin123') {
                localStorage.setItem('adminLoggedIn', 'true');
                loggedIn = true;
                loginModal?.classList.remove('show');
                alert('Login successful!');
                logActivity('Admin logged in');
                
                // Reset session timer
                timeLeft = 1800;
                startSessionTimer();
            } else {
                alert('Invalid username or password');
            }
        });
    }

    // ========== DASHBOARD POPULATION ==========
    function populateDashboard() {
        // Recent Withdrawals
        const wl = document.getElementById('recentWithdrawals');
        if (wl) {
            wl.innerHTML = `
                <div class="withdrawal-item">
                    <div class="withdrawal-info">
                        <h4>John Driver</h4>
                        <p>Requested: ₦25,000</p>
                    </div>
                    <span class="status-badge pending">Pending</span>
                </div>
                <div class="withdrawal-item">
                    <div class="withdrawal-info">
                        <h4>Sarah Smith</h4>
                        <p>Requested: ₦18,500</p>
                    </div>
                    <span class="status-badge approved">Approved</span>
                </div>
                <div class="withdrawal-item">
                    <div class="withdrawal-info">
                        <h4>Mike Johnson</h4>
                        <p>Requested: ₦32,000</p>
                    </div>
                    <span class="status-badge paid">Paid</span>
                </div>
                <div class="withdrawal-item">
                    <div class="withdrawal-info">
                        <h4>Emma Wilson</h4>
                        <p>Requested: ₦12,750</p>
                    </div>
                    <span class="status-badge rejected">Rejected</span>
                </div>
            `;
        }

        // Top Performers
        const tp = document.getElementById('topPerformers');
        if (tp) {
            tp.innerHTML = `
                <div class="performer-item">
                    <div class="performer-rank">1</div>
                    <div class="performer-info">
                        <h4>David Okafor</h4>
                        <p>142 rides • 4.9 ★</p>
                    </div>
                    <div class="performer-earnings">₦425K</div>
                </div>
                <div class="performer-item">
                    <div class="performer-rank">2</div>
                    <div class="performer-info">
                        <h4>Grace Eze</h4>
                        <p>128 rides • 4.8 ★</p>
                    </div>
                    <div class="performer-earnings">₦384K</div>
                </div>
                <div class="performer-item">
                    <div class="performer-rank">3</div>
                    <div class="performer-info">
                        <h4>Ahmed Bello</h4>
                        <p>115 rides • 4.9 ★</p>
                    </div>
                    <div class="performer-earnings">₦345K</div>
                </div>
            `;
        }
    }

    // ========== CHARTS INITIALIZATION ==========
    function initializeAllCharts() {
        // Revenue Chart (Dashboard)
        const revenueCtx = document.getElementById('revenueChart')?.getContext('2d');
        if (revenueCtx) {
            charts.revenue = new Chart(revenueCtx, {
                type: 'line',
                data: {
                    labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                    datasets: [{
                        label: 'Revenue (₦)',
                        data: [85000, 92000, 88000, 95000, 102000, 115000, 124500],
                        borderColor: '#ff5e00',
                        backgroundColor: 'rgba(255, 94, 0, 0.1)',
                        tension: 0.4,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { callback: v => '₦' + v.toLocaleString() }
                        }
                    }
                }
            });
        }

        // Daily Chart (Reports)
        const dailyCtx = document.getElementById('dailyChart')?.getContext('2d');
        if (dailyCtx) {
            charts.daily = new Chart(dailyCtx, {
                type: 'bar',
                data: {
                    labels: ['10am', '12pm', '2pm', '4pm', '6pm', '8pm'],
                    datasets: [{
                        data: [12500, 18400, 22300, 19800, 25600, 18900],
                        backgroundColor: '#ff5e00',
                        borderRadius: 5
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { callback: v => '₦' + v.toLocaleString() }
                        }
                    }
                }
            });
        }

        // Weekly Chart (Reports)
        const weeklyCtx = document.getElementById('weeklyChart')?.getContext('2d');
        if (weeklyCtx) {
            charts.weekly = new Chart(weeklyCtx, {
                type: 'line',
                data: {
                    labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                    datasets: [{
                        data: [124500, 118200, 132800, 125400, 142300, 156800, 142300],
                        borderColor: '#ff5e00',
                        backgroundColor: 'rgba(255, 94, 0, 0.1)',
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } }
                }
            });
        }

        // Monthly Chart (Reports)
        const monthlyCtx = document.getElementById('monthlyChart')?.getContext('2d');
        if (monthlyCtx) {
            charts.monthly = new Chart(monthlyCtx, {
                type: 'bar',
                data: {
                    labels: ['Week 1', 'Week 2', 'Week 3', 'Week 4'],
                    datasets: [{
                        data: [584200, 612500, 598400, 661700],
                        backgroundColor: '#ff5e00',
                        borderRadius: 5
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } }
                }
            });
        }
    }

    // ========== NAVIGATION ==========
    function setupNavigation() {
        navItems.forEach(item => {
            item.addEventListener('click', (e) => {
                e.preventDefault();
                
                // Update active nav item
                navItems.forEach(n => n.classList.remove('active'));
                item.classList.add('active');
                
                // Show corresponding page
                const pageId = item.dataset.page + '-page';
                pages.forEach(p => p.classList.remove('active-page'));
                const target = document.getElementById(pageId);
                if (target) target.classList.add('active-page');
                
                // Update header
                const pageName = item.querySelector('span').innerText;
                titleH1.innerText = pageName;
                
                // Update subtitle based on page
                const subtitles = {
                    'Dashboard': 'Welcome back, Admin! Here\'s what\'s happening today.',
                    'Users': 'Manage all registered users and their ride history.',
                    'Drivers': 'Approve and manage driver accounts.',
                    'Rides': 'Monitor and manage all rides.',
                    'Payments': 'View all payment transactions.',
                    'Wallets': 'Manage driver wallets and withdrawals.',
                    'Reports': 'Analytics and performance reports.',
                    'Settings': 'Configure system settings.',
                    'Disputes': 'Manage complaints and resolve disputes.'
                };
                subtitleP.innerText = subtitles[pageName] || `Managing ${pageName.toLowerCase()}`;
                
                // Log activity for settings page
                if (pageName === 'Settings') {
                    logActivity('Viewed system settings');
                }
            });
        });
    }

    // ========== SEARCH FUNCTIONALITY ==========
    function setupSearch() {
        const userSearch = document.getElementById('userSearch');
        if (userSearch) {
            userSearch.addEventListener('input', function(e) {
                const searchTerm = e.target.value.toLowerCase();
                document.querySelectorAll('#usersTableBody tr').forEach(row => {
                    const text = row.textContent.toLowerCase();
                    row.style.display = text.includes(searchTerm) ? '' : 'none';
                });
            });
        }

        const driverSearch = document.getElementById('driverSearch');
        if (driverSearch) {
            driverSearch.addEventListener('input', function(e) {
                const searchTerm = e.target.value.toLowerCase();
                document.querySelectorAll('#driversTableBody tr').forEach(row => {
                    const text = row.textContent.toLowerCase();
                    row.style.display = text.includes(searchTerm) ? '' : 'none';
                });
            });
        }
    }

    // ========== FILTER TABS ==========
    function setupFilters() {
        document.querySelectorAll('.filter-tab').forEach(tab => {
            tab.addEventListener('click', function() {
                const parent = this.closest('.filter-tabs');
                if (!parent) return;
                
                parent.querySelectorAll('.filter-tab').forEach(t => t.classList.remove('active'));
                this.classList.add('active');
                
                const filter = this.dataset.filter;
                const table = this.closest('.management-header')?.nextElementSibling?.querySelector('tbody');
                
                if (table) {
                    table.querySelectorAll('tr').forEach(row => {
                        const statusCell = row.querySelector('.status-badge');
                        if (statusCell) {
                            const status = statusCell.classList[1]; // pending, approved, etc.
                            row.style.display = (filter === 'all' || status === filter) ? '' : 'none';
                        }
                    });
                }
            });
        });
    }

    // ========== MODALS ==========
    function setupModals() {
        // View buttons
        document.querySelectorAll('.view-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const modal = document.getElementById('userHistoryModal');
                if (modal) modal.classList.add('show');
            });
        });

        // Close modal
        document.querySelectorAll('.close-modal').forEach(btn => {
            btn.addEventListener('click', function() {
                this.closest('.modal')?.classList.remove('show');
            });
        });

        // Close modal when clicking outside
        window.addEventListener('click', (e) => {
            if (e.target.classList.contains('modal')) {
                e.target.classList.remove('show');
            }
        });
    }

    // ========== ACTION BUTTONS ==========
    function setupActionButtons() {
        // Suspend buttons
        document.querySelectorAll('.suspend-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                if (confirm('Are you sure you want to suspend this user/driver?')) {
                    const row = this.closest('tr');
                    const statusCell = row?.querySelector('.status-badge');
                    if (statusCell) {
                        statusCell.className = 'status-badge suspended';
                        statusCell.textContent = 'Suspended';
                    }
                    alert('Account suspended successfully');
                    logActivity('Suspended an account');
                }
            });
        });

        // Delete buttons
        document.querySelectorAll('.delete-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                if (confirm('Are you sure you want to delete this user? This action cannot be undone.')) {
                    this.closest('tr')?.remove();
                    alert('User deleted successfully');
                    logActivity('Deleted a user');
                }
            });
        });

        // Approve buttons
        document.querySelectorAll('.approve-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const row = this.closest('tr');
                const statusCell = row?.querySelector('.status-badge');
                if (statusCell) {
                    statusCell.className = 'status-badge approved';
                    statusCell.textContent = 'Approved';
                }
                alert('Driver approved successfully');
                logActivity('Approved a driver');
            });
        });

        // Reject buttons
        document.querySelectorAll('.reject-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const row = this.closest('tr');
                const statusCell = row?.querySelector('.status-badge');
                if (statusCell) {
                    statusCell.className = 'status-badge rejected';
                    statusCell.textContent = 'Rejected';
                }
                alert('Driver rejected');
                logActivity('Rejected a driver');
            });
        });

        // Resolve buttons
        document.querySelectorAll('.resolve-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                if (confirm('Mark this dispute as resolved?')) {
                    const row = this.closest('tr');
                    const statusCell = row?.querySelector('.status-badge');
                    if (statusCell) {
                        statusCell.className = 'status-badge resolved';
                        statusCell.textContent = 'Resolved';
                    }
                    alert('Dispute resolved');
                    logActivity('Resolved a dispute');
                }
            });
        });

        // Refund buttons
        document.querySelectorAll('.refund-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                if (confirm('Process refund for this transaction?')) {
                    alert('Refund processed successfully');
                    logActivity('Processed a refund');
                }
            });
        });

        // Mark as paid buttons
        document.querySelectorAll('.mark-paid').forEach(btn => {
            btn.addEventListener('click', function() {
                const row = this.closest('tr');
                const statusCell = row?.querySelector('.status-badge');
                if (statusCell) {
                    statusCell.className = 'status-badge paid';
                    statusCell.textContent = 'Paid';
                }
                alert('Withdrawal marked as paid');
                logActivity('Processed withdrawal');
            });
        });

        // View document buttons
        document.querySelectorAll('.view-doc-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                alert('Opening document viewer...');
                logActivity('Viewed driver documents');
            });
        });
    }

    // ========== SETTINGS ==========
    function setupSettings() {
        const saveBtn = document.getElementById('saveSettings');
        if (!saveBtn) return;

        // Load saved settings
        const savedSettings = localStorage.getItem('systemSettings');
        if (savedSettings) {
            try {
                const settings = JSON.parse(savedSettings);
                Object.keys(settings).forEach(key => {
                    const element = document.getElementById(key);
                    if (element) {
                        if (element.type === 'checkbox') {
                            element.checked = settings[key];
                        } else {
                            element.value = settings[key];
                        }
                    }
                });
            } catch (e) {
                console.error('Error loading settings:', e);
            }
        }

        // Save settings
        saveBtn.addEventListener('click', function() {
            const settings = {
                baseFare: document.getElementById('baseFare')?.value,
                ratePerKm: document.getElementById('ratePerKm')?.value,
                surgeMultiplier: document.getElementById('surgeMultiplier')?.value,
                commission: document.getElementById('commission')?.value,
                currency: document.getElementById('currency')?.value,
                currencyCode: document.getElementById('currencyCode')?.value,
                surgePricing: document.getElementById('surgePricing')?.checked,
                driverApproval: document.getElementById('driverApproval')?.checked,
                maintenance: document.getElementById('maintenance')?.checked,
                sessionTimeout: document.getElementById('sessionTimeout')?.value
            };
            
            localStorage.setItem('systemSettings', JSON.stringify(settings));
            alert('Settings saved successfully!');
            logActivity('Updated system settings');
        });
    }

    // ========== WITHDRAWAL FILTER ==========
    function setupWithdrawalFilter() {
        const filter = document.getElementById('withdrawalStatus');
        if (filter) {
            filter.addEventListener('change', function() {
                const status = this.value;
                document.querySelectorAll('#wallets-page tbody tr').forEach(row => {
                    const statusCell = row.querySelector('.status-badge');
                    if (statusCell) {
                        const rowStatus = statusCell.classList[1];
                        row.style.display = (status === 'all' || rowStatus === status) ? '' : 'none';
                    }
                });
            });
        }
    }

    // ========== REPORT SELECTS ==========
    function setupReportSelects() {
        document.querySelectorAll('#reports-page select').forEach(select => {
            select.addEventListener('change', function() {
                alert(`Loading ${this.value} report...`);
            });
        });
    }

    // ========== EXPORT/PRINT BUTTONS ==========
    function setupExportButtons() {
        const exportBtn = document.querySelector('.export-btn');
        if (exportBtn) {
            exportBtn.addEventListener('click', () => {
                alert('Exporting report...');
            });
        }

        const printBtn = document.querySelector('.print-btn');
        if (printBtn) {
            printBtn.addEventListener('click', () => {
                window.print();
            });
        }
    }

    // ========== NOTIFICATION BUTTON ==========
    function setupNotificationButton() {
        const notifBtn = document.querySelector('.notification-btn');
        if (notifBtn) {
            notifBtn.addEventListener('click', () => {
                alert('You have 5 new notifications:\n- 2 new driver applications\n- 1 dispute opened\n- 2 withdrawal requests');
            });
        }
    }

    // ========== SEE ALL BUTTONS ==========
    function setupSeeAllButtons() {
        document.querySelectorAll('.see-all-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                alert('Opening full list...');
            });
        });
    }

    // ========== DATE FILTER ==========
    function setupDateFilter() {
        const filterBtn = document.querySelector('.date-filter .filter-btn');
        if (filterBtn) {
            filterBtn.addEventListener('click', function() {
                const startDate = document.getElementById('startDate')?.value;
                const endDate = document.getElementById('endDate')?.value;
                
                if (startDate && endDate) {
                    alert(`Filtering payments from ${startDate} to ${endDate}`);
                } else {
                    alert('Please select both start and end dates');
                }
            });
        }
    }

    // ========== CHART FILTERS ==========
    function setupChartFilters() {
        document.querySelectorAll('.chart-filters .filter-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const parent = this.parentElement;
                parent.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                alert(`Showing ${this.textContent.toLowerCase()} revenue data`);
            });
        });
    }

    // ========== ACTIVITY LOG ==========
    function logActivity(action) {
        const activityLog = document.querySelector('.activity-log');
        if (!activityLog) return;

        const now = new Date();
        const timeString = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        
        const logEntry = document.createElement('div');
        logEntry.className = 'log-entry';
        logEntry.innerHTML = `
            <span class="log-time">${timeString}</span>
            <span class="log-action">${action}</span>
        `;
        
        activityLog.insertBefore(logEntry, activityLog.firstChild);
        
        // Keep only last 10 entries
        while (activityLog.children.length > 10) {
            activityLog.removeChild(activityLog.lastChild);
        }
    }

    // ========== START ==========
    // Initialize everything when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();