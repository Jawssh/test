<?php
// php/fetch_map_data.php

ini_set('display_errors', 1); // Useful for debugging, turn off for production
error_reporting(E_ALL);

require 'config.php';      // For database connection ($conn)
require 'Algorithm.php'; // For getJenksBreaks() (ensure this path is correct if Algorithm.php is in a different location)

header('Content-Type: application/json');

// --- Configuration ---
$num_classes = 5; // Desired number of socioeconomic classes
$ses_level_names = ["Very Low", "Low", "Medium", "High", "Very High"]; // Names for your classes

if (count($ses_level_names) !== $num_classes) {
    // Fallback if names don't match num_classes, to prevent errors in getSesLevel
    $ses_level_names = [];
    for ($i = 0; $i < $num_classes; $i++) {
        $ses_level_names[] = "Level " . ($i + 1);
    }
    // You might want to log an error here if $num_classes and count($ses_level_names) don't match
}


// --- Helper function to assign SES Level based on score and breaks ---
function getSesLevel(float $score, array $breaks, array $level_names): string {
    if (empty($breaks) || empty($level_names) || count($breaks) < 2 || count($level_names) !== (count($breaks) -1) ) {
        // Not enough breaks to define classes or level names don't match break count
        return "Classification Error";
    }

    // Ensure score is not below the first break (min value) for safety
    $score = max($score, $breaks[0]);
    // Ensure score is not above the last break (max value) for safety
    $score = min($score, $breaks[count($breaks) - 1]);

    for ($i = 0; $i < count($breaks) - 1; $i++) {
        // A score is in class i if: breaks[i] <= score <= breaks[i+1]
        // For all but the last class, the upper bound is exclusive if the next break is different.
        // If the score is exactly on a break point, it typically falls into the class *above* that break (i.e., the break is the lower bound).
        if ($score >= $breaks[$i]) {
            // If it's the last class range or the score is less than the next break point
            if ($i === count($breaks) - 2 || $score < $breaks[$i+1]) {
                 return $level_names[$i];
            }
            // If the score is exactly the last break point, it falls into the last class.
            if ($i === count($breaks) - 2 && $score == $breaks[$i+1]) {
                return $level_names[$i];
            }
        }
    }
    // Fallback: should ideally be caught by score clamping or if breaks define full range.
    // This could indicate an issue if reached with valid score & breaks.
    // If score is exactly the min value, it should fall in the first class.
    if ($score == $breaks[0]) {
        return $level_names[0];
    }
    return "Undefined"; // Fallback for any unclassified scores
}


$barangay_data = [];
$all_ses_scores = [];

// --- 1. Fetch Socioeconomic Data and SES scores from `barangay` table ---
$sql_barangay = "SELECT BarangayID, BarangayName, TotalHouseholds, TotalBeneficiaries, Average_Income, PWD, SES 
                 FROM barangay 
                 WHERE SES IS NOT NULL"; // Only include barangays with an SES score

$result_barangay = mysqli_query($conn, $sql_barangay);

if (!$result_barangay) {
    echo json_encode(["error" => "Failed to fetch barangay data: " . mysqli_error($conn)]);
    mysqli_close($conn);
    exit;
}

while ($row = mysqli_fetch_assoc($result_barangay)) {
    $barangay_data[$row['BarangayName']] = [ // Use BarangayName as key
        'BarangayID' => $row['BarangayID'],
        'BarangayName' => $row['BarangayName'], // Explicitly add BarangayName to properties
        'TotalHouseholds' => (int)$row['TotalHouseholds'],
        'TotalBeneficiaries' => (int)$row['TotalBeneficiaries'],
        'Average_Income' => isset($row['Average_Income']) ? (float)$row['Average_Income'] : null, // Handle NULLs
        'PWD' => (int)$row['PWD'],
        'SES' => (float)$row['SES']
    ];
    $all_ses_scores[] = (float)$row['SES'];
}
mysqli_free_result($result_barangay);

if (empty($all_ses_scores)) {
    echo json_encode(["error" => "No SES scores found to classify. Please ensure CalculateScore.php has run and updated SES values."]);
    mysqli_close($conn);
    exit;
}

// --- 2. Calculate Jenks Natural Breaks ---
$jenks_breaks = getJenksBreaks($all_ses_scores, $num_classes);

if (empty($jenks_breaks) || count($jenks_breaks) < 2 || count($jenks_breaks) > ($num_classes + 1) ) {
     echo json_encode([
        "error" => "Failed to calculate valid Jenks breaks. Check SES data distribution or Algorithm.php.",
        "ses_scores_count" => count($all_ses_scores),
        "num_classes_requested" => $num_classes,
        "calculated_breaks_count" => count($jenks_breaks),
        "calculated_breaks" => $jenks_breaks, // Show the breaks that were calculated
        "ses_scores_sample" => array_slice(array_unique($all_ses_scores), 0, 10) // Show a sample of unique scores
    ]);
    mysqli_close($conn);
    exit;
}


// --- 3. Fetch Coordinates and Build GeoJSON Features (Revised Two-Pass Logic) ---
$features = [];

// First, group all coordinates by barangay name and polygon order
$all_coordinates_by_barangay = [];
$sql_coords = "SELECT barangay_name, polygon_order, longitude, latitude 
               FROM barangay_coordinates 
               ORDER BY barangay_name, polygon_order, coordinate_order ASC";

$result_coords = mysqli_query($conn, $sql_coords);
if (!$result_coords) {
    echo json_encode(["error" => "Failed to fetch coordinates: " . mysqli_error($conn)]);
    mysqli_close($conn);
    exit;
}

while ($coord_row = mysqli_fetch_assoc($result_coords)) {
    $brgy_name_from_coords = $coord_row['barangay_name'];
    
    if (!isset($barangay_data[$brgy_name_from_coords])) {
        // error_log("Notice: Coordinates found for barangay '$brgy_name_from_coords' but no matching socio-economic data in barangay table (or SES was NULL). Skipping this geometry.");
        continue; 
    }
    
    $poly_order = (int)$coord_row['polygon_order']; // polygon_order helps define separate parts of a MultiPolygon
    $all_coordinates_by_barangay[$brgy_name_from_coords][$poly_order][] = [(float)$coord_row['longitude'], (float)$coord_row['latitude']];
}
mysqli_free_result($result_coords);

// Now, iterate through the grouped coordinates to build features
foreach ($all_coordinates_by_barangay as $brgy_name => $polygons_data_for_barangay) {
    // $brgy_name is the key from $all_coordinates_by_barangay, which comes from barangay_coordinates.barangay_name
    // $barangay_data is keyed by barangay.BarangayName. Ensure these match.
    // For safety, check if $barangay_data[$brgy_name] exists (already done when populating $all_coordinates_by_barangay)

    $formatted_polygons_for_geojson = [];
    ksort($polygons_data_for_barangay); // Ensure polygon parts are ordered if that matters for MultiPolygon

    foreach ($polygons_data_for_barangay as $poly_order => $single_polygon_coordinates_array) {
        if (!empty($single_polygon_coordinates_array) && count($single_polygon_coordinates_array) >= 3) { // A valid ring needs at least 3 points
            // Ensure the ring is closed: first and last point should be the same.
            // Leaflet can be forgiving, but good practice for GeoJSON.
            if ($single_polygon_coordinates_array[0] !== $single_polygon_coordinates_array[count($single_polygon_coordinates_array)-1]) {
                $single_polygon_coordinates_array[] = $single_polygon_coordinates_array[0];
            }
            $formatted_polygons_for_geojson[] = [$single_polygon_coordinates_array]; // Each polygon part is an array of [rings]
        }
    }

    if (empty($formatted_polygons_for_geojson)) {
        // error_log("Warning: No valid polygon geometries constructed for barangay '$brgy_name'. Skipping feature.");
        continue; 
    }
    
    $geometry_type = (count($formatted_polygons_for_geojson) > 1) ? 'MultiPolygon' : 'Polygon';
    // For Polygon, coordinates are [ ring1, ring2 (hole), ... ] where ring is [[lng,lat],...]
    // For MultiPolygon, coordinates are [ Polygon1_coords, Polygon2_coords, ... ]
    // Our $formatted_polygons_for_geojson is already structured as [ [ring1_for_poly1], [ring1_for_poly2], ...]
    // So if it's a Polygon (only one item in $formatted_polygons_for_geojson), we take the first element.
    $final_coordinates_for_geojson = ($geometry_type === 'Polygon') ? $formatted_polygons_for_geojson[0] : $formatted_polygons_for_geojson;

    // Retrieve the corresponding socioeconomic data from $barangay_data
    // This assumes $brgy_name from coordinates table matches a key in $barangay_data (which is BarangayName from barangay table)
    $properties = $barangay_data[$brgy_name]; 
    $ses_score = $properties['SES'];
    $properties['SES_Level'] = getSesLevel($ses_score, $jenks_breaks, $ses_level_names);
    // $properties['BarangayNameFromCoordsTable'] = $brgy_name; // For debugging name consistency if needed

    $features[] = [
        'type' => 'Feature',
        'geometry' => [
            'type' => $geometry_type,
            'coordinates' => $final_coordinates_for_geojson
        ],
        'properties' => $properties 
    ];
}

mysqli_close($conn);

// --- 4. Construct the final GeoJSON FeatureCollection ---
$featureCollection = [
    'type' => 'FeatureCollection',
    'crs' => [ 
        'type' => 'name',
        'properties' => [
            'name' => 'urn:ogc:def:crs:OGC:1.3:CRS84' 
        ]
    ],
    'metadata' => [ 
        'jenks_breaks' => $jenks_breaks,
        'ses_level_names' => $ses_level_names,
        'num_classes' => $num_classes
    ],
    'features' => $features
];

// --- 5. Output JSON ---
echo json_encode($featureCollection);

?>