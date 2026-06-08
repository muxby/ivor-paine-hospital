<?php
/**
 * Premium Sidebar Navigation Component
 */
require_once __DIR__ . '/../includes/helpers.php';

$currentFile = basename($_SERVER['PHP_SELF']);

$navSections = [
    'Overview' => [
        ['index.php',         'Dashboard',     '<img src="assets/icons/dashboard.png" class="nav-icon-png" alt="Dashboard">'],
        ['patients.php',      'Patients',      '<img src="assets/icons/patients.png" class="nav-icon-png" alt="Patients">'],
        ['appointments.php',  'Appointments',  '<img src="assets/icons/appointments.png" class="nav-icon-png" alt="Appointments">'],
    ],
    'Medical' => [
        ['complaints.php',    'Complaints',    '<img src="assets/icons/complaints.png" class="nav-icon-png" alt="Complaints">'],
        ['treatments.php',    'Treatments',    '<img src="assets/icons/treatments.png" class="nav-icon-png" alt="Treatments">'],
        ['medicines.php',     'Medicines',     '<img src="assets/icons/treatments.png" class="nav-icon-png" alt="Medicines">'],
        ['prescriptions.php', 'Prescriptions', '<img src="assets/icons/treatments.png" class="nav-icon-png" alt="Prescriptions">'],
    ],
    'Hospital' => [
        ['doctors.php',       'Doctors',       '<img src="assets/icons/staff.png" class="nav-icon-png" alt="Doctors">'],
        ['nurses.php',        'Nurses',        '<img src="assets/icons/staff.png" class="nav-icon-png" alt="Nurses">'],
        ['wards.php',         'Wards',         '<img src="assets/icons/wards.png" class="nav-icon-png" alt="Wards">'],
    ],
    'Analytics' => [
        ['reports.php',       'Reports',       '<img src="assets/icons/analytics.png" class="nav-icon-png" alt="Reports">'],
        ['audit_log.php',     'Audit Log',     '<img src="assets/icons/analytics.png" class="nav-icon-png" alt="Audit Log">'],
    ],
];

// Get counts for badges
$apptToday = dbScalar("SELECT COUNT(*) FROM APPOINTMENT WHERE ApptDate = CAST(GETDATE() AS DATE)");
$criticalComplaints = dbScalar("SELECT COUNT(*) FROM COMPLAINT WHERE Severity IN ('High','Critical') AND DateResolved IS NULL");
$todayRx = dbScalar("SELECT COUNT(*) FROM PRESCRIPTION WHERE CAST(IssuedDate AS DATE) = CAST(GETDATE() AS DATE)");
$todayAudit = dbScalar("SELECT COUNT(*) FROM AUDIT_LOG WHERE CAST(CreatedAt AS DATE) = CAST(GETDATE() AS DATE)");
?>

<div class="sidebar" id="sidebar">
    <div class="sidebar-logo">
        <div class="logo-text">
            <h2>Ivor Paine<br>Memorial</h2>
            <span>Hospital Management</span>
        </div>
    </div>

    <!-- Move search to sidebar -->
    <div class="global-search-wrap" id="globalSearchWrap" style="margin: 0 var(--space-4) var(--space-4); position: relative;">
        <svg class="global-search-icon" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--text-faint); pointer-events: none;"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/></svg>
        <input type="text" class="global-search-input" id="globalSearchInput" placeholder="Search..." autocomplete="off" style="width: 100%; padding: 8px 12px 8px 36px; border: 1px solid var(--border); border-radius: var(--radius); font-size: 0.85rem; outline: none; background: var(--bg); color: var(--text);">
        <div class="global-search-dropdown" id="globalSearchDropdown"></div>
    </div>

    <div class="nav-section">
        <?php foreach ($navSections as $sectionLabel => $items): ?>
            <div class="nav-section-label"><?php echo e($sectionLabel); ?></div>
            <ul class="nav-links">
                <?php foreach ($items as $item):
                    [$file, $label, $icon] = $item;
                    $active = ($currentFile === $file) ? 'active' : '';
                    $badge = '';
                    if ($file === 'appointments.php' && $apptToday > 0) {
                        $badge = '<span class="badge badge-blue" style="margin-left:auto;font-size:0.6rem;padding:2px 6px">' . $apptToday . '</span>';
                    }
                    if ($file === 'complaints.php' && $criticalComplaints > 0) {
                        $badge = '<span class="badge badge-red" style="margin-left:auto;font-size:0.6rem;padding:2px 6px">' . $criticalComplaints . '</span>';
                    }
                    if ($file === 'prescriptions.php' && $todayRx > 0) {
                        $badge = '<span class="badge badge-blue" style="margin-left:auto;font-size:0.6rem;padding:2px 6px">' . $todayRx . '</span>';
                    }
                    if ($file === 'audit_log.php' && $todayAudit > 0) {
                        $badge = '<span class="badge badge-purple" style="margin-left:auto;font-size:0.6rem;padding:2px 6px">' . $todayAudit . '</span>';
                    }
                ?>
                    <li>
                        <a href="<?php echo $file; ?>" class="<?php echo $active; ?>">
                            <?php echo $icon; ?>
                            <span><?php echo e($label); ?></span>
                            <?php echo $badge; ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endforeach; ?>
    </div>
</div>
