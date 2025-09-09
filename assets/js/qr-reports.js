document.addEventListener('DOMContentLoaded', function () {
    if (typeof Chart === 'undefined' || typeof kerbcycleReportData === 'undefined') {
        return;
    }

    const weeklyCtx = document.getElementById('qr-report-chart');
    const dailyCtx = document.getElementById('qr-daily-chart');
    let weeklyChart;
    let dailyChart;

    function renderCharts(data) {
        if (weeklyCtx) {
            if (!weeklyChart) {
                weeklyChart = new Chart(weeklyCtx, {
                    type: 'bar',
                    data: {
                        labels: data.labels,
                        datasets: [{
                            label: 'QR Codes Assigned',
                            data: data.counts,
                            backgroundColor: 'rgba(54, 162, 235, 0.5)',
                            borderColor: 'rgba(54, 162, 235, 1)',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        scales: {
                            y: { beginAtZero: true, precision: 0 }
                        }
                    }
                });
            } else {
                weeklyChart.data.labels = data.labels;
                weeklyChart.data.datasets[0].data = data.counts;
                weeklyChart.update();
            }
        }

        if (dailyCtx) {
            if (!dailyChart) {
                dailyChart = new Chart(dailyCtx, {
                    type: 'line',
                    data: {
                        labels: data.daily_labels,
                        datasets: [{
                            label: 'Assignments Today',
                            data: data.daily_counts,
                            backgroundColor: 'rgba(255, 99, 132, 0.5)',
                            borderColor: 'rgba(255, 99, 132, 1)',
                            borderWidth: 1,
                            fill: true
                        }]
                    },
                    options: {
                        scales: {
                            y: { beginAtZero: true, precision: 0 }
                        }
                    }
                });
            } else {
                dailyChart.data.labels = data.daily_labels;
                dailyChart.data.datasets[0].data = data.daily_counts;
                dailyChart.update();
            }
        }
    }

    function refreshCharts() {
        const body = new URLSearchParams();
        body.append('action', 'kerbcycle_qr_report_data');
        body.append('security', kerbcycleReportData.nonce);

        fetch(kerbcycleReportData.ajax_url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            body: body.toString(),
        })
            .then(res => {
                if (!res.ok) {
                    throw new Error('Network response was not ok');
                }
                return res.json();
            })
            .then(data => {
                renderCharts(data);
            })
            .catch(err => console.error('Failed to load report data', err));
    }

    renderCharts(kerbcycleReportData);
    window.kerbcycleRefreshReports = refreshCharts;

    window.addEventListener('storage', function (e) {
        if (e.key === 'kerbcycleAssignment') {
            refreshCharts();
        }
    });
});

