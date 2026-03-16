// Admin Dashboard JavaScript - COMPLETELY UPDATED VERSION WITH FIXED KYC POPUPS
(function() {
    'use strict';

    // ========== GLOBAL STATE ==========
    let loggedIn = localStorage.getItem('adminLoggedIn') === 'true';
    let timeLeft = 1800; // 30 minutes in seconds
    let timerInterval = null;
    let charts = {};
    let currentDisputeId = null;

    // DOM Elements
    const loginModal = document.getElementById('loginModal');
    const timerSpan = document.querySelector('#sessionTimer span');
    const navItems = document.querySelectorAll('.desktop-nav-item');
    const pages = document.querySelectorAll('.page');
    const titleH1 = document.querySelector('#pageTitle h1');
    const subtitleP = document.querySelector('#pageTitle p');
    const logoutBtn = document.getElementById('logoutBtn');

    // ========== INITIALIZATION ==========
    function init() {
        console.log('Admin dashboard initializing...');
        console.log('SweetAlert2 loaded:', typeof Swal !== 'undefined');
        
        checkAuthStatus();
        initializeCharts();
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
            loadDashboardData();
        }
        
        // Check session status with server
        verifySession();
    }

    // ========== SESSION VERIFICATION ==========
    function verifySession() {
        fetch('SERVER/API/check_session.php')
            .then(response => response.json())
            .then(data => {
                if (!data.logged_in) {
                    console.log('Session expired, redirecting to login');
                    logout();
                }
            })
            .catch(error => console.error('Session check failed:', error));
    }

    // ========== AUTHENTICATION ==========
    function checkAuthStatus() {
        // Check if we're on the dashboard and need to verify session
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('logged_in') === 'true') {
            localStorage.setItem('adminLoggedIn', 'true');
            loggedIn = true;
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
                Swal.fire({
                    icon: 'info',
                    title: 'Session Expired',
                    text: 'Your session has expired. Please login again.',
                    confirmButtonColor: '#ff5e00'
                });
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
        if (timerInterval) {
            clearInterval(timerInterval);
            timerInterval = null;
        }
        window.location.href = 'admin_login.php';
    };

    if (logoutBtn) {
        logoutBtn.addEventListener('click', (e) => {
            e.preventDefault();
            confirmLogout();
        });
    }

    // ========== LOAD DASHBOARD DATA ==========
    function loadDashboardData() {
        loadPayments();
        loadWallets();
        loadDisputes();
        loadActivityLogs();
        loadNotifications();
        loadReport();
    }

    // ========== CHARTS INITIALIZATION ==========
    function initializeCharts() {
        const canvas = document.getElementById('revenueChart');
        if (!canvas) {
            console.log('Revenue chart canvas not found - may be on different page');
            return;
        }
        
        const ctx = canvas.getContext('2d');
        
        // Get data from PHP passed through data attributes or global variables
        let labels = [];
        let data = [];
        
        // Try to get from global variables (set by PHP)
        if (typeof revenueLabels !== 'undefined' && typeof revenueData !== 'undefined') {
            labels = revenueLabels;
            data = revenueData;
            console.log('Using revenue data from PHP:', labels, data);
        } 
        // Try to get from data attributes
        else {
            const debugDiv = document.getElementById('debugData');
            if (debugDiv) {
                try {
                    labels = JSON.parse(debugDiv.dataset.labels || '[]');
                    data = JSON.parse(debugDiv.dataset.values || '[]').map(v => parseFloat(v));
                } catch (e) {
                    console.error('Error parsing chart data:', e);
                }
            }
        }
        
        // Check if we have valid data
        if (!labels || !labels.length || !data || !data.length) {
            console.log('No chart data available');
            const fallback = document.getElementById('chartFallback');
            if (fallback) {
                canvas.style.display = 'none';
                fallback.style.display = 'block';
            }
            return;
        }
        
        // Check if all data is zero
        const hasData = data.some(value => value > 0);
        if (!hasData) {
            console.log('All revenue data is zero');
            const fallback = document.getElementById('chartFallback');
            if (fallback) {
                canvas.style.display = 'none';
                fallback.style.display = 'block';
            }
            return;
        }
        
        // Destroy existing chart if it exists
        if (charts.revenue) {
            charts.revenue.destroy();
        }
        
        // Create new chart
        charts.revenue = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Revenue (₦)',
                    data: data,
                    borderColor: '#ff5e00',
                    backgroundColor: 'rgba(255, 94, 0, 0.1)',
                    borderWidth: 3,
                    tension: 0.4,
                    fill: true,
                    pointBackgroundColor: '#ff5e00',
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
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return '₦' + context.parsed.y.toLocaleString();
                            }
                        },
                        backgroundColor: '#333',
                        titleColor: '#fff',
                        bodyColor: '#fff'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: 'rgba(0, 0, 0, 0.05)' },
                        ticks: {
                            callback: function(value) {
                                return '₦' + value.toLocaleString();
                            }
                        }
                    },
                    x: { grid: { display: false } }
                }
            }
        });
        
        console.log('Chart initialized successfully');
    }

    // ========== NAVIGATION ==========
    function setupNavigation() {
        navItems.forEach(item => {
            item.addEventListener('click', function(e) {
                e.preventDefault();
                
                // Update active nav item
                navItems.forEach(n => n.classList.remove('active'));
                this.classList.add('active');
                
                // Show corresponding page
                const pageId = this.dataset.page + '-page';
                pages.forEach(p => p.classList.remove('active-page'));
                const target = document.getElementById(pageId);
                if (target) target.classList.add('active-page');
                
                // Update header
                const pageName = this.querySelector('span').innerText;
                if (titleH1) titleH1.innerText = pageName;
                
                // Update subtitle based on page
                const subtitles = {
                    'Dashboard': 'Welcome back! Here\'s what\'s happening today.',
                    'Users': 'Manage all registered users.',
                    'Drivers': 'Approve and manage driver accounts.',
                    'Rides': 'Monitor and manage all rides.',
                    'Payments': 'View all payment transactions.',
                    'Wallets': 'Manage driver wallets and withdrawals.',
                    'KYC Approvals': 'Review and approve KYC documents.',
                    'Disputes': 'Manage complaints and resolve disputes.',
                    'Reports': 'Analytics and performance reports.',
                    'Settings': 'Configure system settings.',
                    'Activity Log': 'View admin activity history.'
                };
                if (subtitleP) subtitleP.innerText = subtitles[pageName] || `Managing ${pageName.toLowerCase()}`;
                
                // Load data for specific pages
                switch(this.dataset.page) {
                    case 'payments':
                        loadPayments();
                        break;
                    case 'wallets':
                        loadWallets();
                        break;
                    case 'disputes':
                        loadDisputes();
                        break;
                    case 'activity':
                        loadActivityLogs();
                        break;
                    case 'reports':
                        loadReport();
                        break;
                }
            });
        });
    }

    // ========== SEARCH FUNCTIONALITY ==========
    function setupSearch() {
        const userSearch = document.getElementById('userSearch');
        if (userSearch) {
            userSearch.addEventListener('input', function(e) {
                searchTable('usersTableBody', e.target.value);
            });
        }

        const driverSearch = document.getElementById('driverSearch');
        if (driverSearch) {
            driverSearch.addEventListener('input', function(e) {
                searchTable('driversTableBody', e.target.value);
            });
        }
        
        const paymentSearch = document.getElementById('paymentSearch');
        if (paymentSearch) {
            paymentSearch.addEventListener('input', function(e) {
                searchTable('paymentsTableBody', e.target.value);
            });
        }
        
        const walletSearch = document.getElementById('walletSearch');
        if (walletSearch) {
            walletSearch.addEventListener('input', function(e) {
                searchTable('walletsTableBody', e.target.value);
            });
        }
    }

    // Search table function
    window.searchTable = function(tableId, searchTerm) {
        const table = document.getElementById(tableId);
        if (!table) return;
        
        const rows = table.getElementsByTagName('tr');
        searchTerm = searchTerm.toLowerCase();
        
        for (let row of rows) {
            if (row.classList.contains('empty-state')) continue;
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(searchTerm) ? '' : 'none';
        }
    };

    // ========== FILTER TABS ==========
    function setupFilters() {
        document.querySelectorAll('.filter-tab').forEach(tab => {
            tab.addEventListener('click', function() {
                const parent = this.closest('.filter-tabs');
                if (!parent) return;
                
                parent.querySelectorAll('.filter-tab').forEach(t => t.classList.remove('active'));
                this.classList.add('active');
            });
        });
    }

    // Filter users
    window.filterUsers = function(filter) {
        const rows = document.querySelectorAll('#usersTableBody tr');
        rows.forEach(row => {
            if (row.classList.contains('empty-state')) return;
            if (filter === 'all') {
                row.style.display = '';
            } else {
                const status = row.querySelector('.status-badge')?.textContent.toLowerCase() || '';
                row.style.display = status.includes(filter) ? '' : 'none';
            }
        });
    };

    // Filter drivers
    window.filterDrivers = function(filter) {
        const rows = document.querySelectorAll('#driversTableBody tr');
        rows.forEach(row => {
            if (row.classList.contains('empty-state')) return;
            if (filter === 'all') {
                row.style.display = '';
            } else {
                const status = row.dataset.status || '';
                row.style.display = status === filter ? '' : 'none';
            }
        });
    };

    // Filter rides
    window.filterRides = function(filter) {
        const rows = document.querySelectorAll('#ridesTableBody tr');
        rows.forEach(row => {
            if (row.classList.contains('empty-state')) return;
            if (filter === 'all') {
                row.style.display = '';
            } else {
                const status = row.dataset.status || '';
                if (filter === 'ongoing') {
                    const ongoing = ['accepted', 'driver_assigned', 'driver_arrived', 'ongoing'];
                    row.style.display = ongoing.includes(status) ? '' : 'none';
                } else if (filter === 'cancelled') {
                    row.style.display = status.includes('cancelled') ? '' : 'none';
                } else {
                    row.style.display = status === filter ? '' : 'none';
                }
            }
        });
    };

    // Filter KYC
    window.filterKyc = function(filter) {
        const rows = document.querySelectorAll('#kycTableBody tr');
        rows.forEach(row => {
            if (row.classList.contains('empty-state')) return;
            if (filter === 'all') {
                row.style.display = '';
            } else {
                const status = row.dataset.status || '';
                row.style.display = status === filter ? '' : 'none';
            }
        });
    };

    // Filter disputes
    window.filterDisputes = function(filter) {
        const rows = document.querySelectorAll('#disputesTableBody tr');
        rows.forEach(row => {
            if (row.classList.contains('empty-state')) return;
            if (filter === 'all') {
                row.style.display = '';
            } else {
                const status = row.dataset.status || '';
                row.style.display = status === filter ? '' : 'none';
            }
        });
    };

    // ========== MODALS ==========
    function setupModals() {
        // Close modal when clicking outside
        window.addEventListener('click', function(e) {
            if (e.target.classList.contains('modal')) {
                e.target.classList.remove('show');
            }
        });
    }

    // Modal functions
    window.openModal = function(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) modal.classList.add('show');
    };

    window.closeModal = function(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) modal.classList.remove('show');
    };

    // ========== ACTION BUTTONS ==========
    function setupActionButtons() {
        // Note: Most action buttons are handled by onclick attributes directly in HTML
        // This is for any dynamically added buttons
    }

    // ========== USER FUNCTIONS ==========
    window.viewUser = function(userId) {
        fetch(`SERVER/API/get_user_details.php?user_id=${userId}`)
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    document.getElementById('userDetails').innerHTML = formatUserDetails(data.user);
                    openModal('userModal');
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: data.message || 'Failed to load user details'
                    });
                }
            })
            .catch(error => {
                Swal.fire({
                    icon: 'error',
                    title: 'Connection Error',
                    text: 'Failed to load user details'
                });
            });
    };

    window.toggleUserStatus = function(userId) {
        Swal.fire({
            icon: 'question',
            title: 'Toggle User Status',
            text: 'Are you sure you want to suspend/activate this user?',
            showCancelButton: true,
            confirmButtonColor: '#ff5e00',
            confirmButtonText: 'Yes, proceed'
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    icon: 'success',
                    title: 'Success',
                    text: 'User status updated',
                    timer: 1500,
                    showConfirmButton: false
                }).then(() => {
                    location.reload();
                });
            }
        });
    };

    window.deleteUser = function(userId) {
        Swal.fire({
            icon: 'warning',
            title: 'Delete User',
            text: 'Are you sure you want to delete this user? This action cannot be undone!',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            confirmButtonText: 'Yes, delete'
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    icon: 'success',
                    title: 'Deleted!',
                    text: 'User has been deleted',
                    timer: 1500,
                    showConfirmButton: false
                }).then(() => {
                    location.reload();
                });
            }
        });
    };

    // ========== DRIVER FUNCTIONS ==========
    window.viewDriver = function(driverId) {
        fetch(`SERVER/API/get_driver_details.php?driver_id=${driverId}`)
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    document.getElementById('driverDetails').innerHTML = formatDriverDetails(data.driver);
                    openModal('driverModal');
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: data.message || 'Failed to load driver details'
                    });
                }
            })
            .catch(error => {
                Swal.fire({
                    icon: 'error',
                    title: 'Connection Error',
                    text: 'Failed to load driver details'
                });
            });
    };

    window.approveDriver = function(driverId) {
        Swal.fire({
            icon: 'question',
            title: 'Approve Driver',
            text: 'Approve this driver account?',
            showCancelButton: true,
            confirmButtonColor: '#28a745',
            confirmButtonText: 'Yes, approve'
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    icon: 'success',
                    title: 'Approved!',
                    text: 'Driver has been approved',
                    timer: 1500,
                    showConfirmButton: false
                }).then(() => {
                    location.reload();
                });
            }
        });
    };

    window.suspendDriver = function(driverId) {
        Swal.fire({
            icon: 'warning',
            title: 'Suspend Driver',
            text: 'Suspend this driver account?',
            showCancelButton: true,
            confirmButtonColor: '#ffc107',
            confirmButtonText: 'Yes, suspend'
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    icon: 'success',
                    title: 'Suspended!',
                    text: 'Driver has been suspended',
                    timer: 1500,
                    showConfirmButton: false
                }).then(() => {
                    location.reload();
                });
            }
        });
    };

    window.rejectDriver = function(driverId) {
        Swal.fire({
            icon: 'question',
            title: 'Reject Driver',
            text: 'Reject this driver account?',
            input: 'textarea',
            inputPlaceholder: 'Reason for rejection...',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            confirmButtonText: 'Yes, reject'
        }).then((result) => {
            if (result.isConfirmed && result.value) {
                Swal.fire({
                    icon: 'success',
                    title: 'Rejected!',
                    text: 'Driver has been rejected',
                    timer: 1500,
                    showConfirmButton: false
                }).then(() => {
                    location.reload();
                });
            }
        });
    };

    // ========== RIDE FUNCTIONS ==========
    window.viewRide = function(rideId) {
        fetch(`SERVER/API/get_ride_details.php?ride_id=${rideId}`)
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    document.getElementById('rideDetails').innerHTML = formatRideDetails(data.ride);
                    openModal('rideModal');
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: data.message || 'Failed to load ride details'
                    });
                }
            })
            .catch(error => {
                Swal.fire({
                    icon: 'error',
                    title: 'Connection Error',
                    text: 'Failed to load ride details'
                });
            });
    };

    window.cancelRide = function(rideId) {
        Swal.fire({
            icon: 'warning',
            title: 'Cancel Ride',
            text: 'Cancel this ride?',
            input: 'textarea',
            inputPlaceholder: 'Reason for cancellation...',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            confirmButtonText: 'Yes, cancel'
        }).then((result) => {
            if (result.isConfirmed && result.value) {
                Swal.fire({
                    icon: 'success',
                    title: 'Cancelled!',
                    text: 'Ride has been cancelled',
                    timer: 1500,
                    showConfirmButton: false
                }).then(() => {
                    location.reload();
                });
            }
        });
    };

    // ========== KYC FUNCTIONS - FIXED VERSION WITH PROPER POPUPS ==========
    
    /**
     * View KYC document in new tab
     */
    window.viewKYCDocument = function(documentId) {
        // Show loading
        Swal.fire({
            title: 'Loading Document...',
            text: 'Please wait',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
        
        // Open document in new tab
        const docUrl = 'SERVER/API/view_document.php?kyc_id=' + documentId;
        
        // Create a test to see if document exists
        fetch(docUrl, { method: 'HEAD' })
            .then(response => {
                Swal.close();
                if (response.ok) {
                    window.open(docUrl, '_blank');
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Document Not Found',
                        text: 'The KYC document could not be found or accessed.',
                        confirmButtonColor: '#ff5e00'
                    });
                }
            })
            .catch(error => {
                Swal.close();
                console.error('Error loading document:', error);
                // Still try to open it
                window.open(docUrl, '_blank');
            });
    };

    /**
     * Approve KYC document with proper success popup
     */
    window.approveKYC = function(kycId) {
        Swal.fire({
            icon: 'question',
            title: 'Approve KYC Document',
            text: 'Are you sure you want to approve this KYC document?',
            showCancelButton: true,
            confirmButtonColor: '#28a745',
            confirmButtonText: 'Yes, approve',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                // Show loading
                Swal.fire({
                    title: 'Processing...',
                    text: 'Please wait',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
                
                // Send approve request
                fetch('SERVER/API/approve_kyc.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        kyc_id: kycId,
                        action: 'approve'
                    })
                })
                .then(response => {
                    // Check if response is OK
                    if (!response.ok) {
                        throw new Error('Network response was not ok: ' + response.status);
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('Approve KYC response:', data);
                    
                    // Close loading
                    Swal.close();
                    
                    if (data.success) {
                        // Show success message with SweetAlert popup
                        Swal.fire({
                            icon: 'success',
                            title: 'Success!',
                            text: data.message || 'KYC document approved successfully',
                            confirmButtonColor: '#28a745',
                            confirmButtonText: 'OK'
                        }).then(() => {
                            // Reload the page to show updated status
                            location.reload();
                        });
                    } else {
                        // Show error message
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: data.message || 'Failed to approve KYC document',
                            confirmButtonColor: '#dc3545'
                        });
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    Swal.close();
                    Swal.fire({
                        icon: 'error',
                        title: 'Connection Error',
                        text: 'Failed to connect to server. Please try again.\n\n' + error.message,
                        confirmButtonColor: '#dc3545'
                    });
                });
            }
        });
    };

    /**
     * Reject KYC document with proper popup
     */
    window.rejectKYC = function(kycId) {
        Swal.fire({
            icon: 'warning',
            title: 'Reject KYC Document',
            text: 'Please provide a reason for rejection:',
            input: 'textarea',
            inputPlaceholder: 'Enter rejection reason...',
            inputAttributes: {
                'aria-label': 'Rejection reason',
                'required': 'true'
            },
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            confirmButtonText: 'Yes, reject',
            cancelButtonText: 'Cancel',
            inputValidator: (value) => {
                if (!value) {
                    return 'Rejection reason is required';
                }
            }
        }).then((result) => {
            if (result.isConfirmed && result.value) {
                // Show loading
                Swal.fire({
                    title: 'Processing...',
                    text: 'Please wait',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
                
                // Send reject request
                fetch('SERVER/API/approve_kyc.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        kyc_id: kycId,
                        action: 'reject',
                        reason: result.value
                    })
                })
                .then(response => response.json())
                .then(data => {
                    console.log('Reject KYC response:', data);
                    
                    // Close loading
                    Swal.close();
                    
                    if (data.success) {
                        // Show success message
                        Swal.fire({
                            icon: 'success',
                            title: 'Rejected!',
                            text: data.message || 'KYC document rejected',
                            confirmButtonColor: '#28a745',
                            confirmButtonText: 'OK'
                        }).then(() => {
                            location.reload();
                        });
                    } else {
                        // Show error message
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: data.message || 'Failed to reject KYC document',
                            confirmButtonColor: '#dc3545'
                        });
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    Swal.close();
                    Swal.fire({
                        icon: 'error',
                        title: 'Connection Error',
                        text: 'Failed to connect to server. Please try again.',
                        confirmButtonColor: '#dc3545'
                    });
                });
            }
        });
    };

    // ========== PAYMENT FUNCTIONS ==========
    window.loadPayments = function() {
        fetch('SERVER/API/get_payments.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayPayments(data.payments, data.statistics);
                }
            })
            .catch(error => console.error('Error loading payments:', error));
    };

    function displayPayments(payments, stats) {
        // Display statistics
        const statsHtml = `
            <div class="summary-card">
                <h4>Total Transactions</h4>
                <div class="summary-value">${stats?.total_transactions || 0}</div>
            </div>
            <div class="summary-card">
                <h4>Total Revenue</h4>
                <div class="summary-value">₦${(stats?.total_revenue || 0).toLocaleString()}</div>
            </div>
            <div class="summary-card">
                <h4>Pending Amount</h4>
                <div class="summary-value">₦${(stats?.pending_amount || 0).toLocaleString()}</div>
            </div>
            <div class="summary-card">
                <h4>Pending Count</h4>
                <div class="summary-value">${stats?.pending_count || 0}</div>
            </div>
        `;
        const statsEl = document.getElementById('paymentStats');
        if (statsEl) statsEl.innerHTML = statsHtml;

        // Display payments table
        let html = '';
        if (payments && payments.length > 0) {
            payments.forEach(payment => {
                html += `
                    <tr data-status="${payment.status}">
                        <td>${payment.reference || 'N/A'}</td>
                        <td>${payment.user_name || 'Unknown'}<br><small>${payment.user_email || ''}</small></td>
                        <td>₦${(payment.amount || 0).toLocaleString()}</td>
                        <td>${payment.payment_method || 'N/A'}</td>
                        <td>${payment.ride_number || 'N/A'}</td>
                        <td><span class="status-badge ${payment.status}">${payment.status || 'unknown'}</span></td>
                        <td>${payment.formatted_date || ''}</td>
                        <td class="actions-cell">
                            <button class="action-icon-btn" onclick="viewPayment('${payment.id}')"><i class="fas fa-eye"></i></button>
                        </td>
                    </tr>
                `;
            });
        } else {
            html = '<tr><td colspan="8" class="empty-state">No payments found</td></tr>';
        }
        
        const tbody = document.getElementById('paymentsTableBody');
        if (tbody) tbody.innerHTML = html;
    }

    window.viewPayment = function(paymentId) {
        Swal.fire({
            icon: 'info',
            title: 'Payment Details',
            text: 'Payment ID: ' + paymentId
        });
    };

    // ========== WALLET FUNCTIONS ==========
    window.loadWallets = function() {
        fetch('SERVER/API/get_wallets.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayWallets(data);
                }
            })
            .catch(error => console.error('Error loading wallets:', error));
    };

    function displayWallets(data) {
        // Display statistics
        const statsHtml = `
            <div class="summary-card">
                <h4>Active Wallets</h4>
                <div class="summary-value">${data.statistics?.active_wallets || 0}</div>
            </div>
            <div class="summary-card">
                <h4>Total Deposits</h4>
                <div class="summary-value">₦${(data.statistics?.total_deposits || 0).toLocaleString()}</div>
            </div>
            <div class="summary-card">
                <h4>Total Withdrawals</h4>
                <div class="summary-value">₦${(data.statistics?.total_withdrawals || 0).toLocaleString()}</div>
            </div>
            <div class="summary-card">
                <h4>Net Balance</h4>
                <div class="summary-value">₦${(data.statistics?.net_balance || 0).toLocaleString()}</div>
            </div>
        `;
        const statsEl = document.getElementById('walletStats');
        if (statsEl) statsEl.innerHTML = statsHtml;

        // Display pending withdrawals
        let withdrawalsHtml = '';
        if (data.pending_withdrawals && data.pending_withdrawals.length > 0) {
            data.pending_withdrawals.forEach(w => {
                withdrawalsHtml += `
                    <tr data-status="${w.status}">
                        <td>${w.driver_name || 'Unknown'}<br><small>${w.driver_email || ''}</small></td>
                        <td>₦${(w.amount || 0).toLocaleString()}</td>
                        <td>${w.bank_name || 'N/A'}</td>
                        <td>****${w.account_number || ''}</td>
                        <td>${w.formatted_date || ''}</td>
                        <td><span class="status-badge ${w.status}">${w.status || 'pending'}</span></td>
                        <td class="actions-cell">
                            <button class="action-icon-btn approve-btn" onclick="processWithdrawal('${w.id}', 'approve')"><i class="fas fa-check"></i></button>
                            <button class="action-icon-btn reject-btn" onclick="processWithdrawal('${w.id}', 'reject')"><i class="fas fa-times"></i></button>
                        </td>
                    </tr>
                `;
            });
        } else {
            withdrawalsHtml = '<tr><td colspan="7" class="empty-state">No pending withdrawals</td></tr>';
        }
        
        const withdrawalsBody = document.getElementById('withdrawalsTableBody');
        if (withdrawalsBody) withdrawalsBody.innerHTML = withdrawalsHtml;

        // Display recent transactions
        let transactionsHtml = '';
        if (data.recent_transactions && data.recent_transactions.length > 0) {
            data.recent_transactions.forEach(t => {
                transactionsHtml += `
                    <tr>
                        <td>${t.user_name || 'Unknown'}<br><small>${t.user_role || ''}</small></td>
                        <td><span class="status-badge ${t.type}">${(t.type || '').replace('_', ' ')}</span></td>
                        <td>₦${(t.amount || 0).toLocaleString()}</td>
                        <td>${t.reference || 'N/A'}</td>
                        <td>${t.description || 'N/A'}</td>
                        <td>${t.formatted_date || ''}</td>
                    </tr>
                `;
            });
        } else {
            transactionsHtml = '<tr><td colspan="6" class="empty-state">No transactions found</td></tr>';
        }
        
        const transactionsBody = document.getElementById('transactionsTableBody');
        if (transactionsBody) transactionsBody.innerHTML = transactionsHtml;
    }

    window.filterWithdrawals = function(status) {
        const rows = document.querySelectorAll('#withdrawalsTableBody tr');
        rows.forEach(row => {
            if (row.classList.contains('empty-state')) return;
            if (status === 'all') {
                row.style.display = '';
            } else {
                row.style.display = row.dataset.status === status ? '' : 'none';
            }
        });
    };

    window.processWithdrawal = function(withdrawalId, action) {
        Swal.fire({
            icon: 'question',
            title: action === 'approve' ? 'Approve Withdrawal' : 'Reject Withdrawal',
            text: action === 'approve' ? 'Process this withdrawal?' : 'Reject this withdrawal?',
            input: action === 'reject' ? 'textarea' : null,
            inputPlaceholder: 'Reason for rejection...',
            showCancelButton: true,
            confirmButtonColor: action === 'approve' ? '#28a745' : '#dc3545',
            confirmButtonText: action === 'approve' ? 'Yes, approve' : 'Yes, reject'
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    icon: 'success',
                    title: 'Success',
                    text: `Withdrawal ${action}d successfully`,
                    timer: 1500,
                    showConfirmButton: false
                }).then(() => {
                    loadWallets();
                });
            }
        });
    };

    // ========== DISPUTE FUNCTIONS ==========
    window.loadDisputes = function() {
        fetch('SERVER/API/get_disputes.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayDisputes(data.disputes, data.statistics);
                }
            })
            .catch(error => console.error('Error loading disputes:', error));
    };

    function displayDisputes(disputes, stats) {
        // Display statistics
        const statsHtml = `
            <div class="stat-card">
                <div class="stat-icon pending-icon"><i class="fas fa-exclamation-triangle"></i></div>
                <div class="stat-details">
                    <h3>Total Disputes</h3>
                    <div class="stat-value">${stats?.total || 0}</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-clock"></i></div>
                <div class="stat-details">
                    <h3>Open</h3>
                    <div class="stat-value">${stats?.open || 0}</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-search"></i></div>
                <div class="stat-details">
                    <h3>Investigating</h3>
                    <div class="stat-value">${stats?.investigating || 0}</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                <div class="stat-details">
                    <h3>Resolved</h3>
                    <div class="stat-value">${stats?.resolved || 0}</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon urgent-icon"><i class="fas fa-exclamation"></i></div>
                <div class="stat-details">
                    <h3>Urgent</h3>
                    <div class="stat-value">${stats?.urgent || 0}</div>
                </div>
            </div>
        `;
        const statsEl = document.getElementById('disputeStats');
        if (statsEl) statsEl.innerHTML = statsHtml;

        // Display disputes table
        let html = '';
        if (disputes && disputes.length > 0) {
            disputes.forEach(dispute => {
                const priorityClass = `badge-${dispute.priority || 'medium'}`;
                html += `
                    <tr data-status="${dispute.status || 'open'}" data-dispute-id="${dispute.id}">
                        <td>${dispute.dispute_number || 'N/A'}</td>
                        <td>${dispute.ride_number || 'N/A'}</td>
                        <td>${dispute.raised_by?.name || 'N/A'}<br><small>${dispute.raised_by?.email || ''}</small></td>
                        <td>${dispute.raised_against?.name || 'N/A'}<br><small>${dispute.raised_against?.email || ''}</small></td>
                        <td>${dispute.type_display || dispute.type || 'N/A'}</td>
                        <td><span class="badge ${priorityClass}">${dispute.priority || 'medium'}</span></td>
                        <td><span class="status-badge ${dispute.status || 'open'}">${dispute.status || 'open'}</span></td>
                        <td><span class="message-count">${dispute.message_count || 0}</span></td>
                        <td class="actions-cell">
                            <button class="action-icon-btn" onclick="viewDispute('${dispute.id}')"><i class="fas fa-eye"></i></button>
                            <button class="action-icon-btn" onclick="addDisputeMessage('${dispute.id}')"><i class="fas fa-reply"></i></button>
                            ${dispute.status !== 'resolved' ? 
                                `<button class="action-icon-btn resolve-btn" onclick="resolveDispute('${dispute.id}')"><i class="fas fa-check"></i></button>` : 
                                ''}
                        </td>
                    </tr>
                `;
            });
        } else {
            html = '<tr><td colspan="9" class="empty-state">No disputes found</td></tr>';
        }
        
        const tbody = document.getElementById('disputesTableBody');
        if (tbody) tbody.innerHTML = html;
    }

    window.viewDispute = function(disputeId) {
        currentDisputeId = disputeId;
        Swal.fire({
            icon: 'info',
            title: 'Dispute Details',
            text: 'Viewing dispute: ' + disputeId
        });
    };

    window.addDisputeMessage = function(disputeId) {
        currentDisputeId = disputeId;
        Swal.fire({
            icon: 'question',
            title: 'Add Message',
            input: 'textarea',
            inputPlaceholder: 'Type your message...',
            showCancelButton: true,
            confirmButtonColor: '#ff5e00',
            confirmButtonText: 'Send'
        }).then((result) => {
            if (result.isConfirmed && result.value) {
                Swal.fire({
                    icon: 'success',
                    title: 'Sent!',
                    text: 'Message added successfully',
                    timer: 1500,
                    showConfirmButton: false
                });
            }
        });
    };

    window.resolveDispute = function(disputeId) {
        Swal.fire({
            icon: 'question',
            title: 'Resolve Dispute',
            text: 'Mark this dispute as resolved?',
            input: 'textarea',
            inputPlaceholder: 'Resolution details...',
            showCancelButton: true,
            confirmButtonColor: '#28a745',
            confirmButtonText: 'Yes, resolve'
        }).then((result) => {
            if (result.isConfirmed && result.value) {
                Swal.fire({
                    icon: 'success',
                    title: 'Resolved!',
                    text: 'Dispute has been resolved',
                    timer: 1500,
                    showConfirmButton: false
                }).then(() => {
                    loadDisputes();
                });
            }
        });
    };

    // ========== ACTIVITY LOG FUNCTIONS ==========
    window.loadActivityLogs = function() {
        fetch('SERVER/API/get_activity_logs.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayActivityLogs(data.logs, data.statistics);
                }
            })
            .catch(error => console.error('Error loading activity logs:', error));
    };

    function displayActivityLogs(logs, stats) {
        // Display statistics
        const statsHtml = `
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-history"></i></div>
                <div class="stat-details">
                    <h3>Total Logs</h3>
                    <div class="stat-value">${stats?.total_logs || 0}</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-users-cog"></i></div>
                <div class="stat-details">
                    <h3>Active Admins</h3>
                    <div class="stat-value">${stats?.active_admins || 0}</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-calendar-day"></i></div>
                <div class="stat-details">
                    <h3>Today</h3>
                    <div class="stat-value">${stats?.today_activities || 0}</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-clock"></i></div>
                <div class="stat-details">
                    <h3>Last Activity</h3>
                    <div class="stat-value">${stats?.last_activity ? new Date(stats.last_activity).toLocaleDateString() : 'N/A'}</div>
                </div>
            </div>
        `;
        const statsEl = document.getElementById('activityStats');
        if (statsEl) statsEl.innerHTML = statsHtml;
    }

    window.filterActivityLogs = function(searchTerm) {
        const rows = document.querySelectorAll('#activityTableBody tr');
        searchTerm = searchTerm.toLowerCase();
        
        rows.forEach(row => {
            if (row.classList.contains('empty-state')) return;
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(searchTerm) ? '' : 'none';
        });
    };

    window.viewChanges = function(logId) {
        Swal.fire({
            icon: 'info',
            title: 'Changes',
            text: 'Viewing changes for log: ' + logId
        });
    };

    // ========== REPORT FUNCTIONS ==========
    window.loadReport = function() {
        const period = document.getElementById('reportPeriod')?.value || 'daily';
        Swal.fire({
            icon: 'info',
            title: 'Loading Report',
            text: `Loading ${period} report...`,
            timer: 1500,
            showConfirmButton: false
        });
    };

    window.exportReport = function() {
        Swal.fire({
            icon: 'success',
            title: 'Export Started',
            text: 'Your report is being generated',
            timer: 2000,
            showConfirmButton: false
        });
    };

    // ========== SETTINGS ==========
    function setupSettings() {
        const saveBtn = document.getElementById('saveSettings');
        if (!saveBtn) return;
        
        saveBtn.addEventListener('click', saveSettings);
    }

    window.saveSettings = function() {
        Swal.fire({
            icon: 'success',
            title: 'Success',
            text: 'Settings saved successfully!',
            timer: 1500,
            showConfirmButton: false
        });
    };

    // ========== WITHDRAWAL FILTER ==========
    function setupWithdrawalFilter() {
        const filter = document.getElementById('withdrawalStatus');
        if (filter) {
            filter.addEventListener('change', function() {
                filterWithdrawals(this.value);
            });
        }
    }

    // ========== REPORT SELECTS ==========
    function setupReportSelects() {
        const reportSelect = document.getElementById('reportPeriod');
        if (reportSelect) {
            reportSelect.addEventListener('change', loadReport);
        }
    }

    // ========== EXPORT/PRINT BUTTONS ==========
    function setupExportButtons() {
        const exportBtn = document.querySelector('.export-btn');
        if (exportBtn) {
            exportBtn.addEventListener('click', exportReport);
        }
    }

    // ========== NOTIFICATION BUTTON ==========
    function setupNotificationButton() {
        const notifBtn = document.querySelector('.notification-btn');
        if (notifBtn) {
            notifBtn.addEventListener('click', () => {
                const badge = document.getElementById('notificationBadge');
                const count = badge ? badge.textContent : '0';
                
                Swal.fire({
                    icon: 'info',
                    title: 'Notifications',
                    html: `
                        <div style="text-align: left;">
                            <p>🔔 You have ${count} new notifications</p>
                            <hr>
                            <p>• Pending KYC: ${document.querySelectorAll('#kycTableBody tr').length}</p>
                            <p>• Pending withdrawals: ${document.querySelectorAll('#withdrawalsTableBody tr').length}</p>
                        </div>
                    `,
                    confirmButtonColor: '#ff5e00'
                });
            });
        }
    }

    // ========== SEE ALL BUTTONS ==========
    function setupSeeAllButtons() {
        document.querySelectorAll('.see-all-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const page = this.closest('.desktop-card')?.querySelector('h2')?.innerText;
                if (page === 'Recent Withdrawals') {
                    document.querySelector('[data-page="wallets"]').click();
                }
            });
        });
    }

    // ========== DATE FILTER ==========
    function setupDateFilter() {
        // Not implemented in current UI
    }

    // ========== CHART FILTERS ==========
    function setupChartFilters() {
        document.querySelectorAll('.chart-filters .filter-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const parent = this.parentElement;
                parent.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                filterChart(this.textContent.toLowerCase());
            });
        });
    }

    window.filterChart = function(period) {
        Swal.fire({
            icon: 'info',
            title: 'Loading...',
            text: `Loading ${period} revenue data`,
            timer: 1500,
            showConfirmButton: false
        });
    };

    window.showAllWithdrawals = function() {
        const walletsLink = document.querySelector('[data-page="wallets"]');
        if (walletsLink) walletsLink.click();
    };

    window.confirmLogout = function() {
        Swal.fire({
            icon: 'question',
            title: 'Logout',
            text: 'Are you sure you want to logout?',
            showCancelButton: true,
            confirmButtonColor: '#ff5e00',
            confirmButtonText: 'Yes, logout'
        }).then((result) => {
            if (result.isConfirmed) {
                logout();
            }
        });
    };

    // ========== NOTIFICATIONS ==========
    function loadNotifications() {
        // Simulated for now
        const badge = document.getElementById('notificationBadge');
        if (badge) {
            const kycCount = document.querySelectorAll('#kycTableBody tr').length;
            const withdrawalCount = document.querySelectorAll('#withdrawalsTableBody tr').length;
            badge.textContent = kycCount + withdrawalCount;
        }
    }

    // ========== FORMAT FUNCTIONS ==========
    function formatUserDetails(user) {
        return `
            <p><strong>ID:</strong> ${user.id || 'N/A'}</p>
            <p><strong>Name:</strong> ${user.full_name || 'N/A'}</p>
            <p><strong>Email:</strong> ${user.email || 'N/A'}</p>
            <p><strong>Phone:</strong> ${user.phone_number || 'N/A'}</p>
            <p><strong>Role:</strong> ${user.role || 'N/A'}</p>
            <p><strong>Joined:</strong> ${user.created_at ? new Date(user.created_at).toLocaleDateString() : 'N/A'}</p>
            <p><strong>Status:</strong> ${user.is_active ? 'Active' : 'Inactive'}</p>
            <p><strong>Verified:</strong> ${user.is_verified ? 'Yes' : 'No'}</p>
        `;
    }

    function formatDriverDetails(driver) {
        return `
            <p><strong>Name:</strong> ${driver.full_name || 'N/A'}</p>
            <p><strong>Email:</strong> ${driver.email || 'N/A'}</p>
            <p><strong>Phone:</strong> ${driver.phone_number || 'N/A'}</p>
            <p><strong>License:</strong> ${driver.license_number || 'N/A'}</p>
            <p><strong>License Expiry:</strong> ${driver.license_expiry ? new Date(driver.license_expiry).toLocaleDateString() : 'N/A'}</p>
            <p><strong>Status:</strong> ${driver.driver_status || 'N/A'}</p>
            <p><strong>KYC:</strong> ${driver.verification_status || 'N/A'}</p>
            <p><strong>Completed Rides:</strong> ${driver.completed_rides || 0}</p>
            <p><strong>Rating:</strong> ${driver.average_rating ? driver.average_rating.toFixed(1) : 'N/A'}</p>
            <p><strong>Earnings:</strong> ₦${(driver.total_earnings || 0).toLocaleString()}</p>
        `;
    }

    function formatRideDetails(ride) {
        return `
            <p><strong>Ride #:</strong> ${ride.ride_number || 'N/A'}</p>
            <p><strong>Client:</strong> ${ride.client_name || 'N/A'}</p>
            <p><strong>Driver:</strong> ${ride.driver_name || 'Unassigned'}</p>
            <p><strong>Pickup:</strong> ${ride.pickup_address || 'N/A'}</p>
            <p><strong>Destination:</strong> ${ride.destination_address || 'N/A'}</p>
            <p><strong>Distance:</strong> ${ride.distance_km ? ride.distance_km + ' km' : 'N/A'}</p>
            <p><strong>Fare:</strong> ₦${(ride.total_fare || 0).toLocaleString()}</p>
            <p><strong>Status:</strong> ${ride.status || 'N/A'}</p>
            <p><strong>Payment:</strong> ${ride.payment_status || 'N/A'}</p>
            <p><strong>Created:</strong> ${ride.created_at ? new Date(ride.created_at).toLocaleString() : 'N/A'}</p>
        `;
    }

    // ========== START ==========
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();