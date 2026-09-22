(function () {
    'use strict';

    const root = document.documentElement;
    const themeBtn = document.querySelector('[data-theme-toggle]');
    const navToggle = document.querySelector('.nav-toggle');
    const navLinks = document.getElementById('navLinks');
    const toastContainer = document.getElementById('toastContainer');

    /* Label tema mengikuti bahasa halaman (<html lang>) */
    const THEME_LABELS = root.lang === 'en'
        ? { dark: 'Light mode', light: 'Dark mode' }
        : { dark: 'Mode terang', light: 'Mode gelap' };

    /* Konfirmasi aksi destruktif (data-confirm="...") */
    function initConfirm() {
        document.querySelectorAll('[data-confirm]').forEach(function (el) {
            el.addEventListener('click', function (e) {
                if (!window.confirm(el.getAttribute('data-confirm'))) {
                    e.preventDefault();
                    e.stopPropagation();
                }
            });
        });
    }

    /* Toggle field target broadcast (admin/broadcast.php) */
    function initToggleTarget() {
        const sel = document.querySelector('[data-toggle-target]');
        if (!sel) return;
        const field = document.getElementById('field-user');
        const sync = function () {
            if (field) field.hidden = sel.value !== 'user';
        };
        sel.addEventListener('change', sync);
        sync();
    }

    function initTheme() {
        if (!themeBtn) return;
        const dark = root.dataset.theme === 'dark';
        themeBtn.textContent = dark ? THEME_LABELS.dark : THEME_LABELS.light;
        themeBtn.setAttribute('aria-pressed', dark ? 'true' : 'false');
    }

    function toggleTheme(e) {
        if (!themeBtn) return;
        const dark = root.dataset.theme !== 'dark';
        const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        /* Titik asal animasi: tombol tema (fallback: tengah layar) */
        let ox = window.innerWidth / 2;
        let oy = window.innerHeight / 2;
        if (e && e.currentTarget && e.currentTarget.getBoundingClientRect) {
            const r = e.currentTarget.getBoundingClientRect();
            ox = r.left + r.width / 2;
            oy = r.top + r.height / 2;
        }

        const apply = function () {
            root.dataset.theme = dark ? 'dark' : 'light';
            try { localStorage.setItem('theme', root.dataset.theme); } catch (err) {}
            themeBtn.textContent = dark ? THEME_LABELS.dark : THEME_LABELS.light;
            themeBtn.setAttribute('aria-pressed', dark ? 'true' : 'false');
        };

        if (typeof document.startViewTransition === 'function' && !reduce) {
            /* Browser modern: reveal melingkar dari tombol tema */
            const vt = document.startViewTransition(apply);
            vt.ready.then(function () {
                const radius = Math.hypot(
                    Math.max(ox, window.innerWidth - ox),
                    Math.max(oy, window.innerHeight - oy)
                );
                document.documentElement.animate(
                    {
                        clipPath: [
                            'circle(0px at ' + ox + 'px ' + oy + 'px)',
                            'circle(' + radius + 'px at ' + ox + 'px ' + oy + 'px)'
                        ]
                    },
                    { duration: 550, easing: 'ease-in-out', pseudoElement: '::view-transition-new(root)' }
                );
            }).catch(function () { /* animasi gagal: tema tetap berganti */ });
        } else {
            /* Fallback: transisi warna global lewat class sementara */
            root.classList.add('theme-transitioning');
            apply();
            window.setTimeout(function () { root.classList.remove('theme-transitioning'); }, 500);
        }
    }

    function initMobileNav() {
        if (!navToggle || !navLinks) return;

        /* Harus sama dengan breakpoint CSS: @media (max-width: 768px).
           Tampil/sembunyi diatur murni lewat CSS dari aria-expanded
           (.nav-toggle[aria-expanded="true"] + .nav-links) — JS tidak menyentuh
           properti hidden agar tidak bentrok dengan aturan CSS. */
        var isMobile = function () { return window.innerWidth <= 768; };

        navToggle.addEventListener('click', function () {
            const expanded = navToggle.getAttribute('aria-expanded') === 'true';
            navToggle.setAttribute('aria-expanded', (!expanded).toString());
        });

        document.addEventListener('click', function (e) {
            if (!isMobile()) return;
            if (!navToggle.contains(e.target) && !navLinks.contains(e.target)) {
                navToggle.setAttribute('aria-expanded', 'false');
            }
        });

        navLinks.querySelectorAll('a').forEach(function (link) {
            link.addEventListener('click', function () {
                if (isMobile()) {
                    navToggle.setAttribute('aria-expanded', 'false');
                }
            });
        });

        window.addEventListener('resize', function () {
            if (!isMobile()) {
                navToggle.setAttribute('aria-expanded', 'false');
            }
        });
    }

    function showToast(message, type = 'info', duration = 4000) {
        if (!toastContainer) return;

        const toast = document.createElement('div');
        toast.className = 'toast toast-' + type;
        toast.setAttribute('role', 'alert');
        toast.setAttribute('aria-live', 'polite');

        const icons = {
            ok: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>',
            error: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>',
            warn: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
            info: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>'
        };

        toast.innerHTML = icons[type] + '<span>' + message + '</span>' +
            '<button type="button" class="toast-close" aria-label="Tutup notifikasi">&times;</button>';

        toastContainer.appendChild(toast);

        toast.querySelector('.toast-close').addEventListener('click', function () {
            removeToast(toast);
        });

        if (duration > 0) {
            setTimeout(function () { removeToast(toast); }, duration);
        }

        return toast;
    }

    function removeToast(toast) {
        toast.classList.add('removing');
        toast.addEventListener('animationend', function () { toast.remove(); });
    }

    function fireConfetti(count) {
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
        const n = count || 90;
        const colors = ['#0f4d33', '#16694a', '#c39b2c', '#e8b84d', '#2a5a86', '#a11d31'];
        const container = document.createElement('div');
        container.className = 'confetti-container';
        container.setAttribute('aria-hidden', 'true');
        document.body.appendChild(container);
        for (let i = 0; i < n; i++) {
            const p = document.createElement('span');
            p.className = 'confetti' + (Math.random() < 0.35 ? ' round' : '');
            p.style.left = (Math.random() * 100) + 'vw';
            p.style.backgroundColor = colors[i % colors.length];
            p.style.animationDuration = (2.2 + Math.random() * 1.8) + 's';
            p.style.animationDelay = (Math.random() * 0.7) + 's';
            const s = 7 + Math.random() * 7;
            p.style.width = s + 'px';
            p.style.height = (s * (Math.random() < 0.5 ? 1 : 1.4)) + 'px';
            container.appendChild(p);
        }
        window.setTimeout(function () { container.remove(); }, 5200);
    }

    function initResultEffects() {
        /* Angka naik mulus untuk elemen <span data-countup="..."> */
        const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        document.querySelectorAll('[data-countup]').forEach(function (el) {
            const target = parseInt(el.getAttribute('data-countup'), 10) || 0;
            const suffix = el.getAttribute('data-suffix') || '';
            if (reduce) { el.textContent = target + suffix; return; }
            const dur = 1100;
            const t0 = performance.now();
            const frame = function (t) {
                const pr = Math.min(1, (t - t0) / dur);
                const eased = 1 - Math.pow(1 - pr, 3);
                el.textContent = Math.round(target * eased) + suffix;
                if (pr < 1) requestAnimationFrame(frame);
            };
            requestAnimationFrame(frame);
        });
        /* Perayaan: halaman dengan data-confetti melempar confetti sekali */
        if (document.querySelector('[data-confetti]')) fireConfetti();
    }

    function initDialogs() {
        document.querySelectorAll('[data-open-dialog]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const dialogId = btn.getAttribute('data-open-dialog');
                const dialog = document.getElementById(dialogId);
                if (dialog) {
                    dialog.showModal();
                    const focusable = dialog.querySelector('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
                    if (focusable) focusable.focus();
                }
            });
        });

        document.querySelectorAll('[data-close-dialog]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const dialogId = btn.getAttribute('data-close-dialog');
                const dialog = document.getElementById(dialogId);
                if (dialog) dialog.close();
            });
        });

        document.querySelectorAll('dialog').forEach(function (dialog) {
            dialog.addEventListener('click', function (e) {
                if (e.target === dialog) dialog.close();
            });

            dialog.addEventListener('cancel', function (e) {
                e.preventDefault();
                dialog.close();
            });

            dialog.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') dialog.close();
            });
        });
    }

    function initServiceWorker() {
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function () {
                navigator.serviceWorker.register('../sw.js').catch(function () {});
            });
        }
    }

    function initFormSubmitButtons() {
        document.querySelectorAll('form').forEach(function (form) {
            form.addEventListener('submit', function (e) {
                /* Handler submit halaman (mis. quizForm) boleh membatalkan
                   submit — jangan kunci tombol jika submit dibatalkan. */
                if (e.defaultPrevented) return;
                const submitBtn = form.querySelector('button[type="submit"], input[type="submit"]');
                if (submitBtn && !submitBtn.disabled) {
                    submitBtn.dataset.originalText = submitBtn.textContent;
                    submitBtn.textContent = 'Memproses...';
                    submitBtn.disabled = true;
                    submitBtn.classList.add('btn-loading');
                }
            });
        });

        /* Saat pengguna kembali lewat tombol back (bfcache), tombol submit
           masih dalam keadaan disabled "Memproses..." — kembalikan. */
        window.addEventListener('pageshow', function (event) {
            if (event.persisted) {
                document.querySelectorAll('.btn-loading').forEach(function (btn) {
                    btn.classList.remove('btn-loading');
                    btn.disabled = false;
                    if (btn.dataset.originalText) {
                        btn.textContent = btn.dataset.originalText;
                        delete btn.dataset.originalText;
                    }
                });
            }
        });
    }

    function initAutoDismissAlerts() {
        document.querySelectorAll('.notice:not(.no-auto-dismiss)').forEach(function (notice) {
            setTimeout(function () {
                notice.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
                notice.style.opacity = '0';
                notice.style.transform = 'translateY(-4px)';
                setTimeout(function () { notice.remove(); }, 300);
            }, 8000);
        });
    }

    function initCopyButtons() {
        document.querySelectorAll('[data-copy]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var text = btn.getAttribute('data-copy');

                var fallbackCopy = function () {
                    var ta = document.createElement('textarea');
                    ta.value = text;
                    ta.setAttribute('readonly', '');
                    ta.style.position = 'fixed';
                    ta.style.opacity = '0';
                    document.body.appendChild(ta);
                    ta.select();
                    try {
                        showToast(document.execCommand('copy') ? 'Disalin ke clipboard' : 'Gagal menyalin', 'ok', 2000);
                    } catch (e) {
                        showToast('Gagal menyalin', 'error', 2000);
                    }
                    ta.remove();
                };

                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(text).then(function () {
                        showToast('Disalin ke clipboard', 'ok', 2000);
                    }).catch(fallbackCopy);
                } else {
                    fallbackCopy();
                }
            });
        });
    }

    function initPrintButtons() {
        document.querySelectorAll('[data-print]').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                window.print();
            });
        });
    }

    function initScrollToTop() {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'scroll-top-btn';
        btn.setAttribute('aria-label', 'Scroll ke atas');
        btn.innerHTML = '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><polyline points="18 15 12 9 6 15"/></svg>';
        document.body.appendChild(btn);

        btn.addEventListener('click', function () { window.scrollTo({ top: 0, behavior: 'smooth' }); });

        window.addEventListener('scroll', function () {
            if (window.scrollY > 300) {
                btn.classList.add('visible');
            } else {
                btn.classList.remove('visible');
            }
        }, { passive: true });
    }

    function initSmoothAnchorScroll() {
        document.querySelectorAll('a[href^="#"]').forEach(function (anchor) {
            anchor.addEventListener('click', function (e) {
                const targetId = this.getAttribute('href').slice(1);
                const target = document.getElementById(targetId);
                if (target) {
                    e.preventDefault();
                    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    target.focus({ preventScroll: true });
                }
            });
        });
    }

    function initPageFadeIn() {
        document.body.classList.add('page-enter');
        requestAnimationFrame(function () {
            requestAnimationFrame(function () {
                document.body.classList.add('page-enter-active');
            });
        });
    }

    function initTableSearch() {
        document.querySelectorAll('[data-table-search]').forEach(function (input) {
            const tableId = input.getAttribute('data-table-search');
            const table = document.getElementById(tableId);
            if (!table) return;

            const rows = table.querySelectorAll('tbody tr');
            const counter = document.querySelector('[data-search-count="' + tableId + '"]');

            input.addEventListener('input', function () {
                const q = this.value.trim().toLowerCase();
                let visible = 0;
                rows.forEach(function (row) {
                    const match = row.textContent.toLowerCase().indexOf(q) !== -1;
                    row.hidden = !match;
                    if (match) visible++;
                });
                if (counter) counter.textContent = visible + ' dari ' + rows.length + ' data';
            });
        });
    }

    function initKeyboardShortcuts() {
        document.addEventListener('keydown', function (e) {
            if (e.ctrlKey || e.metaKey || e.altKey) return;
            const tag = (e.target.tagName || '').toLowerCase();
            if (tag === 'input' || tag === 'textarea' || tag === 'select' || e.target.isContentEditable) return;
            if (document.querySelector('dialog[open]')) return;

            switch (e.key) {
                case 'd': case 'D':
                    if (themeBtn) toggleTheme();
                    break;
                case '/':
                    e.preventDefault();
                    const search = document.querySelector('[data-table-search]');
                    if (search) { search.focus(); search.select(); }
                    break;
                case 'Escape':
                    document.querySelectorAll('.notice').forEach(function (n) { n.remove(); });
                    break;
            }
        });
    }

    function initLazyReveal() {
        /* Beri class .reveal + stagger pada anak langsung <main> agar semua
           section punya masuk sinematik yang konsisten. */
        Array.prototype.forEach.call(document.querySelectorAll('main > section, main > article'), function (el, i) {
            if (!el.classList.contains('reveal')) {
                el.classList.add('reveal');
                el.style.transitionDelay = Math.min(i * 60, 360) + 'ms';
            }
        });

        if (!('IntersectionObserver' in window)) {
            /* Fallback: tampilkan semua — jangan biarkan konten opacity:0 selamanya */
            document.querySelectorAll('.reveal').forEach(function (el) {
                el.classList.add('revealed');
            });
            return;
        }
        const observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('revealed');
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.08, rootMargin: '0px 0px -40px 0px' });

        document.querySelectorAll('.reveal').forEach(function (el) { observer.observe(el); });
    }

    function initExternalLinks() {
        document.querySelectorAll('a[target="_blank"]').forEach(function (link) {
            if (!link.getAttribute('rel')) {
                link.setAttribute('rel', 'noopener noreferrer');
            }
        });
    }

    function initSelectAutoSubmit() {
        document.querySelectorAll('select[data-autosubmit]').forEach(function (select) {
            select.addEventListener('change', function () {
                if (select.form) select.form.submit();
            });
        });
    }

    /* ===== Splash sinematik: sekali per sesi browser ===== */
    function initSplash() {
        const splash = document.getElementById('splash');
        if (!splash) return;
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            splash.remove();
            return;
        }
        const KEY = 'kukar_splash';
        let shown = false;
        try {
            shown = sessionStorage.getItem(KEY) === '1';
        } catch (err) {}
        if (shown) { splash.remove(); return; }
        try { sessionStorage.setItem(KEY, '1'); } catch (err) {}

        const MIN = 1400;
        const t0 = Date.now();
        const finish = function () {
            const wait = Math.max(0, MIN - (Date.now() - t0));
            window.setTimeout(function () {
                splash.classList.add('is-done');
                document.body.classList.add('splash-done');
                window.setTimeout(function () { splash.remove(); }, 700);
            }, wait);
        };

        if (document.readyState === 'complete') {
            finish();
        } else {
            window.addEventListener('load', finish);
            /* Jaring pengaman: jangan biarkan splash menutup layar selamanya */
            window.setTimeout(finish, 3500);
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        initSplash();
        initTheme();
        initConfirm();
        initToggleTarget();
        initMobileNav();
        initDialogs();
        initServiceWorker();
        initFormSubmitButtons();
        initAutoDismissAlerts();
        initCopyButtons();
        initPrintButtons();
        initScrollToTop();
        initSmoothAnchorScroll();
        initPageFadeIn();
        initTableSearch();
        initKeyboardShortcuts();
        initLazyReveal();
        initExternalLinks();
        initSelectAutoSubmit();
        initResultEffects();

        if (themeBtn) themeBtn.addEventListener('click', toggleTheme);
    });

    window.KukarApp = {
        showToast: showToast,
        removeToast: removeToast,
        fireConfetti: fireConfetti
    };
})();