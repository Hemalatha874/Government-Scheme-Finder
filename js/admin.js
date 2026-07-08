/**
 * Admin Panel JavaScript
 */

let adminLoggedIn = false;

document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('adminPage')) {
        initAdminPage();
    }
});

async function initAdminPage() {
    const session = await apiCall('auth.php?action=check_session');
    if (session.logged_in && session.is_admin) {
        adminLoggedIn = true;
        showAdminPanel();
    } else {
        document.getElementById('adminLoginSection').style.display = 'block';
        document.getElementById('adminPanelSection').style.display = 'none';
    }

    // Admin nav tabs
    document.querySelectorAll('[data-admin-tab]').forEach(tab => {
        tab.addEventListener('click', (e) => {
            e.preventDefault();
            document.querySelectorAll('[data-admin-tab]').forEach(t => t.classList.remove('active'));
            tab.classList.add('active');
            loadAdminTab(tab.dataset.adminTab);
        });
    });

    // Add scheme form
    document.getElementById('addSchemeForm')?.addEventListener('submit', handleAddScheme);
}

function showAdminPanel() {
    document.getElementById('adminLoginSection').style.display = 'none';
    document.getElementById('adminPanelSection').style.display = 'block';
    loadAdminTab('dashboard');
}

async function loadAdminTab(tab) {
    const container = document.getElementById('adminContent');
    container.innerHTML = '<div class="text-center py-5"><div class="loader-spinner mx-auto"></div></div>';

    switch (tab) {
        case 'dashboard': await loadDashboard(container); break;
        case 'schemes': await loadSchemesAdmin(container); break;
        case 'add_scheme': renderAddSchemeForm(container); break;
        case 'users': await loadUsers(container); break;
        case 'notifications': await loadNotificationsAdmin(container); break;
        case 'documents': await loadDocumentsAdmin(container); break;
        case 'applications': await loadApplications(container); break;
        case 'messages': await loadMessages(container); break;
    }
}

async function loadDashboard(container) {
    const data = await apiCall('admin.php?action=dashboard');
    if (!data.success) return;

    const s = data.stats;
    container.innerHTML = `
        <h3 class="mb-4">Admin Dashboard</h3>
        <div class="row g-3 mb-4">
            <div class="col-md-3"><div class="admin-stat-card"><h3>${s.total_users}</h3><p class="text-muted mb-0">Total Users</p></div></div>
            <div class="col-md-3"><div class="admin-stat-card" style="border-color:var(--primary-green)"><h3>${s.total_schemes}</h3><p class="text-muted mb-0">Total Schemes</p></div></div>
            <div class="col-md-3"><div class="admin-stat-card" style="border-color:var(--accent-orange)"><h3>${s.total_applications}</h3><p class="text-muted mb-0">Applications</p></div></div>
            <div class="col-md-3"><div class="admin-stat-card" style="border-color:#ef4444"><h3>${s.total_documents}</h3><p class="text-muted mb-0">Documents</p></div></div>
        </div>
        <div class="govt-card">
            <h5>Recent Activity</h5>
            <div class="table-responsive mt-3">
                <table class="table table-hover">
                    <thead><tr><th>Type</th><th>User</th><th>Detail</th><th>Date</th></tr></thead>
                    <tbody>${(data.recent_activity || []).map(a => `
                        <tr>
                            <td><span class="badge bg-primary">${a.type}</span></td>
                            <td>${a.user_name}</td>
                            <td>${a.detail}</td>
                            <td>${formatDate(a.activity_date)}</td>
                        </tr>`).join('')}
                    </tbody>
                </table>
            </div>
        </div>`;
}

async function loadSchemesAdmin(container) {
    const data = await apiCall('schemes.php?action=list&limit=50');
    container.innerHTML = `
        <h3 class="mb-4">Manage Schemes</h3>
        <div class="table-responsive">
            <table class="table table-hover govt-card">
                <thead><tr><th>Code</th><th>Name</th><th>Category</th><th>Updated</th><th>Actions</th></tr></thead>
                <tbody>${(data.schemes || []).map(s => `
                    <tr>
                        <td>${s.scheme_code}</td>
                        <td>${s.scheme_name}</td>
                        <td>${s.category}</td>
                        <td>${formatDate(s.last_updated)}</td>
                        <td>
                            <button class="btn btn-sm btn-danger" onclick="deleteScheme(${s.id})"><i class="bi bi-trash"></i></button>
                        </td>
                    </tr>`).join('')}
                </tbody>
            </table>
        </div>`;
}

function renderAddSchemeForm(container) {
    container.innerHTML = `
        <h3 class="mb-4">Add New Scheme</h3>
        <form id="addSchemeForm" class="form-govt govt-card">
            <div class="row g-3">
                <div class="col-md-4"><label class="form-label">Scheme Code *</label><input type="text" name="scheme_code" class="form-control" required></div>
                <div class="col-md-8"><label class="form-label">Scheme Name *</label><input type="text" name="scheme_name" class="form-control" required></div>
                <div class="col-md-4"><label class="form-label">Category *</label>
                    <select name="category" class="form-select" required>
                        <option value="Farmer">Farmer</option><option value="Health">Health</option>
                        <option value="Housing">Housing</option><option value="Business">Business</option>
                        <option value="Education">Education</option><option value="Employment">Employment</option>
                        <option value="Women">Women</option><option value="Senior Citizen">Senior Citizen</option>
                    </select>
                </div>
                <div class="col-md-4"><label class="form-label">Min Age</label><input type="number" name="min_age" class="form-control" value="0"></div>
                <div class="col-md-4"><label class="form-label">Max Age</label><input type="number" name="max_age" class="form-control" value="120"></div>
                <div class="col-12"><label class="form-label">Description *</label><textarea name="description" class="form-control" rows="3" required></textarea></div>
                <div class="col-12"><label class="form-label">Benefits *</label><textarea name="benefits" class="form-control" rows="3" required></textarea></div>
                <div class="col-md-6"><label class="form-label">Official Website</label><input type="url" name="official_website" class="form-control"></div>
                <div class="col-md-6"><label class="form-label">Application Link</label><input type="url" name="official_application_link" class="form-control"></div>
                <div class="col-md-6"><label class="form-label">Helpline</label><input type="text" name="helpline_number" class="form-control"></div>
                <div class="col-md-6"><label class="form-label">Income Limit</label><input type="number" name="income_limit" class="form-control"></div>
                <div class="col-12"><label class="form-label">Required Documents</label><textarea name="required_documents" class="form-control" rows="2"></textarea></div>
                <div class="col-12"><label class="form-label">Application Steps</label><textarea name="application_steps" class="form-control" rows="3"></textarea></div>
                <div class="col-12"><button type="submit" class="btn btn-govt-primary">Add Scheme</button></div>
            </div>
        </form>`;

    document.getElementById('addSchemeForm').addEventListener('submit', handleAddScheme);
}

async function handleAddScheme(e) {
    e.preventDefault();
    const formData = Object.fromEntries(new FormData(e.target));
    const data = await apiCall('admin.php?action=add_scheme', {
        method: 'POST',
        body: JSON.stringify(formData)
    });
    if (data.success) {
        showToast(data.message, 'success');
        e.target.reset();
    }
}

async function deleteScheme(id) {
    if (!confirm('Are you sure you want to deactivate this scheme?')) return;
    const data = await apiCall('admin.php?action=delete_scheme', {
        method: 'POST',
        body: JSON.stringify({ id })
    });
    if (data.success) {
        showToast(data.message, 'success');
        loadAdminTab('schemes');
    }
}

async function loadUsers(container) {
    const data = await apiCall('admin.php?action=users');
    container.innerHTML = `
        <h3 class="mb-4">Manage Users</h3>
        <div class="table-responsive">
            <table class="table table-hover govt-card">
                <thead><tr><th>Name</th><th>Email</th><th>Mobile</th><th>State</th><th>Joined</th><th>Actions</th></tr></thead>
                <tbody>${(data.users || []).map(u => `
                    <tr>
                        <td>${u.full_name}</td><td>${u.email}</td><td>${u.mobile}</td>
                        <td>${u.state || 'N/A'}</td><td>${formatDate(u.created_at)}</td>
                        <td><button class="btn btn-sm btn-danger" onclick="deleteUser(${u.id})"><i class="bi bi-trash"></i></button></td>
                    </tr>`).join('')}
                </tbody>
            </table>
        </div>`;
}

async function deleteUser(id) {
    if (!confirm('Delete this user and all their data?')) return;
    const data = await apiCall('admin.php?action=delete_user', { method: 'POST', body: JSON.stringify({ user_id: id }) });
    if (data.success) { showToast(data.message, 'success'); loadAdminTab('users'); }
}

async function loadNotificationsAdmin(container) {
    const data = await apiCall('notifications.php?action=list');
    container.innerHTML = `
        <h3 class="mb-4">Manage Notifications</h3>
        <form id="addNotifForm" class="form-govt govt-card mb-4">
            <div class="row g-3">
                <div class="col-md-6"><input type="text" name="title" class="form-control" placeholder="Title" required></div>
                <div class="col-md-3"><select name="notification_type" class="form-select"><option value="announcement">Announcement</option><option value="scheme">Scheme</option><option value="deadline">Deadline</option></select></div>
                <div class="col-md-3"><input type="date" name="deadline_date" class="form-control"></div>
                <div class="col-md-9"><textarea name="message" class="form-control" placeholder="Message" rows="2" required></textarea></div>
                <div class="col-md-3"><button type="submit" class="btn btn-govt-primary w-100">Add Notification</button></div>
            </div>
        </form>
        ${(data.notifications || []).map(n => `
            <div class="notif-item notif-type-${n.notification_type}">
                <div class="d-flex justify-content-between">
                    <div><h6>${n.title}</h6><p class="mb-0 small">${n.message}</p><small class="text-muted">${formatDate(n.created_at)}</small></div>
                    <button class="btn btn-sm btn-outline-danger" onclick="deleteNotification(${n.id})"><i class="bi bi-trash"></i></button>
                </div>
            </div>`).join('')}`;

    document.getElementById('addNotifForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = Object.fromEntries(new FormData(e.target));
        const result = await apiCall('notifications.php?action=add', { method: 'POST', body: JSON.stringify(formData) });
        if (result.success) { showToast(result.message, 'success'); loadAdminTab('notifications'); }
    });
}

async function deleteNotification(id) {
    const data = await apiCall('notifications.php?action=delete', { method: 'POST', body: JSON.stringify({ id }) });
    if (data.success) { showToast(data.message, 'success'); loadAdminTab('notifications'); }
}

async function loadDocumentsAdmin(container) {
    const data = await apiCall('admin.php?action=documents');
    container.innerHTML = `
        <h3 class="mb-4">Uploaded Documents</h3>
        <div class="table-responsive">
            <table class="table table-hover govt-card">
                <thead><tr><th>User</th><th>Type</th><th>File</th><th>Size</th><th>Uploaded</th></tr></thead>
                <tbody>${(data.documents || []).map(d => `
                    <tr>
                        <td>${d.full_name}</td><td>${d.document_type}</td>
                        <td><a href="${d.file_path}" target="_blank">${d.file_name}</a></td>
                        <td>${(d.file_size / 1024).toFixed(1)} KB</td>
                        <td>${formatDate(d.uploaded_at)}</td>
                    </tr>`).join('')}
                </tbody>
            </table>
        </div>`;
}

async function loadApplications(container) {
    const data = await apiCall('admin.php?action=applications');
    container.innerHTML = `
        <h3 class="mb-4">Applications</h3>
        <div class="table-responsive">
            <table class="table table-hover govt-card">
                <thead><tr><th>User</th><th>Scheme</th><th>Category</th><th>Status</th><th>Applied</th></tr></thead>
                <tbody>${(data.applications || []).map(a => `
                    <tr>
                        <td>${a.full_name}</td><td>${a.scheme_name}</td><td>${a.category}</td>
                        <td><span class="badge bg-info">${a.status}</span></td>
                        <td>${formatDate(a.applied_at)}</td>
                    </tr>`).join('')}
                </tbody>
            </table>
        </div>`;
}

async function loadMessages(container) {
    const data = await apiCall('contact.php?action=list');
    container.innerHTML = `
        <h3 class="mb-4">Contact Messages</h3>
        ${(data.messages || []).map(m => `
            <div class="notif-item">
                <h6>${m.subject} <small class="text-muted">from ${m.name} (${m.email})</small></h6>
                <p>${m.message}</p>
                <small class="text-muted">${formatDate(m.created_at)}</small>
            </div>`).join('')}`;
}

async function adminLogout() {
    await apiCall('auth.php?action=logout');
    window.location.reload();
}
