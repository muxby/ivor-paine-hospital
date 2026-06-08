<?php
require_once 'includes/db.php';
require_once 'includes/helpers.php';

echo "=== PRE-TEST METRICS ===\n";
$totalPatientsBefore = dbScalar("SELECT COUNT(*) FROM PATIENT");
$occupiedBedsBefore = dbScalar("SELECT COUNT(*) FROM BED WHERE Status = 'Occupied'");
$availBedsBefore = dbScalar("SELECT COUNT(*) FROM BED WHERE Status = 'Available'");

echo "Total Patients: $totalPatientsBefore\n";
echo "Occupied Beds: $occupiedBedsBefore\n";
echo "Available Beds: $availBedsBefore\n\n";

echo "=== FINDING AN AVAILABLE BED ===\n";
$availBed = dbFetchOne(dbQuery("SELECT TOP 1 BedNumber FROM BED WHERE Status = 'Available' ORDER BY BedNumber"));

if (!$availBed) {
    die("No available beds found to run the test.\n");
}

$bedToAssign = $availBed['BedNumber'];
echo "Found available bed: $bedToAssign\n\n";

echo "=== SIMULATING PATIENT REGISTRATION ===\n";
$name = "Test Patient AI_" . time();
$dob = "1990-01-01";
$gender = "Male"; // Changed to match likely check constraint
$address = "123 AI Test Street";
$admitted = date('Y-m-d');
$bed = $bedToAssign;

dbBeginTrans();

$maxId = dbScalar("SELECT MAX(PatientID) FROM PATIENT");
$newId = ($maxId ? $maxId : 0) + 1;

$stmt = dbQuery(
    "INSERT INTO PATIENT (PatientID, PatientName, DOB, Gender, Address, DateAdmitted, BedNumber) VALUES (?, ?, ?, ?, ?, ?, ?)",
    [$newId, $name, $dob, $gender, $address, $admitted, $bed]
);

if ($stmt) {
    dbQuery("UPDATE BED SET Status='Occupied' WHERE BedNumber=?", [$bed]);
    dbCommit();
    echo "Patient '$name' successfully registered and assigned to Bed $bed.\n\n";
} else {
    dbRollback();
    die("Failed to register test patient.\n");
}

echo "=== POST-TEST METRICS ===\n";
$totalPatientsAfter = dbScalar("SELECT COUNT(*) FROM PATIENT");
$occupiedBedsAfter = dbScalar("SELECT COUNT(*) FROM BED WHERE Status = 'Occupied'");
$availBedsAfter = dbScalar("SELECT COUNT(*) FROM BED WHERE Status = 'Available'");

echo "Total Patients: $totalPatientsAfter (Expected: " . ($totalPatientsBefore + 1) . ")\n";
echo "Occupied Beds: $occupiedBedsAfter (Expected: " . ($occupiedBedsBefore + 1) . ")\n";
echo "Available Beds: $availBedsAfter (Expected: " . ($availBedsBefore - 1) . ")\n\n";

if (
    $totalPatientsAfter == $totalPatientsBefore + 1 &&
    $occupiedBedsAfter == $occupiedBedsBefore + 1 &&
    $availBedsAfter == $availBedsBefore - 1
) {
    echo "SUCCESS: The database and analytics are fully dynamic and updating accurately!\n";
} else {
    echo "ERROR: The metrics did not update exactly as expected.\n";
}
