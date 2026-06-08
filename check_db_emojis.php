<?php
require_once 'includes/db.php';

echo "=== SPECIALTY ===\n";
$r = dbQuery("SELECT SpecName FROM SPECIALTY");
while ($row = sqlsrv_fetch_array($r, SQLSRV_FETCH_ASSOC)) echo $row['SpecName'] . "\n";

echo "=== WARD ===\n";
$r = dbQuery("SELECT WardName FROM WARD");
while ($row = sqlsrv_fetch_array($r, SQLSRV_FETCH_ASSOC)) echo $row['WardName'] . "\n";
