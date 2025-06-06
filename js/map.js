// js/map.js

document.addEventListener('DOMContentLoaded', function () {
    // Initialize the map with fractional zoom options
    // Centered on Naic, Cavite, with an initial zoom level of 14.
    const map = L.map('map', {
        center: [14.3030, 120.7920], // NEW coordinates for Halang (near Palangue 1)
        zoom: 13.5,                    // Initial zoom level (you can adjust this)
        zoomSnap: 0.1,
        zoomDelta: 0.2


    });

    // Alternative initialization if you prefer to keep setView separate for initial state:
    /*
    const map = L.map('map', {
        zoomSnap: 0.1,
        zoomDelta: 0.2
    }).setView([14.3203, 120.7605], 14);

    
    */



    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    }).addTo(map);

    let geojsonLayer;
    let legendControl;
    let mapMetadata;

    function getSESColor(levelName) {
        switch (levelName) {
            case "Very Low": return '#ffe135'; // You changed this color, keeping it
            case "Low": return '#FEB24C';
            case "Medium": return '#FD8D3C';
            case "High": return '#FC4E2A';
            case "Very High": return '#BD0026';
            default: return '#CCCCCC';
        }
    }

    function styleFeature(feature) { // For Heatmap ON
        return {
            fillColor: getSESColor(feature.properties.SES_Level),
            weight: 2,
            opacity: 1,
            color: 'white',
            dashArray: '3',
            fillOpacity: 0.7
        };
    }

    function boundaryStyle(feature) { // For Heatmap OFF
        return {
            fillColor: 'transparent',
            weight: 2,
            opacity: 1,
            color: 'blue',
            dashArray: '3',
            fillOpacity: 0.0
        };
    }

    function highlightFeature(e) {
        const layer = e.target;
        const showHeatMap = document.getElementById('cb1-6').checked;

        let hoverStyle = {
            weight: 4,
            color: '#666',
            dashArray: ''
        };

        if (showHeatMap) {
            hoverStyle.fillColor = getSESColor(layer.feature.properties.SES_Level);
            hoverStyle.fillOpacity = 0.9;
        } else {
            hoverStyle.fillColor = 'transparent';
            hoverStyle.fillOpacity = 0.0;
        }
        layer.setStyle(hoverStyle);

        if (!L.Browser.ie && !L.Browser.opera && !L.Browser.edge) {
            layer.bringToFront();
        }

        const props = layer.feature.properties;
        const popupContent = `
            <center><h2>${props.BarangayName || 'N/A'}</h2></center>
            <b>No. of Households:</b> ${props.TotalHouseholds !== undefined ? props.TotalHouseholds : 'N/A'}<br>
            <b>No. of Beneficiaries:</b> ${props.TotalBeneficiaries !== undefined ? props.TotalBeneficiaries : 'N/A'}<br>
            <b>Average Income:</b> ${props.Average_Income !== undefined && props.Average_Income !== null ? '₱' + parseFloat(props.Average_Income).toFixed(2) : 'N/A'}<br>
            <b>No. of PWD's:</b> ${props.PWD !== undefined ? props.PWD : 'N/A'}<br>
            <b>Socioeconomic Score:</b> ${props.SES !== undefined && props.SES !== null ? parseFloat(props.SES).toFixed(2) : 'N/A'}<br>
            <b>SES Level:</b> <span style="color:${getSESColor(props.SES_Level)}">${props.SES_Level || 'N/A'}</span>
        `;
        layer.bindPopup(popupContent, { autoPan: false }).openPopup();
    }

    function resetHighlight(e) {
        if (geojsonLayer && e.target) {
            const showHeatMap = document.getElementById('cb1-6').checked;
            if (showHeatMap) {
                e.target.setStyle(styleFeature(e.target.feature));
            } else {
                e.target.setStyle(boundaryStyle(e.target.feature));
            }
        }
        if (e.target && e.target.isPopupOpen()) {
            e.target.closePopup();
        }
    }

    function onEachFeature(feature, layer) {
        layer.on({
            mouseover: highlightFeature,
            mouseout: resetHighlight,
        });
    }

    function updateLegend() {
        if (legendControl) {
            map.removeControl(legendControl);
        }
        if (!mapMetadata || !mapMetadata.jenks_breaks || !mapMetadata.ses_level_names) {
            console.error("Legend data (mapMetadata, jenks_breaks, or ses_level_names) not available for legend.");
            return;
        }

        legendControl = L.control({ position: 'bottomright' });

        legendControl.onAdd = function () {
            const div = L.DomUtil.create('div', 'info legend');
            const breaks = mapMetadata.jenks_breaks;
            const levels = mapMetadata.ses_level_names;

            if (!Array.isArray(breaks) || !Array.isArray(levels) || levels.length === 0 || breaks.length < (levels.length + 1)) {
                console.error("Invalid breaks or levels for legend. Breaks:", breaks, "Levels:", levels);
                div.innerHTML = '<h4>Legend Data Error</h4>';
                if (Array.isArray(breaks)) {
                    div.innerHTML += '<p style="font-size:10px;">Raw Breaks: ' + breaks.map(b => parseFloat(b).toFixed(2)).join(', ') + '</p>';
                }
                return div;
            }

            div.innerHTML = '<h4>Socioeconomic Level</h4>';
            for (let i = 0; i < levels.length; i++) {
                const color = getSESColor(levels[i]);
                const label = levels[i];

                const from = parseFloat(breaks[i]);
                const to = parseFloat(breaks[i + 1]);

                let rangeString;
                if (!isNaN(from) && !isNaN(to)) {
                    rangeString = `(${from.toFixed(2)} &ndash; ${to.toFixed(2)})`;
                } else if (!isNaN(from)) {
                    rangeString = `(≥ ${from.toFixed(2)})`;
                } else {
                    rangeString = '(Data N/A)';
                }

                div.innerHTML +=
                    '<i style="background:' + color + '"></i> ' +
                    label + ' ' + rangeString + '<br>';
            }
            return div;
        };

        if (document.getElementById('cb1-6').checked && legendControl) {
            legendControl.addTo(map);
        }
    }

    async function loadMapData() {
        try {
            const response = await fetch('fetch_map_data.php');
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            const geojsonData = await response.json();

            if (geojsonData.error) {
                alert('Error fetching map data: ' + geojsonData.error);
                console.error('Error fetching map data:', geojsonData.error);
                return;
            }

            if (!geojsonData.features || !geojsonData.metadata) {
                console.error('GeoJSON data is missing features or metadata.');
                alert('Failed to load map data: Incomplete data received.');
                return;
            }
            mapMetadata = geojsonData.metadata;

            if (geojsonLayer) {
                map.removeLayer(geojsonLayer);
            }

            const showHeatMap = document.getElementById('cb1-6').checked;

            geojsonLayer = L.geoJson(geojsonData.features, {
                style: showHeatMap ? styleFeature : boundaryStyle,
                onEachFeature: onEachFeature
            }).addTo(map);

            // Add barangay name labels at the centroid of each polygon
            geojsonData.features.forEach(feature => {
                const layer = L.geoJson(feature);
                const center = layer.getBounds().getCenter();
                const props = feature.properties;

                const label = L.marker(center, {
                    icon: L.divIcon({
                        className: 'barangay-label',
                        html: `<div>${props.BarangayName || 'Barangay'}</div>`,
                        iconSize: null
                    }),
                    interactive: false // Makes it not clickable
                }).addTo(map);
            });



            if (showHeatMap) {
                updateLegend();
            }

        } catch (error) {
            console.error('Could not load map data:', error);
            alert('Failed to load map data. Please check the console for errors.');
        }
    }

    const toggleChoroplethCheckbox = document.getElementById('cb1-6');
    toggleChoroplethCheckbox.addEventListener('change', function () {
        if (!geojsonLayer) return;

        const isChecked = this.checked;
        geojsonLayer.setStyle(isChecked ? styleFeature : boundaryStyle);

        if (isChecked) {
            updateLegend();
        } else {
            if (legendControl) {
                map.removeControl(legendControl);
            }
        }
    });

    loadMapData();
});