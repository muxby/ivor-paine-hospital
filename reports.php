<?php
/**
 * Advanced Reports & Analytics Dashboard
 * Tasks 41-49: Executive summary, report builder, finance, medicine,
 *              patient medical, doctor workload, ward, appointment,
 *              complaint/treatment outcome reports
 */
require_once 'includes/db.php';
require_once 'includes/helpers.php';
require_once 'includes/medicine_api.php';

$pageTitle = 'Reports & Analytics';
$pageSubtitle = 'Comprehensive reporting with executive KPIs, report builder, and analytics';

// ── ACTIVE REPORT ──
$activeReport = $_GET['report'] ?? '';
$activeCategory = $_GET['category'] ?? '';

// ── FILTERS ──
$filterDateFrom = $_GET['date_from'] ?? date('Y-m-01');
$filterDateTo = $_GET['date_to'] ?? date('Y-m-d');
$filterDoctor = (int)($_GET['doctor'] ?? 0);
$filterPatient = (int)($_GET['patient'] ?? 0);
$filterWard = $_GET['ward'] ?? '';
$filterStatus = $_GET['status'] ?? '';
$filterSeverity = $_GET['severity'] ?? '';

// ── EXECUTIVE SUMMARY KPIs ──
$execKPIs = [
    'total_patients' => dbScalar("SELECT COUNT(*) FROM PATIENT"),
    'new_patients_month' => dbScalar("SELECT COUNT(*) FROM PATIENT WHERE DateAdmitted >= DATEADD(day, -30, GETDATE())"),
    'completed_appts' => dbScalar("SELECT COUNT(*) FROM APPOINTMENT WHERE Status = 'Completed' AND ApptDate >= DATEADD(day, -30, GETDATE())"),
    'cancelled_appts' => dbScalar("SELECT COUNT(*) FROM APPOINTMENT WHERE Status = 'Cancelled' AND ApptDate >= DATEADD(day, -30, GETDATE())"),
    'avg_appts_per_doctor' => round(dbScalar("SELECT CAST(COUNT(*) AS FLOAT) / NULLIF((SELECT COUNT(*) FROM DOCTOR), 0) FROM APPOINTMENT WHERE ApptDate >= DATEADD(day, -30, GETDATE())"), 1),
    'total_beds' => dbScalar("SELECT COUNT(*) FROM BED"),
    'occupied_beds' => dbScalar("SELECT COUNT(*) FROM BED WHERE Status = 'Occupied'"),
    'bed_occupancy_pct' => 0,
    'most_prescribed_medicine' => '',
    'most_common_complaint_severity' => '',
    'total_treatment_cost' => dbScalar("SELECT ISNULL(SUM(Cost), 0) FROM PATIENT_TREATMENT"),
    'total_medicine_cost' => 0,
];

$execKPIs['bed_occupancy_pct'] = $execKPIs['total_beds'] > 0
    ? round($execKPIs['occupied_beds'] / $execKPIs['total_beds'] * 100)
    : 0;

// Most prescribed medicine
$mostPrescribed = dbFetchOne(dbQuery(
    "SELECT TOP 1 MedicineName, COUNT(*) AS RxCount FROM PRESCRIPTION_ITEM GROUP BY MedicineName ORDER BY COUNT(*) DESC"
));
$execKPIs['most_prescribed_medicine'] = $mostPrescribed ? $mostPrescribed['MedicineName'] : 'N/A';

// Most common complaint severity
$mostSeverity = dbFetchOne(dbQuery("SELECT TOP 1 Severity, COUNT(*) AS Cnt FROM COMPLAINT GROUP BY Severity ORDER BY COUNT(*) DESC"));
$execKPIs['most_common_complaint_severity'] = $mostSeverity ? $mostSeverity['Severity'] : 'N/A';

// Total estimated medicine cost
$execKPIs['total_medicine_cost'] = dbScalar("SELECT ISNULL(SUM(CAST(REPLACE(REPLACE(Price, 'Rs.', ''), '$', '') AS FLOAT)), 0) FROM PRESCRIPTION_ITEM WHERE Price IS NOT NULL");

// ── MOST ACTIVE WARD ──
$mostActiveWard = dbFetchOne(dbQuery(
    "SELECT TOP 1 w.WardName, COUNT(p.PatientID) AS PatientCount
     FROM WARD w
     JOIN BED b ON w.WardName = b.WardName
     JOIN PATIENT p ON b.BedNumber = p.BedNumber
     GROUP BY w.WardName
     ORDER BY COUNT(p.PatientID) DESC"
));

// ── REFERENCE DATA FOR FILTERS ──
$allDoctors = dbFetchAll(dbQuery("SELECT d.StaffID, s.StaffName FROM DOCTOR d JOIN STAFF s ON d.StaffID = s.StaffID ORDER BY s.StaffName"));
$allPatients = dbFetchAll(dbQuery("SELECT PatientID, PatientName FROM PATIENT ORDER BY PatientName"));
$allWards = dbFetchAll(dbQuery("SELECT WardName FROM WARD ORDER BY WardName"));

// ── REPORT DEFINITIONS ──
$reportCategories = [
    'executive' => [
        'title' => 'Executive Summary',
        'icon'  => '<i class="fa-solid fa-chart-line"></i>',
        'reports' => ['executive_summary' => 'Executive KPI Dashboard'],
    ],
    'medicines' => [
        'title' => 'Medicines',
        'icon'  => '<i class="fa-solid fa-pills"></i>',
        'reports' => [
            'most_prescribed'       => 'Most Prescribed Medicines',
            'medicines_by_doctor'   => 'Medicines by Doctor',
            'medicines_by_patient'  => 'Medicines by Patient',
            'medicine_cost_by_patient' => 'Medicine Cost by Patient',
            'medicine_usage_source' => 'API vs Manual Medicines',
            'missing_dosage'        => 'Medicines with Missing Dosage',
        ],
    ],
    'patients' => [
        'title' => 'Patients',
        'icon'  => '<i class="fa-solid fa-user-injured"></i>',
        'reports' => [
            'patient_full_report'   => 'Full Patient Medical Report',
            'patient_admissions'    => 'Patient Admissions',
            'new_patients_monthly'  => 'New Patients by Month',
        ],
    ],
    'doctors' => [
        'title' => 'Doctors',
        'icon'  => '<i class="fa-solid fa-user-md"></i>',
        'reports' => [
            'doctor_workload'       => 'Doctor Workload Report',
            'doctor_performance'    => 'Doctor Performance',
            'doctor_appointments'   => 'Appointments by Doctor',
        ],
    ],
    'wards' => [
        'title' => 'Wards',
        'icon'  => '<i class="fa-solid fa-hospital-user"></i>',
        'reports' => [
            'ward_utilization'      => 'Ward Utilization Report',
            'ward_occupancy'        => 'Bed Occupancy Details',
        ],
    ],
    'appointments' => [
        'title' => 'Appointments',
        'icon'  => '<i class="fa-solid fa-calendar-check"></i>',
        'reports' => [
            'appointment_performance'  => 'Appointment Performance',
            'appointments_by_month'    => 'Appointments by Month',
            'appointments_by_purpose'  => 'Appointments by Purpose',
            'no_show_analysis'         => 'No-Show Analysis',
        ],
    ],
    'complaints' => [
        'title' => 'Complaints & Treatments',
        'icon'  => '<i class="fa-solid fa-clipboard-list"></i>',
        'reports' => [
            'complaints_by_severity'   => 'Complaints by Severity',
            'complaint_resolution'     => 'Complaint Resolution',
            'treatment_outcomes'       => 'Treatment Outcomes',
            'complaint_treatment_link' => 'Complaints & Treatments',
        ],
    ],
    'finance' => [
        'title' => 'Finance',
        'icon'  => '<i class="fa-solid fa-file-invoice-dollar"></i>',
        'reports' => [
            'treatment_cost_patient'  => 'Treatment Cost by Patient',
            'treatment_cost_doctor'   => 'Treatment Cost by Doctor',
            'treatment_cost_ward'     => 'Treatment Cost by Ward',
            'monthly_treatment_cost'  => 'Monthly Treatment Cost',
            'high_cost_patients'      => 'High-Cost Patients',
        ],
    ],
];

// ── REPORT SQL DEFINITIONS ──
$reportSQL = [
    'executive_summary' => [
        'sql' => "SELECT 'Total Patients' AS Metric, CAST(COUNT(*) AS VARCHAR) AS Value FROM PATIENT
                  UNION ALL SELECT 'Active Doctors', CAST(COUNT(*) AS VARCHAR) FROM DOCTOR
                  UNION ALL SELECT 'Total Nurses', CAST(COUNT(*) AS VARCHAR) FROM NURSE
                  UNION ALL SELECT 'Total Beds', CAST(COUNT(*) AS VARCHAR) FROM BED
                  UNION ALL SELECT 'Occupied Beds', CAST(COUNT(*) AS VARCHAR) FROM BED WHERE Status='Occupied'",
        'params' => []
    ],
    'most_prescribed' => [
        'sql' => "SELECT TOP 50 MedicineName, COUNT(*) AS PrescriptionCount,
                  STRING_AGG(DISTINCT CAST(PrescriptionID AS VARCHAR), ', ') AS PrescriptionIDs
                  FROM PRESCRIPTION_ITEM
                  WHERE CreatedAt BETWEEN ? AND ?
                  GROUP BY MedicineName
                  ORDER BY COUNT(*) DESC",
        'params' => [$filterDateFrom, $filterDateTo]
    ],
    'medicines_by_doctor' => [
        'sql' => "SELECT TOP 50 s.StaffName AS Doctor, pi.MedicineName, COUNT(*) AS TimesPrescribed
                  FROM PRESCRIPTION_ITEM pi
                  JOIN PRESCRIPTION pr ON pi.PrescriptionID = pr.PrescriptionID
                  JOIN APPOINTMENT a ON pr.ApptID = a.ApptID
                  JOIN STAFF s ON a.DoctorID = s.StaffID
                  WHERE pi.CreatedAt BETWEEN ? AND ?
                  GROUP BY s.StaffName, pi.MedicineName
                  ORDER BY COUNT(*) DESC",
        'params' => [$filterDateFrom, $filterDateTo]
    ],
    'medicines_by_patient' => [
        'sql' => "SELECT TOP 50 p.PatientName, pi.MedicineName, pi.Dosage, pi.Frequency, pi.Price, pr.IssuedDate
                  FROM PRESCRIPTION_ITEM pi
                  JOIN PRESCRIPTION pr ON pi.PrescriptionID = pr.PrescriptionID
                  JOIN APPOINTMENT a ON pr.ApptID = a.ApptID
                  JOIN PATIENT p ON a.PatientID = p.PatientID
                  WHERE pi.CreatedAt BETWEEN ? AND ?
                  ORDER BY p.PatientName, pr.IssuedDate DESC",
        'params' => [$filterDateFrom, $filterDateTo]
    ],
    'medicine_cost_by_patient' => [
        'sql' => "SELECT TOP 50 p.PatientName,
                  COUNT(pi.PrescriptionItemID) AS MedicineCount,
                  SUM(CAST(REPLACE(REPLACE(ISNULL(pi.Price, '0'), 'Rs.', ''), '$', '') AS FLOAT)) AS TotalEstimatedCost
                  FROM PRESCRIPTION_ITEM pi
                  JOIN PRESCRIPTION pr ON pi.PrescriptionID = pr.PrescriptionID
                  JOIN APPOINTMENT a ON pr.ApptID = a.ApptID
                  JOIN PATIENT p ON a.PatientID = p.PatientID
                  WHERE pi.CreatedAt BETWEEN ? AND ?
                  GROUP BY p.PatientName
                  ORDER BY TotalEstimatedCost DESC",
        'params' => [$filterDateFrom, $filterDateTo]
    ],
    'medicine_usage_source' => [
        'sql' => "SELECT
                    CASE WHEN MedicineApiID IS NOT NULL THEN 'API Medicines' ELSE 'Manually Entered' END AS Source,
                    COUNT(*) AS Count,
                    CAST(CAST(COUNT(*) AS FLOAT) * 100 / NULLIF((SELECT COUNT(*) FROM PRESCRIPTION_ITEM), 0) AS DECIMAL(5,1)) AS Percentage
                  FROM PRESCRIPTION_ITEM
                  GROUP BY CASE WHEN MedicineApiID IS NOT NULL THEN 'API Medicines' ELSE 'Manually Entered' END
                  ORDER BY Count DESC",
        'params' => []
    ],
    'missing_dosage' => [
        'sql' => "SELECT TOP 50 pi.MedicineName, p.PatientName, s.StaffName AS DoctorName, pi.Dosage, pi.Frequency, pi.Instructions, pr.IssuedDate
                  FROM PRESCRIPTION_ITEM pi
                  JOIN PRESCRIPTION pr ON pi.PrescriptionID = pr.PrescriptionID
                  JOIN APPOINTMENT a ON pr.ApptID = a.ApptID
                  JOIN PATIENT p ON a.PatientID = p.PatientID
                  JOIN STAFF s ON a.DoctorID = s.StaffID
                  WHERE (pi.Dosage IS NULL OR pi.Dosage = '' OR pi.Instructions IS NULL OR pi.Instructions = '')"
                . ($filterDateFrom ? " AND pi.CreatedAt BETWEEN ? AND ?" : "").
                 " ORDER BY pr.IssuedDate DESC",
        'params' => $filterDateFrom ? [$filterDateFrom, $filterDateTo] : []
    ],
    'patient_full_report' => [
        'sql' => "SELECT TOP 100 p.PatientID, p.PatientName, p.DOB, p.Gender, p.Address, p.DateAdmitted, p.BedNumber,
                  COUNT(DISTINCT a.ApptID) AS TotalAppointments,
                  COUNT(DISTINCT c.ComplaintID) AS TotalComplaints,
                  COUNT(DISTINCT pt.TreatmentID) AS TotalTreatments,
                  SUM(DISTINCT pt.Cost) AS TotalTreatmentCost
                  FROM PATIENT p
                  LEFT JOIN APPOINTMENT a ON p.PatientID = a.PatientID
                  LEFT JOIN APPT_COMPL ac ON a.ApptID = ac.ApptID
                  LEFT JOIN COMPLAINT c ON ac.ComplaintID = c.ComplaintID
                  LEFT JOIN APPT_TREAT at ON a.ApptID = at.ApptID
                  LEFT JOIN PATIENT_TREATMENT pt ON at.TreatmentID = pt.TreatmentID
                  GROUP BY p.PatientID, p.PatientName, p.DOB, p.Gender, p.Address, p.DateAdmitted, p.BedNumber
                  ORDER BY p.PatientName",
        'params' => []
    ],
    'doctor_workload' => [
        'sql' => "SELECT s.StaffName AS Doctor, d.Position, sp.SpecName AS Specialty,
                  COUNT(DISTINCT a.ApptID) AS TotalAppointments,
                  SUM(CASE WHEN a.Status = 'Completed' THEN 1 ELSE 0 END) AS Completed,
                  SUM(CASE WHEN a.Status = 'Cancelled' THEN 1 ELSE 0 END) AS Cancelled,
                  COUNT(DISTINCT p.PatientID) AS UniquePatients,
                  COUNT(DISTINCT pr.PrescriptionID) AS PrescriptionsIssued,
                  COUNT(DISTINCT pt.TreatmentID) AS TreatmentsAssigned,
                  AVG(per.Rating) AS AvgRating
                  FROM DOCTOR d
                  JOIN STAFF s ON d.StaffID = s.StaffID
                  LEFT JOIN SPECIALTY sp ON d.SpecID = sp.SpecID
                  LEFT JOIN APPOINTMENT a ON a.DoctorID = d.StaffID
                      AND a.ApptDate BETWEEN ? AND ?
                  LEFT JOIN PATIENT p ON a.PatientID = p.PatientID
                  LEFT JOIN PRESCRIPTION pr ON a.ApptID = pr.ApptID
                  LEFT JOIN APPT_TREAT at ON a.ApptID = at.ApptID
                  LEFT JOIN PATIENT_TREATMENT pt ON at.TreatmentID = pt.TreatmentID
                  LEFT JOIN PERFORMANCE per ON d.StaffID = per.RevieweeID
                  GROUP BY s.StaffName, d.Position, sp.SpecName
                  ORDER BY COUNT(DISTINCT a.ApptID) DESC",
        'params' => [$filterDateFrom, $filterDateTo]
    ],
    'ward_utilization' => [
        'sql' => "SELECT w.WardName, w.WardFloor, w.WardLocation,
                  COUNT(DISTINCT b.BedNumber) AS TotalBeds,
                  SUM(CASE WHEN b.Status = 'Occupied' THEN 1 ELSE 0 END) AS OccupiedBeds,
                  SUM(CASE WHEN b.Status = 'Available' THEN 1 ELSE 0 END) AS AvailableBeds,
                  SUM(CASE WHEN b.Status = 'Maintenance' THEN 1 ELSE 0 END) AS MaintenanceBeds,
                  COUNT(DISTINCT p.PatientID) AS CurrentPatients,
                  sp.SpecName
                  FROM WARD w
                  JOIN SPECIALTY sp ON w.SpecID = sp.SpecID
                  LEFT JOIN BED b ON w.WardName = b.WardName
                  LEFT JOIN PATIENT p ON b.BedNumber = p.BedNumber AND b.Status = 'Occupied'
                  GROUP BY w.WardName, w.WardFloor, w.WardLocation, sp.SpecName
                  ORDER BY w.WardName",
        'params' => []
    ],
    'appointment_performance' => [
        'sql' => "SELECT
                    COUNT(*) AS TotalAppointments,
                    SUM(CASE WHEN Status = 'Completed' THEN 1 ELSE 0 END) AS Completed,
                    SUM(CASE WHEN Status = 'Cancelled' THEN 1 ELSE 0 END) AS Cancelled,
                    SUM(CASE WHEN Status = 'Scheduled' THEN 1 ELSE 0 END) AS Scheduled,
                    SUM(CASE WHEN Status = 'No-Show' THEN 1 ELSE 0 END) AS NoShow,
                    CAST(SUM(CASE WHEN Status = 'Completed' THEN 1 ELSE 0 END) * 100.0 / NULLIF(COUNT(*), 0) AS DECIMAL(5,1)) AS CompletionRate
                  FROM APPOINTMENT
                  WHERE ApptDate BETWEEN ? AND ?",
        'params' => [$filterDateFrom, $filterDateTo]
    ],
    'complaints_by_severity' => [
        'sql' => "SELECT Severity, COUNT(*) AS Count,
                  SUM(CASE WHEN DateResolved IS NOT NULL THEN 1 ELSE 0 END) AS Resolved,
                  SUM(CASE WHEN DateResolved IS NULL THEN 1 ELSE 0 END) AS Unresolved
                  FROM COMPLAINT
                  WHERE DateReported BETWEEN ? AND ?
                  GROUP BY Severity
                  ORDER BY Count DESC",
        'params' => [$filterDateFrom, $filterDateTo]
    ],
    'treatment_cost_patient' => [
        'sql' => "SELECT TOP 50 p.PatientName,
                  COUNT(pt.TreatmentID) AS TreatmentCount,
                  SUM(pt.Cost) AS TotalCost,
                  AVG(pt.Cost) AS AvgCost
                  FROM PATIENT p
                  JOIN APPOINTMENT a ON p.PatientID = a.PatientID
                  JOIN APPT_TREAT at ON a.ApptID = at.ApptID
                  JOIN PATIENT_TREATMENT pt ON at.TreatmentID = pt.TreatmentID
                  WHERE pt.StartDate BETWEEN ? AND ?
                  GROUP BY p.PatientName
                  ORDER BY SUM(pt.Cost) DESC",
        'params' => [$filterDateFrom, $filterDateTo]
    ],
    'monthly_treatment_cost' => [
        'sql' => "SELECT DATEPART(YEAR, StartDate) AS Year, DATEPART(MONTH, StartDate) AS Month,
                  DATENAME(MONTH, StartDate) AS MonthName,
                  COUNT(*) AS TreatmentCount, SUM(Cost) AS TotalCost, AVG(Cost) AS AvgCost
                  FROM PATIENT_TREATMENT
                  WHERE StartDate BETWEEN ? AND ?
                  GROUP BY DATEPART(YEAR, StartDate), DATEPART(MONTH, StartDate), DATENAME(MONTH, StartDate)
                  ORDER BY Year DESC, Month DESC",
        'params' => [$filterDateFrom, $filterDateTo]
    ],
];

// ── FETCH REPORT DATA ──
$reportData = [];
$currentReportTitle = '';
if ($activeReport && isset($reportSQL[$activeReport])) {
    $currentReportTitle = $reportCategories[$activeCategory]['reports'][$activeReport] ?? $activeReport;
    $sqlDef = $reportSQL[$activeReport];
    $reportData = dbFetchAll(dbQuery($sqlDef['sql'], $sqlDef['params']));
}

// ── CHART DATA ──
$chartLabels = [];
$chartValues = [];
if ($activeReport && in_array($activeReport, ['complaints_by_severity', 'most_prescribed'])) {
    $chartLabels = array_column($reportData, array_keys($reportData[0] ?? [])[0] ?? '');
    $chartValues = array_column($reportData, array_keys($reportData[0] ?? [])[1] ?? '');
}

include 'components/header.php';
?>

<?php include 'components/sidebar.php'; ?>

<div class="main-content">
    <?php include 'components/topbar.php'; ?>

    <!-- Executive Summary KPIs (shown on main page or with executive report) -->
    <?php if (!$activeReport || $activeReport === 'executive_summary'): ?>
    <div class="executive-summary animate-fade-in">
        <div class="kpi-card">
            <div class="kpi-label">Total Patients</div>
            <div class="kpi-value" data-counter="<?php echo $execKPIs['total_patients']; ?>">0</div>
            <div class="kpi-delta up"><?php echo $execKPIs['new_patients_month']; ?> new this month</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-label">Bed Occupancy</div>
            <div class="kpi-value" data-counter="<?php echo $execKPIs['bed_occupancy_pct']; ?>">0</div>
            <div class="kpi-delta <?php echo $execKPIs['bed_occupancy_pct'] > 85 ? 'down' : 'up'; ?>"><?php echo $execKPIs['occupied_beds']; ?> of <?php echo $execKPIs['total_beds']; ?> beds</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-label">Appointments (30d)</div>
            <div class="kpi-value" data-counter="<?php echo $execKPIs['completed_appts']; ?>">0</div>
            <div class="kpi-delta up"><?php echo $execKPIs['cancelled_appts']; ?> cancelled</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-label">Avg Appts/Doctor</div>
            <div class="kpi-value"><?php echo $execKPIs['avg_appts_per_doctor']; ?></div>
            <div class="kpi-delta up">per 30 days</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-label">Treatment Cost</div>
            <div class="kpi-value">$<?php echo number_format($execKPIs['total_treatment_cost'], 0); ?></div>
            <div class="kpi-delta up">all time</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-label">Top Medicine</div>
            <div class="kpi-value" style="font-size: 1.1rem;"><?php echo e($execKPIs['most_prescribed_medicine']); ?></div>
            <div class="kpi-delta up">most prescribed</div>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!$activeReport): ?>
    <!-- Report Category Cards -->
    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: var(--space-5); margin-bottom: var(--space-8);">
        <?php foreach ($reportCategories as $catKey => $cat):
            if ($catKey === 'executive') continue;
        ?>
        <div class="card animate-fade-in">
            <div class="card-body">
                <div style="display: flex; align-items: center; gap: var(--space-3); margin-bottom: var(--space-4);">
                    <div style="width: 44px; height: 44px; border-radius: var(--radius); background: var(--primary-light); color: var(--primary); display: flex; align-items: center; justify-content: center; font-size: 1.4rem;"><?php echo $cat['icon']; ?></div>
                    <h4 style="font-size: 1rem; font-weight: 700;"><?php echo e($cat['title']); ?></h4>
                </div>
                <ul style="list-style: none; display: flex; flex-direction: column; gap: 2px;">
                    <?php foreach ($cat['reports'] as $rKey => $rTitle): ?>
                    <li>
                        <a href="reports.php?category=<?php echo $catKey; ?>&report=<?php echo $rKey; ?>" class="link-action" style="font-size: 0.85rem; font-weight: 500;">
                            <?php echo e($rTitle); ?>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Active Report View -->
    <?php if ($activeReport && $activeReport !== 'executive_summary'): ?>
    <div class="breadcrumbs animate-fade-in">
        <a href="reports.php">Reports</a>
        <span class="sep">/</span>
        <a href="reports.php?category=<?php echo $activeCategory; ?>"><?php echo e($reportCategories[$activeCategory]['title'] ?? ''); ?></a>
        <span class="sep">/</span>
        <span class="current"><?php echo e($currentReportTitle); ?></span>
    </div>

    <!-- Filters Panel -->
    <div class="card animate-fade-in stagger-1" style="margin-bottom: var(--space-5);">
        <div class="card-body">
            <form method="GET" class="section-filters" style="flex-wrap: wrap; margin-bottom: 0;">
                <input type="hidden" name="report" value="<?php echo $activeReport; ?>">
                <input type="hidden" name="category" value="<?php echo $activeCategory; ?>">

                <div class="form-group" style="margin-bottom: 0;">
                    <label style="font-size: 0.7rem;">From</label>
                    <input type="date" name="date_from" value="<?php echo e($filterDateFrom); ?>" class="form-control" style="width: 150px;">
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label style="font-size: 0.7rem;">To</label>
                    <input type="date" name="date_to" value="<?php echo e($filterDateTo); ?>" class="form-control" style="width: 150px;">
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label style="font-size: 0.7rem;">Doctor</label>
                    <select name="doctor" class="form-control" style="width: 160px;">
                        <option value="">All Doctors</option>
                        <?php foreach ($allDoctors as $d): ?>
                        <option value="<?php echo $d['StaffID']; ?>" <?php echo $filterDoctor == $d['StaffID'] ? 'selected' : ''; ?>><?php echo e($d['StaffName']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label style="font-size: 0.7rem;">Patient</label>
                    <select name="patient" class="form-control" style="width: 160px;">
                        <option value="">All Patients</option>
                        <?php foreach ($allPatients as $p): ?>
                        <option value="<?php echo $p['PatientID']; ?>" <?php echo $filterPatient == $p['PatientID'] ? 'selected' : ''; ?>><?php echo e($p['PatientName']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="display: flex; align-items: flex-end; gap: var(--space-2); padding-bottom: 1px;">
                    <button type="submit" class="btn btn-primary btn-sm">Generate</button>
                    <a href="reports.php?report=<?php echo $activeReport; ?>&category=<?php echo $activeCategory; ?>" class="btn btn-ghost btn-sm">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Report Actions -->
    <div class="section-header animate-fade-in stagger-2">
        <h2><?php echo e($currentReportTitle); ?></h2>
        <div style="display: flex; gap: 8px;">
            <button class="btn btn-sm btn-secondary" onclick="exportCSV('<?php echo $activeReport; ?>', 'reportTable')">
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                Export CSV
            </button>
            <button class="btn btn-sm btn-secondary" onclick="printPage()">
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0110.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0l.229 2.523a1.125 1.125 0 01-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0021 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 00-1.913-.247M6.34 18H5.25A2.25 2.25 0 013 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 001.913-.247m10.5 0a48.536 48.536 0 00-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5zm-3 0h.008v.008H15V10.5z"/></svg>
                Print
            </button>
        </div>
    </div>

    <!-- Chart (for applicable reports) -->
    <?php if (!empty($chartLabels) && !empty($chartValues)): ?>
    <div class="card animate-fade-in stagger-3" style="margin-bottom: var(--space-5);">
        <div class="card-header"><h3>Visual Summary</h3></div>
        <div class="card-body">
            <div class="chart-container chart-container-sm">
                <canvas id="reportChart"></canvas>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Data Table -->
    <div class="table-card animate-fade-in stagger-4">
        <div class="table-responsive">
            <table class="data-table finance-table" id="reportTable">
                <thead>
                    <tr>
                        <?php if ($reportData):
                            foreach (array_keys($reportData[0]) as $col): ?>
                            <th onclick="sortTable('reportTable', <?php echo array_search($col, array_keys($reportData[0])); ?>)" class="sortable">
                                <?php echo e(str_replace('_', ' ', $col)); ?>
                            </th>
                        <?php endforeach; endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reportData as $row): ?>
                    <tr>
                        <?php foreach ($row as $key => $val):
                            $display = $val;
                            $cssClass = '';
                            if ($val instanceof DateTime) {
                                $display = fmtDate($val);
                            } elseif (is_numeric($val) && stripos($key, 'cost') !== false) {
                                $cssClass = 'currency';
                                $display = '$' . number_format((float)$val, 2);
                            } elseif (is_numeric($val) && (float)$val > 1000) {
                                $display = number_format((float)$val, 2);
                            }
                            echo '<td' . ($cssClass ? ' class="' . $cssClass . '"' : '') . '>' . e((string)$display) . '</td>';
                        endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if (empty($reportData)): ?>
        <div class="empty-state" style="padding: var(--space-10);">
            <div class="empty-state-icon" style="color: var(--primary); opacity: 0.8;"><i class="fa-regular fa-folder-open fa-2x"></i></div>
            <h4>No Data Found</h4>
            <p>Try adjusting your date range or filters.</p>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- API Health Card -->
    <?php if (!$activeReport): ?>
    <div class="card animate-fade-in" style="margin-top: var(--space-8);">
        <div class="card-header">
            <h3>API Status</h3>
            <span class="badge" id="apiHealthBadge">Checking...</span>
        </div>
        <div class="card-body">
            <div id="apiHealthContent" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: var(--space-4);">
                <div class="skeleton skeleton-text" style="width: 100%;"></div>
                <div class="skeleton skeleton-text" style="width: 80%;"></div>
                <div class="skeleton skeleton-text" style="width: 60%;"></div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php include 'components/footer.php'; ?>

<?php if (!empty($chartLabels) && !empty($chartValues)): ?>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const ctx = document.getElementById('reportChart');
    if (ctx) {
        new Chart(ctx, {
            type: '<?php echo $activeReport === 'most_prescribed' ? 'horizontalBar' : 'bar'; ?>',
            data: {
                labels: <?php echo json_encode($chartLabels); ?>,
                datasets: [{
                    label: 'Count',
                    data: <?php echo json_encode(array_map('intval', $chartValues)); ?>,
                    backgroundColor: [
                        'rgba(37, 99, 235, 0.7)',
                        'rgba(6, 182, 212, 0.7)',
                        'rgba(16, 185, 129, 0.7)',
                        'rgba(245, 158, 11, 0.7)',
                        'rgba(239, 68, 68, 0.7)',
                        'rgba(99, 102, 241, 0.7)',
                        'rgba(139, 92, 246, 0.7)',
                        'rgba(236, 72, 153, 0.7)',
                    ],
                    borderRadius: 6,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.04)' } },
                    x: { grid: { display: false } }
                }
            }
        });
    }
});
</script>
<?php endif; ?>

<script>
// Fetch API health status
<?php if (!$activeReport): ?>
document.addEventListener('DOMContentLoaded', () => {
    fetch('api/medicine_api_proxy.php?action=health')
        .then(r => r.json())
        .then(data => {
            const badge = document.getElementById('apiHealthBadge');
            const content = document.getElementById('apiHealthContent');

            if (data.online) {
                badge.className = 'badge badge-green';
                badge.textContent = 'Online';
            } else {
                badge.className = 'badge badge-red';
                badge.textContent = 'Offline';
            }

            content.innerHTML = `
                <div class="kpi-card" style="padding: var(--space-4);">
                    <div class="kpi-label">Avg Response</div>
                    <div class="kpi-value" style="font-size: 1.5rem;">${data.avg_response_time || 0}ms</div>
                </div>
                <div class="kpi-card" style="padding: var(--space-4);">
                    <div class="kpi-label">Searches Today</div>
                    <div class="kpi-value" style="font-size: 1.5rem;">${data.searches_today || 0}</div>
                </div>
                <div class="kpi-card" style="padding: var(--space-4);">
                    <div class="kpi-label">Details Today</div>
                    <div class="kpi-value" style="font-size: 1.5rem;">${data.details_today || 0}</div>
                </div>
                <div class="kpi-card" style="padding: var(--space-4);">
                    <div class="kpi-label">Status</div>
                    <div style="font-size: 0.85rem; font-weight: 600; color: var(--text);">${data.message || 'Unknown'}</div>
                </div>
            `;
        })
        .catch(() => {
            document.getElementById('apiHealthBadge').className = 'badge badge-amber';
            document.getElementById('apiHealthBadge').textContent = 'Unknown';
            document.getElementById('apiHealthContent').innerHTML = '<p class="text-muted">Unable to fetch API status.</p>';
        });
});
<?php endif; ?>
</script>
