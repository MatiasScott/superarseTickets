document.addEventListener('DOMContentLoaded', () => {
    const syncForm = document.getElementById('correoSyncForm');
    const statusCard = document.getElementById('correoAutoSyncStatus');
    if (!syncForm || !statusCard) {
        return;
    }

    const statusTextNode = statusCard.querySelector('[data-sync-status-text]');
    const lastRunNode = statusCard.querySelector('[data-sync-last-run]');
    const intervalMsRaw = Number(statusCard.getAttribute('data-auto-sync-interval-ms') || '5000');
    const intervalMs = Number.isFinite(intervalMsRaw) && intervalMsRaw >= 5000 ? intervalMsRaw : 5000;
    const autoSyncUrl = statusCard.getAttribute('data-auto-sync-url') || '';

    const tokenInput = syncForm.querySelector('input[name="_token"]');
    const aliasInput = syncForm.querySelector('input[name="account_alias"]');

    if (!tokenInput || !autoSyncUrl) {
        return;
    }

    let inFlight = false;
    let tickHandle = null;

    const updateStatus = (text, level) => {
        if (statusTextNode) {
            statusTextNode.textContent = text;
        }

        statusCard.classList.remove('alert-secondary', 'alert-success', 'alert-warning', 'alert-danger', 'alert-info');
        switch (level) {
            case 'success':
                statusCard.classList.add('alert-success');
                break;
            case 'warning':
                statusCard.classList.add('alert-warning');
                break;
            case 'danger':
                statusCard.classList.add('alert-danger');
                break;
            case 'info':
                statusCard.classList.add('alert-info');
                break;
            default:
                statusCard.classList.add('alert-secondary');
                break;
        }
    };

    const setLastRun = (text) => {
        if (!lastRunNode) {
            return;
        }
        lastRunNode.textContent = text;
    };

    const runAutoSync = async () => {
        if (inFlight || document.hidden) {
            return;
        }

        inFlight = true;
        updateStatus('Sincronizando...', 'info');

        const payload = new URLSearchParams();
        payload.set('_token', tokenInput.value);
        payload.set('account_alias', aliasInput ? aliasInput.value : '');

        try {
            const response = await fetch(autoSyncUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                body: payload.toString(),
            });

            const data = await response.json();
            if (!response.ok || !data || data.ok !== true) {
                const errorMessage = (data && data.error) ? data.error : 'No se pudo completar el auto-sync.';
                updateStatus('Con errores', 'danger');
                setLastRun(errorMessage);
                return;
            }

            const created = Number(data.created || 0);
            const skipped = Number(data.skipped || 0);
            const omitted = data.omitted_breakdown || {};
            const now = new Date();
            const nowText = now.toLocaleTimeString();

            if (created > 0) {
                updateStatus('Creados: ' + created + ' ticket(s)', 'success');
                setLastRun('Ultimo ciclo ' + nowText + '. ' + (data.summary || 'Sin resumen.'));
            } else if (skipped > 0) {
                const processed = Number(omitted.ya_procesado || 0);
                updateStatus('Sin nuevos tickets', 'warning');
                setLastRun('Ultimo ciclo ' + nowText + '. Omitidos: ' + skipped + (processed > 0 ? ' (ya procesados: ' + processed + ')' : '') + '.');
            } else {
                updateStatus('Activo', 'secondary');
                setLastRun('Ultimo ciclo ' + nowText + '. Sin correos nuevos.');
            }

            if (Array.isArray(data.errors) && data.errors.length > 0) {
                updateStatus('Con advertencias', 'warning');
                setLastRun('Ultimo ciclo ' + nowText + '. ' + data.errors[0]);
            }
        } catch (error) {
            updateStatus('Con errores', 'danger');
            setLastRun('Fallo de red en auto-sync.');
        } finally {
            inFlight = false;
        }
    };

    tickHandle = window.setInterval(runAutoSync, intervalMs);
    runAutoSync();

    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) {
            runAutoSync();
        }
    });

    window.addEventListener('beforeunload', () => {
        if (tickHandle !== null) {
            window.clearInterval(tickHandle);
        }
    });
});
