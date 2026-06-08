<?php
/**
 * Ivor Paine Memorial Hospital - Helper Functions
 * Safe output, formatting, badge rendering, and utility functions
 */

require_once __DIR__ . '/db.php';

// ============================================================================
// SAFE OUTPUT
// ============================================================================

/**
 * Escape HTML entities for safe output
 */
function e($string) {
    return htmlspecialchars((string)$string, ENT_QUOTES, 'UTF-8');
}

/**
 * Format a SQL Server date for display
 */
function fmtDate($date, $format = 'M j, Y') {
    if ($date instanceof DateTime) {
        return $date->format($format);
    }
    if ($date) {
        $ts = strtotime($date);
        return $ts ? date($format, $ts) : (string)$date;
    }
    return 'N/A';
}

/**
 * Format a SQL Server time
 */
function fmtTime($time, $format = 'h:i A') {
    if ($time instanceof DateTime) {
        return $time->format($format);
    }
    if ($time) {
        $ts = strtotime($time);
        return $ts ? date($format, $ts) : (string)$time;
    }
    return 'N/A';
}

/**
 * Format datetime combined
 */
function fmtDateTime($date, $time) {
    return fmtDate($date) . ' at ' . fmtTime($time);
}

/**
 * Calculate age from date of birth
 */
function calcAge($dob) {
    if ($dob instanceof DateTime) {
        $dob = $dob->format('Y-m-d');
    }
    if (!$dob) return 'N/A';
    $birth = new DateTime($dob);
    $today = new DateTime();
    return $birth->diff($today)->y;
}

/**
 * Format currency
 */
function fmtCurrency($amount) {
    return '$' . number_format((float)$amount, 2);
}

// ============================================================================
// BADGE RENDERING
// ============================================================================

function badge($text, $variant = 'gray') {
    $variants = [
        'blue'    => 'badge-blue',
        'green'   => 'badge-green',
        'purple'  => 'badge-purple',
        'gray'    => 'badge-gray',
        'red'     => 'badge-red',
        'amber'   => 'badge-amber',
        'cyan'    => 'badge-cyan',
        'emerald' => 'badge-emerald',
        'rose'    => 'badge-rose',
        'indigo'  => 'badge-indigo',
    ];
    $class = $variants[$variant] ?? 'badge-gray';
    return '<span class="badge ' . $class . '">' . e($text) . '</span>';
}

function statusBadge($status) {
    $map = [
        'Scheduled'   => ['blue',   'Scheduled'],
        'Completed'   => ['green',  'Completed'],
        'Cancelled'   => ['gray',   'Cancelled'],
        'Active'      => ['emerald','Active'],
        'Inactive'    => ['gray',   'Inactive'],
        'Occupied'    => ['red',    'Occupied'],
        'Available'   => ['green',  'Available'],
        'Maintenance' => ['amber',  'Maintenance'],
        'Reserved'    => ['purple', 'Reserved'],
        'Admitted'    => ['blue',   'Admitted'],
        'Discharged'  => ['gray',   'Discharged'],
        'Low'         => ['green',  'Low'],
        'Medium'      => ['amber',  'Medium'],
        'High'        => ['red',    'High'],
        'Critical'    => ['rose',   'Critical'],
        'Resolved'    => ['green',  'Resolved'],
        'Unresolved'  => ['amber',  'Unresolved'],
        'Pending'     => ['amber',  'Pending'],
    ];
    $info = $map[$status] ?? ['gray', $status];
    return badge($info[1], $info[0]);
}

function genderBadge($gender) {
    $g = strtolower((string)$gender);
    if ($g === 'male') return badge('Male', 'blue');
    if ($g === 'female') return badge('Female', 'purple');
    return badge($gender, 'gray');
}

function positionBadge($position) {
    $map = [
        'Consultant'          => 'badge-blue',
        'Registrar'           => 'badge-emerald',
        'Senior Houseman'     => 'badge-purple',
        'Junior Houseman'     => 'badge-indigo',
        'Assistant Registrar' => 'badge-cyan',
        'Student'             => 'badge-gray',
    ];
    $class = $map[$position] ?? 'badge-gray';
    return '<span class="badge ' . $class . '">' . e($position) . '</span>';
}

// ============================================================================
// AVATAR
// ============================================================================

function avatar($name, $size = 32, $variant = 'default') {
    $parts = array_filter(explode(' ', trim($name)), fn($p) => !in_array(strtolower($p), ['dr.', 'nurse', 'mr.', 'mrs.', 'ms.']));
    $initials = strtoupper(substr(implode('', array_map(fn($p) => $p[0] ?? '', $parts)), 0, 2));
    if (strlen($initials) < 1) $initials = '?';

    $colors = [
        'default' => ['bg' => 'var(--primary-light)', 'color' => 'var(--primary)'],
        'doctor'  => ['bg' => '#ecfdf5', 'color' => '#059669'],
        'nurse'   => ['bg' => '#fdf4ff', 'color' => '#7c3aed'],
        'patient' => ['bg' => '#eff6ff', 'color' => '#2563eb'],
    ];
    $c = $colors[$variant] ?? $colors['default'];
    $style = "width:{$size}px;height:{$size}px;background:{$c['bg']};color:{$c['color']};font-size:" . ($size * 0.4) . "px;";

    return '<div class="avatar" style="' . $style . '">' . $initials . '</div>';
}

// ============================================================================
// INITIALS
// ============================================================================

function getInitials($name) {
    $parts = array_filter(explode(' ', trim($name)), fn($p) => !in_array(strtolower($p), ['dr.', 'nurse']));
    return strtoupper(substr(implode('', array_map(fn($p) => $p[0] ?? '', $parts)), 0, 2));
}

// ============================================================================
// SIDEBAR HELPERS
// ============================================================================

function isActive($page) {
    return basename($_SERVER['PHP_SELF']) === $page ? 'active' : '';
}

function navLink($href, $label, $iconSvg, $page) {
    $active = isActive($page);
    return '<li><a href="' . $href . '" class="' . $active . '">' . $iconSvg . '<span>' . e($label) . '</span></a></li>';
}

// ============================================================================
// PAGINATION
// ============================================================================

function paginate($totalItems, $itemsPerPage, $currentPage) {
    $totalPages = max(1, ceil($totalItems / $itemsPerPage));
    $currentPage = min(max(1, (int)$currentPage), $totalPages);
    $offset = ($currentPage - 1) * $itemsPerPage;
    return [$offset, $currentPage, $totalPages];
}

function paginationHtml($totalPages, $currentPage, $baseUrl) {
    if ($totalPages <= 1) return '';
    $html = '<div class="pagination">';
    // Prev
    if ($currentPage > 1) {
        $html .= '<a href="' . $baseUrl . '&page=' . ($currentPage - 1) . '" class="pagination-btn">&larr;</a>';
    } else {
        $html .= '<span class="pagination-btn disabled">&larr;</span>';
    }
    // Pages
    $start = max(1, $currentPage - 2);
    $end = min($totalPages, $currentPage + 2);
    if ($start > 1) $html .= '<a href="' . $baseUrl . '&page=1" class="pagination-btn">1</a><span class="pagination-ellipsis">...</span>';
    for ($i = $start; $i <= $end; $i++) {
        $active = $i === $currentPage ? ' active' : '';
        $html .= '<a href="' . $baseUrl . '&page=' . $i . '" class="pagination-btn' . $active . '">' . $i . '</a>';
    }
    if ($end < $totalPages) $html .= '<span class="pagination-ellipsis">...</span><a href="' . $baseUrl . '&page=' . $totalPages . '" class="pagination-btn">' . $totalPages . '</a>';
    // Next
    if ($currentPage < $totalPages) {
        $html .= '<a href="' . $baseUrl . '&page=' . ($currentPage + 1) . '" class="pagination-btn">&rarr;</a>';
    } else {
        $html .= '<span class="pagination-btn disabled">&rarr;</span>';
    }
    $html .= '</div>';
    return $html;
}

// ============================================================================
// TOAST HELPER
// ============================================================================

function toastScript($type, $message) {
    return "<script>document.addEventListener('DOMContentLoaded', () => showToast('" . e($type) . "', '" . e($message) . "'));</script>";
}

// ============================================================================
// KPI QUERY HELPERS
// ============================================================================

function getKPIs($conn) {
    return [
        'totalPatients'    => dbScalar("SELECT COUNT(*) FROM PATIENT"),
        'activeDoctors'    => dbScalar("SELECT COUNT(*) FROM DOCTOR"),
        'totalNurses'      => dbScalar("SELECT COUNT(*) FROM NURSE"),
        'todaysAppts'      => dbScalar("SELECT COUNT(*) FROM APPOINTMENT WHERE ApptDate = CAST(GETDATE() AS DATE)"),
        'activeAdmissions' => dbScalar("SELECT COUNT(*) FROM PATIENT WHERE BedNumber IS NOT NULL"),
        'totalBeds'        => dbScalar("SELECT COUNT(*) FROM BED"),
        'availableBeds'    => dbScalar("SELECT COUNT(*) FROM BED WHERE Status = 'Available'"),
        'occupiedBeds'     => dbScalar("SELECT COUNT(*) FROM BED WHERE Status = 'Occupied'"),
        'criticalComplaints'=> dbScalar("SELECT COUNT(*) FROM COMPLAINT WHERE Severity = 'Critical' AND DateResolved IS NULL"),
        'unresolvedComplaints' => dbScalar("SELECT COUNT(*) FROM COMPLAINT WHERE DateResolved IS NULL"),
    ];
}

// ============================================================================
// ACTIVITY LOGGING
// ============================================================================

function logActivity($conn, $action, $entityType, $entityId, $details = '') {
    // Check if ACTIVITY_LOG table exists
    $check = dbQuery("SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'ACTIVITY_LOG'");
    if (!$check || !dbFetchOne($check)) return;

    dbQuery(
        "INSERT INTO ACTIVITY_LOG (ActionType, EntityType, EntityID, Details, LoggedAt) VALUES (?, ?, ?, ?, GETDATE())",
        [$action, $entityType, $entityId, $details]
    );
}

// ============================================================================
// PERFORMANCE BADGE
// ============================================================================

function performanceBadge($rating) {
    $r = (float)$rating;
    if ($r >= 9) return badge('Excellent', 'emerald');
    if ($r >= 7) return badge('Good', 'blue');
    if ($r >= 5) return badge('Satisfactory', 'amber');
    return badge('Needs Review', 'red');
}

// ============================================================================
// STAR RATING DISPLAY
// ============================================================================

function starRating($rating) {
    $stars = '';
    $full = floor($rating);
    $half = $rating - $full >= 0.5;
    for ($i = 0; $i < 5; $i++) {
        if ($i < $full) $stars .= '<span class="star full">&#9733;</span>';
        elseif ($i === $full && $half) $stars .= '<span class="star half">&#9733;</span>';
        else $stars .= '<span class="star empty">&#9734;</span>';
    }
    return '<div class="star-rating">' . $stars . '<span class="rating-value">' . number_format($rating, 1) . '</span></div>';
}
