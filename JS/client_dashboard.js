// JS/client_dashboard.js

// Global variables
let selectedRating = 0;
let notificationCheckInterval = null;

// Initialize when page loads
document.addEventListener('DOMContentLoaded', function() {
    console.log('Client dashboard JS loaded');
    
    // Responsive view switcher
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
    
    // Add click handlers to transaction items
    document.querySelectorAll('.transaction-item').forEach(item => {
        item.addEventListener('click', function(e) {
            // Don't trigger if clicking on a button inside
            if (e.target.tagName === 'BUTTON' || e.target.closest('button')) return;
            
            // Try to get ride ID from data attribute or from the viewRideDetails in onclick
            const onclick = this.getAttribute('onclick');
            if (onclick) {
                const match = onclick.match(/'([^']+)'/);
                if (match && match[1]) {
                    viewRideDetails(match[1]);
                }
            }
        });
    });
    
    // Start checking for notifications every 30 seconds
    startNotificationCheck();
    
    // Check for URL parameters (ride accepted notification)
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('notification') === 'ride_accepted') {
        showRideAcceptedNotification(urlParams.get('ride_id'));
    } else if (urlParams.get('notification') === 'driver_cancelled') {
        showDriverCancelledNotification(urlParams.get('ride_id'));
    }
});

// Start checking for new notifications
function startNotificationCheck() {
    // Check immediately
    checkForNewNotifications();
    
    // Then check every 30 seconds
    notificationCheckInterval = setInterval(checkForNewNotifications, 30000);
}

// Check for new notifications
function checkForNewNotifications() {
    fetch('SERVER/API/get_notifications.php')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.count > 0) {
                updateNotificationBadge(data.count);
                
                // Show popup for new notifications
                if (data.notifications && data.notifications.length > 0) {
                    data.notifications.forEach(notif => {
                        if (notif.type === 'ride_update') {
                            showRideUpdateNotification(notif);
                        }
                    });
                }
            }
        })
        .catch(error => console.error('Error checking notifications:', error));
}

// Update notification badge
function updateNotificationBadge(count) {
    const badges = document.querySelectorAll('.notification-badge');
    badges.forEach(badge => {
        badge.textContent = count;
        if (count > 0) {
            badge.classList.add('notification-pulse');
        } else {
            badge.classList.remove('notification-pulse');
        }
    });
}

// Show ride update notification
function showRideUpdateNotification(notification) {
    const Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 5000,
        timerProgressBar: true,
        didOpen: (toast) => {
            toast.addEventListener('mouseenter', Swal.stopTimer);
            toast.addEventListener('mouseleave', Swal.resumeTimer);
        }
    });

    Toast.fire({
        icon: 'info',
        title: notification.title,
        text: notification.message
    });
}

// Show ride accepted notification
function showRideAcceptedNotification(rideId) {
    Swal.fire({
        icon: 'success',
        title: 'Ride Accepted!',
        html: `
            <p>A driver has accepted your ride!</p>
            <p class="mt-2">They are on the way to pick you up.</p>
            <button onclick="viewRideDetails('${rideId}')" class="mt-3 bg-[#ff5e00] text-white px-4 py-2 rounded-lg">
                View Ride Details
            </button>
        `,
        showConfirmButton: false,
        showCloseButton: true
    });
}

// Show driver cancelled notification
function showDriverCancelledNotification(rideId) {
    Swal.fire({
        icon: 'warning',
        title: 'Ride Cancelled',
        html: `
            <p>The driver cancelled your ride.</p>
            <p class="mt-2">Please book another ride.</p>
            <button onclick="window.location.href='book-ride.php'" class="mt-3 bg-[#ff5e00] text-white px-4 py-2 rounded-lg">
                Book New Ride
            </button>
        `,
        showConfirmButton: false,
        showCloseButton: true
    });
}

// ========== RIDE DETAILS AND RATING FUNCTIONS ==========

// View ride details - FIXED VERSION
function viewRideDetails(rideId) {
    console.log('Viewing ride details for ID:', rideId);
    
    if (!rideId) {
        Swal.fire({
            icon: 'warning',
            title: 'Invalid Ride',
            text: 'No ride ID provided',
            confirmButtonColor: '#ff5e00'
        });
        return;
    }
    
    // Show loading
    Swal.fire({
        title: 'Loading ride details...',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    // Fetch ride details - use the same URL that worked in the test
    const url = `SERVER/API/get_ride_details.php?ride_id=${encodeURIComponent(rideId)}`;
    console.log('Fetching from:', url);
    
    fetch(url, {
        method: 'GET',
        credentials: 'same-origin',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        }
    })
    .then(response => {
        console.log('Response status:', response.status);
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        Swal.close();
        console.log('Response data:', data);
        
        if (data.success && data.ride) {
            // Call the display function with the ride data
            displayRideDetails(data.ride);
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: data.message || 'Failed to load ride details',
                confirmButtonColor: '#ff5e00'
            });
        }
    })
    .catch(error => {
        Swal.close();
        console.error('Error fetching ride details:', error);
        
        Swal.fire({
            icon: 'error',
            title: 'Connection Error',
            text: 'Failed to load ride details: ' + error.message,
            confirmButtonColor: '#ff5e00'
        });
    });
}

// Display ride details in modal - FIXED VERSION
function displayRideDetails(ride) {
    console.log('Displaying ride details:', ride);
    
    // Format date
    const rideDate = ride.formatted_date || (ride.created_at ? new Date(ride.created_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : 'N/A');
    const rideTime = ride.formatted_time || (ride.created_at ? new Date(ride.created_at).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' }) : 'N/A');
    
    // Get status color
    const statusColor = getStatusColor(ride.status);
    
    // Build HTML
    let html = `
        <div style="text-align: left; max-height: 70vh; overflow-y: auto; padding: 10px;">
            <div style="background: #f8f9fa; padding: 15px; border-radius: 10px; margin-bottom: 15px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                    <span style="font-size: 18px; font-weight: bold;">Ride Details</span>
                    <span style="background: ${statusColor}; color: white; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600;">
                        ${ride.status_display || (ride.status ? ride.status.charAt(0).toUpperCase() + ride.status.slice(1).replace(/_/g, ' ') : 'Unknown')}
                    </span>
                </div>
                <p><strong>Ride #:</strong> ${ride.ride_number || 'N/A'}</p>
                <p><strong>Date:</strong> ${rideDate} at ${rideTime}</p>
                <p><strong>From:</strong> ${ride.pickup_address || 'N/A'}</p>
                <p><strong>To:</strong> ${ride.destination_address || 'N/A'}</p>
                <p><strong>Distance:</strong> ${ride.distance_km ? ride.distance_km.toFixed(1) + ' km' : 'N/A'}</p>
                <p><strong>Fare:</strong> <span style="color: #4CAF50; font-weight: bold;">₦${ride.total_fare ? ride.total_fare.toLocaleString() : '0'}</span></p>
    `;
    
    // Add platform commission info if available
    if (ride.platform_commission) {
        html += `<p><strong>Platform Fee:</strong> ₦${ride.platform_commission.toLocaleString()}</p>`;
    }
    
    // Add payment status
    html += `<p><strong>Payment Status:</strong> <span style="color: ${ride.payment_status === 'paid' ? '#4CAF50' : '#FF9800'};">${ride.payment_status ? ride.payment_status.toUpperCase() : 'PENDING'}</span></p>`;
    
    html += `</div>`;
    
    // Driver info if available
    if (ride.driver_name) {
        html += `
            <div style="background: #e8f5e9; padding: 15px; border-radius: 10px; margin-bottom: 15px;">
                <h4 style="margin-bottom: 10px; color: #2E7D32;">Driver Information</h4>
                <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 10px;">
                    <div style="width: 50px; height: 50px; background: #4CAF50; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 24px; font-weight: bold;">
                        ${ride.driver_name ? ride.driver_name.charAt(0).toUpperCase() : 'D'}
                    </div>
                    <div>
                        <p style="font-weight: bold; font-size: 16px; margin-bottom: 5px;">${ride.driver_name}</p>
                        <p style="color: #666; margin-bottom: 3px;">${ride.vehicle_display || 'Vehicle not specified'}</p>
                        ${ride.driver_phone ? `<p style="color: #666; font-size: 14px; margin-top: 5px;"><i class="fas fa-phone" style="margin-right: 5px;"></i> ${ride.driver_phone}</p>` : ''}
                        ${ride.driver_total_rides !== undefined ? `<p style="color: #666; font-size: 13px; margin-top: 5px;"><i class="fas fa-road" style="margin-right: 5px;"></i> ${ride.driver_total_rides} rides completed</p>` : ''}
                    </div>
                </div>
                <p><strong>Driver Rating:</strong> ${formatRating(ride.driver_rating)}</p>
            </div>
        `;
    } else {
        html += `
            <div style="background: #f5f5f5; padding: 15px; border-radius: 10px; margin-bottom: 15px; text-align: center;">
                <p style="color: #666;">No driver assigned yet</p>
                <p style="font-size: 13px; color: #999;">Waiting for a driver to accept your ride</p>
            </div>
        `;
    }
    
    // Rating section if ride is completed and not rated
    if (ride.can_rate) {
        html += `
            <div style="background: #fff3e0; padding: 15px; border-radius: 10px;">
                <h4 style="margin-bottom: 15px; color: #E65100;">Rate Your Driver</h4>
                <div style="display: flex; justify-content: center; gap: 10px; margin-bottom: 15px; font-size: 30px;" id="ratingStars">
                    <i class="fas fa-star" data-rating="1" style="color: #ddd; cursor: pointer; transition: color 0.2s;"></i>
                    <i class="fas fa-star" data-rating="2" style="color: #ddd; cursor: pointer; transition: color 0.2s;"></i>
                    <i class="fas fa-star" data-rating="3" style="color: #ddd; cursor: pointer; transition: color 0.2s;"></i>
                    <i class="fas fa-star" data-rating="4" style="color: #ddd; cursor: pointer; transition: color 0.2s;"></i>
                    <i class="fas fa-star" data-rating="5" style="color: #ddd; cursor: pointer; transition: color 0.2s;"></i>
                </div>
                <textarea id="reviewText" placeholder="Share your experience with this driver (optional)" style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px; margin-bottom: 15px; font-family: inherit;" rows="3"></textarea>
                <button onclick="submitRating('${ride.id}')" style="background: #ff5e00; color: white; border: none; padding: 12px 20px; border-radius: 8px; width: 100%; font-weight: bold; font-size: 16px; cursor: pointer; transition: background 0.3s;">
                    <i class="fas fa-star"></i> Submit Rating
                </button>
            </div>
        `;
    } else if (ride.user_rating) {
        html += `
            <div style="background: #e8f5e9; padding: 15px; border-radius: 10px;">
                <h4 style="margin-bottom: 10px; color: #2E7D32;">Your Rating</h4>
                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
                    <div style="color: #FFD700; font-size: 24px;">
                        ${'★'.repeat(ride.user_rating)}${'☆'.repeat(5 - ride.user_rating)}
                    </div>
                    <span style="font-weight: bold;">${ride.user_rating}/5</span>
                </div>
                ${ride.user_review ? `<p style="background: white; padding: 10px; border-radius: 8px;"><strong>Your review:</strong> ${ride.user_review}</p>` : ''}
            </div>
        `;
    }
    
    // Add action buttons for active rides (pending, accepted, etc.)
    if (['pending', 'accepted', 'driver_assigned', 'driver_arrived', 'ongoing'].includes(ride.status)) {
        html += `
            <div style="display: flex; gap: 10px; margin-top: 15px;">
                ${ride.driver_phone ? `
                <a href="tel:${ride.driver_phone}" style="flex: 1; background: #4CAF50; color: white; text-decoration: none; padding: 12px; border-radius: 8px; text-align: center; font-weight: 600;">
                    <i class="fas fa-phone"></i> Call Driver
                </a>
                ` : ''}
                ${ride.pickup_latitude && ride.pickup_longitude ? `
                <a href="https://www.google.com/maps/dir/?api=1&destination=${ride.pickup_latitude},${ride.pickup_longitude}" target="_blank" style="flex: 1; background: #2196F3; color: white; text-decoration: none; padding: 12px; border-radius: 8px; text-align: center; font-weight: 600;">
                    <i class="fas fa-map-marked-alt"></i> Track
                </a>
                ` : ''}
                <button onclick="cancelRide('${ride.id}')" style="flex: 1; background: #f44336; color: white; border: none; padding: 12px; border-radius: 8px; font-weight: 600; cursor: pointer;">
                    <i class="fas fa-times"></i> Cancel
                </button>
            </div>
        `;
    }
    
    html += `</div>`;
    
    Swal.fire({
        title: 'Ride Details',
        html: html,
        confirmButtonColor: '#ff5e00',
        confirmButtonText: 'Close',
        width: '600px',
        didOpen: () => {
            // Add star rating functionality if rating section exists
            if (ride.can_rate) {
                selectedRating = 0;
                const stars = document.querySelectorAll('#ratingStars i');
                
                if (stars.length > 0) {
                    function highlightStars(rating) {
                        stars.forEach((s, index) => {
                            if (index < rating) {
                                s.style.color = '#FFD700';
                            } else {
                                s.style.color = '#ddd';
                            }
                        });
                    }
                    
                    stars.forEach(star => {
                        star.addEventListener('mouseenter', function() {
                            const rating = parseInt(this.dataset.rating);
                            highlightStars(rating);
                        });
                        
                        star.addEventListener('mouseleave', function() {
                            highlightStars(selectedRating);
                        });
                        
                        star.addEventListener('click', function() {
                            selectedRating = parseInt(this.dataset.rating);
                            highlightStars(selectedRating);
                        });
                    });
                }
            }
        }
    });
}

// Submit rating for a ride
function submitRating(rideId) {
    const stars = document.querySelectorAll('#ratingStars i');
    let rating = 0;
    
    // Get selected rating
    stars.forEach((star, index) => {
        if (star.style.color === 'rgb(255, 215, 0)') { // Gold color
            rating = index + 1;
        }
    });
    
    if (rating === 0) {
        Swal.fire({
            icon: 'warning',
            title: 'Rating Required',
            text: 'Please select a rating before submitting',
            confirmButtonColor: '#ff5e00'
        });
        return;
    }
    
    const review = document.getElementById('reviewText')?.value || '';
    
    // Show loading
    Swal.fire({
        title: 'Submitting rating...',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    // Create form data
    const formData = new FormData();
    formData.append('ride_id', rideId);
    formData.append('rating', rating);
    formData.append('review', review);
    
    // Send to API
    fetch('SERVER/API/rate_driver.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        Swal.close();
        
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Thank You!',
                text: 'Your rating has been submitted successfully',
                confirmButtonColor: '#ff5e00'
            }).then(() => {
                // Refresh the page to show the updated rating
                location.reload();
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: data.message || 'Failed to submit rating',
                confirmButtonColor: '#ff5e00'
            });
        }
    })
    .catch(error => {
        Swal.close();
        console.error('Error submitting rating:', error);
        Swal.fire({
            icon: 'error',
            title: 'Connection Error',
            text: 'Failed to connect to server. Please try again.',
            confirmButtonColor: '#ff5e00'
        });
    });
}

// Cancel ride
function cancelRide(rideId) {
    Swal.fire({
        title: 'Cancel Ride?',
        text: 'Are you sure you want to cancel this ride?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#f44336',
        confirmButtonText: 'Yes, Cancel',
        cancelButtonText: 'No, Keep It'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Processing...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            fetch('SERVER/API/cancel_ride.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    ride_id: rideId,
                    reason: 'Cancelled by client'
                })
            })
            .then(response => response.json())
            .then(data => {
                Swal.close();
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Cancelled',
                        text: 'Your ride has been cancelled',
                        confirmButtonColor: '#ff5e00'
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: data.message || 'Failed to cancel ride',
                        confirmButtonColor: '#ff5e00'
                    });
                }
            })
            .catch(error => {
                Swal.close();
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Failed to cancel ride',
                    confirmButtonColor: '#ff5e00'
                });
            });
        }
    });
}

// ========== HELPER FUNCTIONS ==========

// Get status color
function getStatusColor(status) {
    const colors = {
        'completed': '#4CAF50',
        'pending': '#FF9800',
        'accepted': '#2196F3',
        'driver_assigned': '#2196F3',
        'driver_arrived': '#9C27B0',
        'ongoing': '#FF5722',
        'cancelled_by_client': '#F44336',
        'cancelled_by_driver': '#F44336',
        'cancelled_by_admin': '#F44336'
    };
    return colors[status] || '#9E9E9E';
}

// Format rating stars - FIXED VERSION
function formatRating(rating) {
    // Handle various input types
    if (rating === null || rating === undefined || rating === '') {
        return 'No ratings yet';
    }
    
    // Convert to number if it's a string
    const numRating = parseFloat(rating);
    
    // Check if it's a valid number
    if (isNaN(numRating) || numRating === 0) {
        return 'No ratings yet';
    }
    
    const fullStars = Math.floor(numRating);
    const halfStar = (numRating - fullStars) >= 0.5;
    let stars = '';
    
    for (let i = 1; i <= 5; i++) {
        if (i <= fullStars) {
            stars += '★';
        } else if (halfStar && i === fullStars + 1) {
            stars += '½';
        } else {
            stars += '☆';
        }
    }
    
    return `<span style="color: #FFD700;">${stars}</span> (${numRating.toFixed(1)})`;
}

// ========== PAYMENT FUNCTIONS ==========

// Add funds to wallet
function addFunds() {
    Swal.fire({
        title: 'Add Funds to Wallet',
        html: `
            <input type="number" id="amount" class="swal2-input" placeholder="Enter amount" min="100" step="100">
            <select id="paymentMethod" class="swal2-input">
                <option value="bank_transfer">Bank Transfer</option>
                <option value="card">Credit/Debit Card</option>
            </select>
        `,
        confirmButtonText: 'Add Funds',
        confirmButtonColor: '#ff5e00',
        showCancelButton: true,
        preConfirm: () => {
            const amount = document.getElementById('amount').value;
            if (!amount || amount < 100) {
                Swal.showValidationMessage('Please enter a valid amount (minimum ₦100)');
                return false;
            }
            return amount;
        }
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                icon: 'success',
                title: 'Funds Added!',
                text: `₦${parseFloat(result.value).toLocaleString()} has been added to your wallet`,
                timer: 2000,
                showConfirmButton: false
            }).then(() => {
                location.reload();
            });
        }
    });
}

// Check notifications
function checkNotifications() {
    fetch('SERVER/API/get_notifications.php')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.notifications.length > 0) {
                let html = '<div style="text-align: left; max-height: 400px; overflow-y: auto;">';
                data.notifications.forEach(notif => {
                    html += `
                        <div style="padding: 10px; border-bottom: 1px solid #eee;">
                            <p><strong>${notif.title}</strong></p>
                            <p>${notif.message}</p>
                            <p style="font-size: 12px; color: #999;">${new Date(notif.created_at).toLocaleString()}</p>
                        </div>
                    `;
                });
                html += '</div>';
                
                Swal.fire({
                    icon: 'info',
                    title: 'Notifications',
                    html: html,
                    confirmButtonColor: '#ff5e00',
                    width: '600px'
                }).then(() => {
                    // Mark notifications as read
                    fetch('SERVER/API/mark_notifications_read.php', { method: 'POST' });
                });
            } else {
                Swal.fire({
                    icon: 'info',
                    title: 'Notifications',
                    text: 'No new notifications',
                    confirmButtonColor: '#ff5e00'
                });
            }
        })
        .catch(() => {
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
        });
}

// Show coming soon message
function showComingSoon(feature) {
    Swal.fire({
        icon: 'info',
        title: 'Coming Soon!',
        text: `${feature} feature will be available in the next update.`,
        confirmButtonColor: '#ff5e00'
    });
}

// Clean up on page unload
window.addEventListener('beforeunload', function() {
    if (notificationCheckInterval) {
        clearInterval(notificationCheckInterval);
    }
});