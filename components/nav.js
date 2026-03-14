// JS/client_dashboard.js

// Global variables
let selectedRating = 0;

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
});

// ========== RIDE DETAILS AND RATING FUNCTIONS ==========

// View ride details
function viewRideDetails(rideId) {
    console.log('Viewing ride details for ID:', rideId);
    
    // Show loading
    Swal.fire({
        title: 'Loading ride details...',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    // Fetch ride details
    fetch(`SERVER/API/get_ride_details.php?ride_id=${rideId}`)
        .then(response => response.json())
        .then(data => {
            Swal.close();
            
            if (data.success && data.ride) {
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
                title: 'Error',
                text: 'Failed to load ride details. Please try again.',
                confirmButtonColor: '#ff5e00'
            });
        });
}

// Display ride details in modal
function displayRideDetails(ride) {
    // Format date
    const rideDate = ride.formatted_date || new Date(ride.created_at).toLocaleDateString();
    const rideTime = ride.formatted_time || new Date(ride.created_at).toLocaleTimeString();
    
    // Build HTML
    let html = `
        <div style="text-align: left; max-height: 500px; overflow-y: auto; padding: 10px;">
            <div style="background: #f8f9fa; padding: 15px; border-radius: 10px; margin-bottom: 15px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                    <span style="font-size: 18px; font-weight: bold;">Ride Details</span>
                    <span style="background: ${getStatusColor(ride.status)}; color: white; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600;">
                        ${ride.status_display || ride.status.charAt(0).toUpperCase() + ride.status.slice(1)}
                    </span>
                </div>
                <p><strong>Ride #:</strong> ${ride.ride_number || 'N/A'}</p>
                <p><strong>Date:</strong> ${rideDate} at ${rideTime}</p>
                <p><strong>From:</strong> ${ride.pickup_address || 'N/A'}</p>
                <p><strong>To:</strong> ${ride.destination_address || 'N/A'}</p>
                <p><strong>Distance:</strong> ${ride.distance_km ? ride.distance_km.toFixed(1) + ' km' : 'N/A'}</p>
                <p><strong>Fare:</strong> <span style="color: #4CAF50; font-weight: bold;">₦${ride.total_fare ? ride.total_fare.toLocaleString() : '0'}</span></p>
                ${ride.trip_duration ? `<p><strong>Trip Duration:</strong> ${ride.trip_duration}</p>` : ''}
            </div>
    `;
    
    // Driver info if available
    if (ride.driver_name) {
        html += `
            <div style="background: #e8f5e9; padding: 15px; border-radius: 10px; margin-bottom: 15px;">
                <h4 style="margin-bottom: 10px; color: #2E7D32;">Driver Information</h4>
                <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 10px;">
                    <div style="width: 50px; height: 50px; background: #4CAF50; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 24px; font-weight: bold;">
                        ${ride.driver_name.charAt(0)}
                    </div>
                    <div>
                        <p style="font-weight: bold; font-size: 16px;">${ride.driver_name}</p>
                        <p style="color: #666;">${ride.vehicle_display || 'Vehicle not specified'}</p>
                        ${ride.driver_phone ? `<p style="color: #666; font-size: 14px;"><i class="fas fa-phone"></i> ${ride.driver_phone}</p>` : ''}
                    </div>
                </div>
                <p><strong>Driver Rating:</strong> ${formatRating(ride.driver_rating)}</p>
                <p><strong>Total Rides:</strong> ${ride.driver_total_rides || 0}</p>
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
    } else if (ride.my_rating) {
        html += `
            <div style="background: #e8f5e9; padding: 15px; border-radius: 10px;">
                <h4 style="margin-bottom: 10px; color: #2E7D32;">Your Rating</h4>
                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
                    <div style="color: #FFD700; font-size: 24px;">
                        ${'★'.repeat(ride.my_rating)}${'☆'.repeat(5 - ride.my_rating)}
                    </div>
                    <span style="font-weight: bold;">${ride.my_rating}/5</span>
                </div>
                ${ride.my_review ? `<p style="background: white; padding: 10px; border-radius: 8px;"><strong>Your review:</strong> ${ride.my_review}</p>` : ''}
            </div>
        `;
    }
    
    // Add action buttons for active rides
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
            // Add star rating functionality
            if (ride.can_rate) {
                selectedRating = 0;
                const stars = document.querySelectorAll('#ratingStars i');
                
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

// Format rating stars
function formatRating(rating) {
    if (!rating) return 'No ratings yet';
    const stars = '★'.repeat(Math.floor(rating)) + '☆'.repeat(5 - Math.floor(rating));
    return `<span style="color: #FFD700;">${stars}</span> (${rating.toFixed(1)})`;
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
                let html = '<div style="text-align: left;">';
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
                    confirmButtonColor: '#ff5e00'
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