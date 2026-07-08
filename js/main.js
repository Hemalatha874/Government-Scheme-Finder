/**
 * Main JavaScript - Government Scheme Eligibility Finder
 */

const API_BASE = 'php/';

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', () => {
    initLoader();
    initScrollTop();
    initDarkMode();
    initActiveNav();
    initToasts();
    checkSession();
});

/** Page loader */
function initLoader() {
    const loader = document.getElementById('page-loader');
    if (loader) {
        window.addEventListener('load', () => {
            setTimeout(() => loader.classList.add('hidden'), 300);
        });
    }
}

/** Scroll to top button */
function initScrollTop() {
    const btn = document.getElementById('scrollTopBtn');
    if (!btn) return;
    window.addEventListener('scroll', () => {
        btn.classList.toggle('visible', window.scrollY > 300);
    });
    btn.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));
}

/** Dark mode toggle */
function initDarkMode() {
    const toggle = document.getElementById('themeToggle');
    const saved = localStorage.getItem('theme') || 'light';
    document.documentElement.setAttribute('data-theme', saved);
    updateThemeIcon(saved);

    if (toggle) {
        toggle.addEventListener('click', () => {
            const current = document.documentElement.getAttribute('data-theme');
            const next = current === 'dark' ? 'light' : 'dark';
            document.documentElement.setAttribute('data-theme', next);
            localStorage.setItem('theme', next);
            updateThemeIcon(next);
        });
    }
}

function updateThemeIcon(theme) {
    const toggle = document.getElementById('themeToggle');
    if (toggle) {
        toggle.innerHTML = theme === 'dark'
            ? '<i class="bi bi-sun-fill"></i>'
            : '<i class="bi bi-moon-fill"></i>';
    }
}

/** Highlight active nav link */
function initActiveNav() {
    const currentPage = window.location.pathname.split('/').pop() || 'index.html';
    document.querySelectorAll('.navbar-govt .nav-link').forEach(link => {
        const href = link.getAttribute('href');
        if (href === currentPage || (currentPage === '' && href === 'index.html')) {
            link.classList.add('active');
        }
    });
}

/** Toast notifications */
function initToasts() {
    if (!document.querySelector('.toast-container-govt')) {
        const container = document.createElement('div');
        container.className = 'toast-container-govt';
        document.body.appendChild(container);
    }
}

function showToast(message, type = 'info', duration = 4000) {
    initToasts();
    const container = document.querySelector('.toast-container-govt');
    const toast = document.createElement('div');
    toast.className = `toast-govt ${type}`;
    const icons = { success: 'check-circle-fill', error: 'x-circle-fill', warning: 'exclamation-triangle-fill', info: 'info-circle-fill' };
    toast.innerHTML = `<i class="bi bi-${icons[type] || icons.info}"></i><span>${message}</span>`;
    container.appendChild(toast);
    setTimeout(() => { toast.style.opacity = '0'; setTimeout(() => toast.remove(), 300); }, duration);
}

/** API helper */
async function apiCall(endpoint, options = {}) {
    const url = API_BASE + endpoint;
    const defaults = {
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin'
    };
    try {
        const response = await fetch(url, { ...defaults, ...options });
        const data = await response.json();
        if (!response.ok && data.message) {
            showToast(data.message, 'error');
        }
        return data;
    } catch (err) {
        showToast('Network error. Please try again.', 'error');
        return { success: false, message: err.message };
    }
}

/** Check user session */
async function checkSession() {
    const data = await apiCall('auth.php?action=check_session');
    const userMenu = document.getElementById('userMenu');
    const loginBtn = document.getElementById('loginNavBtn');

    if (data.logged_in && data.user) {
        if (userMenu) {
            userMenu.style.display = 'block';
            const nameEl = document.getElementById('userDisplayName');
            if (nameEl) nameEl.textContent = data.user.name;
        }
        if (loginBtn) loginBtn.style.display = 'none';
    }
}

/** Form validation helper */
function validateForm(form) {
    let valid = true;
    form.querySelectorAll('[required]').forEach(field => {
        field.classList.remove('is-invalid');
        if (!field.value.trim()) {
            field.classList.add('is-invalid');
            valid = false;
        }
    });
    return valid;
}

/** Logout handler */
async function logout() {
    const data = await apiCall('auth.php?action=logout');
    if (data.success) {
        showToast('Logged out successfully', 'success');
        setTimeout(() => window.location.href = 'index.html', 1000);
    }
}

/** Format date */
function formatDate(dateStr) {
    if (!dateStr) return 'N/A';
    const d = new Date(dateStr);
    return d.toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' });
}

/** Format currency */
function formatCurrency(amount) {
    if (!amount) return 'No limit';
    return 'Rs. ' + Number(amount).toLocaleString('en-IN');
}

/** Truncate text */
function truncate(text, length = 120) {
    if (!text || text.length <= length) return text;
    return text.substring(0, length) + '...';
}

/** Render scheme card HTML */
function renderSchemeCard(scheme) {
    return `
    <div class="col-md-6 col-lg-4 mb-4">
        <div class="scheme-card">
            <div class="scheme-card-header">
                <span class="scheme-category-badge">${scheme.category}</span>
            </div>
            <div class="scheme-card-body">
                <h5>${scheme.scheme_name}</h5>
                <p>${truncate(scheme.description, 150)}</p>
            </div>
            <div class="scheme-card-footer">
                <small class="text-muted"><i class="bi bi-calendar3"></i> ${formatDate(scheme.last_updated)}</small>
                <a href="scheme-details.html?id=${scheme.id}" class="btn btn-sm btn-govt-primary">View Details</a>
            </div>
        </div>
    </div>`;
}

/** Render pagination */
function renderPagination(pagination, onPageChange) {
    const { page, total_pages } = pagination;
    if (total_pages <= 1) return '';

    let html = '<nav><ul class="pagination pagination-govt justify-content-center">';
    html += `<li class="page-item ${page <= 1 ? 'disabled' : ''}"><a class="page-link" href="#" data-page="${page - 1}">Previous</a></li>`;

    for (let i = 1; i <= total_pages; i++) {
        if (i === 1 || i === total_pages || (i >= page - 2 && i <= page + 2)) {
            html += `<li class="page-item ${i === page ? 'active' : ''}"><a class="page-link" href="#" data-page="${i}">${i}</a></li>`;
        } else if (i === page - 3 || i === page + 3) {
            html += '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
    }

    html += `<li class="page-item ${page >= total_pages ? 'disabled' : ''}"><a class="page-link" href="#" data-page="${page + 1}">Next</a></li>`;
    html += '</ul></nav>';
    return html;
}

/** Attach pagination events */
function attachPagination(container, callback) {
    container.querySelectorAll('.page-link[data-page]').forEach(link => {
        link.addEventListener('click', e => {
            e.preventDefault();
            const page = parseInt(link.dataset.page);
            if (page > 0) callback(page);
        });
    });
}

/** Indian states list */
const INDIAN_STATES = [
    'Andhra Pradesh', 'Arunachal Pradesh', 'Assam', 'Bihar', 'Chhattisgarh',
    'Goa', 'Gujarat', 'Haryana', 'Himachal Pradesh', 'Jharkhand', 'Karnataka',
    'Kerala', 'Madhya Pradesh', 'Maharashtra', 'Manipur', 'Meghalaya', 'Mizoram',
    'Nagaland', 'Odisha', 'Punjab', 'Rajasthan', 'Sikkim', 'Tamil Nadu',
    'Telangana', 'Tripura', 'Uttar Pradesh', 'Uttarakhand', 'West Bengal',
    'Andaman and Nicobar Islands', 'Chandigarh', 'Dadra and Nagar Haveli and Daman and Diu',
    'Delhi', 'Jammu and Kashmir', 'Ladakh', 'Lakshadweep', 'Puducherry'
];

/** Populate state dropdown */
function populateStates(selectId) {
    const select = document.getElementById(selectId);
    if (!select) return;
    INDIAN_STATES.forEach(state => {
        const opt = document.createElement('option');
        opt.value = state;
        opt.textContent = state;
        select.appendChild(opt);
    });
}

/** Generate breadcrumb */
function setBreadcrumb(items) {
    const bc = document.getElementById('breadcrumb');
    if (!bc) return;
    let html = '';
    items.forEach((item, i) => {
        if (i === items.length - 1) {
            html += `<li class="breadcrumb-item active">${item.label}</li>`;
        } else {
            html += `<li class="breadcrumb-item"><a href="${item.url}">${item.label}</a></li>`;
        }
    });
    bc.innerHTML = html;
}

/** Protect page - require login */
async function requireAuth(redirectUrl = 'login.html') {
    const data = await apiCall('auth.php?action=check_session');
    if (!data.logged_in) {
        window.location.href = redirectUrl;
        return false;
    }
    return data;
}

/** Animate counter */
function animateCounter(element, target, duration = 2000) {
    let start = 0;
    const increment = target / (duration / 16);
    const timer = setInterval(() => {
        start += increment;
        if (start >= target) {
            element.textContent = target.toLocaleString('en-IN');
            clearInterval(timer);
        } else {
            element.textContent = Math.floor(start).toLocaleString('en-IN');
        }
    }, 16);
}
