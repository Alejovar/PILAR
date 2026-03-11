<?php
// Path: /test/ab_results.php

$file_path = $_SERVER['DOCUMENT_ROOT'] . '/ab_test_results.txt';
$times_A = [];
$times_B = [];

if (file_exists($file_path)) {
    $lines = file($file_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    
    foreach ($lines as $line) {
        $data = explode(',', $line);
        if (count($data) >= 3) {
            $version = trim($data[1]);
            $time = (float)trim($data[2]);
            
            if ($version === 'A') {
                $times_A[] = $time;
            } elseif ($version === 'B') {
                $times_B[] = $time;
            }
        }
    }
}

function calculateAverage($timesArray) {
    $count = count($timesArray);
    if ($count > 0) {
        return round(array_sum($timesArray) / $count, 2);
    }
    return 0;
}

$average_A = calculateAverage($times_A);
$average_B = calculateAverage($times_B);
$total_samples = count($times_A) + count($times_B);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resultados Prueba A/B | KitchenLink</title>
    <link rel="icon" href="/src/images/logos/KitchenLink_logo.png" type="image/png" sizes="32x32">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            background-color: #f4f6f9; 
            color: #333; 
            margin: 0; 
            padding: 20px; 
        }
        .dashboard-container { 
            max-width: 900px; 
            margin: 0 auto; 
            background: white; 
            padding: 40px; 
            border-radius: 12px; 
            box-shadow: 0 10px 25px rgba(0,0,0,0.05); 
        }
        h1 { color: #2c3e50; text-align: center; margin-bottom: 5px; }
        .subtitle { text-align: center; color: #7f8c8d; margin-bottom: 30px; }
        .stats-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 40px; }
        .stat-card { padding: 25px; border-radius: 10px; text-align: center; }
        .stat-title { font-size: 1.1em; margin-bottom: 10px; opacity: 0.9; }
        .stat-value { font-size: 2.5em; font-weight: bold; }
        .card-a { background: #e3f2fd; color: #1565c0; border-left: 5px solid #1565c0; }
        .card-b { background: #e8f5e9; color: #2e7d32; border-left: 5px solid #2e7d32; }
        .chart-container { position: relative; height: 50vh; width: 100%; }
        .footer-info { text-align: center; margin-top: 30px; color: #95a5a6; font-size: 0.9em; }
    </style>
</head>
<body>

<div class="dashboard-container">
    <h1>Dashboard de Métricas - Prueba A/B</h1>
    <p class="subtitle">Evaluación de UX: Tiempo de Navegación "Volver a Mesas"</p>
    
    <div class="stats-grid">
        <div class="stat-card card-a">
            <div class="stat-title">Versión A (Botón Azul Nativo)</div>
            <div class="stat-value"><?php echo $average_A; ?>s</div>
        </div>
        <div class="stat-card card-b">
            <div class="stat-title">Versión B (Estilo Bootstrap)</div>
            <div class="stat-value"><?php echo $average_B; ?>s</div>
        </div>
    </div>

    <div class="chart-container">
        <canvas id="resultsChart"></canvas>
    </div>
    
    <p class="footer-info">Total de interacciones registradas: <?php echo $total_samples; ?> clicks.</p>
</div>

<script>
    const ctx = document.getElementById('resultsChart').getContext('2d');
    const myChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: ['Versión A (Botón Descuadrado)', 'Versión B (Botón Optimizado)'],
            datasets: [{
                label: 'Promedio de segundos antes del clic (Menor es mejor)',
                data: [<?php echo $average_A; ?>, <?php echo $average_B; ?>],
                backgroundColor: [
                    'rgba(21, 101, 192, 0.7)', 
                    'rgba(46, 125, 50, 0.7)'   
                ],
                borderColor: [
                    'rgba(21, 101, 192, 1)',
                    'rgba(46, 125, 50, 1)'
                ],
                borderWidth: 2,
                borderRadius: 5
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    title: { display: true, text: 'Tiempo en Segundos' }
                }
            },
            plugins: { legend: { display: false } }
        }
    });
</script>

</body>
</html>
