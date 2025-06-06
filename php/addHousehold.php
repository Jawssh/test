<?php
// Must be the very first line
session_start();

// Retrieve notification messages and form data from session, then clear them
$successMessage = $_SESSION['success_message'] ?? null;
$validationErrors = $_SESSION['validation_errors'] ?? []; // Expecting an array
$errorMessage = $_SESSION['error_message'] ?? null;     // Expecting a string
$formData = $_SESSION['form_data'] ?? [];             // Expecting an array

// Clear session variables immediately after retrieving so they don't show again
unset($_SESSION['success_message'], $_SESSION['validation_errors'], $_SESSION['error_message'], $_SESSION['form_data']);

require_once 'config.php'; // Need DB config for fetching barangays etc.

// Check authentication and role (Needed to VIEW the page)
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'superadmin')) {
    // Redirect to login page if not authorized to view
    header("Location: ../index.php");
    exit;
}

// --- Data needed FOR DISPLAY ---
// Get barangays for the dropdown
try {
    $barangays = $conn->query("SELECT BarangayID, BarangayName FROM barangay ORDER BY BarangayName")->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
    // Handle error fetching barangays - maybe display an error message
    error_log("Error fetching barangays: " . $e->getMessage());
    $barangays = []; // Set to empty array to avoid errors in foreach loop
    $errorMessage = ($errorMessage ? $errorMessage . "<br>" : '') . "Could not load Barangay list."; // Append to existing error
}

// Occupation options (Grouped for <optgroup>)
$occupationOptions = [
    'Very Low Skill/Informal/Lowest Income' => [
        'Unemployed',
        'No Regular Occupation',
        'Agricultural Labor/Seasonal Worker',
        'Small-Scale Fisherman/Fisherwoman',
        'Laundry Service',
        'Waste Picker/Scavenger',
        'Household Helper'
    ],
    'Low Skill/Informal or Entry-Level/Low Income' => [
        'Street Vendor',
        'Market Vendor', // Consider specifying if established or not if needed elsewhere
        'Construction Laborer/Helper',
        'Tricycle/Jeepney Driver', // Consider specifying if regular or not if needed elsewhere
        'Entry-Level Factory Worker',
        'Non-Professional Caregiver',
        'Home-based Craft Production'
    ],
    'Semi-Skilled/Established/Moderate Income' => [
        'Carpenter/Mason',
        'Hairdresser/Barber (Established Small Stall/Home-Based)',
        'Manicurist/Pedicurist (Established Small Stall/Home-Based)',
        'Market Vendor (Established)',
        'Tricycle/Jeepney Driver (Regular)',
        'Skilled Factory Worker',
        'Small Sari-Sari Store Owner (Developing)',
        'Food Stall Operator'
    ],
    'Skilled/Established Business/Potentially Higher Income' => [
        'Established Sari-Sari Store Owner',
        'Established Food Stall Operator',
        'Skilled Tradesperson (Regular Contracts)',
        'Small Business Owner (Micro-enterprise with steady income)'
    ]
];

// Education options (Simple key => value for <option>)
$educationOptions = [
    'No Education' => 'No Education',
    'Elementary' => 'Elementary',
    'High School' => 'High School',
    'College' => 'College'
];

// Income options (Simple key => value for <option>)
$incomeOptions = [
    'Below PHP 5,000' => 'Below PHP 5,000',
    'PHP 5,001 - PHP 10,000' => 'PHP 5,001 - PHP 10,000',
    'PHP 10,001 - PHP 15,000' => 'PHP 10,001 - PHP 15,000',
    'PHP 15,001 - PHP 20,000' => 'PHP 15,001 - PHP 20,000',
    'Above PHP 20,000' => 'Above PHP 20,000'
];

// Health Condition options (Simple key => value for <option>)
$healthOptions = [
    'Severe Illness' => 'Severe Illness',
    'Significant Illness' => 'Significant Illness',
    'Managed Illness' => 'Managed Illness',
    'No Illness' => 'No Illness'
];


// Generate CSRF token if it doesn't exist (needed for the form submission)
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token']; // Assign to variable for easy use in form

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="../png/logo.png" type="image/x-icon" />
    <title>Add Household - Mapping Hope</title>
    <link rel="stylesheet" href="../css/navbar.css">
    <link rel="stylesheet" href="../css/addHousehold.css">
    <link href='https://unpkg.com/boxicons@2.1.1/css/boxicons.min.css' rel='stylesheet'>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <?php // Include your alert CSS here or ensure it's in addHousehold.css 
    ?>
    <style>
        /* Example Alert Styles */
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid transparent;
            border-radius: 4px;
            font-size: 1em;
            text-align: left;
            line-height: 1.4;
        }

        .alert p,
        .alert ul {
            margin: 0;
            padding: 0;
        }

        .alert strong {
            font-weight: bold;
        }

        .alert ul {
            margin-top: 5px;
            margin-bottom: 0;
            padding-left: 20px;
        }

        .alert.success {
            color: #155724;
            background-color: #d4edda;
            border-color: #c3e6cb;
        }

        .alert.error {
            color: #721c24;
            background-color: #f8d7da;
            border-color: #f5c6cb;
        }
    </style>
</head>

<body>
    <?php include 'navbar.php'; // Assuming navbar is okay 
    ?>

    <section class="home">
        <div class="text">
            <h3>Add a Household</h3>
            <span class="profession">Manage household information</span>
        </div>
        <div class="container">

            <?php // --- Display Notifications --- 
            ?>
            <?php if (!empty($successMessage)): ?>
                <div class="alert success">
                    <p><?php echo htmlspecialchars($successMessage); ?></p>
                </div>
            <?php endif; ?>
            <?php if (!empty($errorMessage)): ?>
                <div class="alert error">
                    <p><?php echo htmlspecialchars($errorMessage); ?></p>
                </div>
            <?php endif; ?>
            <?php if (!empty($validationErrors)): ?>
                <div class="alert error">
                    <strong>Please fix the following issues:</strong><br>
                    <ul>
                        <?php foreach ($validationErrors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            <?php // --- End Notifications --- 
            ?>


            <?php // --- THE FORM --- 
            ?>
            <form id="householdForm" action="addprocess.php" method="POST"> <?php /* <<< ACTION points to process.php */ ?>
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">

                <?php // --- Household Info Section --- 
                ?>
                <div class="form-section household-info">
                    <h2>Household Information</h2>
                    <div class="household">
                        <div class="form-group-1">
                            <label for="barangayID">Barangay:</label>
                            <select id="barangayID" name="barangayID" required>
                                <option value="">Select Barangay</option>
                                <?php foreach ($barangays as $barangay): ?>
                                    <option value="<?php echo htmlspecialchars($barangay['BarangayID']); ?>"
                                        <?php if (isset($formData['barangayID']) && $formData['barangayID'] === $barangay['BarangayID']) echo 'selected'; ?>>
                                        <?php echo htmlspecialchars($barangay['BarangayName']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group-1">
                            <label for="householdID">Household ID:</label>
                            <input type="text" id="householdID" name="householdID" value="<?php echo htmlspecialchars($formData['householdID'] ?? ''); ?>" readonly required>
                        </div>
                        <div class="form-group-1">
                            <label for="householdName">Household Name:</label>
                            <input type="text" id="householdName" name="householdName" value="<?php echo htmlspecialchars($formData['householdName'] ?? ''); ?>">
                        </div>
                        <div class="form-group-1">
                            <label>Total Beneficiaries:</label>
                            <?php // Calculate initial count based on formData if available, else default
                            $initialMemberCount = isset($formData['members']) ? count($formData['members']) : 3;
                            if ($initialMemberCount < 3) $initialMemberCount = 3; // Ensure minimum 3 if repopulating less
                            ?>
                            <input type="text" id="totalBeneficiaries" value="<?php echo $initialMemberCount; ?>" readonly>
                        </div>
                    </div>
                </div>

                <?php // --- Family Members Section --- 
                ?>
                <div class="form-section">
                    <h2>Family Members</h2>
                    <div id="membersContainer">
                        <?php
                        $memberDisplayCount = isset($formData['members']) && count($formData['members']) >= 3 ? count($formData['members']) : 3;

                        for ($i = 0; $i < $memberDisplayCount; $i++):
                            $memberFormData = $formData['members'][$i] ?? [];
                            $relationship = ($i === 0) ? 'Head' : (($i === 1) ? 'Spouse' : ($memberFormData['relationship'] ?? 'Child'));
                            // Adjust relationship display if it was server-converted ('Son'/'Daughter' in $formData)
                            $displayRelationship = ($relationship === 'Son' || $relationship === 'Daughter') ? 'Child' : $relationship;
                            $isHead = ($i === 0);
                            $isSpouse = ($i === 1);
                            $isChild = (!$isHead && !$isSpouse);
                        ?>
                            <div class="member-form">
                                <?php // Display 'Head', 'Spouse', or 'Child' consistently for header 
                                ?>
                                <h3><?php echo htmlspecialchars($displayRelationship); ?>
                                    <?php // Add Son/Daughter span if repopulating child from formData 
                                    ?>
                                    <?php if ($isChild && isset($memberFormData['relationship']) && ($memberFormData['relationship'] === 'Son' || $memberFormData['relationship'] === 'Daughter')): ?>
                                        <span class="member-relationship"><?php echo htmlspecialchars($memberFormData['relationship']); ?></span>
                                    <?php elseif ($isChild): // Default span for template consistency 
                                    ?>
                                        <span class="member-relationship"></span>
                                    <?php endif; ?>
                                </h3>
                                <?php // Ensure hidden input relationship matches what was likely submitted/validated 
                                ?>
                                <input type="hidden" name="members[<?php echo $i; ?>][relationship]" value="<?php echo htmlspecialchars($relationship); ?>">

                                <div class="form-row">
                                    <div class="form-group">
                                        <label>First Name:</label>
                                        <input type="text" name="members[<?php echo $i; ?>][fname]" value="<?php echo htmlspecialchars($memberFormData['fname'] ?? ''); ?>" required>
                                    </div>
                                    <div class="form-group">
                                        <label>Middle Name:</label>
                                        <input type="text" name="members[<?php echo $i; ?>][mname]" value="<?php echo htmlspecialchars($memberFormData['mname'] ?? ''); ?>">
                                    </div>
                                    <div class="form-group">
                                        <label>Last Name:</label>
                                        <input type="text" name="members[<?php echo $i; ?>][lname]" value="<?php echo htmlspecialchars($memberFormData['lname'] ?? ($formData['members'][0]['lname'] ?? '')); ?>" <?php if (!$isHead)    ?> required>
                                    </div>
                                    <div class="form-group">
                                        <label>Sex:</label>
                                        <select name="members[<?php echo $i; ?>][sex]" required>
                                            <?php $currentSex = $memberFormData['sex'] ?? ($isHead ? 'Male' : ($isSpouse ? 'Female' : '')); ?>
                                            <option value="Male" <?php if ($currentSex === 'Male') echo 'selected'; ?>>Male</option>
                                            <option value="Female" <?php if ($currentSex === 'Female') echo 'selected'; ?>>Female</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>Birthdate:</label>
                                        <input type="date" name="members[<?php echo $i; ?>][bday]" value="<?php echo htmlspecialchars($memberFormData['bday'] ?? ''); ?>" required>
                                    </div>
                                    <div class="form-group">
                                        <label>Age:</label>
                                        <input type="text" class="age-display" required>
                                    </div>
                                    <div class="form-group">
                                        <label>Marital Status:</label>
                                        <select name="members[<?php echo $i; ?>][marital_status]" required>
                                            <option value="">Select Status</option>
                                            <?php $currentStatus = $memberFormData['marital_status'] ?? ($isChild ? 'Single' : ($isSpouse ? 'Married' : ($isHead ? 'Single' : ''))); // Default head maybe single? Adjust default as needed 
                                            ?>
                                            <option value="Single" <?php if ($currentStatus === 'Single') echo 'selected'; ?>>Single</option>
                                            <option value="Married" <?php if ($currentStatus === 'Married') echo 'selected'; ?>>Married</option>
                                            <option value="Divorced" <?php if ($currentStatus === 'Divorced') echo 'selected'; ?>>Divorced</option>
                                            <option value="Widowed" <?php if ($currentStatus === 'Widowed') echo 'selected'; ?>>Widowed</option>
                                            <option value="Separated" <?php if ($currentStatus === 'Separated') echo 'selected'; ?>>Separated</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label>Education:</label>
                                        <select name="members[<?php echo $i; ?>][education]" required>
                                            <option value="">Select Education</option>
                                            <?php foreach ($educationOptions as $label => $value): ?>
                                                <option value="<?php echo htmlspecialchars($value); ?>" <?php if (($memberFormData['education'] ?? '') === $value) echo 'selected'; ?>><?php echo htmlspecialchars($label); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-group occupation-field">
                                        <label>Occupation:</label>
                                        <select name="members[<?php echo $i; ?>][occupation]" <?php if ($isChild) echo 'disabled'; ?>>
                                            <option value="">Select Occupation</option>
                                            <?php foreach ($occupationOptions as $level => $occupations): ?>
                                                <optgroup label="<?php echo htmlspecialchars($level); ?>">
                                                    <?php foreach ($occupations as $occupation): ?>
                                                        <option value="<?php echo htmlspecialchars($occupation); ?>" <?php if (($memberFormData['occupation'] ?? '') === $occupation) echo 'selected'; ?>><?php echo htmlspecialchars($occupation); ?></option>
                                                    <?php endforeach; ?>
                                                </optgroup>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-group income-field">
                                        <label>Income:</label>
                                        <select name="members[<?php echo $i; ?>][income]" <?php if ($isChild) echo 'disabled'; ?>>
                                            <option value="">Select Income</option>
                                            <?php foreach ($incomeOptions as $label => $value): ?>
                                                <option value="<?php echo htmlspecialchars($value); ?>" <?php if (($memberFormData['income'] ?? '') === $value) echo 'selected'; ?>><?php echo htmlspecialchars($label); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>Health Condition:</label>
                                        <select name="members[<?php echo $i; ?>][health]" required>
                                            <option value="">Select Condition</option>
                                            <?php foreach ($healthOptions as $label => $value): ?>
                                                <option value="<?php echo htmlspecialchars($value); ?>" <?php if (($memberFormData['health'] ?? '') === $value) echo 'selected'; ?>><?php echo htmlspecialchars($label); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <?php // Remove button for dynamically added members (index > 2 if repopulating, or added by JS) 
                                    ?>
                                    <?php if ($i > 1): ?>
                                        <div class="remove-btn-container" style="align-self: flex-end; margin-left: 10px;">
                                            <button type="button" class="btn-remove">Remove</button>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div><?php // End member-form 
                                    ?>
                        <?php endfor; ?>
                    </div> <?php // End membersContainer 
                            ?>

                    <template id="memberTemplate">
                        <div class="member-form">
                            <h3>Child</h3>
                            <div class="form-row">
                                <div class="form-group"><label>First Name:</label><input type="text" name="members[][fname]" required></div>
                                <div class="form-group"><label>Middle Name:</label><input type="text" name="members[][mname]"></div>
                                <div class="form-group"><label>Last Name:</label><input type="text" name="members[][lname]" class="inherited-lname" readonly required></div>
                                <div class="form-group"><label>Sex:</label><select name="members[][sex]" required>
                                        <option value="Male">Male</option>
                                        <option value="Female" selected>Female</option>
                                    </select></div>
                                <div class="form-group"><label>Birthdate:</label><input type="date" name="members[][bday]" required></div>
                                <div class="form-group"><label>Age:</label><input type="text" class="age-display" readonly></div>
                                <div class="form-group"><label>Marital Status:</label><select name="members[][marital_status]" required>
                                        <option value="">Select Status</option>
                                        <option value="Single" selected>Single</option>
                                        <option value="Married">Married</option>
                                        <option value="Divorced">Divorced</option>
                                        <option value="Widowed">Widowed</option>
                                        <option value="Separated">Separated</option>
                                    </select></div>
                            </div>
                            <div class="form-row">
                                <div class="form-group"><label>Education:</label><select name="members[][education]" required>
                                        <option value="">Select Education</option><?php foreach ($educationOptions as $label => $value): ?><option value="<?php echo htmlspecialchars($value); ?>"><?php echo htmlspecialchars($label); ?></option><?php endforeach; ?>
                                    </select></div>
                                <div class="form-group occupation-field"><label>Occupation:</label><select name="members[][occupation]" disabled>
                                        <option value="">Select Occupation</option><?php foreach ($occupationOptions as $level => $occupations): ?><optgroup label="<?php echo htmlspecialchars($level); ?>"><?php foreach ($occupations as $occupation): ?><option value="<?php echo htmlspecialchars($occupation); ?>"><?php echo htmlspecialchars($occupation); ?></option><?php endforeach; ?></optgroup><?php endforeach; ?>
                                    </select></div>
                                <div class="form-group income-field"><label>Income:</label><select name="members[][income]" disabled>
                                        <option value="">Select Income</option><?php foreach ($incomeOptions as $label => $value): ?><option value="<?php echo htmlspecialchars($value); ?>"><?php echo htmlspecialchars($label); ?></option><?php endforeach; ?>
                                    </select></div>
                                <div class="form-group"><label>Health Condition:</label><select name="members[][health]" required>
                                        <option value="">Select Condition</option><?php foreach ($healthOptions as $label => $value): ?><option value="<?php echo htmlspecialchars($value); ?>"><?php echo htmlspecialchars($label); ?></option><?php endforeach; ?>
                                    </select></div>
                                <div class="remove-btn-container" style="align-self: flex-end; margin-left: 10px;"><button type="button" class="btn-remove">Remove</button></div>
                            </div>
                        </div>
                    </template>
                    <button type="button" id="addMemberBtn" class="btn-add">Add Child</button>
                </div>

                <center><button type="submit" class="btn-submit">Add Household</button></center>
            </form> <?php // End form 
                    ?>

        </div> <?php // End container 
                ?>
    </section>

    <script>
        // Pass the potentially repopulated form data (retrieved from session) to JavaScript
        const phpFormData = <?php echo json_encode($formData); ?>;
    </script>
    <script src="../js/addHousehold.js"></script> <?php // Ensure path to JS is correct 
                                                    ?>
</body>

</html>