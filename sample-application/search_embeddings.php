<?php

/**
 * Script to search in ChromaDB by embeddings
 * Usage: php search_embeddings.php "Your search query"
 */

// Check if a search query is provided
if ($argc < 2) {
    die("Usage: php search_embeddings.php \"Your search query\"\n");
}

$searchQuery = $argv[1];

// Configure ChromaDB connection
$chromaHost = getenv('CHROMA_HOST') ?: 'localhost';
$chromaPort = getenv('CHROMA_PORT') ?: '8010';
$baseUrl = "http://{$chromaHost}:{$chromaPort}";
$collectionId = 'bb308c00-4392-4f8b-ba81-6f722221fc64';
$tenant = getenv('COLLECTION_NAME') ?: 'lhc';
$database = getenv('COLLECTION_NAME') ?: 'lhc';

// Configure embedding API (using OpenAI as an example, you should replace with your embedding provider)
$embeddingEndpoint = getenv('EMBEDDING_ENDPOINT') ?: 'http://localhost:9710/embed_query';

// Generate embedding for the search query
$queryEmbedding = generateEmbedding($searchQuery, $embeddingEndpoint);

if (!$queryEmbedding) {
    die("Error: Failed to generate embedding for the query\n");
}



// Search ChromaDB with the generated embedding
$searchResults = searchChromaDb($queryEmbedding, $baseUrl, $tenant, $database, $collectionId);

if (!$searchResults) {
    die("Error: Failed to search ChromaDB\n");
}


// Display search results
displayResults($searchResults, $searchQuery);

/**
 * Generate embedding for the input text
 */
function generateEmbedding($text, $endpoint) {
    // You can add other embedding providers here
    return generateGenericEmbedding($text, $endpoint);
}

/**
 * Generic embedding generator for other providers
 */
function generateGenericEmbedding($text,  $endpoint) {
    // Implement based on your specific embedding provider
    echo "Using generic embedding provider at $endpoint\n";
    
    $ch = curl_init($endpoint);
    
    $headers = [
        'Content-Type: application/json'
    ];

    $data = json_encode([
        'query' => $text,
    ]);
    
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (curl_errno($ch)) {
        echo "cURL Error: " . curl_error($ch) . "\n";
        return null;
    }
    
    curl_close($ch);
    
    if ($httpCode >= 400) {
        echo "Embedding API Error: $httpCode - Response: $response\n";
        return null;
    }
    
    $responseData = json_decode($response, true);
    
    // Adjust the response parsing based on your provider's response format
    if (isset($responseData['embed'])) {
        return $responseData['embed'];
    }
    
    echo "Unexpected Embedding API response format\n";
    return null;
}

/**
 * Search ChromaDB using the provided embedding
 */
function searchChromaDb($embedding, $baseUrl, $tenant, $database_name, $collectionId, $limit = 5) {
    $queryParams = [
        'query_embeddings' => [$embedding],
        'n_results' => $limit
    ];



    $payload = json_encode($queryParams);


    echo $payload,"\n";


    $ch = curl_init("{$baseUrl}/api/v2/tenants/{$tenant}/databases/{$database_name}/collections/{$collectionId}/query");

    $headers = [
        'Content-Type: application/json',
        'Accept: application/json'
    ];
    
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (curl_errno($ch)) {
        echo "cURL Error: " . curl_error($ch) . "\n";
        return null;
    }
    
    curl_close($ch);
    
    if ($httpCode >= 400) {
        echo "ChromaDB API Error: $httpCode - Response: $response\n";
        return null;
    }
    
    return json_decode($response, true);
}

/**
 * Display the search results
 */
function displayResults($results, $query) {
    echo "Search query: \"$query\"\n";
    echo "Results:\n";
    echo "--------\n";
    
    if (isset($results['documents']) && is_array($results['documents']) && count($results['documents']) > 0) {
        $documents = $results['documents'][0];
        $distances = isset($results['distances']) ? $results['distances'][0] : [];
        $metadatas = isset($results['metadatas']) ? $results['metadatas'][0] : [];
        
        foreach ($documents as $i => $document) {
            $distance = isset($distances[$i]) ? $distances[$i] : 'N/A';
            $metadata = isset($metadatas[$i]) ? json_encode($metadatas[$i]) : 'N/A';
            
            echo "Result #" . ($i + 1) . ":\n";
            echo "Text: $document\n";
            echo "Similarity: " . (1 - $distance) . "\n";
            echo "Metadata: $metadata\n";
            echo "--------\n";
        }
    } else {
        echo "No results found\n";
    }
}
