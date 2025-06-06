<?php
include 'config.php';

$barangay = $_GET['barangay'] ?? 'all';

if ($barangay === 'all') {
    $query = "SELECT 
                SUM(TotalHouseholds) AS TotalHouseholds, 
                SUM(TotalBeneficiaries) AS TotalBeneficiaries, 
                SUM(PWD) AS PWD 
              FROM barangay";
} else {
    $stmt = $conn->prepare("SELECT 
                                TotalHouseholds, 
                                TotalBeneficiaries, 
                                PWD 
                            FROM barangay 
                            WHERE BarangayName = ?");
    $stmt->bind_param("s", $barangay);
    $stmt->execute();
    $result = $stmt->get_result();
    echo json_encode($result->fetch_assoc());
    exit;
}

$result = mysqli_query($conn, $query);
echo json_encode(mysqli_fetch_assoc($result));
