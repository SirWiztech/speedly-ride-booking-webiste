<?php
// SERVER/API/get_location_suggestions.php
header('Content-Type: application/json');

// Enable error logging
error_log("=== Get Location Suggestions Request Started ===");

$query = $_GET['q'] ?? '';
$lat = $_GET['lat'] ?? null;
$lng = $_GET['lng'] ?? null;

if (strlen($query) < 2) {
    echo json_encode([]);
    exit;
}

error_log("Search query: " . $query);

// Google Maps API Key (use the same key from book-ride.php)
$apiKey = 'AIzaSyB1tM_s2w8JWfnIoUTAzJNpbblU-eZiC30';

// If we have coordinates, we can bias results near that location
$locationBias = '';
if ($lat && $lng) {
    $locationBias = "&location=" . urlencode($lat . ',' . $lng) . "&radius=50000";
}

// Use Google Places API for autocomplete
$url = "https://maps.googleapis.com/maps/api/place/autocomplete/json"
    . "?input=" . urlencode($query)
    . "&types=geocode"
    . "&components=country:ng"
    . $locationBias
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
    $fallbackUrl = "https://nominatim.openstreetmap.org/search"
        . "?format=json"
        . "&q=" . urlencode($query . ", Nigeria")
        . "&limit=5"
        . "&countrycodes=ng";
    
    error_log("Using fallback Nominatim API");
    
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
        $suggestions = [];
        
        foreach ($data as $item) {
            $suggestions[] = [
                'description' => $item['display_name'],
                'place_id' => $item['osm_id'],
                'structured_formatting' => [
                    'main_text' => $item['display_name'],
                    'secondary_text' => 'Nigeria'
                ],
                'terms' => [
                    ['value' => $item['display_name']]
                ],
                'source' => 'nominatim'
            ];
        }
        
        echo json_encode($suggestions);
        exit;
    }
    
    echo json_encode([]);
    exit;
}

$data = json_decode($response, true);

if ($data['status'] !== 'OK' && $data['status'] !== 'ZERO_RESULTS') {
    error_log("Google API error: " . $data['status']);
    echo json_encode([]);
    exit;
}

$suggestions = [];

if (isset($data['predictions'])) {
    foreach ($data['predictions'] as $prediction) {
        $suggestions[] = [
            'description' => $prediction['description'],
            'place_id' => $prediction['place_id'],
            'structured_formatting' => [
                'main_text' => $prediction['structured_formatting']['main_text'] ?? $prediction['description'],
                'secondary_text' => $prediction['structured_formatting']['secondary_text'] ?? ''
            ],
            'terms' => $prediction['terms'],
            'source' => 'google'
        ];
    }
}

error_log("Found " . count($suggestions) . " suggestions");
echo json_encode($suggestions);
?>     