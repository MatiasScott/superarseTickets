document.addEventListener('DOMContentLoaded', () => {
        const markRequiredLabels = () => {
            const controls = Array.from(document.querySelectorAll('input[required], select[required], textarea[required], [aria-required="true"]'));
            const labelIds = new Set();

            controls.forEach((control) => {
                if (control.type === 'hidden' || control.disabled) {
                    return;
                }

                const controlId = control.getAttribute('id') || '';
                if (controlId !== '') {
                    const label = document.querySelector(`label[for="${CSS.escape(controlId)}"]`);
                    if (label) {
                        labelIds.add(label);
                    }
                }

                const wrapperLabel = control.closest('label');
                if (wrapperLabel) {
                    labelIds.add(wrapperLabel);
                } else {
                    const siblingLabel = control.previousElementSibling;
                    if (siblingLabel && siblingLabel.tagName === 'LABEL') {
                        labelIds.add(siblingLabel);
                    }
                }
            });

            labelIds.forEach((label) => {
                if (label.dataset.requiredMarked === '1') {
                    return;
                }
                label.dataset.requiredMarked = '1';
                label.classList.add('is-required-field');

                if (!label.querySelector('.required-field-marker')) {
                    const marker = document.createElement('span');
                    marker.className = 'required-field-marker';
                    marker.textContent = ' *';
                    label.appendChild(marker);
                }
            });
        };

        markRequiredLabels();

        const notificationsBtn = document.getElementById('topbarNotificationsBtn');
        const notificationsBadge = document.getElementById('topbarNotificationsBadge');
        const notificationsCount = document.getElementById('topbarNotificationsCount');
        const notificationsList = document.getElementById('topbarNotificationsList');

        const escapeHtml = (value) => {
            const div = document.createElement('div');
            div.textContent = String(value ?? '');
            return div.innerHTML;
        };

        const timeAgo = (rawDate) => {
            if (!rawDate) return '';
            const date = new Date(rawDate.replace(' ', 'T'));
            if (Number.isNaN(date.getTime())) return '';

            const seconds = Math.floor((Date.now() - date.getTime()) / 1000);
            if (seconds < 60) return 'hace un momento';
            if (seconds < 3600) return `hace ${Math.floor(seconds / 60)} min`;
            if (seconds < 86400) return `hace ${Math.floor(seconds / 3600)} h`;
            return `hace ${Math.floor(seconds / 86400)} d`;
        };

        const renderNotifications = (items, totalCount) => {
            if (notificationsCount) {
                notificationsCount.textContent = String(totalCount || 0);
            }

            if (notificationsBadge) {
                notificationsBadge.textContent = String(totalCount || 0);
                notificationsBadge.classList.toggle('d-none', !(totalCount > 0));
            }

            if (!notificationsList) {
                return;
            }

            if (!Array.isArray(items) || items.length === 0) {
                notificationsList.innerHTML = '<div class="notification-empty">No tienes notificaciones nuevas.</div>';
                return;
            }

            notificationsList.innerHTML = items.map((item) => {
                const typeClass = item.type === 'vencido' ? 'is-danger' : (item.type === 'vence_hoy' ? 'is-warning' : 'is-info');
                return `
                    <a href="${escapeHtml(item.url || '#')}" class="notification-item ${typeClass}">
                        <div class="notification-item-title">${escapeHtml(item.title || 'Notificación')}</div>
                        <div class="notification-item-message">${escapeHtml(item.message || '')}</div>
                        <div class="notification-item-time">${escapeHtml(timeAgo(item.created_at || ''))}</div>
                    </a>
                `;
            }).join('');
        };

        const loadNotifications = async () => {
            if (!notificationsBtn) {
                return;
            }

            try {
                const response = await fetch(`${BASE_URL}api/notifications`, {
                    method: 'GET',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                    cache: 'no-store',
                });

                if (!response.ok) {
                    throw new Error('No se pudieron cargar las notificaciones');
                }

                const payload = await response.json();
                if (!payload?.ok) {
                    throw new Error(payload?.error || 'Respuesta inválida');
                }

                renderNotifications(payload.items || [], Number(payload.count || 0));
            } catch (error) {
                if (notificationsList) {
                    notificationsList.innerHTML = '<div class="notification-empty">No se pudieron cargar notificaciones.</div>';
                }
            }
        };

        if (notificationsBtn) {
            notificationsBtn.addEventListener('show.bs.dropdown', () => {
                loadNotifications();
            });
            loadNotifications();
            window.setInterval(loadNotifications, 60000);
        }

        // --- Notificaciones globales tipo toast ---
        window.showGlobalNotification = function(message, type = 'success', timeout = 3500) {
            let container = document.getElementById('globalNotifications');
            if (!container) {
                container = document.createElement('div');
                container.id = 'globalNotifications';
                container.className = 'position-fixed top-0 end-0 p-3';
                document.body.appendChild(container);
            }
            const toast = document.createElement('div');
            toast.className = `toast align-items-center text-bg-${type} show fade global-toast`;
            toast.setAttribute('role', 'alert');
            toast.setAttribute('aria-live', 'assertive');
            toast.setAttribute('aria-atomic', 'true');
            toast.innerHTML = `
                <div class="d-flex">
                    <div class="toast-body">${message}</div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Cerrar"></button>
                </div>
            `;
            container.appendChild(toast);
            // Cerrar al hacer click en la X
            toast.querySelector('.btn-close').onclick = () => toast.remove();
            // Auto-cerrar
            setTimeout(() => { toast.remove(); }, timeout);
        };
    const rows = document.querySelectorAll('table tbody tr');
    rows.forEach((row, index) => {
        row.style.animation = `fadeInUp .25s ease ${index * 0.02}s both`;
    });

    const currentPath = window.location.pathname.toLowerCase();
    const sidebarLinks = document.querySelectorAll('.sidebar-link[href]');
    const sidebarGroups = document.querySelectorAll('.sidebar-group');
    let bestLink = null;
    let bestLength = -1;

    const setGroupState = (group, isOpen) => {
        const toggle = group.querySelector('.sidebar-toggle');
        if (!toggle) return;
        group.classList.toggle('open', isOpen);
        toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    };

    const closeOtherGroups = (groupToKeepOpen) => {
        sidebarGroups.forEach((group) => {
            if (group !== groupToKeepOpen) {
                setGroupState(group, false);
            }
        });
    };

    sidebarGroups.forEach((group) => {
        setGroupState(group, false);
        const toggle = group.querySelector('.sidebar-toggle');
        if (!toggle) return;
        toggle.addEventListener('click', () => {
            const isOpen = group.classList.contains('open');
            if (isOpen) {
                setGroupState(group, false);
                return;
            }

            closeOtherGroups(group);
            setGroupState(group, true);
        });
    });

    sidebarLinks.forEach((link) => {
        const href = link.getAttribute('href');
        if (!href || href === '#') return;

        let linkPath = '';
        try {
            linkPath = new URL(href, window.location.origin).pathname.toLowerCase();
        } catch (e) {
            return;
        }

        if (linkPath === '/' || linkPath === '') return;
        if (currentPath === linkPath || currentPath.startsWith(linkPath + '/')) {
            if (linkPath.length > bestLength) {
                bestLength = linkPath.length;
                bestLink = link;
            }
        }
    });

    if (bestLink) {
        bestLink.classList.add('active');
        const parentGroup = bestLink.closest('.sidebar-group');
        if (parentGroup) {
            closeOtherGroups(parentGroup);
            setGroupState(parentGroup, true);
        }
    } else if (sidebarGroups.length > 0) {
        closeOtherGroups(sidebarGroups[0]);
        setGroupState(sidebarGroups[0], true);
    }

    const responsiveTables = document.querySelectorAll('.table-responsive[data-mobile-cards] table');
    responsiveTables.forEach((table) => {
        const headers = Array.from(table.querySelectorAll('thead th')).map((th) => (th.textContent || '').trim());
        if (headers.length === 0) return;

        table.querySelectorAll('tbody tr').forEach((row) => {
            Array.from(row.children).forEach((cell, index) => {
                if (!cell.getAttribute('data-label')) {
                    cell.setAttribute('data-label', headers[index] || 'Dato');
                }
            });
        });
    });

    const appLayout = document.querySelector('.app-layout[data-heartbeat-enabled]');
    if (appLayout) {
        const heartbeatEnabled = appLayout.getAttribute('data-heartbeat-enabled') === '1';
        const heartbeatUrl = appLayout.getAttribute('data-heartbeat-url') || '';
        const intervalRaw = Number(appLayout.getAttribute('data-heartbeat-interval-ms') || '60000');
        const heartbeatInterval = Number.isFinite(intervalRaw) ? Math.max(60000, intervalRaw) : 60000;

        if (heartbeatEnabled && heartbeatUrl !== '') {
            let heartbeatInFlight = false;
            const runHeartbeat = async () => {
                if (heartbeatInFlight || document.hidden || !navigator.onLine) {
                    return;
                }

                heartbeatInFlight = true;
                try {
                    await fetch(heartbeatUrl, {
                        method: 'GET',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        credentials: 'same-origin',
                        cache: 'no-store',
                    });
                } catch (e) {
                    // Heartbeat silencioso: no interrumpe UX si falla.
                } finally {
                    heartbeatInFlight = false;
                }
            };

            runHeartbeat();
            const heartbeatTimer = window.setInterval(runHeartbeat, heartbeatInterval);
            document.addEventListener('visibilitychange', () => {
                if (!document.hidden) {
                    runHeartbeat();
                }
            });
            window.addEventListener('beforeunload', () => {
                window.clearInterval(heartbeatTimer);
            });
        }
    }
});
