<?php
include 'config.php';

$barangay = $_GET['barangay'] ?? 'all';
$where = "";

if ($barangay !== 'all') {
    $barangayEscaped = mysqli_real_escape_string($conn, $barangay);
    $where = "WHERE h.barangayID = (SELECT BarangayID FROM barangay WHERE BarangayName = '$barangayEscaped')";
}

// Queries
$edu = mysqli_query($conn, "SELECT education, COUNT(*) as count
    FROM beneficiaries b
    JOIN household h ON b.householdID = h.householdID
    $where GROUP BY education");

$occ = mysqli_query($conn, "SELECT occupation, COUNT(*) as count
    FROM beneficiaries b
    JOIN household h ON b.householdID = h.householdID
    $where GROUP BY occupation");

$income = mysqli_query($conn, "SELECT 
    CASE 
        WHEN income < 5000 THEN 'Below PHP 5,000'
        WHEN income BETWEEN 5001 AND 10000 THEN 'PHP 5,001 - PHP 10,000'
        WHEN income BETWEEN 10001 AND 15000 THEN 'PHP 10,001 - PHP 15,000'
        WHEN income BETWEEN 15001 AND 20000 THEN 'PHP 15,001 - PHP 20,000'
        ELSE 'Above PHP 20,000'
    END AS bracket,
    COUNT(*) as count
    FROM beneficiaries b
    JOIN household h ON b.householdID = h.householdID
    $where GROUP BY bracket");

$health = mysqli_query($conn, "SELECT health, COUNT(*) as count
    FROM beneficiaries b
    JOIN household h ON b.householdID = h.householdID
    $where GROUP BY health");

// Formatter
function formatChart($res)
{
    $labels = [];
    $data = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $labels[] = $row[0];
        $data[] = (int)$row['count'];
    }
    return ['labels' => $labels, 'data' => $data];
}

echo json_encode([
    'education' => formatChart($edu),
    'occupation' => formatChart($occ),
    'income' => formatChart($income),
    'health' => formatChart($health)
]);
