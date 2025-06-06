<?php
// php/CalculateScore.php

require 'config.php'; // Your database connection

echo "Starting SES score calculation...\n";

// Define weights (these are examples, you'll need to adjust them based on your data's scale and importance)
// A positive weight for income means higher income = higher score (better SES)
// A positive weight for beneficiaries/PWD means we subtract more, so more beneficiaries/PWDs = lower score (worse SES)
$weight_beneficiaries = 100; 
$weight_pwd = 200;           

// Fetch all barangays
$sql = "SELECT BarangayID, Average_Income, TotalBeneficiaries, PWD FROM barangay";
$result = mysqli_query($conn, $sql);

if ($result) {
    if (mysqli_num_rows($result) > 0) {
        echo "Processing " . mysqli_num_rows($result) . " barangays.\n";
        while ($row = mysqli_fetch_assoc($result)) {
            $barangay_id = $row['BarangayID'];
            // Ensure numeric types and provide defaults if data is NULL
            $avg_income = (float)($row['Average_Income'] ?? 0); 
            $total_beneficiaries = (int)($row['TotalBeneficiaries'] ?? 0);
            $pwd_count = (int)($row['PWD'] ?? 0);

            // Calculate SES score
            // This formula assumes:
            // - Higher income improves the SES score.
            // - More beneficiaries reduce the SES score (indicating higher dependency/need).
            // - More PWDs reduce the SES score (indicating higher vulnerability/need).
            $ses_score = $avg_income - ($total_beneficiaries * $weight_beneficiaries) - ($pwd_count * $weight_pwd);
            
            echo "Barangay: $barangay_id, AvgIncome: $avg_income, Benef: $total_beneficiaries, PWD: $pwd_count, Calculated SES: $ses_score\n";

            // Update the barangay table
            $update_sql = "UPDATE barangay SET SES = ? WHERE BarangayID = ?";
            $stmt = mysqli_prepare($conn, $update_sql);
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "ds", $ses_score, $barangay_id); // 'd' for double (float), 's' for string
                if (mysqli_stmt_execute($stmt)) {
                    // Success, message is optional here as it's echoed above
                } else {
                    echo "ERROR: Could not execute SES update for $barangay_id: " . mysqli_error($conn) . "\n";
                }
                mysqli_stmt_close($stmt);
            } else {
                echo "ERROR: Could not prepare SES update statement for $barangay_id: " . mysqli_error($conn) . "\n";
            }
        }
        echo "Finished processing barangays.\n";
    } else {
        echo "No barangays found to process.\n";
    }
    mysqli_free_result($result);
} else {
    echo "ERROR: Could not execute query to fetch barangays: " . mysqli_error($conn) . "\n";
}

mysqli_close($conn);
echo "SES score calculation (CalculateScore.php) finished.\n";
?>