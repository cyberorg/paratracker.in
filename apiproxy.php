<?php
	// Used to enable cross-domain AJAX calls.
	// Fetch data from a specific API endpoint.
	// Example: index.php/api/device-config?shortName=xxxx&longName=No%20Name

	// Set the base URL for the external API
	$base_url = "https://tracker.bircom.in/api";
	
	// Get the additional path from the request URI after '/index.php/api/'
	$request_uri = $_SERVER['REQUEST_URI'];
	$api_endpoint = str_replace('/apiproxy.php/api', '', $request_uri);
	
	// Construct the full URL to fetch data from, e.g., https://tracker.bircom.in/api/device-config?shortName=xxxx&longName=No%20Name
	$url = $base_url . $api_endpoint;

	// Enable access from all domains
	enable_cors();

	// Handle the device config download specifically
	if (strpos($api_endpoint, 'device-config') !== false) {
		downloadConfigFile($url);
	} else {
		// For other cases, handle normally
		switch ($_SERVER["REQUEST_METHOD"]) {
			case "GET":
				get($url);
				break;
			default:
				post($url);
				break;
		}
	}

	// Fetches and returns the contents of the URL
	function get($url) {
		header('Content-Type: application/json');
		echo file_get_contents($url);
	}

	// Sends a POST request to the external URL and echoes the result
	function post($url) {
		$postdata = http_build_query(
		    array()
		);

		$opts = array('http' =>
		    array(
		        'method'  => $_SERVER['REQUEST_METHOD'],
		        'header'  => 'Content-type: application/x-www-form-urlencoded',
		        'content' => $postdata
		    )
		);

		$context  = stream_context_create($opts);

		header('Content-Type: application/json');
		echo file_get_contents($url, false, $context);
	}

	// Handles the downloading of a binary config file
function downloadConfigFile($url) {
    // Initialize variables
    $filename = 'default_filename.cfg'; // Fallback filename

    // Initialize cURL session
    $ch = curl_init($url);

    // Set cURL options
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);     // Return content as a string
    curl_setopt($ch, CURLOPT_HEADER, true);             // Include headers in the output
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);     // Follow redirects
    curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);     // Binary-safe transfer

    // Capture headers to extract the filename
    curl_setopt($ch, CURLOPT_HEADERFUNCTION, function($curl, $header) use (&$filename) {
        if (stripos($header, 'Content-Disposition:') !== false) {
            if (preg_match('/filename="([^"]+)"/', $header, $matches)) {
                $filename = $matches[1];  // Extract filename
            }
        }
        return strlen($header);  // Return the length of the header processed
    });

    // Execute cURL and get the full response (headers + body)
    $response = curl_exec($ch);

    // Check for errors during execution
    if (curl_errno($ch)) {
        echo 'Error:' . curl_error($ch);
        curl_close($ch);
        return;
    }

    // Separate headers from the body
    $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $body = substr($response, $header_size);  // Extract the binary body (protobuf content)

    // Close the cURL session
    curl_close($ch);

    // Set appropriate headers for file download
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($body));  // Set the correct content length

    // Use fwrite to output the binary data to avoid accidental newlines
    $output = fopen('php://output', 'wb');  // Open output stream in binary mode
    fwrite($output, $body);  // Write the binary data
    fclose($output);  // Close the output stream

    // No closing PHP tag to avoid any accidental whitespace or newlines
}

	// Enable CORS (Cross-Origin Resource Sharing)
	function enable_cors() {
		// Allow from any origin
		if (isset($_SERVER['HTTP_ORIGIN'])) {
			header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
			header('Access-Control-Allow-Credentials: true');
			header('Access-Control-Max-Age: 86400');	// cache for 1 day
		} else {
			header("Access-Control-Allow-Origin: *");
			header('Access-Control-Allow-Credentials: true');
			header('Access-Control-Max-Age: 86400');	// cache for 1 day
		}

		// Access-Control headers are received during OPTIONS requests
		if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {

			if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD']))
				header("Access-Control-Allow-Methods: GET, POST, OPTIONS");

			if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']))
				header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");

			exit(0);
		}
	}

