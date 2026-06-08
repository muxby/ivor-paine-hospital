<?php
require_once 'includes/db.php';

echo "=== HOSPITAL DB INSERTION TEST ===\n";

// - [x] Task 1: Identify all failing forms due to `IDENTITY` issue
// - [x] Task 2: Implement manual `ID` generation for `patients.php`, `appointments.php`, `complaints.php`, `treatments.php`, `prescriptions.php`.
// - [x] Task 3: Implement Add forms for `doctors.php` and `nurses.php`, and verify registration in `wards.php`.
// - [x] Task 4: Fix enums on forms (`complaints.php` severity, `treatments.php` status) to match DB `CHECK` constraints.
// - [x] Task 5: Run tests to ensure everything is saved correctly.

dbBeginTrans();
$passed = 0;
$total = 0;

function runTest($name, $query, $params) {
    global $passed, $total;
    $total++;
    echo "Testing $name... ";
    try {
        $stmt = dbQuery($query, $params);
        if ($stmt) {
            echo "SUCCESS\n";
            $passed++;
        } else {
            echo "FAILED\n";
        }
    } catch (Exception $e) {
        echo "FAILED: " . $e->getMessage() . "\n";
    }
}

// 1. ADD DOCTOR
$maxId = dbScalar("SELECT MAX(StaffID) FROM STAFF");
$newDocId = ($maxId ? $maxId : 0) + 1;
runTest("Insert STAFF (Doctor)", "INSERT INTO STAFF (StaffID, StaffName, DateJoined) VALUES (?, ?, GETDATE())", [$newDocId, "Dr. AI Tester"]);
runTest("Insert DOCTOR", "INSERT INTO DOCTOR (StaffID, Position, LicenseNumber, SpecID) VALUES (?, 'Consultant', 'LIC-TEST', 1)", [$newDocId]);

// 2. ADD NURSE
$newNurId = $newDocId + 1;
runTest("Insert STAFF (Nurse)", "INSERT INTO STAFF (StaffID, StaffName, DateJoined) VALUES (?, ?, GETDATE())", [$newNurId, "Nurse AI Tester"]);
runTest("Insert NURSE", "INSERT INTO NURSE (StaffID, NurseType) VALUES (?, 'Registered Nurse - Staff Nurse')", [$newNurId]);

// 3. BOOK APPOINTMENT
$maxApptId = dbScalar("SELECT MAX(ApptID) FROM APPOINTMENT");
$newApptId = ($maxApptId ? $maxApptId : 0) + 1;
// Grab a random patient
$pid = dbScalar("SELECT TOP 1 PatientID FROM PATIENT");
runTest("Insert APPOINTMENT", "INSERT INTO APPOINTMENT (ApptID, ApptDate, ApptTime, Status, Purpose, PatientID, DoctorID) VALUES (?, GETDATE(), '10:00:00', 'Scheduled', 'Test', ?, ?)", [$newApptId, $pid, $newDocId]);

// 4. ADD COMPLAINT
$maxCompId = dbScalar("SELECT MAX(ComplaintID) FROM COMPLAINT");
$newCompId = ($maxCompId ? $maxCompId : 0) + 1;
runTest("Insert COMPLAINT", "INSERT INTO COMPLAINT (ComplaintID, Description, Severity, DateReported) VALUES (?, 'Test Complaint', 'Mild', GETDATE())", [$newCompId]);
runTest("Insert APPT_COMPL", "INSERT INTO APPT_COMPL (ApptID, ComplaintID) VALUES (?, ?)", [$newApptId, $newCompId]);

// 5. ADD TREATMENT
$maxTreatId = dbScalar("SELECT MAX(TreatmentID) FROM PATIENT_TREATMENT");
$newTreatId = ($maxTreatId ? $maxTreatId : 0) + 1;
runTest("Insert PATIENT_TREATMENT", "INSERT INTO PATIENT_TREATMENT (TreatmentID, TreatmentName, TreatmentType, Cost, StartDate, EndDate, Status) VALUES (?, 'Test Treatment', 'Diagnostic', 100.00, GETDATE(), NULL, 'Ongoing')", [$newTreatId]);
runTest("Insert APPT_TREAT", "INSERT INTO APPT_TREAT (ApptID, TreatmentID) VALUES (?, ?)", [$newApptId, $newTreatId]);

// 6. ADD PRESCRIPTION
$maxRxId = dbScalar("SELECT MAX(PrescriptionID) FROM PRESCRIPTION");
$newRxId = ($maxRxId ? $maxRxId : 0) + 1;
runTest("Insert PRESCRIPTION", "INSERT INTO PRESCRIPTION (PrescriptionID, ApptID, Medication, Dosage, Frequency, IssuedDate) VALUES (?, ?, 'Test Med', '10mg', '1x daily', GETDATE())", [$newRxId, $newApptId]);

// Rollback so we don't pollute the DB with test data
dbRollback();

echo "---------------------------------\n";
echo "Tests Passed: $passed / $total\n";
if ($passed === $total) {
    echo "SUCCESS: ALL ENTITIES CAN NOW BE ADDED MANUALLY WITH GENERATED IDs.\n";
} else {
    echo "ERROR: SOME TESTS FAILED.\n";
}
