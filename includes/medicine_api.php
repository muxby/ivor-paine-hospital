<?php
/**
 * Medster API Integration Service
 * Reusable PHP service for medicine search and details
 * Features: API caching, health monitoring, error handling
 */

require_once __DIR__ . '/db.php';

// =============================================================================
// CONFIGURATION
// =============================================================================

const MEDSTER_BASE_URL = 'https://medster.vercel.app';
const MEDSTER_SEARCH_ENDPOINT = '/api/search';
const MEDSTER_DETAILS_ENDPOINT = '/api/details';
const API_TIMEOUT = 10; // seconds
const CACHE_DURATION_MINUTES = 60; // how long to cache results

// =============================================================================
// CORE API FUNCTIONS
// =============================================================================

/**
 * Search medicines by name via Medster API
 *
 * @param string $query Medicine name to search
 * @return array ['success' => bool, 'results' => [], 'error' => null|msg, 'from_cache' => bool]
 */
function searchMedicines($query) {
    // Validate query
    $query = trim($query);
    if (empty($query) || strlen($query) < 2) {
        return ['success' => false, 'results' => [], 'error' => 'Please enter at least 2 characters to search.', 'from_cache' => false];
    }

    // Check cache first
    $cached = getCachedSearch($query);
    if ($cached) {
        return ['success' => true, 'results' => $cached, 'error' => null, 'from_cache' => true];
    }

    // Build URL
    $url = MEDSTER_BASE_URL . MEDSTER_SEARCH_ENDPOINT . '?q=' . urlencode($query);

    // Call API
    $startTime = microtime(true);
    $response = callMedsterAPI($url);
    $responseTime = round((microtime(true) - $startTime) * 1000);

    // Log the API call
    logApiCall(MEDSTER_SEARCH_ENDPOINT, $query, $response !== false ? 'success' : 'error', $responseTime, $response === false ? 'API call failed' : null);

    if ($response === false) {
        // Try cache for fallback
        $fallback = getCacheFallback($query);
        if ($fallback) {
            return ['success' => true, 'results' => $fallback, 'error' => null, 'from_cache' => true];
        }
        return ['success' => false, 'results' => [], 'error' => 'Unable to reach medicine database. Please try again later.', 'from_cache' => false];
    }

    // Decode JSON
    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
        logApiCall(MEDSTER_SEARCH_ENDPOINT, $query, 'error', $responseTime, 'Invalid JSON response');
        return ['success' => false, 'results' => [], 'error' => 'Invalid response from medicine database.', 'from_cache' => false];
    }

    // Check API returned success
    if (empty($data['success']) || empty($data['results'])) {
        return ['success' => true, 'results' => [], 'error' => null, 'from_cache' => false, 'empty' => true];
    }

    // Normalize and limit results
    $results = array_slice($data['results'], 0, 10);
    foreach ($results as &$item) {
        $item['source'] = 'api';
    }

    // Save to cache
    cacheSearchResults($query, $results);
    cacheIndividualMedicines($results);

    return ['success' => true, 'results' => $results, 'error' => null, 'from_cache' => false];
}

/**
 * Get detailed information about a medicine
 *
 * @param string $medicineId The medicine ID from search results
 * @return array ['success' => bool, 'medicine' => [], 'error' => null|msg, 'from_cache' => bool]
 */
function getMedicineDetails($medicineId) {
    $medicineId = trim($medicineId);
    if (empty($medicineId)) {
        return ['success' => false, 'medicine' => null, 'error' => 'Medicine ID is required.', 'from_cache' => false];
    }

    // Check cache first
    $cached = getCachedDetails($medicineId);
    if ($cached) {
        return ['success' => true, 'medicine' => $cached, 'error' => null, 'from_cache' => true];
    }

    // Build URL
    $url = MEDSTER_BASE_URL . MEDSTER_DETAILS_ENDPOINT . '?id=' . urlencode($medicineId);

    // Call API
    $startTime = microtime(true);
    $response = callMedsterAPI($url);
    $responseTime = round((microtime(true) - $startTime) * 1000);

    // Log the API call
    logApiCall(MEDSTER_DETAILS_ENDPOINT, $medicineId, $response !== false ? 'success' : 'error', $responseTime, $response === false ? 'API call failed' : null);

    if ($response === false) {
        // Try to build partial details from cache
        $partial = getPartialDetailsFromCache($medicineId);
        if ($partial) {
            return ['success' => true, 'medicine' => $partial, 'error' => null, 'from_cache' => true, 'partial' => true];
        }
        return ['success' => false, 'medicine' => null, 'error' => 'Unable to retrieve medicine details. Please try again later.', 'from_cache' => false];
    }

    // Decode JSON
    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
        logApiCall(MEDSTER_DETAILS_ENDPOINT, $medicineId, 'error', $responseTime, 'Invalid JSON response');
        return ['success' => false, 'medicine' => null, 'error' => 'Invalid response from medicine database.', 'from_cache' => false];
    }

    if (empty($data['success']) || empty($data['medicine'])) {
        return ['success' => false, 'medicine' => null, 'error' => 'Medicine details not found.', 'from_cache' => false];
    }

    $medicine = $data['medicine'];
    $medicine['source'] = 'api';

    // Cache the details
    cacheMedicineDetails($medicineId, $medicine);

    return ['success' => true, 'medicine' => $medicine, 'error' => null, 'from_cache' => false];
}

// =============================================================================
// HTTP CLIENT
// =============================================================================

/**
 * Make HTTP GET request to Medster API
 *
 * @param string $url Full API URL
 * @return string|false Response body or false on failure
 */
function callMedsterAPI($url) {
    // Use cURL if available
    if (extension_loaded('curl')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => API_TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'User-Agent: IvorPaineHospital/1.0'
            ],
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error || $httpCode !== 200 || $response === false) {
            error_log("Medster API error: HTTP $httpCode, cURL error: $error, URL: $url");
            return false;
        }
        return $response;
    }

    // Fallback to file_get_contents
    $ctx = stream_context_create([
        'http' => [
            'timeout' => API_TIMEOUT,
            'header' => "Accept: application/json\r\nUser-Agent: IvorPaineHospital/1.0\r\n",
            'ignore_errors' => true,
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
        ],
    ]);
    $response = @file_get_contents($url, false, $ctx);
    if ($response === false) {
        error_log("Medster API error (file_get_contents): $url");
        return false;
    }
    return $response;
}

// =============================================================================
// CACHE FUNCTIONS
// =============================================================================

/**
 * Get cached search results if still fresh
 */
function getCachedSearch($query) {
    $check = dbQuery(
        "SELECT TOP 1 CacheID, DetailsJson FROM MEDICINE_API_CACHE
         WHERE SearchQuery = ? AND CachedAt > DATEADD(minute, -?, GETDATE())
         ORDER BY CachedAt DESC",
        [$query, CACHE_DURATION_MINUTES]
    );
    $row = dbFetchOne($check);
    if ($row && !empty($row['DetailsJson'])) {
        $decoded = json_decode($row['DetailsJson'], true);
        if (is_array($decoded)) {
            foreach ($decoded as &$item) {
                $item['source'] = 'cache';
            }
            return $decoded;
        }
    }
    return null;
}

/**
 * Cache search results
 */
function cacheSearchResults($query, $results) {
    $json = json_encode($results);
    // Delete old cache for same query
    dbQuery("DELETE FROM MEDICINE_API_CACHE WHERE SearchQuery = ?", [$query]);
    // Insert new cache
    dbQuery(
        "INSERT INTO MEDICINE_API_CACHE (SearchQuery, DetailsJson, CachedAt) VALUES (?, ?, GETDATE())",
        [$query, $json]
    );
}

/**
 * Cache individual medicine entries
 */
function cacheIndividualMedicines($results) {
    foreach ($results as $item) {
        if (empty($item['id'])) continue;
        $existing = dbQuery("SELECT CacheID FROM MEDICINE_API_CACHE WHERE MedicineApiID = ?", [$item['id']]);
        if (!dbFetchOne($existing)) {
            dbQuery(
                "INSERT INTO MEDICINE_API_CACHE (MedicineApiID, MedicineName, Price, CachedAt) VALUES (?, ?, ?, GETDATE())",
                [$item['id'], $item['name'] ?? '', $item['price'] ?? '']
            );
        } else {
            dbQuery(
                "UPDATE MEDICINE_API_CACHE SET MedicineName = ?, Price = ?, CachedAt = GETDATE() WHERE MedicineApiID = ?",
                [$item['name'] ?? '', $item['price'] ?? '', $item['id']]
            );
        }
    }
}

/**
 * Get cached medicine details
 */
function getCachedDetails($medicineId) {
    $check = dbQuery(
        "SELECT TOP 1 CacheID, DetailsJson, MedicineName, Price, Discount FROM MEDICINE_API_CACHE
         WHERE MedicineApiID = ? AND CachedAt > DATEADD(minute, -?, GETDATE())
         AND DetailsJson IS NOT NULL
         ORDER BY CachedAt DESC",
        [$medicineId, CACHE_DURATION_MINUTES]
    );
    $row = dbFetchOne($check);
    if ($row && !empty($row['DetailsJson'])) {
        $decoded = json_decode($row['DetailsJson'], true);
        if (is_array($decoded)) {
            $decoded['source'] = 'cache';
            return $decoded;
        }
    }
    return null;
}

/**
 * Cache detailed medicine info
 */
function cacheMedicineDetails($medicineId, $medicine) {
    $name = $medicine['name'] ?? '';
    $price = $medicine['price'] ?? '';
    $discount = $medicine['discount'] ?? '';
    $json = json_encode($medicine);

    $existing = dbQuery("SELECT CacheID FROM MEDICINE_API_CACHE WHERE MedicineApiID = ?", [$medicineId]);
    if (dbFetchOne($existing)) {
        dbQuery(
            "UPDATE MEDICINE_API_CACHE SET MedicineName = ?, Price = ?, Discount = ?, DetailsJson = ?, CachedAt = GETDATE() WHERE MedicineApiID = ?",
            [$name, $price, $discount, $json, $medicineId]
        );
    } else {
        dbQuery(
            "INSERT INTO MEDICINE_API_CACHE (MedicineApiID, MedicineName, Price, Discount, DetailsJson, CachedAt) VALUES (?, ?, ?, ?, ?, GETDATE())",
            [$medicineId, $name, $price, $discount, $json]
        );
    }
}

/**
 * Get fallback cache (any age) when API fails
 */
function getCacheFallback($query) {
    $check = dbQuery(
        "SELECT TOP 1 DetailsJson FROM MEDICINE_API_CACHE
         WHERE SearchQuery = ? AND DetailsJson IS NOT NULL
         ORDER BY CachedAt DESC",
        [$query]
    );
    $row = dbFetchOne($check);
    if ($row && !empty($row['DetailsJson'])) {
        $decoded = json_decode($row['DetailsJson'], true);
        if (is_array($decoded)) {
            foreach ($decoded as &$item) {
                $item['source'] = 'cache';
            }
            return $decoded;
        }
    }
    return null;
}

/**
 * Get partial details from cache when API fails
 */
function getPartialDetailsFromCache($medicineId) {
    $check = dbQuery(
        "SELECT MedicineApiID, MedicineName, Price, Discount FROM MEDICINE_API_CACHE
         WHERE MedicineApiID = ?",
        [$medicineId]
    );
    $row = dbFetchOne($check);
    if ($row) {
        return [
            'id' => $row['MedicineApiID'],
            'name' => $row['MedicineName'],
            'price' => $row['Price'],
            'discount' => $row['Discount'],
            'details' => [],
            'source' => 'cache',
            'partial' => true
        ];
    }
    return null;
}

// =============================================================================
// API LOGGING
// =============================================================================

/**
 * Log API call to database
 */
function logApiCall($endpoint, $queryValue, $status, $responseTimeMs, $errorMessage = null) {
    // Check if API_LOG table exists
    $check = dbQuery("SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'API_LOG'");
    if (!$check || !dbFetchOne($check)) return;

    dbQuery(
        "INSERT INTO API_LOG (Endpoint, QueryValue, Status, ResponseTimeMs, ErrorMessage, CreatedAt) VALUES (?, ?, ?, ?, ?, GETDATE())",
        [$endpoint, $queryValue, $status, $responseTimeMs, $errorMessage]
    );
}

// =============================================================================
// API HEALTH / STATUS
// =============================================================================

/**
 * Get Medster API health status
 *
 * @return array Status information
 */
function getApiHealthStatus() {
    $status = [
        'online' => false,
        'last_success' => null,
        'last_error' => null,
        'avg_response_time' => 0,
        'searches_today' => 0,
        'details_today' => 0,
        'message' => 'Unknown'
    ];

    // Check if API_LOG table exists
    $check = dbQuery("SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'API_LOG'");
    if (!$check || !dbFetchOne($check)) {
        // Quick connectivity test
        $test = @callMedsterAPI(MEDSTER_BASE_URL . MEDSTER_SEARCH_ENDPOINT . '?q=test');
        $status['online'] = ($test !== false);
        $status['message'] = $status['online'] ? 'API is reachable' : 'API unreachable - table not created';
        return $status;
    }

    // Last success
    $lastSuccess = dbFetchOne(dbQuery(
        "SELECT TOP 1 CreatedAt, ResponseTimeMs FROM API_LOG WHERE Status = 'success' ORDER BY CreatedAt DESC"
    ));
    if ($lastSuccess) {
        $status['last_success'] = $lastSuccess['CreatedAt'];
        $status['online'] = true;
    }

    // Last error
    $lastError = dbFetchOne(dbQuery(
        "SELECT TOP 1 CreatedAt, ErrorMessage FROM API_LOG WHERE Status = 'error' ORDER BY CreatedAt DESC"
    ));
    if ($lastError) {
        $status['last_error'] = $lastError['CreatedAt'];
        if ($lastSuccess && $lastError['CreatedAt'] instanceof DateTime && $lastSuccess['CreatedAt'] instanceof DateTime) {
            if ($lastError['CreatedAt'] > $lastSuccess['CreatedAt']) {
                $status['online'] = false;
            }
        }
    }

    // Average response time (last 50 calls)
    $avg = dbScalar(
        "SELECT AVG(CAST(ResponseTimeMs AS FLOAT)) FROM (SELECT TOP 50 ResponseTimeMs FROM API_LOG WHERE Status = 'success' ORDER BY CreatedAt DESC) t"
    );
    $status['avg_response_time'] = round($avg ?: 0);

    // Today's counts
    $status['searches_today'] = dbScalar(
        "SELECT COUNT(*) FROM API_LOG WHERE Endpoint = ? AND CAST(CreatedAt AS DATE) = CAST(GETDATE() AS DATE)",
        [MEDSTER_SEARCH_ENDPOINT]
    );
    $status['details_today'] = dbScalar(
        "SELECT COUNT(*) FROM API_LOG WHERE Endpoint = ? AND CAST(CreatedAt AS DATE) = CAST(GETDATE() AS DATE)",
        [MEDSTER_DETAILS_ENDPOINT]
    );

    $status['message'] = $status['online']
        ? 'API is operational'
        : 'API appears to be down - using cache where available';

    return $status;
}

// =============================================================================
// PRESCRIPTION ITEM FUNCTIONS
// =============================================================================

/**
 * Save prescription items
 *
 * @param int $prescriptionId Parent prescription ID
 * @param array $items Array of medicine items
 * @return bool
 */
function savePrescriptionItems($prescriptionId, $items) {
    // Check if PRESCRIPTION_ITEM table exists
    $check = dbQuery("SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'PRESCRIPTION_ITEM'");
    if (!$check || !dbFetchOne($check)) return false;

    dbBeginTrans();

    // Delete existing items for this prescription
    dbQuery("DELETE FROM PRESCRIPTION_ITEM WHERE PrescriptionID = ?", [$prescriptionId]);

    // Insert new items
    foreach ($items as $item) {
        $stmt = dbQuery(
            "INSERT INTO PRESCRIPTION_ITEM
             (PrescriptionID, MedicineApiID, MedicineName, Price, Dosage, Frequency, Duration, Quantity, Instructions, CreatedAt)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, GETDATE())",
            [
                $prescriptionId,
                $item['medicine_api_id'] ?? null,
                $item['medicine_name'] ?? '',
                $item['price'] ?? null,
                $item['dosage'] ?? null,
                $item['frequency'] ?? null,
                $item['duration'] ?? null,
                !empty($item['quantity']) ? (int)$item['quantity'] : null,
                $item['instructions'] ?? null,
            ]
        );
        if (!$stmt) {
            dbRollback();
            return false;
        }
    }

    dbCommit();
    return true;
}

/**
 * Get prescription items for a prescription
 *
 * @param int $prescriptionId
 * @return array
 */
function getPrescriptionItems($prescriptionId) {
    $check = dbQuery("SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'PRESCRIPTION_ITEM'");
    if (!$check || !dbFetchOne($check)) return [];

    return dbFetchAll(dbQuery(
        "SELECT * FROM PRESCRIPTION_ITEM WHERE PrescriptionID = ? ORDER BY PrescriptionItemID",
        [$prescriptionId]
    ));
}

// =============================================================================
// AUDIT LOG
// =============================================================================

/**
 * Log an audit action
 *
 * @param string $userName Who performed the action
 * @param string $actionType e.g. 'create', 'update', 'delete', 'search'
 * @param string $entityType e.g. 'patient', 'prescription', 'medicine'
 * @param string $entityID The affected entity ID
 * @param string $description Human-readable description
 */
function logAudit($userName, $actionType, $entityType, $entityID, $description = '') {
    $check = dbQuery("SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'AUDIT_LOG'");
    if (!$check || !dbFetchOne($check)) return;

    dbQuery(
        "INSERT INTO AUDIT_LOG (UserName, ActionType, EntityType, EntityID, Description, CreatedAt) VALUES (?, ?, ?, ?, ?, GETDATE())",
        [$userName ?: 'System', $actionType, $entityType, $entityID, $description]
    );
}

/**
 * Get recent audit log entries
 *
 * @param int $limit
 * @return array
 */
function getAuditLog($limit = 100) {
    $check = dbQuery("SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'AUDIT_LOG'");
    if (!$check || !dbFetchOne($check)) return [];

    return dbFetchAll(dbQuery(
        "SELECT TOP " . (int)$limit . " * FROM AUDIT_LOG ORDER BY CreatedAt DESC"
    ));
}

// =============================================================================
// PRICE PARSING
// =============================================================================

/**
 * Parse price string to extract numeric value
 * Handles formats like "Rs. 36.0", "$50.00", "1200 PKR"
 *
 * @param string $priceString
 * @return float
 */
function parsePrice($priceString) {
    if (empty($priceString)) return 0.0;
    $cleaned = preg_replace('/[^0-9.]/', '', $priceString);
    return (float) $cleaned;
}

/**
 * Format medicine price for display
 *
 * @param string $price
 * @return string
 */
function fmtMedicinePrice($price) {
    if (empty($price)) return 'Price unavailable';
    return '<span class="price-badge">' . e($price) . '</span>';
}
