<?php
/**
 * Prescription Management - Premium prescription workflow
 * Task 35-38: Medicine search in prescriptions, multiple items, printable, cost awareness
 */
require_once 'includes/db.php';
require_once 'includes/helpers.php';
require_once 'includes/medicine_api.php';

$pageTitle = 'Prescriptions';
$pageSubtitle = 'Create, manage, and print prescriptions with medicine search';

$successMsg = $errorMsg = '';
$viewRx = isset($_GET['view']) ? (int)$_GET['view'] : 0;
$printRx = isset($_GET['print']) ? (int)$_GET['print'] : 0;

// ── FETCH REFERENCE DATA ──
$patients = dbFetchAll(dbQuery("SELECT PatientID, PatientName FROM PATIENT ORDER BY PatientName"));
$doctors = dbFetchAll(dbQuery(
    "SELECT d.StaffID, s.StaffName FROM DOCTOR d JOIN STAFF s ON d.StaffID = s.StaffID ORDER BY s.StaffName"
));
$appointments = dbFetchAll(dbQuery(
    "SELECT a.ApptID, p.PatientName, s.StaffName AS DoctorName, a.ApptDate
     FROM APPOINTMENT a
     JOIN PATIENT p ON a.PatientID = p.PatientID
     JOIN DOCTOR d ON a.DoctorID = d.StaffID
     JOIN STAFF s ON d.StaffID = s.StaffID
     WHERE a.Status = 'Scheduled'
     ORDER BY a.ApptDate DESC"
));

// ── SAVE PRESCRIPTION ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_prescription'])) {
    $apptId = (int)($_POST['ApptID'] ?? 0);
    $medication = trim($_POST['Medication'] ?? '');
    $dosage = trim($_POST['Dosage'] ?? '');
    $frequency = trim($_POST['Frequency'] ?? '');

    // Validate
    $items = [];
    if (!empty($_POST['medicine_items'])) {
        foreach ($_POST['medicine_items'] as $item) {
            $items[] = [
                'medicine_api_id' => $item['api_id'] ?? null,
                'medicine_name' => trim($item['name'] ?? ''),
                'price' => $item['price'] ?? null,
                'dosage' => trim($item['dosage'] ?? ''),
                'frequency' => trim($item['frequency'] ?? ''),
                'duration' => trim($item['duration'] ?? ''),
                'quantity' => (int)($item['quantity'] ?? 1),
                'instructions' => trim($item['instructions'] ?? ''),
            ];
        }
    }

    // If no items but has medication text, create one item
    if (empty($items) && $medication) {
        $items[] = [
            'medicine_api_id' => null,
            'medicine_name' => $medication,
            'price' => null,
            'dosage' => $dosage,
            'frequency' => $frequency,
            'duration' => null,
            'quantity' => null,
            'instructions' => null,
        ];
    }

    if (!$apptId) {
        $errorMsg = 'Please select an appointment.';
    } elseif (empty($items) || empty(array_filter($items, fn($i) => !empty($i['medicine_name'])))) {
        $errorMsg = 'Please add at least one medicine.';
    } else {
        // Insert main prescription record
        dbBeginTrans();
        $maxId = dbScalar("SELECT MAX(PrescriptionID) FROM PRESCRIPTION");
        $newRxId = ($maxId ? $maxId : 0) + 1;
        $stmt = dbQuery(
            "INSERT INTO PRESCRIPTION (PrescriptionID, ApptID, Medication, Dosage, Frequency, IssuedDate) VALUES (?, ?, ?, ?, ?, GETDATE())",
            [$newRxId, $apptId, $items[0]['medicine_name'], $items[0]['dosage'], $items[0]['frequency']]
        );

        if ($stmt) {
            // Get the inserted ID
            $rxId = $newRxId;

            if ($rxId) {
                // Save items
                $hasItemTable = dbFetchOne(dbQuery("SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'PRESCRIPTION_ITEM'"));
                if ($hasItemTable) {
                    savePrescriptionItems($rxId, $items);
                }

                dbCommit();
                logAudit('System', 'create', 'prescription', $rxId, 'Prescription created with ' . count($items) . ' medicine(s)');
                $successMsg = 'Prescription #' . $rxId . ' created successfully with ' . count($items) . ' medicine(s).';
            } else {
                dbRollback();
                $errorMsg = 'Failed to retrieve prescription ID.';
            }
        } else {
            dbRollback();
            $errorMsg = 'Failed to create prescription. Please try again.';
        }
    }
}

// ── FETCH PRESCRIPTION LIST ──
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 15;
$totalRx = dbScalar("SELECT COUNT(*) FROM PRESCRIPTION");
list($offset, $currentPage, $totalPages) = paginate($totalRx, $perPage, $page);

$prescriptionList = dbFetchAll(dbQuery(
    "SELECT pr.PrescriptionID, pr.ApptID, pr.Medication, pr.Dosage, pr.Frequency, pr.IssuedDate,
            p.PatientName, s.StaffName AS DoctorName
     FROM PRESCRIPTION pr
     JOIN APPOINTMENT a ON pr.ApptID = a.ApptID
     JOIN PATIENT p ON a.PatientID = p.PatientID
     JOIN DOCTOR d ON a.DoctorID = d.StaffID
     JOIN STAFF s ON d.StaffID = s.StaffID
     ORDER BY pr.PrescriptionID DESC
     OFFSET ? ROWS FETCH NEXT ? ROWS ONLY",
    [$offset, $perPage]
));

// ── FETCH PRESCRIPTION DETAIL ──
$rxDetail = null;
$rxItems = [];
$rxAppt = null;
if ($viewRx || $printRx) {
    $targetId = $viewRx ?: $printRx;
    $rxDetail = dbFetchOne(dbQuery(
        "SELECT pr.*, p.PatientID, p.PatientName, p.DOB, p.Gender, p.Address, p.BedNumber,
                a.ApptDate, a.ApptTime, a.Status, a.Purpose,
                s.StaffName AS DoctorName, d.Position, sp.SpecName
         FROM PRESCRIPTION pr
         JOIN APPOINTMENT a ON pr.ApptID = a.ApptID
         JOIN PATIENT p ON a.PatientID = p.PatientID
         JOIN DOCTOR d ON a.DoctorID = d.StaffID
         JOIN STAFF s ON d.StaffID = s.StaffID
         LEFT JOIN SPECIALTY sp ON d.SpecID = sp.SpecID
         WHERE pr.PrescriptionID = ?",
        [$targetId]
    ));

    if ($rxDetail) {
        $rxItems = getPrescriptionItems($targetId);
        // Fallback: if no items in PRESCRIPTION_ITEM, use main PRESCRIPTION data
        if (empty($rxItems)) {
            $rxItems = [[
                'MedicineName' => $rxDetail['Medication'],
                'Dosage' => $rxDetail['Dosage'],
                'Frequency' => $rxDetail['Frequency'],
                'Quantity' => null,
                'Duration' => null,
                'Instructions' => null,
                'Price' => null,
                'MedicineApiID' => null,
            ]];
        }
    }
}

// ── CALCULATE COST ──
$totalEstimatedCost = 0;
foreach ($rxItems as $item) {
    if (!empty($item['Price'])) {
        $qty = !empty($item['Quantity']) ? (int)$item['Quantity'] : 1;
        $totalEstimatedCost += parsePrice($item['Price']) * $qty;
    }
}

$actions = '<a href="#createSection" class="btn btn-primary" onclick="showCreateTab()">' .
    '<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>' .
    'New Prescription</a>';

include 'components/header.php';

// If print mode, show print-only view
if ($printRx && $rxDetail): ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Prescription #<?php echo $printRx; ?> | Ivor Paine Memorial</title>
    <link rel="stylesheet" href="style.css">
    <style>
        body { background: #fff; padding: 20px; }
        .no-print { display: none !important; }
        .prescription-print { display: block !important; }
    </style>
</head>
<body>
    <div class="no-print" style="text-align: center; margin-bottom: 20px;">
        <button class="btn btn-primary" onclick="window.print()">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0110.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0l.229 2.523a1.125 1.125 0 01-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0021 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 00-1.913-.247M6.34 18H5.25A2.25 2.25 0 013 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 001.913-.247m10.5 0a48.536 48.536 0 00-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5zm-3 0h.008v.008H15V10.5z"/></svg>
            Print Prescription
        </button>
        <a href="prescriptions.php?view=<?php echo $printRx; ?>" class="btn btn-secondary">Back</a>
    </div>

    <div class="prescription-print">
        <div class="prescription-print-header">
            <div class="hospital-logo">&#9775;</div>
            <h1>Ivor Paine Memorial Hospital</h1>
            <p>Professional Medical Prescription &middot; Rx #<?php echo $printRx; ?></p>
        </div>

        <div class="prescription-meta">
            <div class="prescription-meta-box">
                <h4>Patient</h4>
                <p><?php echo e($rxDetail['PatientName']); ?></p>
                <p style="font-weight: 400; margin-top: 4px;">
                    <?php echo e($rxDetail['Gender'] ?? ''); ?>
                    <?php echo !empty($rxDetail['DOB']) ? '&middot; Age ' . calcAge($rxDetail['DOB']) : ''; ?>
                </p>
            </div>
            <div class="prescription-meta-box">
                <h4>Doctor</h4>
                <p><?php echo e($rxDetail['DoctorName']); ?></p>
                <p style="font-weight: 400; margin-top: 4px;">
                    <?php echo e($rxDetail['Position'] ?? ''); ?>
                    <?php echo !empty($rxDetail['SpecName']) ? '&middot; ' . e($rxDetail['SpecName']) : ''; ?>
                </p>
            </div>
            <div class="prescription-meta-box">
                <h4>Appointment</h4>
                <p><?php echo fmtDate($rxDetail['ApptDate']); ?></p>
                <p style="font-weight: 400; margin-top: 4px;">Purpose: <?php echo e($rxDetail['Purpose'] ?? ''); ?></p>
            </div>
            <div class="prescription-meta-box">
                <h4>Issue Date</h4>
                <p><?php echo fmtDate($rxDetail['IssuedDate']); ?></p>
                <p style="font-weight: 400; margin-top: 4px;">Prescription #<?php echo $printRx; ?></p>
            </div>
        </div>

        <table class="prescription-medicines-print">
            <thead>
                <tr>
                    <th style="width: 30%;">Medicine</th>
                    <th>Dosage</th>
                    <th>Frequency</th>
                    <th>Qty</th>
                    <th>Duration</th>
                    <th>Instructions</th>
                    <th>Est. Price</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rxItems as $item): ?>
                <tr>
                    <td style="font-weight: 600;"><?php echo e($item['MedicineName']); ?></td>
                    <td><?php echo e($item['Dosage'] ?? '-'); ?></td>
                    <td><?php echo e($item['Frequency'] ?? '-'); ?></td>
                    <td><?php echo $item['Quantity'] ?? '-'; ?></td>
                    <td><?php echo e($item['Duration'] ?? '-'); ?></td>
                    <td><?php echo e($item['Instructions'] ?? '-'); ?></td>
                    <td><?php echo !empty($item['Price']) ? e($item['Price']) : '-'; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ($totalEstimatedCost > 0): ?>
        <div style="text-align: right; margin-bottom: 32px; font-size: 1.1rem; font-weight: 700;">
            Estimated Total: <?php echo fmtCurrency($totalEstimatedCost); ?>
            <p style="font-size: 0.75rem; font-weight: 400; color: #64748b; margin-top: 4px;">Estimated from external medicine API. Actual prices may vary.</p>
        </div>
        <?php endif; ?>

        <div class="prescription-footer-print">
            <div>
                <p style="font-size: 0.75rem; color: #64748b;">Ivor Paine Memorial Hospital</p>
                <p style="font-size: 0.75rem; color: #64748b;">This is a computer-generated prescription.</p>
            </div>
            <div class="prescription-signature">
                <div class="signature-line"></div>
                <p style="font-size: 0.8rem; font-weight: 600;">Dr. <?php echo e($rxDetail['DoctorName']); ?></p>
                <p style="font-size: 0.7rem; color: #64748b;">Signature & Stamp</p>
            </div>
        </div>
    </div>
</body>
</html>
<?php
    exit;
endif;
?>

<?php include 'components/sidebar.php'; ?>

<div class="main-content">
    <?php include 'components/topbar.php'; ?>

    <?php if ($successMsg): echo toastScript('success', strip_tags($successMsg)); endif; ?>
    <?php if ($errorMsg): echo toastScript('error', $errorMsg); endif; ?>

    <!-- Tabs -->
    <div class="tabs" style="margin-bottom: var(--space-6);">
        <button class="tab-btn active" data-tab="tab-list" data-tab-group="rx" onclick="switchTab('rx', 'tab-list')">All Prescriptions</button>
        <button class="tab-btn" data-tab="tab-create" data-tab-group="rx" onclick="switchTab('rx', 'tab-create')" id="createTabBtn">Create New</button>
        <?php if ($viewRx && $rxDetail): ?>
        <button class="tab-btn" data-tab="tab-view" data-tab-group="rx" onclick="switchTab('rx', 'tab-view')" id="viewTabBtn">View Details</button>
        <?php endif; ?>
    </div>

    <!-- List Tab -->
    <div id="tab-list" class="tab-panel active" data-tab-panel="rx">
        <div class="section-header">
            <h2>All Prescriptions <span class="text-muted" style="font-weight: 400;">(<?php echo $totalRx; ?>)</span></h2>
            <div class="section-filters">
                <div class="search-wrap">
                    <svg class="search-icon" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/></svg>
                    <input type="text" id="rxSearchInput" placeholder="Search prescriptions..." oninput="filterTable('rxSearchInput', 'rxTable')">
                </div>
                <button class="btn btn-sm btn-secondary" onclick="exportCSV('prescriptions', 'rxTable')">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                    CSV
                </button>
            </div>
        </div>

        <div class="table-card">
            <div class="table-responsive">
                <table class="data-table" id="rxTable">
                    <thead>
                        <tr>
                            <th>Rx #</th>
                            <th>Patient</th>
                            <th>Doctor</th>
                            <th>Medication</th>
                            <th>Issued</th>
                            <th style="text-align: right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($prescriptionList as $rx):
                            $itemCount = dbScalar("SELECT COUNT(*) FROM PRESCRIPTION_ITEM WHERE PrescriptionID = ?", [$rx['PrescriptionID']]);
                            $itemCount = $itemCount ?: 1;
                        ?>
                        <tr>
                            <td><span class="id-tag">#<?php echo $rx['PrescriptionID']; ?></span></td>
                            <td>
                                <div class="name-cell">
                                    <?php echo avatar($rx['PatientName'], 28, 'patient'); ?>
                                    <span class="name"><?php echo e($rx['PatientName']); ?></span>
                                </div>
                            </td>
                            <td class="text-muted"><?php echo e($rx['DoctorName']); ?></td>
                            <td>
                                <span style="font-weight: 600;"><?php echo e($rx['Medication']); ?></span>
                                <?php if ($itemCount > 1): ?>
                                    <span class="badge badge-blue" style="margin-left: 8px; font-size: 0.6rem;">+<?php echo $itemCount - 1; ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo fmtDate($rx['IssuedDate']); ?></td>
                            <td style="text-align: right;">
                                <div style="display: flex; gap: 6px; justify-content: flex-end;">
                                    <a href="prescriptions.php?view=<?php echo $rx['PrescriptionID']; ?>" class="btn btn-sm btn-secondary btn-icon-sm" title="View">
                                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                    </a>
                                    <a href="prescriptions.php?print=<?php echo $rx['PrescriptionID']; ?>" class="btn btn-sm btn-secondary btn-icon-sm" title="Print" target="_blank">
                                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0110.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0l.229 2.523a1.125 1.125 0 01-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0021 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 00-1.913-.247M6.34 18H5.25A2.25 2.25 0 013 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 001.913-.247m10.5 0a48.536 48.536 0 00-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5zm-3 0h.008v.008H15V10.5z"/></svg>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php echo paginationHtml($totalPages, $currentPage, 'prescriptions.php?perPage=' . $perPage); ?>
    </div>

    <!-- Create Tab -->
    <div id="tab-create" class="tab-panel" data-tab-panel="rx">
        <div class="card">
            <div class="card-header">
                <h3>Create Prescription</h3>
                <span class="text-xs text-muted">Search medicines and build prescription</span>
            </div>
            <form method="POST" id="prescriptionForm" onsubmit="return validatePrescriptionForm()">
                <input type="hidden" name="save_prescription" value="1">
                <div class="card-body">
                    <!-- Appointment Selection -->
                    <div class="form-row full" style="margin-bottom: var(--space-4);">
                        <div class="form-group">
                            <label>Appointment <span class="required">*</span></label>
                            <select name="ApptID" class="form-control" required id="apptSelect">
                                <option value="">Select appointment...</option>
                                <?php foreach ($appointments as $a): ?>
                                <option value="<?php echo $a['ApptID']; ?>">
                                    #<?php echo $a['ApptID']; ?> &mdash; <?php echo e($a['PatientName']); ?> with Dr. <?php echo e($a['DoctorName']); ?> (<?php echo fmtDate($a['ApptDate']); ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Medicine Search -->
                    <div class="form-group" style="margin-bottom: var(--space-4);">
                        <label>Search Medicine</label>
                        <div class="prescription-medicine-search">
                            <input type="text"
                                   class="form-control"
                                   id="rxMedicineSearch"
                                   placeholder="Search for medicine to add (e.g. panadol)..."
                                   autocomplete="off"
                                   oninput="debounceRxSearch(this.value)">
                            <div class="prescription-search-dropdown" id="rxSearchDropdown" style="display: none;"></div>
                        </div>
                    </div>

                    <!-- Selected Medicines -->
                    <label style="font-size: 0.78rem; font-weight: 700; color: var(--text-muted); margin-bottom: 12px; display: block; text-transform: uppercase; letter-spacing: 0.05em;">Prescribed Medicines</label>
                    <div class="selected-medicines" id="selectedMedicines">
                        <div class="empty-state" id="emptyMedicines" style="padding: var(--space-8);">
                            <div class="empty-state-icon" style="width: 56px; height: 56px; font-size: 1.5rem;">&#128138;</div>
                            <h4 style="font-size: 0.9rem;">No medicines added</h4>
                            <p style="font-size: 0.8rem;">Search and select medicines to add them here</p>
                        </div>
                    </div>

                    <!-- Cost Summary -->
                    <div class="cost-summary" id="costSummary" style="display: none;">
                        <div style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: var(--text-muted); letter-spacing: 0.05em; margin-bottom: var(--space-3);">Cost Estimate</div>
                        <div id="costBreakdown"></div>
                        <div class="cost-summary-row total">
                            <span>Total Estimated Cost</span>
                            <span id="totalCost">$0.00</span>
                        </div>
                        <div class="cost-summary-note">Estimated from external medicine API. Actual prices may vary at pharmacy.</div>
                    </div>

                    <!-- Fallback text fields (shown when no API medicines selected) -->
                    <div id="fallbackFields">
                        <div class="form-row" style="margin-top: var(--space-5);">
                            <div class="form-group">
                                <label>Medication Name</label>
                                <input type="text" name="Medication" class="form-control" placeholder="Enter medication name">
                            </div>
                            <div class="form-group">
                                <label>Dosage</label>
                                <input type="text" name="Dosage" class="form-control" placeholder="e.g. 500mg">
                            </div>
                            <div class="form-group">
                                <label>Frequency</label>
                                <input type="text" name="Frequency" class="form-control" placeholder="e.g. 3 times daily">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer" style="display: flex; justify-content: flex-end; gap: var(--space-3);">
                    <button type="button" class="btn btn-ghost" onclick="switchTab('rx', 'tab-list')">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="saveRxBtn">
                        <span class="btn-text">Save Prescription</span>
                        <span class="btn-spinner"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- View Tab -->
    <?php if ($viewRx && $rxDetail): ?>
    <div id="tab-view" class="tab-panel" data-tab-panel="rx">
        <div class="breadcrumbs">
            <a href="prescriptions.php">Prescriptions</a>
            <span class="sep">/</span>
            <span class="current">Rx #<?php echo $viewRx; ?></span>
        </div>

        <div class="card">
            <div class="card-header">
                <h3>Prescription #<?php echo $viewRx; ?></h3>
                <div style="display: flex; gap: 8px;">
                    <a href="prescriptions.php?print=<?php echo $viewRx; ?>" class="btn btn-sm btn-secondary" target="_blank">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0110.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0l.229 2.523a1.125 1.125 0 01-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0021 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 00-1.913-.247M6.34 18H5.25A2.25 2.25 0 013 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 001.913-.247m10.5 0a48.536 48.536 0 00-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5zm-3 0h.008v.008H15V10.5z"/></svg>
                        Print
                    </a>
                </div>
            </div>
            <div class="card-body">
                <!-- Patient & Doctor Info -->
                <div class="grid-2" style="margin-bottom: var(--space-6);">
                    <div class="card-glass" style="padding: var(--space-5);">
                        <div style="font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: var(--text-muted); margin-bottom: var(--space-3);">Patient</div>
                        <div style="display: flex; align-items: center; gap: var(--space-3);">
                            <?php echo avatar($rxDetail['PatientName'], 40, 'patient'); ?>
                            <div>
                                <div style="font-weight: 700;"><?php echo e($rxDetail['PatientName']); ?></div>
                                <div class="text-sm text-muted"><?php echo e($rxDetail['Gender']); ?> &middot; Age <?php echo calcAge($rxDetail['DOB']); ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="card-glass" style="padding: var(--space-5);">
                        <div style="font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: var(--text-muted); margin-bottom: var(--space-3);">Doctor</div>
                        <div style="display: flex; align-items: center; gap: var(--space-3);">
                            <?php echo avatar($rxDetail['DoctorName'], 40, 'doctor'); ?>
                            <div>
                                <div style="font-weight: 700;">Dr. <?php echo e($rxDetail['DoctorName']); ?></div>
                                <div class="text-sm text-muted"><?php echo e($rxDetail['Position']); ?> &middot; <?php echo e($rxDetail['SpecName'] ?? ''); ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Medicines Table -->
                <div style="font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: var(--text-muted); margin-bottom: var(--space-3);">Prescribed Medicines</div>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Medicine</th>
                                <th>Dosage</th>
                                <th>Frequency</th>
                                <th>Qty</th>
                                <th>Duration</th>
                                <th>Instructions</th>
                                <th>Est. Price</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rxItems as $item): ?>
                            <tr>
                                <td style="font-weight: 600;"><?php echo e($item['MedicineName']); ?></td>
                                <td><?php echo e($item['Dosage'] ?? '-'); ?></td>
                                <td><?php echo e($item['Frequency'] ?? '-'); ?></td>
                                <td><?php echo $item['Quantity'] ?? '-'; ?></td>
                                <td><?php echo e($item['Duration'] ?? '-'); ?></td>
                                <td><?php echo e($item['Instructions'] ?? '-'); ?></td>
                                <td style="font-weight: 600; color: var(--success);"><?php echo !empty($item['Price']) ? e($item['Price']) : '-'; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Cost Summary -->
                <?php if ($totalEstimatedCost > 0): ?>
                <div class="cost-summary" style="margin-top: var(--space-5);">
                    <div class="cost-summary-row total">
                        <span>Total Estimated Cost</span>
                        <span><?php echo fmtCurrency($totalEstimatedCost); ?></span>
                    </div>
                    <div class="cost-summary-note">Estimated from external medicine API. Actual prices may vary.</div>
                </div>
                <?php endif; ?>

                <div style="margin-top: var(--space-5); font-size: 0.78rem; color: var(--text-muted);">
                    Issued: <?php echo fmtDate($rxDetail['IssuedDate']); ?> &middot; Appointment: #<?php echo $rxDetail['ApptID']; ?> on <?php echo fmtDate($rxDetail['ApptDate']); ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php include 'components/footer.php'; ?>

<script>
// ── Prescription Medicine Search ──
let rxDebounceTimer;
let selectedMedicines = [];
let medicineCounter = 0;

function debounceRxSearch(query) {
    clearTimeout(rxDebounceTimer);
    const dropdown = document.getElementById('rxSearchDropdown');

    if (query.length < 2) {
        dropdown.style.display = 'none';
        return;
    }

    rxDebounceTimer = setTimeout(() => searchRxMedicines(query), 400);
}

function searchRxMedicines(query) {
    const dropdown = document.getElementById('rxSearchDropdown');
    dropdown.innerHTML = '<div style="padding: var(--space-4); text-align: center; color: var(--text-muted);"><div class="search-spinner" style="margin: 0 auto;"></div></div>';
    dropdown.style.display = 'block';

    fetch('api/medicine_api_proxy.php?action=search&q=' + encodeURIComponent(query))
        .then(r => r.json())
        .then(data => {
            if (!data.success || !data.results || data.results.length === 0) {
                dropdown.innerHTML = '<div style="padding: var(--space-4); text-align: center; color: var(--text-muted); font-size: 0.85rem;">No medicines found</div>';
                return;
            }

            let html = '';
            data.results.forEach(med => {
                html += '<div class="prescription-search-item" onclick="addMedicineToRx(' + JSON.stringify(med).replace(/"/g, '&quot;') + ')">';
                html += '<svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" style="color: var(--primary); flex-shrink: 0;"><path stroke-linecap="round" stroke-linejoin="round" d="M9.75 3.104v5.714a2.25 2.25 0 01-.659 1.591L5 14.5M9.75 3.104c-.251.023-.501.05-.75.082m.75-.082a24.301 24.301 0 014.5 0m0 0v5.714a2.25 2.25 0 00.659 1.591L19.8 14.5M14.25 3.104c.251.023.501.05.75.082M19.8 14.5l-1.971 2.971M14.25 9.75l1.971 2.971M5 14.5l1.971-2.971M5 14.5l6.75 6.75M19.8 14.5l-6.75 6.75M9.75 3.104V8.91"/></svg>';
                html += '<div style="flex: 1; min-width: 0;"><div class="item-name">' + escapeHtml(med.name) + '</div></div>';
                if (med.price) {
                    html += '<div class="item-price">' + escapeHtml(med.price) + '</div>';
                }
                html += '</div>';
            });
            dropdown.innerHTML = html;
        })
        .catch(() => {
            dropdown.style.display = 'none';
        });
}

function addMedicineToRx(med) {
    document.getElementById('rxSearchDropdown').style.display = 'none';
    document.getElementById('rxMedicineSearch').value = '';
    document.getElementById('fallbackFields').style.display = 'none';

    const id = medicineCounter++;
    const price = med.price || '';
    const priceValue = parsePrice(price);

    selectedMedicines.push({
        id: id,
        api_id: med.id || '',
        name: med.name || '',
        price: price,
        priceValue: priceValue
    });

    const item = document.createElement('div');
    item.className = 'selected-medicine-item';
    item.id = 'medItem_' + id;
    item.dataset.id = id;
    item.dataset.price = priceValue;

    item.innerHTML =
        '<svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" style="color: var(--primary); flex-shrink: 0; margin-top: 2px;"><path stroke-linecap="round" stroke-linejoin="round" d="M9.75 3.104v5.714a2.25 2.25 0 01-.659 1.591L5 14.5M9.75 3.104c-.251.023-.501.05-.75.082m.75-.082a24.301 24.301 0 014.5 0m0 0v5.714a2.25 2.25 0 00.659 1.591L19.8 14.5M14.25 3.104c.251.023.501.05.75.082M19.8 14.5l-1.971 2.971M14.25 9.75l1.971 2.971M5 14.5l1.971-2.971M5 14.5l6.75 6.75M19.8 14.5l-6.75 6.75M9.75 3.104V8.91"/></svg>' +
        '<div class="item-info">' +
            '<div class="item-name">' + escapeHtml(med.name) + '</div>' +
            '<div class="item-meta"><span class="id-tag">' + escapeHtml(med.id || '') + '</span>' + (price ? '<span>' + escapeHtml(price) + '</span>' : '') + '</div>' +
            '<div class="prescription-item-form" style="margin-top: var(--space-3);">' +
                '<div class="form-group"><label>Dosage</label><input type="text" name="medicine_items[' + id + '][dosage]" class="form-control" placeholder="e.g. 500mg"></div>' +
                '<div class="form-group"><label>Frequency</label><input type="text" name="medicine_items[' + id + '][frequency]" class="form-control" placeholder="e.g. 3x daily"></div>' +
                '<div class="form-group"><label>Duration</label><input type="text" name="medicine_items[' + id + '][duration]" class="form-control" placeholder="e.g. 7 days"></div>' +
                '<div class="form-group"><label>Quantity</label><input type="number" name="medicine_items[' + id + '][quantity]" class="form-control quantity-input" placeholder="1" value="1" min="1" onchange="updateCost()"></div>' +
            '</div>' +
            '<div class="form-group" style="margin-top: var(--space-3);">' +
                '<label>Instructions</label>' +
                '<input type="text" name="medicine_items[' + id + '][instructions]" class="form-control" placeholder="Special instructions...">' +
            '</div>' +
            '<input type="hidden" name="medicine_items[' + id + '][api_id]" value="' + escapeHtml(med.id || '') + '">' +
            '<input type="hidden" name="medicine_items[' + id + '][name]" value="' + escapeHtml(med.name || '') + '">' +
            '<input type="hidden" name="medicine_items[' + id + '][price]" value="' + escapeHtml(price) + '">' +
        '</div>' +
        '<div class="item-price" style="margin-left: auto; padding-left: var(--space-3);">' + (price ? escapeHtml(price) : '') + '</div>' +
        '<button type="button" class="btn-remove" onclick="removeMedicine(' + id + ')" title="Remove">' +
            '<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>' +
        '</button>';

    const container = document.getElementById('selectedMedicines');
    const emptyState = document.getElementById('emptyMedicines');
    if (emptyState) emptyState.style.display = 'none';
    container.appendChild(item);
    updateCost();
}

function removeMedicine(id) {
    const el = document.getElementById('medItem_' + id);
    if (el) el.remove();
    selectedMedicines = selectedMedicines.filter(m => m.id !== id);

    if (selectedMedicines.length === 0) {
        const emptyState = document.getElementById('emptyMedicines');
        if (emptyState) emptyState.style.display = 'flex';
        document.getElementById('fallbackFields').style.display = 'block';
        document.getElementById('costSummary').style.display = 'none';
    } else {
        updateCost();
    }
}

function updateCost() {
    if (selectedMedicines.length === 0) {
        document.getElementById('costSummary').style.display = 'none';
        return;
    }

    let total = 0;
    const breakdown = document.getElementById('costBreakdown');
    let html = '';

    document.querySelectorAll('.selected-medicine-item').forEach(el => {
        const name = el.querySelector('.item-name')?.textContent || '';
        const price = parseFloat(el.dataset.price) || 0;
        const qtyInput = el.querySelector('.quantity-input');
        const qty = qtyInput ? (parseInt(qtyInput.value) || 1) : 1;
        const subtotal = price * qty;
        total += subtotal;

        if (price > 0) {
            html += '<div class="cost-summary-row">' +
                '<span>' + escapeHtml(name) + ' x ' + qty + '</span>' +
                '<span class="font-mono">$' + subtotal.toFixed(2) + '</span>' +
            '</div>';
        }
    });

    breakdown.innerHTML = html;
    document.getElementById('totalCost').textContent = '$' + total.toFixed(2);
    document.getElementById('costSummary').style.display = total > 0 ? 'block' : 'none';
}

function parsePrice(str) {
    if (!str) return 0;
    const num = str.replace(/[^0-9.]/g, '');
    return parseFloat(num) || 0;
}

function showCreateTab() {
    switchTab('rx', 'tab-create');
}

function validatePrescriptionForm() {
    const btn = document.getElementById('saveRxBtn');
    setLoading(btn, true);
    return true;
}

// Auto-switch to view tab
<?php if ($viewRx && $rxDetail): ?>
document.addEventListener('DOMContentLoaded', () => {
    switchTab('rx', 'tab-view');
});
<?php endif; ?>

// Close dropdown on outside click
document.addEventListener('click', function(e) {
    const searchWrap = document.querySelector('.prescription-medicine-search');
    if (searchWrap && !searchWrap.contains(e.target)) {
        document.getElementById('rxSearchDropdown').style.display = 'none';
    }
});

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Pre-fill from URL params
<?php if (isset($_GET['add_medicine'])): ?>
document.addEventListener('DOMContentLoaded', () => {
    switchTab('rx', 'tab-create');
    const med = {
        id: '<?php echo e($_GET['add_medicine'] ?? ''); ?>',
        name: '<?php echo e($_GET['name'] ?? ''); ?>',
        price: '<?php echo e($_GET['price'] ?? ''); ?>'
    };
    if (med.id) addMedicineToRx(med);
});
<?php endif; ?>
</script>
