<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>Speedly | PURE GPS - GOOGLE CLOUD MAPS</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts - Poppins -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Google Maps JavaScript API -->
    <script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyB1tM_s2w8JWfnIoUTAzJNpbblU-eZiC30&libraries=places,geometry,marker&v=weekly"></script>
    
    <!-- external css -->
     <link rel="stylesheet" href="./CSS/location.css">
</head>
<body>
    <!-- Dashboard Container -->
    <div class="dashboard-container" style=" max-width: 1700px;">

        <!-- ========== MOBILE VIEW ========== -->
        <div class="mobile-view">

            <!-- Header -->
            <div class="header">
                <div class="user-info">
                    <h1>Google Cloud Maps</h1>
                    <p id="live-status-mobile">📍 Live Location • Places • Streets • Churches</p>
                </div>
                <button class="notification-btn">
                    <i class="fas fa-bell"></i>
                    <span class="notification-badge">3</span>
                </button>
            </div>

            <!-- Location Permission Prompt (shows if denied) -->
            <div id="permission-prompt-mobile" style="display: none;" class="permission-prompt">
                <div>
                    <i class="fas fa-exclamation-triangle text-orange-500 mr-2"></i>
                    <span class="font-medium">Location access denied</span>
                    <p class="text-sm text-gray-600 mt-1">Please allow location to use GPS tracking</p>
                </div>
                <button onclick="requestLocationPermission()" class="bg-orange-500 text-white px-4 py-2 rounded-lg text-sm font-semibold">
                    Enable
                </button>
            </div>

            <!-- ===== LIVE GPS CARD ===== -->
            <div class="live-location-card">
                <div class="live-header flex flex-wrap items-center gap-3">
                    <div class="live-title flex items-center gap-2">
                        <span class="gps-pulse w-3 h-3 sm:w-4 sm:h-4" id="gps-pulse-mobile"></span>
                        <span id="gps-state-mobile" class="text-sm sm:text-base font-semibold">⚠️ WAITING FOR GPS</span>
                    </div>
                    <span class="gps-badge text-xs sm:text-sm px-3 py-1.5 sm:px-4 sm:py-2" id="gps-source-mobile">
                        <i class="fas fa-satellite-dish mr-1"></i> Google Maps GPS
                    </span>
                </div>
                
                <div class="street-address flex items-start gap-2 mt-4">
                    <i class="fas fa-location-dot text-xl sm:text-2xl flex-shrink-0 mt-1"></i>
                    <span id="street-name-mobile" class="text-base sm:text-lg md:text-xl font-bold leading-tight">
                        📍 Click "Allow" to see your exact location
                    </span>
                </div>
                
                <div class="full-address flex items-start gap-2 mt-3 pb-4 border-b border-white/20" id="full-address-mobile">
                    <i class="fas fa-map-pin text-sm sm:text-base flex-shrink-0 mt-0.5"></i> 
                    <span class="text-xs sm:text-sm opacity-95 leading-relaxed">
                        Waiting for Google Maps geolocation permission...
                    </span>
                </div>
                
                <div class="coordinate-row grid grid-cols-1 xs:grid-cols-3 gap-3 mt-4 p-4 bg-black/15 rounded-2xl backdrop-blur-sm">
                    <div class="flex items-center justify-center xs:justify-start gap-2 text-xs sm:text-sm">
                        <i class="fas fa-globe-africa text-sm sm:text-base flex-shrink-0"></i>
                        <span class="font-medium">Lat:</span>
                        <span id="latitude-mobile" class="font-mono font-bold">--</span>
                    </div>
                    <div class="flex items-center justify-center xs:justify-start gap-2 text-xs sm:text-sm">
                        <i class="fas fa-globe-americas text-sm sm:text-base flex-shrink-0"></i>
                        <span class="font-medium">Lng:</span>
                        <span id="longitude-mobile" class="font-mono font-bold">--</span>
                    </div>
                    <div class="flex items-center justify-center xs:justify-start gap-2 text-xs sm:text-sm">
                        <i class="fas fa-bullseye text-sm sm:text-base flex-shrink-0"></i>
                        <span class="font-medium">±</span>
                        <span id="accuracy-mobile" class="font-mono font-bold">--</span>
                        <span>m</span>
                    </div>
                </div>
                
                <div class="movement-stats grid grid-cols-1 xs:grid-cols-3 gap-3 mt-4">
                    <div class="stat-badge flex items-center justify-center xs:justify-start gap-2 bg-white/15 px-4 py-3 rounded-3xl backdrop-blur-sm">
                        <i class="fas fa-tachometer-alt text-sm sm:text-base"></i>
                        <span class="text-xs sm:text-sm font-medium whitespace-nowrap">Speed:</span>
                        <span id="speed-mobile" class="text-sm sm:text-base font-bold">0</span>
                        <span class="text-xs sm:text-sm">km/h</span>
                    </div>
                    
                    <div class="stat-badge flex items-center justify-center xs:justify-start gap-2 bg-white/15 px-4 py-3 rounded-3xl backdrop-blur-sm">
                        <i class="fas fa-compass text-sm sm:text-base"></i>
                        <span class="text-xs sm:text-sm font-medium whitespace-nowrap">Heading:</span>
                        <span id="heading-mobile" class="text-sm sm:text-base font-bold">--</span>
                        <span class="text-xs sm:text-sm">°</span>
                    </div>
                    
                    <div class="stat-badge flex items-center justify-center xs:justify-start gap-2 bg-white/15 px-4 py-3 rounded-3xl backdrop-blur-sm">
                        <i class="fas fa-mountain text-sm sm:text-base"></i>
                        <span class="text-xs sm:text-sm font-medium whitespace-nowrap">Alt:</span>
                        <span id="altitude-mobile" class="text-sm sm:text-base font-bold">--</span>
                        <span class="text-xs sm:text-sm">m</span>
                    </div>
                </div>
            </div>

            <!-- ===== MAP CONTAINER WITH GOOGLE MAPS ===== -->
            <div class="map-container">
                <div id="mobile-map"></div>
                <div class="map-controls">
                    <button class="map-control-btn" onclick="togglePlaces('mobile')" title="Show Nearby Places">
                        <i class="fas fa-store"></i>
                    </button>
                    <button class="map-control-btn" onclick="findNearbyChurches('mobile')" title="Find Nearby Churches">
                        <i class="fas fa-church"></i>
                    </button>
                    <button class="map-control-btn" onclick="centerOnUser('mobile')" title="Center on My Location">
                        <i class="fas fa-crosshairs"></i>
                    </button>
                </div>
                <div class="places-container" id="mobile-places"></div>
                <div class="direction-arrow" id="direction-arrow-mobile">
                    <i class="fas fa-location-arrow"></i>
                </div>
            </div>

            

            <!-- Bottom Navigation -->
            <?php
                require_once './components/mobile-nav.php';
            ?>
        </div>

        <!-- ========== DESKTOP VIEW ========== -->
        <div class="desktop-view">
            <!-- Sidebar -->
            <div class="desktop-sidebar">
                <div class="logo">
                    <img src="./main-assets/logo-no-background.png" alt="Speedly Logo" class="logo-image">

                </div>
                
                <?php
                require_once './components/desktop-nav.php';
                ?>

                
            </div>

            <!-- Main Content -->
            <div class="desktop-main">
                <div class="desktop-header flex justify-between items-center">
                    <div class="desktop-title">
                        <h1>⚡ Google Cloud Maps - All Features</h1>
                        <p id="desktop-live-status">📍 Live Location • Places • Streets • Churches</p>
                    </div>
                    <div class="desktop-actions">
                        <button class="notification-btn">
                            <i class="fas fa-bell"></i>
                            <span class="notification-badge">3</span>
                        </button>
                    </div>
                </div>

                <!-- Desktop Search -->
                <!-- <div class="search-section mt-5" style="padding: 0 0 25px 0;">
                    <div class="search-container">
                        <input type="text" class="search-input" id="desktop-search" 
                               placeholder="Search places, streets, churches...">
                        <button class="search-btn" id="desktop-search-btn">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </div> -->

                <!-- Desktop Map Container -->
                <div class="desktop-map-container mt-10 w-full">
                    <div class="desktop-map-col">
                        <div id="desktop-map"></div>
                        <div class="map-controls">
                            <button class="map-control-btn" onclick="togglePlaces('desktop')" title="Show Nearby Places">
                                <i class="fas fa-store"></i>
                            </button>
                            <button class="map-control-btn" onclick="findNearbyChurches('desktop')" title="Find Nearby Churches">
                                <i class="fas fa-church"></i>
                            </button>
                            <button class="map-control-btn" onclick="centerOnUser('desktop')" title="Center on My Location">
                                <i class="fas fa-crosshairs"></i>
                            </button>
                        </div>
                        <div class="places-container" id="desktop-places"></div>
                        <div class="direction-arrow" id="direction-arrow-desktop">
                            <i class="fas fa-location-arrow"></i>
                        </div>
                    </div>
                    <div class="desktop-location-panel">
                        <div class="flex items-center gap-3 mb-4">
                            <span class="gps-pulse" id="desktop-gps-pulse"></span>
                            <span class="font-bold text-lg" id="desktop-gps-state">⏳ Waiting for GPS permission</span>
                        </div>
                        
                        <!-- Desktop Street Address -->
                        <div class="bg-orange-50 p-5 rounded-xl mb-4">
                            <div class="font-bold text-xl text-gray-800 mb-2 flex items-center gap-2">
                                <i class="fas fa-location-dot text-orange-500"></i>
                                <span id="desktop-street">Click allow to see your location</span>
                            </div>
                            <div class="text-gray-600 text-sm" id="desktop-address">
                                Google Maps geolocation required for GPS tracking
                            </div>
                        </div>
                        
                        <!-- Desktop Coordinates Grid -->
                        <div class="grid grid-cols-2 gap-4 mb-4">
                            <div class="bg-gray-50 p-3 rounded-lg">
                                <div class="text-xs text-gray-500">LATITUDE</div>
                                <div class="font-mono font-bold text-lg" id="desktop-lat">--</div>
                            </div>
                            <div class="bg-gray-50 p-3 rounded-lg">
                                <div class="text-xs text-gray-500">LONGITUDE</div>
                                <div class="font-mono font-bold text-lg" id="desktop-lng">--</div>
                            </div>
                            <div class="bg-gray-50 p-3 rounded-lg">
                                <div class="text-xs text-gray-500">ACCURACY</div>
                                <div class="font-bold text-green-600 text-lg" id="desktop-acc">--</div>
                            </div>
                            <div class="bg-gray-50 p-3 rounded-lg">
                                <div class="text-xs text-gray-500">GPS SOURCE</div>
                                <div class="font-bold text-lg" id="desktop-source">Google Maps</div>
                            </div>
                        </div>
                        
                        <!-- Permission Button -->
                        <button onclick="requestLocationPermission()" class="w-full bg-orange-500 hover:bg-orange-600 text-white py-4 px-4 rounded-xl font-bold transition-all flex items-center justify-center gap-2">
                            <i class="fas fa-location-arrow"></i>
                            ALLOW GPS ACCESS FOR ALL FEATURES
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // ============================================================
        // GOOGLE CLOUD MAPS WITH ALL FEATURES
        // API Key: AIzaSyB1tM_s2w8JWfnIoUTAzJNpbblU-eZiC30
        // Features: Live Location, Places, Streets, Churches, Everything
        // ============================================================
        
        // ========== GLOBAL VARIABLES ==========
        let mobileMap, desktopMap;
        let mobileMarker, desktopMarker;
        let userLocation = null;
        let watchId = null;
        let hasGPSPermission = false;
        let mobilePlacesService, desktopPlacesService;
        let mobilePlacesContainer, desktopPlacesContainer;
        let mobileInfoWindow, desktopInfoWindow;
        
        // ========== INITIALIZE ON LOAD ==========
        document.addEventListener('DOMContentLoaded', function() {
            console.log('🚀 Initializing Google Cloud Maps with all features');
            
            // Initialize info windows
            mobileInfoWindow = new google.maps.InfoWindow();
            desktopInfoWindow = new google.maps.InfoWindow();
            
            // Initialize maps when Google Maps API is ready
            initMaps();
            
            // Check permission status
            checkGeolocationPermission();
        });
        
        // ========== INITIALIZE MAPS ==========
        function initMaps() {
            // Default location (Anambra, Nigeria)
            const defaultLocation = { lat: 6.2109, lng: 6.7985 };
            
            // Mobile Map
            const mobileMapEl = document.getElementById('mobile-map');
            if (mobileMapEl) {
                mobileMap = new google.maps.Map(mobileMapEl, {
                    center: defaultLocation,
                    zoom: 15,
                    mapTypeId: google.maps.MapTypeId.ROADMAP,
                    mapTypeControl: true,
                    streetViewControl: true,
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
                        },
                        {
                            featureType: "administrative",
                            elementType: "labels",
                            stylers: [{ visibility: "on" }]
                        }
                    ]
                });
                
                // Initialize Places Service
                mobilePlacesService = new google.maps.places.PlacesService(mobileMap);
                mobilePlacesContainer = document.getElementById('mobile-places');
            }
            
            // Desktop Map
            const desktopMapEl = document.getElementById('desktop-map');
            if (desktopMapEl) {
                desktopMap = new google.maps.Map(desktopMapEl, {
                    center: defaultLocation,
                    zoom: 15,
                    mapTypeId: google.maps.MapTypeId.ROADMAP,
                    mapTypeControl: true,
                    streetViewControl: true,
                    fullscreenControl: true,
                    zoomControl: true
                });
                
                // Initialize Places Service
                desktopPlacesService = new google.maps.places.PlacesService(desktopMap);
                desktopPlacesContainer = document.getElementById('desktop-places');
            }
        }
        
        // ========== CHECK PERMISSION STATUS ==========
        function checkGeolocationPermission() {
            if (!navigator.permissions) {
                requestLocationPermission();
                return;
            }
            
            navigator.permissions.query({ name: 'geolocation' }).then((result) => {
                if (result.state === 'granted') {
                    console.log('✅ GPS permission already granted');
                    hasGPSPermission = true;
                    startPureGPS();
                } else if (result.state === 'prompt') {
                    console.log('⏳ Waiting for GPS permission');
                    updateGPSError('⚠️ Please allow location access');
                } else if (result.state === 'denied') {
                    console.log('❌ GPS permission denied');
                    hasGPSPermission = false;
                    showPermissionDenied();
                }
                
                result.addEventListener('change', () => {
                    if (result.state === 'granted') {
                        console.log('✅ GPS permission now granted');
                        hasGPSPermission = true;
                        startPureGPS();
                    }
                });
            });
        }
        
        // ========== REQUEST LOCATION PERMISSION ==========
        function requestLocationPermission() {
            document.getElementById('permission-prompt-mobile').style.display = 'none';
            
            document.getElementById('gps-state-mobile').innerHTML = '🔍 REQUESTING GPS ACCESS...';
            document.getElementById('street-name-mobile').innerHTML = '📍 Please allow location in browser prompt';
            
            navigator.geolocation.getCurrentPosition(
                (position) => {
                    console.log('✅ GPS permission GRANTED');
                    hasGPSPermission = true;
                    startPureGPS();
                },
                (error) => {
                    console.error('❌ GPS permission DENIED:', error.message);
                    showPermissionDenied();
                },
                {
                    enableHighAccuracy: true,
                    timeout: 30000,
                    maximumAge: 0
                }
            );
        }
        
        // ========== SHOW PERMISSION DENIED ==========
        function showPermissionDenied() {
            document.getElementById('gps-state-mobile').innerHTML = '❌ GPS ACCESS DENIED';
            document.getElementById('street-name-mobile').innerHTML = '📍 Location blocked. Please enable in browser settings.';
            document.getElementById('gps-pulse-mobile').style.background = '#ef4444';
            document.getElementById('permission-prompt-mobile').style.display = 'flex';
            
            document.getElementById('desktop-gps-state').innerHTML = '❌ GPS ACCESS DENIED';
            document.getElementById('desktop-street').innerHTML = 'Location permission denied';
            document.getElementById('desktop-gps-pulse').style.background = '#ef4444';
        }
        
        // ========== START GPS TRACKING ==========
        function startPureGPS() {
            if (!navigator.geolocation) {
                alert('Geolocation not supported');
                return;
            }
            
            // Update UI
            document.getElementById('gps-state-mobile').innerHTML = '🟢 GPS ACTIVE - TRACKING';
            document.getElementById('gps-pulse-mobile').style.background = '#4ade80';
            document.getElementById('street-name-mobile').innerHTML = '🔴 Acquiring GPS...';
            
            document.getElementById('desktop-gps-state').innerHTML = '🟢 GPS ACTIVE - LIVE TRACKING';
            document.getElementById('desktop-gps-pulse').style.background = '#4ade80';
            
            // Get current position
            navigator.geolocation.getCurrentPosition(
                (position) => {
                    userLocation = {
                        lat: position.coords.latitude,
                        lng: position.coords.longitude,
                        accuracy: position.coords.accuracy,
                        altitude: position.coords.altitude || 0,
                        speed: position.coords.speed || 0,
                        heading: position.coords.heading || 0
                    };
                    
                    console.log('📍 Location locked:', userLocation);
                    
                    // Update UI
                    updateAllUI(position.coords);
                    
                    // Update maps
                    updateMapLocations();
                    
                    // Get address
                    reverseGeocode(position.coords.latitude, position.coords.longitude);
                    
                    // Start watching for movement
                    startWatchingPosition();
                },
                (error) => {
                    console.error('GPS Error:', error.message);
                },
                {
                    enableHighAccuracy: true,
                    timeout: 30000,
                    maximumAge: 0
                }
            );
        }
        
        // ========== WATCH POSITION ==========
        function startWatchingPosition() {
            if (watchId) {
                navigator.geolocation.clearWatch(watchId);
            }
            
            watchId = navigator.geolocation.watchPosition(
                (position) => {
                    const coords = position.coords;
                    
                    userLocation = {
                        lat: coords.latitude,
                        lng: coords.longitude,
                        accuracy: coords.accuracy,
                        altitude: coords.altitude || 0,
                        speed: coords.speed || 0,
                        heading: coords.heading || 0
                    };
                    
                    // Update UI
                    updateAllUI(coords);
                    
                    // Update markers
                    updateMapMarkers(coords);
                    
                    // Update address
                    reverseGeocode(coords.latitude, coords.longitude);
                    
                    // Update direction arrow
                    if (coords.heading) {
                        document.getElementById('direction-arrow-mobile').style.transform = `rotate(${coords.heading}deg)`;
                        document.getElementById('direction-arrow-desktop').style.transform = `rotate(${coords.heading}deg)`;
                    }
                },
                (error) => {
                    console.log('Watch error:', error.message);
                },
                {
                    enableHighAccuracy: true,
                    maximumAge: 0,
                    timeout: 10000
                }
            );
        }
        
        // ========== UPDATE ALL UI ==========
        function updateAllUI(coords) {
            const speedKmh = (coords.speed || 0) * 3.6;
            
            // Mobile
            document.getElementById('latitude-mobile').innerHTML = coords.latitude.toFixed(6);
            document.getElementById('longitude-mobile').innerHTML = coords.longitude.toFixed(6);
            document.getElementById('accuracy-mobile').innerHTML = coords.accuracy.toFixed(0);
            document.getElementById('speed-mobile').innerHTML = speedKmh.toFixed(1);
            document.getElementById('heading-mobile').innerHTML = (coords.heading || 0).toFixed(0);
            document.getElementById('altitude-mobile').innerHTML = (coords.altitude || 0).toFixed(0);
            
            // Desktop
            document.getElementById('desktop-lat').innerHTML = coords.latitude.toFixed(6);
            document.getElementById('desktop-lng').innerHTML = coords.longitude.toFixed(6);
            document.getElementById('desktop-acc').innerHTML = `±${coords.accuracy.toFixed(0)}m`;
            
            document.getElementById('gps-state-mobile').innerHTML = '🟢 GPS ACTIVE - LIVE';
            document.getElementById('desktop-gps-state').innerHTML = '🟢 GPS ACTIVE - TRACKING';
        }
        
        // ========== UPDATE MAP LOCATIONS ==========
        function updateMapLocations() {
            if (!userLocation) return;
            
            const position = { lat: userLocation.lat, lng: userLocation.lng };
            
            // Update mobile map
            if (mobileMap) {
                mobileMap.setCenter(position);
                mobileMap.setZoom(18);
                
                // Create or update marker
                if (mobileMarker) {
                    mobileMarker.setPosition(position);
                } else {
                    mobileMarker = new google.maps.Marker({
                        position: position,
                        map: mobileMap,
                        title: 'You are here',
                        icon: {
                            path: google.maps.SymbolPath.CIRCLE,
                            scale: 10,
                            fillColor: '#ff5e00',
                            fillOpacity: 1,
                            strokeColor: '#ffffff',
                            strokeWeight: 2
                        },
                        animation: google.maps.Animation.DROP
                    });
                    
                    mobileMarker.addListener('click', () => {
                        mobileInfoWindow.setContent(`
                            <div style="padding: 10px;">
                                <h3 style="font-weight: bold; color: #ff5e00;">📍 You Are Here</h3>
                                <p>Accuracy: ±${userLocation.accuracy.toFixed(0)}m</p>
                                <p>Speed: ${(userLocation.speed * 3.6).toFixed(1)} km/h</p>
                            </div>
                        `);
                        mobileInfoWindow.open(mobileMap, mobileMarker);
                    });
                }
            }
            
            // Update desktop map
            if (desktopMap) {
                desktopMap.setCenter(position);
                desktopMap.setZoom(18);
                
                if (desktopMarker) {
                    desktopMarker.setPosition(position);
                } else {
                    desktopMarker = new google.maps.Marker({
                        position: position,
                        map: desktopMap,
                        title: 'You are here',
                        icon: {
                            path: google.maps.SymbolPath.CIRCLE,
                            scale: 10,
                            fillColor: '#ff5e00',
                            fillOpacity: 1,
                            strokeColor: '#ffffff',
                            strokeWeight: 2
                        },
                        animation: google.maps.Animation.DROP
                    });
                    
                    desktopMarker.addListener('click', () => {
                        desktopInfoWindow.setContent(`
                            <div style="padding: 10px;">
                                <h3 style="font-weight: bold; color: #ff5e00;">📍 Your Location</h3>
                                <p>Accuracy: ±${userLocation.accuracy.toFixed(0)}m</p>
                            </div>
                        `);
                        desktopInfoWindow.open(desktopMap, desktopMarker);
                    });
                }
            }
        }
        
        // ========== UPDATE MAP MARKERS ==========
        function updateMapMarkers(coords) {
            const position = { lat: coords.latitude, lng: coords.longitude };
            
            if (mobileMarker) {
                mobileMarker.setPosition(position);
                mobileMap.panTo(position);
            }
            
            if (desktopMarker) {
                desktopMarker.setPosition(position);
                desktopMap.panTo(position);
            }
        }
        
        // ========== REVERSE GEOCODE ==========
        function reverseGeocode(lat, lng) {
            const geocoder = new google.maps.Geocoder();
            
            geocoder.geocode({ location: { lat, lng } }, (results, status) => {
                if (status === 'OK' && results[0]) {
                    const address = results[0].address_components;
                    const formatted = results[0].formatted_address;
                    
                    // Extract address components
                    let street = '', area = '', city = '', state = '', country = '';
                    
                    for (let component of address) {
                        if (component.types.includes('route')) {
                            street = component.long_name;
                        }
                        if (component.types.includes('sublocality') || component.types.includes('neighborhood')) {
                            area = component.long_name;
                        }
                        if (component.types.includes('locality')) {
                            city = component.long_name;
                        }
                        if (component.types.includes('administrative_area_level_1')) {
                            state = component.long_name;
                        }
                        if (component.types.includes('country')) {
                            country = component.long_name;
                        }
                    }
                    
                    // Update UI
                    document.getElementById('street-name-mobile').innerHTML = street || 'Unknown Street';
                    document.getElementById('full-address-mobile').innerHTML = `<i class="fas fa-map-pin"></i> ${formatted}`;
                    
                    document.getElementById('detail-street-mobile').innerHTML = street || '--';
                    document.getElementById('detail-area-mobile').innerHTML = area || '--';
                    document.getElementById('detail-city-mobile').innerHTML = city || '--';
                    document.getElementById('detail-state-mobile').innerHTML = state || 'Anambra';
                    document.getElementById('detail-country-mobile').innerHTML = country || 'Nigeria';
                    
                    document.getElementById('desktop-street').innerHTML = street || 'Unknown Street';
                    document.getElementById('desktop-address').innerHTML = formatted;
                }
            });
        }
        
        // ========== SEARCH LOCATION ==========
        function searchLocation(query, isMobile = true) {
            if (!query) return;
            
            const map = isMobile ? mobileMap : desktopMap;
            const service = isMobile ? mobilePlacesService : desktopPlacesService;
            
            const request = {
                query: query,
                fields: ['name', 'geometry', 'formatted_address']
            };
            
            service.findPlaceFromQuery(request, (results, status) => {
                if (status === google.maps.places.PlacesServiceStatus.OK && results[0]) {
                    const place = results[0];
                    const location = place.geometry.location;
                    
                    map.setCenter(location);
                    map.setZoom(16);
                    
                    new google.maps.Marker({
                        position: location,
                        map: map,
                        title: place.name,
                        animation: google.maps.Animation.DROP
                    });
                }
            });
        }
        
        // ========== FIND NEARBY PLACES ==========
        function findNearbyPlaces(isMobile = true) {
            if (!userLocation) {
                alert('Please enable location first');
                return;
            }
            
            const map = isMobile ? mobileMap : desktopMap;
            const service = isMobile ? mobilePlacesService : desktopPlacesService;
            const container = isMobile ? mobilePlacesContainer : desktopPlacesContainer;
            
            const request = {
                location: { lat: userLocation.lat, lng: userLocation.lng },
                radius: 1000,
                type: ['restaurant', 'cafe', 'store', 'bank', 'hospital']
            };
            
            service.nearbySearch(request, (results, status) => {
                if (status === google.maps.places.PlacesServiceStatus.OK) {
                    container.innerHTML = '<h3 class="font-bold p-2">Nearby Places</h3>';
                    
                    results.slice(0, 10).forEach(place => {
                        const div = document.createElement('div');
                        div.className = 'place-item';
                        div.innerHTML = `
                            <i class="fas fa-store place-icon"></i>
                            <div>
                                <div class="place-name">${place.name}</div>
                                <div class="place-address">${place.vicinity || ''}</div>
                            </div>
                        `;
                        
                        div.addEventListener('click', () => {
                            map.setCenter(place.geometry.location);
                            map.setZoom(18);
                            
                            new google.maps.Marker({
                                position: place.geometry.location,
                                map: map,
                                title: place.name
                            });
                            
                            container.classList.remove('active');
                        });
                        
                        container.appendChild(div);
                    });
                    
                    container.classList.add('active');
                }
            });
        }
        
        // ========== FIND NEARBY CHURCHES ==========
        function findNearbyChurches(view) {
            const isMobile = view === 'mobile';
            
            if (!userLocation) {
                alert('Please enable location first');
                return;
            }
            
            const map = isMobile ? mobileMap : desktopMap;
            const service = isMobile ? mobilePlacesService : desktopPlacesService;
            const container = isMobile ? mobilePlacesContainer : desktopPlacesContainer;
            
            const request = {
                location: { lat: userLocation.lat, lng: userLocation.lng },
                radius: 2000,
                type: ['church', 'place_of_worship']
            };
            
            service.nearbySearch(request, (results, status) => {
                if (status === google.maps.places.PlacesServiceStatus.OK) {
                    container.innerHTML = '<h3 class="font-bold p-2">Nearby Churches</h3>';
                    
                    results.slice(0, 10).forEach(place => {
                        const div = document.createElement('div');
                        div.className = 'place-item';
                        div.innerHTML = `
                            <i class="fas fa-church place-icon"></i>
                            <div>
                                <div class="place-name">${place.name}</div>
                                <div class="place-address">${place.vicinity || ''}</div>
                            </div>
                        `;
                        
                        div.addEventListener('click', () => {
                            map.setCenter(place.geometry.location);
                            map.setZoom(18);
                            
                            new google.maps.Marker({
                                position: place.geometry.location,
                                map: map,
                                title: place.name,
                                icon: {
                                    url: 'https://maps.google.com/mapfiles/ms/icons/blue-dot.png'
                                }
                            });
                            
                            container.classList.remove('active');
                        });
                        
                        container.appendChild(div);
                    });
                    
                    container.classList.add('active');
                } else {
                    alert('No churches found nearby');
                }
            });
        }
        
        // ========== TOGGLE PLACES ==========
        function togglePlaces(view) {
            findNearbyPlaces(view === 'mobile');
        }
        
        // ========== CENTER ON USER ==========
        function centerOnUser(view) {
            if (!userLocation) {
                alert('Location not available');
                return;
            }
            
            const map = view === 'mobile' ? mobileMap : desktopMap;
            if (map) {
                map.setCenter({ lat: userLocation.lat, lng: userLocation.lng });
                map.setZoom(18);
            }
        }
        
        // ========== EVENT LISTENERS ==========
        document.addEventListener('DOMContentLoaded', function() {
            // Mobile search
            document.getElementById('mobile-search-btn')?.addEventListener('click', function() {
                searchLocation(document.getElementById('mobile-search').value, true);
            });
            
            document.getElementById('mobile-search')?.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') searchLocation(this.value, true);
            });
            
            // Desktop search
            document.getElementById('desktop-search-btn')?.addEventListener('click', function() {
                searchLocation(document.getElementById('desktop-search').value, false);
            });
            
            document.getElementById('desktop-search')?.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') searchLocation(this.value, false);
            });
            
            // Close places container when clicking outside
            document.addEventListener('click', function(e) {
                if (!e.target.closest('.places-container') && !e.target.closest('.map-control-btn')) {
                    if (mobilePlacesContainer) mobilePlacesContainer.classList.remove('active');
                    if (desktopPlacesContainer) desktopPlacesContainer.classList.remove('active');
                }
            });
        });
        
        // ========== CLEANUP ==========
        window.addEventListener('beforeunload', function() {
            if (watchId) {
                navigator.geolocation.clearWatch(watchId);
            }
        });
    </script>
</body>
</html>  