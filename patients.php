<?php
/**
 * Patient Directory - Premium patient management
 * Features: List, search, register, edit, discharge, profile view, bed assignment
 */
require_once 'includes/db.php';
require_once 'includes/helpers.php';

$pageTitle = 'Patient Directory';
$pageSubtitle = 'Manage patient records, admissions, and bed assignments';

$successMsg = $errorMsg = '';
$viewPatient = isset($_GET['view']) ? (int)$_GET['view'] : 0;

// ── ACTIONS ──

// Register patient
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register_patient'])) {
    $name = trim($_POST['PatientName'] ?? '');
    $dob = $_POST['DOB'] ?? '';
    $gender = $_POST['Gender'] ?? '';
    $address = trim($_POST['Address'] ?? '');
    $admitted = $_POST['DateAdmitted'] ?? date('Y-m-d');
    $bed = !empty($_POST['BedNumber']) ? (int)$_POST['BedNumber'] : null;

    if (!$name || !$dob || !$gender || !$address) {
        $errorMsg = 'Please fill in all required fields.';
    } else {
        dbBeginTrans();
        $maxId = dbScalar("SELECT MAX(PatientID) FROM PATIENT");
        $newId = ($maxId ? $maxId : 0) + 1;

        $stmt = dbQuery(
            "INSERT INTO PATIENT (PatientID, PatientName, DOB, Gender, Address, DateAdmitted, BedNumber) VALUES (?, ?, ?, ?, ?, ?, ?)",
            [$newId, $name, $dob, $gender, $address, $admitted, $bed]
        );
        if ($stmt) {
            if ($bed) dbQuery("UPDATE BED SET Status='Occupied' WHERE BedNumber=?", [$bed]);
            dbCommit();
            $successMsg = "Patient <strong>" . e($name) . "</strong> registered successfully.";
        } else {
            dbRollback();
            $errorMsg = 'Failed to register patient. Please try again.';
        }
    }
}

// Edit patient
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_patient'])) {
    $pid = (int)$_POST['PatientID'];
    $name = trim($_POST['PatientName'] ?? '');
    $dob = $_POST['DOB'] ?? '';
    $gender = $_POST['Gender'] ?? '';
    $address = trim($_POST['Address'] ?? '');
    $newBed = !empty($_POST['BedNumber']) ? (int)$_POST['BedNumber'] : null;

    // Get current bed
    $current = dbFetchOne(dbQuery("SELECT BedNumber FROM PATIENT WHERE PatientID=?", [$pid]));
    $oldBed = $current['BedNumber'] ?? null;

    dbBeginTrans();
    $stmt = dbQuery(
        "UPDATE PATIENT SET PatientName=?, DOB=?, Gender=?, Address=?, BedNumber=? WHERE PatientID=?",
        [$name, $dob, $gender, $address, $newBed, $pid]
    );
    if ($stmt) {
        if ($oldBed && $oldBed != $newBed) dbQuery("UPDATE BED SET Status='Available' WHERE BedNumber=?", [$oldBed]);
        if ($newBed && $oldBed != $newBed) dbQuery("UPDATE BED SET Status='Occupied' WHERE BedNumber=?", [$newBed]);
        if (!$newBed && $oldBed) dbQuery("UPDATE BED SET Status='Available' WHERE BedNumber=?", [$oldBed]);
        dbCommit();
        $successMsg = "Patient <strong>" . e($name) . "</strong> updated successfully.";
    } else {
        dbRollback();
        $errorMsg = 'Failed to update patient.';
    }
}

// Discharge patient
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['discharge_patient'])) {
    $pid = (int)$_POST['PatientID'];
    $p = dbFetchOne(dbQuery("SELECT PatientName, BedNumber FROM PATIENT WHERE PatientID=?", [$pid]));
    if ($p) {
        $bed = $p['BedNumber'];
        dbBeginTrans();
        dbQuery("UPDATE PATIENT SET BedNumber=NULL WHERE PatientID=?", [$pid]);
        if ($bed) dbQuery("UPDATE BED SET Status='Available' WHERE BedNumber=?", [$bed]);
        dbCommit();
        $successMsg = "Patient <strong>" . e($p['PatientName']) . "</strong> discharged successfully.";
    }
}

// Pagination
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = isset($_GET['perPage']) ? (int)$_GET['perPage'] : 15;
$totalPatients = dbScalar("SELECT COUNT(*) FROM PATIENT");
list($offset, $currentPage, $totalPages) = paginate($totalPatients, $perPage, $page);

// Patient list
$patientList = dbFetchAll(dbQuery(
    "SELECT p.PatientID, p.PatientName, p.DOB, p.Gender, p.Address, p.DateAdmitted, p.BedNumber, b.BedType, b.WardName, b.Status AS BedStatus
     FROM PATIENT p
     LEFT JOIN BED b ON p.BedNumber = b.BedNumber
     ORDER BY p.PatientID DESC
     OFFSET ? ROWS FETCH NEXT ? ROWS ONLY",
    [$offset, $perPage]
));

// Available beds for dropdowns
$availBeds = dbFetchAll(dbQuery(
    "SELECT BedNumber, BedType, WardName FROM BED WHERE Status = 'Available' ORDER BY WardName, BedNumber"
));

// All beds for edit dropdown (include currently assigned)
$allBeds = dbFetchAll(dbQuery(
    "SELECT BedNumber, BedType, WardName, Status FROM BED ORDER BY WardName, BedNumber"
));

// Patient detail for modal
$patientDetail = null;
$patientAppts = $patientComplaints = $patientTreatments = $patientPrescriptions = [];
if ($viewPatient) {
    $patientDetail = dbFetchOne(dbQuery(
        "SELECT p.*, b.BedType, b.WardName, b.UnitCode, b.Status AS BedStatus, w.WardFloor
         FROM PATIENT p
         LEFT JOIN BED b ON p.BedNumber = b.BedNumber
         LEFT JOIN WARD w ON b.WardName = w.WardName
         WHERE p.PatientID = ?",
        [$viewPatient]
    ));

    if ($patientDetail) {
        $patientAppts = dbFetchAll(dbQuery(
            "SELECT a.ApptID, a.ApptDate, a.ApptTime, a.Status, a.Purpose, s.StaffName AS DoctorName
             FROM APPOINTMENT a
             JOIN DOCTOR d ON a.DoctorID = d.StaffID
             JOIN STAFF s ON d.StaffID = s.StaffID
             WHERE a.PatientID = ? ORDER BY a.ApptDate DESC, a.ApptTime DESC",
            [$viewPatient]
        ));

        $patientComplaints = dbFetchAll(dbQuery(
            "SELECT DISTINCT c.ComplaintID, c.Description, c.Severity, c.DateReported, c.DateResolved
             FROM COMPLAINT c
             JOIN APPT_COMPL ac ON c.ComplaintID = ac.ComplaintID
             JOIN APPOINTMENT a ON ac.ApptID = a.ApptID
             WHERE a.PatientID = ? ORDER BY c.DateReported DESC",
            [$viewPatient]
        ));

        $patientTreatments = dbFetchAll(dbQuery(
            "SELECT DISTINCT t.TreatmentID, t.TreatmentName, t.TreatmentType, t.Cost, t.StartDate, t.EndDate, t.Status
             FROM PATIENT_TREATMENT t
             JOIN APPT_TREAT at ON t.TreatmentID = at.TreatmentID
             JOIN APPOINTMENT a ON at.ApptID = a.ApptID
             WHERE a.PatientID = ? ORDER BY t.StartDate DESC",
            [$viewPatient]
        ));

        $patientPrescriptions = dbFetchAll(dbQuery(
            "SELECT pr.PrescriptionID, pr.Medication, pr.Dosage, pr.Frequency, pr.IssuedDate, a.ApptID
             FROM PRESCRIPTION pr
             JOIN APPOINTMENT a ON pr.ApptID = a.ApptID
             WHERE a.PatientID = ? ORDER BY pr.IssuedDate DESC",
            [$viewPatient]
        ));
    }
}

$actions = '<button class="btn btn-primary" onclick="openModal(\'registerModal\')">' .
    '<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>' .
    'Register Patient</button>';

include 'components/header.php';
?>

<?php include 'components/sidebar.php'; ?>

<div class="main-content">
    <?php include 'components/topbar.php'; ?>

    <?php if ($successMsg): echo toastScript('success', strip_tags($successMsg)); endif; ?>
    <?php if ($errorMsg): echo toastScript('error', $errorMsg); endif; ?>

    <!-- Filters -->
    <div class="section-header">
        <h2>All Patients <span class="text-muted" style="font-weight:400;">(<?php echo $totalPatients; ?>)</span></h2>
        <div class="section-filters">
            <div class="search-wrap">
                <svg class="search-icon" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/></svg>
                <input type="text" id="searchInput" placeholder="Search patients..." oninput="filterTable('searchInput', 'patientTable')">
            </div>
            <select class="form-control" style="width: auto;" id="statusFilter" onchange="filterStatus()">
                <option value="">All Status</option>
                <option value="admitted">Admitted</option>
                <option value="outpatient">Outpatient</option>
            </select>
            <button class="btn btn-sm btn-secondary" onclick="exportCSV('patients', 'patientTable')">
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                CSV
            </button>
        </div>
    </div>

    <!-- Patient Table -->
    <div class="table-card">
        <div class="table-responsive">
            <table class="data-table" id="patientTable">
                <thead>
                    <tr>
                        <th onclick="sortTable('patientTable', 0)" class="sortable">ID</th>
                        <th onclick="sortTable('patientTable', 1)" class="sortable">Patient</th>
                        <th onclick="sortTable('patientTable', 2)" class="sortable">Age</th>
                        <th onclick="sortTable('patientTable', 3)" class="sortable">Gender</th>
                        <th onclick="sortTable('patientTable', 4)" class="sortable">Admitted</th>
                        <th onclick="sortTable('patientTable', 5)" class="sortable">Bed / Ward</th>
                        <th style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($patientList as $p):
                        $dob = $p['DOB'] instanceof DateTime ? $p['DOB']->format('Y-m-d') : $p['DOB'];
                        $admitted = $p['DateAdmitted'] instanceof DateTime ? $p['DateAdmitted']->format('Y-m-d') : $p['DateAdmitted'];
                        $hasBed = $p['BedNumber'] !== null;
                    ?>
                    <tr data-status="<?php echo $hasBed ? 'admitted' : 'outpatient'; ?>">
                        <td><span class="id-tag">#<?php echo $p['PatientID']; ?></span></td>
                        <td>
                            <div class="name-cell">
                                <?php echo avatar($p['PatientName'], 32, 'patient'); ?>
                                <div>
                                    <div class="name"><?php echo e($p['PatientName']); ?></div>
                                    <div class="text-xs text-muted"><?php echo e(substr($p['Address'], 0, 40)); ?></div>
                                </div>
                            </div>
                        </td>
                        <td><?php echo calcAge($dob); ?></td>
                        <td><?php echo genderBadge($p['Gender']); ?></td>
                        <td><?php echo fmtDate($admitted); ?></td>
                        <td>
                            <?php if ($hasBed): ?>
                                <div><?php echo badge('Bed ' . $p['BedNumber'], 'blue'); ?></div>
                                <div class="text-xs text-muted"><?php echo e($p['WardName']); ?> &middot; <?php echo e($p['BedType']); ?></div>
                            <?php else: ?>
                                <?php echo badge('Outpatient', 'gray'); ?>
                            <?php endif; ?>
                        </td>
                        <td style="text-align:right;">
                            <div style="display:flex; gap:6px; justify-content:flex-end;">
                                <button class="btn btn-sm btn-secondary btn-icon-sm" onclick="viewPatient(<?php echo $p['PatientID']; ?>)" title="View profile">
                                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                </button>
                                <button class="btn btn-sm btn-secondary btn-icon-sm" onclick="editPatient(<?php echo e(json_encode($p)); ?>)" title="Edit">
                                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10"/></svg>
                                </button>
                                <?php if ($hasBed): ?>
                                <button class="btn btn-sm btn-danger btn-icon-sm" onclick="dischargePatient(<?php echo $p['PatientID']; ?>, '<?php echo e($p['PatientName']); ?>')" title="Discharge">
                                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l-3 3m0 0l3 3m-3-3h12.75"/></svg>
                                </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php echo paginationHtml($totalPages, $currentPage, 'patients.php?perPage=' . $perPage); ?>
</div>

<!-- Register Patient Modal -->
<div class="modal-overlay" id="registerModal">
    <div class="modal">
        <div class="modal-header">
            <h3>Register New Patient</h3>
            <button class="modal-close" onclick="closeModal('registerModal')">&#10005;</button>
        </div>
        <form method="POST" onsubmit="return validateForm(this)" id="registerForm">
            <input type="hidden" name="register_patient" value="1">
            <div class="modal-body">
                <div class="form-row full">
                    <div class="form-group">
                        <label>Full Name <span class="required">*</span></label>
                        <input type="text" name="PatientName" class="form-control" required placeholder="e.g. Emily Davis">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Date of Birth <span class="required">*</span></label>
                        <input type="date" name="DOB" class="form-control" required id="regDOB" onchange="calcAgePreview()">
                        <div class="form-hint" id="agePreview"></div>
                    </div>
                    <div class="form-group">
                        <label>Gender <span class="required">*</span></label>
                        <select name="Gender" class="form-control" required>
                            <option value="">Select gender</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                </div>
                <div class="form-row full">
                    <div class="form-group">
                        <label>Address <span class="required">*</span></label>
                        <input type="text" name="Address" class="form-control" required placeholder="e.g. 14 Baker Street, London">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Date Admitted <span class="required">*</span></label>
                        <input type="date" name="DateAdmitted" class="form-control" required value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="form-group">
                        <label>Assign Bed <span class="text-muted" style="font-weight:400; text-transform:none;">(optional)</span></label>
                        <select name="BedNumber" class="form-control" id="regBedSelect">
                            <option value="">Outpatient (no bed)</option>
                            <?php foreach ($availBeds as $b): ?>
                            <option value="<?php echo $b['BedNumber']; ?>" data-ward="<?php echo e($b['WardName']); ?>" data-type="<?php echo e($b['BedType']); ?>">
                                Bed <?php echo $b['BedNumber']; ?> &mdash; <?php echo e($b['BedType']); ?> (<?php echo e($b['WardName']); ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-hint" id="bedPreview"></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="closeModal('registerModal')">Cancel</button>
                <button type="submit" class="btn btn-primary" id="regSubmitBtn">
                    <span class="btn-text">Register Patient</span>
                    <span class="btn-spinner"></span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Patient Modal -->
<div class="modal-overlay" id="editModal">
    <div class="modal">
        <div class="modal-header">
            <h3>Edit Patient</h3>
            <button class="modal-close" onclick="closeModal('editModal')">&#10005;</button>
        </div>
        <form method="POST" id="editForm">
            <input type="hidden" name="edit_patient" value="1">
            <input type="hidden" name="PatientID" id="editPatientID">
            <div class="modal-body">
                <div class="form-row full">
                    <div class="form-group">
                        <label>Full Name <span class="required">*</span></label>
                        <input type="text" name="PatientName" class="form-control" required id="editPatientName">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Date of Birth <span class="required">*</span></label>
                        <input type="date" name="DOB" class="form-control" required id="editDOB">
                    </div>
                    <div class="form-group">
                        <label>Gender <span class="required">*</span></label>
                        <select name="Gender" class="form-control" required id="editGender">
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                </div>
                <div class="form-row full">
                    <div class="form-group">
                        <label>Address <span class="required">*</span></label>
                        <input type="text" name="Address" class="form-control" required id="editAddress">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Change Bed</label>
                        <select name="BedNumber" class="form-control" id="editBed">
                            <option value="">Remove bed (outpatient)</option>
                            <?php foreach ($allBeds as $b): ?>
                            <option value="<?php echo $b['BedNumber']; ?>">
                                Bed <?php echo $b['BedNumber']; ?> &mdash; <?php echo e($b['BedType']); ?> (<?php echo e($b['WardName']); ?>) [<?php echo $b['Status']; ?>]
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="closeModal('editModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- Discharge Confirm Form (hidden) -->
<form method="POST" id="dischargeForm" style="display:none;">
    <input type="hidden" name="discharge_patient" value="1">
    <input type="hidden" name="PatientID" id="dischargePatientID">
</form>

<!-- Patient Detail Modal -->
<div class="modal-overlay" id="detailModal" <?php echo $viewPatient && $patientDetail ? 'style="display:flex"' : ''; ?>>
    <div class="modal modal-lg">
        <div class="modal-header">
            <h3>Patient Profile</h3>
            <button class="modal-close" onclick="closeModal('detailModal'); window.history.replaceState({}, '', 'patients.php');">&#10005;</button>
        </div>
        <?php if ($patientDetail): ?>
        <div class="modal-body">
            <!-- Patient Header -->
            <div style="display:flex; align-items:flex-start; gap:var(--space-5); margin-bottom:var(--space-6); padding-bottom:var(--space-5); border-bottom:1px solid var(--border-light);">
                <?php echo avatar($patientDetail['PatientName'], 64, 'patient'); ?>
                <div style="flex:1;">
                    <h2 style="font-size:1.3rem; font-weight:800; margin-bottom:4px;"><?php echo e($patientDetail['PatientName']); ?></h2>
                    <div style="display:flex; gap:var(--space-4); flex-wrap:wrap; color:var(--text-muted); font-size:0.85rem;">
                        <span><strong style="color:var(--text);">ID:</strong> #<?php echo $patientDetail['PatientID']; ?></span>
                        <span><strong style="color:var(--text);">Age:</strong> <?php echo calcAge($patientDetail['DOB']); ?> years</span>
                        <span><?php echo genderBadge($patientDetail['Gender']); ?></span>
                        <span><?php echo $patientDetail['BedNumber'] ? badge('Admitted', 'blue') : badge('Outpatient', 'gray'); ?></span>
                    </div>
                    <div class="text-muted text-sm" style="margin-top:8px;"><?php echo e($patientDetail['Address']); ?></div>
                </div>
                <div style="text-align:right;">
                    <?php if ($patientDetail['BedNumber']): ?>
                    <div class="card-glass" style="padding:var(--space-4); text-align:center;">
                        <div class="text-xs text-muted">Assigned Bed</div>
                        <div style="font-size:1.5rem; font-weight:800; color:var(--primary);"><?php echo $patientDetail['BedNumber']; ?></div>
                        <div class="text-xs"><?php echo e($patientDetail['BedType']); ?></div>
                        <div class="text-xs text-muted"><?php echo e($patientDetail['WardName']); ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Tabs -->
            <div class="tabs">
                <button class="tab-btn active" data-tab="tab-appts" data-tab-group="patient" onclick="switchTab('patient', 'tab-appts')">Appointments (<?php echo count($patientAppts); ?>)</button>
                <button class="tab-btn" data-tab="tab-complaints" data-tab-group="patient" onclick="switchTab('patient', 'tab-complaints')">Complaints (<?php echo count($patientComplaints); ?>)</button>
                <button class="tab-btn" data-tab="tab-treatments" data-tab-group="patient" onclick="switchTab('patient', 'tab-treatments')">Treatments (<?php echo count($patientTreatments); ?>)</button>
                <button class="tab-btn" data-tab="tab-prescriptions" data-tab-group="patient" onclick="switchTab('patient', 'tab-prescriptions')">Prescriptions (<?php echo count($patientPrescriptions); ?>)</button>
            </div>

            <div id="tab-appts" class="tab-panel active" data-tab-panel="patient">
                <?php if ($patientAppts): ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead><tr><th>Date</th><th>Doctor</th><th>Purpose</th><th>Status</th></tr></thead>
                        <tbody>
                            <?php foreach ($patientAppts as $a): ?>
                            <tr><td><?php echo fmtDate($a['ApptDate']) . ' ' . fmtTime($a['ApptTime']); ?></td><td><?php echo e($a['DoctorName']); ?></td><td><?php echo e($a['Purpose']); ?></td><td><?php echo statusBadge($a['Status']); ?></td></tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?><div class="empty-state"><div class="empty-state-icon">&#128197;</div><h4>No appointments found</h4></div><?php endif; ?>
            </div>

            <div id="tab-complaints" class="tab-panel" data-tab-panel="patient">
                <?php if ($patientComplaints): ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead><tr><th>Complaint</th><th>Severity</th><th>Reported</th><th>Status</th></tr></thead>
                        <tbody>
                            <?php foreach ($patientComplaints as $c): ?>
                            <tr><td><?php echo e($c['Description']); ?></td><td><?php echo statusBadge($c['Severity']); ?></td><td><?php echo fmtDate($c['DateReported']); ?></td><td><?php echo $c['DateResolved'] ? badge('Resolved', 'green') : badge('Unresolved', 'amber'); ?></td></tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?><div class="empty-state"><div class="empty-state-icon">&#128203;</div><h4>No complaints recorded</h4></div><?php endif; ?>
            </div>

            <div id="tab-treatments" class="tab-panel" data-tab-panel="patient">
                <?php if ($patientTreatments): ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead><tr><th>Treatment</th><th>Type</th><th>Cost</th><th>Dates</th><th>Status</th></tr></thead>
                        <tbody>
                            <?php foreach ($patientTreatments as $t): ?>
                            <tr><td><?php echo e($t['TreatmentName']); ?></td><td><?php echo e($t['TreatmentType']); ?></td><td style="font-weight:600;"><?php echo fmtCurrency($t['Cost']); ?></td><td><?php echo fmtDate($t['StartDate']) . ($t['EndDate'] ? ' - ' . fmtDate($t['EndDate']) : ''); ?></td><td><?php echo statusBadge($t['Status']); ?></td></tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?><div class="empty-state"><div class="empty-state-icon">&#128138;</div><h4>No treatments recorded</h4></div><?php endif; ?>
            </div>

            <div id="tab-prescriptions" class="tab-panel" data-tab-panel="patient">
                <?php if ($patientPrescriptions): ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead><tr><th>Medication</th><th>Dosage</th><th>Frequency</th><th>Issued</th></tr></thead>
                        <tbody>
                            <?php foreach ($patientPrescriptions as $pr): ?>
                            <tr><td style="font-weight:600;"><?php echo e($pr['Medication']); ?></td><td><?php echo e($pr['Dosage']); ?></td><td><?php echo e($pr['Frequency']); ?></td><td><?php echo fmtDate($pr['IssuedDate']); ?></td></tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?><div class="empty-state"><div class="empty-state-icon">&#128196;</div><h4>No prescriptions recorded</h4></div><?php endif; ?>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="printPage()">
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0110.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0l.229 2.523a1.125 1.125 0 01-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0021 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 00-1.913-.247M6.34 18H5.25A2.25 2.25 0 013 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 001.913-.247m10.5 0a48.536 48.536 0 00-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5zm-3 0h.008v.008H15V10.5z"/></svg>
                Print
            </button>
            <button type="button" class="btn btn-ghost" onclick="closeModal('detailModal'); window.history.replaceState({}, '', 'patients.php');">Close</button>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'components/footer.php'; ?>

<script>
function viewPatient(id) {
    window.location.href = 'patients.php?view=' + id;
}

function editPatient(data) {
    document.getElementById('editPatientID').value = data.PatientID;
    document.getElementById('editPatientName').value = data.PatientName;
    document.getElementById('editDOB').value = data.DOB ? (data.DOB.date ? data.DOB.date.split(' ')[0] : data.DOB) : '';
    document.getElementById('editGender').value = data.Gender;
    document.getElementById('editAddress').value = data.Address;
    document.getElementById('editBed').value = data.BedNumber || '';
    openModal('editModal');
}

function dischargePatient(id, name) {
    confirmAction('Discharge Patient', 'Are you sure you want to discharge <strong>' + name + '</strong>? This will free their assigned bed.', function() {
        document.getElementById('dischargePatientID').value = id;
        document.getElementById('dischargeForm').submit();
    });
}

function calcAgePreview() {
    const dob = document.getElementById('regDOB').value;
    if (dob) {
        const age = Math.floor((new Date() - new Date(dob)) / 31557600000);
        document.getElementById('agePreview').textContent = 'Age: ' + age + ' years old';
    }
}

function filterStatus() {
    const s = document.getElementById('statusFilter').value;
    document.querySelectorAll('#patientTable tbody tr').forEach(row => {
        row.style.display = !s || row.dataset.status === s ? '' : 'none';
    });
}

function validateForm(form) {
    const btn = form.querySelector('button[type="submit"]');
    setLoading(btn, true);
    return true;
}

<?php if ($viewPatient && $patientDetail): ?>
document.addEventListener('DOMContentLoaded', () => {
    openModal('detailModal');
});
<?php endif; ?>
</script>
