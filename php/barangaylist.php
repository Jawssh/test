<?php
session_start();
include 'config.php'; // Included once at the top

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit;
}

// Handle AJAX request for heads of households
if (isset($_GET['barangay'])) {
    header('Content-Type: text/html'); // Set content type for HTML fragment
    $barangay = $_GET['barangay'];

    // Ensure $conn is available and active. If config.php doesn't create a persistent $conn,
    // you might need to re-include it or re-establish connection here.
    // For this example, assuming $conn from the top include is still valid.

    $stmt = $conn->prepare("
    SELECT b.fname, b.mname, b.lname, b.householdID, h.totalMembers
    FROM beneficiaries b
    JOIN household h ON b.householdID = h.householdID
    WHERE b.relationship = 'Head' AND h.barangayName = ?
    ORDER BY b.lname, b.fname, b.mname
");


    if (!$stmt) {
        echo "<p style='color: red; text-align: center;'>Error preparing statement: " . htmlspecialchars($conn->error) . "</p>";
        $conn->close(); // Close connection if it was opened by this script block
        exit;
    }

    $stmt->bind_param("s", $barangay);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        echo "<table class='inner-details-table'>"; // Added class for specific styling
        echo "<thead>
                <tr>
                    <th>Household</th>
                    <th>Number of Beneficiaries</th>
                </tr>
              </thead>
              <tbody>";

        while ($row = $result->fetch_assoc()) {
            $middleInitial = !empty($row['mname']) ? htmlspecialchars(substr($row['mname'], 0, 1)) . '.' : '';
            // Ensure all parts of name are properly escaped
            $fullName = htmlspecialchars($row['fname']) . ' ' . $middleInitial . ' ' . htmlspecialchars($row['lname']);
            $totalMembers = htmlspecialchars($row['totalMembers']);
            echo "<tr class='clickable-row' data-householdid='" . htmlspecialchars($row['householdID']) . "'>
                <td>{$fullName}</td>
                 <td style='text-align: center;'>{$totalMembers}</td>
             </tr>";
        }
        echo "</tbody></table>";
    } else {
        echo "<p style='padding: 10px; text-align: center;'>No heads of households found for " . htmlspecialchars($barangay) . ".</p>";
    }
    $stmt->close();
    // $conn->close(); // Only close $conn if this AJAX part is the sole user of it for this request lifecycle.
    // If main page part below also needs it, manage connection state carefully.
    // Typically, for AJAX, it's fine to close.
    exit; // Crucial to stop further HTML rendering for AJAX response
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="../png/logo.png" type="image/x-icon" />
    <link rel="stylesheet" href="../css/barangaylist.css">
    <link rel="stylesheet" href="../css/navbar.css">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <title>List of Barangay</title>
</head>

<body>
    <?php include 'navbar.php'; ?>
    <section class="home">
        <div class="text">
            <h3>List of Barangay</h3>
            <span class="profession">Table list of all Barangays and initial data of beneficiaries</span>
        </div>
        <div class="container">
            <div class="table-wrapper">
                <div class="table-container">
                    <table class="content-table">
                        <thead>
                            <tr>
                                <th>Barangay Name</th>
                                <th>Number of Households</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Main page data fetching - $conn should be available from the include at the top.
                            // If AJAX part closes it, you'd need to re-establish here or ensure config.php handles it.
                            if (!$conn || $conn->connect_error) {
                                // Fallback if connection was closed or failed, re-establish
                                // This depends heavily on how config.php is structured.
                                // A robust way is to have a function getConnection() in config.php
                                include 'config.php'; // Attempt to re-include if $conn is not valid.
                            }


                            $sql = "SELECT BarangayName, TotalHouseholds FROM barangay ORDER BY BarangayName ASC";
                            $result_main_list = $conn->query($sql); // Use a distinct variable name

                            if ($result_main_list && $result_main_list->num_rows > 0) {
                                while ($row_main = $result_main_list->fetch_assoc()) {
                                    // *** THIS IS THE KEY CHANGE FOR ROW CLICKABILITY ***
                                    echo '<tr class="expandable-row" onclick="toggleExpand(this)" data-barangay="' . htmlspecialchars($row_main['BarangayName']) . '">';
                                    echo '<td>' . htmlspecialchars($row_main['BarangayName']) . '</td>';
                                    echo '<td class="member-td">' . htmlspecialchars($row_main['TotalHouseholds']) . '</td>';
                                    echo '<td><span class="expand-arrow">&#9660;</span></td>'; // Arrow is now just a visual indicator
                                    echo '</tr>';

                                    // Sub-row for expanded data
                                    echo '<tr class="expanded-data" style="display:none;">';
                                    echo '<td colspan="3" class="expand-content">Loading...</td>'; // Corrected colspan to 3
                                    echo '</tr>';
                                }
                            } else {
                                echo '<tr><td colspan="3" style="text-align:center;">No barangay data found.</td></tr>'; // Corrected colspan to 3
                            }
                            // $conn->close(); // Close connection when main page rendering is done
                            ?>
                            <script>
                                document.addEventListener("DOMContentLoaded", function() {
                                    document.querySelectorAll(".clickable-row").forEach(row => {
                                        row.addEventListener("click", function() {
                                            const householdID = this.getAttribute("data-householdid");
                                            if (householdID) {
                                                window.location.href = `household.php?householdID=${encodeURIComponent(householdID)}`;
                                            }
                                        });
                                    });
                                });
                            </script>
                        </tbody>

                    </table>
                </div>
            </div>
        </div>

    </section>

    <script>
        function toggleExpand(rowElement) { // Parameter is now the clicked TR element
            // alert('Row clicked! Barangay: ' + rowElement.getAttribute('data-barangay')); // For testing

            const expandedRow = rowElement.nextElementSibling;
            const barangay = rowElement.getAttribute("data-barangay");
            const arrow = rowElement.querySelector(".expand-arrow"); // Get arrow from within the row
            const currentlyOpen = expandedRow.style.display === "table-row";

            // Close all other expanded rows
            document.querySelectorAll("tr.expanded-data").forEach(r => {
                if (r !== expandedRow) {
                    r.style.display = "none";
                    r.querySelector(".expand-content").innerHTML = "Loading...";
                    const prevRow = r.previousElementSibling;
                    if (prevRow && prevRow.classList.contains("expandable-row")) {
                        prevRow.classList.remove("active-row");
                        const prevArrow = prevRow.querySelector(".expand-arrow");
                        if (prevArrow) {
                            prevArrow.innerHTML = "&#9660;";
                        }
                    }
                }
            });

            // Toggle current row
            if (!currentlyOpen) {
                expandedRow.querySelector(".expand-content").innerHTML = "Loading...";
                expandedRow.style.display = "table-row";
                if (arrow) arrow.innerHTML = "&#9650;";
                rowElement.classList.add("active-row");

                fetch("barangaylist.php?barangay=" + encodeURIComponent(barangay))
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Network response was not ok: ' + response.statusText);
                        }
                        return response.text();
                    })
                    .then(data => {
                        if (expandedRow.style.display === "table-row") {
                            expandedRow.querySelector(".expand-content").innerHTML = data;

                            // Re-attach event listeners to newly added rows
                            expandedRow.querySelectorAll(".clickable-row").forEach(row => {
                                row.addEventListener("click", function() {
                                    const householdID = this.getAttribute("data-householdid");
                                    if (householdID) {
                                        window.location.href = `household.php?householdID=${encodeURIComponent(householdID)}`;
                                    }
                                });
                            });
                        }
                    })

                    .catch(error => {
                        console.error('Fetch error for ' + barangay + ':', error);
                        if (expandedRow.style.display === "table-row") {
                            expandedRow.querySelector(".expand-content").innerHTML = "<p style='color: red; text-align: center;'>Could not load details. " + error.message + "</p>";
                        }
                    });
            } else {
                expandedRow.style.display = "none";
                expandedRow.querySelector(".expand-content").innerHTML = "Loading...";
                if (arrow) arrow.innerHTML = "&#9660;";
                rowElement.classList.remove("active-row");
            }
        }

        // Sidebar toggle script (from your previous code)
        document.addEventListener('DOMContentLoaded', () => {
            const body = document.querySelector('body'),
                sidebar = body.querySelector('nav'),
                toggle = body.querySelector(".toggle"),
                searchBtn = body.querySelector(".search-box"),
                modeSwitch = body.querySelector(".toggle-switch"),
                modeText = body.querySelector(".mode-text");

            if (toggle && sidebar) { // Check if elements exist before adding listeners
                toggle.addEventListener("click", () => {
                    sidebar.classList.toggle("close");
                });
            }

            if (searchBtn && sidebar) {
                searchBtn.addEventListener("click", () => {
                    sidebar.classList.remove("close");
                });
            }

            if (modeSwitch && body && modeText) {
                modeSwitch.addEventListener("click", () => {
                    body.classList.toggle("dark");
                    if (body.classList.contains("dark")) {
                        modeText.innerText = "Light mode";
                    } else {
                        modeText.innerText = "Dark mode";
                    }
                });
            }
        });
    </script>


</body>

</html>