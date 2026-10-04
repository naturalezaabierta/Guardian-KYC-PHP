// assets/js/mothership-stats.js
(function($) {
    'use strict';
    $(function() {
        if (typeof gkycMothershipStatsData === 'undefined') {
            return;
        }

        // Gráfico 1: Distribución por Plan (Pastel)
        if ($('#gkyc-plans-chart').length && gkycMothershipStatsData.plan_data.length > 0) {
            var plansCtx = document.getElementById('gkyc-plans-chart').getContext('2d');
            new Chart(plansCtx, {
                type: 'doughnut',
                data: {
                    labels: gkycMothershipStatsData.plan_labels,
                    datasets: [{
                        label: 'Clientes', data: gkycMothershipStatsData.plan_data,
                        backgroundColor: ['#36A2EB','#FF6384','#4BC0C0','#FFCD56','#9966FF','#FF9F40'],
                        hoverOffset: 4
                    }]
                },
                options: { plugins: { legend: { position: 'bottom' } } }
            });
        }

        // Gráfico 2: Ingresos por Mes (Barras)
        if ($('#gkyc-revenue-chart').length && gkycMothershipStatsData.revenue_data.length > 0) {
            var revenueCtx = document.getElementById('gkyc-revenue-chart').getContext('2d');
            new Chart(revenueCtx, {
                type: 'bar',
                data: {
                    labels: gkycMothershipStatsData.revenue_labels,
                    datasets: [{
                        label: 'Ingresos Mensuales ($)',
                        data: gkycMothershipStatsData.revenue_data,
                        backgroundColor: 'rgba(40, 167, 69, 0.7)',
                        borderColor: 'rgba(40, 167, 69, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    scales: { y: { beginAtZero: true } },
                    plugins: { legend: { display: false } }
                }
            });
        }
    });
})(jQuery);