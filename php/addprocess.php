<?php
// php/addprocess.php
session_start(); // Must be first

require_once __DIR__ . '/config.php';        // For $conn
require_once __DIR__ . '/ses_calculation_logic.php'; // For getNumericIncomeFromString & calculateAndUpdateHouseholdSES

// --- Authentication & Role Check ---
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'superadmin')) {
    $_SESSION['error_message'] = "You do not have permission to perform this action.";
    header("Location: addHousehold.php"); // Redirect to the form page
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // --- CSRF Protection ---
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $_SESSION['error_message'] = "Invalid security token. Please submit the form again.";
        unset($_SESSION['form_data']); // Do NOT keep form data on CSRF failure
        // The form page (addHousehold.php) should regenerate a CSRF token.
        header("Location: addHousehold.php");
        exit;
    }
    unset($_SESSION['csrf_token']); // Consume the token

    // --- Retrieve and Sanitize Form Data ---
    $householdData = [
        'barangayID'    => $_POST['barangayID'] ?? null,
        'householdID'   => $_POST['householdID'] ?? null,
        'householdName' => isset($_POST['householdName']) ? trim($_POST['householdName']) : null
    ];

    $submitted_members_from_form = $_POST['members'] ?? [];
    $final_members_for_db_insert = [];
    $errors = []; // Array to hold validation errors

    // --- Validate Household Data ---
    if (empty($householdData['barangayID'])) {
        $errors[] = "Barangay selection is required.";
    }
    if (empty($householdData['householdID'])) {
        $errors[] = "Household ID is missing.";
    }
    if (empty($householdData['householdName'])) {
        $errors[] = "Household name is required.";
    }
    // ** ADD YOUR OTHER SPECIFIC VALIDATION RULES FOR HOUSEHOLD DATA HERE **

    // --- Process and Validate Members ---
    if (empty($submitted_members_from_form)) {
        $errors[] = "No member data submitted. At least the Head of household is required.";
    } else {
        foreach ($submitted_members_from_form as $index => $member_input) {
            if (empty(array_filter(array_values($member_input)))) continue; // Skip entirely empty member rows

            // Retrieve raw name data and trim whitespace
            $raw_fname = trim($member_input['fname'] ?? '');
            $raw_mname = trim($member_input['mname'] ?? '');
            $raw_lname = trim($member_input['lname'] ?? '');

            // *** APPLY TITLE CASE CONVERSION TO NAMES ***
            $db_fname = !empty($raw_fname) ? mb_convert_case($raw_fname, MB_CASE_TITLE, "UTF-8") : '';
            $db_mname = !empty($raw_mname) ? mb_convert_case($raw_mname, MB_CASE_TITLE, "UTF-8") : '';
            $db_lname = !empty($raw_lname) ? mb_convert_case($raw_lname, MB_CASE_TITLE, "UTF-8") : '';
            // *** END OF TITLE CASE CONVERSION ***

            $numeric_income = getNumericIncomeFromString($member_input['income'] ?? null);

            $current_member_data = [
                'relationship'   => trim($member_input['relationship'] ?? ''),
                'fname'          => $db_fname, // Use the Title Cased version
                'mname'          => $db_mname, // Use the Title Cased version
                'lname'          => $db_lname, // Use the Title Cased version
                'sex'            => $member_input['sex'] ?? '',
                'bday'           => $member_input['bday'] ?? '',
                'marital_status' => $member_input['marital_status'] ?? '',
                'education'      => $member_input['education'] ?? '',
                'occupation'     => (isset($member_input['occupation']) && trim($member_input['occupation']) !== '') ? trim($member_input['occupation']) : null,
                'income_numeric' => $numeric_income,
                'health'         => $member_input['health'] ?? ''
            ];
            
            // Convert "Child" relationship based on sex for storage (Son/Daughter)
            if ($current_member_data['relationship'] === 'Child') {
                $current_member_data['relationship'] = ($current_member_data['sex'] === 'Male') ? 'Son' : 'Daughter';
            }

            // ** YOUR DETAILED VALIDATION FOR EACH MEMBER FIELD CONTINUES HERE **
            $memberNum = $index + 1;
            if (empty($current_member_data['fname'])) $errors[] = "First name is required for member #$memberNum.";
            // Middle name might be optional, so don't validate if empty unless it's truly required
            // if (empty($current_member_data['mname'])) $errors[] = "Middle name is required for member #$memberNum."; 
            if (empty($current_member_data['lname'])) $errors[] = "Last name is required for member #$memberNum.";
            if (empty($current_member_data['sex'])) $errors[] = "Sex is required for member #$memberNum.";
            if (empty($current_member_data['bday'])) {
                $errors[] = "Birthdate is required for member #$memberNum.";
            } else {
                $d = DateTime::createFromFormat('Y-m-d', $current_member_data['bday']);
                if (!$d || $d->format('Y-m-d') !== $current_member_data['bday']) {
                    $errors[] = "Invalid birthdate format for member #$memberNum (use YYYY-MM-DD).";
                }
            }
            if (empty($current_member_data['marital_status'])) $errors[] = "Marital status is required for member #$memberNum.";
            if (empty($current_member_data['education'])) $errors[] = "Education level is required for member #$memberNum.";
            if (empty($current_member_data['health'])) $errors[] = "Health condition is required for member #$memberNum.";

            if ($current_member_data['relationship'] === 'Head' || $current_member_data['relationship'] === 'Spouse') {
                if ($current_member_data['occupation'] === null) {
                    $errors[] = "Occupation is required for the " . htmlspecialchars($current_member_data['relationship']) . ".";
                }
                if ($current_member_data['income_numeric'] === null && empty($member_input['income'])) {
                     $errors[] = "Income is required for the " . htmlspecialchars($current_member_data['relationship']) . ".";
                }
            }
            // ** End of member validation block **

            $final_members_for_db_insert[] = $current_member_data;
        }
        if (empty($final_members_for_db_insert) && empty($errors) && !empty($submitted_members_from_form)) {
             $errors[] = "Valid member data is required.";
        }
    }

    // --- Database Operations ---
    if (empty($errors)) {
        $conn->begin_transaction();
        try {
            // 1. Insert Household
            $stmtHousehold = $conn->prepare("INSERT INTO household (householdID, barangayID, barangayName, householdname, sesScore) VALUES (?, ?, (SELECT BarangayName FROM barangay WHERE BarangayID = ? LIMIT 1), ?, NULL)");
            if ($stmtHousehold === false) throw new Exception("Database Error: Could not prepare household insert statement. " . $conn->error);
            
            $stmtHousehold->bind_param("ssss",
                $householdData['householdID'],
                $householdData['barangayID'],
                $householdData['barangayID'], // Parameter for the subquery
                $householdData['householdName']
            );
            if (!$stmtHousehold->execute()) {
                if ($stmtHousehold->errno == 1062) {
                    throw new Exception("Household ID '{$householdData['householdID']}' already exists.", 1062);
                }
                throw new Exception("Database Error: Could not execute household insert. " . $stmtHousehold->error, $stmtHousehold->errno);
            }
            $stmtHousehold->close();

            // 2. Insert Members
            $stmtMember = $conn->prepare("INSERT INTO beneficiaries (householdID, relationship, fname, mname, lname, sex, bday, age, marital_status, education, occupation, income, health, status) VALUES (?, ?, ?, ?, ?, ?, ?, TIMESTAMPDIFF(YEAR, ?, CURDATE()), ?, ?, ?, ?, ?, 'Active')");
            if ($stmtMember === false) throw new Exception("Database Error: Could not prepare member insert statement. " . $conn->error);

            foreach ($final_members_for_db_insert as $member_to_insert) {
                $stmtMember->bind_param("ssssssssssdss", // 'd' for income_numeric (DECIMAL)
                    $householdData['householdID'], $member_to_insert['relationship'],
                    $member_to_insert['fname'], $member_to_insert['mname'], $member_to_insert['lname'], // These are now Title Cased
                    $member_to_insert['sex'], $member_to_insert['bday'], $member_to_insert['bday'], // For TIMESTAMPDIFF
                    $member_to_insert['marital_status'], $member_to_insert['education'],
                    $member_to_insert['occupation'], $member_to_insert['income_numeric'],
                    $member_to_insert['health']
                );
                if (!$stmtMember->execute()) throw new Exception("Database Error: Could not insert member (" . htmlspecialchars($member_to_insert['fname']) . "). " . $stmtMember->error, $stmtMember->errno);
            }
            $stmtMember->close();

            // 3. Update Household and Barangay Counts
            $stmtHouseholdCount = $conn->prepare("CALL update_household_counts(?)");
            if ($stmtHouseholdCount) { $stmtHouseholdCount->bind_param("s", $householdData['householdID']); $stmtHouseholdCount->execute(); $stmtHouseholdCount->close(); }
            else { error_log("Warning: Could not prepare update_household_counts procedure: " . $conn->error); }

            $stmtBarangayCount = $conn->prepare("CALL update_barangay_counts(?)");
            if($stmtBarangayCount) { $stmtBarangayCount->bind_param("s", $householdData['barangayID']); $stmtBarangayCount->execute(); $stmtBarangayCount->close(); }
            else { error_log("Warning: Could not prepare update_barangay_counts procedure: " . $conn->error); }

            // 4. Calculate and Update SES Score for the newly added household
            if (!calculateAndUpdateHouseholdSES($householdData['householdID'], $conn)) {
                error_log("Warning: SES score calculation failed for newly added HHID {$householdData['householdID']}. It will be N/A until next global update.");
            }

            // 5. COMMIT Transaction
            if (!$conn->commit()) {
                throw new Exception("Database Transaction Error: Commit failed. " . $conn->error);
            }

            $_SESSION['success_message'] = "Household '" . htmlspecialchars($householdData['householdName']) . "' (ID: " . htmlspecialchars($householdData['householdID']) . ") added successfully.";
            unset($_SESSION['error_message'], $_SESSION['validation_errors'], $_SESSION['form_data']);

        } catch (Exception $e) {
            $conn->rollback();
            error_log("DATABASE TRANSACTION ERROR in addprocess.php: " . $e->getMessage() . " (Code: " . $e->getCode() . ")");
            
            $userErrorMessage = "Failed to add household due to a server problem.";
            if ($e->getCode() == 1062) {
                $userErrorMessage = "Failed to add household: The Household ID '".htmlspecialchars($householdData['householdID'])."' already exists. Please select the barangay again to generate a new ID or check existing records.";
            } elseif ($e->getCode() == 1452) { // Foreign key constraint
                 $userErrorMessage = "Failed to add household: Invalid Barangay selected or related data missing.";
            } else {
                 $userErrorMessage .= " Please check your data or try again later. Details: " . htmlspecialchars($e->getMessage());
            }
            
            $_SESSION['error_message'] = $userErrorMessage;
            $_SESSION['form_data'] = $_POST;
            unset($_SESSION['success_message'], $_SESSION['validation_errors']);
        }
    } else {
        // Validation errors occurred
        $_SESSION['validation_errors'] = $errors;
        $_SESSION['form_data'] = $_POST;
        unset($_SESSION['success_message'], $_SESSION['error_message']);
    }

    header("Location: addHousehold.php"); // Redirect back to the form page
    exit;

} else {
    // Request method is not POST
    $_SESSION['error_message'] = "Invalid access method. Please submit the form correctly.";
    header("Location: addHousehold.php");
    exit;
}
?>