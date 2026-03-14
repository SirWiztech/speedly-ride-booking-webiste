document.addEventListener('DOMContentLoaded', function () {
    // ----- withdraw buttons (both mobile & desktop) -----
    const withdrawBtns = document.querySelectorAll('.withdraw-btn');
    withdrawBtns.forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            alert('💸 Withdrawal request initiated. Your earnings will be sent to your bank (1-2 business days).');
        });
    });

    // desktop withdraw from balance card
    const balanceWithdraw = document.querySelector('.balance-card button');
    if (balanceWithdraw) {
        balanceWithdraw.addEventListener('click', function(e) {
            e.preventDefault();
            alert('💸 Withdrawal request initiated. Your earnings will be sent to your bank (1-2 business days).');
        });
    }

    // notification bell
    const notifBtn = document.querySelector('.notification-btn');
    if (notifBtn) {
        notifBtn.addEventListener('click', function(e) {
            e.preventDefault();
            alert('🔔 2 new ride requests · 1 message');
        });
    }

    // see all buttons
    document.querySelectorAll('.see-all-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            alert('📋 Opening full ride history / earnings breakdown');
        });
    });

    // responsive switcher
    function checkScreenSize() {
        const mobileView = document.querySelector('.mobile-view');
        const desktopView = document.querySelector('.desktop-view');
        if (window.innerWidth >= 1024) {
            if (mobileView) mobileView.style.display = 'none';
            if (desktopView) desktopView.style.display = 'flex';
        } else {
            if (mobileView) mobileView.style.display = 'block';
            if (desktopView) desktopView.style.display = 'none';
        }
    }
    checkScreenSize();
    window.addEventListener('resize', checkScreenSize);
});