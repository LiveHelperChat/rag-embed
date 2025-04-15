<?php
/**
 * Embed documents Script
 * 
 * This script reads a query from a file and sends it to the embedding service.
 * Usage: php embed_documents.php <path_to_query_file>
 */

$host = 'http://localhost:9710';

// Check if file path is provided
if ($argc < 2) {
    echo "Usage: php embed_query.php <path_to_query_file>\n";
    exit(1);
}

$queryFile = $argv[1];

// Check if file exists
if (!file_exists($queryFile)) {
    echo "Error: File '{$queryFile}' not found.\n";
    exit(1);
}

// Read query from file
$query = trim(file_get_contents($queryFile));

if (empty($query)) {
    echo "Error: Query file is empty.\n";
    exit(1);
}

$documents = preg_split('/^---$/m', $query);

// Prepare data for the POST request
$data = json_encode(['docs' => $documents]);

// Set up cURL request
$ch = curl_init($host . '/embed_documents');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Content-Length: ' . strlen($data)
]);

// Execute the request
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

// Check for errors
if (curl_errno($ch)) {
    echo "Error: " . curl_error($ch) . "\n";
    exit(1);
}
curl_close($ch);

// Display response
echo "HTTP Status Code: {$httpCode}\n";
echo "Response:\n";

file_put_contents('output.json', $response) . "\n";

?>
