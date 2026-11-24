document.addEventListener('DOMContentLoaded', function () {
    const sidebar = document.getElementById('app-sidebar');
    const toggleBtn = document.querySelector('[data-sidebar-toggle]');
    const closeBtn = document.querySelector('[data-sidebar-close]');
    const backdrop = document.querySelector('[data-sidebar-backdrop]');

    function setSidebarOpen(open) {
        if (!sidebar) {
            return;
        }
        sidebar.classList.toggle('is-open', open);
        if (backdrop) {
            backdrop.hidden = !open;
        }
        if (toggleBtn) {
            toggleBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
        }
    }

    if (toggleBtn) {
        toggleBtn.addEventListener('click', function () {
            const isOpen = sidebar ? !sidebar.classList.contains('is-open') : false;
            setSidebarOpen(isOpen);
        });
    }
    if (closeBtn) {
        closeBtn.addEventListener('click', function () {
            setSidebarOpen(false);
        });
    }
    if (backdrop) {
        backdrop.addEventListener('click', function () {
            setSidebarOpen(false);
        });
    }

    document.querySelectorAll('[data-password-toggle]').forEach(function (button) {
        button.addEventListener('click', function () {
            const targetId = button.getAttribute('data-password-toggle');
            const input = targetId ? document.getElementById(targetId) : null;
            if (!input) {
                return;
            }
            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            button.textContent = show ? 'Fshih' : 'Shfaq';
            button.setAttribute('aria-pressed', show ? 'true' : 'false');
        });
    });

    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            const message = form.getAttribute('data-confirm') || 'A jeni të sigurt?';
            if (!window.confirm(message)) {
                event.preventDefault();
            }
        });
    });

    document.querySelectorAll('[data-print-trigger]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            window.print();
        });
    });

    document.querySelectorAll('.admin-role-form').forEach(function (form) {
        const roleSelect = form.querySelector('select[name="role"]');
        if (!roleSelect) {
            return;
        }
        const linkFields = form.querySelectorAll('[data-role-link]');
        function syncRoleLinks() {
            const selectedRole = roleSelect.value;
            linkFields.forEach(function (field) {
                const targetRole = field.getAttribute('data-role-link');
                const visible = targetRole === selectedRole;
                field.classList.toggle('is-hidden', !visible);
                const innerSelect = field.tagName === 'SELECT' ? field : field.querySelector('select');
                if (innerSelect) {
                    innerSelect.disabled = !visible;
                }
            });
        }
        roleSelect.addEventListener('change', syncRoleLinks);
        syncRoleLinks();
    });
});
