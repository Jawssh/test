// js/home.js
document.addEventListener('DOMContentLoaded', function() {
    console.log("js/home.js: DOM fully loaded.");

    // --- Main function to fetch ALL dashboard data ---
    function fetchAllDashboardData() {
        console.log("JS: Initiating fetch for ALL dashboard data from 'php/dashboard.php'...");
        fetch('php/dashboard.php') // Path to your consolidated data script
            .then(response => {
                console.log("JS: Received response from php/dashboard.php. Status:", response.status, "Ok:", response.ok);
                if (!response.ok) {
                    return response.text().then(text => {
                        console.error("JS: HTTP error response text:", text);
                        throw new Error(`HTTP error! Status: ${response.status}. Server message: ${text || "No additional message"}`);
                    });
                }
                console.log("JS: Response OK. Attempting to parse JSON...");
                return response.json();
            })
            .then(data => {
                console.log("JS: Successfully parsed consolidated JSON data:", data);

                if (data === null || typeof data === 'undefined') {
                    console.error("JS: Parsed data is null or undefined.");
                    displayKpiErrorState("Received empty or invalid data from server.");
                    // Also show errors for charts if needed
                    displayChartErrorOnCanvas('occupationPieChart', "Invalid data received.");
                    displayChartErrorOnCanvas('educationPieChart', "Invalid data received.");
                    return;
                }

                if (data.error) {
                    console.error("JS: Error reported in JSON data from server:", data.error);
                    displayKpiErrorState(data.error);
                    displayChartErrorOnCanvas('occupationPieChart', `Error: ${data.error}`);
                    displayChartErrorOnCanvas('educationPieChart', `Error: ${data.error}`);
                    return;
                }

                // Populate KPIs
                if (data.kpi) {
                    console.log("JS: Populating KPI cards with data:", data.kpi);
                    populateKpiCards(data.kpi);
                } else {
                    console.warn("JS: KPI data missing in server response.");
                    displayKpiErrorState("KPI data missing.");
                }

                // Load Education Chart
                if (data.educationChartData) {
                    console.log("JS: Loading Education Chart with data:", data.educationChartData);
                    renderPieChart('educationPieChart', 'Education Attainment', data.educationChartData);
                } else {
                    console.warn("JS: Education chart data missing in server response.");
                    displayChartErrorOnCanvas('educationPieChart', "Education data missing.");
                }

                // Load Occupation Chart
                if (data.occupationChartData) {
                    console.log("JS: Loading Occupation Chart with data:", data.occupationChartData);
                    renderPieChart('occupationPieChart', 'Occupation Distribution', data.occupationChartData);
                } else {
                    console.warn("JS: Occupation chart data missing in server response.");
                    displayChartErrorOnCanvas('occupationPieChart', "Occupation data missing.");
                }

                // Placeholders for other charts/tables from your sketch
                // loadBarChart('sesLevelBarChart', '% of Households by SES Level', data.sesLevelData);
                // populateBarangayRankingTable(data.barangayRankingData);

            })
            .catch(error => {
                console.error('JS: Critical error during fetch or JSON parsing for dashboard data:', error);
                displayKpiErrorState(error.message || "Failed to load dashboard data.");
                displayChartErrorOnCanvas('occupationPieChart', "Failed to load chart data.");
                displayChartErrorOnCanvas('educationPieChart', "Failed to load chart data.");
            });
    }

    // --- Helper function to populate KPI cards ---
    function populateKpiCards(kpiData) {
        function updateElementText(id, value, formatter) {
            const el = document.getElementById(id);
            if (el) {
                if (value !== null && value !== undefined && (typeof value === 'number' || !isNaN(parseFloat(value)))) {
                    try {
                        el.textContent = formatter ? formatter(value) : value.toString();
                    } catch (e) {
                        console.error(`JS Error: Formatting value for element '${id}'. Value: ${value}`, e);
                        el.textContent = 'Format Err';
                    }
                } else {
                    el.textContent = 'N/A';
                }
            } else {
                console.error(`JS Error: HTML element with ID '${id}' not found for KPI.`);
            }
        }

        updateElementText('kpiTotalHouseholds', kpiData.totalHouseholds, val => parseInt(val).toLocaleString());
        updateElementText('kpiTotalBeneficiaries', kpiData.totalBeneficiaries, val => parseInt(val).toLocaleString());
        updateElementText('kpiAverageIncome', kpiData.averageIncome, val => parseFloat(val).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}));
        updateElementText('kpiAveragePwdPerBarangay', kpiData.averagePwdPerBarangay, val => parseFloat(val).toFixed(2));
        updateElementText('kpiPercentageUnemployed', kpiData.percentageUnemployed, val => parseFloat(val).toFixed(2));
        updateElementText('kpiPercentageNoSchool', kpiData.percentageNoSchool, val => parseFloat(val).toFixed(2));
    }

    // --- Helper function to display error state on all KPI cards ---
    function displayKpiErrorState(errorMessage) {
        console.warn("JS: Displaying error state for KPIs. Message:", errorMessage);
        const kpiIds = ['kpiTotalHouseholds', 'kpiTotalBeneficiaries', 'kpiAverageIncome', 
                        'kpiAveragePwdPerBarangay', 'kpiPercentageUnemployed', 'kpiPercentageNoSchool'];
        kpiIds.forEach(id => {
            const el = document.getElementById(id);
            if (el) el.textContent = 'Error';
        });
    }

    // --- Modified Chart Rendering Function (takes data directly) ---
    function renderPieChart(canvasId, chartLabel, chartData) { // Renamed from loadPieChart
        const canvas = document.getElementById(canvasId);
        if (!canvas) {
            console.error(`JS Error: Canvas element with ID '${canvasId}' not found for chart: ${chartLabel}.`);
            return;
        }
        const ctx = canvas.getContext('2d');

        if (!chartData || chartData.length === 0) {
            console.warn(`JS Warn: No data provided for chart: ${chartLabel}`);
            displayChartErrorOnCanvas(canvasId, "No data available for this chart.");
            return;
        }

        const filteredData = chartData.filter(item => item.label !== null && item.label !== '');
        if (filteredData.length === 0) {
            console.warn(`JS Warn: No valid (non-empty label) data to display for chart: ${chartLabel}`);
            displayChartErrorOnCanvas(canvasId, "No valid data for display.");
            return;
        }
        const labels = filteredData.map(item => item.label);
        const values = filteredData.map(item => item.value);
        
        let existingChart = Chart.getChart(canvasId);
        if (existingChart) {
            existingChart.destroy();
        }

        new Chart(ctx, {
            type: 'pie',
            data: {
                labels: labels,
                datasets: [{
                    label: chartLabel,
                    data: values,
                    backgroundColor: [
                        'rgba(255, 99, 132, 0.8)', 'rgba(54, 162, 235, 0.8)',
                        'rgba(255, 206, 86, 0.8)', 'rgba(75, 192, 192, 0.8)',
                        'rgba(153, 102, 255, 0.8)', 'rgba(255, 159, 64, 0.8)',
                        'rgba(100, 150, 200, 0.8)', 'rgba(200, 150, 100, 0.8)'
                    ],
                    borderColor: '#fff',
                    borderWidth: 1.5
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'right', labels: { boxWidth: 12, font: { size: 10 } } },
                    title: { display: false, text: chartLabel },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = context.label || '';
                                if (label) { label += ': '; }
                                if (context.parsed !== null) {
                                    const total = context.dataset.data.reduce((a, b) => parseFloat(a) + parseFloat(b), 0);
                                    const percentage = total > 0 ? ((parseFloat(context.parsed) / total) * 100).toFixed(1) + '%' : '0%';
                                    label += parseFloat(context.parsed).toLocaleString() + ' (' + percentage + ')';
                                }
                                return label;
                            }
                        }
                    }
                }
            }
        });
    }
    
    // Helper to display error messages on a chart canvas
    function displayChartErrorOnCanvas(canvasId, message) {
        const canvas = document.getElementById(canvasId);
        if (canvas) {
            const ctx = canvas.getContext('2d');
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            ctx.font = "14px Arial";
            ctx.textAlign = "center";
            ctx.fillStyle = "red";
            ctx.fillText(message, canvas.width / 2, canvas.height / 2);
        }
    }

    // Call the main function to load all dashboard data
    fetchAllDashboardData();

}); // End DOMContentLoaded