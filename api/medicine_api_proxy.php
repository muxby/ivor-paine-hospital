<?php
/**
 * Medicine API Proxy
 * Secure proxy for Medster API calls with caching and logging
 * All medicine search/detail requests go through this endpoint
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/medicine_api.php';

header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'search':
        $query = $_GET['q'] ?? '';
        logAudit('System', 'search', 'medicine', $query, "Searched medicines for: $query");
        echo json_encode(searchMedicines($query));
        break;

    case 'details':
        $id = $_GET['id'] ?? '';
        logAudit('System', 'view', 'medicine', $id, "Viewed medicine details for: $id");
        echo json_encode(getMedicineDetails($id));
        break;

    case 'health':
        echo json_encode(getApiHealthStatus());
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
}
