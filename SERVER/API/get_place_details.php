<?php
// SERVER/API/get_place_details.php
header('Content-Type: application/json');

// Enable error logging
error_log("=== Get Place Details Request Started ===");

$place_id = $_GET['place_id'] ?? '';

if (empty($place_id)) {
    error_log("Place ID not provided");
    echo json_encode(['success' => false, 'message' => 'Place ID required']);
    exit;
}

error_log("Fetching details for place_id: " . $place_id);

// Google Maps API Key (use the same key from book-ride.php)
$apiKey = 'AIzaSyB1tM_s2w8JWfnIoUTAzJNpbblU-eZiC30';

// Use Google Places API for place details
$url = "https://maps.googleapis.com/maps/api/place/details/json"
    . "?place_id=" . urlencode($place_id)
    . "&fields=place_id,name,formatted_address,geometry,address_components,types,formatted_phone_number,website,opening_hours,rating,user_ratings_total,price_level"
    . "&key=" . $apiKey;

error_log("Google API URL: " . str_replace($apiKey, 'HIDDEN', $url));

// Initialize cURL
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200 || !$response) {
    error_log("Google API request failed. HTTP Code: " . $httpCode);
    
    // Fallback to OpenStreetMap Nominatim if Google fails
    error_log("Using fallback Nominatim API");
    
    // Try to extract location from place_id (if it's an OSM ID)
    if (strpos($place_id, 'osm_') === 0) {
        $osm_id = substr($place_id, 4);
        $fallbackUrl = "https://nominatim.openstreetmap.org/details"
            . "?osmtype=R"
            . "&osmid=" . urlencode($osm_id)
            . "&format=json"
            . "&addressdetails=1";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $fallbackUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Speedly App/1.0');
        
        $fallbackResponse = curl_exec($ch);
        curl_close($ch);
        
        if ($fallbackResponse) {
            $data = json_decode($fallbackResponse, true);
            
            if (isset($data['lat']) && isset($data['lon'])) {
                $result = [
                    'success' => true,
                    'place_id' => $place_id,
                    'name' => $data['name'] ?? $data['display_name'] ?? 'Unknown Location',
                    'formatted_address' => $data['display_name'] ?? '',
                    'geometry' => [
                        'location' => [
                            'lat' => (float)$data['lat'],
                            'lng' => (float)$data['lon']
                        ]
                    ],
                    'address_components' => [],
                    'types' => ['establishment'],
                    'source' => 'nominatim'
                ];
                
                // Add address components if available
                if (isset($data['address'])) {
                    foreach ($data['address'] as $key => $value) {
                        $result['address_components'][] = [
                            'long_name' => $value,
                            'short_name' => $value,
                            'types' => [$key]
                        ];
                    }
                }
                
                echo json_encode($result);
                exit;
            }
        }
    }
    
    echo json_encode(['success' => false, 'message' => 'Could not fetch place details']);
    exit;
}

$data = json_decode($response, true);

if ($data['status'] !== 'OK') {
    error_log("Google API error: " . $data['status']);
    echo json_encode(['success' => false, 'message' => 'Place not found: ' . $data['status']]);
    exit;
}

if (!isset($data['result'])) {
    error_log("No result in Google API response");
    echo json_encode(['success' => false, 'message' => 'No details found for this place']);
    exit;
}

$result = $data['result'];

// Extract location coordinates
$location = null;
if (isset($result['geometry']['location'])) {
    $location = [
        'lat' => $result['geometry']['location']['lat'],
        'lng' => $result['geometry']['location']['lng']
    ];
}

// Extract address components into a more usable format
$address_components = [];
if (isset($result['address_components'])) {
    foreach ($result['address_components'] as $component) {
        foreach ($component['types'] as $type) {
            $address_components[$type] = [
                'long_name' => $component['long_name'],
                'short_name' => $component['short_name']
            ];
        }
    }
}

// Build a structured address
$structured_address = [
    'street_number' => $address_components['street_number']['long_name'] ?? '',
    'route' => $address_components['route']['long_name'] ?? '',
    'neighborhood' => $address_components['neighborhood']['long_name'] ?? '',
    'locality' => $address_components['locality']['long_name'] ?? '',
    'administrative_area_level_1' => $address_components['administrative_area_level_1']['long_name'] ?? '',
    'administrative_area_level_2' => $address_components['administrative_area_level_2']['long_name'] ?? '',
    'country' => $address_components['country']['long_name'] ?? '',
    'postal_code' => $address_components['postal_code']['long_name'] ?? ''
];

// Determine place type for categorization
$place_types = $result['types'] ?? [];
$place_category = 'other';
$place_icon = 'map-marker-alt';

if (in_array('airport', $place_types)) {
    $place_category = 'airport';
    $place_icon = 'plane';
} elseif (in_array('shopping_mall', $place_types) || in_array('department_store', $place_types)) {
    $place_category = 'mall';
    $place_icon = 'shopping-cart';
} elseif (in_array('restaurant', $place_types) || in_array('cafe', $place_types) || in_array('bar', $place_types)) {
    $place_category = 'restaurant';
    $place_icon = 'utensils';
} elseif (in_array('lodging', $place_types) || in_array('hotel', $place_types)) {
    $place_category = 'hotel';
    $place_icon = 'hotel';
} elseif (in_array('hospital', $place_types) || in_array('doctor', $place_types) || in_array('pharmacy', $place_types)) {
    $place_category = 'medical';
    $place_icon = 'hospital';
} elseif (in_array('university', $place_types) || in_array('school', $place_types)) {
    $place_category = 'education';
    $place_icon = 'school';
} elseif (in_array('bus_station', $place_types) || in_array('transit_station', $place_types)) {
    $place_category = 'transit';
    $place_icon = 'bus';
} elseif (in_array('park', $place_types)) {
    $place_category = 'park';
    $place_icon = 'tree';
} elseif (in_array('bank', $place_types)) {
    $place_category = 'bank';
    $place_icon = 'university';
} elseif (in_array('gas_station', $place_types)) {
    $place_category = 'gas';
    $place_icon = 'gas-pump';
}

$response_data = [
    'success' => true,
    'place_id' => $result['place_id'],
    'name' => $result['name'] ?? '',
    'formatted_address' => $result['formatted_address'] ?? '',
    'structured_address' => $structured_address,
    'geometry' => [
        'location' => $location
    ],
    'address_components' => $address_components,
    'types' => $place_types,
    'category' => $place_category,
    'icon' => $place_icon,
    'phone' => $result['formatted_phone_number'] ?? null,
    'website' => $result['website'] ?? null,
    'rating' => $result['rating'] ?? null,
    'user_ratings_total' => $result['user_ratings_total'] ?? null,
    'price_level' => $result['price_level'] ?? null,
    'opening_hours' => $result['opening_hours'] ?? null,
    'utc_offset' => $result['utc_offset'] ?? null,
    'source' => 'google'
];

error_log("Successfully fetched details for place: " . ($result['name'] ?? 'Unknown'));

echo json_encode($response_data);
?>  