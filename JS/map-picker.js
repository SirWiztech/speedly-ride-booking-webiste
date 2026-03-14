// ==================== MAP PICKER FUNCTIONALITY ====================
// This file handles the map-based location selection for booking rides

let map, pickupMarker, destMarker;
let pickupLocation = null;
let destLocation = null;
let currentMode = 'pickup'; // 'pickup' or 'destination'
let geocoder;
let placesService;
let autocomplete;

// Initialize the map
function initMapPicker(mapElementId, initialCenter = { lat: 6.5244, lng: 3.3792 }) {
    const mapElement = document.getElementById(mapElementId);
    if (!mapElement) return null;
    
    // Create map
    map = new google.maps.Map(mapElement, {
        center: initialCenter,
        zoom: 13,
        mapTypeId: google.maps.MapTypeId.ROADMAP,
        mapTypeControl: false,
        streetViewControl: false,
        fullscreenControl: true,
        zoomControl: true,
        styles: [
            {
                featureType: "poi",
                elementType: "labels",
                stylers: [{ visibility: "on" }]
            },
            {
                featureType: "road",
                elementType: "labels",
                stylers: [{ visibility: "on" }]
            }
        ]
    });
    
    // Initialize geocoder
    geocoder = new google.maps.Geocoder();
    
    // Add click listener
    map.addListener('click', function(e) {
        handleMapClick(e.latLng);
    });
    
    return map;
}

// Handle map click
function handleMapClick(latLng) {
    const lat = latLng.lat();
    const lng = latLng.lng();
    
    showLoading();
    
    // Reverse geocode to get address
    geocoder.geocode({ location: { lat, lng } }, (results, status) => {
        hideLoading();
        
        let address = '';
        let placeId = '';
        
        if (status === 'OK' && results && results[0]) {
            address = results[0].formatted_address;
            placeId = results[0].place_id;
        } else {
            address = `${lat.toFixed(6)}, ${lng.toFixed(6)}`;
        }
        
        if (currentMode === 'pickup') {
            setPickupLocation(lat, lng, address, placeId);
        } else {
            setDestinationLocation(lat, lng, address, placeId);
        }
    });
}

// Set pickup location
function setPickupLocation(lat, lng, address, placeId) {
    // Remove existing pickup marker
    if (pickupMarker) {
        pickupMarker.setMap(null);
    }
    
    // Create new marker
    pickupMarker = new google.maps.Marker({
        position: { lat, lng },
        map: map,
        title: 'Pickup Location',
        icon: {
            path: google.maps.SymbolPath.CIRCLE,
            scale: 12,
            fillColor: '#4CAF50',
            fillOpacity: 1,
            strokeColor: '#ffffff',
            strokeWeight: 3,
            labelOrigin: new google.maps.Point(0, -10)
        },
        label: {
            text: 'P',
            color: 'white',
            fontSize: '12px',
            fontWeight: 'bold'
        },
        animation: google.maps.Animation.DROP
    });
    
    pickupLocation = { lat, lng, address, placeId };
    
    // Update UI
    updatePickupCard(address, lat, lng);
    
    // If both locations are set, draw route
    if (pickupLocation && destLocation) {
        drawRoute();
        calculateFare();
    }
    
    // Auto-switch to destination mode after setting pickup
    setTimeout(() => {
        setMode('destination');
    }, 500);
}

// Set destination location
function setDestinationLocation(lat, lng, address, placeId) {
    // Remove existing destination marker
    if (destMarker) {
        destMarker.setMap(null);
    }
    
    // Create new marker
    destMarker = new google.maps.Marker({
        position: { lat, lng },
        map: map,
        title: 'Destination',
        icon: {
            path: google.maps.SymbolPath.CIRCLE,
            scale: 12,
            fillColor: '#F44336',
            fillOpacity: 1,
            strokeColor: '#ffffff',
            strokeWeight: 3,
            labelOrigin: new google.maps.Point(0, -10)
        },
        label: {
            text: 'D',
            color: 'white',
            fontSize: '12px',
            fontWeight: 'bold'
        },
        animation: google.maps.Animation.DROP
    });
    
    destLocation = { lat, lng, address, placeId };
    
    // Update UI
    updateDestinationCard(address, lat, lng);
    
    // If both locations are set, draw route
    if (pickupLocation && destLocation) {
        drawRoute();
        calculateFare();
    }
}

// Draw route between pickup and destination
function drawRoute() {
    if (!pickupLocation || !destLocation || !map) return;
    
    const directionsService = new google.maps.DirectionsService();
    const directionsRenderer = new google.maps.DirectionsRenderer({
        map: map,
        suppressMarkers: true,
        polylineOptions: {
            strokeColor: '#ff5e00',
            strokeWeight: 5,
            strokeOpacity: 0.7
        }
    });
    
    directionsService.route({
        origin: { lat: pickupLocation.lat, lng: pickupLocation.lng },
        destination: { lat: destLocation.lat, lng: destLocation.lng },
        travelMode: google.maps.TravelMode.DRIVING
    }, (result, status) => {
        if (status === 'OK') {
            directionsRenderer.setDirections(result);
            
            // Get distance from route
            if (result.routes && result.routes[0] && result.routes[0].legs && result.routes[0].legs[0]) {
                const distance = result.routes[0].legs[0].distance.value / 1000; // Convert to km
                updateDistance(distance);
            }
        }
    });
}

// Calculate fare based on distance and ride type
function calculateFare() {
    if (!pickupLocation || !destLocation) return;
    
    const distance = calculateDistance(
        pickupLocation.lat, pickupLocation.lng,
        destLocation.lat, destLocation.lng
    );
    
    // Get selected ride type
    const rideType = document.querySelector('input[name="ride-type"]:checked')?.value || 'economy';
    const ratePerKm = rideType === 'economy' ? 1000 : 1500;
    const baseFare = 500;
    const totalFare = (distance * ratePerKm) + baseFare;
    
    updateFare(distance, totalFare);
}

// Calculate distance using Haversine formula
function calculateDistance(lat1, lon1, lat2, lon2) {
    const R = 6371; // Radius of the earth in km
    const dLat = deg2rad(lat2 - lat1);
    const dLon = deg2rad(lon2 - lon1);
    const a = 
        Math.sin(dLat/2) * Math.sin(dLat/2) +
        Math.cos(deg2rad(lat1)) * Math.cos(deg2rad(lat2)) * 
        Math.sin(dLon/2) * Math.sin(dLon/2); 
    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a)); 
    const distance = R * c; // Distance in km
    return Math.max(distance, 1); // Minimum 1km
}

function deg2rad(deg) {
    return deg * (Math.PI/180);
}

// Update UI functions
function updatePickupCard(address, lat, lng) {
    const card = document.getElementById('pickup-card');
    const addressEl = document.getElementById('pickup-address');
    const coordsEl = document.getElementById('pickup-coords');
    
    if (card && addressEl && coordsEl) {
        addressEl.textContent = address;
        coordsEl.textContent = `${lat.toFixed(6)}, ${lng.toFixed(6)}`;
        card.style.display = 'block';
    }
    
    // Update hidden inputs
    const latInput = document.getElementById('pickup-lat');
    const lngInput = document.getElementById('pickup-lng');
    const addrInput = document.getElementById('pickup-address-input');
    
    if (latInput) latInput.value = lat;
    if (lngInput) lngInput.value = lng;
    if (addrInput) addrInput.value = address;
}

function updateDestinationCard(address, lat, lng) {
    const card = document.getElementById('destination-card');
    const addressEl = document.getElementById('destination-address');
    const coordsEl = document.getElementById('destination-coords');
    
    if (card && addressEl && coordsEl) {
        addressEl.textContent = address;
        coordsEl.textContent = `${lat.toFixed(6)}, ${lng.toFixed(6)}`;
        card.style.display = 'block';
    }
    
    // Update hidden inputs
    const latInput = document.getElementById('destination-lat');
    const lngInput = document.getElementById('destination-lng');
    const addrInput = document.getElementById('destination-address-input');
    
    if (latInput) latInput.value = lat;
    if (lngInput) lngInput.value = lng;
    if (addrInput) addrInput.value = address;
}

function updateDistance(distance) {
    const distanceEl = document.getElementById('distance-display');
    if (distanceEl) {
        distanceEl.textContent = distance.toFixed(1) + ' km';
    }
}

function updateFare(distance, fare) {
    const fareEl = document.getElementById('fare-display');
    if (fareEl) {
        fareEl.textContent = '₦' + fare.toFixed(2);
    }
}

// Set mode (pickup/destination)
function setMode(mode) {
    currentMode = mode;
    
    const pickupBtn = document.getElementById('pickup-mode-btn');
    const destBtn = document.getElementById('destination-mode-btn');
    
    if (pickupBtn && destBtn) {
        if (mode === 'pickup') {
            pickupBtn.classList.add('active');
            destBtn.classList.remove('active');
        } else {
            pickupBtn.classList.remove('active');
            destBtn.classList.add('active');
        }
    }
}

// Clear locations
function clearPickup() {
    if (pickupMarker) {
        pickupMarker.setMap(null);
        pickupMarker = null;
    }
    pickupLocation = null;
    
    const card = document.getElementById('pickup-card');
    if (card) card.style.display = 'none';
}

function clearDestination() {
    if (destMarker) {
        destMarker.setMap(null);
        destMarker = null;
    }
    destLocation = null;
    
    const card = document.getElementById('destination-card');
    if (card) card.style.display = 'none';
}

// Setup search autocomplete
function setupSearch(searchInputId, mapInstance) {
    const searchInput = document.getElementById(searchInputId);
    if (!searchInput || !mapInstance) return;
    
    // Try to use modern PlaceAutocompleteElement if available
    if (google.maps.places && google.maps.places.PlaceAutocompleteElement) {
        try {
            const autocomplete = new google.maps.places.PlaceAutocompleteElement({
                inputElement: searchInput,
                restrictions: {
                    country: ['ng']
                }
            });
            
            autocomplete.addEventListener('gmp-placeselect', (event) => {
                const place = event.place;
                
                place.fetchFields({
                    fields: ['displayName', 'formattedAddress', 'location', 'id']
                }).then(() => {
                    const location = place.location;
                    const lat = location.lat();
                    const lng = location.lng();
                    
                    mapInstance.setCenter({lat, lng});
                    mapInstance.setZoom(16);
                    
                    const address = place.formattedAddress || place.displayName || `${lat.toFixed(6)}, ${lng.toFixed(6)}`;
                    
                    if (currentMode === 'pickup') {
                        setPickupLocation(lat, lng, address, place.id || '');
                    } else {
                        setDestinationLocation(lat, lng, address, place.id || '');
                    }
                });
            });
            
            return;
        } catch (e) {
            console.log('PlaceAutocompleteElement not available, falling back to Autocomplete', e);
        }
    }
    
    // Fallback to legacy Autocomplete
    autocomplete = new google.maps.places.Autocomplete(searchInput, {
        componentRestrictions: { country: 'ng' },
        fields: ['place_id', 'geometry', 'formatted_address', 'name']
    });
    
    autocomplete.addListener('place_changed', () => {
        const place = autocomplete.getPlace();
        
        if (!place || !place.geometry) {
            Swal.fire('Invalid Location', 'Please select from suggestions', 'warning');
            return;
        }
        
        const location = place.geometry.location;
        const lat = location.lat();
        const lng = location.lng();
        
        mapInstance.setCenter(location);
        mapInstance.setZoom(16);
        
        if (currentMode === 'pickup') {
            setPickupLocation(lat, lng, place.formatted_address, place.place_id);
        } else {
            setDestinationLocation(lat, lng, place.formatted_address, place.place_id);
        }
    });
}

// Use saved location
function useSavedLocation(address, lat, lng, type) {
    map.setCenter({ lat: parseFloat(lat), lng: parseFloat(lng) });
    map.setZoom(16);
    
    if (currentMode === 'pickup') {
        setPickupLocation(parseFloat(lat), parseFloat(lng), address, '');
    } else {
        setDestinationLocation(parseFloat(lat), parseFloat(lng), address, '');
    }
}

// Loading indicators
function showLoading() {
    const loader = document.getElementById('map-loading');
    if (loader) loader.style.display = 'flex';
}

function hideLoading() {
    const loader = document.getElementById('map-loading');
    if (loader) loader.style.display = 'none';
}

// Export functions for use in other files
window.MapPicker = {
    initMapPicker,
    setMode,
    clearPickup,
    clearDestination,
    useSavedLocation,
    getPickupLocation: () => pickupLocation,
    getDestinationLocation: () => destLocation
};