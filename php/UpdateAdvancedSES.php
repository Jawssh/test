<?php
// php/UpdateAdvancedSES.php

ini_set('display_errors', 1); // Useful for debugging this script when run directly
error_reporting(E_ALL);
set_time_limit(0); // Allow script to run for a long time if many households

// Ensure these paths are correct relative to UpdateAdvancedSES.php
require_once __DIR__ . '/config.php';          // Your database connection
require_once __DIR__ . '/ses_calculation_logic.php'; // The centralized SES logic

// Basic HTML output for progress
echo "<!DOCTYPE html><html><head><title>Update All SES Scores</title>";
echo "<meta http-equiv='Content-Type' content='text/html; charset=utf-8'>";
echo "<style>body { font-family: 'Courier New', Courier, monospace; line-height: 1.5; padding: 15px; background-color: #f4f4f4; color: #333; }
h1, h2 { color: #0056b3; border-bottom: 1px solid #ccc; padding-bottom: 5px;}
.success { color: #28a745; font-weight: bold; }
.error { color: #dc3545; font-weight: bold; }
.info { color: #17a2b8; }
.debug { color: #ff7f50; font-style: italic; } /* Coral color for debug messages */
ul { list-style-type: none; padding-left: 0;} li { margin-bottom: 3px; padding: 2px; border-left: 3px solid #eee; }
li:nth-child(odd) { background-color: #fdfdfd; }
hr { margin: 25px 0; border: 0; border-top: 1px solid #ccc; }
</style>";
echo "</head><body>";
echo "<h1>Updating Socioeconomic Scores (All Households & Barangays)</h1>";
echo "<p>Current Server Time: " . date('Y-m-d H:i:s') . "</p>";

// --- Stage 1: Update Individual Household SES Scores ---
echo "<h2>Stage 1: Updating Individual Household SES Scores</h2>";
echo "<p class='info'>Starting process to update SES scores for all households using centralized logic...</p>";
ob_flush(); flush(); // Flush output buffer to see progress

$household_ids_query = "SELECT householdID FROM household ORDER BY householdID"; // Added ORDER BY for consistent processing
$household_ids_result = mysqli_query($conn, $household_ids_query);

if (!$household_ids_result) {
    echo "<p class='error'>FATAL ERROR: Could not fetch household IDs: " . mysqli_error($conn) . "</p>";
    echo "</body></html>";
    exit;
}

$households_total = mysqli_num_rows($household_ids_result);
$households_processed_count = 0;
$households_success_count = 0;
$households_failed_count = 0;

echo "<p>Found <strong>" . $households_total . "</strong> households to process.</p><ul>";
ob_flush(); flush();

while ($hh_row = mysqli_fetch_assoc($household_ids_result)) {
    $householdID = $hh_row['householdID'];
    $households_processed_count++;
    echo "<li>Processing Household ID: <strong>" . htmlspecialchars($householdID) . "</strong> ($households_processed_count/$households_total)... ";
    ob_flush(); flush();

    if (calculateAndUpdateHouseholdSES($householdID, $conn)) {
        echo "<span class='success'>SUCCESS</span></li>";
        $households_success_count++;
    } else {
        echo "<span class='error'>FAILED (check server error log for details specific to HHID: " . htmlspecialchars($householdID) . ")</span></li>";
        $households_failed_count++;
    }
    // Optional: Add a small delay if server load is an issue, e.g., usleep(50000); // 50ms
}
mysqli_free_result($household_ids_result);

echo "</ul>";
echo "<p class='info'><strong>Household SES Update Summary:</strong><br>";
echo "Total Households Processed: " . $households_processed_count . "<br>";
echo "Successfully Updated: <span class='success'>" . $households_success_count . "</span><br>";
echo "Failed to Update: <span class='error'>" . $households_failed_count . "</span></p>";
echo "<hr>";
ob_flush(); flush();

// --- Stage 2: Calculate Raw Average SES for each Barangay ---
echo "<h2>Stage 2: Calculating Raw Average SES for Each Barangay</h2>";
echo "<p class='info'>Aggregating updated household SES scores to calculate raw average SES for each barangay...</p>";
ob_flush(); flush();

$barangay_ids_query_for_ses = "SELECT BarangayID, BarangayName FROM barangay ORDER BY BarangayName";
$barangay_ids_result_for_ses = mysqli_query($conn, $barangay_ids_query_for_ses);

if (!$barangay_ids_result_for_ses) {
    echo "<p class='error'>FATAL ERROR: Could not fetch barangay IDs for SES aggregation: " . mysqli_error($conn) . "</p>";
    echo "</body></html>";
    exit;
}

$all_barangay_raw_scores = [];
$barangays_processed_for_avg = 0;
$barangays_total_in_db = mysqli_num_rows($barangay_ids_result_for_ses);
echo "<p>Processing " . $barangays_total_in_db . " barangays for average SES calculation.</p><ul>";
ob_flush(); flush();

while ($b_row = mysqli_fetch_assoc($barangay_ids_result_for_ses)) {
    $barangayID = $b_row['BarangayID'];
    $barangayName = $b_row['BarangayName'];
    $barangays_processed_for_avg++;
    echo "<li>Processing Barangay: <strong>" . htmlspecialchars($barangayName) . " (ID: " . htmlspecialchars($barangayID) . ")</strong> ($barangays_processed_for_avg/$barangays_total_in_db)... ";
    ob_flush(); flush();

    $avg_hh_ses_query = "SELECT AVG(sesScore) as avg_barangay_ses FROM household WHERE barangayID = ? AND sesScore IS NOT NULL";
    $stmt_avg_brgy = mysqli_prepare($conn, $avg_hh_ses_query);

    if ($stmt_avg_brgy) {
        mysqli_stmt_bind_param($stmt_avg_brgy, "s", $barangayID);
        mysqli_stmt_execute($stmt_avg_brgy);
        $avg_hh_ses_result = mysqli_stmt_get_result($stmt_avg_brgy);

        if ($avg_hh_ses_row = mysqli_fetch_assoc($avg_hh_ses_result)) {
            if ($avg_hh_ses_row['avg_barangay_ses'] !== null) {
                $all_barangay_raw_scores[$barangayID] = (float)$avg_hh_ses_row['avg_barangay_ses'];
                echo "Raw Avg. SES: <span class='success'>" . number_format($all_barangay_raw_scores[$barangayID], 4) . "</span></li>";
            } else {
                echo "<span class='info'>No households with SES scores found or average is NULL.</span></li>";
                 $all_barangay_raw_scores[$barangayID] = null; // Ensure it's set if no scores
            }
        } else {
             echo "<span class='error'>Could not fetch average.</span></li>";
             $all_barangay_raw_scores[$barangayID] = null; // Ensure it's set on fetch error
        }
        mysqli_free_result($avg_hh_ses_result);
        mysqli_stmt_close($stmt_avg_brgy);
    } else {
        echo "<span class='error'>Error preparing statement to fetch average household SES: " . mysqli_error($conn) . "</span></li>";
    }
}
mysqli_free_result($barangay_ids_result_for_ses);
echo "</ul>";
echo "<p class='info'>Finished calculating raw average SES for " . count(array_filter($all_barangay_raw_scores, 'is_numeric')) . " barangays that have household data with scores.</p>";
echo "<hr>";
ob_flush(); flush();

// --- Stage 3: Normalize Barangay SES Scores ---
echo "<h2>Stage 3: Normalizing Barangay SES Scores (Range 0.01 - 10.00)</h2>";
echo "<p class='info'>Higher normalized score indicates greater need/vulnerability. Uses averages calculated in Stage 2.</p>";
ob_flush(); flush();

$barangays_normalized_count = 0;

if (!empty($all_barangay_raw_scores)) {
    $valid_scores_for_norm = array_filter($all_barangay_raw_scores, 'is_numeric');

    if (!empty($valid_scores_for_norm)) {
        $min_raw_score = min($valid_scores_for_norm);
        $max_raw_score = max($valid_scores_for_norm);
        $raw_score_range = $max_raw_score - $min_raw_score;

        $desired_min = 0.01;
        $desired_max = 10.00;
        $desired_range = $desired_max - $desired_min;

        echo "<p>Raw Score Details for Normalization - Min: " . number_format($min_raw_score, 4) . ", Max: " . number_format($max_raw_score, 4) . ", Range: " . number_format($raw_score_range, 4) . "</p>";
        echo "<ul>";
        ob_flush(); flush();

        $all_barangay_ids_for_update_query = "SELECT BarangayID, BarangayName FROM barangay ORDER By BarangayName";
        $all_barangay_ids_for_update_result = mysqli_query($conn, $all_barangay_ids_for_update_query);

        if (!$all_barangay_ids_for_update_result) {
            echo "<p class='error'>FATAL ERROR: Could not fetch all barangay IDs for normalization update: " . mysqli_error($conn) . "</p>";
            echo "</body></html>";
            exit;
        }
        $total_brgys_to_norm = mysqli_num_rows($all_barangay_ids_for_update_result);
        $brgy_norm_count = 0;

        while ($brgy_update_row = mysqli_fetch_assoc($all_barangay_ids_for_update_result)) {
            $current_barangayID_for_update = $brgy_update_row['BarangayID'];
            $current_barangayName_for_update = $brgy_update_row['BarangayName'];
            $normalized_ses = null;
            $brgy_norm_count++;

            echo "<li>Normalizing Barangay: <strong>" . htmlspecialchars($current_barangayName_for_update) . " (ID: " . htmlspecialchars($current_barangayID_for_update) . ")</strong> ($brgy_norm_count/$total_brgys_to_norm)... ";
            ob_flush(); flush();

            if (isset($all_barangay_raw_scores[$current_barangayID_for_update]) && is_numeric($all_barangay_raw_scores[$current_barangayID_for_update])) {
                $raw_score = $all_barangay_raw_scores[$current_barangayID_for_update];
                if ($raw_score_range == 0) { // Avoid division by zero if all raw scores are identical
                    $normalized_ses = $desired_min + ($desired_range / 2); // Assign midpoint
                } else {
                    $normalized_ses = $desired_min + (($raw_score - $min_raw_score) * $desired_range / $raw_score_range);
                }
                $normalized_ses = round($normalized_ses, 2); // Round to 2 decimal places
                echo "Raw: " . number_format($raw_score, 4) . ", Calculated Normalized: <span class='success'>" . $normalized_ses . "</span>";
            } else {
                echo "<span class='info'>No valid raw score found, SES will be set to NULL.</span>";
                $normalized_ses = null; // Explicitly set to null for binding
            }

            // Specific debug for Sapa (ID '4110-28') BEFORE update attempt
            if ($current_barangayID_for_update === '4110-28') {
                $sapa_debug_sql = "SELECT SES FROM barangay WHERE BarangayID = ?";
                $stmt_sapa_debug = mysqli_prepare($conn, $sapa_debug_sql);
                if ($stmt_sapa_debug) {
                    mysqli_stmt_bind_param($stmt_sapa_debug, "s", $current_barangayID_for_update);
                    mysqli_stmt_execute($stmt_sapa_debug);
                    $sapa_debug_result = mysqli_stmt_get_result($stmt_sapa_debug);
                    if ($sapa_debug_row = mysqli_fetch_assoc($sapa_debug_result)) {
                        echo " -- <span class='debug'>SAPA DEBUG (Current DB SES before this update): " . htmlspecialchars($sapa_debug_row['SES']) . "</span>";
                    } else {
                        echo " -- <span class='debug'>SAPA DEBUG: Sapa (ID " . htmlspecialchars($current_barangayID_for_update) . ") not found in DB before update or SES is NULL.</span>";
                    }
                    mysqli_free_result($sapa_debug_result);
                    mysqli_stmt_close($stmt_sapa_debug);
                } else {
                     echo " -- <span class='debug'>SAPA DEBUG: Failed to prepare statement to check Sapa's current SES.</span>";
                }
            }

            $update_brgy_sql = "UPDATE barangay SET SES = ? WHERE BarangayID = ?";
            $stmt_brgy_update = mysqli_prepare($conn, $update_brgy_sql);
            if ($stmt_brgy_update) {
                mysqli_stmt_bind_param($stmt_brgy_update, "ds", $normalized_ses, $current_barangayID_for_update);
                if (mysqli_stmt_execute($stmt_brgy_update)) {
                    $affected_rows = mysqli_stmt_affected_rows($stmt_brgy_update);
                    
                    if ($affected_rows > 0) {
                        echo " - DB Update: <span class='success'>Success</span> (Affected Rows: " . $affected_rows . ")</li>";
                        if ($normalized_ses !== null) { // Only count if a non-null score was successfully applied and affected rows
                           $barangays_normalized_count++;
                        }
                    } else if ($affected_rows == 0) {
                         echo " - DB Update: <span class='info'>Executed, but 0 rows affected.</span> (Value might have been the same, or BarangayID not found)</li>";
                    } else { // Should not happen if execute returned true, but as a fallback
                         echo " - DB Update: <span class='info'>Executed, status of affected rows unknown (mysqli_stmt_affected_rows returned " . $affected_rows . ").</span></li>";
                    }

                    // Specific debug for Sapa (ID '4110-28') AFTER update attempt
                    if ($current_barangayID_for_update === '4110-28') {
                        $sapa_after_debug_sql = "SELECT SES FROM barangay WHERE BarangayID = ?";
                        $stmt_sapa_after_debug = mysqli_prepare($conn, $sapa_after_debug_sql);
                        if ($stmt_sapa_after_debug) {
                            mysqli_stmt_bind_param($stmt_sapa_after_debug, "s", $current_barangayID_for_update);
                            mysqli_stmt_execute($stmt_sapa_after_debug);
                            $sapa_after_debug_result = mysqli_stmt_get_result($stmt_sapa_after_debug);
                            if ($sapa_after_debug_row = mysqli_fetch_assoc($sapa_after_debug_result)) {
                                echo "<br />   -- <span class='debug'>SAPA DEBUG (DB SES after this update attempt): " . htmlspecialchars($sapa_after_debug_row['SES']) . "</span>";
                            } else {
                                echo "<br />   -- <span class='debug'>SAPA DEBUG: Sapa (ID " . htmlspecialchars($current_barangayID_for_update) . ") not found in DB after update or SES is NULL.</span>";
                            }
                            mysqli_free_result($sapa_after_debug_result);
                            mysqli_stmt_close($stmt_sapa_after_debug);
                        } else {
                            echo "<br />   -- <span class='debug'>SAPA DEBUG: Failed to prepare statement to check Sapa's SES after update.</span>";
                        }
                    }

                } else {
                    echo " - DB Update: <span class='error'>Failed to Execute</span> (" . mysqli_stmt_error($stmt_brgy_update) . ")</li>";
                }
                mysqli_stmt_close($stmt_brgy_update);
            } else {
                echo " - <span class='error'>Error preparing barangay normalized SES update: " . mysqli_error($conn) . "</span></li>";
            }
        }
        mysqli_free_result($all_barangay_ids_for_update_result);
        echo "</ul>";
        echo "<p class='info'>Finished normalizing and attempting to update barangay SES scores.<br>";
        echo "Number of barangays where SES was updated with a non-null normalized score and affected rows: " . $barangays_normalized_count . "</p>";
    } else {
        echo "<p class='info'>No valid raw SES scores found from households to normalize for any barangay. No barangay SES values were updated.</p>";
    }
} else {
    echo "<p class='info'>No barangay raw scores were calculated (e.g., no households with scores found in Stage 2) to proceed with normalization.</p>";
}

echo "<hr>";
echo "<h2>Process Complete</h2>";
echo "<p>All stages finished at " . date('Y-m-d H:i:s') . ". Check output above for details and any errors.</p>";

mysqli_close($conn);
echo "</body></html>";
?>