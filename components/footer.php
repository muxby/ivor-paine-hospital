<?php
/**
 * Footer Component - Include at the end of body, after main-content
 */
?>

<!-- Mobile Bottom Navigation -->
<nav class="mobile-nav" id="mobileNav">
    <div class="mobile-nav-items">
        <a href="index.php" class="mobile-nav-item <?php echo basename($_SERVER['PHP_SELF']) === 'index.php' ? 'active' : ''; ?>">
            <img src="assets/icons/dashboard.png" style="width:30px;height:30px;margin-bottom:2px;" alt="Home">
            Home
        </a>
        <a href="patients.php" class="mobile-nav-item <?php echo basename($_SERVER['PHP_SELF']) === 'patients.php' ? 'active' : ''; ?>">
            <img src="assets/icons/patients.png" style="width:30px;height:30px;margin-bottom:2px;" alt="Patients">
            Patients
        </a>
        <a href="appointments.php" class="mobile-nav-item <?php echo basename($_SERVER['PHP_SELF']) === 'appointments.php' ? 'active' : ''; ?>">
            <img src="assets/icons/appointments.png" style="width:30px;height:30px;margin-bottom:2px;" alt="Appts">
            Appts
        </a>
        <a href="wards.php" class="mobile-nav-item <?php echo basename($_SERVER['PHP_SELF']) === 'wards.php' ? 'active' : ''; ?>">
            <img src="assets/icons/wards.png" style="width:30px;height:30px;margin-bottom:2px;" alt="Wards">
            Wards
        </a>
        <a href="doctors.php" class="mobile-nav-item <?php echo basename($_SERVER['PHP_SELF']) === 'doctors.php' ? 'active' : ''; ?>">
            <img src="assets/icons/staff.png" style="width:30px;height:30px;margin-bottom:2px;" alt="Staff">
            Staff
        </a>
    </div>
</nav>

<script>
// ── Theme Toggle ──
function toggleTheme() {
    const html = document.documentElement;
    const current = html.getAttribute('data-theme');
    const next = current === 'dark' ? 'light' : 'dark';
    html.setAttribute('data-theme', next);
    localStorage.setItem('theme', next);
    updateThemeIcon(next);
}

function updateThemeIcon(theme) {
    const icon = document.getElementById('themeIcon');
    if (!icon) return;
    if (theme === 'dark') {
        icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386l-1.591 1.591M21 12h-2.25m-.386 6.364l-1.591-1.591M12 18.75V21m-4.773-4.227l-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z"/>';
    } else {
        icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.718 9.718 0 0118 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 003 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 009.002-5.998z"/>';
    }
}

// Update icon on load
(function() {
    const theme = document.documentElement.getAttribute('data-theme');
    updateThemeIcon(theme);
})();

// ── Live Clock ──
function updateClock() {
    const now = new Date();
    const timeEl = document.getElementById('clockTime');
    const dateEl = document.getElementById('clockDate');
    if (timeEl) {
        timeEl.textContent = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });
    }
    if (dateEl) {
        dateEl.textContent = now.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' });
    }
}
setInterval(updateClock, 1000);
updateClock();

// ── Mobile Sidebar ──
function toggleMobileSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    sidebar.classList.toggle('open');
    overlay.style.display = sidebar.classList.contains('open') ? 'block' : 'none';
}

// ── Toast Notifications ──
function showToast(type, message, duration = 4000) {
    const container = document.getElementById('toastContainer');
    if (!container) return;

    const toast = document.createElement('div');
    toast.className = 'toast toast-' + type;

    const icons = {
        success: '<svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>',
        error: '<svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>',
        warning: '<svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>',
        info: '<svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z"/></svg>'
    };

    toast.innerHTML = (icons[type] || icons.info) + '<span>' + message + '</span>';
    container.appendChild(toast);

    toast.addEventListener('click', () => hideToast(toast));

    setTimeout(() => hideToast(toast), duration);
}

function hideToast(toast) {
    toast.classList.add('hiding');
    setTimeout(() => toast.remove(), 250);
}

// ── Modal System ──
function openModal(id) {
    const modal = document.getElementById(id);
    if (modal) {
        modal.style.display = 'flex';
        modal.classList.add('open');
        document.body.style.overflow = 'hidden';
    }
}

function closeModal(id) {
    const modal = document.getElementById(id);
    if (modal) {
        modal.style.display = 'none';
        modal.classList.remove('open');
        document.body.style.overflow = '';
    }
}

// Close modal on overlay click (clicking outside the modal box)
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal-overlay')) {
        e.target.style.display = 'none';
        e.target.classList.remove('open');
        document.body.style.overflow = '';
    }
});

// Close modal on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay').forEach(m => {
            if (m.style.display === 'flex' || m.classList.contains('open')) {
                m.style.display = 'none';
                m.classList.remove('open');
            }
        });
        document.body.style.overflow = '';
    }
});

// ── Tab System ──
function switchTab(tabGroup, tabId) {
    // Deactivate all tabs in group
    document.querySelectorAll('[data-tab-group="' + tabGroup + '"]').forEach(btn => {
        btn.classList.remove('active');
    });
    document.querySelectorAll('[data-tab-panel="' + tabGroup + '"]').forEach(panel => {
        panel.classList.remove('active');
    });
    // Activate selected
    const btn = document.querySelector('[data-tab="' + tabId + '"]');
    const panel = document.getElementById(tabId);
    if (btn) btn.classList.add('active');
    if (panel) panel.classList.add('active');
}

// ── Confirmation Dialog ──
function confirmAction(title, message, onConfirm) {
    const overlay = document.createElement('div');
    overlay.className = 'modal-overlay open';
    overlay.id = 'confirmOverlay';
    overlay.innerHTML = `
        <div class="modal modal-sm">
            <div class="confirm-dialog-body">
                <div class="confirm-icon">!</div>
                <h4>${title}</h4>
                <p>${message}</p>
            </div>
            <div class="modal-footer" style="justify-content:center">
                <button class="btn btn-secondary" onclick="document.getElementById('confirmOverlay').remove();document.body.style.overflow=''">Cancel</button>
                <button class="btn btn-danger" id="confirmBtn">Confirm</button>
            </div>
        </div>
    `;
    document.body.appendChild(overlay);
    document.body.style.overflow = 'hidden';
    document.getElementById('confirmBtn').addEventListener('click', () => {
        document.getElementById('confirmOverlay').remove();
        document.body.style.overflow = '';
        onConfirm();
    });
}

// ── Table Sorting ──
function sortTable(tableId, colIndex, type = 'string') {
    const table = document.getElementById(tableId);
    if (!table) return;
    const tbody = table.querySelector('tbody');
    const rows = Array.from(tbody.querySelectorAll('tr'));
    const th = table.querySelectorAll('th')[colIndex];
    const asc = !th.classList.contains('sort-asc');

    // Reset all sort indicators
    table.querySelectorAll('th').forEach(h => h.classList.remove('sort-asc', 'sort-desc'));
    th.classList.add(asc ? 'sort-asc' : 'sort-desc');

    rows.sort((a, b) => {
        let aVal = a.cells[colIndex]?.textContent?.trim() || '';
        let bVal = b.cells[colIndex]?.textContent?.trim() || '';

        if (type === 'number') {
            aVal = parseFloat(aVal.replace(/[^0-9.-]/g, '')) || 0;
            bVal = parseFloat(bVal.replace(/[^0-9.-]/g, '')) || 0;
        } else if (type === 'date') {
            aVal = new Date(aVal).getTime() || 0;
            bVal = new Date(bVal).getTime() || 0;
        }

        if (aVal < bVal) return asc ? -1 : 1;
        if (aVal > bVal) return asc ? 1 : -1;
        return 0;
    });

    rows.forEach(row => tbody.appendChild(row));
}

// ── Table Filtering ──
function filterTable(inputId, tableId, colIndex = null) {
    const q = document.getElementById(inputId)?.value.toLowerCase() || '';
    const table = document.getElementById(tableId);
    if (!table) return;
    table.querySelectorAll('tbody tr').forEach(row => {
        const text = colIndex !== null ? (row.cells[colIndex]?.textContent || '') : row.textContent;
        row.style.display = text.toLowerCase().includes(q) ? '' : 'none';
    });
}

// ── Global Search ──
(function() {
    const searchInput = document.getElementById('globalSearchInput');
    const dropdown = document.getElementById('globalSearchDropdown');
    if (!searchInput || !dropdown) return;

    let debounceTimer;
    searchInput.addEventListener('input', function() {
        clearTimeout(debounceTimer);
        const q = this.value.trim();
        if (q.length < 2) {
            dropdown.classList.remove('open');
            return;
        }
        debounceTimer = setTimeout(() => doGlobalSearch(q), 300);
    });

    searchInput.addEventListener('focus', function() {
        if (this.value.trim().length >= 2) {
            dropdown.classList.add('open');
        }
    });

    document.addEventListener('click', function(e) {
        if (!searchInput.contains(e.target) && !dropdown.contains(e.target)) {
            dropdown.classList.remove('open');
        }
    });

    function doGlobalSearch(q) {
        fetch('api/search.php?q=' + encodeURIComponent(q))
            .then(r => r.json())
            .then(data => {
                let html = '';
                for (const [group, items] of Object.entries(data)) {
                    if (items.length === 0) continue;
                    html += '<div class="search-group">';
                    html += '<div class="search-group-label">' + group + '</div>';
                    items.forEach(item => {
                        html += '<a href="' + item.url + '" class="search-result-item">';
                        html += '<div class="sr-icon">' + item.icon + '</div>';
                        html += '<div class="sr-info"><div class="sr-name">' + item.name + '</div>';
                        html += '<div class="sr-sub">' + item.sub + '</div></div></a>';
                    });
                    html += '</div>';
                }
                if (!html) html = '<div class="search-group"><div class="search-result-item" style="color:var(--text-muted)">No results found</div></div>';
                dropdown.innerHTML = html;
                dropdown.classList.add('open');
            })
            .catch(() => {
                dropdown.innerHTML = '<div class="search-group"><div class="search-result-item" style="color:var(--text-muted)">Search unavailable</div></div>';
                dropdown.classList.add('open');
            });
    }
})();

// ── Print function ──
function printPage() {
    window.print();
}

// ── Export to CSV ──
function exportCSV(filename, tableId) {
    const table = document.getElementById(tableId);
    if (!table) return;
    let csv = [];
    table.querySelectorAll('tr').forEach(row => {
        let cols = [];
        row.querySelectorAll('td, th').forEach(cell => {
            let text = cell.textContent.replace(/"/g, '""').trim();
            cols.push('"' + text + '"');
        });
        csv.push(cols.join(','));
    });
    const blob = new Blob(['\uFEFF' + csv.join('\n')], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = filename + '_' + new Date().toISOString().slice(0,10) + '.csv';
    link.click();
}

// ── Animated Counter ──
function animateCounter(el, target, duration = 1500) {
    const start = 0;
    const increment = target / (duration / 16);
    let current = start;
    function update() {
        current += increment;
        if (current >= target) {
            el.textContent = target.toLocaleString();
            return;
        }
        el.textContent = Math.floor(current).toLocaleString();
        requestAnimationFrame(update);
    }
    update();
}

// Trigger counters on page load
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-counter]').forEach(el => {
        const target = parseInt(el.dataset.counter, 10);
        if (!isNaN(target)) {
            // Start when visible
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        animateCounter(el, target);
                        observer.unobserve(el);
                    }
                });
            }, { threshold: 0.5 });
            observer.observe(el);
        }
    });
});

// ── Loading button state ──
function setLoading(btn, loading = true) {
    if (loading) {
        btn.classList.add('loading');
        btn.disabled = true;
    } else {
        btn.classList.remove('loading');
        btn.disabled = false;
    }
}

// ── Premium Smooth Scroll Easing ──
// Removed custom scroll JS to rely on delay-free native smooth scrolling (scroll-behavior: smooth in CSS)

// ── Global Premium Entry Animations ──
document.addEventListener('DOMContentLoaded', () => {
    const mainContent = document.querySelector('.main-content');
    if (mainContent) {
        const children = Array.from(mainContent.children).filter(el => {
            const tagName = el.tagName.toLowerCase();
            return !el.classList.contains('topbar') && 
                   !el.classList.contains('modal-overlay') && 
                   !el.classList.contains('toast-container') && 
                   !el.classList.contains('sidebar-overlay') &&
                   tagName !== 'script' && 
                   tagName !== 'style' && 
                   el.style.display !== 'none';
        });

        children.forEach((el, index) => {
            el.style.opacity = '0';
            el.style.animation = 'fadeInUp 800ms cubic-bezier(0.16, 1, 0.3, 1) forwards';
            el.style.animationDelay = `${index * 80}ms`;
        });
    }
});
</script>
</body>
</html>
