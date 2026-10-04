// assets/js/mothership-financials.js
(function($) {
    'use strict';
    $(function() {
        if (typeof gkycFinancialsData === 'undefined' || !$('#gkyc-financials-chart').length) {
            return;
        }

        var financialsCtx = document.getElementById('gkyc-financials-chart').getContext('2d');
        new Chart(financialsCtx, {
            type: 'bar', // Gráfico de barras es mejor para comparar día a día
            data: {
                labels: gkycFinancialsData.labels,
                datasets: [
                    {
                        label: 'Ganancia Neta ($)',
                        data: gkycFinancialsData.ganancia,
                        backgroundColor: 'rgba(40, 167, 69, 0.7)', // Verde
                        borderColor: 'rgba(40, 167, 69, 1)',
                        borderWidth: 1,
                        yAxisID: 'y'
                    },
                    {
                        label: 'Ingresos ($)',
                        data: gkycFinancialsData.ingresos,
                        backgroundColor: 'rgba(23, 162, 184, 0.2)', // Azul claro
                        type: 'line', // Mostrar como línea para no saturar
                        yAxisID: 'y',
                        tension: 0.3
                    },
                    {
                        label: 'Costos ($)',
                        data: gkycFinancialsData.costos,
                        backgroundColor: 'rgba(220, 53, 69, 0.2)', // Rojo claro
                        type: 'line', // Mostrar como línea
                        yAxisID: 'y',
                        tension: 0.3
                    }
                ]
            },
            options: {
                scales: {
                    y: {
                        beginAtZero: true,
                        position: 'left',
                        ticks: {
                            callback: function(value, index, values) {
                                return '$' + value;
                            }
                        }
                    }
                },
                plugins: {
                    legend: {
                        position: 'bottom'
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false
                    }
                },
                responsive: true,
                maintainAspectRatio: true
            }
        });
    });
})(jQuery);