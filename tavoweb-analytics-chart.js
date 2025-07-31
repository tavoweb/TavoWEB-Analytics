document.addEventListener('DOMContentLoaded', function () {
    const ctx = document.getElementById('tavowebAnalyticsChart');
    if (ctx && typeof tavoweb_chart_data !== 'undefined') {
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: tavoweb_chart_data.labels,
                datasets: [
                    {
                        label: 'Visitors',
                        data: tavoweb_chart_data.visitors,
                        borderColor: 'rgb(54, 162, 235)',
                        backgroundColor: 'rgba(54, 162, 235, 0.5)',
                        tension: 0.1,
                        yAxisID: 'y',
                    },
                    {
                        label: 'Pageviews',
                        data: tavoweb_chart_data.pageviews,
                        borderColor: 'rgb(255, 99, 132)',
                        backgroundColor: 'rgba(255, 99, 132, 0.5)',
                        tension: 0.1,
                        yAxisID: 'y',
                    }
                ]
            },
            options: {
                responsive: true,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                    }
                }
            }
        });
    }
});
