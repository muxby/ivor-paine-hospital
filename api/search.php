<?php
/**
 * Global Search API - Returns JSON results for patients, doctors, appointments
 */
header('Content-Type: application/json');
require_once dirname(__DIR__) . '/includes/db.php';

$q = trim($_GET['q'] ?? '');
if (strlen($q) < 2) { echo json_encode([]); exit; }

$like = '%' . $q . '%';
$results = [];

// Patients
$patients = dbFetchAll(dbQuery(
    "SELECT TOP 5 PatientID, PatientName, Gender, BedNumber FROM PATIENT WHERE PatientName LIKE ?",
    [$like]
));
$patientItems = [];
foreach ($patients as $p) {
    $patientItems[] = [
        'name' => $p['PatientName'],
        'url'  => 'patients.php?view=' . $p['PatientID'],
        'sub'  => ($p['BedNumber'] ? 'Admitted · Bed ' . $p['BedNumber'] : 'Outpatient') . ' · ' . $p['Gender'],
        'icon' => '<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"/></svg>'
    ];
}
if ($patientItems) $results['Patients'] = $patientItems;

// Doctors
$doctors = dbFetchAll(dbQuery(
    "SELECT TOP 5 d.StaffID, s.StaffName, d.Position FROM DOCTOR d JOIN STAFF s ON d.StaffID = s.StaffID WHERE s.StaffName LIKE ?",
    [$like]
));
$doctorItems = [];
foreach ($doctors as $d) {
    $doctorItems[] = [
        'name' => $d['StaffName'],
        'url'  => 'doctors.php?view=' . $d['StaffID'],
        'sub'  => $d['Position'],
        'icon' => '<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.26 10.147a60.436 60.436 0 00-.491 6.347A48.627 48.627 0 0112 20.904a48.627 48.627 0 018.232-4.41 60.46 60.46 0 00-.491-6.347m-15.482 0a50.57 50.57 0 00-2.658-.813A59.905 59.905 0 0112 3.493a59.902 59.902 0 0110.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.697 50.697 0 0112 13.489a50.702 50.702 0 017.74-3.342M6.75 15a.75.75 0 100-1.5.75.75 0 000 1.5zm0 0v-3.675A55.378 55.378 0 0112 8.443m-7.007 11.55A5.981 5.981 0 006.75 15.75v-1.5"/></svg>'
    ];
}
if ($doctorItems) $results['Doctors'] = $doctorItems;

// Appointments
$appts = dbFetchAll(dbQuery(
    "SELECT TOP 5 a.ApptID, p.PatientName, s.StaffName AS DoctorName, a.ApptDate, a.Status
     FROM APPOINTMENT a
     JOIN PATIENT p ON a.PatientID = p.PatientID
     JOIN DOCTOR d ON a.DoctorID = d.StaffID
     JOIN STAFF s ON d.StaffID = s.StaffID
     WHERE p.PatientName LIKE ? OR s.StaffName LIKE ?
     ORDER BY a.ApptDate DESC",
    [$like, $like]
));
$apptItems = [];
foreach ($appts as $a) {
    $date = $a['ApptDate'] instanceof DateTime ? $a['ApptDate']->format('M j, Y') : date('M j, Y', strtotime($a['ApptDate']));
    $apptItems[] = [
        'name' => $a['PatientName'] . ' with ' . $a['DoctorName'],
        'url'  => 'appointments.php?view=' . $a['ApptID'],
        'sub'  => $date . ' · ' . $a['Status'],
        'icon' => '<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5"/></svg>'
    ];
}
if ($apptItems) $results['Appointments'] = $apptItems;

echo json_encode($results);
