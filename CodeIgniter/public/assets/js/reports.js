(() => {
    const source = document.getElementById('report-chart-data');
    if (!source || typeof Chart === 'undefined') return;
    const data = JSON.parse(source.textContent);
    Chart.defaults.font.family = 'Inter, sans-serif';
    const colors = ['#276bd2', '#9096a2', '#d99a26', '#198754', '#d71920'];
    const baseOptions = {
        responsive: true,
        maintainAspectRatio: false,
        animation: false,
        plugins: {
            legend: { position: 'bottom', labels: { color: '#575e6b', boxWidth: 10, boxHeight: 10, padding: 16, font: { size: 11 } } },
            tooltip: { callbacks: { label: context => `${context.dataset.label || context.label}: ${context.parsed.x ?? context.parsed}` } }
        }
    };
    const draw = (key, config) => {
        const viewport = document.querySelector(`[data-chart-viewport="${key}"]`);
        viewport.hidden = false;
        try {
            new Chart(document.getElementById(`chart-${key}`), config);
            document.getElementById(`chart-${key}-data`).open = false;
        } catch (error) {
            viewport.hidden = true;
            // The server-rendered values remain available even if a chart cannot render.
            document.getElementById(`chart-${key}-data`).open = true;
            console.error('Não foi possível renderizar o gráfico do relatório.', error);
        }
    };
    ['states', 'results'].forEach(key => {
        if (!data[key].values.some(value => value > 0)) return;
        draw(key, {
            type: 'doughnut',
            data: { labels: data[key].labels, datasets: [{ data: data[key].values, backgroundColor: key === 'states' ? colors : ['#198754', '#d99a26', '#d71920', '#9096a2'], borderWidth: 2, borderColor: '#fff' }] },
            options: { ...baseOptions, cutout: '65%' }
        });
    });
    if (data.owners.selected.some(value => value > 0)) {
        const canvasParent = document.querySelector('[data-chart-viewport="owners"] .report-chart-canvas');
        canvasParent.style.height = `${Math.max(280, data.owners.labels.length * 52 + 60)}px`;
        draw('owners', {
            type: 'bar',
            data: {
                labels: data.owners.labels,
                datasets: [
                    { label: 'Selecionadas', data: data.owners.selected, backgroundColor: '#276bd2', borderRadius: 3 },
                    { label: 'Encerradas', data: data.owners.closed, backgroundColor: '#198754', borderRadius: 3 }
                ]
            },
            options: {
                ...baseOptions, indexAxis: 'y',
                scales: {
                    x: { beginAtZero: true, ticks: { precision: 0, color: '#687080' }, grid: { color: '#eef0f3' }, border: { display: false } },
                    y: { ticks: { color: '#575e6b', font: { size: 11 }, callback: function (value) { const label = this.getLabelForValue(value); return label.length > 24 ? label.slice(0, 23) + '…' : label; } }, grid: { display: false }, border: { display: false } }
                }
            }
        });
    }
})();
