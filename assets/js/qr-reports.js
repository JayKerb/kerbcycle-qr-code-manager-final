document.addEventListener('DOMContentLoaded', function () {
    if (typeof Chart === 'undefined' || typeof kerbcycleReportData === 'undefined') {
        return;
    }

    const ctx = document.getElementById('qr-report-chart');
    if (!ctx) {
        return;
    }

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: kerbcycleReportData.labels,
            datasets: [{
                label: 'QR Codes Assigned',
                data: kerbcycleReportData.counts,
                backgroundColor: 'rgba(54, 162, 235, 0.5)',
                borderColor: 'rgba(54, 162, 235, 1)',
                borderWidth: 1
            }]
        },
        options: {
            scales: {
                y: {
                    beginAtZero: true,
                    precision: 0
                }
            }
        }
    });
});

