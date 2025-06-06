<?php
session_start();
require_once __DIR__ . '/config.php'; // Ensure this path is correct

// 1. CSRF Protection
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    $_SESSION['error_message'] = "Invalid CSRF token. Action aborted.";
    header("Location: editHousehold.php");
    exit;
}

// 2. Role-based Access Control
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'superadmin')) {
    $_SESSION['error_message'] = "Unauthorized access.";
    header("Location: ../index.php"); // Redirect to login or a suitable page
    exit;
}

// 3. Check if householdID is provided
if (!isset($_POST['householdID']) || empty($_POST['householdID'])) {
    $_SESSION['error_message'] = "Household ID not provided.";
    header("Location: editHousehold.php");
    exit;
}

$householdID = $_POST['householdID'];

// Database connection check
if (!$conn) {
    error_log("deletehousehold.php: Database connection is not available.");
    $_SESSION['error_message'] = "Database connection error. Please try again later.";
    header("Location: editHousehold.php");
    exit;
}

// Start a transaction for atomicity
$conn->begin_transaction();

try {
    // Get the barangayID associated with the household before deletion
    $stmt = $conn->prepare("SELECT barangayID FROM household WHERE householdID = ?");
    if ($stmt === false) {
        throw new Exception("Prepare statement failed: " . $conn->error);
    }
    $stmt->bind_param("s", $householdID);
    $stmt->execute();
    $result = $stmt->get_result();
    $household = $result->fetch_assoc();
    $barangayID = $household['barangayID'] ?? null;
    $stmt->close();

    if (!$barangayID) {
        throw new Exception("Household not found or barangay ID missing.");
    }

    // 4. Delete the household (and its beneficiaries due to CASCADE DELETE)
    // The `beneficiaries_ibfk_1` and `fk_beneficiaries_to_household` foreign keys
    // with `ON DELETE CASCADE` will automatically delete associated beneficiaries.
    $stmt = $conn->prepare("DELETE FROM household WHERE householdID = ?");
    if ($stmt === false) {
        throw new Exception("Prepare statement failed: " . $conn->error);
    }
    $stmt->bind_param("s", $householdID);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        // 5. Update barangay aggregates after deletion
        // The trigger `after_beneficiary_delete_update_custom_pwd` on `beneficiaries` table
        // should handle the PWD count, but `TotalHouseholds` and `TotalBeneficiaries`
        // and `Average_Income` are updated by `update_barangay_counts` procedure.
        // We need to call `update_barangay_counts` explicitly because deleting a household
        // directly impacts `TotalHouseholds` and may affect `TotalBeneficiaries` and `Average_Income`
        // if no beneficiaries are left or if the averages change significantly.
        // The `after_beneficiary_delete_update_custom_pwd` trigger is only for beneficiaries table.
        // So we need to call `sp_UpdateBarangayAggregates` or `update_barangay_counts` directly.

        // Assuming you want to use the sp_UpdateBarangayAggregates for a comprehensive update
        $updateAggregatesStmt = $conn->prepare("CALL sp_UpdateBarangayAggregates(?)");
        if ($updateAggregatesStmt === false) {
            throw new Exception("Prepare CALL statement failed: " . $conn->error);
        }
        $updateAggregatesStmt->bind_param("s", $barangayID);
        $updateAggregatesStmt->execute();
        $updateAggregatesStmt->close();

        $conn->commit();
        $_SESSION['success_message'] = "Household '{$householdID}' deleted successfully.";
        header("Location: editHousehold.php");
        exit;
    } else {
        $conn->rollback();
        $_SESSION['error_message'] = "Household '{$householdID}' not found or could not be deleted.";
        header("Location: editHousehold.php");
        exit;
    }
} catch (Exception $e) {
    $conn->rollback();
    error_log("Error deleting household in deletehousehold.php: " . $e->getMessage());
    $_SESSION['error_message'] = "An unexpected error occurred: " . $e->getMessage();
    header("Location: editHousehold.php");
    exit;
} finally {
    if (isset($stmt) && $stmt instanceof mysqli_stmt) {
        $stmt->close();
    }
    if (isset($conn) && $conn instanceof mysqli && $conn->thread_id) {
        $conn->close();
    }
}
