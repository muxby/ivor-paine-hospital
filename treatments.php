<?php
require_once 'includes/db.php';
require_once 'includes/helpers.php';

$pageTitle = 'Treatments';
$pageSubtitle = 'Manage patient treatments and track progress';

$successMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_treatment'])) {
    $maxId = dbScalar("SELECT MAX(TreatmentID) FROM PATIENT_TREATMENT");
    $newTreatmentId = ($maxId ? $maxId : 0) + 1;
    dbQuery(
        "INSERT INTO PATIENT_TREATMENT (TreatmentID, TreatmentName, TreatmentType, Cost, StartDate, EndDate, Status) VALUES (?, ?, ?, ?, ?, ?, ?)",
        [$newTreatmentId, trim($_POST['TreatmentName']), $_POST['TreatmentType'], (float)$_POST['Cost'], $_POST['StartDate'], $_POST['EndDate'] ?: null, $_POST['Status']]
    );
    $successMsg = 'Treatment added.';
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_note'])) {
    dbQuery("INSERT INTO TREATMENT_NOTES (TreatmentID, Note) VALUES (?, ?)", [(int)$_POST['TreatmentID'], trim($_POST['Note'])]);
    $successMsg = 'Note added.';
}

$typeFilter = $_GET['type'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$sql = "SELECT * FROM PATIENT_TREATMENT WHERE 1=1";
$params = [];
if ($typeFilter) { $sql .= " AND TreatmentType = ?"; $params[] = $typeFilter; }
if ($statusFilter) { $sql .= " AND Status = ?"; $params[] = $statusFilter; }
$sql .= " ORDER BY StartDate DESC";
$treatments = dbFetchAll(dbQuery($sql, $params));

$treatmentTypes = dbFetchAll(dbQuery("SELECT DISTINCT TreatmentType FROM PATIENT_TREATMENT ORDER BY TreatmentType"));
$treatmentStatuses = dbFetchAll(dbQuery("SELECT DISTINCT Status FROM PATIENT_TREATMENT ORDER BY Status"));

$notes = [];
$notesData = dbFetchAll(dbQuery("SELECT * FROM TREATMENT_NOTES"));
foreach ($notesData as $n) { $notes[$n['TreatmentID']][] = $n; }

$filterHtml = '<form method="GET" style="display:flex; gap:8px;">' .
    '<select name="type" class="form-control" style="width:auto;" onchange="this.form.submit()"><option value="">All Types</option>';
foreach ($treatmentTypes as $t) $filterHtml .= '<option value="' . e($t['TreatmentType']) . '"' . ($typeFilter === $t['TreatmentType'] ? ' selected' : '') . '>' . e($t['TreatmentType']) . '</option>';
$filterHtml .= '</select><select name="status" class="form-control" style="width:auto;" onchange="this.form.submit()"><option value="">All Statuses</option>';
foreach ($treatmentStatuses as $s) $filterHtml .= '<option value="' . e($s['Status']) . '"' . ($statusFilter === $s['Status'] ? ' selected' : '') . '>' . e($s['Status']) . '</option>';
$filterHtml .= '</select>' . (($typeFilter || $statusFilter) ? ' <a href="treatments.php" class="btn btn-sm btn-ghost">Reset</a>' : '') . '</form>';

include 'components/header.php';
?>
<?php include 'components/sidebar.php'; ?>
<div class="main-content">
    <?php $actions = $filterHtml . '<button class="btn btn-primary" onclick="openModal(\'addModal\')"><svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>Add Treatment</button>'; include 'components/topbar.php'; ?>
    <?php if ($successMsg): echo toastScript('success', $successMsg); endif; ?>

    <div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: var(--space-5);">
        <?php foreach ($treatments as $t):
            $typeBadge = match($t['TreatmentType']) {
                'Diagnostic' => badge($t['TreatmentType'], 'blue'),
                'Therapeutic' => badge($t['TreatmentType'], 'emerald'),
                'Surgical' => badge($t['TreatmentType'], 'rose'),
                'Rehabilitative' => badge($t['TreatmentType'], 'purple'),
                default => badge($t['TreatmentType'], 'gray'),
            };
            $tnotes = $notes[$t['TreatmentID']] ?? [];
        ?>
        <div class="card">
            <div class="card-body">
                <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:var(--space-3);">
                    <h4 style="font-size:1rem; font-weight:700;"><?php echo e($t['TreatmentName']); ?></h4>
                    <?php echo statusBadge($t['Status']); ?>
                </div>
                <div style="display:flex; gap:var(--space-3); margin-bottom:var(--space-3); flex-wrap:wrap;"><?php echo $typeBadge; ?><span style="font-weight:700; color:var(--primary);"><?php echo fmtCurrency($t['Cost']); ?></span></div>
                <div class="text-sm text-muted" style="margin-bottom:var(--space-3);">
                    <?php echo fmtDate($t['StartDate']); ?> <?php echo $t['EndDate'] ? ' - ' . fmtDate($t['EndDate']) : ''; ?>
                </div>
                <?php if ($tnotes): ?>
                <div style="background:var(--bg); border-radius:var(--radius); padding:var(--space-3); margin-bottom:var(--space-3);">
                    <div class="text-xs text-muted" style="font-weight:700; text-transform:uppercase; margin-bottom:4px;">Notes</div>
                    <?php foreach ($tnotes as $n): ?><div class="text-sm" style="margin-bottom:4px;">- <?php echo e($n['Note']); ?></div><?php endforeach; ?>
                </div>
                <?php endif; ?>
                <button class="btn btn-sm btn-secondary" onclick="document.getElementById('noteForm<?php echo $t['TreatmentID']; ?>').style.display = document.getElementById('noteForm<?php echo $t['TreatmentID']; ?>').style.display === 'none' ? 'block' : 'none'">Add Note</button>
                <form method="POST" id="noteForm<?php echo $t['TreatmentID']; ?>" style="display:none; margin-top:var(--space-3);">
                    <input type="hidden" name="add_note" value="1"><input type="hidden" name="TreatmentID" value="<?php echo $t['TreatmentID']; ?>">
                    <div class="form-group"><input type="text" name="Note" class="form-control" placeholder="Enter note..." required></div>
                    <button type="submit" class="btn btn-sm btn-primary">Save</button>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<div class="modal-overlay" id="addModal">
    <div class="modal modal-sm">
        <div class="modal-header"><h3>New Treatment</h3><button class="modal-close" onclick="closeModal('addModal')">&#10005;</button></div>
        <form method="POST"><input type="hidden" name="add_treatment" value="1">
            <div class="modal-body">
                <div class="form-group"><label>Name <span class="required">*</span></label><input type="text" name="TreatmentName" class="form-control" required></div>
                <div class="form-row">
                    <div class="form-group"><label>Type</label><select name="TreatmentType" class="form-control"><option>Diagnostic</option><option>Therapeutic</option><option>Surgical</option><option>Rehabilitative</option></select></div>
                    <div class="form-group"><label>Cost ($)</label><input type="number" name="Cost" class="form-control" step="0.01" required></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Start Date</label><input type="date" name="StartDate" class="form-control" required value="<?php echo date('Y-m-d'); ?>"></div>
                    <div class="form-group"><label>End Date</label><input type="date" name="EndDate" class="form-control"></div>
                </div>
                <div class="form-group"><label>Status <span class="required">*</span></label><select name="Status" class="form-control" required><option>Ongoing</option><option>Completed</option><option>Discontinued</option></select></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-ghost" onclick="closeModal('addModal')">Cancel</button><button type="submit" class="btn btn-primary">Add</button></div>
        </form>
    </div>
</div>
<?php include 'components/footer.php'; ?>
