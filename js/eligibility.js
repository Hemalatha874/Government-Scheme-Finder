/**
 * Eligibility Checker JavaScript
 */

document.addEventListener('DOMContentLoaded', async () => {
    if (document.getElementById('eligibilityPage')) {
        await initEligibilityPage();
    }
});

async function initEligibilityPage() {
    const session = await requireAuth();
    if (!session) return;

    await loadProfileSummary();
    document.getElementById('checkEligibilityBtn')?.addEventListener('click', runEligibilityCheck);
}

async function loadProfileSummary() {
    const data = await apiCall('profile.php?action=get');
    const container = document.getElementById('profileSummary');

    if (!data.success || !data.profile) {
        container.innerHTML = '<div class="alert alert-warning">Please complete your profile first. <a href="profile.html">Go to Profile</a></div>';
        return;
    }

    const p = data.profile;
    if (!p.age) {
        container.innerHTML = '<div class="alert alert-warning"><i class="bi bi-exclamation-triangle"></i> Your profile is incomplete. <a href="profile.html" class="alert-link">Complete Profile</a> to check eligibility.</div>';
        document.getElementById('checkEligibilityBtn').disabled = true;
        return;
    }

    container.innerHTML = `
        <div class="row g-3">
            <div class="col-md-3 col-6"><strong>Name:</strong> ${p.full_name || 'N/A'}</div>
            <div class="col-md-3 col-6"><strong>Age:</strong> ${p.age || 'N/A'}</div>
            <div class="col-md-3 col-6"><strong>Gender:</strong> ${p.gender || 'N/A'}</div>
            <div class="col-md-3 col-6"><strong>State:</strong> ${p.state || 'N/A'}</div>
            <div class="col-md-3 col-6"><strong>Category:</strong> ${p.category || 'N/A'}</div>
            <div class="col-md-3 col-6"><strong>Income:</strong> ${formatCurrency(p.annual_income)}</div>
            <div class="col-md-3 col-6"><strong>Farmer:</strong> ${p.is_farmer || 'No'}</div>
            <div class="col-md-3 col-6"><strong>Student:</strong> ${p.is_student || 'No'}</div>
        </div>
        <div class="mt-2"><a href="profile.html" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i> Edit Profile</a></div>`;
}

async function runEligibilityCheck() {
    const btn = document.getElementById('checkEligibilityBtn');
    const resultsContainer = document.getElementById('eligibilityResults');

    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Checking...';
    resultsContainer.innerHTML = '<div class="text-center py-5"><div class="loader-spinner mx-auto"></div><p class="mt-3">Analyzing your profile against government schemes...</p></div>';

    const data = await apiCall('eligibility.php?action=check');

    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-search"></i> Find Eligible Schemes';

    if (!data.success) {
        resultsContainer.innerHTML = `<div class="alert alert-danger">${data.message}</div>`;
        if (data.redirect) setTimeout(() => window.location.href = data.redirect, 2000);
        return;
    }

    renderEligibilityResults(data);
}

function renderEligibilityResults(data) {
    const container = document.getElementById('eligibilityResults');
    const { results, summary } = data;

    let html = `
        <div class="row g-3 mb-4">
            <div class="col-md-4"><div class="govt-card stat-card"><div class="stat-number text-success">${summary.eligible}</div><div class="stat-label">Eligible Schemes</div></div></div>
            <div class="col-md-4"><div class="govt-card stat-card"><div class="stat-number" style="-webkit-text-fill-color:#f59e0b">${summary.partially_eligible}</div><div class="stat-label">Partially Eligible</div></div></div>
            <div class="col-md-4"><div class="govt-card stat-card"><div class="stat-number" style="-webkit-text-fill-color:#ef4444">${summary.not_eligible}</div><div class="stat-label">Not Eligible</div></div></div>
        </div>`;

    // Eligible
    if (results.eligible.length > 0) {
        html += '<h4 class="mb-3"><i class="bi bi-check-circle-fill text-success"></i> Eligible Schemes</h4>';
        html += '<div class="row g-3 mb-4">';
        results.eligible.forEach(s => { html += renderEligibilityCard(s); });
        html += '</div>';
    }

    // Partially eligible
    if (results.partially_eligible.length > 0) {
        html += '<h4 class="mb-3"><i class="bi bi-exclamation-circle-fill text-warning"></i> Partially Eligible</h4>';
        html += '<div class="row g-3 mb-4">';
        results.partially_eligible.forEach(s => { html += renderEligibilityCard(s); });
        html += '</div>';
    }

    // Not eligible
    if (results.not_eligible.length > 0) {
        html += '<h4 class="mb-3"><i class="bi bi-x-circle-fill text-danger"></i> Not Eligible</h4>';
        html += '<div class="row g-3 mb-4">';
        results.not_eligible.forEach(s => { html += renderEligibilityCard(s); });
        html += '</div>';
    }

    container.innerHTML = html;
}

function renderEligibilityCard(scheme) {
    const badgeClass = scheme.eligibility_status === 'eligible' ? 'badge-eligible'
        : scheme.eligibility_status === 'partial' ? 'badge-partial' : 'badge-not-eligible';
    const badgeText = scheme.eligibility_status === 'eligible' ? 'Eligible'
        : scheme.eligibility_status === 'partial' ? 'Partially Eligible' : 'Not Eligible';

    let reasonsHtml = '';
    if (scheme.reasons && scheme.reasons.length > 0) {
        reasonsHtml = '<ul class="small text-muted mt-2 mb-0">' +
            scheme.reasons.map(r => `<li>${r}</li>`).join('') + '</ul>';
    }

    return `
    <div class="col-md-6 col-lg-4">
        <div class="scheme-card">
            <div class="scheme-card-header">
                <span class="scheme-category-badge">${scheme.category}</span>
                <span class="${badgeClass}">${badgeText} (${scheme.match_score}%)</span>
            </div>
            <div class="scheme-card-body">
                <h5>${scheme.scheme_name}</h5>
                <p>${truncate(scheme.description, 120)}</p>
                <p class="small"><strong>Benefits:</strong> ${truncate(scheme.benefits, 100)}</p>
                ${reasonsHtml}
            </div>
            <div class="scheme-card-footer">
                <a href="scheme-details.html?id=${scheme.id}" class="btn btn-sm btn-govt-primary w-100">View Details</a>
            </div>
        </div>
    </div>`;
}
