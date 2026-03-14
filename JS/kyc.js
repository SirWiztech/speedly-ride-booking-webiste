
        document.addEventListener('DOMContentLoaded', function () {
            // ----- UPLOAD simulation (mobile & desktop) -----
            const uploadElements = document.querySelectorAll('#uploadLicenseMobile, #uploadSelfieMobile, #desktopUploadLicense, #desktopUploadLicenseBack, #desktopUploadSelfie, .upload-box, .upload-area-desktop');
            uploadElements.forEach(el => {
                if (el.classList?.contains('upload-box') || el.classList?.contains('upload-area-desktop') || el.id.includes('upload')) {
                    el.addEventListener('click', function (e) {
                        e.preventDefault();
                        let docType = 'document';
                        if (this.id.includes('Selfie')) docType = 'selfie';
                        else if (this.id.includes('License')) docType = 'license';
                        else if (this.textContent.includes('selfie')) docType = 'selfie with ID';
                        else if (this.textContent.includes('back')) docType = 'license back';
                        alert(`📎 Simulated upload: ${docType} (Speedly driver KYC)`);
                        // Visual feedback (if selfie, change warning)
                        if (docType.includes('selfie')) {
                            const warningDiv = this.closest('.desktop-card')?.querySelector('.bg-orange-50') || 
                                               this.closest('.kyc-form-card')?.querySelector('.bg-orange-50');
                            if (warningDiv) {
                                warningDiv.innerHTML = '<i class="fas fa-check-circle"></i> Selfie uploaded! (simulated)';
                                warningDiv.classList.remove('bg-orange-50', 'text-orange-600');
                                warningDiv.classList.add('bg-green-50', 'text-green-700');
                            }
                        }
                    });
                }
            });

            // KYC submit buttons
            const submitBtns = document.querySelectorAll('.kyc-submit-btn');
            submitBtns.forEach(btn => {
                btn.addEventListener('click', function () {
                    alert('✅ KYC application submitted! Speedly will verify your documents shortly. You’ll be notified via SMS.');
                });
            });

            // navigation simulation
            const navItems = document.querySelectorAll('.nav-item, .desktop-nav-item');
            navItems.forEach(item => {
                item.addEventListener('click', function (e) {
                    e.preventDefault();
                    navItems.forEach(n => n.classList.remove('active'));
                    this.classList.add('active');
                });
            });

            // notification bell
            const notifBtn = document.querySelector('.notification-btn');
            if (notifBtn) notifBtn.addEventListener('click', function () {
                alert('🔔 Reminder: Upload your selfie to complete KYC.');
            });

            // document preview click
            const docPreviews = document.querySelectorAll('.document-preview');
            docPreviews.forEach(pre => {
                pre.addEventListener('click', () => alert('📄 Document preview (simulated)'));
            });

            // responsive view switch (exact from dashboard)
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
    