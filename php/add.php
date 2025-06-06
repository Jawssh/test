<!-- Include Sidebar -->
<?php
session_start(); // Start the session
// Check if the user is logged in by verifying if 'user_id' session variable is set
if (!isset($_SESSION['user_id'])) {
    // If not logged in, redirect to the login page
    header("Location: ../index.php");
    exit;
} ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- <link rel="icon" href="../png/logo.png" type="image/x-icon" /> -->
    <link rel="icon" href="../png/logo.png" type="image/x-icon" />
    <link rel="stylesheet" href="../css/add.css">
    <link href='https://unpkg.com/boxicons@2.1.1/css/boxicons.min.css' rel='stylesheet'>
    <title>Add/Update Household</title>
</head>

<body>

    <!-- Include Sidebar -->
    <?php include 'navbar.php'; ?>

    <section class="home">
        <div class="text">
            <!-- Add your main content here -->
            <h3>Application Form</h3>
            <span class="profession">Fill out form for adding new household of 4P's</span>
            <div class="option-container">
                <div class="option-1-container">
                    <a href="addHousehold.php">
                        <div class="add-container">
                            <div class="icon-container">
                                <i class='bx bx-plus-circle icon'></i>
                            </div>
                            <div class="title-container">
                                <h3>Add <br> Household</h3>
                            </div>
                        </div>
                    </a>
                </div>
                <div class="option-2-container">
                    <a href="editHousehold.php">
                        <div class="update-container">
                            <div class="icon-container">
                                <i class='bx bx-cog icon'></i>
                            </div>
                            <div class="title-container">
                                <h3>Update<br> Household</h3>
                            </div>

                        </div>
                    </a>
                </div>
            </div>
    </section>
</body>

</html>