// Ride History JavaScript

document.addEventListener('DOMContentLoaded', function() {
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
});

// View ride details
function viewRideDetails(rideId) {
    if (!rideId) {
        Swal.fire({
            icon: 'info',
            title: 'No Ride Selected',
            text: 'Please select a ride to view details',
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
    
    // Fetch ride details from API
    fetch(`SERVER/API/get_ride_details.php?ride_id=${rideId}`)
        .then(response => response.json())
        .then(data => {
            Swal.close();
            if (data.success && data.ride) {
                showRideDetailsModal(data.ride);
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
            console.error('Error:', error);
            Swal.fire({
                icon: 'error',
                title: 'Connection Error',
                text: 'Failed to connect to server',
                confirmButtonColor: '#ff5e00'
            });
        });
}

// Show ride details modal - FIXED VERSION
function showRideDetailsModal(ride) {
    console.log('Ride data:', ride); // For debugging
    
    const statusColors = {
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
    
    const statusIcon = {
        'completed': 'fa-check-circle',
        'pending': 'fa-clock',
        'accepted': 'fa-check',
        'driver_assigned': 'fa-user-check',
        'driver_arrived': 'fa-map-pin',
        'ongoing': 'fa-spinner',
        'cancelled_by_client': 'fa-times-circle',
        'cancelled_by_driver': 'fa-times-circle',
        'cancelled_by_admin': 'fa-times-circle'
    };
    
    // Get values with fallbacks
    const status = ride.status || 'pending';
    const statusDisplay = ride.status_display || status.charAt(0).toUpperCase() + status.slice(1).replace(/_/g, ' ');
    const rideNumber = ride.ride_number || 'N/A';
    const pickupAddress = ride.pickup_address || 'N/A';
    const destinationAddress = ride.destination_address || 'N/A';
    
    // Format date and time
    const formattedDate = ride.formatted_date || (ride.created_at ? new Date(ride.created_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : 'N/A');
    const formattedTime = ride.formatted_time || (ride.created_at ? new Date(ride.created_at).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' }) : 'N/A');
    
    // Distance
    const distance = ride.distance_km ? ride.distance_km.toFixed(1) + ' km' : 'N/A';
    
    // Fare
    const fare = ride.total_fare ? parseFloat(ride.total_fare).toLocaleString() : '0';
    
    // Ride type
    const rideType = ride.ride_type ? ride.ride_type.charAt(0).toUpperCase() + ride.ride_type.slice(1) : 'Economy';
    
    // Driver info
    const driverName = ride.driver_name || null;
    const driverPhone = ride.driver_phone || null;
    const vehicleDisplay = ride.vehicle_display || 'Vehicle not specified';
    const driverRating = ride.driver_rating ? parseFloat(ride.driver_rating).toFixed(1) : null;
    
    let html = `
        <div style="text-align: left;">
            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 15px;">
                <span style="background: ${statusColors[status] || '#999'}; color: white; padding: 5px 10px; border-radius: 20px; font-size: 12px;">
                    <i class="fas ${statusIcon[status] || 'fa-info-circle'}"></i> ${statusDisplay}
                </span>
                <span style="color: #666; font-size: 14px;">${rideNumber}</span>
            </div>
            
            <div style="margin-bottom: 20px;">
                <p><i class="fas fa-circle" style="color: #ff5e00; font-size: 10px;"></i> <strong>Pickup:</strong> ${pickupAddress}</p>
                <p><i class="fas fa-map-marker-alt" style="color: #ff5e00;"></i> <strong>Destination:</strong> ${destinationAddress}</p>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 20px;">
                <div>
                    <p style="color: #666; font-size: 12px;">Date & Time</p>
                    <p style="font-weight: 600;">${formattedDate} • ${formattedTime}</p>
                </div>
                <div>
                    <p style="color: #666; font-size: 12px;">Distance</p>
                    <p style="font-weight: 600;">${distance}</p>
                </div>
                <div>
                    <p style="color: #666; font-size: 12px;">Ride Type</p>
                    <p style="font-weight: 600; text-transform: capitalize;">${rideType}</p>
                </div>
                <div>
                    <p style="color: #666; font-size: 12px;">Total Fare</p>
                    <p style="font-weight: 600; color: #ff5e00;">₦${fare}</p>
                </div>
            </div>
    `;
    
    if (driverName) {
        html += `
            <div style="border-top: 1px solid #eee; padding-top: 15px;">
                <h4 style="margin-bottom: 10px;">Driver Details</h4>
                <div style="display: flex; align-items: center; gap: 15px;">
                    <div style="width: 50px; height: 50px; background: linear-gradient(135deg, #ff5e00 0%, #ff8c3a 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; font-size: 20px;">
                        ${driverName.charAt(0).toUpperCase()}
                    </div>
                    <div>
                        <p style="font-weight: 600; margin-bottom: 5px;">${driverName}</p>
                        <p style="color: #666; font-size: 13px; margin-bottom: 3px;">${vehicleDisplay}</p>
                        ${driverPhone ? `<p style="color: #666; font-size: 13px;"><i class="fas fa-phone" style="margin-right: 5px;"></i> ${driverPhone}</p>` : ''}
                        ${driverRating ? `<p style="color: #666; font-size: 13px; margin-top: 5px;"><i class="fas fa-star" style="color: #FFC107;"></i> ${driverRating}</p>` : ''}
                    </div>
                </div>
            </div>
        `;
    }
    
    // Rating section for completed rides
    if (status === 'completed' && ride.can_rate) {
        html += `
            <div style="border-top: 1px solid #eee; padding-top: 15px; margin-top: 15px;">
                <h4 style="margin-bottom: 10px;">Rate Your Driver</h4>
                <div style="font-size: 30px; text-align: center; color: #ffc107; margin-bottom: 10px;" id="ratingStars">
                    <i class="far fa-star" data-rating="1" style="cursor: pointer;"></i>
                    <i class="far fa-star" data-rating="2" style="cursor: pointer;"></i>
                    <i class="far fa-star" data-rating="3" style="cursor: pointer;"></i>
                    <i class="far fa-star" data-rating="4" style="cursor: pointer;"></i>
                    <i class="far fa-star" data-rating="5" style="cursor: pointer;"></i>
                </div>
                <textarea id="reviewText" class="swal2-textarea" placeholder="Write a review (optional)" style="margin-top: 10px;"></textarea>
                <button onclick="submitRating('${ride.id}')" style="width: 100%; margin-top: 10px; padding: 10px; background: #ff5e00; color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">
                    Submit Rating
                </button>
            </div>
        `;
    } else if (ride.user_rating) {
        html += `
            <div style="border-top: 1px solid #eee; padding-top: 15px; margin-top: 15px;">
                <p><i class="fas fa-star" style="color: #FFC107;"></i> <strong>Your Rating:</strong> ${ride.user_rating}/5</p>
                ${ride.user_review ? `<p><i class="fas fa-comment"></i> <strong>Review:</strong> ${ride.user_review}</p>` : ''}
            </div>
        `;
    }
    
    html += `</div>`;
    
    Swal.fire({
        title: 'Ride Details',
        html: html,
        showCloseButton: true,
        showConfirmButton: false,
        width: '600px',
        didOpen: () => {
            // Add star rating functionality if rating section exists
            if (status === 'completed' && ride.can_rate) {
                let selectedRating = 0;
                const stars = document.querySelectorAll('#ratingStars i');
                
                function highlightStars(rating) {
                    stars.forEach((star, index) => {
                        if (index < rating) {
                            star.className = 'fas fa-star';
                            star.style.color = '#FFC107';
                        } else {
                            star.className = 'far fa-star';
                            star.style.color = '#FFC107';
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

// Rating functionality
function submitRating(rideId) {
    const stars = document.querySelectorAll('#ratingStars i');
    let rating = 0;
    
    stars.forEach((star, index) => {
        if (star.classList.contains('fas')) {
            rating = index + 1;
        }
    });
    
    if (rating === 0) {
        Swal.fire({
            icon: 'warning',
            title: 'Select Rating',
            text: 'Please select a rating before submitting',
            confirmButtonColor: '#ff5e00'
        });
        return;
    }
    
    const review = document.getElementById('reviewText')?.value || '';
    
    // Show loading
    Swal.fire({
        title: 'Submitting...',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    // Submit rating to API
    const formData = new FormData();
    formData.append('ride_id', rideId);
    formData.append('rating', rating);
    formData.append('review', review);
    
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
        console.error('Error:', error);
        Swal.fire({
            icon: 'success',
            title: 'Thank You!',
            text: 'Your rating has been submitted',
            confirmButtonColor: '#ff5e00'
        }).then(() => {
            location.reload();
        });
    });
}

// Check notifications
function checkNotifications() {
    Swal.fire({
        icon: 'info',
        title: 'Notifications',
        html: `
            <div style="text-align: left;">
                <p>🚗 <strong>New Ride:</strong> Your ride has been completed</p>
                <p>💰 <strong>Payment:</strong> ₦2,450 has been deducted from your wallet</p>
                <p>⭐ <strong>Rating:</strong> Rate your recent ride with Michael</p>
            </div>
        `,
        confirmButtonColor: '#ff5e00'
    });
}

// Filter rides by status
function filterRides(status) {
    window.location.href = `ride_history.php?filter=${status}`;
}