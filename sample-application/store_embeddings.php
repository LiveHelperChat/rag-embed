<?php

/**
 * Script to store embeddings from output.json to ChromaDB
 */

// Configure ChromaDB connection
$chromaHost = getenv('CHROMA_HOST') ?: 'localhost';
$chromaPort = getenv('CHROMA_PORT') ?: '8010';
$baseUrl = "http://{$chromaHost}:{$chromaPort}";
$collectionName = getenv('COLLECTION_NAME') ?: 'lhc';
$collectionId = null;
$tenant = getenv('COLLECTION_NAME') ?: 'lhc';
$database  = getenv('COLLECTION_NAME') ?: 'lhc';

// Load embeddings from the JSON file
$jsonFilePath = __DIR__ . '/output.json';

if (!file_exists($jsonFilePath)) {
    die("Error: File not found at $jsonFilePath\n");
}

$jsonData = file_get_contents($jsonFilePath);
$data = json_decode($jsonData, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    die("Error: Failed to parse JSON file: " . json_last_error_msg() . "\n");
}

// Check if required data exists
if (!isset($data['embeddings']) || !isset($data['chunk_texts']) || !isset($data['document_indices'])) {
    die("Error: Missing required data in JSON file\n");
}

// Check embedding dimension
$embeddingDimension = count($data['embeddings'][0]);
echo "Embedding dimension: $embeddingDimension\n";

// Create collection if it doesn't exist
$collectionExists = false;
$listCollectionsResponse = httpRequest("{$baseUrl}/api/v2/tenants/{$tenant}/databases/{$database}/collections", 'GET');

if ($listCollectionsResponse) {
    $collections = json_decode($listCollectionsResponse, true);
    if (!empty($collections)) {
        foreach ($collections as $collection) {
            if (isset($collection['name']) && $collection['name'] === $collectionName) {
                $collectionId = $collection['id'];
                $collectionExists = true;
                if (isset($collection['dimension']) && $collection['dimension'] !== $embeddingDimension) {
                    echo "Collection exists but has wrong dimension ({$collection['dimension']} instead of $embeddingDimension). Recreating...\n";
                    // Delete the collection
                    httpRequest("{$baseUrl}/api/v2/tenants/{$tenant}/databases/{$database}/collections/{$collectionName}", 'DELETE');
                    $collectionExists = false;
                }
                break;
            }
        }
    }
}

if (!$collectionExists) {
    // Create collection with proper dimension
    $createPayload = json_encode([
        'name' => $collectionName,
        'metadata' => ['description' => 'Live Helper Chat embeddings collection'],
        'dimension' => $embeddingDimension
    ]);
    
    $createResponse = httpRequest("{$baseUrl}/api/v2/tenants/{$tenant}/databases/{$database}/collections", 'POST', $createPayload);
    if ($createResponse) {
        $newCollection = json_decode($createResponse, true);
        if (isset($newCollection['id'])) {
            $collectionId = $newCollection['id'];
        }
    }
    echo "Created new collection: $collectionName with dimension $embeddingDimension\n";
} else {
    echo "Using existing collection: $collectionName with id $collectionId\n";
}

// Prepare data for upsert
$ids = [];
$embeddings = $data['embeddings'];
$documents = $data['chunk_texts'];
$metadatas = [];

// Build IDs and metadata from document indices
foreach ($data['document_indices'] as $index => $docInfo) {
    $documentId = $docInfo['document_id'];
    foreach ($docInfo['embedding_ids'] as $embeddingId) {
        $ids[] = "doc_{$documentId}_emb_{$embeddingId}";
        $metadatas[] = [
            'document_id' => $documentId,
            'embedding_id' => $embeddingId
        ];
    }
}

// Ensure all arrays have the same count
$count = min(count($ids), count($embeddings), count($documents), count($metadatas));
if ($count < count($ids)) {
    $ids = array_slice($ids, 0, $count);
    $embeddings = array_slice($embeddings, 0, $count);
    $documents = array_slice($documents, 0, $count);
    $metadatas = array_slice($metadatas, 0, $count);
}

// Upsert data to ChromaDB
$upsertPayload = json_encode([
    'ids' => $ids,
    'embeddings' => $embeddings,
    'metadatas' => $metadatas,
    'documents' => $documents
]);

$upsertResponse = httpRequest("{$baseUrl}/api/v2/tenants/{$tenant}/databases/{$database}/collections/{$collectionId}/upsert", 'POST', $upsertPayload);
if ($upsertResponse) {
    $responseData = json_decode($upsertResponse, true);
    echo "Successfully stored " . count($ids) . " embeddings to ChromaDB\n";
} else {
    echo "Error storing embeddings to ChromaDB\n";
}

/**
 * Helper function to make HTTP requests
 */
function httpRequest($url, $method = 'GET', $data = null) {
    $ch = curl_init($url);
    
    $headers = [
        'Content-Type: application/json',
        'Accept: application/json'
    ];
    
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($data !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }
    } elseif ($method !== 'GET') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        if ($data !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (curl_errno($ch)) {
        echo "cURL Error: " . curl_error($ch) . "\n";
        return false;
    }
    
    curl_close($ch);
    
    if ($httpCode >= 400) {
        echo "HTTP Error: $httpCode - Response: $response\n";
        return false;
    }
    
    return $response;
}
