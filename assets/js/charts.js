document.addEventListener('DOMContentLoaded', function () {
    const svgNs = 'http://www.w3.org/2000/svg';

    document.querySelectorAll('[data-bar-chart]').forEach(function (host) {
        let items = [];
        try {
            items = JSON.parse(host.getAttribute('data-bar-chart') || '[]');
        } catch (err) {
            return;
        }
        if (!Array.isArray(items) || items.length === 0) {
            return;
        }

        const maxVal = Math.max(1, items.reduce(function (acc, item) {
            return Math.max(acc, Number(item.value) || 0);
        }, 0));

        const width = 480;
        const height = 185;
        const topPad = 24;
        const bottomPad = 38;
        const chartHeight = height - topPad - bottomPad;
        const step = width / items.length;
        const barWidth = Math.min(38, step * 0.54);

        const svg = document.createElementNS(svgNs, 'svg');
        svg.setAttribute('viewBox', '0 0 ' + width + ' ' + height);
        svg.setAttribute('class', 'clinic-bar-svg');
        svg.setAttribute('role', 'img');
        svg.setAttribute('aria-label', 'Grafiku javor i termineve');

        items.forEach(function (item, idx) {
            const val = Number(item.value) || 0;
            const barH = Math.max(val > 0 ? 10 : 4, Math.round((val / maxVal) * chartHeight));
            const x = Math.round(idx * step + (step - barWidth) / 2);
            const y = topPad + chartHeight - barH;
            const centerX = Math.round(idx * step + step / 2);

            const rect = document.createElementNS(svgNs, 'rect');
            rect.setAttribute('x', String(x));
            rect.setAttribute('y', String(y));
            rect.setAttribute('width', String(barWidth));
            rect.setAttribute('height', String(barH));
            rect.setAttribute('rx', '6');
            rect.setAttribute('class', item.today ? 'clinic-bar-rect is-today' : 'clinic-bar-rect');
            svg.appendChild(rect);

            const valText = document.createElementNS(svgNs, 'text');
            valText.setAttribute('x', String(centerX));
            valText.setAttribute('y', String(y - 6));
            valText.setAttribute('text-anchor', 'middle');
            valText.setAttribute('class', 'clinic-bar-val');
            valText.textContent = String(val);
            svg.appendChild(valText);

            const labelText = document.createElementNS(svgNs, 'text');
            labelText.setAttribute('x', String(centerX));
            labelText.setAttribute('y', String(height - 18));
            labelText.setAttribute('text-anchor', 'middle');
            labelText.setAttribute('class', 'clinic-bar-label');
            labelText.textContent = String(item.label || '');
            svg.appendChild(labelText);

            const subText = document.createElementNS(svgNs, 'text');
            subText.setAttribute('x', String(centerX));
            subText.setAttribute('y', String(height - 4));
            subText.setAttribute('text-anchor', 'middle');
            subText.setAttribute('class', 'clinic-bar-sub');
            subText.textContent = String(item.sublabel || '');
            svg.appendChild(subText);
        });

        host.innerHTML = '';
        host.appendChild(svg);
    });

    document.querySelectorAll('[data-status-chart]').forEach(function (host) {
        let items = [];
        try {
            items = JSON.parse(host.getAttribute('data-status-chart') || '[]');
        } catch (err) {
            return;
        }
        if (!Array.isArray(items) || items.length === 0) {
            return;
        }

        const total = Math.max(1, items.reduce(function (sum, item) {
            return sum + (Number(item.value) || 0);
        }, 0));

        const wrapper = document.createElement('div');
        wrapper.className = 'status-breakdown';

        items.forEach(function (item) {
            const val = Number(item.value) || 0;
            const pct = Math.round((val / total) * 100);

            const row = document.createElement('div');
            const head = document.createElement('div');
            head.className = 'status-row-head';

            const nameSpan = document.createElement('span');
            nameSpan.textContent = String(item.label || '');

            const countSpan = document.createElement('span');
            countSpan.className = 'tabular-nums';
            countSpan.textContent = val + ' (' + pct + '%)';

            head.appendChild(nameSpan);
            head.appendChild(countSpan);

            const track = document.createElement('div');
            track.className = 'status-track';

            const barSvg = document.createElementNS(svgNs, 'svg');
            barSvg.setAttribute('viewBox', '0 0 100 10');
            barSvg.setAttribute('preserveAspectRatio', 'none');
            barSvg.setAttribute('class', 'status-bar-svg');

            const fillRect = document.createElementNS(svgNs, 'rect');
            fillRect.setAttribute('x', '0');
            fillRect.setAttribute('y', '0');
            fillRect.setAttribute('height', '10');
            fillRect.setAttribute('width', String(pct));
            fillRect.setAttribute('class', 'tone-' + String(item.tone || 'teal'));
            barSvg.appendChild(fillRect);

            track.appendChild(barSvg);
            row.appendChild(head);
            row.appendChild(track);
            wrapper.appendChild(row);
        });

        host.innerHTML = '';
        host.appendChild(wrapper);
    });
});
