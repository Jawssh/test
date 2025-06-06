<?php
session_start();
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit;
}

if (!isset($_GET['householdID'])) {
    echo "Household ID not specified.";
    exit;
}

$householdID = $_GET['householdID'];

// First, get the BarangayId associated with this householdID
$stmtBarangayId = $conn->prepare("SELECT householdID FROM beneficiaries WHERE householdID = ? LIMIT 1");
$stmtBarangayId->bind_param("s", $householdID);
$stmtBarangayId->execute();
$resultBarangayId = $stmtBarangayId->get_result();

if ($resultBarangayId->num_rows > 0) {
    $rowBarangay = $resultBarangayId->fetch_assoc();
    $barangayId = $rowBarangay['householdID'];

    // Debug output:
    // echo "BarangayId fetched: " . htmlspecialchars($barangayId);

    // Now fetch the BarangayName using the BarangayId
    // Get the barangay name directly from the household table
    $stmtBarangay = $conn->prepare("SELECT barangayName FROM household WHERE householdID = ? LIMIT 1");
    $stmtBarangay->bind_param("s", $householdID);
    $stmtBarangay->execute();
    $resultBarangay = $stmtBarangay->get_result();

    if ($resultBarangay->num_rows > 0) {
        $rowBarangay = $resultBarangay->fetch_assoc();
        $barangayName = $rowBarangay['barangayName'];
    } else {
        $barangayName = "Unknown Barangay";
    }
} else {
    $barangayId = null;
    $barangayName = "Barangay not found";
}




// Now get the household members
$stmt = $conn->prepare("
    SELECT fname, mname, lname, relationship, sex, bday, age, marital_status, education, occupation, income, health, status
    FROM beneficiaries
    WHERE householdID = ?
    ORDER BY FIELD(relationship, 'Head', 'Spouse', 'Son', 'Daughter'), lname
");
$stmt->bind_param("s", $householdID);
$stmt->execute();
$result = $stmt->get_result();
?>


<!DOCTYPE html>
<html>

<head>
    <title>Household Details</title>
    <link rel="icon" href="../png/logo.png" type="image/x-icon" />
    <link rel="stylesheet" href="../css/household.css"> <!-- Use existing CSS -->
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
</head>

<body>

    <div class="text">

        <div class="back-btn">
            <a href="barangaylist.php" class="btn-back"><i class='bx bx-chevron-left'></i> </a>

        </div>
        <div class="title-container">
            <h3>Household Information</h3>
            <span class="profession">
                Household Member
            </span>
        </div>

    </div>

    <div class="container1">
        <div class="card1">
            <h3>Barangay <i class='bx bx-map'></i></h3>
            <p id="totalHousehold"><?= htmlspecialchars($barangayName) ?>
            </p>
        </div>
        <div class="card1">
            <h3>Household ID<i class='bx bx-clinic'></i></h3>
            <p id="totalHousehold"><?= htmlspecialchars($householdID) ?></p>
        </div>
        <div class="card1">
            <h3>Family Name<i class='bx bx-group'></i></h3>
            <?php // Get family (head's last name)
            $stmtFamilyName = $conn->prepare("SELECT lname FROM beneficiaries WHERE householdID = ? AND relationship = 'Head' LIMIT 1");
            $stmtFamilyName->bind_param("s", $householdID);
            $stmtFamilyName->execute();
            $resultFamilyName = $stmtFamilyName->get_result();

            $familyName = "Unknown";
            if ($resultFamilyName->num_rows > 0) {
                $rowFamilyName = $resultFamilyName->fetch_assoc();
                $familyName = $rowFamilyName['lname'];
            }
            ?>
            <p id="totalHousehold"><?= htmlspecialchars($familyName) ?> Family</p>

        </div>


    </div>





    <div class="category-continer">
        <h3>Socioeconomic Status</h3>
    </div>

    <div class="container">
        <div class="table-container">
            <div class="content-table">
                <?php if ($result->num_rows > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Relationship</th>
                                <th>Sex</th>
                                <th>Birthday</th>
                                <th>Age</th>
                                <th>Marital Status</th>
                                <th>Education</th>
                                <th>Occupation</th>
                                <th>Income</th>
                                <th>Health</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $currentGroup = '';
                            while ($row = $result->fetch_assoc()):
                                $middleInitial = $row['mname'] ? substr($row['mname'], 0, 1) . '.' : '';
                                $fullName = htmlspecialchars($row['fname']) . ' ' . htmlspecialchars($middleInitial) . ' ' . htmlspecialchars($row['lname']);
                                $group = $row['relationship'];

                                // You had a conditional for grouping, but for a flat table, it's not strictly needed for row display.
                                // If you want to group rows visually, you might add a row with the group name,
                                // or style rows differently. For a simple table, we'll just list them.
                            ?>
                                <tr>
                                    <td><?= $fullName ?></td>
                                    <td><?= htmlspecialchars($row['relationship']) ?></td>
                                    <td><?= htmlspecialchars($row['sex']) ?></td>
                                    <td><?= htmlspecialchars($row['bday']) ?></td>
                                    <td><?= htmlspecialchars($row['age']) ?></td>
                                    <td><?= htmlspecialchars($row['marital_status']) ?></td>
                                    <td><?= htmlspecialchars($row['education']) ?></td>
                                    <td><?= htmlspecialchars($row['occupation']) ?></td>
                                    <td><?= htmlspecialchars($row['income']) ?></td>
                                    <td><?= htmlspecialchars($row['health']) ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>No members found for this household.</p>
                <?php endif; ?>

            </div>
        </div>
    </div>




</body>

</html>