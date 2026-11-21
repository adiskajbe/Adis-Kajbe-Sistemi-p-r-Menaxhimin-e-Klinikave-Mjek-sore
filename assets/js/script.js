document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('[data-password-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            const input = document.getElementById(button.getAttribute('data-password-toggle'));
            if (!input) return;

            const shouldShow = input.type === 'password';
            input.type = shouldShow ? 'text' : 'password';
            button.textContent = shouldShow ? 'Fshih' : 'Shfaq';
            button.setAttribute('aria-label', shouldShow ? 'Fshih fjalëkalimin' : 'Shfaq fjalëkalimin');
            button.setAttribute('aria-pressed', shouldShow ? 'true' : 'false');
        });
    });

    // Konfirmimi për fshirje (mos e fshi këtë, është funksionaliteti yt)
    document.querySelectorAll('[data-confirm]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            const message = form.getAttribute('data-confirm') || 'A dëshironi të vazhdoni?';
            if (!window.confirm(message)) {
                event.preventDefault();
            }
        });
    });

    // Shtimi i Loading State për të gjitha format (Pacient, Mjek, Termin)
    const forms = document.querySelectorAll('form.patient-form');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            if (!this.checkValidity()) return; 

            const submitBtn = this.querySelector('button[type="submit"]');
            if (submitBtn) {
                const originalText = submitBtn.innerHTML;
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="spinner"></span> Duke procesuar...';
                
                // Rikthimi nëse serveri vonohet shumë (maksimumi 5 sekonda)
                setTimeout(() => {
                    if (submitBtn.disabled) {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = originalText;
                    }
                }, 5000);
            }
        });
    });
});

// ===== Përmirësime të ndërfaqes (v2) – nuk ndryshojnë formularët, tokenët ose logjikën PHP =====
document.addEventListener('DOMContentLoaded', function () {
    const reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    // 1. Menuja për telefon dhe tabletë
    const headerInner = document.querySelector('.header-inner');
    const nav = headerInner ? headerInner.querySelector('.main-nav') : null;
    if (headerInner && nav) {
        document.documentElement.classList.add('js');
        if (!nav.id) nav.id = 'main-nav';
        const toggle = document.createElement('button');
        toggle.type = 'button';
        toggle.className = 'nav-toggle';
        toggle.setAttribute('aria-controls', nav.id);
        toggle.setAttribute('aria-expanded', 'false');
        toggle.setAttribute('aria-label', 'Hap menunë');
        toggle.innerHTML = '<span></span><span></span><span></span>';
        toggle.addEventListener('click', () => {
            const open = nav.classList.toggle('open');
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            toggle.setAttribute('aria-label', open ? 'Mbyll menunë' : 'Hap menunë');
        });
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && nav.classList.contains('open')) {
                toggle.click();
                toggle.focus();
            }
        });
        headerInner.insertBefore(toggle, nav);

        // Lidhja aktive sipas adresës (p.sh. Auditimi), vetëm kur ka përputhje të saktë
        const here = location.pathname.replace(/\/+$/, '');
        nav.querySelectorAll('a[href]').forEach((link) => {
            if (link.classList.contains('account-label')) return;
            if (link.closest('.mega-menu')) {
                if (new URL(link.href, location.href).pathname.replace(/\/+$/, '') === here) {
                    link.classList.add('is-current');
                    link.setAttribute('aria-current', 'page');
                }
                return;
            }
            if (new URL(link.href, location.href).pathname.replace(/\/+$/, '') === here) {
                nav.querySelectorAll('a.active').forEach((a) => a.classList.remove('active'));
                link.classList.add('active');
            }
        });
    }

    // 2. Etiketat e kolonave për tabelat që kthehen në karta në telefon
    document.querySelectorAll('.table-wrap table').forEach((table) => {
        const heads = Array.from(table.querySelectorAll('thead th')).map((th) => th.textContent.trim());
        table.querySelectorAll('tbody tr').forEach((row) => {
            Array.from(row.children).forEach((cell, index) => {
                if (cell.colSpan === 1 && heads[index] && !cell.hasAttribute('data-label')) {
                    cell.setAttribute('data-label', heads[index]);
                }
            });
        });
    });

    // 3. Statuset e termineve si pill, edhe aty ku paneli i shfaq si tekst i thjeshtë
    const statusLabels = { planifikuar: 'Planifikuar', perfunduar: 'Përfunduar', anuluar: 'Anuluar' };
    document.querySelectorAll('.table-wrap td').forEach((cell) => {
        const value = cell.textContent.trim();
        if (cell.children.length === 0 && Object.prototype.hasOwnProperty.call(statusLabels, value)) {
            const pill = document.createElement('span');
            pill.className = 'status-pill status-' + value;
            pill.textContent = statusLabels[value];
            cell.textContent = '';
            cell.appendChild(pill);
        }
    });

    // 4. Numërimi i butë i statistikave
    if (!reduceMotion) {
        document.querySelectorAll('.stat-card strong').forEach((el) => {
            const target = parseInt(el.textContent, 10);
            if (!/^\d+$/.test(el.textContent.trim()) || target > 100000) return;
            const start = performance.now();
            const tick = (now) => {
                const progress = Math.min((now - start) / 700, 1);
                el.textContent = String(Math.round(target * progress));
                if (progress < 1) requestAnimationFrame(tick);
            };
            el.textContent = '0';
            requestAnimationFrame(tick);
        });
    }

    // 5. Gjendje ngarkimi te formularët e kyçjes (pa çaktivizuar butonin, që të dërgohet siç është)
    document.querySelectorAll('form.auth-form').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (form.dataset.submitting === '1') { event.preventDefault(); return; }
            if (!form.checkValidity()) return;
            form.dataset.submitting = '1';
            const button = form.querySelector('button[type="submit"], button:not([type])');
            if (button) button.classList.add('is-loading');
        });
    });
    window.addEventListener('pageshow', () => {
        document.querySelectorAll('form.auth-form').forEach((form) => {
            delete form.dataset.submitting;
            form.querySelectorAll('.is-loading').forEach((b) => b.classList.remove('is-loading'));
        });
    });
});

// Shfaq vetëm profilin që lidhet me rolin e zgjedhur gjatë menaxhimit të llogarive.
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.admin-role-form').forEach((form) => {
        const roleSelect = form.querySelector('select[name="role"]');
        if (!roleSelect) return;
        const updateRoleFields = () => {
            form.querySelectorAll('[data-role-link]').forEach((field) => {
                const linkedRole = field.getAttribute('data-role-link');
                const select = field.matches('select') ? field : field.querySelector('select');
                const visible = roleSelect.value === linkedRole;
                field.hidden = !visible;
                if (select) select.required = visible;
            });
        };
        roleSelect.addEventListener('change', updateRoleFields);
        updateRoleFields();
    });
});

// ===== v3: dropdown "Shërbimet" dhe shfaqje e butë gjatë scroll-it =====
document.addEventListener('DOMContentLoaded', function () {
    const dropdown = document.querySelector('.nav-dropdown');
    if (dropdown) {
        const button = dropdown.querySelector('.nav-dropdown-toggle');
        const hoverQuery = window.matchMedia('(hover: hover) and (min-width: 901px)');
        let closeTimer;
        const setOpen = (open) => {
            dropdown.classList.toggle('open', open);
            button.setAttribute('aria-expanded', open ? 'true' : 'false');
        };
        button.addEventListener('click', () => setOpen(!dropdown.classList.contains('open')));
        dropdown.addEventListener('mouseenter', () => { if (hoverQuery.matches) { clearTimeout(closeTimer); setOpen(true); } });
        dropdown.addEventListener('mouseleave', () => { if (hoverQuery.matches) { closeTimer = setTimeout(() => setOpen(false), 180); } });
        dropdown.addEventListener('focusout', (event) => { if (!dropdown.contains(event.relatedTarget)) setOpen(false); });
        document.addEventListener('click', (event) => { if (!dropdown.contains(event.target)) setOpen(false); });
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && dropdown.classList.contains('open')) { setOpen(false); button.focus(); }
        });
    }

    const reveals = document.querySelectorAll('.reveal');
    const calm = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const done = (el) => setTimeout(() => el.classList.remove('reveal', 'in-view'), 900);
    if (!('IntersectionObserver' in window) || calm) {
        reveals.forEach((el) => el.classList.remove('reveal'));
    } else {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('in-view');
                    observer.unobserve(entry.target);
                    done(entry.target);
                }
            });
        }, { threshold: 0.12 });
        reveals.forEach((el) => observer.observe(el));
    }
});
