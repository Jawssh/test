<!-- Include Sidebar -->
<?php
session_start(); // Start the session
// Check if the user is logged in by verifying if 'user_id' session variable is set
if (!isset($_SESSION['user_id'])) {
    // If not logged in, redirect to the login page
    header("Location: ../index.php");
    exit;
} ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="../png/logo.png" type="image/x-icon" />
    <link rel="stylesheet" href="../css/map.css">
    <title>Choropleth Map</title>

    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
</head>

<body>
    <?php include 'navbar.php'; // Assumes navbar.php is in the php/ folder
    ?>

    <section class="home">
        <div class="text">
            <h3>Map</h3>
            <span class="profession">Boundaries per Barangay with Socioeconomic Level</span>
        </div>
        <div class="filter-container">
            <label for="cb1-6">
                <div class="checkbox-wrapper-6">
                    <input class="tgl tgl-light" id="cb1-6" type="checkbox" />
                    <label class="tgl-btn" for="cb1-6"></label>
                </div>
                Show SES Score
            </label>
        </div>
        <div class="map-container">
            <div id="map"></div>
        </div>

        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

        <script src="../js/map.js" defer></script>
    </section>
</body>

</html>