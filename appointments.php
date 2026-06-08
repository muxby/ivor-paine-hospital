<?php
/**
 * Advanced Appointment Scheduling
 * Features: Calendar view, table view, conflict detection, detail modal with medical actions
 */
require_once 'includes/db.php';
require_once 'includes/helpers.php';

$pageTitle = 'Appointments';
$pageSubtitle = 'Schedule and manage patient appointments';

$successMsg = $errorMsg = '';
$viewAppt = isset($_GET['view']) ? (int)$_GET['view'] : 0;

// Book appointment with conflict detection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['book_appt'])) {
    $pid = (int)$_POST['PatientID'];
    $did = (int)$_POST['DoctorID'];
    $date = $_POST['ApptDate'];
    $time = $_POST['ApptTime'];
    $purpose = trim($_POST['Purpose'] ?? '');

    // Conflict check
    $conflict = dbScalar(
        "SELECT COUNT(*) FROM APPOINTMENT WHERE DoctorID = ? AND ApptDate = ? AND ApptTime = ? AND Status != 'Cancelled'",
        [$did, $date, $time]
    );
    if ($conflict > 0) {
        $errorMsg = 'This doctor already has an appointment at the selected date and time.';
    } else {
        $maxId = dbScalar("SELECT MAX(ApptID) FROM APPOINTMENT");
        $newApptId = ($maxId ? $maxId : 0) + 1;
        $stmt = dbQuery(
            "INSERT INTO APPOINTMENT (ApptID, ApptDate, ApptTime, Status, Purpose, PatientID, DoctorID) VALUES (?, ?, ?, 'Scheduled', ?, ?, ?)",
            [$newApptId, $date, $time, $purpose, $pid, $did]
        );
        if ($stmt) {
            $successMsg = 'Appointment booked successfully.';
        } else {
            $errorMsg = 'Failed to book appointment.';
        }
    }
}

// Update status (complete/cancel)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $apptID = (int)$_POST['ApptID'];
    $status = $_POST['Status'];
    dbQuery("UPDATE APPOINTMENT SET Status = ? WHERE ApptID = ?", [$status, $apptID]);
    $successMsg = 'Appointment status updated to ' . $status . '.';
}

// Add complaint to appointment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_complaint'])) {
    $apptID = (int)$_POST['ApptID'];
    $desc = trim($_POST['Description']);
    $severity = $_POST['Severity'];
    $maxId = dbScalar("SELECT MAX(ComplaintID) FROM COMPLAINT");
    $newComplaintId = ($maxId ? $maxId : 0) + 1;
    $stmt = dbQuery("INSERT INTO COMPLAINT (ComplaintID, Description, Severity, DateReported) VALUES (?, ?, ?, GETDATE())", [$newComplaintId, $desc, $severity]);
    if ($stmt) {
        dbQuery("INSERT INTO APPT_COMPL (ApptID, ComplaintID) VALUES (?, ?)", [$apptID, $newComplaintId]);
        $successMsg = 'Complaint added successfully.';
    }
}

// Add treatment to appointment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_treatment'])) {
    $apptID = (int)$_POST['ApptID'];
    $tname = trim($_POST['TreatmentName']);
    $ttype = $_POST['TreatmentType'];
    $cost = (float)$_POST['Cost'];
    $sdate = $_POST['StartDate'];
    $edate = $_POST['EndDate'] ?: null;
    $tstatus = $_POST['TreatmentStatus'];
    $note = trim($_POST['Note'] ?? '');

    $maxId = dbScalar("SELECT MAX(TreatmentID) FROM PATIENT_TREATMENT");
    $newTreatmentId = ($maxId ? $maxId : 0) + 1;
    $stmt = dbQuery(
        "INSERT INTO PATIENT_TREATMENT (TreatmentID, TreatmentName, TreatmentType, Cost, StartDate, EndDate, Status) VALUES (?, ?, ?, ?, ?, ?, ?)",
        [$newTreatmentId, $tname, $ttype, $cost, $sdate, $edate, $tstatus]
    );
    if ($stmt) {
        dbQuery("INSERT INTO APPT_TREAT (ApptID, TreatmentID) VALUES (?, ?)", [$apptID, $newTreatmentId]);
        if ($note) dbQuery("INSERT INTO TREATMENT_NOTES (TreatmentID, Note) VALUES (?, ?)", [$newTreatmentId, $note]);
        $successMsg = 'Treatment added successfully.';
    }
}

// Add prescription
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_prescription'])) {
    $apptID = (int)$_POST['ApptID'];
    $med = trim($_POST['Medication']);
    $dosage = trim($_POST['Dosage']);
    $freq = trim($_POST['Frequency']);
    $maxId = dbScalar("SELECT MAX(PrescriptionID) FROM PRESCRIPTION");
    $newPrescriptionId = ($maxId ? $maxId : 0) + 1;
    dbQuery(
        "INSERT INTO PRESCRIPTION (PrescriptionID, Medication, Dosage, Frequency, IssuedDate, ApptID) VALUES (?, ?, ?, ?, GETDATE(), ?)",
        [$newPrescriptionId, $med, $dosage, $freq, $apptID]
    );
    $successMsg = 'Prescription added successfully.';
}

// Filters
$statusFilter = $_GET['status'] ?? '';
$docFilter = $_GET['doctor'] ?? '';
$viewMode = $_GET['viewMode'] ?? 'table';

$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 15;

$countSql = "SELECT COUNT(*) FROM APPOINTMENT a WHERE 1=1";
$listSql = "SELECT a.ApptID, p.PatientName, s.StaffName AS DoctorName, a.ApptDate, a.ApptTime, a.Purpose, a.Status, a.PatientID, a.DoctorID
            FROM APPOINTMENT a
            JOIN PATIENT p ON a.PatientID = p.PatientID
            JOIN DOCTOR d ON a.DoctorID = d.StaffID
            JOIN STAFF s ON d.StaffID = s.StaffID
            WHERE 1=1";
$params = [];
if ($statusFilter) { $countSql .= " AND a.Status = ?"; $listSql .= " AND a.Status = ?"; $params[] = $statusFilter; }
if ($docFilter) { $countSql .= " AND a.DoctorID = ?"; $listSql .= " AND a.DoctorID = ?"; $params[] = (int)$docFilter; }
$totalAppts = dbScalar($countSql, $params);
list($offset, $currentPage, $totalPages) = paginate($totalAppts, $perPage, $page);
$listSql .= " ORDER BY a.ApptDate DESC, a.ApptTime DESC OFFSET ? ROWS FETCH NEXT ? ROWS ONLY";
$params[] = $offset; $params[] = $perPage;
$appointments = dbFetchAll(dbQuery($listSql, $params));

// Dropdown data
$patients = dbFetchAll(dbQuery("SELECT PatientID, PatientName FROM PATIENT ORDER BY PatientName"));
$doctors = dbFetchAll(dbQuery("SELECT d.StaffID, s.StaffName FROM DOCTOR d JOIN STAFF s ON d.StaffID = s.StaffID ORDER BY s.StaffName"));
$doctorFilter = dbFetchAll(dbQuery("SELECT d.StaffID, s.StaffName FROM DOCTOR d JOIN STAFF s ON d.StaffID = s.StaffID ORDER BY s.StaffName"));

// Appointment detail
$apptDetail = $apptComplaints = $apptTreatments = $apptPrescriptions = null;
if ($viewAppt) {
    $apptDetail = dbFetchOne(dbQuery(
        "SELECT a.*, p.PatientName, p.DOB, p.Gender, s.StaffName AS DoctorName, sp.SpecName, d.Position
         FROM APPOINTMENT a
         JOIN PATIENT p ON a.PatientID = p.PatientID
         JOIN DOCTOR d ON a.DoctorID = d.StaffID
         JOIN STAFF s ON d.StaffID = s.StaffID
         LEFT JOIN SPECIALTY sp ON d.SpecID = sp.SpecID
         WHERE a.ApptID = ?", [$viewAppt]
    ));
    if ($apptDetail) {
        $apptComplaints = dbFetchAll(dbQuery(
            "SELECT c.* FROM COMPLAINT c JOIN APPT_COMPL ac ON c.ComplaintID = ac.ComplaintID WHERE ac.ApptID = ?",
            [$viewAppt]
        ));
        $apptTreatments = dbFetchAll(dbQuery(
            "SELECT t.* FROM PATIENT_TREATMENT t JOIN APPT_TREAT at ON t.TreatmentID = at.TreatmentID WHERE at.ApptID = ?",
            [$viewAppt]
        ));
        $apptPrescriptions = dbFetchAll(dbQuery(
            "SELECT * FROM PRESCRIPTION WHERE ApptID = ? ORDER BY IssuedDate DESC", [$viewAppt]
        ));
    }
}

$filterHtml = '<form method="GET" style="display:flex; gap:8px; align-items:center;">' .
    '<select name="status" class="form-control" style="width:auto;" onchange="this.form.submit()">' .
    '<option value="">All Statuses</option>' .
    '<option value="Scheduled"' . ($statusFilter === 'Scheduled' ? ' selected' : '') . '>Scheduled</option>' .
    '<option value="Completed"' . ($statusFilter === 'Completed' ? ' selected' : '') . '>Completed</option>' .
    '<option value="Cancelled"' . ($statusFilter === 'Cancelled' ? ' selected' : '') . '>Cancelled</option>' .
    '</select>' .
    '<select name="doctor" class="form-control" style="width:auto;" onchange="this.form.submit()">' .
    '<option value="">All Doctors</option>';
foreach ($doctorFilter as $df) {
    $filterHtml .= '<option value="' . $df['StaffID'] . '"' . ($docFilter == $df['StaffID'] ? ' selected' : '') . '>' . e($df['StaffName']) . '</option>';
}
$filterHtml .= '</select>' .
    '<input type="hidden" name="viewMode" value="' . e($viewMode) . '">' .
    ($statusFilter || $docFilter ? ' <a href="appointments.php?viewMode=' . e($viewMode) . '" class="btn btn-sm btn-ghost">Reset</a>' : '') .
    '</form>';

$addBtn = '<div style="display:flex; gap:8px; align-items:center;">' .
    '<a href="?viewMode=' . ($viewMode === 'table' ? 'calendar' : 'table') . ($statusFilter ? '&status=' . e($statusFilter) : '') . ($docFilter ? '&doctor=' . e($docFilter) : '') . '" class="btn btn-secondary btn-sm">' . ($viewMode === 'table' ? '&#128197; Calendar' : '&#9776; Table') . '</a>' .
    '<button class="btn btn-primary" onclick="openModal(\'bookModal\')"><svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>Book Appointment</button>' .
    '</div>';

include 'components/header.php';
?>

<?php include 'components/sidebar.php'; ?>

<div class="main-content">
    <?php $actions = $filterHtml . $addBtn; include 'components/topbar.php'; ?>

    <?php if ($successMsg): echo toastScript('success', $successMsg); endif; ?>
    <?php if ($errorMsg): echo toastScript('error', $errorMsg); endif; ?>

    <?php if ($viewMode === 'calendar'): ?>
    <!-- Calendar View - Interactive JS -->
    <div class="card">
        <div class="card-header">
            <div style="display:flex; align-items:center; gap:var(--space-4);">
                <button class="btn btn-sm btn-secondary" onclick="calPrev()" id="calPrevBtn">&larr;</button>
                <h3 id="calTitle" style="min-width:200px; text-align:center;">Loading...</h3>
                <button class="btn btn-sm btn-secondary" onclick="calNext()" id="calNextBtn">&rarr;</button>
            </div>
            <button class="btn btn-sm btn-ghost" onclick="calGoToday()">Today</button>
        </div>
        <div class="card-body" style="padding: var(--space-4);">
            <div id="calendarGrid" style="display:grid; grid-template-columns: repeat(7, 1fr); gap:1px; background:var(--border); border-radius:var(--radius); overflow:hidden;"></div>
        </div>
    </div>
    <?php else: ?>
    <!-- Table View -->
    <div class="table-card">
        <div class="table-responsive">
            <table class="data-table" id="apptTable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Patient</th>
                        <th>Doctor</th>
                        <th>Date & Time</th>
                        <th>Purpose</th>
                        <th>Status</th>
                        <th style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($appointments as $a):
                        $date = $a['ApptDate'] instanceof DateTime ? $a['ApptDate']->format('Y-m-d') : $a['ApptDate'];
                    ?>
                    <tr>
                        <td><span class="id-tag">#<?php echo $a['ApptID']; ?></span></td>
                        <td><?php echo e($a['PatientName']); ?></td>
                        <td class="text-muted"><?php echo e($a['DoctorName']); ?></td>
                        <td><span style="font-weight:600;"><?php echo fmtDate($date); ?></span> <span class="text-xs text-muted"><?php echo fmtTime($a['ApptTime']); ?></span></td>
                        <td style="max-width:200px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><?php echo e($a['Purpose'] ?? '-'); ?></td>
                        <td><?php echo statusBadge($a['Status']); ?></td>
                        <td style="text-align:right;">
                            <button class="btn btn-sm btn-secondary btn-icon-sm" onclick="viewAppt(<?php echo $a['ApptID']; ?>)"><svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg></button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php echo paginationHtml($totalPages, $currentPage, 'appointments.php?status=' . e($statusFilter) . '&doctor=' . e($docFilter) . '&viewMode=' . e($viewMode)); ?>
    <?php endif; ?>
</div>

<!-- Book Modal -->
<div class="modal-overlay" id="bookModal">
    <div class="modal">
        <div class="modal-header"><h3>Book Appointment</h3><button class="modal-close" onclick="closeModal('bookModal')">&#10005;</button></div>
        <form method="POST" onsubmit="return validateBookForm()">
            <input type="hidden" name="book_appt" value="1">
            <div class="modal-body">
                <div class="form-row full">
                    <div class="form-group"><label>Patient <span class="required">*</span></label>
                        <select name="PatientID" class="form-control" required><option value="">Select...</option><?php foreach ($patients as $p) echo '<option value="' . $p['PatientID'] . '">' . e($p['PatientName']) . '</option>'; ?></select></div>
                </div>
                <div class="form-row full">
                    <div class="form-group"><label>Doctor <span class="required">*</span></label>
                        <select name="DoctorID" class="form-control" required id="bookDoctor"><option value="">Select...</option><?php foreach ($doctors as $d) echo '<option value="' . $d['StaffID'] . '">' . e($d['StaffName']) . '</option>'; ?></select></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Date <span class="required">*</span></label><input type="date" name="ApptDate" class="form-control" required value="<?php echo date('Y-m-d'); ?>"></div>
                    <div class="form-group"><label>Time <span class="required">*</span></label><input type="time" name="ApptTime" class="form-control" required value="09:00"></div>
                </div>
                <div class="form-row full">
                    <div class="form-group"><label>Purpose</label><input type="text" name="Purpose" class="form-control" placeholder="e.g. Routine check-up"></div>
                </div>
                <div class="form-error" id="bookError" style="display:none; color:var(--danger);"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="closeModal('bookModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Book</button>
            </div>
        </form>
    </div>
</div>

<!-- Detail Modal -->
<div class="modal-overlay" id="detailModal" <?php echo $viewAppt && $apptDetail ? 'style="display:flex"' : ''; ?>>
    <div class="modal modal-xl">
        <div class="modal-header"><h3>Appointment Details</h3><button class="modal-close" onclick="closeModal('detailModal'); window.history.replaceState({}, '', 'appointments.php');">&#10005;</button></div>
        <?php if ($apptDetail): ?>
        <div class="modal-body">
            <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:var(--space-4); margin-bottom:var(--space-5); padding-bottom:var(--space-4); border-bottom:1px solid var(--border-light);">
                <div style="display:flex; gap:var(--space-4); align-items:center;">
                    <?php echo avatar($apptDetail['PatientName'], 48, 'patient'); ?>
                    <div><div style="font-size:1.1rem; font-weight:700;"><?php echo e($apptDetail['PatientName']); ?></div><div class="text-sm text-muted">Age <?php echo calcAge($apptDetail['DOB']); ?> &middot; <?php echo e($apptDetail['Gender']); ?></div></div>
                </div>
                <div style="text-align:right;">
                    <div style="font-size:1.5rem; font-weight:800;">#<?php echo $apptDetail['ApptID']; ?></div>
                    <div class="text-sm text-muted"><?php echo fmtDate($apptDetail['ApptDate']); ?> at <?php echo fmtTime($apptDetail['ApptTime']); ?></div>
                    <div style="margin-top:4px;"><?php echo statusBadge($apptDetail['Status']); ?></div>
                </div>
            </div>
            <div style="margin-bottom:var(--space-4);"><strong>Doctor:</strong> <?php echo e($apptDetail['DoctorName']); ?> &middot; <?php echo e($apptDetail['Position']); ?> &middot; <?php echo e($apptDetail['SpecName'] ?? 'General'); ?></div>
            <?php if ($apptDetail['Purpose']): ?><div style="margin-bottom:var(--space-4);"><strong>Purpose:</strong> <?php echo e($apptDetail['Purpose']); ?></div><?php endif; ?>

            <div class="tabs">
                <button class="tab-btn active" data-tab="appt-complaints" data-tab-group="appt" onclick="switchTab('appt', 'appt-complaints')">Complaints</button>
                <button class="tab-btn" data-tab="appt-treatments" data-tab-group="appt" onclick="switchTab('appt', 'appt-treatments')">Treatments</button>
                <button class="tab-btn" data-tab="appt-prescriptions" data-tab-group="appt" onclick="switchTab('appt', 'appt-prescriptions')">Prescriptions</button>
                <button class="tab-btn" data-tab="appt-actions" data-tab-group="appt" onclick="switchTab('appt', 'appt-actions')">Actions</button>
            </div>

            <div id="appt-complaints" class="tab-panel active" data-tab-panel="appt">
                <?php if ($apptComplaints): ?>
                <table class="data-table"><thead><tr><th>Description</th><th>Severity</th><th>Reported</th><th>Status</th></tr></thead>
                <tbody><?php foreach ($apptComplaints as $c): ?><tr><td><?php echo e($c['Description']); ?></td><td><?php echo statusBadge($c['Severity']); ?></td><td><?php echo fmtDate($c['DateReported']); ?></td><td><?php echo $c['DateResolved'] ? badge('Resolved', 'green') : badge('Open', 'amber'); ?></td></tr><?php endforeach; ?></tbody></table>
                <?php else: ?><div class="empty-state"><div class="empty-state-icon">&#128203;</div><h4>No complaints</h4></div><?php endif; ?>
            </div>

            <div id="appt-treatments" class="tab-panel" data-tab-panel="appt">
                <?php if ($apptTreatments): ?>
                <table class="data-table"><thead><tr><th>Treatment</th><th>Type</th><th>Cost</th><th>Period</th><th>Status</th></tr></thead>
                <tbody><?php foreach ($apptTreatments as $t): ?><tr><td><?php echo e($t['TreatmentName']); ?></td><td><?php echo e($t['TreatmentType']); ?></td><td><?php echo fmtCurrency($t['Cost']); ?></td><td><?php echo fmtDate($t['StartDate']) . ($t['EndDate'] ? ' - ' . fmtDate($t['EndDate']) : ''); ?></td><td><?php echo statusBadge($t['Status']); ?></td></tr><?php endforeach; ?></tbody></table>
                <?php else: ?><div class="empty-state"><div class="empty-state-icon">&#128138;</div><h4>No treatments</h4></div><?php endif; ?>
            </div>

            <div id="appt-prescriptions" class="tab-panel" data-tab-panel="appt">
                <?php if ($apptPrescriptions): ?>
                <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(220px, 1fr)); gap:var(--space-4);">
                    <?php foreach ($apptPrescriptions as $pr): ?>
                    <div class="card" style="border-left:3px solid var(--primary);"><div class="card-body" style="padding:var(--space-4);">
                        <div style="font-weight:700; font-size:1rem; margin-bottom:4px;"><?php echo e($pr['Medication']); ?></div>
                        <div class="text-sm text-muted"><?php echo e($pr['Dosage']); ?> &middot; <?php echo e($pr['Frequency']); ?></div>
                        <div class="text-xs text-faint" style="margin-top:8px;">Issued: <?php echo fmtDate($pr['IssuedDate']); ?></div>
                    </div></div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?><div class="empty-state"><div class="empty-state-icon">&#128196;</div><h4>No prescriptions</h4></div><?php endif; ?>
            </div>

            <div id="appt-actions" class="tab-panel" data-tab-panel="appt">
                <div style="display:grid; grid-template-columns:repeat(2, 1fr); gap:var(--space-5);">
                    <!-- Status Actions -->
                    <div class="card"><div class="card-header"><h3>Update Status</h3></div><div class="card-body">
                        <form method="POST"><input type="hidden" name="update_status" value="1"><input type="hidden" name="ApptID" value="<?php echo $viewAppt; ?>">
                            <div style="display:flex; gap:8px;">
                                <select name="Status" class="form-control"><option value="Scheduled">Scheduled</option><option value="Completed">Completed</option><option value="Cancelled">Cancelled</option></select>
                                <button type="submit" class="btn btn-primary">Update</button>
                            </div>
                        </form>
                    </div></div>
                    <!-- Add Complaint -->
                    <div class="card"><div class="card-header"><h3>Add Complaint</h3></div><div class="card-body">
                        <form method="POST"><input type="hidden" name="add_complaint" value="1"><input type="hidden" name="ApptID" value="<?php echo $viewAppt; ?>">
                            <div class="form-group"><textarea name="Description" class="form-control" required placeholder="Describe complaint..."></textarea></div>
                            <div class="form-group"><select name="Severity" class="form-control"><option value="Low">Low</option><option value="Medium">Medium</option><option value="High">High</option><option value="Critical">Critical</option></select></div>
                            <button type="submit" class="btn btn-primary">Add</button>
                        </form>
                    </div></div>
                    <!-- Add Treatment (full width) -->
                    <div class="card" style="grid-column: 1 / -1;"><div class="card-header"><h3>Add Treatment</h3></div><div class="card-body">
                        <form method="POST"><input type="hidden" name="add_treatment" value="1"><input type="hidden" name="ApptID" value="<?php echo $viewAppt; ?>">
                            <div class="form-row">
                                <div class="form-group" style="flex:2;"><label>Treatment Name <span class="required">*</span></label><input type="text" name="TreatmentName" class="form-control" required placeholder="e.g. IV Fluid Administration"></div>
                                <div class="form-group"><label>Type</label><select name="TreatmentType" class="form-control"><option>Diagnostic</option><option>Therapeutic</option><option>Surgical</option><option>Rehabilitative</option></select></div>
                                <div class="form-group"><label>Status</label><select name="TreatmentStatus" class="form-control"><option value="Ongoing">Ongoing</option><option value="Completed">Completed</option></select></div>
                            </div>
                            <div class="form-row">
                                <div class="form-group"><label>Cost ($) <span class="required">*</span></label><input type="number" name="Cost" class="form-control" step="0.01" min="0" placeholder="0.00" required></div>
                                <div class="form-group"><label>Start Date <span class="required">*</span></label><input type="date" name="StartDate" class="form-control" required value="<?php echo date('Y-m-d'); ?>"></div>
                                <div class="form-group"><label>End Date</label><input type="date" name="EndDate" class="form-control"></div>
                            </div>
                            <div class="form-group"><label>Note</label><input type="text" name="Note" class="form-control" placeholder="Optional note"></div>
                            <button type="submit" class="btn btn-primary">Add Treatment</button>
                        </form>
                    </div></div>
                    <!-- Add Prescription (full width) -->
                    <div class="card" style="grid-column: 1 / -1;"><div class="card-header"><h3>Add Prescription</h3></div><div class="card-body">
                        <form method="POST"><input type="hidden" name="add_prescription" value="1"><input type="hidden" name="ApptID" value="<?php echo $viewAppt; ?>">
                            <div class="form-row">
                                <div class="form-group" style="flex:2;"><label>Medication Name <span class="required">*</span></label><input type="text" name="Medication" class="form-control" required placeholder="e.g. Amoxicillin"></div>
                                <div class="form-group"><label>Dosage <span class="required">*</span></label><input type="text" name="Dosage" class="form-control" required placeholder="e.g. 500mg"></div>
                                <div class="form-group"><label>Frequency <span class="required">*</span></label><input type="text" name="Frequency" class="form-control" required placeholder="e.g. Twice daily"></div>
                            </div>
                            <button type="submit" class="btn btn-primary">Prescribe</button>
                        </form>
                    </div></div>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="printPage()">Print</button>
            <button type="button" class="btn btn-ghost" onclick="closeModal('detailModal'); window.history.replaceState({}, '', 'appointments.php');">Close</button>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'components/footer.php'; ?>

<script>
function viewAppt(id) { window.location.href = 'appointments.php?view=' + id; }
function validateBookForm() {
    const doc = document.getElementById('bookDoctor').value;
    if (!doc) { showToast('error', 'Please select a doctor'); return false; }
    return true;
}
<?php if ($viewAppt && $apptDetail): ?>document.addEventListener('DOMContentLoaded', () => openModal('detailModal'));<?php endif; ?>

// ── Interactive Calendar ──
(function() {
    // All appointments from DB as JSON for JS rendering
    const apptsByDate = {};
    const rawAppts = <?php
        $allAppts = dbFetchAll(dbQuery("SELECT ApptDate, Status, COUNT(*) AS Cnt FROM APPOINTMENT " . ($statusFilter ? "WHERE Status = '" . addslashes($statusFilter) . "'" : "") . " GROUP BY ApptDate, Status"));
        $apptMap = [];
        foreach ($allAppts as $a) {
            $d = $a['ApptDate'] instanceof DateTime ? $a['ApptDate']->format('Y-m-d') : substr((string)$a['ApptDate'], 0, 10);
            if (!isset($apptMap[$d])) $apptMap[$d] = 0;
            $apptMap[$d] += (int)$a['Cnt'];
        }
        echo json_encode($apptMap);
    ?>;
    Object.assign(apptsByDate, rawAppts);

    const today = new Date();
    let curYear = today.getFullYear();
    let curMonth = today.getMonth(); // 0-indexed
    const monthNames = ['January','February','March','April','May','June','July','August','September','October','November','December'];
    const dayNames = ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];

    function renderCalendar() {
        const grid = document.getElementById('calendarGrid');
        const title = document.getElementById('calTitle');
        if (!grid || !title) return;

        title.textContent = monthNames[curMonth] + ' ' + curYear;

        let html = '';
        // Day headers
        dayNames.forEach(d => {
            html += `<div style="background:var(--bg); padding:10px; text-align:center; font-weight:700; font-size:0.75rem; text-transform:uppercase; color:var(--text-muted);">${d}</div>`;
        });

        // Calculate first day of month (Mon=1, Sun=7 -> we want Mon=0)
        const firstDay = new Date(curYear, curMonth, 1);
        let startOffset = firstDay.getDay(); // 0=Sun
        startOffset = startOffset === 0 ? 6 : startOffset - 1; // Convert to Mon-based

        const daysInMonth = new Date(curYear, curMonth + 1, 0).getDate();
        const isCurrentMonth = (curYear === today.getFullYear() && curMonth === today.getMonth());
        const currentDay = today.getDate();

        // Empty cells before start
        for (let i = 0; i < startOffset; i++) {
            html += `<div style="background:var(--surface); min-height:90px; padding:8px; opacity:0.3;"></div>`;
        }

        for (let day = 1; day <= daysInMonth; day++) {
            const dateStr = curYear + '-' + String(curMonth + 1).padStart(2,'0') + '-' + String(day).padStart(2,'0');
            const isToday = isCurrentMonth && day === currentDay;
            const apptCount = apptsByDate[dateStr] || 0;
            const borderStyle = isToday ? 'box-shadow:inset 0 0 0 2px var(--primary);' : '';
            const dayStyle = isToday ? 'font-weight:800; color:var(--primary);' : 'font-weight:600;';
            html += `<div style="background:var(--surface); min-height:90px; padding:8px; position:relative; cursor:default; ${borderStyle} transition: background 0.15s;" onmouseenter="this.style.background='var(--bg)'" onmouseleave="this.style.background='var(--surface)'">`;
            html += `<div style="${dayStyle} font-size:0.85rem; margin-bottom:4px;">${day}</div>`;
            if (apptCount > 0) {
                html += `<div style="display:inline-block; background:var(--primary); color:#fff; font-size:0.65rem; font-weight:700; padding:2px 7px; border-radius:999px; margin-top:2px;">${apptCount} appt${apptCount > 1 ? 's' : ''}</div>`;
            }
            html += '</div>';
        }
        grid.innerHTML = html;
    }

    window.calPrev = function() { curMonth--; if (curMonth < 0) { curMonth = 11; curYear--; } renderCalendar(); };
    window.calNext = function() { curMonth++; if (curMonth > 11) { curMonth = 0; curYear++; } renderCalendar(); };
    window.calGoToday = function() { curYear = today.getFullYear(); curMonth = today.getMonth(); renderCalendar(); };

    document.addEventListener('DOMContentLoaded', renderCalendar);
})();
</script>
