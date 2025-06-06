<?php
require 'config.php';

if (!isset($_GET['barangayID'])) {
    die("Barangay ID required");
}

$barangayID = $conn->real_escape_string($_GET['barangayID']);

// Get the count of existing households in this barangay
$query = "SELECT COUNT(*) as count FROM household WHERE barangayID = '$barangayID'";
$result = $conn->query($query);

if ($result) {
    $row = $result->fetch_assoc();
    $count = $row['count'] + 1;

    // Format: BARANGAYID-0001 (removed HH)
    $householdID = $barangayID . "-" . str_pad($count, 4, '0', STR_PAD_LEFT);
    echo $householdID;
} else {
    echo "Error generating ID";
}

$conn->close();
?>