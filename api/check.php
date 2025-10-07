<?php
// CORS (reflect requesting origin and headers for broader compatibility)
header('Vary: Origin, Access-Control-Request-Method, Access-Control-Request-Headers');
if (isset($_SERVER['HTTP_ORIGIN'])) {
	header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
} else {
	header('Access-Control-Allow-Origin: *');
}
header('Access-Control-Allow-Methods: GET, OPTIONS');
$requestHeaders = isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'])
	? trim($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'])
	: 'Origin, X-Requested-With, Content-Type, Accept';
if ($requestHeaders !== '') {
	header('Access-Control-Allow-Headers: ' . $requestHeaders);
}
header('Access-Control-Max-Age: 600');

// Preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
	http_response_code(204);
	exit;
}

header('Content-Type: application/json; charset=UTF-8');

// Basic caching (client-side)
$maxAgeSeconds = 300;
header('Cache-Control: public, max-age=' . $maxAgeSeconds);

// Upstream JSON URL (updated)
$upstreamUrl = 'https://asdasduz.github.io/king/api/check1.php';

// Fetch with cURL
function fetchJsonWithCurl($url) {
	$ch = curl_init($url);
	if ($ch === false) {
		throw new Exception('cURL init failed');
	}

	$headers = [
		'Accept: application/json',
		'User-Agent: check.php/1.0 (+fetch-json)'
	];

	curl_setopt_array($ch, [
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_FOLLOWLOCATION => true,
		CURLOPT_MAXREDIRS => 5,
		CURLOPT_CONNECTTIMEOUT => 5,
		CURLOPT_TIMEOUT => 10,
		CURLOPT_HTTPHEADER => $headers,
		CURLOPT_SSL_VERIFYPEER => true,
		CURLOPT_SSL_VERIFYHOST => 2,
	]);

	$body = curl_exec($ch);
	$errno = curl_errno($ch);
	$error = curl_error($ch);
	$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

	curl_close($ch);

	if ($errno !== 0) {
		throw new Exception('Network error: ' . $error);
	}

	if ($httpCode < 200 || $httpCode >= 300) {
		throw new Exception('Upstream HTTP ' . $httpCode);
	}

	if ($body === false || $body === '') {
		throw new Exception('Empty response from upstream');
	}

	return $body;
}

try {
	$json = fetchJsonWithCurl($upstreamUrl);

	// Validate JSON
	$decoded = json_decode($json, true);
	if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
		throw new Exception('Invalid JSON received from upstream');
	}

	// Optional: lightweight ETag to help client caching
	$etag = '"' . substr(sha1($json), 0, 16) . '"';
	header('ETag: ' . $etag);

	if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH']) === $etag) {
		http_response_code(304);
		exit;
	}

	echo $json;
} catch (Exception $e) {
	http_response_code(502);
	echo json_encode(['error' => $e->getMessage()]);
}
?>

