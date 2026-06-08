<?php
/**
 * Audit Trail System
 * Task 50: Comprehensive audit logging for all important system actions
 */
require_once 'includes/db.php';
require_once 'includes/helpers.php';
require_once 'includes/medicine_api.php';

$pageTitle = 'Audit Log';
$pageSubtitle = 'Complete trail of all system actions for accountability and compliance';

// ── FILTERS ──
$filterAction = $_GET['action_type'] ?? '';
$filterEntity = $_GET['entity_type'] ?? '';
$filterDateFrom = $_GET['date_from'] ?? '';
$filterDateTo = $_GET['date_to'] ?? '';
$filterUser = $_GET['user'] ?? '';
$search = $_GET['search'] ?? '';

// ── BUILD QUERY ──
$whereParts = ['1=1'];
$params = [];

if ($filterAction) {
    $whereParts[] = "ActionType = ?";
    $params[] = $filterAction;
}
if ($filterEntity) {
    $whereParts[] = "EntityType = ?";
    $params[] = $filterEntity;
}
if ($filterDateFrom) {
    $whereParts[] = "CAST(CreatedAt AS DATE) >= ?";
    $params[] = $filterDateFrom;
}
if ($filterDateTo) {
    $whereParts[] = "CAST(CreatedAt AS DATE) <= ?";
    $params[] = $filterDateTo;
}
if ($filterUser) {
    $whereParts[] = "UserName LIKE ?";
    $params[] = "%$filterUser%";
}
if ($search) {
    $whereParts[] = "(Description LIKE ? OR EntityID LIKE ? OR UserName LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$whereSql = implode(' AND ', $whereParts);

// ── PAGINATION ──
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 25;
$totalLogs = dbScalar("SELECT COUNT(*) FROM AUDIT_LOG WHERE $whereSql", $params);
list($offset, $currentPage, $totalPages) = paginate($totalLogs, $perPage, $page);

// ── FETCH LOGS ──
$logs = dbFetchAll(dbQuery(
    "SELECT * FROM AUDIT_LOG WHERE $whereSql ORDER BY CreatedAt DESC OFFSET ? ROWS FETCH NEXT ? ROWS ONLY",
    array_merge($params, [$offset, $perPage])
));

// ── REFERENCE DATA ──
$actionTypes = dbFetchAll(dbQuery("SELECT DISTINCT ActionType FROM AUDIT_LOG ORDER BY ActionType"));
$entityTypes = dbFetchAll(dbQuery("SELECT DISTINCT EntityType FROM AUDIT_LOG ORDER BY EntityType"));
$users = dbFetchAll(dbQuery("SELECT DISTINCT UserName FROM AUDIT_LOG WHERE UserName IS NOT NULL ORDER BY UserName"));

// ── ACTION TYPE STYLES ──
function actionIcon($type) {
    $icons = [
        'create' => '<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>',
        'update' => '<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10"/></svg>',
        'delete' => '<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/></svg>',
        'search' => '<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/></svg>',
        'view'   => '<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>',
    ];
    return $icons[$type] ?? $icons['view'];
}

function actionClass($type) {
    return $type;
}

$actions = '';

include 'components/header.php';
?>

<?php include 'components/sidebar.php'; ?>

<div class="main-content">
    <?php include 'components/topbar.php'; ?>

    <div class="section-header animate-fade-in">
        <h2>Audit Log <span class="text-muted" style="font-weight: 400;">(<?php echo $totalLogs; ?> entries)</span></h2>
        <div style="display: flex; gap: 8px;">
            <button class="btn btn-sm btn-secondary" onclick="exportCSV('audit_log', 'auditTable')">
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                Export CSV
            </button>
        </div>
    </div>

    <!-- Filters -->
    <div class="card animate-fade-in stagger-1" style="margin-bottom: var(--space-5);">
        <div class="card-body">
            <form method="GET" class="section-filters" style="flex-wrap: wrap; margin-bottom: 0;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label style="font-size: 0.7rem;">Action</label>
                    <select name="action_type" class="form-control" style="width: 130px;">
                        <option value="">All</option>
                        <?php foreach ($actionTypes as $a): ?>
                        <option value="<?php echo e($a['ActionType']); ?>" <?php echo $filterAction === $a['ActionType'] ? 'selected' : ''; ?>><?php echo e($a['ActionType']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label style="font-size: 0.7rem;">Entity</label>
                    <select name="entity_type" class="form-control" style="width: 130px;">
                        <option value="">All</option>
                        <?php foreach ($entityTypes as $e): ?>
                        <option value="<?php echo e($e['EntityType']); ?>" <?php echo $filterEntity === $e['EntityType'] ? 'selected' : ''; ?>><?php echo e($e['EntityType']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label style="font-size: 0.7rem;">From</label>
                    <input type="date" name="date_from" value="<?php echo e($filterDateFrom); ?>" class="form-control" style="width: 140px;">
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label style="font-size: 0.7rem;">To</label>
                    <input type="date" name="date_to" value="<?php echo e($filterDateTo); ?>" class="form-control" style="width: 140px;">
                </div>
                <div class="search-wrap" style="margin-bottom: 0;">
                    <svg class="search-icon" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/></svg>
                    <input type="text" name="search" value="<?php echo e($search); ?>" class="form-control" placeholder="Search..." style="width: 180px; padding-left: 36px;">
                </div>
                <div style="display: flex; align-items: flex-end; gap: var(--space-2); padding-bottom: 1px;">
                    <button type="submit" class="btn btn-primary btn-sm">Filter</button>
                    <a href="audit_log.php" class="btn btn-ghost btn-sm">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Audit Log Entries -->
    <div class="card animate-fade-in stagger-2">
        <div class="card-body" style="padding: 0;">
            <?php if (!empty($logs)): ?>
            <div class="table-responsive">
                <table class="data-table" id="auditTable">
                    <thead>
                        <tr>
                            <th style="width: 40px;"></th>
                            <th>Action</th>
                            <th>Entity</th>
                            <th>Description</th>
                            <th>User</th>
                            <th style="text-align: right;">Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                        <tr>
                            <td>
                                <div class="audit-log-icon <?php echo actionClass($log['ActionType']); ?>">
                                    <?php echo actionIcon($log['ActionType']); ?>
                                </div>
                            </td>
                            <td>
                                <span class="badge badge-<?php echo $log['ActionType'] === 'create' ? 'green' : ($log['ActionType'] === 'delete' ? 'red' : ($log['ActionType'] === 'update' ? 'blue' : 'amber')); ?>">
                                    <?php echo e($log['ActionType']); ?>
                                </span>
                            </td>
                            <td class="text-muted"><?php echo e($log['EntityType']); ?></td>
                            <td>
                                <div style="font-weight: 500; font-size: 0.85rem;"><?php echo e($log['Description']); ?></div>
                                <?php if ($log['EntityID']): ?>
                                <span class="id-tag">ID: <?php echo e($log['EntityID']); ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="text-muted"><?php echo e($log['UserName'] ?: 'System'); ?></td>
                            <td style="text-align: right; font-size: 0.78rem; color: var(--text-muted); white-space: nowrap;">
                                <?php echo fmtDate($log['CreatedAt'], 'M j, Y'); ?><br>
                                <?php echo fmtDate($log['CreatedAt'], 'g:i A'); ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="empty-state" style="padding: var(--space-10);">
                <div class="empty-state-icon" style="width: 64px; height: 64px;">&#128203;</div>
                <h4>No Audit Entries Found</h4>
                <p>Audit trail entries will appear here once system actions are logged.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($totalPages > 1): echo paginationHtml($totalPages, $currentPage, 'audit_log.php?action_type=' . e($filterAction) . '&entity_type=' . e($filterEntity)); endif; ?>
</div>

<?php include 'components/footer.php'; ?>
