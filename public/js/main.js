"use strict";

// ── Mobile sidebar toggle ─────────────────────────────────────────────────────

const sidebar       = document.getElementById('sidebar');
const sidebarToggle = document.getElementById('sidebarToggle');
let   backdrop      = null;

/**
 * Close the sidebar and remove the backdrop overlay.
 */
function closeSidebar() {
    sidebar?.classList.remove('sidebar--open');
    if (backdrop) {
        backdrop.remove();
        backdrop = null;
    }
}

if (sidebarToggle && sidebar) {
    sidebarToggle.addEventListener('click', function () {
        const isOpen = sidebar.classList.toggle('sidebar--open');

        if (isOpen) {
            // Add a semi-transparent backdrop so tapping outside closes the sidebar
            backdrop = document.createElement('div');
            backdrop.className = 'sidebar-backdrop';
            document.body.appendChild(backdrop);
            backdrop.addEventListener('click', closeSidebar);
        } else {
            closeSidebar();
        }
    });
}

// ── Utility helpers ───────────────────────────────────────────────────────────

/**
 * Show a Bootstrap dismissible alert inside #alert-container.
 * @param {string} message - Text to display.
 * @param {string} type    - Bootstrap colour: 'success', 'danger', 'warning', etc.
 */
function showAlert(message, type = 'success') {
    const alertBox = document.getElementById('alert-container');
    if (!alertBox) return;

    alertBox.innerHTML = `
        <div class="alert alert-${type} alert-dismissible fade show" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>`;
}

/**
 * Generic Fetch API helper — POST JSON body, return parsed response object.
 * @param {string} url  - Endpoint URL.
 * @param {Object} data - Payload to serialise as JSON.
 * @returns {Promise<Object>}
 */
async function fetchApi(url, data = {}) {
    const response = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data),
    });
    return response.json();
}

// ── Dashboard stats ───────────────────────────────────────────────────────────

/**
 * Map each stat key returned by api/dashboard_stats.php to the DOM element ID
 * that should display it, grouped by role.
 */
const STAT_MAP = {
    admin: {
        pending_trainers: 'stat-pending-trainers',
        banned_users:     'stat-banned-users',
        total_users:      'stat-total-users',
    },
    trainer: {
        my_exercises: 'stat-my-exercises',
        my_workouts:  'stat-my-workouts',
    },
    user: {
        saved_workouts:  'stat-saved-workouts',
        total_workouts:  'stat-total-workouts',
    },
};

// Load dashboard stats via Fetch and populate the stat cards
async function loadDashboardStats() {
    const role = window.USER_ROLE;
    if (!role) return; // Not on the dashboard page

    try {
        const response = await fetch((window.APP_BASE || '') + '/api/dashboard_stats.php');
        const stats    = await response.json();

        if (stats.error) {
            console.warn('Dashboard stats error:', stats.error);
            return;
        }

        // Write each value into its matching DOM element
        const map = STAT_MAP[role] || {};
        for (const [key, elementId] of Object.entries(map)) {
            const el = document.getElementById(elementId);
            if (el && stats[key] !== undefined) {
                el.textContent = stats[key];
            }
        }

    } catch (err) {
        // Network error — fail silently (stats are non-critical)
        console.error('Failed to load dashboard stats:', err);
    }
}

// Trigger stats load on dashboard page
if (document.getElementById('stats-row')) {
    loadDashboardStats();
}

// ── Registration form ─────────────────────────────────────────────────────────

const registerForm = document.getElementById('register-form');

if (registerForm) {

    // Show a warning when the trainer checkbox is ticked
    const trainerCheckbox = document.getElementById('is-trainer');
    const trainerNotice   = document.getElementById('trainer-notice');

    if (trainerCheckbox && trainerNotice) {
        trainerCheckbox.addEventListener('change', function () {
            trainerNotice.style.display = this.checked ? 'block' : 'none';
        });
    }

    // Real-time password match feedback
    const password  = document.getElementById('password');
    const password2 = document.getElementById('password2');

    if (password2) {
        password2.addEventListener('input', function () {
            if (this.value !== password.value) {
                this.setCustomValidity('Passwords do not match.');
                this.classList.add('is-invalid');
                this.classList.remove('is-valid');
            } else {
                this.setCustomValidity('');
                this.classList.remove('is-invalid');
                this.classList.add('is-valid');
            }
        });
    }

    // Debounced real-time email availability check via Fetch API
    const emailInput = document.getElementById('email');
    const emailCheck = document.getElementById('email-check');
    let   emailTimer = null;

    if (emailInput && emailCheck) {
        emailInput.addEventListener('input', function () {
            clearTimeout(emailTimer);
            const value = this.value.trim();

            // Skip the check for obviously incomplete addresses
            if (!value || !value.includes('@')) {
                emailCheck.textContent = '';
                return;
            }

            emailTimer = setTimeout(async () => {
                emailCheck.textContent = 'Checking...';

                const result = await fetchApi(
                    (window.APP_BASE || '') + '/api/check_email.php',
                    { email: value }
                );

                if (result.exists) {
                    emailCheck.textContent = '❌ This email is already registered.';
                    emailCheck.className   = 'form-text text-danger';
                    emailInput.classList.add('is-invalid');
                    emailInput.classList.remove('is-valid');
                } else {
                    emailCheck.textContent = '✅ Email is available.';
                    emailCheck.className   = 'form-text text-success';
                    emailInput.classList.remove('is-invalid');
                    emailInput.classList.add('is-valid');
                }
            }, 500); // Wait 500 ms after the user stops typing before sending the request
        });
    }

    // Run Bootstrap's built-in form validation on submit
    registerForm.addEventListener('submit', function (e) {
        if (!this.checkValidity()) {
            e.preventDefault();
            e.stopPropagation();
        }
        this.classList.add('was-validated');
    });
}
