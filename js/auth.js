/**
 * Authentication JavaScript
 */

document.addEventListener('DOMContentLoaded', () => {
    initRegisterForm();
    initLoginForm();
    initAdminLoginForm();
    initForgotPassword();
});

function initRegisterForm() {
    const form = document.getElementById('registerForm');
    if (!form) return;

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (!validateForm(form)) {
            showToast('Please fill all required fields', 'warning');
            return;
        }

        const password = form.querySelector('[name="password"]').value;
        const confirm = form.querySelector('[name="confirm_password"]').value;
        if (password !== confirm) {
            showToast('Passwords do not match', 'error');
            return;
        }
        if (password.length < 6) {
            showToast('Password must be at least 6 characters', 'error');
            return;
        }

        const btn = form.querySelector('[type="submit"]');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Registering...';

        const formData = Object.fromEntries(new FormData(form));
        const data = await apiCall('auth.php?action=register', {
            method: 'POST',
            body: JSON.stringify(formData)
        });

        btn.disabled = false;
        btn.textContent = 'Register';

        if (data.success) {
            showToast(data.message, 'success');
            setTimeout(() => window.location.href = data.redirect || 'login.html', 1500);
        }
    });
}

function initLoginForm() {
    const form = document.getElementById('loginForm');
    if (!form) return;

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (!validateForm(form)) {
            showToast('Please enter email and password', 'warning');
            return;
        }

        const btn = form.querySelector('[type="submit"]');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Logging in...';

        const formData = Object.fromEntries(new FormData(form));
        const data = await apiCall('auth.php?action=login', {
            method: 'POST',
            body: JSON.stringify(formData)
        });

        btn.disabled = false;
        btn.textContent = 'Login';

        if (data.success) {
            showToast(data.message, 'success');
            setTimeout(() => window.location.href = data.redirect || 'dashboard.html', 1000);
        }
    });
}

function initAdminLoginForm() {
    const form = document.getElementById('adminLoginForm');
    if (!form) return;

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (!validateForm(form)) return;

        const btn = form.querySelector('[type="submit"]');
        btn.disabled = true;

        const formData = Object.fromEntries(new FormData(form));
        const data = await apiCall('auth.php?action=admin_login', {
            method: 'POST',
            body: JSON.stringify(formData)
        });

        btn.disabled = false;

        if (data.success) {
            showToast(data.message, 'success');
            setTimeout(() => showAdminPanel(), 1000);
        }
    });
}

function initForgotPassword() {
    const form = document.getElementById('forgotPasswordForm');
    if (!form) return;

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const email = form.querySelector('[name="email"]').value;
        if (!email) {
            showToast('Please enter your email', 'warning');
            return;
        }

        const data = await apiCall('auth.php?action=forgot_password', {
            method: 'POST',
            body: JSON.stringify({ email })
        });

        if (data.success) showToast(data.message, 'success');
    });
}
