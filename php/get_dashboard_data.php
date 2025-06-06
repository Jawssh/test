<?php
session_start();
require_once 'config.php'; // Ensure this path is correct to your DB config

// Note: The original dashboard.php had this initial check,
// but for an AJAX endpoint, it's generally handled client-side
// or by making sure the session is active before calling this.
// If you want to strictly enforce session on this endpoint, keep it:
// if (!isset($_SESSION['user_id'])) {
//     // You might return an error JSON instead of redirecting for AJAX
//     header('Content-Type: application/json');
//     echo json_encode(['error' => 'Unauthorized']);
//     exit;
// }

$barangay = $_GET['barangay'] ?? 'all'; // Default to 'all' if not set
$whereClause = ($barangay === 'all') ? '' : "WHERE BarangayName = '" . $conn->real_escape_string($barangay) . "'";

// Query for top cards (total households, beneficiaries, PWD)
$sql_stats = "SELECT
    SUM(TotalHouseholds) AS total_households,
    SUM(TotalBeneficiaries) AS total_beneficiaries,
    SUM(PWD) AS total_pwd
FROM barangay $whereClause";

$result_stats = $conn->query($sql_stats);
$stats = $result_stats ? $result_stats->fetch_assoc() : [
    'total_households' => 0,
    'total_beneficiaries' => 0,
    'total_pwd' => 0
];

// Calculate percentage for PWD (adjust based on total beneficiaries)
$stats['percentage_pwd'] = ($stats['total_beneficiaries'] > 0) ? ($stats['total_pwd'] / $stats['total_beneficiaries']) * 100 : 0;


// Query for Top Priority Barangays (SES Scores)
$sql_top_barangays = "SELECT BarangayName, SES
FROM barangay $whereClause
ORDER BY SES DESC
LIMIT 30"; // You might want to adjust LIMIT based on your data and display
$result_top_barangays = $conn->query($sql_top_barangays);
$top_barangays = $result_top_barangays ? $result_top_barangays->fetch_all(MYSQLI_ASSOC) : [];


// Queries for Chart Data - IMPORTANT: These assume your individual data is in an 'individuals' table.
// If it's all in the 'barangay' table (denormalized), you'll need to adjust the queries significantly.
// Assuming 'individuals' table is linked to 'barangay' by 'BarangayName' or a 'barangay_id'
// For simplicity, I'm assuming 'individuals' table also has 'BarangayName' for filtering.
// If you have a separate 'individuals' table linked by foreign key, adjust WHERE clause like:
// "SELECT EducationAttainment as label, COUNT(*) as value FROM individuals WHERE barangay_id IN (SELECT id FROM barangay WHERE BarangayName = '$barangay') GROUP BY EducationAttainment";

$sql_education = "SELECT EducationAttainment as label, COUNT(*) as value FROM individuals $whereClause GROUP BY EducationAttainment";
$result_education = $conn->query($sql_education);
$education_data = $result_education ? $result_education->fetch_all(MYSQLI_ASSOC) : [];

$sql_occupation = "SELECT Occupation as label, COUNT(*) as value FROM individuals $whereClause GROUP BY Occupation";
$result_occupation = $conn->query($sql_occupation);
$occupation_data = $result_occupation ? $result_occupation->fetch_all(MYSQLI_ASSOC) : [];

$sql_income = "SELECT IncomeRange as label, COUNT(*) as value FROM individuals $whereClause GROUP BY IncomeRange";
$result_income = $conn->query($sql_income);
$income_data = $result_income ? $result_income->fetch_all(MYSQLI_ASSOC) : [];

$sql_health = "SELECT HealthCondition as label, COUNT(*) as value FROM individuals $whereClause GROUP BY HealthCondition";
$result_health = $conn->query($sql_health);
$health_data = $result_health ? $result_health->fetch_all(MYSQLI_ASSOC) : [];

$sql_gender = "SELECT Gender as label, COUNT(*) as value FROM individuals $whereClause GROUP BY Gender";
$result_gender = $conn->query($sql_gender);
$gender_data = $result_gender ? $result_gender->fetch_all(MYSQLI_ASSOC) : [];

// SES data for the line chart (or bar chart) - this aggregates by SES score range
// Assuming SES is a numerical score in the 'barangay' table
$sql_ses = "SELECT
    CASE
        WHEN SES >= 0 AND SES < 20 THEN '0-19'
        WHEN SES >= 20 AND SES < 40 THEN '20-39'
        WHEN SES >= 40 AND SES < 60 THEN '40-59'
        WHEN SES >= 60 AND SES < 80 THEN '60-79'
        WHEN SES >= 80 AND SES <= 100 THEN '80-100'
        ELSE 'Unknown'
    END as label,
    COUNT(*) as count
    FROM barangay $whereClause
    GROUP BY label
    ORDER BY label"; // Order by label for consistent chart axis
$result_ses = $conn->query($sql_ses);
$ses_data = $result_ses ? $result_ses->fetch_all(MYSQLI_ASSOC) : [];


$response = [
    'stats' => $stats,
    'top_barangays' => $top_barangays,
    'education_data' => $education_data,
    'occupation_data' => $occupation_data,
    'income_data' => $income_data,
    'health_data' => $health_data,
    'gender_data' => $gender_data,
    'ses_data' => $ses_data
];

header('Content-Type: application/json');
echo json_encode($response);
