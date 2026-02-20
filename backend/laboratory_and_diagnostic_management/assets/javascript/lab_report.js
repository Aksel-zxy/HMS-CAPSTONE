let lineChartInstance = null;
let donutChartInstance = null;

window.initCharts = function(data) {
    const lineLabels = data.lineLabels || [];
    const lineData = data.lineData || [];
    const donutLabels = data.donutLabels || [];
    const donutData = data.donutData || [];

    const lineCanvas = document.getElementById('lineChart');
    if (lineCanvas) {
        if (lineChartInstance) {
            lineChartInstance.destroy();
        }
        const ctxLine = lineCanvas.getContext('2d');
        lineChartInstance = new Chart(ctxLine, {
            type: 'line',
            data: {
                labels: lineLabels.length ? lineLabels : ['No Data'],
                datasets: [{
                    label: 'Tests Performed',
                    data: lineData.length ? lineData : [0],
                    borderColor: '#0d6efd',
                    backgroundColor: 'rgba(13, 110, 253, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { grid: { display: false } },
                    y: { beginAtZero: true, ticks: { precision: 0 } }
                }
            }
        });
    }

    const donutCanvas = document.getElementById('donutChart');
    if (donutCanvas) {
        if (donutChartInstance) {
            donutChartInstance.destroy();
        }
        const ctxDonut = donutCanvas.getContext('2d');
        donutChartInstance = new Chart(ctxDonut, {
            type: 'doughnut',
            data: {
                labels: donutLabels.length ? donutLabels : ['No Data'],
                datasets: [{
                    data: donutData.length ? donutData : [1],
                    backgroundColor: [
                        '#0d47a1', 
                        '#1976d2', 
                        '#42a5f5', 
                        '#90caf9', 
                        '#cfd8dc'  
                    ],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '60%',
                plugins: {
                    legend: {
                        position: 'right',
                        labels: { boxWidth: 12, usePointStyle: true }
                    }
                }
            }
        });
    }
};

document.addEventListener("DOMContentLoaded", function() {
    const toggler = document.querySelector(".toggler-btn");
    if (toggler) {
        toggler.addEventListener("click", function() {
            document.querySelector("#sidebar").classList.toggle("collapsed");
        });
    }

    if (window.dashboardData) {
        window.initCharts(window.dashboardData);
    }
});