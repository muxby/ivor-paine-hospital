<?php
require_once 'includes/db.php';

echo "=== TREATMENT_NOTES columns ===\n";
$r = dbQuery("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='TREATMENT_NOTES'");
while ($row = sqlsrv_fetch_array($r, SQLSRV_FETCH_ASSOC)) echo $row['COLUMN_NAME'] . "\n";

echo "=== APPT_TREAT columns ===\n";
$r2 = dbQuery("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='APPT_TREAT'");
while ($row = sqlsrv_fetch_array($r2, SQLSRV_FETCH_ASSOC)) echo $row['COLUMN_NAME'] . "\n";

echo "=== APPT_COMPL columns ===\n";
$r3 = dbQuery("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='APPT_COMPL'");
while ($row = sqlsrv_fetch_array($r3, SQLSRV_FETCH_ASSOC)) echo $row['COLUMN_NAME'] . "\n";

echo "=== COMPLAINT columns ===\n";
$r4 = dbQuery("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='COMPLAINT'");
while ($row = sqlsrv_fetch_array($r4, SQLSRV_FETCH_ASSOC)) echo $row['COLUMN_NAME'] . "\n";
