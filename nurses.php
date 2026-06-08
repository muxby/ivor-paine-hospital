<?php
/**
 * Nursing Staff - Premium nurse directory with shift management
 */
require_once 'includes/db.php';
require_once 'includes/helpers.php';

$pageTitle = 'Nursing Staff';
$pageSubtitle = 'Nurse directory, shift schedules, and ward assignments';

$viewNurse = isset($_GET['view']) ? (int)$_GET['view'] : 0;
$wardFilter = $_GET['ward'] ?? '';
$typeFilter = $_GET['type'] ?? '';

// Add Nurse
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_nurse'])) {
    $name = trim($_POST['StaffName']);
    $type = trim($_POST['NurseType']);
    
    dbBeginTrans();
    $maxId = dbScalar("SELECT MAX(StaffID) FROM STAFF");
    $newStaffId = ($maxId ? $maxId : 0) + 1;
    
    $stmt1 = dbQuery("INSERT INTO STAFF (StaffID, StaffName, DateJoined) VALUES (?, ?, GETDATE())", [$newStaffId, $name]);
    if ($stmt1) {
        $stmt2 = dbQuery("INSERT INTO NURSE (StaffID, NurseType) VALUES (?, ?)", [$newStaffId, $type]);
        if ($stmt2) {
            dbCommit();
            $successMsg = 'Nurse added successfully.';
        } else {
            dbRollback();
            $errorMsg = 'Failed to add nurse to NURSE table.';
        }
    } else {
        dbRollback();
        $errorMsg = 'Failed to add nurse to STAFF table.';
    }
}

// Filters - wards come from NURSE_SHIFT since NURSE table doesn't have WardName directly
$wards = dbFetchAll(dbQuery("SELECT DISTINCT UnitWardName AS WardName FROM NURSE_SHIFT ORDER BY UnitWardName"));
$types = dbFetchAll(dbQuery("SELECT DISTINCT NurseType FROM NURSE ORDER BY NurseType"));

// Build nurse list - join NURSE_SHIFT to get ward/unit (latest shift per nurse)
$sql = "SELECT n.StaffID, s.StaffName, s.DateJoined, n.NurseType,
                ns.UnitWardName AS WardName, ns.UnitCode
        FROM NURSE n
        JOIN STAFF s ON n.StaffID = s.StaffID
        LEFT JOIN (
            SELECT NurseID, UnitWardName, UnitCode,
                   ROW_NUMBER() OVER (PARTITION BY NurseID ORDER BY ShiftDate DESC) AS rn
            FROM NURSE_SHIFT
        ) ns ON n.StaffID = ns.NurseID AND ns.rn = 1
        WHERE 1=1";
$params = [];
if ($wardFilter) { $sql .= " AND ns.UnitWardName = ?"; $params[] = $wardFilter; }
if ($typeFilter) { $sql .= " AND n.NurseType = ?"; $params[] = $typeFilter; }
$sql .= " ORDER BY ns.UnitWardName, s.StaffName";
$nurses = dbFetchAll(dbQuery($sql, $params));

// Nurse detail with shifts
$nurseDetail = $shifts = null;
if ($viewNurse) {
    $nurseDetail = dbFetchOne(dbQuery(
        "SELECT n.StaffID, n.NurseType, s.StaffName, s.DateJoined,
                ns.UnitWardName AS WardName, ns.UnitCode,
                cu.UnitType,
                w.WardFloor
         FROM NURSE n
         JOIN STAFF s ON n.StaffID = s.StaffID
         LEFT JOIN (
             SELECT NurseID, UnitWardName, UnitCode,
                    ROW_NUMBER() OVER (PARTITION BY NurseID ORDER BY ShiftDate DESC) AS rn
             FROM NURSE_SHIFT
         ) ns ON n.StaffID = ns.NurseID AND ns.rn = 1
         LEFT JOIN CARE_UNIT cu ON ns.UnitCode = cu.UnitCode AND ns.UnitWardName = cu.WardName
         LEFT JOIN WARD w ON ns.UnitWardName = w.WardName
         WHERE n.StaffID = ?",
        [$viewNurse]
    ));
    if ($nurseDetail) {
        $shifts = dbFetchAll(dbQuery(
            "SELECT ShiftDate, ShiftType, ShiftTime, Duration, UnitCode, UnitWardName
             FROM NURSE_SHIFT WHERE NurseID = ? ORDER BY ShiftDate DESC", [$viewNurse]
        ));
    }
}


$filterHtml = '<form method="GET" style="display:flex; gap:8px; align-items:center;">' .
    '<select name="ward" class="form-control" style="width:auto;" onchange="this.form.submit()">' .
    '<option value="">All Wards</option>';
foreach ($wards as $w) {
    $sel = $wardFilter === $w['WardName'] ? ' selected' : '';
    $filterHtml .= '<option value="' . e($w['WardName']) . '"' . $sel . '>' . e($w['WardName']) . '</option>';
}
$filterHtml .= '</select>' .
    '<select name="type" class="form-control" style="width:auto;" onchange="this.form.submit()">' .
    '<option value="">All Types</option>';
foreach ($types as $t) {
    $sel = $typeFilter === $t['NurseType'] ? ' selected' : '';
    $filterHtml .= '<option value="' . e($t['NurseType']) . '"' . $sel . '>' . e($t['NurseType']) . '</option>';
}
$filterHtml .= '</select>' .
    ($wardFilter || $typeFilter ? ' <a href="nurses.php" class="btn btn-sm btn-ghost">Reset</a>' : '') .
    '</form>';

include 'components/header.php';
?>

<?php include 'components/sidebar.php'; ?>

<div class="main-content">
    <?php 
    $actions = $filterHtml . '<button class="btn btn-primary" style="margin-left:8px;" onclick="openModal(\'addNurseModal\')"><svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>Add Nurse</button>'; 
    include 'components/topbar.php'; 
    ?>
    <?php if (isset($successMsg) && $successMsg): echo toastScript('success', $successMsg); endif; ?>
    <?php if (isset($errorMsg) && $errorMsg): echo toastScript('error', $errorMsg); endif; ?>

    <div class="table-card">
        <div class="table-responsive">
            <table class="data-table" id="nurseTable">
                <thead>
                    <tr>
                        <th>Nurse</th>
                        <th>Type</th>
                        <th>Ward</th>
                        <th>Unit</th>
                        <th>Joined</th>
                        <th style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($nurses as $n):
                        $typeColors = [
                            'Registered Nurse - Day Sister' => 'badge-blue',
                            'Registered Nurse - Night Sister' => 'badge-purple',
                            'Registered Nurse - Staff Nurse' => 'badge-green',
                            'Non-registered Nurse' => 'badge-gray',
                        ];
                        $badgeClass = $typeColors[$n['NurseType']] ?? 'badge-gray';
                    ?>
                    <tr>
                        <td><div class="name-cell"><?php echo avatar($n['StaffName'], 32, 'nurse'); ?><span class="name"><?php echo e($n['StaffName']); ?></span></div></td>
                        <td><span class="badge <?php echo $badgeClass; ?>"><?php echo e($n['NurseType']); ?></span></td>
                        <td><?php echo e($n['WardName']); ?></td>
                        <td><span class="id-tag"><?php echo e($n['UnitCode']); ?></span></td>
                        <td><?php echo fmtDate($n['DateJoined'], 'M Y'); ?></td>
                        <td style="text-align:right;"><button class="btn btn-sm btn-secondary btn-icon-sm" onclick="viewNurse(<?php echo $n['StaffID']; ?>)"><svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg></button></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal-overlay" id="nurseModal" <?php echo $viewNurse && $nurseDetail ? 'style="display:flex"' : ''; ?>>
    <div class="modal modal-lg">
        <div class="modal-header">
            <h3>Nurse Profile</h3>
            <button class="modal-close" onclick="closeModal('nurseModal'); window.history.replaceState({}, '', 'nurses.php');">&#10005;</button>
        </div>
        <?php if ($nurseDetail): ?>
        <div class="modal-body">
            <div style="display:flex; align-items:flex-start; gap:var(--space-5); margin-bottom:var(--space-5);">
                <?php echo avatar($nurseDetail['StaffName'], 64, 'nurse'); ?>
                <div>
                    <h2 style="font-size:1.2rem; font-weight:800;"><?php echo e($nurseDetail['StaffName']); ?></h2>
                    <div style="display:flex; gap:var(--space-3); margin-top:var(--space-2); flex-wrap:wrap;">
                        <span class="badge <?php echo $typeColors[$nurseDetail['NurseType']] ?? 'badge-gray'; ?>"><?php echo e($nurseDetail['NurseType']); ?></span>
                        <span class="badge badge-blue"><?php echo e($nurseDetail['WardName']); ?></span>
                        <span class="badge badge-cyan"><?php echo e($nurseDetail['UnitCode']); ?></span>
                    </div>
                    <div class="text-sm text-muted" style="margin-top:var(--space-2);">
                        Unit: <?php echo e($nurseDetail['UnitType'] ?? 'N/A'); ?> &middot; Floor: <?php echo $nurseDetail['WardFloor'] ?? 'N/A'; ?> &middot; Joined: <?php echo fmtDate($nurseDetail['DateJoined']); ?>
                    </div>
                </div>
            </div>
            <h4 style="font-size:0.9rem; font-weight:700; margin-bottom:var(--space-3); text-transform:uppercase; letter-spacing:0.04em; color:var(--text-muted);">Recent Shifts</h4>
            <?php if ($shifts): ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead><tr><th>Date</th><th>Type</th><th>Start Time</th><th>Duration</th><th>Ward</th></tr></thead>
                    <tbody>
                        <?php foreach ($shifts as $sh): ?>
                        <tr><td><?php echo fmtDate($sh['ShiftDate']); ?></td><td><?php echo badge($sh['ShiftType'], $sh['ShiftType'] === 'Day' ? 'amber' : 'indigo'); ?></td><td><?php echo fmtTime($sh['ShiftTime']); ?></td><td><?php echo $sh['Duration']; ?> hours</td><td><?php echo e($sh['UnitWardName'] ?? 'N/A'); ?></td></tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?><div class="empty-state"><div class="empty-state-icon">&#128197;</div><h4>No shift records</h4></div><?php endif; ?>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-ghost" onclick="closeModal('nurseModal'); window.history.replaceState({}, '', 'nurses.php');">Close</button>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add Nurse Modal -->
<div class="modal-overlay" id="addNurseModal">
    <div class="modal modal-sm">
        <div class="modal-header"><h3>Add Nurse</h3><button class="modal-close" onclick="closeModal('addNurseModal')">&#10005;</button></div>
        <form method="POST">
            <input type="hidden" name="add_nurse" value="1">
            <div class="modal-body">
                <div class="form-group"><label>Nurse Name <span class="required">*</span></label><input type="text" name="StaffName" class="form-control" required placeholder="Jane Doe"></div>
                <div class="form-group">
                    <label>Nurse Type <span class="required">*</span></label>
                    <select name="NurseType" class="form-control" required>
                        <option value="Registered Nurse - Day Sister">Registered Nurse - Day Sister</option>
                        <option value="Registered Nurse - Night Sister">Registered Nurse - Night Sister</option>
                        <option value="Registered Nurse - Staff Nurse" selected>Registered Nurse - Staff Nurse</option>
                        <option value="Non-registered Nurse">Non-registered Nurse</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-ghost" onclick="closeModal('addNurseModal')">Cancel</button><button type="submit" class="btn btn-primary">Add Nurse</button></div>
        </form>
    </div>
</div>

<?php include 'components/footer.php'; ?>

<script>function viewNurse(id) { window.location.href = 'nurses.php?view=' + id; }
<?php if ($viewNurse && $nurseDetail): ?>document.addEventListener('DOMContentLoaded', () => openModal('nurseModal'));<?php endif; ?>
</script>
