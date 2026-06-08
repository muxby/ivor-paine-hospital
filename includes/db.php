<?php
/**
 * Ivor Paine Memorial Hospital - Database Connection
 * Secure SQL Server connection with error handling
 */

$serverName = "Lenovo-P52\\SQLEXPRESS";
$connectionOptions = array(
    "Database" => "IvorPaineHospital",
    "Uid" => "",
    "PWD" => "",
    "TrustServerCertificate" => true,
    "CharacterSet" => "UTF-8"
);

$conn = sqlsrv_connect($serverName, $connectionOptions);

if ($conn === false) {
    error_log("Database connection failed: " . print_r(sqlsrv_errors(), true));
    // Don't expose raw errors to users
    die("<div style='padding:40px;text-align:center;font-family:Inter,sans-serif'>
        <h2 style='color:#dc2626'>System Unavailable</h2>
        <p>Unable to connect to the hospital database. Please contact IT support.</p>
    </div>");
}

/**
 * Execute a parameterized query safely
 */
function dbQuery($sql, $params = []) {
    global $conn;
    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) {
        $errors = sqlsrv_errors();
        error_log("SQL Error: " . ($errors[0]['message'] ?? 'Unknown error'));
        return false;
    }
    return $stmt;
}

/**
 * Fetch all rows from a query result
 */
function dbFetchAll($stmt) {
    $rows = [];
    if ($stmt) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $rows[] = $row;
        }
    }
    return $rows;
}

/**
 * Fetch a single row
 */
function dbFetchOne($stmt) {
    if ($stmt) {
        return sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    }
    return null;
}

/**
 * Get a single scalar value
 */
function dbScalar($sql, $params = []) {
    $stmt = dbQuery($sql, $params);
    if ($stmt) {
        $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_NUMERIC);
        return (int)($row[0] ?? 0);
    }
    return 0;
}

/**
 * Begin a transaction
 */
function dbBeginTrans() {
    global $conn;
    sqlsrv_begin_transaction($conn);
}

/**
 * Commit a transaction
 */
function dbCommit() {
    global $conn;
    sqlsrv_commit($conn);
}

/**
 * Rollback a transaction
 */
function dbRollback() {
    global $conn;
    sqlsrv_rollback($conn);
}
