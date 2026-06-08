<?php
/**
 * Doctor Profiles - Premium medical staff directory
 * Features: Profile cards, table view, specialty filters, detail modal with performance
 */
require_once 'includes/db.php';
require_once 'includes/helpers.php';

$pageTitle = 'Doctor Profiles';
$pageSubtitle = 'Medical staff directory with performance and certifications';

$viewDoctor = isset($_GET['view']) ? (int)$_GET['view'] : 0;
$specFilter = $_GET['specialty'] ?? '';
$posFilter = $_GET['position'] ?? '';

// Add Doctor
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_doctor'])) {
    $name = trim($_POST['StaffName']);
    $specID = (int)$_POST['SpecID'];
    $position = trim($_POST['Position']);
    $license = trim($_POST['LicenseNumber']);
    
    dbBeginTrans();
    $maxId = dbScalar("SELECT MAX(StaffID) FROM STAFF");
    $newStaffId = ($maxId ? $maxId : 0) + 1;
    
    $stmt1 = dbQuery("INSERT INTO STAFF (StaffID, StaffName, DateJoined) VALUES (?, ?, GETDATE())", [$newStaffId, $name]);
    if ($stmt1) {
        $stmt2 = dbQuery("INSERT INTO DOCTOR (StaffID, Position, LicenseNumber, SpecID) VALUES (?, ?, ?, ?)", [$newStaffId, $position, $license, $specID]);
        if ($stmt2) {
            dbCommit();
            $successMsg = 'Doctor added successfully.';
        } else {
            dbRollback();
            $errorMsg = 'Failed to add doctor to DOCTOR table.';
        }
    } else {
        dbRollback();
        $errorMsg = 'Failed to add doctor to STAFF table.';
    }
}

// Specialties for filter
$specialties = dbFetchAll(dbQuery("SELECT SpecID, SpecName FROM SPECIALTY ORDER BY SpecName"));

// Positions for filter
$positions = dbFetchAll(dbQuery("SELECT DISTINCT Position FROM DOCTOR ORDER BY Position"));

// Build query
$sql = "SELECT d.StaffID, s.StaffName, s.DateJoined, sp.SpecName, d.Position, d.TeamName, d.LicenseNumber, d.ConsultantID, d.SpecID
        FROM DOCTOR d
        JOIN STAFF s ON d.StaffID = s.StaffID
        LEFT JOIN SPECIALTY sp ON d.SpecID = sp.SpecID
        WHERE 1=1";
$params = [];
if ($specFilter) { $sql .= " AND d.SpecID = ?"; $params[] = (int)$specFilter; }
if ($posFilter) { $sql .= " AND d.Position = ?"; $params[] = $posFilter; }
$sql .= " ORDER BY s.StaffName";

$doctorList = dbFetchAll(dbQuery($sql, $params));

// Performance averages
$perfData = [];
$perfStmt = dbQuery("SELECT RevieweeID, AVG(Rating) AS AvgRating, COUNT(*) AS ReviewCount FROM PERFORMANCE GROUP BY RevieweeID");
if ($perfStmt) { while ($r = dbFetchOne($perfStmt)) $perfData[$r['RevieweeID']] = $r; }

// Appt counts
$apptCounts = [];
$apptStmt = dbQuery("SELECT DoctorID, COUNT(*) AS ApptCount FROM APPOINTMENT GROUP BY DoctorID");
if ($apptStmt) { while ($r = dbFetchOne($apptStmt)) $apptCounts[$r['DoctorID']] = $r['ApptCount']; }

// Doctor detail
$doctorDetail = $experiences = $certifications = $performances = $teamMembers = null;
if ($viewDoctor) {
    $doctorDetail = dbFetchOne(dbQuery(
        "SELECT d.*, s.StaffName, s.DateJoined, sp.SpecName, c.StaffName AS ConsultantName
         FROM DOCTOR d
         JOIN STAFF s ON d.StaffID = s.StaffID
         LEFT JOIN SPECIALTY sp ON d.SpecID = sp.SpecID
         LEFT JOIN DOCTOR cd ON d.ConsultantID = cd.StaffID
         LEFT JOIN STAFF c ON cd.StaffID = c.StaffID
         WHERE d.StaffID = ?",
        [$viewDoctor]
    ));

    if ($doctorDetail) {
        $experiences = dbFetchAll(dbQuery(
            "SELECT * FROM DOCTOR_EXPERIENCE WHERE StaffID = ? ORDER BY YearsOfExp DESC",
            [$viewDoctor]
        ));

        $perfQuery = dbQuery(
            "SELECT p.*, rev.StaffName AS ReviewerName
             FROM PERFORMANCE p
             JOIN DOCTOR rd ON p.ReviewerID = rd.StaffID
             JOIN STAFF rev ON rd.StaffID = rev.StaffID
             WHERE p.RevieweeID = ? ORDER BY p.EvaluationDate DESC",
            [$viewDoctor]
        );
        $performances = dbFetchAll($perfQuery);

        $teamMembers = dbFetchAll(dbQuery(
            "SELECT d.StaffID, s.StaffName, d.Position FROM DOCTOR d
             JOIN STAFF s ON d.StaffID = s.StaffID
             WHERE d.ConsultantID = ? AND d.StaffID != ? ORDER BY s.StaffName",
            [$viewDoctor, $viewDoctor]
        ));
        if (empty($teamMembers)) {
            $teamMembers = dbFetchAll(dbQuery(
                "SELECT d.StaffID, s.StaffName, d.Position FROM DOCTOR d
                 JOIN STAFF s ON d.StaffID = s.StaffID
                 WHERE d.ConsultantID = ? ORDER BY s.StaffName",
                [$doctorDetail['ConsultantID']]
            ));
        }
    }
}

$filterHtml = '<form method="GET" style="display:flex; gap:8px; align-items:center;">' .
    '<select name="specialty" class="form-control" style="width:auto;" onchange="this.form.submit()">' .
    '<option value="">All Specialties</option>';
foreach ($specialties as $sp) {
    $sel = $specFilter == $sp['SpecID'] ? ' selected' : '';
    $filterHtml .= '<option value="' . $sp['SpecID'] . '"' . $sel . '>' . e($sp['SpecName']) . '</option>';
}
$filterHtml .= '</select>' .
    '<select name="position" class="form-control" style="width:auto;" onchange="this.form.submit()">' .
    '<option value="">All Positions</option>';
foreach ($positions as $pos) {
    $sel = $posFilter == $pos['Position'] ? ' selected' : '';
    $filterHtml .= '<option value="' . e($pos['Position']) . '"' . $sel . '>' . e($pos['Position']) . '</option>';
}
$filterHtml .= '</select>' .
    ($specFilter || $posFilter ? ' <a href="doctors.php" class="btn btn-sm btn-ghost">Reset</a>' : '') .
    '</form>';

include 'components/header.php';
?>

<?php include 'components/sidebar.php'; ?>

<div class="main-content">
    <?php
    $actions = $filterHtml . '<button class="btn btn-primary" style="margin-left:8px;" onclick="openModal(\'addDoctorModal\')"><svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>Add Doctor</button>';
    include 'components/topbar.php';
    ?>
    <?php if (isset($successMsg) && $successMsg): echo toastScript('success', $successMsg); endif; ?>
    <?php if (isset($errorMsg) && $errorMsg): echo toastScript('error', $errorMsg); endif; ?>

    <!-- Doctor Cards Grid -->
    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: var(--space-5); margin-bottom: var(--space-8);">
        <?php foreach ($doctorList as $d):
            $perf = $perfData[$d['StaffID']] ?? null;
            $avgRating = $perf ? round((float)$perf['AvgRating'], 1) : null;
            $apptCount = $apptCounts[$d['StaffID']] ?? 0;
            $isConsultant = $d['Position'] === 'Consultant';
        ?>
        <div class="card" style="cursor:pointer;" onclick="viewDoctor(<?php echo $d['StaffID']; ?>)">
            <div class="card-body">
                <div style="display:flex; align-items:flex-start; gap:var(--space-4); margin-bottom:var(--space-4);">
                    <?php echo avatar($d['StaffName'], 48, 'doctor'); ?>
                    <div style="flex:1; min-width:0;">
                        <h4 style="font-size:1rem; font-weight:700; margin-bottom:2px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><?php echo e($d['StaffName']); ?></h4>
                        <div style="font-size:0.8rem; color:var(--text-muted); margin-bottom:6px;"><?php echo e($d['SpecName'] ?? 'General Practice'); ?></div>
                        <div style="display:flex; gap:6px; flex-wrap:wrap;">
                            <?php echo positionBadge($d['Position']); ?>
                            <?php if ($isConsultant): echo badge('Team Lead', 'cyan'); endif; ?>
                        </div>
                    </div>
                </div>

                <div style="display:flex; gap:var(--space-4); padding-top:var(--space-3); border-top:1px solid var(--border-light);">
                    <div style="text-align:center; flex:1;">
                        <div style="font-size:1.2rem; font-weight:800; color:var(--text);"><?php echo $apptCount; ?></div>
                        <div class="text-xs text-muted">Appointments</div>
                    </div>
                    <?php if ($avgRating): ?>
                    <div style="text-align:center; flex:1; border-left:1px solid var(--border-light); padding-left:var(--space-3);">
                        <div style="font-size:1.2rem; font-weight:800; color:var(--amber);"><?php echo number_format($avgRating, 1); ?></div>
                        <div class="text-xs text-muted"><?php echo $perf['ReviewCount']; ?> Reviews</div>
                    </div>
                    <?php else: ?>
                    <div style="text-align:center; flex:1; border-left:1px solid var(--border-light); padding-left:var(--space-3);">
                        <div style="font-size:1.2rem; font-weight:800; color:var(--text-faint);">-</div>
                        <div class="text-xs text-muted">No reviews</div>
                    </div>
                    <?php endif; ?>
                    <div style="text-align:center; flex:1; border-left:1px solid var(--border-light); padding-left:var(--space-3);">
                        <div style="font-size:0.8rem; font-weight:600; color:var(--text-muted); padding-top:6px;"><?php echo e($d['TeamName'] ?? '-'); ?></div>
                        <div class="text-xs text-muted">Team</div>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Doctor Detail Modal -->
<div class="modal-overlay" id="doctorModal" <?php echo $viewDoctor && $doctorDetail ? 'style="display:flex"' : ''; ?>>
    <div class="modal modal-lg">
        <div class="modal-header">
            <h3>Doctor Profile</h3>
            <button class="modal-close" onclick="closeModal('doctorModal'); window.history.replaceState({}, '', 'doctors.php');">&#10005;</button>
        </div>
        <?php if ($doctorDetail): ?>
        <div class="modal-body">
            <div style="display:flex; align-items:flex-start; gap:var(--space-5); margin-bottom:var(--space-6); padding-bottom:var(--space-5); border-bottom:1px solid var(--border-light);">
                <?php echo avatar($doctorDetail['StaffName'], 72, 'doctor'); ?>
                <div style="flex:1;">
                    <h2 style="font-size:1.3rem; font-weight:800;"><?php echo e($doctorDetail['StaffName']); ?></h2>
                    <div style="display:flex; gap:var(--space-3); flex-wrap:wrap; margin:var(--space-2) 0;">
                        <?php echo positionBadge($doctorDetail['Position']); ?>
                        <?php echo badge($doctorDetail['SpecName'] ?? 'General', 'blue'); ?>
                        <?php if ($doctorDetail['TeamName']) echo badge($doctorDetail['TeamName'], 'cyan'); ?>
                    </div>
                    <div style="display:flex; gap:var(--space-5); font-size:0.85rem; color:var(--text-muted); flex-wrap:wrap;">
                        <span><strong style="color:var(--text);">License:</strong> <?php echo e($doctorDetail['LicenseNumber']); ?></span>
                        <span><strong style="color:var(--text);">Joined:</strong> <?php echo fmtDate($doctorDetail['DateJoined']); ?></span>
                        <span><strong style="color:var(--text);">Appointments:</strong> <?php echo $apptCounts[$doctorDetail['StaffID']] ?? 0; ?></span>
                    </div>
                    <?php if ($doctorDetail['ConsultantName']): ?>
                    <div class="text-sm text-muted" style="margin-top:4px;">Reports to: <?php echo e($doctorDetail['ConsultantName']); ?></div>
                    <?php endif; ?>
                </div>
                <?php if ($avgRating): ?>
                <div class="card-glass" style="padding:var(--space-4); text-align:center;">
                    <div class="text-xs text-muted">Avg Rating</div>
                    <div style="font-size:2rem; font-weight:800; color:var(--amber);"><?php echo number_format($avgRating, 1); ?></div>
                    <?php echo starRating($avgRating); ?>
                    <div class="text-xs text-muted" style="margin-top:4px;"><?php echo $perf['ReviewCount']; ?> evaluations</div>
                </div>
                <?php endif; ?>
            </div>

            <div class="tabs">
                <button class="tab-btn active" data-tab="doc-experience" data-tab-group="doctor" onclick="switchTab('doctor', 'doc-experience')">Experience</button>
                <button class="tab-btn" data-tab="doc-performance" data-tab-group="doctor" onclick="switchTab('doctor', 'doc-performance')">Performance</button>
                <?php if ($teamMembers): ?>
                <button class="tab-btn" data-tab="doc-team" data-tab-group="doctor" onclick="switchTab('doctor', 'doc-team')">Team</button>
                <?php endif; ?>
            </div>

            <div id="doc-experience" class="tab-panel active" data-tab-panel="doctor">
                <?php if ($experiences): ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead><tr><th>Specialization</th><th>Years</th><th>Institution</th></tr></thead>
                        <tbody>
                            <?php foreach ($experiences as $exp): ?>
                            <tr><td><?php echo e($exp['Specialization']); ?></td><td><?php echo $exp['YearsOfExp']; ?> years</td><td><?php echo e($exp['InstitutionName']); ?></td></tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?><div class="empty-state"><div class="empty-state-icon">&#127891;</div><h4>No experience records</h4></div><?php endif; ?>
            </div>

            <div id="doc-performance" class="tab-panel" data-tab-panel="doctor">
                <?php if ($performances): ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead><tr><th>Date</th><th>Rating</th><th>Evaluator</th><th>Badge</th></tr></thead>
                        <tbody>
                            <?php foreach ($performances as $perf):
                                $r = (float)$perf['Rating'];
                                if ($r >= 9) $badge = badge('Excellent', 'emerald');
                                elseif ($r >= 7) $badge = badge('Good', 'blue');
                                elseif ($r >= 5) $badge = badge('Satisfactory', 'amber');
                                else $badge = badge('Needs Review', 'red');
                            ?>
                            <tr><td><?php echo fmtDate($perf['EvaluationDate']); ?></td><td><?php echo starRating($r); ?></td><td><?php echo e($perf['ReviewerName']); ?></td><td><?php echo $badge; ?></td></tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?><div class="empty-state"><div class="empty-state-icon">&#11088;</div><h4>No performance evaluations</h4></div><?php endif; ?>
            </div>

            <div id="doc-team" class="tab-panel" data-tab-panel="doctor">
                <?php if ($teamMembers): ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead><tr><th>Name</th><th>Position</th></tr></thead>
                        <tbody>
                            <?php foreach ($teamMembers as $tm): ?>
                            <tr><td><div class="name-cell"><?php echo avatar($tm['StaffName'], 28, 'doctor'); ?><span class="name"><?php echo e($tm['StaffName']); ?></span></div></td><td><?php echo positionBadge($tm['Position']); ?></td></tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?><div class="empty-state"><h4>No team members</h4></div><?php endif; ?>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-ghost" onclick="closeModal('doctorModal'); window.history.replaceState({}, '', 'doctors.php');">Close</button>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add Doctor Modal -->
<div class="modal-overlay" id="addDoctorModal">
    <div class="modal modal-sm">
        <div class="modal-header"><h3>Add Doctor</h3><button class="modal-close" onclick="closeModal('addDoctorModal')">&#10005;</button></div>
        <form method="POST">
            <input type="hidden" name="add_doctor" value="1">
            <div class="modal-body">
                <div class="form-group"><label>Doctor Name <span class="required">*</span></label><input type="text" name="StaffName" class="form-control" required placeholder="Dr. John Doe"></div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Specialty <span class="required">*</span></label>
                        <select name="SpecID" class="form-control" required>
                            <option value="">Select specialty...</option>
                            <?php foreach ($specialties as $sp): ?>
                            <option value="<?php echo $sp['SpecID']; ?>"><?php echo e($sp['SpecName']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Position</label>
                        <select name="Position" class="form-control" required>
                            <option value="Consultant">Consultant</option>
                            <option value="Senior Registrar">Senior Registrar</option>
                            <option value="Registrar">Registrar</option>
                            <option value="House Officer" selected>House Officer</option>
                            <option value="Junior Doctor">Junior Doctor</option>
                        </select>
                    </div>
                </div>
                <div class="form-group"><label>License Number <span class="required">*</span></label><input type="text" name="LicenseNumber" class="form-control" required placeholder="LIC-12345"></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-ghost" onclick="closeModal('addDoctorModal')">Cancel</button><button type="submit" class="btn btn-primary">Add Doctor</button></div>
        </form>
    </div>
</div>

<?php include 'components/footer.php'; ?>

<script>
function viewDoctor(id) { window.location.href = 'doctors.php?view=' + id; }
<?php if ($viewDoctor && $doctorDetail): ?>
document.addEventListener('DOMContentLoaded', () => openModal('doctorModal'));
<?php endif; ?>
</script>
