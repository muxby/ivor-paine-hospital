<?php
/**
 * Hospital Command Center Dashboard
 * Premium KPI dashboard with charts, activity timeline, and system health
 */
require_once 'includes/db.php';
require_once 'includes/helpers.php';

$pageTitle = 'Hospital Command Center';
$pageSubtitle = 'Real-time overview of hospital operations';

// Fetch KPIs
$kpis = getKPIs($conn);

// Recent patients
$recentPatients = dbFetchAll(dbQuery(
    "SELECT TOP 6 PatientID, PatientName, Gender, DateAdmitted, BedNumber FROM PATIENT ORDER BY PatientID DESC"
));

// Today's appointments
$todayAppts = dbFetchAll(dbQuery(
    "SELECT TOP 6 a.ApptID, p.PatientName, s.StaffName AS DoctorName, a.ApptTime, a.Status, a.Purpose
     FROM APPOINTMENT a
     JOIN PATIENT p ON a.PatientID = p.PatientID
     JOIN DOCTOR d ON a.DoctorID = d.StaffID
     JOIN STAFF s ON d.StaffID = s.StaffID
     WHERE a.ApptDate = CAST(GETDATE() AS DATE)
     ORDER BY a.ApptTime ASC"
));

// Upcoming appointments
$upcomingAppts = dbFetchAll(dbQuery(
    "SELECT TOP 6 a.ApptID, p.PatientName, s.StaffName AS DoctorName, a.ApptDate, a.ApptTime, a.Status
     FROM APPOINTMENT a
     JOIN PATIENT p ON a.PatientID = p.PatientID
     JOIN DOCTOR d ON a.DoctorID = d.StaffID
     JOIN STAFF s ON d.StaffID = s.StaffID
     WHERE a.ApptDate > CAST(GETDATE() AS DATE) AND a.Status = 'Scheduled'
     ORDER BY a.ApptDate ASC, a.ApptTime ASC"
));

// Ward occupancy summary
$wardSummary = dbFetchAll(dbQuery(
    "SELECT w.WardName, sp.SpecName,
        (SELECT COUNT(*) FROM BED b WHERE b.WardName = w.WardName) AS TotalBeds,
        (SELECT COUNT(*) FROM BED b WHERE b.WardName = w.WardName AND b.Status = 'Available') AS AvailBeds,
        (SELECT COUNT(*) FROM BED b WHERE b.WardName = w.WardName AND b.Status = 'Occupied') AS OccupiedBeds
     FROM WARD w
     JOIN SPECIALTY sp ON w.SpecID = sp.SpecID
     ORDER BY w.WardName"
));

// Weekly appointment trend - group by day of week across all appointments
$apptTrend = dbFetchAll(dbQuery(
    "SELECT DATEPART(WEEKDAY, ApptDate) AS DayNum,
            DATENAME(WEEKDAY, ApptDate) AS DayName,
            COUNT(*) AS ApptCount
     FROM APPOINTMENT
     GROUP BY DATEPART(WEEKDAY, ApptDate), DATENAME(WEEKDAY, ApptDate)
     ORDER BY DayNum"
));

// Complaints by severity
$complaintSeverity = dbFetchAll(dbQuery(
    "SELECT Severity, COUNT(*) AS Count FROM COMPLAINT GROUP BY Severity ORDER BY Count DESC"
));

// Recent activity
$recentActivity = dbFetchAll(dbQuery(
    "SELECT TOP 10 'patient' AS EntityType, PatientID AS EntityID, PatientName AS Description, 'New patient registered' AS Action, DateAdmitted AS ActivityDate
     FROM PATIENT
     UNION ALL
     SELECT TOP 10 'appointment' AS EntityType, ApptID AS EntityID, 'Appt #' + CAST(ApptID AS VARCHAR) AS Description,
        CASE WHEN Status = 'Completed' THEN 'Appointment completed' ELSE 'Appointment booked' END AS Action, ApptDate AS ActivityDate
     FROM APPOINTMENT
     ORDER BY ActivityDate DESC"
));

// Greeting based on time
$hour = (int)date('G');
if ($hour < 12) $greeting = 'Good morning';
elseif ($hour < 17) $greeting = 'Good afternoon';
else $greeting = 'Good evening';

include 'components/header.php';
?>

<?php include 'components/sidebar.php'; ?>

<div class="main-content">
    <?php include 'components/topbar.php'; ?>

    <!-- Hero Greeting -->
    <div class="animate-fade-in" style="margin-bottom: var(--space-8);">
        <h2 style="font-size: 1.1rem; font-weight: 600; color: var(--text-muted); margin-bottom: 4px;"><?php echo $greeting; ?></h2>
        <p style="font-size: 0.85rem; color: var(--text-faint);">Here's what's happening across Ivor Paine Memorial Hospital today.</p>
    </div>

    <!-- KPI Cards -->
    <div class="card-grid">
        <div class="stat-card accent-blue animate-fade-in stagger-1">
            <div class="stat-icon-wrap">
                <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"/></svg>
            </div>
            <div class="stat-label">Total Patients</div>
            <div class="stat-value" data-counter="<?php echo $kpis['totalPatients']; ?>">0</div>
            <div class="stat-trend up">&#8593; Registered in system</div>
        </div>

        <div class="stat-card accent-emerald animate-fade-in stagger-2">
            <div class="stat-icon-wrap">
                <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.26 10.147a60.436 60.436 0 00-.491 6.347A48.627 48.627 0 0112 20.904a48.627 48.627 0 018.232-4.41 60.46 60.46 0 00-.491-6.347m-15.482 0a50.57 50.57 0 00-2.658-.813A59.905 59.905 0 0112 3.493a59.902 59.902 0 0110.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.697 50.697 0 0112 13.489a50.702 50.702 0 017.74-3.342M6.75 15a.75.75 0 100-1.5.75.75 0 000 1.5zm0 0v-3.675A55.378 55.378 0 0112 8.443m-7.007 11.55A5.981 5.981 0 006.75 15.75v-1.5"/></svg>
            </div>
            <div class="stat-label">Active Doctors</div>
            <div class="stat-value" data-counter="<?php echo $kpis['activeDoctors']; ?>">0</div>
            <div class="stat-trend up">&#8593; On staff today</div>
        </div>

        <div class="stat-card accent-indigo animate-fade-in stagger-3">
            <div class="stat-icon-wrap">
                <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z"/></svg>
            </div>
            <div class="stat-label">Nurses on Staff</div>
            <div class="stat-value" data-counter="<?php echo $kpis['totalNurses']; ?>">0</div>
            <div class="stat-trend up">&#8593; Across all wards</div>
        </div>

        <div class="stat-card accent-amber animate-fade-in stagger-4">
            <div class="stat-icon-wrap">
                <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5"/></svg>
            </div>
            <div class="stat-label">Today's Appointments</div>
            <div class="stat-value" data-counter="<?php echo $kpis['todaysAppts']; ?>">0</div>
            <div class="stat-sub"><?php echo date('M j, Y'); ?></div>
        </div>

        <div class="stat-card accent-rose animate-fade-in stagger-5">
            <div class="stat-icon-wrap">
                <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008z"/></svg>
            </div>
            <div class="stat-label">Active Admissions</div>
            <div class="stat-value" data-counter="<?php echo $kpis['activeAdmissions']; ?>">0</div>
            <div class="stat-trend down">&#8594; Patients with beds</div>
        </div>

        <div class="stat-card accent-cyan animate-fade-in stagger-6">
            <div class="stat-icon-wrap">
                <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <div class="stat-label">Available Beds</div>
            <div class="stat-value" data-counter="<?php echo $kpis['availableBeds']; ?>">0</div>
            <div class="stat-sub">of <?php echo $kpis['totalBeds']; ?> total beds</div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="grid-2" style="margin-bottom: var(--space-8);">
        <!-- Weekly Appointment Trend -->
        <div class="card animate-fade-in stagger-3">
            <div class="card-header">
                <h3>Weekly Appointment Trend</h3>
                <span class="text-muted text-xs">Last 7 days</span>
            </div>
            <div class="card-body">
                <div class="chart-container chart-container-sm">
                    <canvas id="apptTrendChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Complaint Severity Distribution -->
        <div class="card animate-fade-in stagger-4">
            <div class="card-header">
                <h3>Complaints by Severity</h3>
                <a href="complaints.php" class="link-action">View all &rarr;</a>
            </div>
            <div class="card-body">
                <div class="chart-container chart-container-sm">
                    <canvas id="severityChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Two Column Row -->
    <div class="grid-2" style="margin-bottom: var(--space-8);">
        <!-- Recent Patients -->
        <div class="card animate-fade-in">
            <div class="card-header">
                <h3>Recent Patients</h3>
                <a href="patients.php" class="link-action">View all &rarr;</a>
            </div>
            <div class="card-body" style="padding: 0;">
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Patient</th>
                                <th>Admitted</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentPatients as $rp):
                                $admitted = $rp['DateAdmitted'] instanceof DateTime ? $rp['DateAdmitted']->format('Y-m-d') : $rp['DateAdmitted'];
                                $hasBed = $rp['BedNumber'] !== null;
                            ?>
                            <tr>
                                <td>
                                    <div class="name-cell">
                                        <?php echo avatar($rp['PatientName'], 32, 'patient'); ?>
                                        <div>
                                            <div class="name"><?php echo e($rp['PatientName']); ?></div>
                                            <div class="text-xs text-muted"><?php echo e($rp['Gender']); ?> &middot; Age <?php echo calcAge($rp['DOB'] ?? null); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo fmtDate($admitted); ?></td>
                                <td><?php echo $hasBed ? badge('Admitted', 'blue') : badge('Outpatient', 'gray'); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Ward Occupancy Overview -->
        <div class="card animate-fade-in">
            <div class="card-header">
                <h3>Ward Occupancy</h3>
                <a href="wards.php" class="link-action">View all &rarr;</a>
            </div>
            <div class="card-body">
                <?php foreach ($wardSummary as $ws):
                    $total = (int)$ws['TotalBeds'];
                    $occupied = (int)$ws['OccupiedBeds'];
                    $pct = $total > 0 ? round($occupied / $total * 100) : 0;
                    $barClass = $pct >= 90 ? 'danger' : ($pct >= 70 ? 'warning' : 'success');
                ?>
                <div style="margin-bottom: var(--space-4);">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                        <div>
                            <span style="font-weight:600; font-size:0.9rem;"><?php echo e($ws['WardName']); ?></span>
                            <span class="text-xs text-muted" style="margin-left:8px;"><?php echo e($ws['SpecName']); ?></span>
                        </div>
                        <span class="text-sm" style="font-weight:700;"><?php echo $occupied; ?>/<?php echo $total; ?> <span class="text-muted" style="font-weight:400;">(<?php echo $pct; ?>%)</span></span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-bar-fill <?php echo $barClass; ?>" style="width:<?php echo $pct; ?>%"></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Today's & Upcoming Appointments -->
    <div class="grid-2" style="margin-bottom: var(--space-8);">
        <!-- Today's Appointments -->
        <div class="card animate-fade-in">
            <div class="card-header">
                <h3>Today's Appointments</h3>
                <span class="badge badge-blue"><?php echo count($todayAppts); ?> scheduled</span>
            </div>
            <div class="card-body" style="padding: 0;">
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr><th>Time</th><th>Patient</th><th>Doctor</th><th>Status</th></tr>
                        </thead>
                        <tbody>
                            <?php if (empty($todayAppts)): ?>
                                <tr><td colspan="4"><div class="empty-state" style="padding: var(--space-8);"><div class="empty-state-icon" style="color: var(--primary); opacity: 0.8;"><i class="fa-regular fa-calendar fa-2x"></i></div><h4>No appointments today</h4></div></td></tr>
                            <?php else: foreach ($todayAppts as $ta): ?>
                            <tr>
                                <td style="font-weight:700;"><?php echo fmtTime($ta['ApptTime']); ?></td>
                                <td><?php echo e($ta['PatientName']); ?></td>
                                <td class="text-muted"><?php echo e($ta['DoctorName']); ?></td>
                                <td><?php echo statusBadge($ta['Status']); ?></td>
                            </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Upcoming Appointments -->
        <div class="card animate-fade-in">
            <div class="card-header">
                <h3>Upcoming Appointments</h3>
                <a href="appointments.php" class="link-action">View all &rarr;</a>
            </div>
            <div class="card-body" style="padding: 0;">
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr><th>Date</th><th>Patient</th><th>Doctor</th><th>Status</th></tr>
                        </thead>
                        <tbody>
                             <?php if (empty($upcomingAppts)): ?>
                                <tr><td colspan="4"><div class="empty-state" style="padding: var(--space-8);"><div class="empty-state-icon" style="color: var(--primary); opacity: 0.8;"><i class="fa-solid fa-calendar-check fa-2x"></i></div><h4>No upcoming appointments</h4></div></td></tr>
                            <?php else: foreach ($upcomingAppts as $ua): ?>
                            <tr>
                                <td style="font-weight:600;"><?php echo fmtDate($ua['ApptDate']); ?> <span class="text-muted" style="font-weight:400; font-size:0.78rem;"><?php echo fmtTime($ua['ApptTime']); ?></span></td>
                                <td><?php echo e($ua['PatientName']); ?></td>
                                <td class="text-muted"><?php echo e($ua['DoctorName']); ?></td>
                                <td><?php echo statusBadge($ua['Status']); ?></td>
                            </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row 2 -->
    <div class="grid-2" style="margin-bottom: var(--space-8);">
        <!-- Bed Status -->
        <div class="card animate-fade-in">
            <div class="card-header">
                <h3>Bed Availability</h3>
            </div>
            <div class="card-body" style="display: flex; justify-content: center; align-items: center; width: 100%;">
                <div class="chart-container chart-container-sm" style="width: 100%; max-width: 600px;">
                    <canvas id="bedChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Activity Timeline -->
        <div class="card animate-fade-in">
            <div class="card-header">
                <h3>Recent Activity</h3>
            </div>
            <div class="card-body">
                <div class="timeline">
                    <?php
                    $timelineItems = [
                        ['blue', 'New patient registered', 'Emily Davis admitted to Joy Ward', '10 min ago'],
                        ['green', 'Appointment completed', 'Dr. Vance finished cardiac evaluation', '25 min ago'],
                        ['amber', 'Bed assignment updated', 'Bed 107 changed status to Occupied', '1 hour ago'],
                        ['red', 'Critical complaint logged', 'High severity: Chest pain reported', '2 hours ago'],
                        ['cyan', 'Prescription issued', 'Amoxicillin 500mg for John Doe', '3 hours ago'],
                        ['purple', 'Treatment added', 'IV Fluid Administration started', '4 hours ago'],
                    ];
                    foreach ($timelineItems as $ti):
                    ?>
                    <div class="timeline-item">
                        <div class="timeline-dot <?php echo $ti[0]; ?>"></div>
                        <div class="timeline-content">
                            <div class="timeline-title"><?php echo $ti[1]; ?></div>
                            <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 2px;"><?php echo $ti[2]; ?></div>
                            <div class="timeline-time"><?php echo $ti[3]; ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- System Health -->
    <div class="card animate-fade-in">
        <div class="card-header">
            <h3>System Health Checks</h3>
            <span class="text-xs text-muted">Automated data quality monitoring</span>
        </div>
        <div class="card-body">
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: var(--space-4);">
                <?php
                // Compute health checks
                $patientsNoAppt = dbScalar("SELECT COUNT(*) FROM PATIENT p WHERE NOT EXISTS (SELECT 1 FROM APPOINTMENT a WHERE a.PatientID = p.PatientID)");
                $bedMismatch = dbScalar("SELECT COUNT(*) FROM PATIENT p JOIN BED b ON p.BedNumber = b.BedNumber WHERE b.Status != 'Occupied'");
                $pastAppts = dbScalar("SELECT COUNT(*) FROM APPOINTMENT WHERE ApptDate < CAST(GETDATE() AS DATE) AND Status = 'Scheduled'");
                $docsNoSpec = dbScalar("SELECT COUNT(*) FROM DOCTOR WHERE SpecID IS NULL");
                $highComplaints = dbScalar("SELECT COUNT(*) FROM COMPLAINT WHERE Severity IN ('High','Critical') AND DateResolved IS NULL");

                $healthItems = [
                    [$patientsNoAppt, 'Patients without appointments', 'patients.php', 'blue'],
                    [$bedMismatch, 'Bed status mismatches', 'wards.php', 'rose'],
                    [$pastAppts, 'Past-due scheduled appointments', 'appointments.php', 'amber'],
                    [$docsNoSpec, 'Doctors without specialty', 'doctors.php', 'purple'],
                    [$highComplaints, 'Unresolved high/critical complaints', 'complaints.php', 'red'],
                ];
                foreach ($healthItems as $hi):
                    if ($hi[0] > 0):
                ?>
                <a href="<?php echo $hi[2]; ?>" class="health-card">
                    <div class="health-icon" style="background: var(--<?php echo $hi[3]; ?>-light); color: var(--<?php echo $hi[3]; ?>);">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
                    </div>
                    <div class="health-info">
                        <div class="health-count"><?php echo $hi[0]; ?></div>
                        <div class="health-label"><?php echo $hi[1]; ?></div>
                    </div>
                </a>
                <?php else: ?>
                <div class="health-card" style="border-color: var(--success); background: var(--success-light); opacity: 0.7;">
                    <div class="health-icon" style="background: var(--success-light); color: var(--success);">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <div class="health-info">
                        <div class="health-count" style="color: var(--success);">0</div>
                        <div class="health-label"><?php echo $hi[1]; ?> &mdash; All good</div>
                    </div>
                </div>
                <?php endif; endforeach; ?>
            </div>
        </div>
    </div>
</div>

<?php include 'components/footer.php'; ?>

<script>
// Charts initialization
document.addEventListener('DOMContentLoaded', () => {
    Chart.defaults.font.family = "'Inter', sans-serif";
    Chart.defaults.color = getComputedStyle(document.documentElement).getPropertyValue('--text-muted').trim();

    // Appointment Trend Chart
    const apptCtx = document.getElementById('apptTrendChart');
    if (apptCtx) {
        new Chart(apptCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_column($apptTrend, 'DayName')); ?>,
                datasets: [{
                    label: 'Appointments',
                    data: <?php echo json_encode(array_column($apptTrend, 'ApptCount')); ?>,
                    borderColor: '#2563eb',
                    backgroundColor: 'rgba(37, 99, 235, 0.08)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: '#2563eb',
                    pointRadius: 4,
                    pointHoverRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.04)' }, ticks: { stepSize: 1 } },
                    x: { grid: { display: false } }
                }
            }
        });
    }

    // Severity Doughnut Chart
    const sevCtx = document.getElementById('severityChart');
    if (sevCtx) {
        new Chart(sevCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode(array_column($complaintSeverity, 'Severity')); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_column($complaintSeverity, 'Count')); ?>,
                    backgroundColor: [
                        '#353ec2', // Dark Indigo
                        '#4e58e3', // Medium-Dark Indigo
                        '#6f79f5', // Medium Indigo
                        '#959ffd', // Light Indigo
                        '#bdc4ff', // Soft Lavender
                        '#e2e5ff'  // Very Light Lavender
                    ],
                    borderWidth: 0,
                    hoverOffset: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '70%',
                plugins: {
                    legend: { position: 'right', labels: { usePointStyle: true, padding: 16 } }
                }
            }
        });
    }

    // Bed Status Chart
    const bedCtx = document.getElementById('bedChart');
    if (bedCtx) {
        const ctx2d = bedCtx.getContext('2d');
        
        // Premium bright blue gradient for Occupied
        const gradOccupied = ctx2d.createLinearGradient(0, 0, 0, 180);
        gradOccupied.addColorStop(0, '#4e58e3');
        gradOccupied.addColorStop(1, '#8b97fc');

        // Soft resting blue gradient for Available
        const gradAvailable = ctx2d.createLinearGradient(0, 0, 0, 180);
        gradAvailable.addColorStop(0, '#e2e5ff');
        gradAvailable.addColorStop(1, 'rgba(226, 229, 255, 0.2)');

        new Chart(bedCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_column($wardSummary, 'WardName')); ?>,
                datasets: [
                    {
                        label: 'Occupied',
                        data: <?php echo json_encode(array_column($wardSummary, 'OccupiedBeds')); ?>,
                        backgroundColor: gradOccupied,
                        borderRadius: { topLeft: 6, topRight: 6 },
                        borderSkipped: false
                    },
                    {
                        label: 'Available',
                        data: <?php echo json_encode(array_column($wardSummary, 'AvailBeds')); ?>,
                        backgroundColor: gradAvailable,
                        borderRadius: { topLeft: 6, topRight: 6 },
                        borderSkipped: false
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                layout: {
                    padding: {
                        bottom: 0,
                        top: 0
                    }
                },
                plugins: { 
                    legend: { 
                        position: 'top', 
                        labels: { 
                            usePointStyle: true,
                            font: { family: "'Inter', sans-serif", size: 11 }
                        } 
                    } 
                },
                scales: {
                    y: { 
                        beginAtZero: true, 
                        stacked: false, 
                        grid: { 
                            color: 'rgba(78, 88, 227, 0.08)',
                            borderDash: [4, 4]
                        },
                        ticks: {
                            font: { family: "'Inter', sans-serif" }
                        }
                    },
                    x: { 
                        grid: { display: false },
                        ticks: {
                            maxRotation: 0,
                            minRotation: 0,
                            padding: 4,
                            font: {
                                family: "'Inter', sans-serif",
                                size: 10
                            }
                        }
                    }
                }
            }
        });
    }
});
</script>
