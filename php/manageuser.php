<?php
session_start();
require_once 'config.php';

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit;
}

// Handle User Registration
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['registerUser'])) {
    $fname = trim($_POST['fname']);
    $lname = trim($_POST['lname']);
    $username = trim($_POST['username']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role = $_POST['role'];

    // Check if username exists
    $checkStmt = $conn->prepare("SELECT username FROM users WHERE username = ?");
    $checkStmt->bind_param("s", $username);
    $checkStmt->execute();
    $checkStmt->store_result();

    if ($checkStmt->num_rows > 0) {
        echo json_encode(["success" => false, "error" => "Username already taken."]);
        $checkStmt->close();
        exit;
    }
    $checkStmt->close();

    // Insert new user
    $stmt = $conn->prepare("INSERT INTO users (fname, lname, username, password, role) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssss", $fname, $lname, $username, $password, $role);

    if ($stmt->execute()) {
        echo json_encode([
            "success" => true,
            "newUser" => [
                "User_ID" => $stmt->insert_id,
                "fname" => $fname,
                "lname" => $lname,
                "username" => $username,
                "role" => $role
            ]
        ]);
    } else {
        echo json_encode(["success" => false, "error" => $stmt->error]);
    }
    $stmt->close();
    exit;
}

// Handle User Update
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['updateUser'])) {
    $userID = $_POST['userID'];
    $fname = trim($_POST['fname']);
    $lname = trim($_POST['lname']);
    $username = trim($_POST['username']);
    $role = $_POST['role'];

    $stmt = $conn->prepare("UPDATE users SET fname = ?, lname = ?, username = ?, role = ? WHERE User_ID = ?");
    $stmt->bind_param("ssssi", $fname, $lname, $username, $role, $userID);

    if ($stmt->execute()) {
        echo json_encode(["success" => true, "message" => "User updated successfully."]);
    } else {
        echo json_encode(["success" => false, "error" => $stmt->error]);
    }
    $stmt->close();
    exit;
}

// Handle User Deletion
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['deleteUser'])) {
    $userID = $_POST['userID'];

    $stmt = $conn->prepare("DELETE FROM users WHERE User_ID = ?");
    $stmt->bind_param("i", $userID);

    if ($stmt->execute()) {
        echo json_encode(["success" => true, "message" => "User deleted successfully."]);
    } else {
        echo json_encode(["success" => false, "error" => $stmt->error]);
    }
    $stmt->close();
    exit;
}

// Fetch users
$sql = "SELECT User_ID, fname, lname, username, role FROM users";
$result = $conn->query($sql);

// Fetch unique roles
$roles_sql = "SELECT DISTINCT role FROM users";
$roles_result = $conn->query($roles_sql);
$roles = [];
while ($row = $roles_result->fetch_assoc()) {
    $roles[] = $row['role'];
}

$conn->close();
?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="../png/logo.png" type="image/x-icon" />
    <link rel="stylesheet" href="../css/manageuser.css">
    <link rel="stylesheet" href="../css/navbar.css">
    <title>Manage Users</title>
</head>

<body>
    <?php include 'navbar.php'; ?>
    <section class="home">
        <div class="text">
            <h3>Manage Users</h3>
            <span class="profession">Managing User's Infromation</span>
        </div>
        <div class="create-account-container">
            <button class="create-account-btn" onclick="openModal()">Create a New Account</button>
        </div>
        <div class="table-wrapper">
            <div class="table-container">

                <table class="content-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Username</th>
                            <th>Role</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $result->fetch_assoc()) : ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['User_ID']); ?></td>
                                <td><?php echo htmlspecialchars($row['fname'] . ' ' . $row['lname']); ?></td>
                                <td><?php echo htmlspecialchars($row['username']); ?></td>
                                <td><?php echo htmlspecialchars($row['role']); ?></td>
                                <td>
                                    <button class="update-btn" onclick="openUpdateModal(<?php echo $row['User_ID']; ?>, '<?php echo $row['fname']; ?>', '<?php echo $row['lname']; ?>', '<?php echo $row['username']; ?>', '<?php echo $row['role']; ?>')">
                                        <i class='bx bx-edit-alt'></i>
                                        Update</button>
                                    <button class="delete-btn" onclick="confirmDelete(<?php echo $row['User_ID']; ?>)"><i class='bx bx-trash'></i>Delete</button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>

                <!-- Create Account Button -->


                <!-- Notification Message -->
                <div id="notification" class="notification">Registration Successful!</div>

                <!-- Modal Structure -->
                <div id="registerModal" class="modal">
                    <div class="modal-content">
                        <span class="close" onclick="closeModal()">&times;</span>
                        <h2>Register a New User</h2>
                        <form id="registerForm">
                            <div class="form-group">
                                <label for="fname">First Name:</label>
                                <input type="text" id="fname" name="fname" required>
                            </div>
                            <div class="form-group">
                                <label for="lname">Last Name:</label>
                                <input type="text" id="lname" name="lname" required>
                            </div>
                            <div class="form-group">
                                <label for="username">Username:</label>
                                <input type="text" id="username" name="username" required>
                            </div>
                            <div class="form-group">
                                <label for="password">Password:</label>
                                <input type="password" id="password" name="password" required>
                            </div>
                            <div class="form-group">
                                <label for="role">Role:</label>
                                <select id="role" name="role">
                                    <?php foreach ($roles as $role): ?>
                                        <option value="<?php echo htmlspecialchars($role); ?>"><?php echo htmlspecialchars($role); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="submit" class="submit-btn">Register</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <!-- Update User Modal -->
    <div id="updateModal" class="modal">

        <div class="modal-content">
            <div class="logo-container">
                <img src="../png/logo.png" alt="">
            </div>
            <span class="close" onclick="closeUpdateModal()">&times;</span>
            <div class="modal-title-container">
                <h2>Update User</h2>
            </div>

            <form id="updateForm">
                <input type="hidden" id="updateUserID" name="userID">
                <div class="form-group">
                    <label for="updateFname">First Name:</label>
                    <input type="text" id="updateFname" name="fname" required>
                </div>
                <div class="form-group">
                    <label for="updateLname">Last Name:</label>
                    <input type="text" id="updateLname" name="lname" required>
                </div>
                <div class="form-group">
                    <label for="updateUsername">Username:</label>
                    <input type="text" id="updateUsername" name="username" required>
                </div>
                <div class="form-group">
                    <label for="updateRole">Role:</label>
                    <input type="text" id="updateRole" name="role" required>
                </div>
                <div class="modal-btn-container">
                    <button type="submit" class="submit-btn">Save Changes</button>
                </div>

            </form>
        </div>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            // Handle User Registration
            document.getElementById("registerForm").addEventListener("submit", function(event) {
                event.preventDefault();

                let formData = new FormData(this);
                formData.append("registerUser", true); // Ensure correct POST flag

                fetch("manageuser.php", {
                        method: "POST",
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            document.getElementById("registerForm").reset();
                            closeModal();
                            showNotification("User registered successfully!", "success");
                            addUserToTable(data.newUser);
                        } else {
                            showNotification("Registration failed: " + data.error, "error");
                        }
                    })
                    .catch(error => console.error("Error:", error));
            });
        });

        // Open and Close Modal for Registration
        function openModal() {
            document.getElementById("registerModal").style.display = "flex";
        }

        function closeModal() {
            document.getElementById("registerModal").style.display = "none";
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            let modal = document.getElementById("registerModal");
            if (event.target === modal) {
                modal.style.display = "none";
            }
        };

        // Show Notifications
        function showNotification(message, type = "success") {
            let notification = document.getElementById("notification");
            notification.textContent = message;
            notification.className = type;
            notification.style.display = "block";
            setTimeout(() => {
                notification.style.display = "none";
            }, 2000);
        }

        // Dynamically Add New User to Table
        function addUserToTable(user) {
            let table = document.querySelector("tbody");
            let newRow = document.createElement("tr");

            newRow.innerHTML = `
        <td>${user.User_ID}</td>
        <td>${user.fname} ${user.lname}</td>
        <td>${user.username}</td>
        <td>${user.role}</td>
        <td>
            <button class="update-btn" onclick="openUpdateModal(${user.User_ID}, '${user.fname}', '${user.lname}', '${user.username}', '${user.role}')">Update</button>
            <button class="delete-btn" onclick="confirmDelete(${user.User_ID})">Delete</button>
        </td>
    `;

            table.appendChild(newRow);
        }

        // Open Update User Modal
        function openUpdateModal(id, fname, lname, username, role) {
            document.getElementById("updateUserID").value = id;
            document.getElementById("updateFname").value = fname;
            document.getElementById("updateLname").value = lname;
            document.getElementById("updateUsername").value = username;
            document.getElementById("updateRole").value = role;
            document.getElementById("updateModal").style.display = "flex";
        }

        // Close Update User Modal
        function closeUpdateModal() {
            document.getElementById("updateModal").style.display = "none";
        }

        // Handle User Update
        document.getElementById("updateForm").addEventListener("submit", function(event) {
            event.preventDefault();

            let formData = new FormData(this);
            formData.append("updateUser", true);

            fetch("manageuser.php", {
                    method: "POST",
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showNotification("User updated successfully!", "success");
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        showNotification("Update failed: " + data.error, "error");
                    }
                })
                .catch(error => console.error("Error:", error));
        });

        // Handle User Deletion with Confirmation
        function confirmDelete(userID) {
            if (confirm("Are you sure you want to delete this user?")) {
                let formData = new FormData();
                formData.append("deleteUser", true);
                formData.append("userID", userID);

                fetch("manageuser.php", {
                        method: "POST",
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            showNotification("User deleted successfully!", "success");
                            setTimeout(() => location.reload(), 1000);
                        } else {
                            showNotification("Deletion failed: " + data.error, "error");
                        }
                    })
                    .catch(error => console.error("Error:", error));
            }
        }
    </script>
</body>

</html>