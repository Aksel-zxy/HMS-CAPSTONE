document.addEventListener("DOMContentLoaded", function() {
    
    // --- Sidebar Toggler ---
    const toggler = document.querySelector(".toggler-btn");
    if (toggler) {
        toggler.addEventListener("click", function() {
            document.querySelector("#sidebar").classList.toggle("collapsed");
        });
    }

    // --- Retrieve Data from PHP Bridge ---
    // We use a default empty array [] if the data is missing to prevent errors
    const data = window.dashboardData || {};
    const lineLabels = data.lineLabels || [];
    const lineData = data.lineData || [];
    const donutLabels = data.donutLabels || [];
    const donutData = data.donutData || [];

    // --- Chart 1: Line Chart (Tests Performed) ---
    const lineCanvas = document.getElementById('lineChart');
    if (lineCanvas) {
        const ctxLine = lineCanvas.getContext('2d');
        new Chart(ctxLine, {
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

    // --- Chart 2: Donut Chart (Test Breakdown) ---
    const donutCanvas = document.getElementById('donutChart');
    if (donutCanvas) {
        const ctxDonut = donutCanvas.getContext('2d');
        new Chart(ctxDonut, {
            type: 'doughnut',
            data: {
                labels: donutLabels.length ? donutLabels : ['No Data'],
                datasets: [{
                    data: donutData.length ? donutData : [1],
                    backgroundColor: [
                        '#0d47a1', // Dark Blue
                        '#1976d2', 
                        '#42a5f5', 
                        '#90caf9', 
                        '#cfd8dc'  // Grey
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
});