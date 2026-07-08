/**
 * Schemes Page JavaScript
 */

let currentPage = 1;
let currentFilters = {};

document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('schemesPage')) {
        initSchemesPage();
    }
    if (document.getElementById('schemeDetailsPage')) {
        initSchemeDetails();
    }
});

function initSchemesPage() {
    populateStates('filterState');
    loadSchemes();

    // Live search
    const searchInput = document.getElementById('schemeSearch');
    let searchTimeout;
    searchInput?.addEventListener('input', () => {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            currentPage = 1;
            loadSchemes();
        }, 300);
    });

    // Category filter buttons
    document.querySelectorAll('[data-category]').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('[data-category]').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            currentPage = 1;
            loadSchemes();
        });
    });

    // Sort
    document.getElementById('sortSelect')?.addEventListener('change', () => {
        currentPage = 1;
        loadSchemes();
    });

    // Advanced filters
    document.getElementById('applyFilters')?.addEventListener('click', () => {
        currentPage = 1;
        loadSchemes();
    });

    document.getElementById('clearFilters')?.addEventListener('click', () => {
        document.getElementById('filterForm')?.reset();
        currentPage = 1;
        loadSchemes();
    });
}

async function loadSchemes() {
    const container = document.getElementById('schemesGrid');
    container.innerHTML = '<div class="col-12 text-center py-5"><div class="loader-spinner mx-auto"></div></div>';

    const params = new URLSearchParams({
        action: 'list',
        page: currentPage,
        limit: 9,
        search: document.getElementById('schemeSearch')?.value || '',
        sort: document.getElementById('sortSelect')?.value || 'latest'
    });

    const activeCategory = document.querySelector('[data-category].active');
    if (activeCategory) params.set('category', activeCategory.dataset.category);

    // Advanced filters
    const filterFields = ['filterGender', 'filterIncome', 'filterOccupation', 'filterEducation',
        'filterState', 'filterCategory', 'filterDisability', 'filterFarmer', 'filterStudent', 'filterMinAge', 'filterMaxAge'];
    filterFields.forEach(id => {
        const el = document.getElementById(id);
        if (el && el.value) {
            const key = id.replace('filter', '').toLowerCase();
            const map = { gender: 'gender', income: 'income', occupation: 'occupation', education: 'education',
                state: 'state', category: 'category_filter', disability: 'disability', farmer: 'farmer',
                student: 'student', minage: 'min_age', maxage: 'max_age' };
            params.set(map[key] || key, el.value);
        }
    });

    const data = await apiCall('schemes.php?' + params.toString());

    if (!data.success || !data.schemes.length) {
        container.innerHTML = '<div class="col-12"><div class="empty-state"><i class="bi bi-search"></i><h5>No schemes found</h5><p>Try adjusting your search or filters</p></div></div>';
        document.getElementById('paginationContainer').innerHTML = '';
        return;
    }

    container.innerHTML = data.schemes.map(s => renderSchemeCard(s)).join('');

    const pagContainer = document.getElementById('paginationContainer');
    pagContainer.innerHTML = renderPagination(data.pagination);
    attachPagination(pagContainer, page => { currentPage = page; loadSchemes(); window.scrollTo({ top: 0, behavior: 'smooth' }); });
}

async function initSchemeDetails() {
    const params = new URLSearchParams(window.location.search);
    const id = params.get('id');
    if (!id) { window.location.href = 'schemes.html'; return; }

    const data = await apiCall(`schemes.php?action=details&id=${id}`);
    if (!data.success) {
        document.getElementById('schemeDetailsContent').innerHTML = '<div class="alert alert-danger">Scheme not found</div>';
        return;
    }

    renderSchemeDetails(data.scheme);
}

function renderSchemeDetails(scheme) {
    const container = document.getElementById('schemeDetailsContent');
    document.title = scheme.scheme_name + ' - Govt Scheme Finder';

    setBreadcrumb([
        { label: 'Home', url: 'index.html' },
        { label: 'Schemes', url: 'schemes.html' },
        { label: scheme.scheme_name, url: '#' }
    ]);

    const docs = scheme.required_documents ? scheme.required_documents.split(',').map(d => d.trim()) : [];
    const steps = scheme.application_steps ? scheme.application_steps.split(/\d+\./).filter(s => s.trim()) : [];

    container.innerHTML = `
        <div class="govt-card mb-4">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
                <div>
                    <span class="scheme-category-badge">${scheme.category}</span>
                    <h2 class="mt-2 mb-1">${scheme.scheme_name}</h2>
                    <small class="text-muted">Code: ${scheme.scheme_code} | Updated: ${formatDate(scheme.last_updated)}</small>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-govt-primary" onclick="applyForScheme(${scheme.id})"><i class="bi bi-send"></i> Apply Now</button>
                    <a href="downloads/${scheme.scheme_code}.pdf" class="btn btn-outline-secondary" onclick="downloadPDF(event, '${scheme.scheme_code}')"><i class="bi bi-download"></i> Download PDF</a>
                    <a href="schemes.html" class="btn btn-outline-primary"><i class="bi bi-arrow-left"></i> Back</a>
                </div>
            </div>
            <p class="lead">${scheme.description}</p>
        </div>

        <div class="row g-4">
            <div class="col-lg-8">
                <div class="govt-card mb-4">
                    <h4><i class="bi bi-gift text-success"></i> Benefits</h4>
                    <p>${scheme.benefits}</p>
                </div>
                <div class="govt-card mb-4">
                    <h4><i class="bi bi-check2-square text-primary"></i> Eligibility Criteria</h4>
                    <div class="row g-2 mt-2">
                        <div class="col-md-6"><strong>Age:</strong> ${scheme.min_age} - ${scheme.max_age} years</div>
                        <div class="col-md-6"><strong>Income Limit:</strong> ${scheme.income_limit ? formatCurrency(scheme.income_limit) : 'No limit'}</div>
                        <div class="col-md-6"><strong>Gender:</strong> ${scheme.gender_eligibility}</div>
                        <div class="col-md-6"><strong>Category:</strong> ${scheme.caste_category}</div>
                        <div class="col-md-6"><strong>Farmer:</strong> ${scheme.farmer_eligibility}</div>
                        <div class="col-md-6"><strong>Student:</strong> ${scheme.student_eligibility}</div>
                        <div class="col-md-6"><strong>Disability:</strong> ${scheme.disability_eligibility}</div>
                        <div class="col-md-6"><strong>State/UT:</strong> ${scheme.state_ut}</div>
                        <div class="col-md-6"><strong>Education:</strong> ${scheme.education_requirement}</div>
                        <div class="col-md-6"><strong>Occupation:</strong> ${scheme.occupation}</div>
                    </div>
                </div>
                <div class="govt-card mb-4">
                    <h4><i class="bi bi-file-earmark-text text-warning"></i> Required Documents</h4>
                    <ul class="list-group list-group-flush mt-2">
                        ${docs.map(d => `<li class="list-group-item"><i class="bi bi-file-check text-success"></i> ${d}</li>`).join('')}
                    </ul>
                </div>
                <div class="govt-card">
                    <h4><i class="bi bi-list-ol text-info"></i> Application Steps</h4>
                    <ol class="mt-2">
                        ${steps.map(s => `<li class="mb-2">${s.trim()}</li>`).join('')}
                    </ol>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="govt-card mb-4">
                    <h5>Quick Links</h5>
                    <div class="d-grid gap-2 mt-3">
                        <a href="${scheme.official_website}" target="_blank" class="btn btn-outline-primary"><i class="bi bi-globe"></i> Official Website</a>
                        <a href="${scheme.official_application_link}" target="_blank" class="btn btn-govt-primary"><i class="bi bi-box-arrow-up-right"></i> Apply Online</a>
                    </div>
                </div>
                <div class="govt-card">
                    <h5><i class="bi bi-telephone"></i> Helpline</h5>
                    <p class="fs-4 fw-bold text-primary mt-2">${scheme.helpline_number || 'N/A'}</p>
                </div>
            </div>
        </div>`;
}

async function applyForScheme(schemeId) {
    const session = await apiCall('auth.php?action=check_session');
    if (!session.logged_in) {
        showToast('Please login to apply', 'warning');
        setTimeout(() => window.location.href = 'login.html', 1500);
        return;
    }

    const data = await apiCall('schemes.php?action=apply', {
        method: 'POST',
        body: JSON.stringify({ scheme_id: schemeId })
    });

    if (data.success) {
        showToast(data.message, 'success');
        if (data.official_link) {
            setTimeout(() => window.open(data.official_link, '_blank'), 2000);
        }
    }
}

function downloadPDF(e, code) {
    e.preventDefault();
    showToast('PDF download will be available from the official portal. Redirecting...', 'info');
    setTimeout(() => {
        const link = document.querySelector('[href*="official"]') || document.querySelector('a[target="_blank"]');
        if (link) window.open(link.href, '_blank');
    }, 1500);
}
