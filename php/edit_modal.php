<?php
session_start();
require_once __DIR__ . '/config.php'; // Use __DIR__ for reliable path

header('Content-Type: text/html; charset=utf-8'); // Indicate HTML response

// --- Auth Check ---
// Make sure this reflects the actual roles allowed to edit
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'superadmin')) {
    http_response_code(403); // Forbidden
    // Return a user-friendly HTML error message for the modal
    exit('<div class="alert error" style="margin:20px;">Error: Unauthorized access. You do not have permission to edit households.</div>');
}

// --- Get Household ID ---
$householdID = $_GET['householdID'] ?? null;
if (empty($householdID)) {
    http_response_code(400); // Bad Request
    exit('<div class="alert error" style="margin:20px;">Error: Household ID is required.</div>');
}

// --- Fetch Data ---
$householdData = null;
$membersData = [];
$barangays = [];
$optionsError = null; // Variable to hold potential errors during setup

// --- Define Options Arrays (Crucial: Ensure these are complete and correct) ---
$occupationOptions = [
    'Very Low Skill/Informal/Lowest Income' => [
        'Unemployed', 'No Regular Occupation', 'Agricultural Labor/Seasonal Worker',
        'Small-Scale Fisherman/Fisherwoman', 'Laundry Service', 'Waste Picker/Scavenger', 'Household Helper'
    ],
    'Low Skill/Informal or Entry-Level/Low Income' => [
        'Street Vendor', 'Market Vendor', 'Construction Laborer/Helper', 'Tricycle/Jeepney Driver',
        'Entry-Level Factory Worker', 'Non-Professional Caregiver', 'Home-based Craft Production'
    ],
    'Semi-Skilled/Established/Moderate Income' => [
        'Carpenter/Mason', 'Hairdresser/Barber (Established Small Stall/Home-Based)',
        'Manicurist/Pedicurist (Established Small Stall/Home-Based)', 'Market Vendor (Established)',
        'Tricycle/Jeepney Driver (More Regular)', 'Skilled Factory Worker',
        'Small Sari-Sari Store Owner (Developing)', 'Food Stall Operator (Developing)'
    ],
    'Skilled/Established Business/Potentially Higher Income' => [
        'Established Small Sari-Sari Store Owner', 'Established Food Stall Operator (Good Income)',
        'Skilled Tradesperson (Regular Contracts)', 'Small Business Owner (Micro-enterprise with steady income)'
    ]
];
$educationOptions = [
    'No Education' => 'No Education', 'Elementary' => 'Elementary', 'High School' => 'High School', 'College' => 'College'
];
$incomeOptions = [
    'Below PHP 5,000' => 'Below PHP 5,000', 'PHP 5,001 - PHP 10,000' => 'PHP 5,001 - PHP 10,000',
    'PHP 10,001 - PHP 15,000' => 'PHP 10,001 - PHP 15,000', 'PHP 15,001 - PHP 20,000' => 'PHP 15,001 - PHP 20,000',
    'Above PHP 20,000' => 'Above PHP 20,000'
];
$healthOptions = [
    'Severe Illness' => 'Severe Illness', 'Significant Illness' => 'Significant Illness',
    'Managed Illness' => 'Managed Illness', 'No Illness' => 'No Illness'
];
// --- End Options Arrays ---


// --- Helper function to get Income Range String ---
function getIncomeRangeString($numericIncome) {
    if ($numericIncome === null || $numericIncome === '') return ''; // Handle null/empty string
    $income = floatval($numericIncome); // Ensure it's a number

    // Determine the range string based on the numeric value
    // IMPORTANT: Ensure these ranges match the keys/values in $incomeOptions exactly
    if ($income <= 5000) return 'Below PHP 5,000';
    if ($income <= 10000) return 'PHP 5,001 - PHP 10,000';
    if ($income <= 15000) return 'PHP 10,001 - PHP 15,000';
    if ($income <= 20000) return 'PHP 15,001 - PHP 20,000';
    // If none of the above matched, it must be above 20,000
    return 'Above PHP 20,000';
}
// --- End Helper function ---


try {
    // Fetch Household Data
    $stmt_h = $conn->prepare("SELECT * FROM household WHERE householdID = ?");
    if (!$stmt_h) throw new Exception("Household prepare failed: " . $conn->error);
    $stmt_h->bind_param("s", $householdID);
    if(!$stmt_h->execute()) throw new Exception("Household execute failed: " . $stmt_h->error);
    $result_h = $stmt_h->get_result();
    $householdData = $result_h->fetch_assoc();
    $stmt_h->close();

    if (!$householdData) {
        http_response_code(404); // Not Found
        exit('<div class="alert error" style="margin:20px;">Error: Household not found (ID: '.htmlspecialchars($householdID).').</div>');
    }

    // Fetch Beneficiaries Data
    // Ensure beneficiaries table has mname and marital_status columns
    $stmt_m = $conn->prepare("SELECT * FROM beneficiaries WHERE householdID = ? ORDER BY FIELD(relationship, 'Head', 'Spouse', 'Son', 'Daughter'), bday");
    if (!$stmt_m) throw new Exception("Beneficiaries prepare failed: " . $conn->error);
    $stmt_m->bind_param("s", $householdID);
     if(!$stmt_m->execute()) throw new Exception("Beneficiaries execute failed: " . $stmt_m->error);
    $result_m = $stmt_m->get_result();
    while ($row = $result_m->fetch_assoc()) {
        $membersData[] = $row;
    }
    $stmt_m->close();

    // Fetch Barangays for dropdown
    $barangayResult = $conn->query("SELECT BarangayID, BarangayName FROM barangay ORDER BY BarangayName");
     if(!$barangayResult) throw new Exception("Barangay query failed: " . $conn->error);
    $barangays = $barangayResult->fetch_all(MYSQLI_ASSOC);
    $barangayResult->free();


} catch (Exception $e) {
    error_log("Error fetching data for edit_modal.php (HHID: $householdID): " . $e->getMessage());
    http_response_code(500); // Internal Server Error
    // Ensure you echo valid HTML for the AJAX request to insert
    exit('<div class="alert error" style="margin:20px;">Error loading data from server. Please close the editor and try again. Details logged.</div>');
} finally {
     // Ensure connection is closed if persistent connections aren't used
     // $conn->close();
}


// --- Generate CSRF Token ---
// Use a specific token name for edits if desired
if (empty($_SESSION['edit_csrf_token'])) {
    $_SESSION['edit_csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['edit_csrf_token'];

// --- Generate HTML Form Content ---
?>
<form id="editHouseholdForm" action="editprocess.php" method="POST">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
    <input type="hidden" name="householdID_original" value="<?php echo htmlspecialchars($householdData['householdID']); ?>">

    <div class="form-section household-info">
         <h2>Household Information</h2>
         <div class="household">
            <!-- <div class="form-group-1">
                <label for="edit_barangayID">Barangay:</label>
                <select id="edit_barangayID" name="barangayID" required>
                    <option value="">Select Barangay</option>
                    <?php foreach ($barangays as $barangay): ?>
                        <option value="<?php echo htmlspecialchars($barangay['BarangayID']); ?>"
                            <?php echo ($householdData['barangayID'] === $barangay['BarangayID']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($barangay['BarangayName']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div> -->

            <div class="form-group-1">
                <label for="edit_barangayID_display">Barangay:</label>
                <select id="edit_barangayID_display" name="barangayID_display" required disabled style="background-color:#eee; cursor: not-allowed;">
                    <?php // We only need to show the currently selected barangay, no need to loop all
                        $currentBarangayName = "N/A"; // Default if not found
                        if (isset($householdData['barangayID'])) {
                            // You might already have $householdData['barangayName'] if you selected it in your initial query.
                            // If not, you might need a quick lookup or adjust the initial household data query.
                            // For simplicity, let's assume $householdData['barangayName'] is available.
                            // If not, you would iterate through $barangays to find the matching name.
                            $currentBarangayName = htmlspecialchars($householdData['barangayName'] ?? 'Unknown Barangay');
                            // If you don't have barangayName directly in $householdData, find it:
                            // foreach ($barangays as $b) {
                            //    if ($b['BarangayID'] === $householdData['barangayID']) {
                            //        $currentBarangayName = htmlspecialchars($b['BarangayName']);
                            //        break;
                            //    }
                            // }
                        }
                    ?>
                    <option value="<?php echo htmlspecialchars($householdData['barangayID'] ?? ''); ?>"><?php echo $currentBarangayName; ?></option>
                </select>
                <?php // Add a hidden input to submit the actual barangayID value ?>
                <input type="hidden" name="barangayID" value="<?php echo htmlspecialchars($householdData['barangayID'] ?? ''); ?>">
            </div>
             <div class="form-group-1">
                <label for="edit_householdID_display">Household ID:</label>
                <input type="text" id="edit_householdID_display" value="<?php echo htmlspecialchars($householdData['householdID']); ?>" readonly disabled style="background-color:#eee; cursor: not-allowed;">
                <input type="hidden" name="householdID" value="<?php echo htmlspecialchars($householdData['householdID']); ?>">
            </div>
             <div class="form-group-1">
                <label for="edit_householdName">Household Name:</label>
                <input type="text" id="edit_householdName" name="householdName" value="<?php echo htmlspecialchars($householdData['householdname']); ?>" required>
            </div>
            <div class="form-group-1">
                <label>Total Beneficiaries:</label>
                <input type="text" id="edit_totalBeneficiaries" value="<?php echo count($membersData); ?>" readonly style="background-color:#eee;">
            </div>
         </div>
    </div>

    <div class="form-section">
        <h2>Family Members</h2>
        <div id="editMembersContainer">
            <?php foreach ($membersData as $index => $member):
                // Determine display details
                $relationship = $member['relationship'] ?? 'Child';
                $displayRelationship = ($relationship === 'Son' || $relationship === 'Daughter') ? 'Child' : $relationship;
                $isHead = ($relationship === 'Head');
                $isSpouse = ($relationship === 'Spouse');
                $isChild = (!$isHead && !$isSpouse);
            ?>
            <div class="member-form existing-member" data-index="<?php echo $index; ?>" data-member-id="<?php echo htmlspecialchars($member['memberID']); ?>">
                 <input type="hidden" name="members[<?php echo $index; ?>][memberID]" value="<?php echo htmlspecialchars($member['memberID']); ?>">
                 <input type="hidden" name="members[<?php echo $index; ?>][existing]" value="1">

                <h3>
                    <?php echo htmlspecialchars($displayRelationship); ?>
                    <?php if ($isChild): ?><span class="member-relationship"><?php echo htmlspecialchars($relationship); ?></span><?php endif; ?>
                </h3>
                <input type="hidden" name="members[<?php echo $index; ?>][relationship]" value="<?php echo htmlspecialchars($relationship); ?>">

                <div class="form-row">
                    <div class="form-group">
                        <label>First Name:</label>
                        <input type="text" name="members[<?php echo $index; ?>][fname]" value="<?php echo htmlspecialchars($member['fname'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Middle Name:</label>
                        <input type="text" name="members[<?php echo $index; ?>][mname]" value="<?php echo htmlspecialchars($member['mname'] ?? ''); // Assumes column exists ?>">
                    </div>
                    <div class="form-group">
                        <label>Last Name:</label>
                        <input type="text" name="members[<?php echo $index; ?>][lname]" value="<?php echo htmlspecialchars($member['lname'] ?? ''); ?>" <?php if (!$isHead) echo 'readonly style="background-color:#eee; cursor: not-allowed;"'; ?> required>
                    </div>
                    <div class="form-group">
                        <label>Sex:</label>
                        <select name="members[<?php echo $index; ?>][sex]" required <?php if ($isHead || $isSpouse) echo 'disabled style="background-color:#eee; pointer-events: none;"'; ?>>
                            <option value="Male" <?php echo (($member['sex'] ?? '') === 'Male') ? 'selected' : ''; ?>>Male</option>
                            <option value="Female" <?php echo (($member['sex'] ?? '') === 'Female') ? 'selected' : ''; ?>>Female</option>
                        </select>
                         <?php if ($isHead || $isSpouse): ?>
                           <input type="hidden" name="members[<?php echo $index; ?>][sex]" value="<?php echo htmlspecialchars($member['sex'] ?? ''); ?>" />
                         <?php endif; ?>
                    </div>
                     <div class="form-group">
                        <label>Birthdate:</label>
                        <input type="date" class="edit-member-bday" name="members[<?php echo $index; ?>][bday]" value="<?php echo htmlspecialchars($member['bday'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Age:</label>
                        <input type="text" class="age-display" value="<?php echo htmlspecialchars($member['age'] ?? ''); ?>" readonly style="background-color:#eee;">
                    </div>
                    <div class="form-group">
                         <label>Marital Status:</label>
                        <select name="members[<?php echo $index; ?>][marital_status]" required> <?php // Assumes column exists ?>
                            <option value="">Select Status</option>
                            <option value="Single" <?php echo (($member['marital_status'] ?? '') === 'Single') ? 'selected' : ''; ?>>Single</option>
                            <option value="Married" <?php echo (($member['marital_status'] ?? '') === 'Married') ? 'selected' : ''; ?>>Married</option>
                            <option value="Divorced" <?php echo (($member['marital_status'] ?? '') === 'Divorced') ? 'selected' : ''; ?>>Divorced</option>
                            <option value="Widowed" <?php echo (($member['marital_status'] ?? '') === 'Widowed') ? 'selected' : ''; ?>>Widowed</option>
                            <option value="Separated" <?php echo (($member['marital_status'] ?? '') === 'Separated') ? 'selected' : ''; ?>>Separated</option>
                        </select>
                    </div>
                </div> <?php // End first form-row ?>

                 <div class="form-row">
                     <div class="form-group">
                        <label>Education:</label>
                        <select name="members[<?php echo $index; ?>][education]" required>
                            <option value="">Select Education</option>
                            <?php foreach ($educationOptions as $label => $value): ?>
                                <option value="<?php echo htmlspecialchars($value); ?>" <?php echo (($member['education'] ?? '') === $value) ? 'selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group occupation-field">
                        <label>Occupation:</label>
                        <select name="members[<?php echo $index; ?>][occupation]" <?php if ($isChild) echo 'disabled style="background-color:#eee;"'; ?>>
                            <option value="">Select Occupation</option>
                            <?php foreach ($occupationOptions as $level => $occupations): ?>
                                <optgroup label="<?php echo htmlspecialchars($level); ?>">
                                    <?php foreach ($occupations as $occupation): ?>
                                        <option value="<?php echo htmlspecialchars($occupation); ?>" <?php echo (($member['occupation'] ?? null) === $occupation) ? 'selected' : ''; ?>><?php echo htmlspecialchars($occupation); ?></option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endforeach; ?>
                        </select>
                         <?php if ($isChild): ?>
                           <input type="hidden" name="members[<?php echo $index; ?>][occupation]" value="<?php echo htmlspecialchars($member['occupation'] ?? ''); ?>" />
                         <?php endif; ?>
                    </div>
                     <div class="form-group income-field">
                        <label>Income:</label>
                        <select name="members[<?php echo $index; ?>][income]" <?php if ($isChild) echo 'disabled style="background-color:#eee;"'; ?>>
                             <option value="">Select Income</option>
                             <?php
                             // *** USE HELPER FUNCTION FOR COMPARISON ***
                             $storedIncomeString = getIncomeRangeString($member['income'] ?? null);
                             ?>
                             <?php foreach ($incomeOptions as $label => $value): ?>
                                 <option value="<?php echo htmlspecialchars($value); ?>" <?php echo ($storedIncomeString === $value) ? 'selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
                             <?php endforeach; ?>
                        </select>
                         <?php if ($isChild): ?>
                           <input type="hidden" name="members[<?php echo $index; ?>][income]" value="<?php echo htmlspecialchars($member['income'] ?? ''); // Send original value ?>" />
                         <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label>Health Condition:</label>
                        <select name="members[<?php echo $index; ?>][health]" required>
                            <option value="">Select Condition</option>
                            <?php foreach ($healthOptions as $label => $value): ?>
                                <?php // This line compares DB value ($member['health']) with Option value ($value) ?>
                                <option value="<?php echo htmlspecialchars($value); ?>" <?php echo (($member['health'] ?? '') === $value) ? 'selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                     <div class="remove-btn-container">
                         <?php if (!$isHead && !$isSpouse): ?>
                             <button type="button" class="btn btn-remove-member" title="Mark member for removal upon saving">Remove</button>
                         <?php endif; ?>
                     </div>
                </div> <?php // End second form-row ?>
            </div> <?php // End member-form ?>
            <?php endforeach; ?>
        </div> <?php // End editMembersContainer ?>

         <?php // Template for adding NEW members during edit ?>
         <template id="editMemberTemplate">
             <div class="member-form new-member" data-index="__INDEX__">
                <input type="hidden" name="members[__INDEX__][memberID]" value="">
                <input type="hidden" name="members[__INDEX__][existing]" value="0">
                <h3>Child <span class="member-relationship">Daughter</span></h3>
                <input type="hidden" name="members[__INDEX__][relationship]" value="Child">
                 <div class="form-row">
                    <div class="form-group"><label>First Name:</label><input type="text" name="members[__INDEX__][fname]" required></div>
                    <div class="form-group"><label>Middle Name:</label><input type="text" name="members[__INDEX__][mname]"></div>
                    <div class="form-group"><label>Last Name:</label><input type="text" name="members[__INDEX__][lname]" class="inherited-lname" readonly required style="background-color:#eee;"></div>
                    <div class="form-group"><label>Sex:</label><select class="new-member-sex" name="members[__INDEX__][sex]" required><option value="Male">Male</option><option value="Female" selected>Female</option></select></div>
                    <div class="form-group"><label>Birthdate:</label><input type="date" class="edit-member-bday new-bday" name="members[__INDEX__][bday]" required></div>
                    <div class="form-group"><label>Age:</label><input type="text" class="age-display" readonly style="background-color:#eee;"></div>
                    <div class="form-group"><label>Marital Status:</label><select name="members[__INDEX__][marital_status]" required><option value="">Select Status</option><option value="Single" selected>Single</option><option value="Married">Married</option><option value="Divorced">Divorced</option><option value="Widowed">Widowed</option><option value="Separated">Separated</option></select></div>
                 </div>
                 <div class="form-row">
                    <div class="form-group"><label>Education:</label><select name="members[__INDEX__][education]" required><option value="">Select Education</option><?php foreach ($educationOptions as $label => $value):?><option value="<?php echo htmlspecialchars($value); ?>"><?php echo htmlspecialchars($label); ?></option><?php endforeach; ?></select></div>
                    <div class="form-group occupation-field"><label>Occupation:</label><select name="members[__INDEX__][occupation]" disabled style="background-color:#eee;"><option value="">Select Occupation</option><?php foreach ($occupationOptions as $level => $occupations):?><optgroup label="<?php echo htmlspecialchars($level); ?>"><?php foreach ($occupations as $occupation):?><option value="<?php echo htmlspecialchars($occupation); ?>"><?php echo htmlspecialchars($occupation); ?></option><?php endforeach; ?></optgroup><?php endforeach; ?></select><input type="hidden" name="members[__INDEX__][occupation]" value=""></div>
                    <div class="form-group income-field"><label>Income:</label><select name="members[__INDEX__][income]" disabled style="background-color:#eee;"><option value="">Select Income</option><?php foreach ($incomeOptions as $label => $value):?><option value="<?php echo htmlspecialchars($value); ?>"><?php echo htmlspecialchars($label); ?></option><?php endforeach; ?></select><input type="hidden" name="members[__INDEX__][income]" value=""></div>
                    <div class="form-group"><label>Health Condition:</label><select name="members[__INDEX__][health]" required><option value="">Select Condition</option><?php foreach ($healthOptions as $label => $value):?><option value="<?php echo htmlspecialchars($value); ?>"><?php echo htmlspecialchars($label); ?></option><?php endforeach; ?></select></div>
                     <div class="remove-btn-container"><button type="button" class="btn btn-remove-member new-remove">Remove</button></div>
                 </div>
            </div>
        </template>

        <button type="button" id="editAddMemberBtn" class="btn btn-add" style="margin-top:10px;">
             <i class='bx bx-plus-circle'></i> Add Child
        </button>
        <?php // Hidden input to track IDs of members marked for deletion by JS ?>
        <input type="hidden" name="deleted_members" id="deleted_members" value="">
    </div> <?php // End form-section for members ?>

    <?php // --- Form Actions --- ?>
    <div style="text-align: right; margin-top: 20px; padding-top: 15px; border-top: 1px solid #eee;">
         <button type="button" class="btn btn-cancel modal-close-button">Cancel</button>
         <button type="submit" class="btn btn-submit">Save Changes</button>
    </div>
</form>