<?php
session_start();
require_once 'config.php'; // Your database connection

// Check if user is authenticated
if (!isset($_SESSION['user_id'])) {
    http_response_code(401); // Unauthorized
    echo json_encode(["error" => "Unauthorized access. Please login."]);
    exit;
}

$type = isset($_GET['type']) ? $_GET['type'] : '';
$data = [];

// Set header to JSON
header('Content-Type: application/json');

if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["error" => "Database connection failed: " . $conn->connect_error]);
    exit;
}

if ($type === 'education') {
    // --- Your existing education logic ---
    $sql = "SELECT education AS label, COUNT(*) AS value 
            FROM beneficiaries 
            WHERE education IS NOT NULL AND TRIM(education) != '' 
            GROUP BY education";
    
    $result = $conn->query($sql);

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
    } else {
        http_response_code(500);
        echo json_encode(["error" => "Database query failed for education data: " . $conn->error]);
        exit;
    }
    // --- End of existing education logic ---

} elseif ($type === 'occupation') {
    // Define the mapping from specific occupations to broad socioeconomic categories
     $occupation_to_socioeconomic_map = [
        // Very Low Skill
        'Unemployed' => 'Very Low Skill', // Value now matches the new shortened label
        'No Regular Occupation' => 'Very Low Skill',
        'Agricultural Labor/Seasonal Worker' => 'Very Low Skill',
        'Small-Scale Fisherman/Fisherwoman' => 'Very Low Skill',
        'Laundry Service' => 'Very Low Skill',
        'Waste Picker/Scavenger' => 'Very Low Skill',
        'Household Helper' => 'Very Low Skill',
        // Low Skill
        'Street Vendor' => 'Low Skill', // Value now matches the new shortened label
        'Market Vendor' => 'Low Skill',
        'Construction Laborer/Helper' => 'Low Skill',
        'Tricycle/Jeepney Driver' => 'Low Skill',
        'Entry-Level Factory Worker' => 'Low Skill',
        'Non-Professional Caregiver' => 'Low Skill',
        'Home-based Craft Production' => 'Low Skill',
        // Semi-Skilled
        'Carpenter/Mason' => 'Semi-Skilled', // Value now matches the new shortened label
        'Hairdresser/Barber (Established Small Stall/Home-Based)' => 'Semi-Skilled',
        'Manicurist/Pedicurist (Established Small Stall/Home-Based)' => 'Semi-Skilled',
        'Market Vendor (Established)' => 'Semi-Skilled',
        'Tricycle/Jeepney Driver (More Regular)' => 'Semi-Skilled',
        'Skilled Factory Worker' => 'Semi-Skilled',
        'Small Sari-Sari Store Owner (Developing)' => 'Semi-Skilled',
        'Food Stall Operator (Developing)' => 'Semi-Skilled',
        // Skilled/Business
        'Established Small Sari-Sari Store Owner' => 'Skilled/Business', // Value now matches the new shortened label
        'Established Food Stall Operator (Good Income)' => 'Skilled/Business',
        'Skilled Tradesperson (Regular Contracts)' => 'Skilled/Business',
        'Small Business Owner (Micro-enterprise with steady income)' => 'Skilled/Business',
        'Teacher' => 'Skilled/Business'
    ];

    // The four broad categories that will be the labels for the chart
    $socioeconomic_labels = [
        "Very Low Skill",  // Changed
        "Low Skill",       // Changed
        "Semi-Skilled",    // Changed
        "Skilled/Business" // Changed
    ];

    // Initialize counts for these categories to 0
    $category_counts = array_fill_keys($socioeconomic_labels, 0);

    // Construct the SQL CASE statement parts
    $sql_case_parts = [];
    foreach ($occupation_to_socioeconomic_map as $specific_occupation => $broad_category) {
        // Ensure the broad_category is one of our defined labels
        if (in_array($broad_category, $socioeconomic_labels)) {
            $sql_case_parts[] = "WHEN occupation = '" . $conn->real_escape_string($specific_occupation) . "' THEN '" . $conn->real_escape_string($broad_category) . "'";
        }
    }

    if (empty($sql_case_parts)) {
        // This would happen if the mapping is empty or incorrect
        // Output empty data for all categories
        foreach ($socioeconomic_labels as $label) {
            $data[] = ["label" => $label, "value" => 0];
        }
        echo json_encode($data);
        $conn->close();
        exit;
    }
    
    // IMPORTANT: Replace 'occupation' with the actual name of the column in your 'beneficiaries' table
    // that stores these specific job titles.
    $occupation_column_name = 'occupation'; // <--- CHECK AND CHANGE THIS IF NEEDED

    $sql_case_statement = "CASE " . implode(' ', $sql_case_parts) . " ELSE NULL END";

    $sql_query = "SELECT 
                        mapped_socioeconomic_category AS label, 
                        COUNT(*) AS value 
                   FROM (
                       SELECT {$sql_case_statement} AS mapped_socioeconomic_category
                       FROM beneficiaries  -- Make sure 'beneficiaries' is your correct table name
                       WHERE {$occupation_column_name} IS NOT NULL AND TRIM({$occupation_column_name}) != '' -- Consider only non-empty occupations
                   ) AS subquery
                   WHERE mapped_socioeconomic_category IS NOT NULL
                   GROUP BY mapped_socioeconomic_category";
    
    $result_occupation = $conn->query($sql_query);

    if ($result_occupation) {
        while ($row = $result_occupation->fetch_assoc()) {
            // The label from the query should directly match one of our defined socioeconomic categories
            if (array_key_exists($row['label'], $category_counts)) {
                $category_counts[$row['label']] = (int)$row['value'];
            }
        }
        
        // Format the data for Chart.js, maintaining the desired order of socioeconomic_labels
        foreach ($socioeconomic_labels as $label) {
            $data[] = ["label" => $label, "value" => $category_counts[$label]];
        }

    } else {
        http_response_code(500);
        // Provide more detailed error for debugging (you might want to log this instead of echoing in production)
        echo json_encode(["error" => "Database query failed for occupation data: " . $conn->error, "query_executed" => $sql_query]);
        exit;
    }

} else {
    if (empty($type)) {
        http_response_code(400); // Bad Request
        echo json_encode(["error" => "Chart type not specified."]);
    } else {
        http_response_code(400); // Bad Request
        echo json_encode(["error" => "Invalid chart type specified: " . htmlspecialchars($type)]);
    }
    exit;
}

echo json_encode($data);
$conn->close();
?>