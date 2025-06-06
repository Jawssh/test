<?php
// php/editprocess.php
header('Content-Type: application/json'); // Ensure this is the very first output
session_start();

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/ses_calculation_logic.php'; // For getNumericIncomeFromString & calculateAndUpdateHouseholdSES

$response_data = ['success' => false, 'message' => 'An unexpected error occurred during edit.'];

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'superadmin')) {
    $response_data['message'] = "Unauthorized access. Please log in with appropriate permissions.";
    echo json_encode($response_data);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Token Validation
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['edit_csrf_token']) || !hash_equals($_SESSION['edit_csrf_token'], $_POST['csrf_token'])) {
        $response_data['message'] = "Invalid security token. Please refresh the page and try again.";
        echo json_encode($response_data);
        exit;
    }
    unset($_SESSION['edit_csrf_token']); // Consume the token

    // --- Retrieve Form Data ---
    $householdID_original = $_POST['householdID_original'] ?? null;
    $householdData_submitted = [
        'barangayID' => $_POST['barangayID'] ?? null,
        'householdname' => isset($_POST['householdName']) ? trim($_POST['householdName']) : null
    ];
    $members_submitted_from_form = $_POST['members'] ?? [];
    $deleted_member_ids_string = $_POST['deleted_members'] ?? '';
    $deleted_member_ids = array_filter(array_map('trim', explode(',', $deleted_member_ids_string)));
    $errors = [];

    // --- Basic Household Data Validation ---
    if (empty($householdID_original)) {
        $errors[] = "Original Household ID is missing. Cannot process update.";
    }
    if (empty($householdData_submitted['barangayID'])) {
        $errors[] = "Barangay selection is required.";
    }
    if (empty($householdData_submitted['householdname'])) {
        $errors[] = "Household name is required.";
    }
    // ** ADD YOUR OTHER SPECIFIC VALIDATION RULES FOR HOUSEHOLD DATA HERE **
    // e.g., length checks, format checks for householdname if any.

    // --- Process and Validate Members ---
    $final_members_to_process_in_db = [];
    $active_member_count_after_edit = 0;

    if (empty($members_submitted_from_form) && empty($deleted_member_ids)) {
        $errors[] = "No member data or deletion instructions received.";
    } else {
        foreach ($members_submitted_from_form as $index => $member_input) {
            $memberID = $member_input['memberID'] ?? null;

            // If this memberID is in the explicit delete list from JS, skip further processing for update/insert
            if ($memberID && in_array($memberID, $deleted_member_ids)) {
                continue; 
            }
            
            // If a member was marked with a 'delete' flag by JS but NOT in hidden deleted_members list 
            // (e.g. a NEWLY added then removed row before save) and doesn't have a memberID, it's effectively not submitted.
            if (isset($member_input['delete']) && $member_input['delete'] === '1' && empty($memberID)) {
                continue; // Skip this phantom new-then-deleted member
            }

            $is_existing_member = !empty($memberID) && isset($member_input['existing']) && $member_input['existing'] === '1';
            $current_member_data = [
                'memberID'       => $memberID,
                'existing'       => $is_existing_member ? '1' : '0',
                'relationship'   => trim($member_input['relationship'] ?? ''),
                'fname'          => trim($member_input['fname'] ?? ''),
                'mname'          => trim($member_input['mname'] ?? null), // Allow null mname
                'lname'          => trim($member_input['lname'] ?? ''),
                'sex'            => $member_input['sex'] ?? '',
                'bday'           => $member_input['bday'] ?? '',
                'marital_status' => $member_input['marital_status'] ?? '',
                'education'      => $member_input['education'] ?? '',
                'occupation'     => (isset($member_input['occupation']) && trim($member_input['occupation']) !== '') ? trim($member_input['occupation']) : null,
                'income_numeric' => getNumericIncomeFromString($member_input['income'] ?? null),
                'health'         => $member_input['health'] ?? ''
            ];
            
            if ($current_member_data['relationship'] === 'Child') { // Assuming 'Child' is a generic option from form
                $current_member_data['relationship'] = ($current_member_data['sex'] === 'Male') ? 'Son' : 'Daughter';
            }

            // ** ADD YOUR DETAILED VALIDATION FOR EACH MEMBER FIELD HERE **
            if (empty($current_member_data['fname'])) $errors[] = "First name is required for member entry #" . ($index + 1) . ".";
            if (empty($current_member_data['lname'])) $errors[] = "Last name is required for member entry #" . ($index + 1) . ".";
            if (empty($current_member_data['sex'])) $errors[] = "Sex is required for member entry #" . ($index + 1) . ".";
            if (empty($current_member_data['bday'])) $errors[] = "Birthdate is required for member entry #" . ($index + 1) . ".";
            // Add more validation for bday format, enum values for relationship, sex, marital_status, education, health
            if (empty($current_member_data['education'])) $errors[] = "Education level is required for member entry #" . ($index + 1) . ".";
            if (empty($current_member_data['health'])) $errors[] = "Health condition is required for member entry #" . ($index + 1) . ".";

            if ($current_member_data['relationship'] === 'Head' || $current_member_data['relationship'] === 'Spouse') {
                if ($current_member_data['occupation'] === null) $errors[] = "Occupation is required for " . $current_member_data['relationship'] . " (member entry #" . ($index + 1) . ").";
                // Income validation for Head/Spouse might be more complex (e.g., allow 0 but not empty if field was shown)
                if ($current_member_data['income_numeric'] === null && (!isset($member_input['income']) || trim($member_input['income']) === '' )) {
                     $errors[] = "Income is required for " . $current_member_data['relationship'] . " (member entry #" . ($index + 1) . ").";
                }
            }
            // ** End of member validation block **

            $final_members_to_process_in_db[] = $current_member_data;
            $active_member_count_after_edit++;
        }

        if ($active_member_count_after_edit === 0 && empty($deleted_member_ids) && !empty($members_submitted_from_form) ) {
            $errors[] = "A household must have at least one active member after processing edits.";
        } elseif (empty($final_members_to_process_in_db) && empty($deleted_member_ids)) {
            // This case means no members to update/insert AND no members to delete.
            // If $members_submitted_from_form was not empty, it implies all were invalid or filtered out.
            if (!empty($members_submitted_from_form)) {
                 $errors[] = "No valid member data found to process for update/insert, and no members marked for deletion.";
            } else {
                // If $members_submitted_from_form was empty AND $deleted_member_ids was empty, this was caught earlier.
                // This specific elseif might be redundant if the first $errors[] condition for "No member data..." is robust.
            }
        }
    }

    // --- Database Operations ---
    if (empty($errors)) {
        $conn->begin_transaction();
        try {
            // 1. Update Household Information
            $stmt_h_update = $conn->prepare("UPDATE household SET barangayID = ?, barangayName = (SELECT BarangayName FROM barangay WHERE BarangayID = ? LIMIT 1), householdname = ? WHERE householdID = ?");
            if (!$stmt_h_update) throw new Exception("DB Error (Prepare HH Update): " . $conn->error);
            $stmt_h_update->bind_param("ssss",
                $householdData_submitted['barangayID'],
                $householdData_submitted['barangayID'], // For subquery to get barangayName
                $householdData_submitted['householdname'],
                $householdID_original
            );
            if (!$stmt_h_update->execute()) throw new Exception("DB Error (Execute HH Update): " . $stmt_h_update->error);
            $stmt_h_update->close();

            // 2. Process Beneficiaries: Update existing, Insert new
            $stmt_m_update = $conn->prepare("UPDATE beneficiaries SET relationship=?, fname=?, mname=?, lname=?, sex=?, bday=?, age=TIMESTAMPDIFF(YEAR, ?, CURDATE()), marital_status=?, education=?, occupation=?, income=?, health=? WHERE memberID = ? AND householdID = ?");
            if (!$stmt_m_update) throw new Exception("DB Error (Prepare Member Update): " . $conn->error);

            $stmt_m_insert = $conn->prepare("INSERT INTO beneficiaries (householdID, relationship, fname, mname, lname, sex, bday, age, marital_status, education, occupation, income, health, status) VALUES (?, ?, ?, ?, ?, ?, ?, TIMESTAMPDIFF(YEAR, ?, CURDATE()), ?, ?, ?, ?, ?, 'Active')");
            if (!$stmt_m_insert) throw new Exception("DB Error (Prepare Member Insert): " . $conn->error);

            foreach ($final_members_to_process_in_db as $member_data) {
                if ($member_data['existing'] === '1' && !empty($member_data['memberID'])) { // Update Existing Member
                    $stmt_m_update->bind_param("ssssssssssdsss", // 'd' for income_numeric
                        $member_data['relationship'], $member_data['fname'], $member_data['mname'], $member_data['lname'],
                        $member_data['sex'], $member_data['bday'], $member_data['bday'], // bday for age calculation
                        $member_data['marital_status'], $member_data['education'],
                        $member_data['occupation'], $member_data['income_numeric'], 
                        $member_data['health'], $member_data['memberID'], $householdID_original
                    );
                    if (!$stmt_m_update->execute()) throw new Exception("DB Error (Update Member ID: {$member_data['memberID']}): " . $stmt_m_update->error);
                } else { // Insert New Member
                    if (!empty($member_data['fname']) || !empty($member_data['lname'])) { // Ensure it's not an empty row
                        $stmt_m_insert->bind_param("sssssssssssds", 
                            $householdID_original, $member_data['relationship'], $member_data['fname'], $member_data['mname'],
                            $member_data['lname'], $member_data['sex'], $member_data['bday'], $member_data['bday'], 
                            $member_data['marital_status'], $member_data['education'],
                            $member_data['occupation'], $member_data['income_numeric'], 
                            $member_data['health']
                        );
                        if (!$stmt_m_insert->execute()) throw new Exception("DB Error (Insert New Member {$member_data['fname']}): " . $stmt_m_insert->error);
                    }
                }
            }
            $stmt_m_update->close();
            $stmt_m_insert->close();

            // 3. Delete Members explicitly marked
            if (!empty($deleted_member_ids)) {
                $placeholders = implode(',', array_fill(0, count($deleted_member_ids), '?'));
                $stmt_m_delete = $conn->prepare("DELETE FROM beneficiaries WHERE householdID = ? AND memberID IN ($placeholders)");
                if (!$stmt_m_delete) throw new Exception("DB Error (Prepare Member Delete): " . $conn->error);
                
                $types_for_delete = "s" . str_repeat('s', count($deleted_member_ids));
                $bind_params_for_delete = array_merge([$householdID_original], $deleted_member_ids); // Use original non-sanitized if your IDs are safe
                
                $stmt_m_delete->bind_param($types_for_delete, ...$bind_params_for_delete);
                if (!$stmt_m_delete->execute()) throw new Exception("DB Error (Execute Member Delete): " . $stmt_m_delete->error);
                $stmt_m_delete->close();
            }

            // 4. Update Household and Barangay Counts (Stored Procedures)
            // These should ideally be called AFTER all member changes (add/update/delete) are done for accurate counts.
            $stmtHouseholdCount = $conn->prepare("CALL update_household_counts(?)");
            if ($stmtHouseholdCount) { 
                $stmtHouseholdCount->bind_param("s", $householdID_original); 
                $stmtHouseholdCount->execute(); 
                $stmtHouseholdCount->close(); 
            } else { 
                error_log("Warning: Could not prepare update_household_counts procedure: " . $conn->error); 
            }

            $barangayID_for_counts = $householdData_submitted['barangayID']; // The (potentially new) barangayID for the household
            $stmtBarangayCount = $conn->prepare("CALL update_barangay_counts(?)");
            if($stmtBarangayCount) { 
                $stmtBarangayCount->bind_param("s", $barangayID_for_counts); 
                $stmtBarangayCount->execute(); 
                $stmtBarangayCount->close(); 
            } else { 
                error_log("Warning: Could not prepare update_barangay_counts procedure: " . $conn->error); 
            }

            // 5. Recalculate and Update SES Score for THIS EDITED household
            $household_ses_updated_successfully = false;
            if (calculateAndUpdateHouseholdSES($householdID_original, $conn)) {
                $household_ses_updated_successfully = true;
                error_log("Successfully recalculated household.sesScore for HHID $householdID_original after edit.");
            } else {
                error_log("Warning: household.sesScore re-calculation failed for HHID $householdID_original after edit. Affected Barangay SES will not be dynamically updated now.");
                // Depending on policy, you might throw an exception here if this is critical
                // throw new Exception("Critical: Failed to recalculate individual household SES for HHID: $householdID_original. Transaction rolled back.");
            }

            // 6. Dynamically Update Raw Average SES for the Affected Barangay
            // This runs if the individual household SES was successfully updated.
            if ($household_ses_updated_successfully && $barangayID_for_counts) {
                error_log("Attempting to dynamically update raw SES for Barangay ID: $barangayID_for_counts after editing HHID: $householdID_original");

                $avg_ses_query = "SELECT AVG(sesScore) as new_avg_barangay_ses FROM household WHERE barangayID = ? AND sesScore IS NOT NULL";
                $stmt_avg = $conn->prepare($avg_ses_query);

                if ($stmt_avg) {
                    $stmt_avg->bind_param("s", $barangayID_for_counts);
                    if ($stmt_avg->execute()) {
                        $result_avg = $stmt_avg->get_result();
                        if ($row_avg = $result_avg->fetch_assoc()) {
                            $new_raw_barangay_ses = ($row_avg['new_avg_barangay_ses'] !== null) ? (float)$row_avg['new_avg_barangay_ses'] : null;

                            $update_b_ses_sql = "UPDATE barangay SET SES = ? WHERE BarangayID = ?";
                            $stmt_b_update = $conn->prepare($update_b_ses_sql);
                            if ($stmt_b_update) {
                                $stmt_b_update->bind_param("ds", $new_raw_barangay_ses, $barangayID_for_counts);
                                if ($stmt_b_update->execute()) {
                                    if ($stmt_b_update->affected_rows > 0) {
                                        error_log("Successfully updated raw SES for Barangay ID $barangayID_for_counts to $new_raw_barangay_ses");
                                        $response_data['message_extra_info'] = "Barangay raw SES also updated."; // Append to main message later
                                    } else {
                                        error_log("Raw SES for Barangay ID $barangayID_for_counts (value: " . ($new_raw_barangay_ses ?? 'NULL') . ") was already up-to-date or BarangayID not found during dynamic SES update step.");
                                    }
                                } else {
                                    error_log("Failed to execute dynamic SES update for Barangay ID $barangayID_for_counts: " . $stmt_b_update->error);
                                }
                                $stmt_b_update->close();
                            } else {
                                error_log("Failed to prepare dynamic SES update statement for Barangay ID $barangayID_for_counts: " . $conn->error);
                            }
                        } else {
                            error_log("Could not fetch new average SES for Barangay ID $barangayID_for_counts (no households with scores or avg is null).");
                        }
                        if(isset($result_avg)) $result_avg->free(); // Free result if it was obtained
                    } else {
                        error_log("Failed to execute query to fetch new average SES for Barangay ID $barangayID_for_counts: " . $stmt_avg->error);
                    }
                    $stmt_avg->close();
                } else {
                    error_log("Failed to prepare statement to fetch new average SES for Barangay ID $barangayID_for_counts: " . $conn->error);
                }
            } else {
                if ($household_ses_updated_successfully) { // Only log if the reason was missing barangayID
                     error_log("Cannot dynamically update barangay SES because barangayID_for_counts ('$barangayID_for_counts') is not set or invalid.");
                }
            }

            // 7. Commit Transaction
            if (!$conn->commit()) {
                throw new Exception("Database Transaction Error: Commit failed. " . $conn->error);
            }

            $response_data['success'] = true;
            $main_message = "Household '" . htmlspecialchars($householdData_submitted['householdname']) . "' (ID: " . htmlspecialchars($householdID_original) . ") updated successfully.";
            if (isset($response_data['message_extra_info'])) {
                $main_message .= " " . $response_data['message_extra_info'];
            }
            $response_data['message'] = $main_message;

        } catch (Exception $e) {
            $conn->rollback(); 
            error_log("DATABASE TRANSACTION ERROR in editprocess.php (HHID: $householdID_original): " . $e->getMessage());
            $response_data['message'] = "Failed to update household. Details: " . $e->getMessage();
        }
    } else {
        // Validation errors occurred
        $error_message_html = "Update failed. Please correct the following issues:<ul>";
        foreach ($errors as $error) {
            $error_message_html .= "<li>" . htmlspecialchars($error) . "</li>";
        }
        $error_message_html .= "</ul>";
        $response_data['message'] = $error_message_html;
        $response_data['errors'] = $errors;
    }

    echo json_encode($response_data);
    exit;

} else {
    $response_data['message'] = "Invalid request method. This page only accepts POST requests.";
    echo json_encode($response_data);
    exit;
}
?>
