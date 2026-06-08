<?php
require_once 'includes/db.php';
require_once 'includes/helpers.php';

$pageTitle = 'Complaints Management';
$pageSubtitle = 'Track, manage, and resolve patient complaints';

$successMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_complaint'])) {
    $desc = trim($_POST['Description']);
    $sev = $_POST['Severity'];
    $maxId = dbScalar("SELECT MAX(ComplaintID) FROM COMPLAINT");
    $newComplaintId = ($maxId ? $maxId : 0) + 1;
    dbQuery("INSERT INTO COMPLAINT (ComplaintID, Description, Severity, DateReported) VALUES (?, ?, ?, GETDATE())", [$newComplaintId, $desc, $sev]);
    $successMsg = 'Complaint added successfully.';
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resolve_complaint'])) {
    dbQuery("UPDATE COMPLAINT SET DateResolved = GETDATE() WHERE ComplaintID = ?", [(int)$_POST['ComplaintID']]);
    $successMsg = 'Complaint resolved.';
}

$severityFilter = $_GET['severity'] ?? '';
$sql = "SELECT * FROM COMPLAINT WHERE 1=1";
$params = [];
if ($severityFilter) { $sql .= " AND Severity = ?"; $params[] = $severityFilter; }
$sql .= " ORDER BY CASE WHEN DateResolved IS NULL THEN 0 ELSE 1 END, CASE Severity WHEN 'Critical' THEN 1 WHEN 'High' THEN 2 WHEN 'Medium' THEN 3 ELSE 4 END, DateReported DESC";
$complaints = dbFetchAll(dbQuery($sql, $params));

include 'components/header.php';
?>
<?php include 'components/sidebar.php'; ?>
<div class="main-content">
    <?php
    $addBtn = '<button class="btn btn-primary" onclick="openModal(\'addModal\')"><svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>Add Complaint</button>';
    $filterHtml = '<form method="GET" style="display:flex; gap:8px;"><select name="severity" class="form-control" style="width:auto;" onchange="this.form.submit()"><option value="">All Severities</option><option value="Low"' . ($severityFilter === 'Low' ? ' selected' : '') . '>Low</option><option value="Medium"' . ($severityFilter === 'Medium' ? ' selected' : '') . '>Medium</option><option value="High"' . ($severityFilter === 'High' ? ' selected' : '') . '>High</option><option value="Critical"' . ($severityFilter === 'Critical' ? ' selected' : '') . '>Critical</option></select>' . ($severityFilter ? ' <a href="complaints.php" class="btn btn-sm btn-ghost">Reset</a>' : '') . '</form>';
    $actions = $filterHtml . $addBtn;
    include 'components/topbar.php';
    ?>
    <?php if ($successMsg): echo toastScript('success', $successMsg); endif; ?>

    <div class="table-card">
        <div class="table-responsive">
            <table class="data-table" id="complaintTable">
                <thead><tr><th>ID</th><th>Description</th><th>Severity</th><th>Reported</th><th>Resolved</th><th style="text-align:right;">Actions</th></tr></thead>
                <tbody>
                    <?php foreach ($complaints as $c):
                        $isResolved = $c['DateResolved'] !== null;
                    ?>
                    <tr <?php echo $isResolved ? '' : 'style="background:var(--danger-light);"'; ?>>
                        <td><span class="id-tag">#<?php echo $c['ComplaintID']; ?></span></td>
                        <td><?php echo e(substr($c['Description'], 0, 80)); ?></td>
                        <td><?php echo statusBadge($c['Severity']); ?></td>
                        <td><?php echo fmtDate($c['DateReported']); ?></td>
                        <td><?php echo $isResolved ? badge('Resolved', 'green') : badge('Open', 'amber'); ?></td>
                        <td style="text-align:right;">
                            <?php if (!$isResolved): ?>
                            <form method="POST" style="display:inline;"><input type="hidden" name="resolve_complaint" value="1"><input type="hidden" name="ComplaintID" value="<?php echo $c['ComplaintID']; ?>"><button type="submit" class="btn btn-sm btn-success">Resolve</button></form>
                            <?php else: ?><span class="text-muted text-sm"><?php echo fmtDate($c['DateResolved']); ?></span><?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal-overlay" id="addModal">
    <div class="modal modal-sm">
        <div class="modal-header"><h3>New Complaint</h3><button class="modal-close" onclick="closeModal('addModal')">&#10005;</button></div>
        <form method="POST"><input type="hidden" name="add_complaint" value="1">
            <div class="modal-body">
                <div class="form-group"><label>Description <span class="required">*</span></label><textarea name="Description" class="form-control" required placeholder="Describe the complaint..."></textarea></div>
                <div class="form-group">
                        <label>Severity <span class="required">*</span></label>
                        <select name="Severity" class="form-control" required>
                            <option value="Mild">Mild</option>
                            <option value="Moderate" selected>Moderate</option>
                            <option value="Severe">Severe</option>
                            <option value="Critical">Critical</option>
                        </select>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-ghost" onclick="closeModal('addModal')">Cancel</button><button type="submit" class="btn btn-primary">Add</button></div>
        </form>
    </div>
</div>
<?php include 'components/footer.php'; ?>
