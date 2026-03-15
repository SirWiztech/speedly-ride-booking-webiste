<?php
// test_api_direct.php
$ride_id = $_GET['ride_id'] ?? '189f185b15d83fc10982d181a7e80df5';
?>
<!DOCTYPE html>
<html>
<head>
    <title>Direct API Test</title>
    <style>
        body { font-family: Arial; padding: 20px; }
        .success { color: green; }
        .error { color: red; }
        pre { background: #f4f4f4; padding: 10px; border-radius: 5px; overflow: auto; }
        button { padding: 10px 20px; background: #ff5e00; color: white; border: none; border-radius: 5px; cursor: pointer; }
    </style>
</head>
<body>
    <h1>Direct API Test</h1>
    
    <p>Ride ID: <strong><?php echo $ride_id; ?></strong></p>
    
    <button onclick="testDirectFetch()">Test Direct Fetch</button>
    <button onclick="testWithCredentials()">Test with Credentials</button>
    <button onclick="testRelativePath()">Test Relative Path</button>
    
    <div id="result" style="margin-top: 20px;"></div>
    
    <script>
    function testDirectFetch() {
        const rideId = '<?php echo $ride_id; ?>';
        const url = `SERVER/API/get_ride_details.php?ride_id=${rideId}`;
        
        document.getElementById('result').innerHTML = '<p>Fetching: ' + url + '</p>';
        
        fetch(url)
            .then(response => {
                document.getElementById('result').innerHTML += '<p>Status: ' + response.status + ' ' + response.statusText + '</p>';
                return response.text();
            })
            .then(text => {
                document.getElementById('result').innerHTML += '<h3>Raw Response:</h3><pre>' + text + '</pre>';
                try {
                    const json = JSON.parse(text);
                    document.getElementById('result').innerHTML += '<h3>Parsed JSON:</h3><pre>' + JSON.stringify(json, null, 2) + '</pre>';
                } catch (e) {
                    document.getElementById('result').innerHTML += '<p class="error">Failed to parse JSON: ' + e.message + '</p>';
                }
            })
            .catch(error => {
                document.getElementById('result').innerHTML += '<p class="error">Fetch error: ' + error.message + '</p>';
            });
    }
    
    function testWithCredentials() {
        const rideId = '<?php echo $ride_id; ?>';
        const url = `SERVER/API/get_ride_details.php?ride_id=${rideId}`;
        
        fetch(url, {
            credentials: 'include',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.text())
        .then(text => {
            document.getElementById('result').innerHTML = '<h3>With Credentials:</h3><pre>' + text + '</pre>';
        })
        .catch(error => {
            document.getElementById('result').innerHTML = '<p class="error">Error: ' + error.message + '</p>';
        });
    }
    
    function testRelativePath() {
        const rideId = '<?php echo $ride_id; ?>';
        const url = `/SPEEDLY/SERVER/API/get_ride_details.php?ride_id=${rideId}`;
        
        document.getElementById('result').innerHTML = '<p>Fetching absolute path: ' + url + '</p>';
        
        fetch(url)
            .then(response => response.text())
            .then(text => {
                document.getElementById('result').innerHTML = '<h3>Absolute Path:</h3><pre>' + text + '</pre>';
            })
            .catch(error => {
                document.getElementById('result').innerHTML = '<p class="error">Error: ' + error.message + '</p>';
            });
    }
    </script>
</body>
</html>