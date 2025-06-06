<?php
// php/ses_calculation_logic.php

// This file contains the core logic for SES calculation and related helper functions.
// It should be included by scripts that need to calculate SES scores.

// --- Scoring Rubrics (Higher score = more vulnerable/greater need) ---
$education_scores_rubric = [
    'No Education' => 4,
    'Elementary' => 3,
    'High School' => 2,
    'College' => 1,
    'default_edu_score' => 4 // Default for missing/unexpected data
];

$occupation_group_scores_rubric = [
    'Very Low Skill/Informal/Lowest Income' => 4,
    'Low Skill/Informal or Entry-Level/Low Income' => 3,
    'Semi-Skilled/Established/Moderate Income' => 2,
    'Skilled/Established Business/Potentially Higher Income' => 1
    // Add more specific occupation group scores if needed
];

$occupation_mapping_rubric = [
    'Unemployed' => 'Very Low Skill/Informal/Lowest Income',
    'No Regular Occupation' => 'Very Low Skill/Informal/Lowest Income',
    'Agricultural Labor/Seasonal Worker' => 'Very Low Skill/Informal/Lowest Income',
    'Small-Scale Fisherman/Fisherwoman' => 'Very Low Skill/Informal/Lowest Income',
    'Laundry Service' => 'Very Low Skill/Informal/Lowest Income',
    'Waste Picker/Scavenger' => 'Very Low Skill/Informal/Lowest Income',
    'Household Helper' => 'Very Low Skill/Informal/Lowest Income',
    'Street Vendor' => 'Low Skill/Informal or Entry-Level/Low Income',
    'Market Vendor' => 'Low Skill/Informal or Entry-Level/Low Income',
    'Construction Laborer/Helper' => 'Low Skill/Informal or Entry-Level/Low Income',
    'Tricycle/Jeepney Driver' => 'Low Skill/Informal or Entry-Level/Low Income',
    'Entry-Level Factory Worker' => 'Low Skill/Informal or Entry-Level/Low Income',
    'Non-Professional Caregiver' => 'Low Skill/Informal or Entry-Level/Low Income',
    'Home-based Craft Production' => 'Low Skill/Informal or Entry-Level/Low Income',
    'Carpenter/Mason' => 'Semi-Skilled/Established/Moderate Income',
    'Hairdresser/Barber (Established Small Stall/Home-Based)' => 'Semi-Skilled/Established/Moderate Income',
    'Manicurist/Pedicurist (Established Small Stall/Home-Based)' => 'Semi-Skilled/Established/Moderate Income',
    'Market Vendor (Established)' => 'Semi-Skilled/Established/Moderate Income',
    'Tricycle/Jeepney Driver (More Regular)' => 'Semi-Skilled/Established/Moderate Income',
    'Skilled Factory Worker' => 'Semi-Skilled/Established/Moderate Income',
    'Small Sari-Sari Store Owner (Developing)' => 'Semi-Skilled/Established/Moderate Income',
    'Food Stall Operator (Developing)' => 'Semi-Skilled/Established/Moderate Income',
    'Established Small Sari-Sari Store Owner' => 'Skilled/Established Business/Potentially Higher Income',
    'Established Food Stall Operator (Good Income)' => 'Skilled/Established Business/Potentially Higher Income',
    'Skilled Tradesperson (Regular Contracts)' => 'Skilled/Established Business/Potentially Higher Income',
    'Small Business Owner (Micro-enterprise with steady income)' => 'Skilled/Established Business/Potentially Higher Income',
    'Teacher' => 'Skilled/Established Business/Potentially Higher Income',
    // Add ALL your occupation mappings
    'default_occ_score_parent_group' => 'Very Low Skill/Informal/Lowest Income'
];

$health_scores_rubric = [
    'Severe Illness' => 4,
    'Significant Illness' => 3,
    'Managed Illness' => 2,
    'No Illness' => 1,
    'default_health_score' => 1 // Or 4 if unknown is considered more vulnerable
];

// Weights for SES components
$W_edu_rubric = 1;    // Example weight for education
$W_health_rubric = 1; // Example weight for health
$W_econ_rubric = 1;   // Example weight for economic factors (occupation & income combined)

/**
 * Converts income string ranges from form select options to a representative numeric value.
 * @param string|null $incomeString The income range string (e.g., "PHP 5,001 - PHP 10,000").
 * @return float|null Representative numeric income or null if not recognized or empty.
 */
function getNumericIncomeFromString($incomeString) {
    if ($incomeString === null || trim($incomeString) === '') return null;
    switch (trim($incomeString)) {
        case 'Below PHP 5,000':       return 4999.00;  // Or 2500, or min poverty threshold
        case 'PHP 5,001 - PHP 10,000':  return 7500.00;  // Midpoint or upper bound
        case 'PHP 10,001 - PHP 15,000': return 12500.00;
        case 'PHP 15,001 - PHP 20,000': return 17500.00;
        case 'Above PHP 20,000':        return 25000.00; // Or a higher representative value like 30000
        default:
            // Attempt to parse if it's already a number (e.g. "10000.00")
            if (is_numeric($incomeString)) return (float)$incomeString;
            return null; // Unrecognized string
    }
}

/**
 * Calculates an income category score based on numeric monthly income.
 * Higher score means more vulnerable.
 * @param float|null $decimal_income Numeric monthly household income.
 * @return int Score from 1 to 5.
 */
function getIncomeCategoryScoreForSES($decimal_income) {
    if ($decimal_income === null) return 5; // Most vulnerable for unknown income

    if ($decimal_income <= 5000) return 5;
    else if ($decimal_income <= 10000) return 4;
    else if ($decimal_income <= 15000) return 3;
    else if ($decimal_income <= 20000) return 2;
    else return 1; // Income > 20000
}

/**
 * Calculates and updates the SES score for a single household.
 * Stores the calculated score in the `household.sesScore` field.
 * @param string $householdID_to_calculate The ID of the household.
 * @param mysqli $db_connection The active database connection object.
 * @return bool True on successful update, False on failure.
 */
function calculateAndUpdateHouseholdSES($householdID_to_calculate, $db_connection) {
    // Make rubrics and weights accessible within this function
    global $education_scores_rubric, $occupation_group_scores_rubric, $occupation_mapping_rubric,
           $health_scores_rubric, $W_edu_rubric, $W_health_rubric, $W_econ_rubric;

    $beneficiaries_query = "SELECT relationship, education, occupation, income, health FROM beneficiaries WHERE householdID = ?";
    $stmt_beneficiaries = mysqli_prepare($db_connection, $beneficiaries_query);

    if (!$stmt_beneficiaries) {
        error_log("SES CALC (HHID: $householdID_to_calculate): Prepare failed for beneficiaries query: " . mysqli_error($db_connection));
        return false;
    }
    mysqli_stmt_bind_param($stmt_beneficiaries, "s", $householdID_to_calculate);
    if (!mysqli_stmt_execute($stmt_beneficiaries)) {
        error_log("SES CALC (HHID: $householdID_to_calculate): Execute failed for beneficiaries query: " . mysqli_stmt_error($stmt_beneficiaries));
        mysqli_stmt_close($stmt_beneficiaries);
        return false;
    }
    $beneficiaries_result = mysqli_stmt_get_result($stmt_beneficiaries);

    if (!$beneficiaries_result) {
        error_log("SES CALC (HHID: $householdID_to_calculate): Get result failed for beneficiaries query: " . mysqli_stmt_error($stmt_beneficiaries));
        mysqli_stmt_close($stmt_beneficiaries);
        return false;
    }

    $sum_edu_scores = 0;
    $sum_health_scores = 0;
    $member_count_for_edu_health = 0;
    $sum_parent_economic_contributions = 0;
    $parent_count = 0;
    $household_ses_score = null; // Default to null

    if (mysqli_num_rows($beneficiaries_result) > 0) {
        while ($ben_row = mysqli_fetch_assoc($beneficiaries_result)) {
            $member_count_for_edu_health++;

            // Education Score
            $edu_level = $ben_row['education'];
            $edu_score = $education_scores_rubric[$edu_level] ?? $education_scores_rubric['default_edu_score'];
            $sum_edu_scores += $edu_score;

            // Health Score
            $health_status = $ben_row['health'];
            $health_score = $health_scores_rubric[$health_status] ?? $health_scores_rubric['default_health_score'];
            $sum_health_scores += $health_score;

            // Economic Score (for Head/Spouse)
            if ($ben_row['relationship'] === 'Head' || $ben_row['relationship'] === 'Spouse') {
                $parent_count++;
                $occupation = $ben_row['occupation'];
                $occ_group_key = $occupation_mapping_rubric[$occupation] ?? $occupation_mapping_rubric['default_occ_score_parent_group'];
                $occ_score = $occupation_group_scores_rubric[$occ_group_key] ?? 4; // Default to most vulnerable if mapping fails

                // $ben_row['income'] should be numeric from the database (DECIMAL type)
                $decimal_income = ($ben_row['income'] !== null) ? (float)$ben_row['income'] : null;
                $inc_score = getIncomeCategoryScoreForSES($decimal_income);
                
                $sum_parent_economic_contributions += ($occ_score + $inc_score); // Combined economic score for a parent
            }
        }
        mysqli_free_result($beneficiaries_result);

        // Calculate Averages
        $avg_household_edu_score = ($member_count_for_edu_health > 0) ? ($sum_edu_scores / $member_count_for_edu_health) : $education_scores_rubric['default_edu_score'];
        $avg_household_health_score = ($member_count_for_edu_health > 0) ? ($sum_health_scores / $member_count_for_edu_health) : $health_scores_rubric['default_health_score'];
        
        // Average economic component from parents, or default if no parents
        $avg_parent_economic_component = ($occupation_group_scores_rubric[$occupation_mapping_rubric['default_occ_score_parent_group']] + getIncomeCategoryScoreForSES(null)); // Default max vulnerability
        if ($parent_count > 0) {
            $avg_parent_economic_component = $sum_parent_economic_contributions / $parent_count;
        }
        
        // Final Weighted Household SES Score
        $household_ses_score = ($avg_household_edu_score * $W_edu_rubric) +
                               ($avg_household_health_score * $W_health_rubric) +
                               ($avg_parent_economic_component * $W_econ_rubric);
    } else {
        // No beneficiaries found for household - assign a default high vulnerability score
        $household_ses_score = ($education_scores_rubric['default_edu_score'] * $W_edu_rubric) +
                               ($health_scores_rubric['default_health_score'] * $W_health_rubric) +
                               (($occupation_group_scores_rubric[$occupation_mapping_rubric['default_occ_score_parent_group']] + getIncomeCategoryScoreForSES(null)) * $W_econ_rubric);
    }
    mysqli_stmt_close($stmt_beneficiaries);

    // Update household table with the calculated score
    if ($household_ses_score !== null) {
        $update_hh_sql = "UPDATE household SET sesScore = ? WHERE householdID = ?";
        $stmt_hh_update = mysqli_prepare($db_connection, $update_hh_sql);
        if ($stmt_hh_update) {
            mysqli_stmt_bind_param($stmt_hh_update, "ds", $household_ses_score, $householdID_to_calculate); // 'd' for double
            $exec_result = mysqli_stmt_execute($stmt_hh_update);
            mysqli_stmt_close($stmt_hh_update);

            if (!$exec_result) {
                error_log("SES CALC (HHID: $householdID_to_calculate): Failed to update sesScore in DB: " . mysqli_error($db_connection));
                return false;
            }
            return true; // Successfully calculated and updated
        } else {
            error_log("SES CALC (HHID: $householdID_to_calculate): Prepare failed for household update: " . mysqli_error($db_connection));
            return false;
        }
    }
    error_log("SES CALC (HHID: $householdID_to_calculate): household_ses_score was NULL, not updated.");
    return false; // Score was null or update failed
}
?>