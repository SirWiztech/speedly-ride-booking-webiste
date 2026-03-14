// ==================== GLOBAL VARIABLES ====================
let mobileStep = 1;
let desktopStep = 1;

let mobileBooking = {
    pickup: '',
    destination: '',
    pickupLat: null,
    pickupLng: null,
    pickupPlaceId: null,
    destLat: null,
    destLng: null,
    destPlaceId: null,
    plan: '',
    driver: '',
    driverId: null,
    payment: '',
    distance: 0,
    fare: 0
};

let desktopBooking = {
    pickup: '',
    destination: '',
    pickupLat: null,
    pickupLng: null,
    pickupPlaceId: null,
    destLat: null,
    destLng: null,
    destPlaceId: null,
    plan: '',
    driver: '',
    driverId: null,
    payment: '',
    distance: 0,
    fare: 0
};

// Google Maps Autocomplete instances
let pickupAutocompleteMobile, destAutocompleteMobile;
let pickupAutocompleteDesktop, destAutocompleteDesktop;

// Popular locations in Nigeria
const popularLocations = [
    { name: 'Lagos Airport', address: 'Murtala Muhammed International Airport, Lagos, Nigeria', icon: 'plane' },
    { name: 'Victoria Island', address: 'Victoria Island, Lagos, Nigeria', icon: 'building' },
    { name: 'Lekki Phase 1', address: 'Lekki Phase 1, Lagos, Nigeria', icon: 'map-pin' },
    { name: 'Ikeja Mall', address: 'Ikeja City Mall, Lagos, Nigeria', icon: 'shopping-cart' },
    { name: 'Maryland Mall', address: 'Maryland Mall, Lagos, Nigeria', icon: 'store' },
    { name: 'Ajah', address: 'Ajah, Lagos, Nigeria', icon: 'market' }
];

// ==================== INITIALIZE GOOGLE MAPS ====================
function initMap() {
    console.log('✅ Google Maps loaded');
    
    // Initialize Mobile Autocomplete
    const pickupMobile = document.getElementById('pickup-mobile');
    const destMobile = document.getElementById('destination-mobile');
    
    if (pickupMobile) {
        pickupAutocompleteMobile = new google.maps.places.Autocomplete(pickupMobile, {
            componentRestrictions: { country: 'ng' },
            fields: ['place_id', 'geometry', 'formatted_address', 'name']
        });
        
        pickupAutocompleteMobile.addListener('place_changed', () => {
            const place = pickupAutocompleteMobile.getPlace();
            
            if (!place.geometry) {
                Swal.fire({
                    icon: 'error',
                    title: 'Invalid Location',
                    text: 'Please select a location from the suggestions',
                    confirmButtonColor: '#ff5e00'
                });
                return;
            }
            
            mobileBooking.pickup = place.formatted_address;
            mobileBooking.pickupLat = place.geometry.location.lat();
            mobileBooking.pickupLng = place.geometry.location.lng();
            mobileBooking.pickupPlaceId = place.place_id;
            
            document.getElementById('pickup-lat-mobile').value = mobileBooking.pickupLat;
            document.getElementById('pickup-lng-mobile').value = mobileBooking.pickupLng;
            document.getElementById('pickup-place-id-mobile').value = mobileBooking.pickupPlaceId;
            document.getElementById('pickup-address-mobile').value = mobileBooking.pickup;
            
            console.log('✅ Pickup selected:', {
                address: place.formatted_address,
                lat: mobileBooking.pickupLat,
                lng: mobileBooking.pickupLng,
                placeId: place.place_id
            });
            
            if (mobileBooking.destLat) {
                calculateFare('mobile');
            }
            updateMobileButtonState();
        });
    }
    
    if (destMobile) {
        destAutocompleteMobile = new google.maps.places.Autocomplete(destMobile, {
            componentRestrictions: { country: 'ng' },
            fields: ['place_id', 'geometry', 'formatted_address', 'name']
        });
        
        destAutocompleteMobile.addListener('place_changed', () => {
            const place = destAutocompleteMobile.getPlace();
            
            if (!place.geometry) {
                Swal.fire({
                    icon: 'error',
                    title: 'Invalid Location',
                    text: 'Please select a location from the suggestions',
                    confirmButtonColor: '#ff5e00'
                });
                return;
            }
            
            mobileBooking.destination = place.formatted_address;
            mobileBooking.destLat = place.geometry.location.lat();
            mobileBooking.destLng = place.geometry.location.lng();
            mobileBooking.destPlaceId = place.place_id;
            
            document.getElementById('destination-lat-mobile').value = mobileBooking.destLat;
            document.getElementById('destination-lng-mobile').value = mobileBooking.destLng;
            document.getElementById('destination-place-id-mobile').value = mobileBooking.destPlaceId;
            document.getElementById('destination-address-mobile').value = mobileBooking.destination;
            
            console.log('✅ Destination selected:', {
                address: place.formatted_address,
                lat: mobileBooking.destLat,
                lng: mobileBooking.destLng,
                placeId: place.place_id
            });
            
            if (mobileBooking.pickupLat) {
                calculateFare('mobile');
            }
            updateMobileButtonState();
        });
    }
    
    // Initialize Desktop Autocomplete
    initDesktopAutocomplete();
    
    // Setup quick locations
    setupQuickLocations();
}

function initDesktopAutocomplete() {
    const pickupDesktop = document.getElementById('pickup-desktop');
    const destDesktop = document.getElementById('destination-desktop');
    
    if (pickupDesktop) {
        pickupAutocompleteDesktop = new google.maps.places.Autocomplete(pickupDesktop, {
            componentRestrictions: { country: 'ng' },
            fields: ['place_id', 'geometry', 'formatted_address', 'name']
        });
        
        pickupAutocompleteDesktop.addListener('place_changed', () => {
            const place = pickupAutocompleteDesktop.getPlace();
            
            if (!place.geometry) {
                Swal.fire({
                    icon: 'error',
                    title: 'Invalid Location',
                    text: 'Please select a location from the suggestions',
                    confirmButtonColor: '#ff5e00'
                });
                return;
            }
            
            desktopBooking.pickup = place.formatted_address;
            desktopBooking.pickupLat = place.geometry.location.lat();
            desktopBooking.pickupLng = place.geometry.location.lng();
            desktopBooking.pickupPlaceId = place.place_id;
            
            document.getElementById('pickup-lat-desktop').value = desktopBooking.pickupLat;
            document.getElementById('pickup-lng-desktop').value = desktopBooking.pickupLng;
            document.getElementById('pickup-place-id-desktop').value = desktopBooking.pickupPlaceId;
            document.getElementById('pickup-address-desktop').value = desktopBooking.pickup;
            
            if (desktopBooking.destLat) {
                calculateFare('desktop');
            }
            updateDesktopButtonState();
        });
    }
    
    if (destDesktop) {
        destAutocompleteDesktop = new google.maps.places.Autocomplete(destDesktop, {
            componentRestrictions: { country: 'ng' },
            fields: ['place_id', 'geometry', 'formatted_address', 'name']
        });
        
        destAutocompleteDesktop.addListener('place_changed', () => {
            const place = destAutocompleteDesktop.getPlace();
            
            if (!place.geometry) {
                Swal.fire({
                    icon: 'error',
                    title: 'Invalid Location',
                    text: 'Please select a location from the suggestions',
                    confirmButtonColor: '#ff5e00'
                });
                return;
            }
            
            desktopBooking.destination = place.formatted_address;
            desktopBooking.destLat = place.geometry.location.lat();
            desktopBooking.destLng = place.geometry.location.lng();
            desktopBooking.destPlaceId = place.place_id;
            
            document.getElementById('destination-lat-desktop').value = desktopBooking.destLat;
            document.getElementById('destination-lng-desktop').value = desktopBooking.destLng;
            document.getElementById('destination-place-id-desktop').value = desktopBooking.destPlaceId;
            document.getElementById('destination-address-desktop').value = desktopBooking.destination;
            
            if (desktopBooking.pickupLat) {
                calculateFare('desktop');
            }
            updateDesktopButtonState();
        });
    }
}

// ==================== SETUP QUICK LOCATIONS ====================
function setupQuickLocations() {
    const mobileContainer = document.getElementById('quick-locations-mobile');
    const desktopContainer = document.getElementById('quick-locations-desktop');
    
    if (mobileContainer) {
        popularLocations.forEach(loc => {
            const btn = document.createElement('button');
            btn.className = 'quick-location-btn';
            btn.innerHTML = `<i class="fas fa-${loc.icon}"></i> ${loc.name}`;
            btn.onclick = () => geocodeAddress(loc.address, 'pickup', 'mobile');
            mobileContainer.appendChild(btn);
        });
    }
    
    if (desktopContainer) {
        popularLocations.forEach(loc => {
            const btn = document.createElement('div');
            btn.className = 'saved-location-card';
            btn.innerHTML = `
                <i class="fas fa-${loc.icon}"></i>
                <h4>${loc.name}</h4>
                <p>${loc.address.substring(0, 20)}...</p>
            `;
            btn.onclick = () => geocodeAddress(loc.address, 'pickup', 'desktop');
            desktopContainer.appendChild(btn);
        });
    }
}

// ==================== USE SAVED LOCATION ====================
function useSavedLocation(address, type, view) {
    geocodeAddress(address, type, view);
}

// ==================== GEOCODE ADDRESS (for quick buttons) ====================
function geocodeAddress(address, type, view) {
    Swal.fire({
        title: 'Finding location...',
        text: 'Please wait',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    const geocoder = new google.maps.Geocoder();
    
    geocoder.geocode({ address: address, componentRestrictions: { country: 'NG' } }, (results, status) => {
        Swal.close();
        
        if (status === 'OK' && results[0]) {
            const place = results[0];
            
            if (view === 'mobile') {
                if (type === 'pickup' || type === 'home' || type === 'Home') {
                    document.getElementById('pickup-mobile').value = place.formatted_address;
                    mobileBooking.pickup = place.formatted_address;
                    mobileBooking.pickupLat = place.geometry.location.lat();
                    mobileBooking.pickupLng = place.geometry.location.lng();
                    
                    document.getElementById('pickup-lat-mobile').value = mobileBooking.pickupLat;
                    document.getElementById('pickup-lng-mobile').value = mobileBooking.pickupLng;
                } else {
                    document.getElementById('destination-mobile').value = place.formatted_address;
                    mobileBooking.destination = place.formatted_address;
                    mobileBooking.destLat = place.geometry.location.lat();
                    mobileBooking.destLng = place.geometry.location.lng();
                    
                    document.getElementById('destination-lat-mobile').value = mobileBooking.destLat;
                    document.getElementById('destination-lng-mobile').value = mobileBooking.destLng;
                }
                
                Swal.fire({
                    icon: 'success',
                    title: 'Location Found!',
                    text: place.formatted_address.substring(0, 50) + '...',
                    timer: 2000,
                    showConfirmButton: false
                });
                
                if (mobileBooking.pickupLat && mobileBooking.destLat) {
                    calculateFare('mobile');
                }
                updateMobileButtonState();
            } else {
                if (type === 'pickup' || type === 'home' || type === 'Home') {
                    document.getElementById('pickup-desktop').value = place.formatted_address;
                    desktopBooking.pickup = place.formatted_address;
                    desktopBooking.pickupLat = place.geometry.location.lat();
                    desktopBooking.pickupLng = place.geometry.location.lng();
                    
                    document.getElementById('pickup-lat-desktop').value = desktopBooking.pickupLat;
                    document.getElementById('pickup-lng-desktop').value = desktopBooking.pickupLng;
                } else {
                    document.getElementById('destination-desktop').value = place.formatted_address;
                    desktopBooking.destination = place.formatted_address;
                    desktopBooking.destLat = place.geometry.location.lat();
                    desktopBooking.destLng = place.geometry.location.lng();
                    
                    document.getElementById('destination-lat-desktop').value = desktopBooking.destLat;
                    document.getElementById('destination-lng-desktop').value = desktopBooking.destLng;
                }
                
                Swal.fire({
                    icon: 'success',
                    title: 'Location Found!',
                    text: place.formatted_address.substring(0, 50) + '...',
                    timer: 2000,
                    showConfirmButton: false
                });
                
                if (desktopBooking.pickupLat && desktopBooking.destLat) {
                    calculateFare('desktop');
                }
                updateDesktopButtonState();
            }
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Location Not Found',
                text: 'Could not find this location. Please try typing it manually.',
                confirmButtonColor: '#ff5e00'
            });
        }
    });
}

// ==================== FARE CALCULATION ====================
function calculateFare(view) {
    let booking, pickupLat, pickupLng, destLat, destLng, plan;
    
    if (view === 'mobile') {
        booking = mobileBooking;
        pickupLat = mobileBooking.pickupLat;
        pickupLng = mobileBooking.pickupLng;
        destLat = mobileBooking.destLat;
        destLng = mobileBooking.destLng;
        plan = mobileBooking.plan || 'economy';
    } else {
        booking = desktopBooking;
        pickupLat = desktopBooking.pickupLat;
        pickupLng = desktopBooking.pickupLng;
        destLat = desktopBooking.destLat;
        destLng = desktopBooking.destLng;
        plan = desktopBooking.plan || 'economy';
    }
    
    if (!pickupLat || !pickupLng || !destLat || !destLng) {
        console.log('Missing coordinates for fare calculation');
        return;
    }
    
    console.log('Calculating fare with:', {pickupLat, pickupLng, destLat, destLng, plan});
    
    const formData = new FormData();
    formData.append('pickup_lat', pickupLat);
    formData.append('pickup_lng', pickupLng);
    formData.append('dest_lat', destLat);
    formData.append('dest_lng', destLng);
    formData.append('ride_type', plan);
    
    fetch('SERVER/API/calculate_fare.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            booking.distance = data.distance;
            booking.fare = data.fare;
            
            if (view === 'mobile') {
                document.getElementById('distance-mobile').textContent = data.distance.toFixed(1) + ' km';
                document.getElementById('rate-mobile').textContent = '₦' + (plan === 'economy' ? '1,000' : '1,500');
                document.getElementById('base-fare-mobile').textContent = '₦' + data.base_fare.toLocaleString();
                document.getElementById('total-fare-mobile').textContent = '₦' + data.fare.toLocaleString();
                document.getElementById('fare-summary-mobile').style.display = 'block';
                
                // Find nearby drivers
                findNearbyDrivers(pickupLat, pickupLng, plan, 'mobile');
            } else {
                document.getElementById('distance-desktop').textContent = data.distance.toFixed(1) + ' km';
                document.getElementById('rate-desktop').textContent = '₦' + (plan === 'economy' ? '1,000' : '1,500');
                document.getElementById('base-fare-desktop').textContent = '₦' + data.base_fare.toLocaleString();
                document.getElementById('total-fare-desktop').textContent = '₦' + data.fare.toLocaleString();
                document.getElementById('fare-summary-desktop').style.display = 'block';
                
                // Find nearby drivers
                findNearbyDrivers(pickupLat, pickupLng, plan, 'desktop');
            }
        } else {
            console.error('Fare calculation failed:', data);
            Swal.fire({
                icon: 'error',
                title: 'Calculation Failed',
                text: 'Could not calculate fare. Please try again.',
                confirmButtonColor: '#ff5e00'
            });
        }
    })
    .catch(error => {
        console.error('Error calculating fare:', error);
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Failed to calculate fare. Please try again.',
            confirmButtonColor: '#ff5e00'
        });
    });
}

// ==================== FIND NEARBY DRIVERS ====================
function findNearbyDrivers(lat, lng, rideType, view) {
    console.log('Finding drivers with:', {lat, lng, rideType});
    
    fetch(`SERVER/API/get_nearby_drivers.php?lat=${lat}&lng=${lng}&ride_type=${rideType}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.drivers && data.drivers.length > 0) {
                displayDrivers(data.drivers, view);
                if (view === 'mobile') {
                    document.getElementById('driver-status-mobile').textContent = `${data.drivers.length} drivers available nearby`;
                } else {
                    document.getElementById('driver-status-desktop').textContent = `${data.drivers.length} drivers available nearby`;
                }
            } else {
                // Show mock drivers as fallback
                showMockDrivers(view);
            }
        })
        .catch(error => {
            console.error('Error finding drivers:', error);
            // Show mock drivers as fallback
            showMockDrivers(view);
        });
}

// ==================== SHOW MOCK DRIVERS AS FALLBACK ====================
function showMockDrivers(view) {
    const mockDrivers = [
        { id: 'driver1', name: 'Michael Chen', rating: 4.8, rides: 1242, distance: (Math.random() * 3 + 1).toFixed(1), vehicle: 'Black Toyota Prius', plate: 'LAG123AB' },
        { id: 'driver2', name: 'Sarah Johnson', rating: 5.0, rides: 892, distance: (Math.random() * 5 + 2).toFixed(1), vehicle: 'White Honda Civic', plate: 'LAG456CD' },
        { id: 'driver3', name: 'James Wilson', rating: 4.9, rides: 2156, distance: (Math.random() * 2 + 1).toFixed(1), vehicle: 'Silver Toyota Camry', plate: 'LAG789EF' },
        { id: 'driver4', name: 'Emma Davis', rating: 4.9, rides: 1876, distance: (Math.random() * 4 + 1).toFixed(1), vehicle: 'Blue Hyundai Elantra', plate: 'LAG321FG' }
    ];
    
    displayDrivers(mockDrivers, view);
    
    if (view === 'mobile') {
        document.getElementById('driver-status-mobile').textContent = '4 drivers available';
    } else {
        document.getElementById('driver-status-desktop').textContent = '4 drivers available';
    }
}

// ==================== DISPLAY DRIVERS ====================
function displayDrivers(drivers, view) {
    const container = view === 'mobile' ? 
        document.getElementById('drivers-list-mobile') : 
        document.getElementById('drivers-list-desktop');
    
    if (!container) return;
    
    container.innerHTML = '';
    
    drivers.forEach(driver => {
        const stars = '★'.repeat(Math.floor(driver.rating)) + '☆'.repeat(5 - Math.floor(driver.rating));
        const div = document.createElement('div');
        div.className = view === 'mobile' ? 'driver-card-mobile' : 'driver-card-desktop';
        div.setAttribute('data-driver-id', driver.id);
        
        if (view === 'mobile') {
            div.innerHTML = `
                <div class="driver-info" onclick="selectDriver('${driver.id}', 'mobile', this)">
                    <div class="driver-avatar">${driver.name.charAt(0)}</div>
                    <div class="driver-details">
                        <h4>${driver.name}</h4>
                        <div class="driver-rating">${stars} <span>${driver.rating}</span></div>
                        <div style="font-size: 14px;"><i class="fas fa-car"></i> ${driver.vehicle}</div>
                    </div>
                </div>
                <div class="driver-stats">
                    <div class="stat-item"><span class="stat-value">${driver.distance}</span><span class="stat-label">km away</span></div>
                    <div class="stat-item"><span class="stat-value">${driver.rating}</span><span class="stat-label">rating</span></div>
                    <div class="stat-item"><span class="stat-value">${driver.rides}+</span><span class="stat-label">rides</span></div>
                </div>
            `;
        } else {
            div.innerHTML = `
                <div class="driver-info-desktop" onclick="selectDriver('${driver.id}', 'desktop', this)">
                    <div class="driver-avatar-desktop">${driver.name.charAt(0)}</div>
                    <div class="driver-details-desktop">
                        <h4>${driver.name}</h4>
                        <div class="driver-rating-desktop">${stars} <span>${driver.rating} (${driver.rides}+ rides)</span></div>
                        <div class="driver-car-desktop"><i class="fas fa-car"></i> ${driver.vehicle} • ${driver.plate}</div>
                    </div>
                </div>
                <div class="driver-stats-desktop">
                    <div class="stat-item-desktop"><span class="stat-value-desktop">${driver.distance}</span><span class="stat-label-desktop">km away</span></div>
                    <div class="stat-item-desktop"><span class="stat-value-desktop">${driver.rating}</span><span class="stat-label-desktop">rating</span></div>
                    <div class="stat-item-desktop"><span class="stat-value-desktop">${driver.rides}+</span><span class="stat-label-desktop">rides</span></div>
                </div>
            `;
        }
        
        container.appendChild(div);
    });
}

// ==================== SELECT DRIVER ====================
function selectDriver(driverId, view, element) {
    const card = element.closest(view === 'mobile' ? '.driver-card-mobile' : '.driver-card-desktop');
    if (!card) return;
    
    if (view === 'mobile') {
        document.querySelectorAll('#drivers-list-mobile .driver-card-mobile').forEach(c => c.classList.remove('selected'));
        card.classList.add('selected');
        mobileBooking.driverId = driverId;
        updateMobileButtonState();
    } else {
        document.querySelectorAll('#drivers-list-desktop .driver-card-desktop').forEach(c => c.classList.remove('selected'));
        card.classList.add('selected');
        desktopBooking.driverId = driverId;
        updateDesktopButtonState();
    }
}

// ==================== MOBILE FUNCTIONS ====================
function updateMobileStep(step) {
    document.querySelectorAll('.booking-section').forEach(s => s.classList.remove('active'));
    document.getElementById(`step${step}-mobile`).classList.add('active');
    
    document.querySelectorAll('.step-mobile').forEach(s => s.classList.remove('active'));
    document.querySelector(`.step-mobile[data-step="${step}"]`).classList.add('active');
    
    const actionBtn = document.getElementById('mobile-action-btn');
    const icon = actionBtn.querySelector('i');
    const text = actionBtn.querySelector('span');
    
    switch(step) {
        case 1:
            text.textContent = 'Set Pickup & Destination';
            icon.className = 'fas fa-arrow-right';
            break;
        case 2:
            text.textContent = 'Choose Ride Plan';
            icon.className = 'fas fa-car';
            break;
        case 3:
            text.textContent = 'Select Driver';
            icon.className = 'fas fa-user-tie';
            break;
        case 4:
            text.textContent = 'Make Payment';
            icon.className = 'fas fa-credit-card';
            break;
    }
    
    updateMobileButtonState();
}

function updateMobileButtonState() {
    const actionBtn = document.getElementById('mobile-action-btn');
    if (!actionBtn) return;
    
    switch(mobileStep) {
        case 1:
            actionBtn.disabled = !(mobileBooking.pickupLat && mobileBooking.destLat);
            break;
        case 2:
            actionBtn.disabled = !mobileBooking.plan;
            break;
        case 3:
            actionBtn.disabled = !mobileBooking.driverId;
            break;
        case 4:
            if (mobileBooking.payment) {
                actionBtn.innerHTML = '<i class="fas fa-check"></i><span>Confirm & Book Ride</span>';
                actionBtn.onclick = confirmMobileBooking;
                actionBtn.disabled = false;
            } else {
                actionBtn.disabled = true;
            }
            break;
    }
}

function nextStepMobile() {
    if (mobileStep < 4) {
        mobileStep++;
        updateMobileStep(mobileStep);
    }
}

function goBackMobile() {
    if (mobileStep > 1) {
        mobileStep--;
        updateMobileStep(mobileStep);
    }
}

// Plan selection
document.querySelectorAll('.plan-card-mobile').forEach(card => {
    card.addEventListener('click', function() {
        document.querySelectorAll('.plan-card-mobile').forEach(c => c.classList.remove('selected'));
        this.classList.add('selected');
        mobileBooking.plan = this.dataset.plan;
        
        if (mobileBooking.pickupLat && mobileBooking.destLat) {
            calculateFare('mobile');
        }
        updateMobileButtonState();
    });
});

// Payment selection
document.querySelectorAll('.payment-option').forEach(option => {
    option.addEventListener('click', function() {
        document.querySelectorAll('.payment-option').forEach(o => o.classList.remove('selected'));
        this.classList.add('selected');
        mobileBooking.payment = this.dataset.payment;
        updateMobileButtonState();
    });
});

// ==================== DESKTOP FUNCTIONS ====================
function updateDesktopStep(step) {
    document.querySelectorAll('.booking-section-desktop').forEach(s => s.classList.remove('active'));
    document.getElementById(`step${step}-desktop`).classList.add('active');
    
    document.querySelectorAll('.step-desktop').forEach(s => s.classList.remove('active'));
    document.querySelector(`.step-desktop[data-step="${step}"]`).classList.add('active');
    
    const actionBtn = document.getElementById('desktop-action-btn');
    const icon = actionBtn.querySelector('i');
    const text = actionBtn.querySelector('span');
    
    switch(step) {
        case 1:
            text.textContent = 'Set Pickup & Destination';
            icon.className = 'fas fa-arrow-right';
            break;
        case 2:
            text.textContent = 'Choose Ride Plan';
            icon.className = 'fas fa-car';
            break;
        case 3:
            text.textContent = 'Select Driver';
            icon.className = 'fas fa-user-tie';
            break;
        case 4:
            text.textContent = 'Make Payment';
            icon.className = 'fas fa-credit-card';
            break;
    }
    
    updateDesktopButtonState();
}

function updateDesktopButtonState() {
    const actionBtn = document.getElementById('desktop-action-btn');
    if (!actionBtn) return;
    
    switch(desktopStep) {
        case 1:
            actionBtn.disabled = !(desktopBooking.pickupLat && desktopBooking.destLat);
            break;
        case 2:
            actionBtn.disabled = !desktopBooking.plan;
            break;
        case 3:
            actionBtn.disabled = !desktopBooking.driverId;
            break;
        case 4:
            if (desktopBooking.payment) {
                actionBtn.innerHTML = '<i class="fas fa-check"></i><span>Confirm & Book Ride</span>';
                actionBtn.onclick = confirmDesktopBooking;
                actionBtn.disabled = false;
            } else {
                actionBtn.disabled = true;
            }
            break;
    }
}

function nextStepDesktop() {
    if (desktopStep < 4) {
        desktopStep++;
        updateDesktopStep(desktopStep);
    }
}

function goBackDesktop() {
    if (desktopStep > 1) {
        desktopStep--;
        updateDesktopStep(desktopStep);
    }
}

// Plan selection for desktop
document.querySelectorAll('.plan-card-desktop').forEach(card => {
    card.addEventListener('click', function() {
        document.querySelectorAll('.plan-card-desktop').forEach(c => c.classList.remove('selected'));
        this.classList.add('selected');
        desktopBooking.plan = this.dataset.plan;
        
        if (desktopBooking.pickupLat && desktopBooking.destLat) {
            calculateFare('desktop');
        }
        updateDesktopButtonState();
    });
});

// Payment selection for desktop
document.querySelectorAll('.payment-card-desktop').forEach(card => {
    card.addEventListener('click', function() {
        document.querySelectorAll('.payment-card-desktop').forEach(c => c.classList.remove('selected'));
        this.classList.add('selected');
        desktopBooking.payment = this.dataset.payment;
        updateDesktopButtonState();
    });
});

// ==================== CONFIRM BOOKING ====================
function confirmMobileBooking() {
    if (!mobileBooking.pickup || !mobileBooking.destination || !mobileBooking.plan || !mobileBooking.driverId || !mobileBooking.payment) {
        Swal.fire({
            icon: 'error',
            title: 'Incomplete Booking',
            text: 'Please complete all steps',
            confirmButtonColor: '#ff5e00'
        });
        return;
    }
    
    if (!mobileBooking.pickupLat || !mobileBooking.pickupLng || !mobileBooking.destLat || !mobileBooking.destLng) {
        Swal.fire({
            icon: 'error',
            title: 'Invalid Locations',
            text: 'Please select valid locations from the suggestions',
            confirmButtonColor: '#ff5e00'
        });
        return;
    }
    
    // Show loading
    Swal.fire({
        title: 'Booking your ride...',
        text: 'Please wait',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    const formData = new FormData();
    formData.append('pickup_address', mobileBooking.pickup);
    formData.append('pickup_lat', mobileBooking.pickupLat);
    formData.append('pickup_lng', mobileBooking.pickupLng);
    formData.append('pickup_place_id', mobileBooking.pickupPlaceId);
    formData.append('dest_address', mobileBooking.destination);
    formData.append('dest_lat', mobileBooking.destLat);
    formData.append('dest_lng', mobileBooking.destLng);
    formData.append('dest_place_id', mobileBooking.destPlaceId);
    formData.append('distance', mobileBooking.distance);
    formData.append('fare', mobileBooking.fare);
    formData.append('driver_id', mobileBooking.driverId);
    formData.append('ride_type', mobileBooking.plan);
    
    fetch('SERVER/API/book_ride.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        Swal.close();
        
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Ride Booked!',
                text: `New balance: ₦${data.balance.toLocaleString()}`,
                confirmButtonColor: '#ff5e00'
            }).then(() => {
                window.location.href = 'ride_history.php';
            });
        } else if (data.redirect) {
            Swal.fire({
                icon: 'warning',
                title: 'Insufficient Balance',
                html: `You need ₦${data.shortage.toLocaleString()} more.`,
                showCancelButton: true,
                confirmButtonText: 'Add Funds',
                cancelButtonText: 'Cancel',
                confirmButtonColor: '#ff5e00'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = data.redirect;
                }
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Booking Failed',
                text: data.message,
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
            text: 'Failed to book ride',
            confirmButtonColor: '#ff5e00'
        });
    });
}

function confirmDesktopBooking() {
    if (!desktopBooking.pickup || !desktopBooking.destination || !desktopBooking.plan || !desktopBooking.driverId || !desktopBooking.payment) {
        Swal.fire({
            icon: 'error',
            title: 'Incomplete Booking',
            text: 'Please complete all steps',
            confirmButtonColor: '#ff5e00'
        });
        return;
    }
    
    if (!desktopBooking.pickupLat || !desktopBooking.pickupLng || !desktopBooking.destLat || !desktopBooking.destLng) {
        Swal.fire({
            icon: 'error',
            title: 'Invalid Locations',
            text: 'Please select valid locations from the suggestions',
            confirmButtonColor: '#ff5e00'
        });
        return;
    }
    
    // Show loading
    Swal.fire({
        title: 'Booking your ride...',
        text: 'Please wait',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    const formData = new FormData();
    formData.append('pickup_address', desktopBooking.pickup);
    formData.append('pickup_lat', desktopBooking.pickupLat);
    formData.append('pickup_lng', desktopBooking.pickupLng);
    formData.append('pickup_place_id', desktopBooking.pickupPlaceId);
    formData.append('dest_address', desktopBooking.destination);
    formData.append('dest_lat', desktopBooking.destLat);
    formData.append('dest_lng', desktopBooking.destLng);
    formData.append('dest_place_id', desktopBooking.destPlaceId);
    formData.append('distance', desktopBooking.distance);
    formData.append('fare', desktopBooking.fare);
    formData.append('driver_id', desktopBooking.driverId);
    formData.append('ride_type', desktopBooking.plan);
    
    fetch('SERVER/API/book_ride.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        Swal.close();
        
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Ride Booked!',
                text: `New balance: ₦${data.balance.toLocaleString()}`,
                confirmButtonColor: '#ff5e00'
            }).then(() => {
                window.location.href = 'ride_history.php';
            });
        } else if (data.redirect) {
            Swal.fire({
                icon: 'warning',
                title: 'Insufficient Balance',
                html: `You need ₦${data.shortage.toLocaleString()} more.`,
                showCancelButton: true,
                confirmButtonText: 'Add Funds',
                cancelButtonText: 'Cancel',
                confirmButtonColor: '#ff5e00'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = data.redirect;
                }
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Booking Failed',
                text: data.message,
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
            text: 'Failed to book ride',
            confirmButtonColor: '#ff5e00'
        });
    });
}

// ==================== INIT ====================
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
    
    // Initialize steps
    updateMobileStep(1);
    updateDesktopStep(1);
});

function checkNotifications() {
    Swal.fire({
        icon: 'info',
        title: 'Notifications',
        html: '<p>🚗 20% off your next ride</p><p>💰 Add funds get 10% bonus</p><p>⭐ 2 pending ratings</p>',
        confirmButtonColor: '#ff5e00'
    });
}