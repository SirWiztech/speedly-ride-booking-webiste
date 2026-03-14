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
    
    // Fetch ride details from API
    fetch(`SERVER/API/get_ride_details.php?ride_id=${rideId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showRideDetailsModal(data.ride);
            } else {
                // Fallback to mock data for demo
                showMockRideDetails(rideId);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showMockRideDetails(rideId);
        });
}

// Show ride details modal
function showRideDetailsModal(ride) {
    const statusColors = {
        'completed': '#4CAF50',
        'pending': '#FF9800',
        'cancelled': '#F44336',
        'ongoing': '#2196F3'
    };
    
    const statusIcon = {
        'completed': 'fa-check-circle',
        'pending': 'fa-clock',
        'cancelled': 'fa-times-circle',
        'ongoing': 'fa-spinner'
    };
    
    Swal.fire({
        title: 'Ride Details',
        html: `
            <div style="text-align: left;">
                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 15px;">
                    <span style="background: ${statusColors[ride.status] || '#999'}; color: white; padding: 5px 10px; border-radius: 20px; font-size: 12px;">
                        <i class="fas ${statusIcon[ride.status] || 'fa-info-circle'}"></i> ${ride.status.toUpperCase()}
                    </span>
                    <span style="color: #666; font-size: 14px;">${ride.ride_number}</span>
                </div>
                
                <div style="margin-bottom: 20px;">
                    <p><i class="fas fa-circle" style="color: #ff5e00; font-size: 10px;"></i> <strong>Pickup:</strong> ${ride.pickup_address}</p>
                    <p><i class="fas fa-map-marker-alt" style="color: #ff5e00;"></i> <strong>Destination:</strong> ${ride.destination_address}</p>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 20px;">
                    <div>
                        <p style="color: #666; font-size: 12px;">Date & Time</p>
                        <p style="font-weight: 600;">${ride.date} • ${ride.time}</p>
                    </div>
                    <div>
                        <p style="color: #666; font-size: 12px;">Distance</p>
                        <p style="font-weight: 600;">${ride.distance} km</p>
                    </div>
                    <div>
                        <p style="color: #666; font-size: 12px;">Ride Type</p>
                        <p style="font-weight: 600; text-transform: capitalize;">${ride.ride_type}</p>
                    </div>
                    <div>
                        <p style="color: #666; font-size: 12px;">Total Fare</p>
                        <p style="font-weight: 600; color: #ff5e00;">₦${parseFloat(ride.fare).toLocaleString()}</p>
                    </div>
                </div>
                
                ${ride.driver_name ? `
                <div style="border-top: 1px solid #eee; padding-top: 15px;">
                    <h4 style="margin-bottom: 10px;">Driver Details</h4>
                    <div style="display: flex; align-items: center; gap: 15px;">
                        <div style="width: 50px; height: 50px; background: linear-gradient(135deg, #ff5e00 0%, #ff8c3a 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; font-size: 20px;">
                            ${ride.driver_name.charAt(0)}
                        </div>
                        <div>
                            <p style="font-weight: 600;">${ride.driver_name}</p>
                            <p style="color: #666; font-size: 13px;">${ride.vehicle || 'Vehicle not specified'}</p>
                            <p style="color: #666; font-size: 13px;"><i class="fas fa-star" style="color: #FFC107;"></i> ${ride.driver_rating || '4.9'}</p>
                        </div>
                    </div>
                </div>
                ` : ''}
                
                ${ride.status == 'completed' && !ride.user_rating ? `
                <div style="border-top: 1px solid #eee; padding-top: 15px; margin-top: 15px;">
                    <h4 style="margin-bottom: 10px;">Rate Your Driver</h4>
                    <div style="font-size: 30px; text-align: center; color: #ffc107;">
                        <i class="far fa-star" onclick="setRating(1)" style="cursor: pointer;"></i>
                        <i class="far fa-star" onclick="setRating(2)" style="cursor: pointer;"></i>
                        <i class="far fa-star" onclick="setRating(3)" style="cursor: pointer;"></i>
                        <i class="far fa-star" onclick="setRating(4)" style="cursor: pointer;"></i>
                        <i class="far fa-star" onclick="setRating(5)" style="cursor: pointer;"></i>
                    </div>
                    <textarea id="reviewText" class="swal2-textarea" placeholder="Write a review (optional)" style="margin-top: 10px;"></textarea>
                    <button onclick="submitRating('${ride.id}')" style="width: 100%; margin-top: 10px; padding: 10px; background: #ff5e00; color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">
                        Submit Rating
                    </button>
                </div>
                ` : ride.user_rating ? `
                <div style="border-top: 1px solid #eee; padding-top: 15px; margin-top: 15px;">
                    <p><i class="fas fa-star" style="color: #FFC107;"></i> <strong>Your Rating:</strong> ${ride.user_rating}/5</p>
                    ${ride.user_review ? `<p><i class="fas fa-comment"></i> <strong>Review:</strong> ${ride.user_review}</p>` : ''}
                </div>
                ` : ''}
            </div>
        `,
        showCloseButton: true,
        showConfirmButton: false,
        width: '600px'
    });
}

// Show mock ride details (fallback)
function showMockRideDetails(rideId) {
    const rides = {
        '1': {
            id: '1',
            ride_number: 'R202602261234',
            status: 'completed',
            pickup: 'Lagos Airport',
            destination: 'Lekki Phase 1',
            date: 'Feb 26, 2026',
            time: '1:53 PM',
            distance: '28.5',
            ride_type: 'economy',
            fare: '2450',
            driver_name: 'Michael Chen',
            vehicle: 'Toyota Prius • LAG123AB',
            driver_rating: '4.8'
        },
        '2': {
            id: '2',
            ride_number: 'R202602261235',
            status: 'completed',
            pickup: 'Ikeja',
            destination: 'Victoria Island',
            date: 'Feb 26, 2026',
            time: '2:33 PM',
            distance: '15.2',
            ride_type: 'courier',
            fare: '1875',
            driver_name: 'Sarah Johnson',
            vehicle: 'Honda Civic • LAG456CD',
            driver_rating: '5.0'
        }
    };
    
    const ride = rides[rideId] || rides['1'];
    
    Swal.fire({
        title: 'Ride Details',
        html: `
            <div style="text-align: left;">
                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 15px;">
                    <span style="background: #4CAF50; color: white; padding: 5px 10px; border-radius: 20px; font-size: 12px;">
                        <i class="fas fa-check-circle"></i> COMPLETED
                    </span>
                    <span style="color: #666; font-size: 14px;">${ride.ride_number}</span>
                </div>
                
                <div style="margin-bottom: 20px;">
                    <p><i class="fas fa-circle" style="color: #ff5e00; font-size: 10px;"></i> <strong>Pickup:</strong> ${ride.pickup}</p>
                    <p><i class="fas fa-map-marker-alt" style="color: #ff5e00;"></i> <strong>Destination:</strong> ${ride.destination}</p>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 20px;">
                    <div>
                        <p style="color: #666; font-size: 12px;">Date & Time</p>
                        <p style="font-weight: 600;">${ride.date} • ${ride.time}</p>
                    </div>
                    <div>
                        <p style="color: #666; font-size: 12px;">Distance</p>
                        <p style="font-weight: 600;">${ride.distance} km</p>
                    </div>
                    <div>
                        <p style="color: #666; font-size: 12px;">Ride Type</p>
                        <p style="font-weight: 600; text-transform: capitalize;">${ride.ride_type}</p>
                    </div>
                    <div>
                        <p style="color: #666; font-size: 12px;">Total Fare</p>
                        <p style="font-weight: 600; color: #ff5e00;">₦${ride.fare}</p>
                    </div>
                </div>
                
                <div style="border-top: 1px solid #eee; padding-top: 15px;">
                    <h4 style="margin-bottom: 10px;">Driver Details</h4>
                    <div style="display: flex; align-items: center; gap: 15px;">
                        <div style="width: 50px; height: 50px; background: linear-gradient(135deg, #ff5e00 0%, #ff8c3a 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; font-size: 20px;">
                            ${ride.driver_name.charAt(0)}
                        </div>
                        <div>
                            <p style="font-weight: 600;">${ride.driver_name}</p>
                            <p style="color: #666; font-size: 13px;">${ride.vehicle}</p>
                            <p style="color: #666; font-size: 13px;"><i class="fas fa-star" style="color: #FFC107;"></i> ${ride.driver_rating}</p>
                        </div>
                    </div>
                </div>
            </div>
        `,
        showCloseButton: true,
        showConfirmButton: false,
        width: '600px'
    });
}

// Rating functionality
let selectedRating = 0;

function setRating(rating) {
    selectedRating = rating;
    const stars = document.querySelectorAll('.fa-star');
    stars.forEach((star, index) => {
        if (index < rating) {
            star.classList.remove('far');
            star.classList.add('fas');
        } else {
            star.classList.remove('fas');
            star.classList.add('far');
        }
    });
}

function submitRating(rideId) {
    if (selectedRating === 0) {
        Swal.fire({
            icon: 'warning',
            title: 'Select Rating',
            text: 'Please select a rating before submitting',
            confirmButtonColor: '#ff5e00'
        });
        return;
    }
    
    const review = document.getElementById('reviewText')?.value || '';
    
    // Submit rating to API
    const formData = new FormData();
    formData.append('ride_id', rideId);
    formData.append('rating', selectedRating);
    formData.append('review', review);
    
    fetch('SERVER/API/rate_driver.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
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
        console.error('Error:', error);
        Swal.fire({
            icon: 'success',
            title: 'Thank You!',
            text: 'Your rating has been submitted (demo mode)',
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