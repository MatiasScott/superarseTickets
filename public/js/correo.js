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

document.addEventListener('DOMContentLoaded', () => {
    const panel = document.querySelector('[data-chat-dashboard] .chat-chart-panel');
    const canvas = document.getElementById('chatIncomingChart');
    if (!panel || !canvas) {
        return;
    }

    const parseSeries = (raw) => {
        try {
            const value = JSON.parse(raw || '[]');
            if (!Array.isArray(value)) {
                return [];
            }
            return value.map((item) => Number(item) || 0);
        } catch (error) {
            return [];
        }
    };

    const today = parseSeries(panel.getAttribute('data-today-series'));
    const lastWeek = parseSeries(panel.getAttribute('data-last-week-series'));
    if (today.length === 0 || lastWeek.length === 0) {
        return;
    }

    const dpr = window.devicePixelRatio || 1;
    const draw = () => {
        const rect = canvas.getBoundingClientRect();
        const width = Math.max(320, Math.floor(rect.width));
        const height = Math.max(210, Math.floor(rect.height || 220));
        canvas.width = Math.floor(width * dpr);
        canvas.height = Math.floor(height * dpr);

        const ctx = canvas.getContext('2d');
        if (!ctx) {
            return;
        }

        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
        ctx.clearRect(0, 0, width, height);

        const padding = { top: 14, right: 16, bottom: 26, left: 40 };
        const chartWidth = width - padding.left - padding.right;
        const chartHeight = height - padding.top - padding.bottom;
        const maxValue = Math.max(10, ...today, ...lastWeek);

        const toX = (index, total) => padding.left + (chartWidth * index) / Math.max(1, total - 1);
        const toY = (value) => padding.top + chartHeight - ((value / maxValue) * chartHeight);

        ctx.strokeStyle = '#e5edf6';
        ctx.lineWidth = 1;
        for (let i = 0; i <= 5; i++) {
            const y = padding.top + (chartHeight * i) / 5;
            ctx.beginPath();
            ctx.moveTo(padding.left, y);
            ctx.lineTo(width - padding.right, y);
            ctx.stroke();
        }

        ctx.fillStyle = '#6a8198';
        ctx.font = '11px Manrope, sans-serif';
        ctx.textAlign = 'right';
        for (let i = 0; i <= 5; i++) {
            const value = Math.round(maxValue - (maxValue * i) / 5);
            const y = padding.top + (chartHeight * i) / 5 + 4;
            ctx.fillText(String(value), padding.left - 8, y);
        }

        const drawSeries = (series, color, fillColor) => {
            if (series.length < 2) {
                return;
            }

            if (fillColor) {
                const gradient = ctx.createLinearGradient(0, padding.top, 0, padding.top + chartHeight);
                gradient.addColorStop(0, fillColor);
                gradient.addColorStop(1, 'rgba(236, 138, 35, 0.03)');
                ctx.beginPath();
                series.forEach((value, index) => {
                    const x = toX(index, series.length);
                    const y = toY(value);
                    if (index === 0) {
                        ctx.moveTo(x, y);
                    } else {
                        ctx.lineTo(x, y);
                    }
                });
                ctx.lineTo(toX(series.length - 1, series.length), padding.top + chartHeight);
                ctx.lineTo(toX(0, series.length), padding.top + chartHeight);
                ctx.closePath();
                ctx.fillStyle = gradient;
                ctx.fill();
            }

            ctx.beginPath();
            series.forEach((value, index) => {
                const x = toX(index, series.length);
                const y = toY(value);
                if (index === 0) {
                    ctx.moveTo(x, y);
                } else {
                    ctx.lineTo(x, y);
                }
            });

            ctx.strokeStyle = color;
            ctx.lineWidth = 2;
            ctx.stroke();

            ctx.fillStyle = color;
            series.forEach((value, index) => {
                if (index % 3 !== 0 && index !== series.length - 1) {
                    return;
                }
                const x = toX(index, series.length);
                const y = toY(value);
                ctx.beginPath();
                ctx.arc(x, y, 2.2, 0, Math.PI * 2);
                ctx.fill();
            });
        };

        drawSeries(lastWeek, '#ec8a23', 'rgba(236, 138, 35, 0.24)');
        drawSeries(today, '#38baa4');

        ctx.fillStyle = '#6a8198';
        ctx.textAlign = 'center';
        const marks = ['02:00', '06:00', '10:00', '14:00', '18:00', '22:00'];
        marks.forEach((label, i) => {
            const x = padding.left + (chartWidth * i) / (marks.length - 1);
            ctx.fillText(label, x, height - 8);
        });
    };

    draw();
    window.addEventListener('resize', draw);
});
