<?php
/**
 * Ward & Bed Management Dashboard
 * Features: Ward cards, bed map, occupancy visualization, bed assignment, add ward
 */
require_once 'includes/db.php';
require_once 'includes/helpers.php';

$pageTitle = 'Wards & Beds';
$pageSubtitle = 'Hospital ward overview and interactive bed management';

$successMsg = $errorMsg = '';
$viewWard = $_GET['ward'] ?? '';

// Add ward
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_ward'])) {
    $wname = trim($_POST['WardName'] ?? '');
    $wfloor = (int)($_POST['WardFloor'] ?? 0);
    $specID = (int)($_POST['SpecID'] ?? 0);
    if ($wname && $wfloor > 0 && $specID > 0) {
        $r = dbQuery("INSERT INTO WARD (WardName, WardFloor, SpecID) VALUES (?, ?, ?)", [$wname, $wfloor, $specID]);
        if ($r !== false) {
            $successMsg = 'Ward "' . htmlspecialchars($wname, ENT_QUOTES, 'UTF-8') . '" registered successfully.';
        } else {
            $errorMsg = 'Failed to add ward. Ward name may already exist.';
        }
    } else {
        $errorMsg = 'Please fill in all required fields.';
    }
}

// Assign bed
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_bed'])) {
    $pid = (int)$_POST['PatientID'];
    $bedNum = (int)$_POST['BedNumber'];
    // Free old bed
    $old = dbFetchOne(dbQuery("SELECT BedNumber FROM PATIENT WHERE PatientID = ?", [$pid]));
    if ($old && $old['BedNumber']) dbQuery("UPDATE BED SET Status = 'Available' WHERE BedNumber = ?", [$old['BedNumber']]);
    // Assign new
    dbQuery("UPDATE PATIENT SET BedNumber = ? WHERE PatientID = ?", [$bedNum, $pid]);
    dbQuery("UPDATE BED SET Status = 'Occupied' WHERE BedNumber = ?", [$bedNum]);
    $successMsg = 'Bed assigned successfully.';
}

// Ward data with occupancy
$wardData = dbFetchAll(dbQuery(
    "SELECT w.WardName, w.WardFloor, sp.SpecName,
        (SELECT COUNT(*) FROM CARE_UNIT cu WHERE cu.WardName = w.WardName) AS UnitCount,
        (SELECT COUNT(*) FROM BED b WHERE b.WardName = w.WardName) AS TotalBeds,
        (SELECT COUNT(*) FROM BED b WHERE b.WardName = w.WardName AND b.Status = 'Available') AS AvailBeds,
        (SELECT COUNT(*) FROM BED b WHERE b.WardName = w.WardName AND b.Status = 'Occupied') AS OccupiedBeds,
        (SELECT COUNT(*) FROM BED b WHERE b.WardName = w.WardName AND b.Status = 'Maintenance') AS MaintBeds,
        (SELECT COUNT(*) FROM CARE_UNIT cu WHERE cu.WardName = w.WardName) AS NurseCount
     FROM WARD w
     JOIN SPECIALTY sp ON w.SpecID = sp.SpecID
     ORDER BY w.WardName"
));

// Detail ward data
$beds = $patientsInWard = [];
if ($viewWard) {
    $beds = dbFetchAll(dbQuery(
        "SELECT b.*, p.PatientID, p.PatientName FROM BED b
         LEFT JOIN PATIENT p ON b.BedNumber = p.BedNumber
         WHERE b.WardName = ? ORDER BY b.BedNumber", [$viewWard]
    ));
    $patientsInWard = dbFetchAll(dbQuery(
        "SELECT p.PatientID, p.PatientName, p.BedNumber FROM PATIENT p
         JOIN BED b ON p.BedNumber = b.BedNumber WHERE b.WardName = ? ORDER BY p.PatientName",
        [$viewWard]
    ));
}

// Unassigned patients for bed assignment dropdown
$unassignedPatients = dbFetchAll(dbQuery("SELECT PatientID, PatientName FROM PATIENT WHERE BedNumber IS NULL ORDER BY PatientName"));

// Specialties for form
$specialties = dbFetchAll(dbQuery("SELECT SpecID, SpecName FROM SPECIALTY ORDER BY SpecName"));

$actions = '<button class="btn btn-primary" onclick="openModal(\'addWardModal\')"><svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>Register Ward</button>';

include 'components/header.php';
?>

<?php include 'components/sidebar.php'; ?>

<div class="main-content">
    <?php include 'components/topbar.php'; ?>
    <?php if ($successMsg): echo toastScript('success', $successMsg); endif; ?>
    <?php if ($errorMsg): echo toastScript('error', $errorMsg); endif; ?>

    <?php if (!$viewWard): ?>
    <!-- Ward Cards -->
    <div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: var(--space-5);">
        <?php foreach ($wardData as $w):
            $total = (int)$w['TotalBeds'];
            $avail = (int)$w['AvailBeds'];
            $occupied = (int)$w['OccupiedBeds'];
            $pct = $total > 0 ? round($occupied / $total * 100) : 0;
            $barClass = $pct >= 90 ? 'danger' : ($pct >= 70 ? 'warning' : 'success');
        ?>
        <div class="card" style="cursor:pointer;" onclick="window.location.href='wards.php?ward=<?php echo urlencode($w['WardName']); ?>'">
            <div class="card-body">
                <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:var(--space-4);">
                    <div><h3 style="font-size:1.05rem; font-weight:700;"><?php echo e($w['WardName']); ?></h3><div class="text-xs text-muted">Floor <?php echo $w['WardFloor']; ?> &middot; <?php echo e($w['SpecName']); ?></div></div>
                    <?php echo badge($pct >= 90 ? 'Full' : ($pct >= 70 ? 'Busy' : 'Open'), $barClass === 'danger' ? 'red' : ($barClass === 'warning' ? 'amber' : 'green')); ?>
                </div>
                <div style="margin-bottom:var(--space-3);"><div style="display:flex; justify-content:space-between; font-size:0.78rem; color:var(--text-muted); margin-bottom:6px;"><span>Occupancy</span><span><?php echo $occupied; ?>/<?php echo $total; ?> (<?php echo $pct; ?>%)</span></div><div class="progress-bar"><div class="progress-bar-fill <?php echo $barClass; ?>" style="width:<?php echo $pct; ?>%"></div></div></div>
                <div style="display:flex; gap:var(--space-4); text-align:center;">
                    <div style="flex:1;"><div style="font-size:1.2rem; font-weight:700; color:var(--success);"><?php echo $avail; ?></div><div class="text-xs text-muted">Available</div></div>
                    <div style="flex:1; border-left:1px solid var(--border-light);"><div style="font-size:1.2rem; font-weight:700; color:var(--danger);"><?php echo $occupied; ?></div><div class="text-xs text-muted">Occupied</div></div>
                    <div style="flex:1; border-left:1px solid var(--border-light);"><div style="font-size:1.2rem; font-weight:700; color:var(--primary);"><?php echo $w['NurseCount']; ?></div><div class="text-xs text-muted">Units</div></div>
                    <div style="flex:1; border-left:1px solid var(--border-light);"><div style="font-size:1.2rem; font-weight:700;"><?php echo $w['UnitCount']; ?></div><div class="text-xs text-muted">Care Units</div></div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php if (empty($wardData)): ?>
        <div class="card" style="grid-column: 1/-1;">
            <div class="empty-state" style="padding: var(--space-12);">
                <div class="empty-state-icon"><svg width="32" height="32" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008z"/></svg></div>
                <h4>No Wards Found</h4>
                <p>Register the first ward to get started.</p>
                <button class="btn btn-primary" style="margin-top: var(--space-4);" onclick="openModal('addWardModal')">Register Ward</button>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <?php else:
    // Ward detail view
    $currentWard = null;
    foreach ($wardData as $w) if ($w['WardName'] === $viewWard) { $currentWard = $w; break; }
    ?>
    <div class="breadcrumbs">
        <a href="wards.php">Wards</a><span class="sep">/</span><span class="current"><?php echo e($viewWard); ?></span>
    </div>

    <div style="display:flex; gap:var(--space-4); align-items:center; margin-bottom:var(--space-6);">
        <h2 style="font-size:1.3rem; font-weight:800;"><?php echo e($viewWard); ?></h2>
        <?php echo badge(e($currentWard['SpecName'] ?? ''), 'blue'); ?>
        <span class="text-sm text-muted">Floor <?php echo $currentWard['WardFloor']; ?></span>
    </div>

    <!-- Bed Map -->
    <div class="card" style="margin-bottom:var(--space-8);">
        <div class="card-header">
            <h3>Bed Map</h3>
            <div style="display:flex; gap:var(--space-4); font-size:0.78rem;">
                <span style="display:flex; align-items:center; gap:4px;"><span style="width:10px;height:10px;border-radius:3px;background:var(--success-light);border:2px solid var(--success);display:inline-block;"></span> Available</span>
                <span style="display:flex; align-items:center; gap:4px;"><span style="width:10px;height:10px;border-radius:3px;background:var(--danger-light);border:2px solid var(--danger);display:inline-block;"></span> Occupied</span>
                <span style="display:flex; align-items:center; gap:4px;"><span style="width:10px;height:10px;border-radius:3px;background:var(--warning-light);border:2px solid var(--warning);display:inline-block;"></span> Maintenance</span>
            </div>
        </div>
        <div class="card-body">
            <?php if ($beds): ?>
            <div class="bed-grid">
                <?php foreach ($beds as $bed):
                    $statusClass = strtolower($bed['Status']);
                    $hasPatient = $bed['PatientName'] !== null;
                ?>
                <div class="bed-item <?php echo $statusClass; ?>">
                    <span class="bed-number"><?php echo $bed['BedNumber']; ?></span>
                    <span class="bed-type"><?php echo e($bed['BedType']); ?></span>
                    <?php if ($hasPatient): ?><span class="bed-patient"><?php echo e($bed['PatientName']); ?></span><?php endif; ?>
                    <span class="badge badge-<?php echo $statusClass === 'available' ? 'green' : ($statusClass === 'occupied' ? 'red' : 'amber'); ?>" style="margin-top:6px; font-size:0.6rem;"><?php echo e($bed['Status']); ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="empty-state"><h4>No beds registered in this ward yet.</h4></div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Bed Assignment Form -->
    <div class="card" style="margin-bottom:var(--space-8);">
        <div class="card-header"><h3>Assign Bed to Patient</h3></div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="assign_bed" value="1">
                <div class="form-row">
                    <div class="form-group">
                        <label>Patient</label>
                        <select name="PatientID" class="form-control" required>
                            <option value="">Select patient...</option>
                            <?php foreach ($unassignedPatients as $p): ?><option value="<?php echo $p['PatientID']; ?>"><?php echo e($p['PatientName']); ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Bed</label>
                        <select name="BedNumber" class="form-control" required>
                            <option value="">Select bed...</option>
                            <?php foreach ($beds as $b): if ($b['Status'] === 'Available'): ?>
                            <option value="<?php echo $b['BedNumber']; ?>">Bed <?php echo $b['BedNumber']; ?> &mdash; <?php echo e($b['BedType']); ?></option>
                            <?php endif; endforeach; ?>
                        </select>
                    </div>
                </div>
                <?php if (empty($unassignedPatients)): ?>
                <p class="text-muted text-sm" style="margin-bottom: var(--space-4);">All patients currently have beds assigned.</p>
                <?php endif; ?>
                <button type="submit" class="btn btn-primary">Assign Bed</button>
            </form>
        </div>
    </div>

    <!-- Patients in Ward -->
    <div class="card">
        <div class="card-header"><h3>Patients Currently in Ward</h3></div>
        <div class="card-body" style="padding:0;">
            <?php if ($patientsInWard): ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead><tr><th>Patient</th><th>Bed</th></tr></thead>
                    <tbody>
                        <?php foreach ($patientsInWard as $p): ?>
                        <tr><td><div class="name-cell"><?php echo avatar($p['PatientName'], 28, 'patient'); ?><span class="name"><?php echo e($p['PatientName']); ?></span></div></td><td><span class="id-tag">Bed <?php echo $p['BedNumber']; ?></span></td></tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?><div class="empty-state"><h4>No patients currently in this ward</h4></div><?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Add Ward Modal -->
<div class="modal-overlay" id="addWardModal">
    <div class="modal">
        <div class="modal-header"><h3>Register New Ward</h3><button class="modal-close" onclick="closeModal('addWardModal')">&#10005;</button></div>
        <form method="POST">
            <input type="hidden" name="add_ward" value="1">
            <div class="modal-body">
                <div class="form-group">
                    <label>Ward Name <span class="required">*</span></label>
                    <input type="text" name="WardName" class="form-control" required placeholder="e.g. Cardiology, Neurology">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Floor Number <span class="required">*</span></label>
                        <input type="number" name="WardFloor" class="form-control" required min="1" max="20" placeholder="1">
                    </div>
                    <div class="form-group">
                        <label>Specialty <span class="required">*</span></label>
                        <select name="SpecID" class="form-control" required>
                            <option value="">Select specialty...</option>
                            <?php foreach ($specialties as $sp): ?>
                            <option value="<?php echo $sp['SpecID']; ?>"><?php echo e($sp['SpecName']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="closeModal('addWardModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Register Ward</button>
            </div>
        </form>
    </div>
</div>

<?php include 'components/footer.php'; ?>
