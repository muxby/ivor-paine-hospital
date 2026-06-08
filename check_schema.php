<?php
require 'includes/db.php';
$tables = ['APPOINTMENT', 'COMPLAINT', 'PATIENT_TREATMENT', 'PRESCRIPTION', 'STAFF', 'WARD', 'DOCTOR', 'NURSE', 'SPECIALTY'];
foreach($tables as $t) {
    echo "Table: $t\n";
    $res = dbQuery("SELECT c.name, c.is_identity FROM sys.columns c JOIN sys.objects o ON c.object_id = o.object_id WHERE o.name = '$t'");
    $cols = dbFetchAll($res);
    $hasIdentity = false;
    foreach($cols as $c) {
        if ($c['is_identity']) {
            $hasIdentity = true;
            echo "  [IDENTITY] " . $c['name'] . "\n";
        }
    }
    if (!$hasIdentity) {
        echo "  [NO IDENTITY COLUMNS]\n";
    }
}
