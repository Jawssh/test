<?php
session_start();
require_once 'config.php'; // Ensure this path is correct to your DB config

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit;
}

// Fetch overall statistics for the top cards
$sql_stats = "SELECT
                SUM(TotalHouseholds) AS total_households,
                SUM(TotalBeneficiaries) AS total_beneficiaries,
                AVG(Average_Income) AS average_income,
                SUM(PWD) AS total_pwd
            FROM barangay";

$result_stats = $conn->query($sql_stats);
$stats = $result_stats && $result_stats->num_rows > 0 ? $result_stats->fetch_assoc() : [
    'total_households' => 0,
    'total_beneficiaries' => 0,
    'average_income' => 0,
    'total_pwd' => 0
];

$totalPWD = $stats['total_pwd'];
$totalBeneficiaries = $stats['total_beneficiaries'];
$percentage = ($totalBeneficiaries > 0) ? ($totalPWD / $totalBeneficiaries) * 100 : 0;

// Query to get top priority barangays
$sql_top_barangays = "SELECT BarangayName, SES
                      FROM barangay
                      ORDER BY SES DESC
                      LIMIT 30";
$result_top_barangays = $conn->query($sql_top_barangays);
$top_barangays = [];
if ($result_top_barangays && $result_top_barangays->num_rows > 0) {
    while ($row = $result_top_barangays->fetch_assoc()) {
        $top_barangays[] = $row;
    }
}

// Fetch distinct barangay names for the dropdown
$barangay_names = [];
$query_barangays = "SELECT DISTINCT BarangayName FROM barangay";
$result_barangays = mysqli_query($conn, $query_barangays);
if ($result_barangays) {
    while ($row = mysqli_fetch_assoc($result_barangays)) {
        $barangay_names[] = $row['BarangayName'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Dashboard | Mapping Hope</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="../png/logo.png" type="image/x-icon" />
    <link rel="stylesheet" href="../css/navbar.css">
    <link rel="stylesheet" href="../css/home.css">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
        integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2"></script>
</head>

<body>

    <?php include 'navbar.php'; ?>

    <section class="home">
        <div class="text">

            <h3>Dashboard</h3>
            <span class="profession">Analytics and Overall Visualisation of Data</span>
        </div>
        <div class="dropdown-container">
            <select id="barangayDropdown">
                <option value="all">All Barangays</option>
                <?php foreach ($barangay_names as $name): ?>
                    <option value="<?php echo htmlspecialchars($name); ?>"><?php echo htmlspecialchars($name); ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="container1">
            <div class="card1">
                <h3> Total Households<i class='bx bx-clinic'></i></h3>
                <p id="totalHousehold"><?php echo number_format($stats['total_households']); ?></p>
            </div>
            <div class="card1">
                <h3> Total Beneficiaries<i class='bx bx-body'></i></h3>
                <p id="totalBenef"><?php echo number_format($stats['total_beneficiaries']); ?></p>
            </div>
            <div class="card2">
                <h3>Total PWDs<i class='bx bx-handicap'></i></h3>
                <div class="label">
                    <span id="pwdCount"><?php echo number_format($totalPWD); ?></span><span><?php echo number_format($totalBeneficiaries); ?></span>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: <?php echo number_format($percentage, 2); ?>%;"></div>
                </div>
            </div>
        </div>

        <div class="category-continer">
            <h5>Socioeconomic Status</h5>
        </div>
        <div class="container2">
            <div class="circle-chart-container">
                <h3>Education Attainment</h3>
                <div class="chart-wrapper">
                    <canvas id="educationPieChart"></canvas>
                    <div id="educationLegend" class="chart-legend"></div>
                </div>
            </div>
            <div class="circle-chart-container">
                <h3>Occupation</h3>
                <div class="chart-wrapper">
                    <canvas id="occupationChart"></canvas>
                    <div id="occupationLegend" class="chart-legend"></div>
                </div>
            </div>
            <div class="circle-chart-container">
                <h3>Income</h3>
                <div class="chart-wrapper">
                    <canvas id="incomeDoughnutChart"></canvas>
                    <div id="incomeLegend" class="chart-legend"></div>
                </div>
            </div>
            <div class="circle-chart-container">
                <h3>Health</h3>
                <div class="chart-wrapper">
                    <canvas id="healthPolarChart"></canvas>
                    <div id="healthLegend" class="chart-legend"></div>
                </div>
            </div>
        </div>

        <div class="category-continer">
            <h5>Socioeconomic Score</h5>
        </div>
        <div class="container3">
            <div class="left-container3">
                <h3>Barangay SES Score</h3>
                <div class="linechart-container">
                    <canvas class="bar-chart" id="sesBarChart"></canvas>
                </div>
            </div>
            <div class="right-container3">
                <h3>Top Priority Barangays</h3>
                <?php if (!empty($top_barangays)): ?>
                    <table class="top-priority-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>BRGY</th>
                                <th>SES Score</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($top_barangays as $index => $barangay): ?>
                                <tr>
                                    <td><?php echo $index + 1; ?></td>
                                    <td><?php echo htmlspecialchars($barangay['BarangayName']); ?></td>
                                    <td><?php echo number_format(floatval($barangay['SES']), 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>No barangay data available to display.</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="category-continer">
            <h5>Gender Percentage and Minimap</h5>
        </div>
        <div class="container4">
            <div class="left-container4">
                <h3>Gender Chart</h3>
                <div class="gender-container">
                    <div class="gender-female">
                        <h4>Female</h4>
                        <i class='bx bx-female'></i>
                    </div>
                    <div class="gender-chart">
                        <canvas id="genderPieChart"></canvas>
                    </div>
                    <div class="gender-male">
                        <h4>Male</h4>
                        <i class='bx bx-male'></i>
                    </div>
                </div>
            </div>
            <div class="right-container4">
                <div class="minimap-wrapper">
                    <div id="dashboard-minimap-container"></div>
                    <a href="map.php" class="view-map-button">View Full Map</a>
                </div>
            </div>
        </div>
        <div class="space"></div>


    </section>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
        integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Counter animations for top cards
            function animateCounter(elementId, targetValue) {
                const counter = document.getElementById(elementId);
                if (!counter) return;

                const duration = 1000; // milliseconds
                const start = performance.now();
                const initialValue = parseFloat(counter.textContent.replace(/,/g, '')) || 0; // Handle initial formatted numbers

                function updateCounter(timestamp) {
                    const elapsed = timestamp - start;
                    const progress = Math.min(elapsed / duration, 1);
                    const currentValue = initialValue + (targetValue - initialValue) * progress;
                    counter.textContent = Math.floor(currentValue).toLocaleString();

                    if (progress < 1) {
                        requestAnimationFrame(updateCounter);
                    } else {
                        counter.textContent = targetValue.toLocaleString();
                    }
                }
                requestAnimationFrame(updateCounter);
            }

            animateCounter("totalHousehold", <?php echo json_encode($stats['total_households']); ?>);
            animateCounter("totalBenef", <?php echo json_encode($stats['total_beneficiaries']); ?>);
            animateCounter("pwdCount", <?php echo json_encode($totalPWD); ?>);

            // Barangay Dropdown functionality
            document.getElementById('barangayDropdown').addEventListener('change', function() {
                const barangay = this.value;
                fetch('get_barangay_stats.php?barangay=' + encodeURIComponent(barangay))
                    .then(res => res.json())
                    .then(data => {
                        document.getElementById('totalHousehold').textContent = data.TotalHouseholds.toLocaleString();
                        document.getElementById('totalBenef').textContent = data.TotalBeneficiaries.toLocaleString();
                        document.getElementById('pwdCount').textContent = data.PWD.toLocaleString();

                        const percentage = data.TotalBeneficiaries > 0 ?
                            (data.PWD / data.TotalBeneficiaries) * 100 : 0;
                        document.querySelector('.progress-fill').style.width = percentage.toFixed(2) + '%';
                    })
                    .catch(err => console.error('Fetch error for barangay stats:', err));
            });

            // --- CHARTING FUNCTIONS ---
            Chart.register(ChartDataLabels); // Register ChartDataLabels globally

            function generateColors(num) {
                const baseColors = ['#695CFE', '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#FF9F40', '#9966FF', '#C9CBCF', '#FFD700', '#ADFF2F'];
                let colors = [];
                for (let i = 0; i < num; i++) {
                    colors.push(baseColors[i % baseColors.length]);
                }
                return colors;
            }

            function createChart(canvasId, chartType, chartData, legendId, options = {}) {
                const canvasElement = document.getElementById(canvasId);
                if (!canvasElement) {
                    console.error(`Canvas element with ID ${canvasId} not found.`);
                    return null;
                }
                const ctx = canvasElement.getContext('2d');

                const defaultOptions = {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        datalabels: {
                            color: 'black',
                            font: {
                                size: 12
                            },
                            formatter: (value, context) => {
                                const total = context.chart.data.datasets[0].data.reduce((a, b) => a + b, 0);
                                const percentage = ((value / total) * 100).toFixed(1);
                                return `${percentage}%`;
                            }
                        }
                    }
                };

                const mergedOptions = {
                    ...defaultOptions,
                    ...options
                };

                const chart = new Chart(ctx, {
                    type: chartType,
                    data: chartData,
                    options: mergedOptions,
                    plugins: [ChartDataLabels]
                });

                const legendContainer = document.getElementById(legendId);
                if (legendContainer && chartData.labels) {
                    legendContainer.innerHTML = chartData.labels.map((label, index) =>
                        `<div class="legend-item">
                            <span class="legend-color-box" style="background-color:${chartData.datasets[0].backgroundColor[index]}"></span>
                            ${label}
                        </div>`
                    ).join('');
                }
                return chart;
            }

            // Fetch and render Income Doughnut Chart
            fetch('get_income_data.php')
                .then(response => response.json())
                .then(data => {
                    const labels = data.map(item => item.label);
                    const values = data.map(item => item.value);
                    const chartData = {
                        labels: labels,
                        datasets: [{
                            data: values,
                            backgroundColor: generateColors(labels.length) // Use dynamic colors
                        }]
                    };
                    createChart('incomeDoughnutChart', 'doughnut', chartData, 'incomeLegend');
                })
                .catch(error => console.error('Error fetching income data:', error));

            // Fetch and render Education Pie Chart
            fetch('dashboard.php?type=education') // Assuming this endpoint returns education data
                .then(response => response.json())
                .then(data => {
                    if (data.error) return console.error(data.error);
                    const labels = data.map(item => item.label);
                    const values = data.map(item => item.value);
                    const chartData = {
                        labels: labels,
                        datasets: [{

                            data: values,
                            backgroundColor: generateColors(labels.length)
                        }]
                    };
                    createChart('educationPieChart', 'pie', chartData, 'educationLegend', {
                        plugins: {


                            legend: {
                                display: false,
                                position: 'bottom',
                            },
                            datalabels: {
                                color: 'black',
                                font: {
                                    size: 12
                                },
                                formatter: (value) => value // Display raw value for education
                            }
                        }
                    });
                })
                .catch(error => console.error('Error fetching education data:', error));

            // Fetch and render Occupation Doughnut Chart
            fetch('dashboard.php?type=occupation') // Assuming this endpoint returns occupation data
                .then(response => response.json())
                .then(data => {
                    if (data.error) return console.error(data.error);
                    const labels = data.map(item => item.label);
                    const values = data.map(item => item.value);
                    const chartData = {
                        labels: labels,
                        datasets: [{
                            data: values,
                            backgroundColor: generateColors(labels.length)
                        }]
                    };
                    createChart('occupationChart', 'doughnut', chartData, 'occupationLegend');
                })
                .catch(error => console.error('Error fetching occupation data:', error));

            // Fetch and render Health Polar Area Chart
            fetch('get_health_data.php')
                .then(response => response.json())
                .then(data => {
                    const labels = data.map(item => item.label);
                    const values = data.map(item => item.value);
                    const chartData = {
                        labels: labels,
                        datasets: [{
                            data: values,
                            backgroundColor: generateColors(labels.length)
                        }]
                    };
                    createChart('healthPolarChart', 'pie', chartData, 'healthLegend');
                })
                .catch(error => console.error('Error fetching health chart data:', error));

            // Fetch and render Gender Pie Chart
            fetch('get_gender_data.php')
                .then(response => response.json())
                .then(data => {
                    const total = data.reduce((sum, item) => sum + item.value, 0);
                    const chartData = {
                        labels: data.map(item => item.label),
                        datasets: [{
                            data: data.map(item => item.value),
                            backgroundColor: ['#36A2EB', '#FF6384'] // Specific colors for gender
                        }]
                    };
                    createChart('genderPieChart', 'pie', chartData, null, { // No legend for gender chart
                        plugins: {
                            legend: {
                                display: false,
                                position: 'bottom',
                            },
                            datalabels: {
                                color: 'black',
                                font: {
                                    size: 16
                                },
                                formatter: (value) => {
                                    const percent = ((value / total) * 100).toFixed(1);
                                    return percent + '%';
                                }
                            }
                        }
                    });
                })
                .catch(err => console.error("Error loading gender chart data:", err));

            // Fetch and render SES Line Chart
            fetch('get_ses_score_data.php')
                .then(response => response.ok ? response.json() : Promise.reject(`SES Line Chart Error: ${response.status}`))
                .then(data => {
                    const labels = data.map(item => item.label);
                    const counts = data.map(item => item.count);
                    const canvasElement = document.getElementById('sesBarChart');
                    if (!canvasElement) {
                        console.error("Canvas for SES Line Chart not found.");
                        return;
                    }
                    const ctx = canvasElement.getContext('2d');
                    new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: labels,
                            datasets: [{
                                label: 'Number of Barangays',
                                data: counts,
                                backgroundColor: 'rgba(105, 92, 254, 0.2)',
                                borderColor: '#695CFE',
                                borderWidth: 2,
                                fill: true,
                                tension: 0.1
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    suggestedMax: Math.max(...counts) > 0 ? Math.max(...counts) + Math.ceil(Math.max(...counts) * 0.1) : 10
                                }
                            },
                            plugins: {
                                legend: {
                                    display: true,
                                    position: 'bottom',
                                }
                            }
                        }
                    });
                })
                .catch(error => console.error('Error fetching SES line chart data:', error));

            // --- MINIMAP SCRIPTING ---
            function getSESColor(levelName) {
                switch (levelName) {
                    case "Very Low":
                        return '#ffe135';
                    case "Low":
                        return '#FEB24C';
                    case "Medium":
                        return '#FD8D3C';
                    case "High":
                        return '#FC4E2A';
                    case "Very High":
                        return '#BD0026';
                    default:
                        return '#CCCCCC';
                }
            }

            function minimapHeatStyle(feature) {
                return {
                    fillColor: getSESColor(feature.properties.SES_Level),
                    weight: 0.5,
                    opacity: 1,
                    color: 'white',
                    dashArray: '2',
                    fillOpacity: 0.65
                };
            }

            let dashboardMinimap;
            let minimapGeojsonLayer;

            async function loadMinimapData() {
                if (!dashboardMinimap) {
                    console.error("Minimap not initialized.");
                    return;
                }
                try {
                    const response = await fetch('fetch_map_data.php');
                    if (!response.ok) {
                        throw new Error(`HTTP error for minimap data! Status: ${response.status}`);
                    }
                    const geojsonData = await response.json();

                    if (geojsonData.error) {
                        console.error('Error from fetch_map_data.php:', geojsonData.error);
                        document.getElementById('dashboard-minimap-container').innerHTML = `<p class="map-error-message">Could not load map data: ${geojsonData.error}</p>`;
                        return;
                    }
                    if (!geojsonData.features || geojsonData.features.length === 0) {
                        console.warn('Minimap GeoJSON data missing or empty.');
                        document.getElementById('dashboard-minimap-container').innerHTML = `<p class="map-error-message">No map features to display.</p>`;
                        dashboardMinimap.setView([14.3030, 120.7920], 10);
                        return;
                    }

                    if (minimapGeojsonLayer && dashboardMinimap.hasLayer(minimapGeojsonLayer)) {
                        dashboardMinimap.removeLayer(minimapGeojsonLayer);
                    }

                    minimapGeojsonLayer = L.geoJson(geojsonData.features, {
                        style: minimapHeatStyle,
                        onEachFeature: function(feature, layer) {
                            if (feature.properties && feature.properties.BarangayName) {
                                let popupContent = `<b>${feature.properties.BarangayName}</b>`;
                                if (feature.properties.SES_Level) {
                                    popupContent += `<br>SES Level: ${feature.properties.SES_Level}`;
                                }
                                layer.bindTooltip(popupContent);
                            }
                        }
                    }).addTo(dashboardMinimap);

                    if (minimapGeojsonLayer.getBounds().isValid()) {
                        dashboardMinimap.fitBounds(minimapGeojsonLayer.getBounds(), {
                            padding: [15, 15]
                        });
                    } else {
                        console.warn("Minimap GeoJSON bounds invalid, using default.");
                        dashboardMinimap.setView([14.3030, 120.7920], 10);
                    }
                } catch (error) {
                    console.error('Failed to load minimap data:', error);
                    document.getElementById('dashboard-minimap-container').innerHTML = `<p class="map-error-message">Error loading map.</p>`;
                }
            }

            function initializeDashboardMinimap() {
                const minimapContainer = document.getElementById('dashboard-minimap-container');
                if (!minimapContainer) {
                    console.error("Minimap container div not found!");
                    return;
                }

                const initialCenter = [14.3030, 120.7920]; // General Naic, Cavite Area
                const initialZoom = 10;

                dashboardMinimap = L.map(minimapContainer, {
                    center: initialCenter,
                    zoom: initialZoom,
                    zoomControl: false,
                    scrollWheelZoom: false,
                    doubleClickZoom: false,
                    boxZoom: false,
                    touchZoom: false,
                    keyboard: false,
                    dragging: false,
                    tap: false,
                    attributionControl: false
                });

                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(dashboardMinimap);

                const MapTitleControl = L.Control.extend({
                    options: {
                        position: 'topleft'
                    },
                    onAdd: function(map) {
                        const container = L.DomUtil.create('div', 'leaflet-control-map-title');
                        container.innerHTML = 'MINIMAP OF NAIC';
                        L.DomEvent.disableClickPropagation(container);
                        L.DomEvent.disableScrollPropagation(container);
                        return container;
                    }
                });
                new MapTitleControl().addTo(dashboardMinimap);

                loadMinimapData();
                // Invalidate size after a short delay to ensure map renders correctly
                setTimeout(() => {
                    if (dashboardMinimap) dashboardMinimap.invalidateSize();
                }, 500);
            }

            initializeDashboardMinimap();
        });
    </script>
</body>

</html>