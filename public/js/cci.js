document.addEventListener('DOMContentLoaded', () => {
    const drawSimpleChart = (canvasId, color, fillColor) => {
        const canvas = document.getElementById(canvasId);
        if (!canvas) return;

        const labels = JSON.parse(canvas.getAttribute('data-labels') || '[]');
        const values = JSON.parse(canvas.getAttribute('data-values') || '[]').map((v) => Number(v) || 0);
        if (!Array.isArray(labels) || !Array.isArray(values) || labels.length === 0 || values.length === 0) return;

        const dpr = window.devicePixelRatio || 1;
        const draw = () => {
            const rect = canvas.getBoundingClientRect();
            const width = Math.max(320, Math.floor(rect.width));
            const height = Math.max(180, Math.floor(rect.height || 180));

            canvas.width = Math.floor(width * dpr);
            canvas.height = Math.floor(height * dpr);

            const ctx = canvas.getContext('2d');
            if (!ctx) return;

            ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
            ctx.clearRect(0, 0, width, height);

            const pad = { top: 14, right: 12, bottom: 30, left: 30 };
            const chartW = width - pad.left - pad.right;
            const chartH = height - pad.top - pad.bottom;
            const maxVal = Math.max(5, ...values);

            ctx.strokeStyle = '#e4ecf6';
            for (let i = 0; i <= 4; i++) {
                const y = pad.top + (chartH * i) / 4;
                ctx.beginPath();
                ctx.moveTo(pad.left, y);
                ctx.lineTo(width - pad.right, y);
                ctx.stroke();
            }

            const step = chartW / Math.max(1, values.length - 1);
            const toX = (i) => pad.left + (step * i);
            const toY = (v) => pad.top + chartH - ((v / maxVal) * chartH);

            const gradient = ctx.createLinearGradient(0, pad.top, 0, pad.top + chartH);
            gradient.addColorStop(0, fillColor);
            gradient.addColorStop(1, 'rgba(47,128,237,0.03)');

            ctx.beginPath();
            values.forEach((v, i) => {
                const x = toX(i);
                const y = toY(v);
                if (i === 0) ctx.moveTo(x, y);
                else ctx.lineTo(x, y);
            });
            ctx.lineTo(toX(values.length - 1), pad.top + chartH);
            ctx.lineTo(toX(0), pad.top + chartH);
            ctx.closePath();
            ctx.fillStyle = gradient;
            ctx.fill();

            ctx.beginPath();
            values.forEach((v, i) => {
                const x = toX(i);
                const y = toY(v);
                if (i === 0) ctx.moveTo(x, y);
                else ctx.lineTo(x, y);
            });
            ctx.strokeStyle = color;
            ctx.lineWidth = 2;
            ctx.stroke();

            ctx.fillStyle = color;
            values.forEach((v, i) => {
                ctx.beginPath();
                ctx.arc(toX(i), toY(v), 2.4, 0, Math.PI * 2);
                ctx.fill();
            });

            ctx.fillStyle = '#607891';
            ctx.font = '11px Manrope, sans-serif';
            ctx.textAlign = 'center';
            labels.forEach((label, i) => {
                if (i % Math.ceil(labels.length / 6) !== 0) return;
                ctx.fillText(String(label), toX(i), height - 8);
            });
        };

        draw();
        window.addEventListener('resize', draw);
    };

    drawSimpleChart('cciDailyChart', '#2f80ed', 'rgba(47,128,237,0.25)');
    drawSimpleChart('cciMonthlyChart', '#1da887', 'rgba(29,168,135,0.25)');
    drawSimpleChart('cciReportsDeliveryChart', '#118a69', 'rgba(17,138,105,0.24)');
    drawSimpleChart('cciReportsErrorsChart', '#ca5f1e', 'rgba(202,95,30,0.24)');
});
