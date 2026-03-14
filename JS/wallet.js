// Wait for DOM to be fully loaded
document.addEventListener('DOMContentLoaded', function () {
    
    // ===== PAYMENT METHOD SELECTION (Desktop) =====
    const paymentItems = document.querySelectorAll('.payment-method-item');
    paymentItems.forEach(item => {
        item.addEventListener('click', function (e) {
            // Don't trigger if clicking on action menu
            if (e.target.closest('.payment-method-action')) return;
            
            // Remove selected class from all items
            paymentItems.forEach(i => i.classList.remove('selected'));
            // Add selected class to clicked item
            this.classList.add('selected');

            const method = this.querySelector('h4')?.textContent || 'Payment method';
            console.log(`Selected payment method: ${method}`);
            // Removed alert that was blocking navigation
        });
    });

    // ===== PAYMENT METHOD ACTION MENU (Desktop) =====
    const paymentActions = document.querySelectorAll('.payment-method-action i');
    paymentActions.forEach(action => {
        action.addEventListener('click', function (e) {
            e.stopPropagation(); // Prevent triggering the parent click
            e.preventDefault();
            
            const methodItem = this.closest('.payment-method-item');
            const methodName = methodItem?.querySelector('h4')?.textContent || 'Payment method';
            
            // Show options menu
            showPaymentOptions(methodName);
        });
    });

    // ===== MOBILE PAYMENT METHOD SELECTION =====
    const mobilePaymentItems = document.querySelectorAll('.mobile-payment-item');
    mobilePaymentItems.forEach(item => {
        item.addEventListener('click', function (e) {
            // Don't trigger if clicking on action menu
            if (e.target.closest('.payment-action')) return;
            
            // Remove selected class from all items
            mobilePaymentItems.forEach(payment => {
                payment.classList.remove('selected');
            });

            // Add selected class to clicked item
            this.classList.add('selected');

            // Get the selected payment method
            const methodName = this.querySelector('h4')?.textContent || 'Payment method';
            console.log(`Selected payment method: ${methodName}`);
            // Removed alert that was blocking navigation
        });
    });

    // ===== MOBILE PAYMENT ACTION MENU =====
    const mobilePaymentActions = document.querySelectorAll('.payment-action');
    mobilePaymentActions.forEach(action => {
        action.addEventListener('click', function (e) {
            e.stopPropagation(); // Prevent triggering the parent click
            e.preventDefault();

            const paymentItem = this.closest('.mobile-payment-item');
            const methodName = paymentItem?.querySelector('h4')?.textContent || 'Payment method';
            
            // Show custom context menu
            showMobileContextMenu(this, paymentItem, methodName, mobilePaymentItems);
        });
    });

    // ===== ADD MONEY BUTTON =====
    const addMoneyBtns = document.querySelectorAll('.add-money-btn');
    addMoneyBtns.forEach(btn => {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            // This will be handled by the function in wallet.js
            if (typeof addFunds === 'function') {
                addFunds();
            } else {
                console.log('Add money feature');
                window.location.href = 'wallet.php?action=add';
            }
        });
    });

    // ===== BOOK RIDE BUTTONS =====
    const rideBtns = document.querySelectorAll('.ride-booking-btn, .book-ride-btn-desktop, .mobile-book-ride-btn');
    rideBtns.forEach(btn => {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            // Navigate to book ride page without alert
            window.location.href = 'book-ride.php';
        });
    });

    // ===== QUICK ACTION BUTTONS =====
    const actionBtns = document.querySelectorAll('.action-btn, .desktop-action-btn, .mobile-action-btn');
    actionBtns.forEach(btn => {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            const actionName = this.querySelector('span')?.textContent || 'Action';
            
            // Handle specific actions
            if (actionName.includes('Add to Wallet') || actionName.includes('Add Money')) {
                if (typeof addFunds === 'function') {
                    addFunds();
                }
            } else if (actionName.includes('Withdraw')) {
                const balance = document.querySelector('.balance-amount')?.textContent?.replace(/[^0-9.]/g, '') || '0';
                if (typeof withdrawFunds === 'function') {
                    withdrawFunds(parseFloat(balance));
                }
            } else if (actionName.includes('History')) {
                window.location.href = 'ride_history.php';
            } else if (actionName.includes('Methods')) {
                // Scroll to payment methods section
                document.querySelector('.payment-methods-card, .mobile-payment-methods')?.scrollIntoView({ behavior: 'smooth' });
            } else {
                console.log(`Action: ${actionName}`);
                // For other actions, you can add specific handlers
            }
        });
    });

    // ===== SEE ALL BUTTONS =====
    const seeAllBtns = document.querySelectorAll('.see-all-btn');
    seeAllBtns.forEach(btn => {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            // Navigate to ride history without alert
            window.location.href = 'ride_history.php';
        });
    });

    // ===== NOTIFICATION BUTTON =====
    const notificationBtn = document.querySelector('.notification-btn');
    if (notificationBtn) {
        notificationBtn.addEventListener('click', function (e) {
            e.preventDefault();
            if (typeof checkNotifications === 'function') {
                checkNotifications();
            } else {
                console.log('Notifications clicked');
            }
        });
    }

    // ===== TRANSACTION ITEMS =====
    const transactionItems = document.querySelectorAll('.transaction-item');
    transactionItems.forEach(item => {
        item.addEventListener('click', function (e) {
            e.preventDefault();
            const transactionId = this.dataset.id || 'unknown';
            // Navigate to transaction details or show modal
            if (typeof viewTransaction === 'function') {
                viewTransaction(transactionId);
            } else {
                window.location.href = `ride_history.php?transaction=${transactionId}`;
            }
        });
    });

    // ===== RESPONSIVE VIEW SWITCHER =====
    function checkScreenSize() {
        const mobileView = document.querySelector('.mobile-view');
        const desktopView = document.querySelector('.desktop-view');

        if (window.innerWidth >= 1024) {
            // Desktop view
            if (mobileView) mobileView.style.display = 'none';
            if (desktopView) desktopView.style.display = 'flex';
        } else {
            // Mobile view
            if (mobileView) mobileView.style.display = 'block';
            if (desktopView) desktopView.style.display = 'none';
        }
    }

    // Check on load and resize
    checkScreenSize();
    window.addEventListener('resize', checkScreenSize);

    // ===== UPDATE RADIO DOTS =====
    function updateRadioDots() {
        mobilePaymentItems.forEach(item => {
            const radioDot = item.querySelector('.radio-dot');
            if (radioDot) {
                radioDot.style.display = item.classList.contains('selected') ? 'block' : 'none';
            }
        });
    }
    
    // Initialize radio dots
    updateRadioDots();

    // Add CSS for radio dots if not present
    if (!document.querySelector('#payment-styles')) {
        const style = document.createElement('style');
        style.id = 'payment-styles';
        style.textContent = `
            .mobile-payment-item.selected .payment-radio {
                border-color: #ff5e00 !important;
                background-color: #ff5e00 !important;
            }
            
            .mobile-payment-item.selected .radio-dot {
                background-color: white !important;
                width: 8px !important;
                height: 8px !important;
                border-radius: 50% !important;
                display: block !important;
            }
            
            .payment-radio {
                width: 20px;
                height: 20px;
                border-radius: 50%;
                border: 2px solid #ddd;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            
            .radio-dot {
                display: none;
            }
        `;
        document.head.appendChild(style);
    }
});

// ===== HELPER FUNCTIONS (defined outside DOMContentLoaded) =====

// Show payment options menu
function showPaymentOptions(methodName) {
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            title: `Options for ${methodName}`,
            showDenyButton: true,
            showCancelButton: true,
            confirmButtonText: 'Edit',
            denyButtonText: 'Set as Default',
            cancelButtonText: 'Remove',
            confirmButtonColor: '#ff5e00',
            denyButtonColor: '#4CAF50',
            cancelButtonColor: '#F44336'
        }).then((result) => {
            if (result.isConfirmed) {
                console.log(`Edit ${methodName}`);
            } else if (result.isDenied) {
                console.log(`${methodName} set as default`);
            } else if (result.dismiss === Swal.DismissReason.cancel) {
                console.log(`Remove ${methodName}`);
            }
        });
    } else {
        console.log(`Options for ${methodName}: Edit, Remove, Set as Default`);
    }
}

// Show mobile context menu
function showMobileContextMenu(triggerElement, paymentItem, methodName, allItems) {
    // Create a simple menu
    const menu = document.createElement('div');
    menu.className = 'payment-context-menu';
    menu.innerHTML = `
        <div class="menu-item" data-action="edit">Edit</div>
        <div class="menu-item" data-action="remove">Remove</div>
        <div class="menu-item" data-action="default">Set as Default</div>
    `;

    // Position the menu
    const rect = triggerElement.getBoundingClientRect();
    menu.style.position = 'fixed';
    menu.style.top = (rect.bottom + 5) + 'px';
    menu.style.right = (window.innerWidth - rect.right) + 'px';
    menu.style.zIndex = '1000';
    menu.style.backgroundColor = 'white';
    menu.style.borderRadius = '8px';
    menu.style.boxShadow = '0 4px 12px rgba(0,0,0,0.15)';
    menu.style.padding = '8px 0';
    menu.style.minWidth = '150px';

    // Add styles to menu items
    const menuItems = menu.querySelectorAll('.menu-item');
    menuItems.forEach(menuItem => {
        menuItem.style.padding = '10px 16px';
        menuItem.style.cursor = 'pointer';
        menuItem.style.fontSize = '14px';
        menuItem.style.color = '#333';
        menuItem.style.transition = 'background-color 0.2s';

        menuItem.addEventListener('mouseenter', function () {
            this.style.backgroundColor = '#f5f5f5';
        });

        menuItem.addEventListener('mouseleave', function () {
            this.style.backgroundColor = 'transparent';
        });

        menuItem.addEventListener('click', function (e) {
            e.stopPropagation();
            const action = this.getAttribute('data-action');

            switch (action) {
                case 'edit':
                    console.log(`Editing ${methodName}`);
                    break;
                case 'remove':
                    if (confirm(`Are you sure you want to remove ${methodName}?`)) {
                        paymentItem.remove();
                    }
                    break;
                case 'default':
                    // Remove selected from all
                    allItems.forEach(payment => {
                        payment.classList.remove('selected');
                    });
                    // Add selected to this item
                    paymentItem.classList.add('selected');
                    console.log(`${methodName} set as default`);
                    break;
            }

            // Remove menu
            if (document.querySelector('.payment-context-menu')) {
                document.querySelector('.payment-context-menu').remove();
            }
        });
    });

    // Remove existing menu if any
    const existingMenu = document.querySelector('.payment-context-menu');
    if (existingMenu) {
        existingMenu.remove();
    }

    // Add menu to body
    document.body.appendChild(menu);

    // Close menu when clicking elsewhere
    const closeMenu = function (e) {
        if (!menu.contains(e.target) && e.target !== triggerElement) {
            if (document.querySelector('.payment-context-menu')) {
                document.querySelector('.payment-context-menu').remove();
            }
            document.removeEventListener('click', closeMenu);
        }
    };

    // Add event listener with a slight delay
    setTimeout(() => {
        document.addEventListener('click', closeMenu);
    }, 10);
}

// Add funds function (can be called from buttons)
function addFunds() {
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            title: 'Add Funds to Wallet',
            input: 'number',
            inputLabel: 'Amount (₦)',
            inputPlaceholder: 'Enter amount',
            showCancelButton: true,
            confirmButtonText: 'Add',
            confirmButtonColor: '#ff5e00',
            inputValidator: (value) => {
                if (!value || value < 100) {
                    return 'Please enter a valid amount (minimum ₦100)';
                }
            }
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    icon: 'success',
                    title: 'Funds Added!',
                    text: `₦${result.value} has been added to your wallet`,
                    timer: 2000,
                    showConfirmButton: false
                });
            }
        });
    } else {
        alert('Add funds feature');
    }
}

// Withdraw funds function
function withdrawFunds(balance) {
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            title: 'Withdraw Funds',
            input: 'number',
            inputLabel: `Amount (₦) - Available: ₦${balance.toLocaleString()}`,
            inputPlaceholder: 'Enter amount',
            showCancelButton: true,
            confirmButtonText: 'Withdraw',
            confirmButtonColor: '#ff5e00',
            inputValidator: (value) => {
                if (!value || value < 100) {
                    return 'Minimum withdrawal is ₦100';
                }
                if (value > balance) {
                    return 'Insufficient balance';
                }
            }
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    icon: 'success',
                    title: 'Withdrawal Initiated',
                    text: `₦${result.value} will be sent to your account`,
                    timer: 2000,
                    showConfirmButton: false
                });
            }
        });
    } else {
        alert('Withdraw funds feature');
    }
}

// Check notifications
function checkNotifications() {
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            icon: 'info',
            title: 'Notifications',
            html: `
                <div style="text-align: left;">
                    <p>💰 <strong>Balance Update:</strong> Your wallet balance is updated</p>
                    <p>🏆 <strong>Reward:</strong> You've earned a ride reward!</p>
                    <p>📢 <strong>Promo:</strong> 20% off your next ride</p>
                </div>
            `,
            confirmButtonColor: '#ff5e00'
        });
    } else {
        alert('You have 3 new notifications');
    }
}

// View transaction
function viewTransaction(transactionId) {
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            icon: 'info',
            title: 'Transaction Details',
            text: `Transaction ID: ${transactionId}`,
            confirmButtonColor: '#ff5e00'
        });
    } else {
        alert(`Transaction details: ${transactionId}`);
    }
}