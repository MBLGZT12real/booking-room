/**
 * app.js - Custom JS for Booking Room Admin Panel
 * Handles SSE notifications, UI interactions, and helpers.
 */

/* ============================================================
 * Global APP object (populated in footer.php)
 * APP.baseUrl, APP.userId, APP.csrfToken
 * ============================================================ */

(function () {
    'use strict';

    // ================================================================
    // NOTIFICATION SYSTEM (SSE with polling fallback)
    // ================================================================

    const Notif = {
        lastId: 0,
        useSSE: typeof EventSource !== 'undefined',
        sseSource: null,
        pollTimer: null,
        POLL_INTERVAL: 15000, // 15s fallback

        init() {
            if (!APP || !APP.userId) return;

            if (this.useSSE) {
                this.startSSE();
            } else {
                this.startPolling();
            }
        },

        startSSE() {
            const url = APP.baseUrl + '/api/notifications-stream.php?last_id=' + this.lastId;

            if (this.sseSource) {
                this.sseSource.close();
            }

            this.sseSource = new EventSource(url);

            this.sseSource.addEventListener('notification', (e) => {
                try {
                    const data = JSON.parse(e.data);
                    if (data.notifications && data.notifications.length) {
                        this.handleNew(data.notifications, data.unread_count);
                        this.lastId = data.last_id;
                    }
                } catch {}
            });

            this.sseSource.addEventListener('reconnect', () => {
                this.sseSource.close();
                setTimeout(() => this.startSSE(), 1000);
            });

            this.sseSource.onerror = () => {
                this.sseSource.close();
                // Fallback to polling on error
                this.useSSE = false;
                this.startPolling();
            };
        },

        startPolling() {
            this.poll();
            this.pollTimer = setInterval(() => this.poll(), this.POLL_INTERVAL);
        },

        poll() {
            fetch(APP.baseUrl + '/api/notifications-poll.php?last_id=' + this.lastId)
                .then(r => r.json())
                .then(data => {
                    if (data.success && data.notifications.length) {
                        this.handleNew(data.notifications, data.unread_count);
                        this.lastId = data.last_id;
                    }
                    this.updateBadge(data.unread_count);
                })
                .catch(() => {});
        },

        handleNew(notifications, unreadCount) {
            this.updateBadge(unreadCount);
            this.updateDropdown(notifications);
            this.playSound();
            // Toast notification for latest
            const n = notifications[0];
            if (n) this.showToast(n);
        },

        updateBadge(count) {
            const badge = document.getElementById('notifBadge');
            const mobileCount = document.getElementById('notifCount');
            if (!badge) return;
            if (count > 0) {
                badge.textContent = count > 99 ? '99+' : count;
                badge.style.display = 'inline-flex';
                if (mobileCount) mobileCount.textContent = count;
            } else {
                badge.style.display = 'none';
            }
        },

        updateDropdown(notifications) {
            const list = document.getElementById('notifDropdownList');
            if (!list) return;

            // Prepend new notifications
            notifications.forEach(n => {
                const el = document.createElement('a');
                el.href = APP.baseUrl + '/admin/notifications/index.php';
                el.className = 'dropdown-item notif-item unread py-2 px-3';
                el.dataset.id = n.id;
                el.innerHTML = `
                    <div class="d-flex gap-2 align-items-start">
                        <span class="notif-icon">${this.getIcon(n.type)}</span>
                        <div class="flex-grow-1 overflow-hidden">
                            <div class="small fw-semibold text-truncate">${escHtml(n.title)}</div>
                            <div class="text-muted" style="font-size:.75rem;line-height:1.3;">${escHtml(n.message)}</div>
                        </div>
                    </div>`;
                list.prepend(el);
            });

            // Remove 'no notifications' placeholder if present
            const empty = list.querySelector('.notif-empty');
            if (empty) empty.remove();
        },

        getIcon(type) {
            const icons = {
                new_booking: '<i class="bi bi-calendar-plus text-primary"></i>',
                approved:    '<i class="bi bi-check-circle text-success"></i>',
                rejected:    '<i class="bi bi-x-circle text-danger"></i>',
                checkin:     '<i class="bi bi-box-arrow-in-right text-info"></i>',
                checkout:    '<i class="bi bi-box-arrow-right text-warning"></i>',
                reminder:    '<i class="bi bi-bell text-warning"></i>',
            };
            return icons[type] || '<i class="bi bi-bell"></i>';
        },

        showToast(notif) {
            const container = document.getElementById('toastContainer') || this.createToastContainer();
            const id = 'toast_' + Date.now();
            const el = document.createElement('div');
            el.id = id;
            el.className = 'toast align-items-center border-0 show';
            el.role = 'alert';
            el.setAttribute('aria-live', 'assertive');
            el.innerHTML = `
                <div class="d-flex">
                    <div class="toast-body">
                        ${this.getIcon(notif.type)}
                        <strong class="ms-1">${escHtml(notif.title)}</strong><br>
                        <small class="text-muted">${escHtml(notif.message)}</small>
                    </div>
                    <button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>`;
            container.appendChild(el);

            // Auto-remove after 5s
            setTimeout(() => {
                el.classList.remove('show');
                setTimeout(() => el.remove(), 300);
            }, 5000);
        },

        createToastContainer() {
            const el = document.createElement('div');
            el.id = 'toastContainer';
            el.className = 'toast-container position-fixed bottom-0 end-0 p-3';
            el.style.zIndex = '9999';
            document.body.appendChild(el);
            return el;
        },

        playSound() {
            // Optional: play notification sound
            // const audio = new Audio(APP.baseUrl + '/assets/sounds/notif.mp3');
            // audio.play().catch(() => {});
        },

        markRead(id) {
            const data = new FormData();
            data.append('id', id);
            fetch(APP.baseUrl + '/api/mark-read.php', {
                method: 'POST',
                body: data,
            }).catch(() => {});
        },

        markAllRead() {
            const data = new FormData();
            data.append('all', '1');
            fetch(APP.baseUrl + '/api/mark-read.php', {
                method: 'POST',
                body: data,
            }).then(() => {
                document.querySelectorAll('.notif-item.unread').forEach(el => {
                    el.classList.remove('unread');
                });
                this.updateBadge(0);
            }).catch(() => {});
        },
    };

    // ================================================================
    // HELPER: HTML escape
    // ================================================================
    function escHtml(str) {
        if (!str) return '';
        const d = document.createElement('div');
        d.appendChild(document.createTextNode(str));
        return d.innerHTML;
    }

    // ================================================================
    // CONFIRM DELETE (SweetAlert2)
    // ================================================================
    window.confirmDelete = function (form, msg) {
        msg = msg || 'Data ini akan dihapus permanen.';
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: 'Hapus data?',
                text: msg,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Ya, hapus!',
                cancelButtonText: 'Batal',
            }).then(result => {
                if (result.isConfirmed) form.submit();
            });
        } else {
            if (confirm('Hapus? ' + msg)) form.submit();
        }
        return false;
    };

    // ================================================================
    // SIDEBAR TOGGLE
    // ================================================================
    window.toggleSidebar = function () {
        document.querySelector('.admin-wrapper')?.classList.toggle('sidebar-collapsed');
        document.querySelector('.sidebar')?.classList.toggle('show');
    };

    // ================================================================
    // MARK NOTIFICATION AS READ (global, used in navbar.php)
    // ================================================================
    window.markRead = function (id) {
        Notif.markRead(id);
        const item = document.querySelector('[data-notif-id="' + id + '"]');
        if (item) item.classList.remove('unread');
    };

    window.markAllRead = function () {
        Notif.markAllRead();
    };

    // ================================================================
    // AUTO-DISMISS ALERTS
    // ================================================================
    function initAlerts() {
        document.querySelectorAll('.alert-dismissible:not(.alert-warning)').forEach(el => {
            setTimeout(() => {
                const bsAlert = bootstrap.Alert.getOrCreateInstance(el);
                bsAlert?.close();
            }, 4000);
        });
    }

    // ================================================================
    // DATATABLE DEFAULT INIT
    // ================================================================
    function initDataTables() {
        if (typeof $.fn.DataTable === 'undefined') return;

        document.querySelectorAll('table.datatable').forEach(table => {
            if ($.fn.DataTable.isDataTable(table)) return;

            const noFilter = table.dataset.noFilter === 'true';
            const domWithFilter    = '<"d-flex flex-wrap gap-2 justify-content-between align-items-center py-2 px-3"lf>rt<"d-flex flex-wrap gap-2 justify-content-between align-items-center py-2 px-3"ip>';
            const domWithoutFilter = '<"d-flex flex-wrap gap-2 justify-content-between align-items-center py-2 px-3"l>rt<"d-flex flex-wrap gap-2 justify-content-between align-items-center py-2 px-3"ip>';

            $(table).DataTable({
                language: {
                    url: APP.baseUrl + '/assets/js/datatables-id.json',
                },
                pageLength: 25,
                responsive: true,
                dom: noFilter ? domWithoutFilter : domWithFilter,
            });
        });
    }

    // ================================================================
    // SELECT2 DEFAULT INIT
    // ================================================================
    function initSelect2() {
        if (typeof $.fn.select2 === 'undefined') return;

        $('.select2:not(.select2-hidden-accessible)').select2({
            width: '100%',
        });
    }

    // ================================================================
    // FORM DOUBLE-SUBMIT PREVENTION
    // ================================================================
    function initFormGuards() {
        document.querySelectorAll('form[data-guard="true"]').forEach(form => {
            form.addEventListener('submit', function () {
                const btn = this.querySelector('[type="submit"]');
                if (btn) {
                    btn.disabled = true;
                    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Menyimpan...';
                }
            });
        });
    }

    // ================================================================
    // COPY TO CLIPBOARD
    // ================================================================
    window.copyToClipboard = function (text, btn) {
        navigator.clipboard.writeText(text).then(() => {
            const orig = btn?.innerHTML;
            if (btn) {
                btn.innerHTML = '<i class="bi bi-check"></i> Tersalin!';
                setTimeout(() => { btn.innerHTML = orig; }, 2000);
            }
        }).catch(() => {
            // Fallback
            const el = document.createElement('textarea');
            el.value = text;
            el.style.position = 'fixed';
            el.style.opacity = '0';
            document.body.appendChild(el);
            el.select();
            document.execCommand('copy');
            document.body.removeChild(el);
        });
    };

    // ================================================================
    // INIT ON DOM READY
    // ================================================================
    document.addEventListener('DOMContentLoaded', function () {
        initAlerts();
        initDataTables();
        initSelect2();
        initFormGuards();

        // Start notification system
        if (typeof APP !== 'undefined' && APP.userId) {
            Notif.init();
        }

        // Active sidebar link highlighting
        const path = window.location.pathname;
        document.querySelectorAll('.sidebar-link').forEach(link => {
            const href = link.getAttribute('href');
            if (href && path.startsWith(href) && href !== '/') {
                link.classList.add('active');
                const parent = link.closest('.sidebar-collapse');
                if (parent) parent.classList.add('show');
            }
        });

        // Bootstrap tooltip init
        document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => {
            new bootstrap.Tooltip(el);
        });

        // Mark notification as read when clicked
        document.querySelectorAll('.notif-item[data-id]').forEach(el => {
            el.addEventListener('click', function () {
                Notif.markRead(this.dataset.id);
                this.classList.remove('unread');
            });
        });
    });

})();
