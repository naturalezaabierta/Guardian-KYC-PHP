// assets/js/admin-stats.js

(function($) {
    'use strict';

    $(function() {
        // Nos aseguramos de que los datos y los contenedores existan
        if (typeof gkycStatsData === 'undefined' || !$('#gkyc-daily-chart').length || !$('#gkyc-status-chart').length) {
            return;
        }

        // 1. Configuración del Gráfico de Líneas (Últimos 30 días)
        var dailyCtx = document.getElementById('gkyc-daily-chart').getContext('2d');
        new Chart(dailyCtx, {
            type: 'line',
            data: {
                labels: gkycStatsData.daily.labels, // Las fechas
                datasets: [{
                    label: 'Verificaciones por Día',
                    data: gkycStatsData.daily.data, // El número de verificaciones
                    backgroundColor: 'rgba(34, 113, 177, 0.1)',
                    borderColor: 'rgba(34, 113, 177, 1)',
                    borderWidth: 2,
                    pointBackgroundColor: 'rgba(34, 113, 177, 1)',
                    tension: 0.2
                }]
            },
            options: {
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1 // Asegura que el eje Y cuente de 1 en 1
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });

        // 2. Configuración del Gráfico de Pastel (Desglose por Estado)
        var statusCtx = document.getElementById('gkyc-status-chart').getContext('2d');
        new Chart(statusCtx, {
            type: 'doughnut', // Gráfico de tipo "dona"
            data: {
                labels: gkycStatsData.status.labels, // Ej: 'Aprobadas', 'Rechazadas'
                datasets: [{
                    label: 'Desglose por Estado',
                    data: gkycStatsData.status.data, // El número para cada estado
                    backgroundColor: [
                        '#28a745', // Verde para Aprobadas
                        '#dc3545', // Rojo para Rechazadas
                        '#ffc107', // Amarillo para Declinadas
                        '#17a2b8', // Azul claro para Pendientes
                        '#6c757d'  // Gris para No Verificadas
                    ],
                    hoverOffset: 4
                }]
            },
            options: {
                 plugins: {
                    legend: {
                        position: 'bottom', // Mueve las etiquetas a la parte inferior
                    }
                }
            }
        });
    });

})(jQuery);