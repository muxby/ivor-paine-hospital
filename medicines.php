<?php
/**
 * Medicine Search - Premium Pharmacy Interface
 * Task 33: Medster API-powered medicine search with debounced input
 * Features: Skeleton loading, empty states, error handling, glassmorphism cards
 */
require_once 'includes/db.php';
require_once 'includes/helpers.php';
require_once 'includes/medicine_api.php';

$pageTitle = 'Medicine Search';
$pageSubtitle = 'Search and browse medicines from the pharmaceutical database';

include 'components/header.php';
?>

<?php include 'components/sidebar.php'; ?>

<div class="main-content">
    <?php include 'components/topbar.php'; ?>



    <!-- Search Section -->
    <div class="card animate-fade-in stagger-1" style="margin-bottom: var(--space-8);">
        <div class="card-body" style="padding: var(--space-6) var(--space-5);">
            <!-- API Status Badge -->
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--space-4);">
                <div style="display: flex; align-items: center; gap: var(--space-2);">
                    <div id="apiStatusDot" class="badge-dot" style="background: var(--text-faint);"></div>
                    <span id="apiStatusText" class="text-xs text-muted">Checking API status...</span>
                </div>
                <span class="text-xs text-muted" id="cacheIndicator" style="display: none;">
                    <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="display: inline; vertical-align: middle;"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 6.375c0 2.278-3.714 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.714-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.714 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 0v3.75m-16.5-3.75v3.75m16.5 0v3.75C20.25 16.153 16.536 18 12 18s-8.25-1.847-8.25-4.125v-3.75m16.5 0c0 2.278-3.714 4.125-8.25 4.125s-8.25-1.847-8.25-4.125"/></svg>
                    Cached result
                </span>
            </div>

            <!-- Search Input -->
            <div class="medicine-search-wrap">
                <svg class="medicine-search-icon" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/></svg>
                <input type="text"
                       id="medicineSearchInput"
                       class="medicine-search-input"
                       placeholder="Search medicines, dosage, brand name... (e.g. panadol, amoxicillin)"
                       autocomplete="off"
                       value="<?php echo isset($_GET['q']) ? e($_GET['q']) : ''; ?>">
                <div class="medicine-search-loader" id="searchLoader">
                    <div class="search-spinner"></div>
                </div>
            </div>
            <div class="form-hint" style="margin-top: 8px; text-align: center;">Type at least 2 characters. Results appear automatically.</div>
        </div>
    </div>

    <!-- Empty State (before search) -->
    <div id="emptyState" class="animate-fade-in">
        <div class="card" style="padding: var(--space-12) var(--space-8);">
            <div class="empty-state" style="padding: 0;">
                <div class="empty-state-icon" style="width: 80px; height: 80px; font-size: 2.5rem;">&#128269;</div>
                <h4 style="font-size: 1.1rem; margin-bottom: var(--space-2);">Search for Medicines</h4>
                <p style="max-width: 420px;">Enter a medicine name, brand, or active ingredient to search the pharmaceutical database. Try "panadol", "brufen", or "amoxicillin".</p>
                <div style="display: flex; gap: var(--space-2); margin-top: var(--space-4); flex-wrap: wrap; justify-content: center;">
                    <button class="btn btn-sm btn-secondary" onclick="quickSearch('panadol')">Panadol</button>
                    <button class="btn btn-sm btn-secondary" onclick="quickSearch('brufen')">Brufen</button>
                    <button class="btn btn-sm btn-secondary" onclick="quickSearch('amoxicillin')">Amoxicillin</button>
                    <button class="btn btn-sm btn-secondary" onclick="quickSearch('aspirin')">Aspirin</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Loading Skeleton -->
    <div id="loadingSkeleton" style="display: none;">
        <div class="medicine-grid">
            <?php for ($i = 0; $i < 6; $i++): ?>
            <div class="medicine-card skeleton">
                <div class="medicine-card-header">
                    <div class="skeleton" style="width: 48px; height: 48px; border-radius: var(--radius);"></div>
                    <div style="flex: 1;">
                        <div class="skeleton skeleton-text" style="width: 70%;"></div>
                        <div class="skeleton skeleton-text short" style="width: 40%;"></div>
                    </div>
                </div>
                <div class="medicine-card-body">
                    <div class="skeleton skeleton-text" style="width: 50%;"></div>
                    <div class="skeleton skeleton-text short" style="width: 80%;"></div>
                </div>
            </div>
            <?php endfor; ?>
        </div>
    </div>

    <!-- Error State -->
    <div id="errorState" style="display: none;">
        <div class="card animate-fade-in" style="padding: var(--space-10) var(--space-8);">
            <div class="empty-state" style="padding: 0;">
                <div class="empty-state-icon" style="width: 80px; height: 80px; font-size: 2rem; background: var(--danger-light); color: var(--danger);">
                    <svg width="32" height="32" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
                </div>
                <h4 style="font-size: 1.1rem; margin-bottom: var(--space-2);">API Service Unavailable</h4>
                <p id="errorMessage" style="max-width: 420px;">Unable to reach the medicine database. This could be a temporary issue.</p>
                <button class="btn btn-primary" style="margin-top: var(--space-4);" onclick="retrySearch()">
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182"/></svg>
                    Retry
                </button>
            </div>
        </div>
    </div>

    <!-- No Results State -->
    <div id="noResultsState" style="display: none;">
        <div class="card animate-fade-in" style="padding: var(--space-10) var(--space-8);">
            <div class="empty-state" style="padding: 0;">
                <div class="empty-state-icon" style="width: 80px; height: 80px; font-size: 2rem;">&#128221;</div>
                <h4 style="font-size: 1.1rem; margin-bottom: var(--space-2);">No Medicines Found</h4>
                <p id="noResultsMessage" style="max-width: 420px;">Try a different search term or check your spelling.</p>
            </div>
        </div>
    </div>

    <!-- Results Section -->
    <div id="resultsSection" style="display: none;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--space-4);">
            <div>
                <h3 style="font-size: 1rem; font-weight: 700;">Search Results</h3>
                <span id="resultsCount" class="text-xs text-muted"></span>
            </div>
        </div>
        <div id="medicineResults" class="medicine-grid"></div>
    </div>
</div>

<!-- Medicine Details Modal (Task 34) -->
<div class="modal-overlay" id="medicineDetailModal">
    <div class="modal modal-lg">
        <div class="modal-header">
            <div style="display: flex; align-items: center; gap: var(--space-3);">
                <div class="modal-medicine-icon" id="modalMedicineIcon">
                    <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9.75 3.104v5.714a2.25 2.25 0 01-.659 1.591L5 14.5M9.75 3.104c-.251.023-.501.05-.75.082m.75-.082a24.301 24.301 0 014.5 0m0 0v5.714a2.25 2.25 0 00.659 1.591L19.8 14.5M14.25 3.104c.251.023.501.05.75.082M19.8 14.5l-1.971 2.971M14.25 9.75l1.971 2.971M5 14.5l1.971-2.971M5 14.5l6.75 6.75M19.8 14.5l-6.75 6.75M9.75 3.104V8.91"/></svg>
                </div>
                <div>
                    <h3 id="modalMedicineName" style="font-size: 1.1rem;">Medicine Details</h3>
                    <span class="id-tag" id="modalMedicineId"></span>
                </div>
            </div>
            <button class="modal-close" onclick="closeModal('medicineDetailModal')">&#10005;</button>
        </div>

        <!-- Modal Loading State -->
        <div id="modalLoadingState" class="modal-body">
            <div class="skeleton skeleton-text" style="width: 60%; height: 24px; margin-bottom: var(--space-4);"></div>
            <div class="skeleton" style="width: 100px; height: 32px; margin-bottom: var(--space-5);"></div>
            <div class="skeleton skeleton-text" style="width: 100%; margin-bottom: var(--space-3);"></div>
            <div class="skeleton skeleton-text" style="width: 80%;"></div>
        </div>

        <!-- Modal Content -->
        <div id="modalContent" class="modal-body" style="display: none;">
            <!-- Price & Discount Row -->
            <div style="display: flex; gap: var(--space-3); margin-bottom: var(--space-5); flex-wrap: wrap;">
                <div id="modalPriceBadge" class="price-badge-lg"></div>
                <div id="modalDiscountBadge" class="discount-badge"></div>
                <span id="modalCacheBadge" class="cache-badge" style="display: none;">Cached</span>
            </div>

            <!-- Medical Information Warning -->
            <div class="medical-warning">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
                <div>
                    <strong>Medical Information Notice</strong><br>
                    This information is for reference only. Always consult a qualified healthcare professional before taking any medication.
                </div>
            </div>

            <!-- Detail Sections (Accordion) -->
            <div id="modalDetailSections"></div>
        </div>

        <!-- Modal Error State -->
        <div id="modalErrorState" class="modal-body" style="display: none;">
            <div class="alert alert-error">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
                <span id="modalErrorMessage">Unable to load medicine details.</span>
            </div>
        </div>

        <div class="modal-footer">
            <button type="button" class="btn btn-ghost" onclick="closeModal('medicineDetailModal')">Close</button>
            <a id="modalAddToRxBtn" href="#" class="btn btn-primary">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                Add to Prescription
            </a>
        </div>
    </div>
</div>

<?php include 'components/footer.php'; ?>

<script>
// ── Medicine Search with Debounce ──
let searchDebounceTimer;
let lastQuery = '';

const searchInput = document.getElementById('medicineSearchInput');
const searchLoader = document.getElementById('searchLoader');
const emptyState = document.getElementById('emptyState');
const loadingSkeleton = document.getElementById('loadingSkeleton');
const errorState = document.getElementById('errorState');
const noResultsState = document.getElementById('noResultsState');
const resultsSection = document.getElementById('resultsSection');
const medicineResults = document.getElementById('medicineResults');
const cacheIndicator = document.getElementById('cacheIndicator');
const apiStatusDot = document.getElementById('apiStatusDot');
const apiStatusText = document.getElementById('apiStatusText');

// Check API health on page load
fetch('api/medicine_api_proxy.php?action=health')
    .then(r => r.json())
    .then(data => {
        if (data.online) {
            apiStatusDot.style.background = 'var(--success)';
            apiStatusText.textContent = 'API Online';
        } else {
            apiStatusDot.style.background = 'var(--danger)';
            apiStatusText.textContent = 'API Offline - Using cache';
        }
    })
    .catch(() => {
        apiStatusDot.style.background = 'var(--warning)';
        apiStatusText.textContent = 'API status unknown';
    });

searchInput.addEventListener('input', function() {
    clearTimeout(searchDebounceTimer);
    const query = this.value.trim();

    if (query.length < 2) {
        hideAllStates();
        emptyState.style.display = 'block';
        return;
    }

    searchDebounceTimer = setTimeout(() => performSearch(query), 400);
});

searchInput.addEventListener('keydown', function(e) {
    if (e.key === 'Enter') {
        clearTimeout(searchDebounceTimer);
        const query = this.value.trim();
        if (query.length >= 2) performSearch(query);
    }
});

function performSearch(query) {
    if (query === lastQuery && resultsSection.style.display === 'block') return;
    lastQuery = query;

    hideAllStates();
    loadingSkeleton.style.display = 'block';
    searchLoader.classList.add('visible');
    cacheIndicator.style.display = 'none';

    fetch('api/medicine_api_proxy.php?action=search&q=' + encodeURIComponent(query))
        .then(r => r.json())
        .then(data => {
            loadingSkeleton.style.display = 'none';
            searchLoader.classList.remove('visible');

            if (!data.success) {
                showError(data.error || 'Search failed. Please try again.');
                return;
            }

            if (data.empty || !data.results || data.results.length === 0) {
                showNoResults(query);
                return;
            }

            if (data.from_cache) {
                cacheIndicator.style.display = 'inline';
            }

            renderResults(data.results);
        })
        .catch(err => {
            loadingSkeleton.style.display = 'none';
            searchLoader.classList.remove('visible');
            showError('Network error. Please check your connection and try again.');
        });
}

function renderResults(results) {
    resultsSection.style.display = 'block';
    document.getElementById('resultsCount').textContent = results.length + ' medicine' + (results.length > 1 ? 's' : '') + ' found';

    let html = '';
    results.forEach((med, index) => {
        const delay = Math.min(index * 80, 500);
        const hasPrice = med.price && med.price !== '';
        html += `
            <div class="medicine-card animate-fade-in" style="animation-delay: ${delay}ms; opacity: 0;">
                <div class="medicine-card-header">
                    <div class="medicine-card-icon">
                        <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 3.104v5.714a2.25 2.25 0 01-.659 1.591L5 14.5M9.75 3.104c-.251.023-.501.05-.75.082m.75-.082a24.301 24.301 0 014.5 0m0 0v5.714a2.25 2.25 0 00.659 1.591L19.8 14.5M14.25 3.104c.251.023.501.05.75.082M19.8 14.5l-1.971 2.971M14.25 9.75l1.971 2.971M5 14.5l1.971-2.971M5 14.5l6.75 6.75M19.8 14.5l-6.75 6.75M9.75 3.104V8.91"/>
                        </svg>
                    </div>
                    <div class="medicine-card-info">
                        <div class="medicine-card-name">${escapeHtml(med.name)}</div>
                        <span class="id-tag">${escapeHtml(med.id)}</span>
                    </div>
                </div>
                <div class="medicine-card-body">
                    ${hasPrice ? `<div class="medicine-card-price">${escapeHtml(med.price)}</div>` : '<div class="medicine-card-price-unavailable">Price unavailable</div>'}
                </div>
                <div class="medicine-card-footer">
                    <button class="btn btn-sm btn-secondary" onclick="viewMedicineDetails('${escapeHtml(med.id)}')">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        Details
                    </button>
                    <a href="prescriptions.php?add_medicine=${encodeURIComponent(med.id)}&name=${encodeURIComponent(med.name)}&price=${encodeURIComponent(med.price || '')}" class="btn btn-sm btn-primary">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                        Prescribe
                    </a>
                </div>
            </div>
        `;
    });
    medicineResults.innerHTML = html;
}

function viewMedicineDetails(medicineId) {
    openModal('medicineDetailModal');

    // Reset states
    document.getElementById('modalLoadingState').style.display = 'block';
    document.getElementById('modalContent').style.display = 'none';
    document.getElementById('modalErrorState').style.display = 'none';
    document.getElementById('modalMedicineName').textContent = 'Loading...';
    document.getElementById('modalMedicineId').textContent = medicineId;
    document.getElementById('modalAddToRxBtn').style.display = 'none';

    fetch('api/medicine_api_proxy.php?action=details&id=' + encodeURIComponent(medicineId))
        .then(r => r.json())
        .then(data => {
            document.getElementById('modalLoadingState').style.display = 'none';

            if (!data.success || !data.medicine) {
                document.getElementById('modalErrorMessage').textContent = data.error || 'Failed to load details.';
                document.getElementById('modalErrorState').style.display = 'block';
                return;
            }

            const med = data.medicine;
            document.getElementById('modalMedicineName').textContent = med.name || 'Medicine Details';
            document.getElementById('modalMedicineId').textContent = med.id || medicineId;

            // Price badge
            const priceBadge = document.getElementById('modalPriceBadge');
            if (med.price) {
                priceBadge.textContent = med.price;
                priceBadge.style.display = 'inline-flex';
            } else {
                priceBadge.style.display = 'none';
            }

            // Discount badge
            const discountBadge = document.getElementById('modalDiscountBadge');
            if (med.discount) {
                discountBadge.textContent = med.discount;
                discountBadge.style.display = 'inline-flex';
            } else {
                discountBadge.style.display = 'none';
            }

            // Cache badge
            document.getElementById('modalCacheBadge').style.display = (data.from_cache || data.partial) ? 'inline-flex' : 'none';

            // Detail sections (accordion)
            const sectionsContainer = document.getElementById('modalDetailSections');
            if (med.details && Array.isArray(med.details) && med.details.length > 0) {
                let sectionsHtml = '';
                med.details.forEach((section, idx) => {
                    const sectionId = 'medSection_' + idx;
                    sectionsHtml += `
                        <div class="medicine-accordion">
                            <button class="medicine-accordion-header ${idx === 0 ? 'active' : ''}" onclick="toggleAccordion(this, '${sectionId}')">
                                <span>${escapeHtml(section.title || 'Details')}</span>
                                <svg class="accordion-icon ${idx === 0 ? 'open' : ''}" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/></svg>
                            </button>
                            <div id="${sectionId}" class="medicine-accordion-body" style="${idx === 0 ? '' : 'display: none;'}">
                                <div class="medicine-accordion-content">${escapeHtml(section.content || 'No information available.')}</div>
                            </div>
                        </div>
                    `;
                });
                sectionsContainer.innerHTML = sectionsHtml;
            } else {
                sectionsContainer.innerHTML = '<p style="color: var(--text-muted); padding: var(--space-4);">No detailed information available for this medicine.</p>';
            }

            // Update Add to Prescription link
            const rxBtn = document.getElementById('modalAddToRxBtn');
            rxBtn.href = 'prescriptions.php?add_medicine=' + encodeURIComponent(med.id || medicineId) +
                         '&name=' + encodeURIComponent(med.name || '') +
                         '&price=' + encodeURIComponent(med.price || '');
            rxBtn.style.display = 'inline-flex';

            document.getElementById('modalContent').style.display = 'block';
        })
        .catch(() => {
            document.getElementById('modalLoadingState').style.display = 'none';
            document.getElementById('modalErrorMessage').textContent = 'Network error. Please try again.';
            document.getElementById('modalErrorState').style.display = 'block';
        });
}

function toggleAccordion(btn, sectionId) {
    const body = document.getElementById(sectionId);
    const icon = btn.querySelector('.accordion-icon');
    const isOpen = body.style.display !== 'none';

    if (isOpen) {
        body.style.display = 'none';
        btn.classList.remove('active');
        icon.classList.remove('open');
    } else {
        body.style.display = 'block';
        btn.classList.add('active');
        icon.classList.add('open');
    }
}

function hideAllStates() {
    emptyState.style.display = 'none';
    loadingSkeleton.style.display = 'none';
    errorState.style.display = 'none';
    noResultsState.style.display = 'none';
    resultsSection.style.display = 'none';
}

function showError(msg) {
    document.getElementById('errorMessage').textContent = msg;
    errorState.style.display = 'block';
}

function showNoResults(query) {
    document.getElementById('noResultsMessage').textContent = 'No medicines found for "' + escapeHtml(query) + '".';
    noResultsState.style.display = 'block';
}

function quickSearch(term) {
    searchInput.value = term;
    performSearch(term);
}

function retrySearch() {
    const query = searchInput.value.trim();
    if (query.length >= 2) performSearch(query);
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Auto-search if query is in URL
<?php if (isset($_GET['q']) && !empty($_GET['q'])): ?>
document.addEventListener('DOMContentLoaded', () => {
    setTimeout(() => performSearch('<?php echo e($_GET['q']); ?>'), 300);
});
<?php endif; ?>
</script>
