<?php
/**
 * Topbar Component - Include after sidebar, inside main-content
 */
if (!isset($pageTitle)) $pageTitle = 'Dashboard';
if (!isset($pageSubtitle)) $pageSubtitle = '';
if (!isset($showSearch)) $showSearch = true;
if (!isset($actions)) $actions = '';
?>
<div class="topbar">
    <div>
        <h1><?php echo e($pageTitle); ?></h1>
        <?php if ($pageSubtitle): ?>
            <div class="topbar-meta"><?php echo e($pageSubtitle); ?></div>
        <?php endif; ?>
    </div>
    <div class="topbar-right">
        <!-- Search bar moved to sidebar -->
        <?php echo $actions; ?>

        <div class="live-clock" id="liveClock">
            <span class="time" id="clockTime">--:--</span>
            <span class="date" id="clockDate">Loading...</span>
        </div>

        <button class="theme-toggle" id="themeToggle" onclick="toggleTheme()" title="Toggle dark mode" aria-label="Toggle theme">
            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" id="themeIcon">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.718 9.718 0 0118 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 003 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 009.002-5.998z"/>
            </svg>
        </button>
    </div>
</div>
