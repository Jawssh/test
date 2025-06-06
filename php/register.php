<?php
require_once 'config.php'; 
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fname = trim($_POST['fname']);
    $lname = trim($_POST['lname']);
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $role = trim($_POST['role']);

    // Hash the password before storing it
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    // Insert user into the database
    $sql = "INSERT INTO users (fname, lname, username, password, role) VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssss", $fname, $lname, $username, $hashedPassword, $role);

    if ($stmt->execute()) {
        echo "<p class='success'>Registration successful!</p>";
    } else {
        echo "<p class='error'>Error: " . $conn->error . "</p>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register</title>
    <link rel="stylesheet" href="css/index.css">
</head>
<body>

    <div class="login-container">
        <h2>Register</h2>
        <form action="" method="POST">
            <div class="form-group">
                <label for="fname">First Name</label>
                <input type="text" name="fname" placeholder="Enter first name" required>
            </div>
            <div class="form-group">
                <label for="lname">Last Name</label>
                <input type="text" name="lname" placeholder="Enter last name" required>
            </div>
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" name="username" placeholder="Enter username" required>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" name="password" placeholder="Enter password" required>
            </div>
            <div class="form-group">
                <label for="role">Role</label>
                <select name="role" required>
                    <option value="user">User</option>
                    <option value="admin">Admin</option>
                    <option value="superadmin">Super Admin</option>
                </select>
            </div>
            <button type="submit" class="btn">Register</button>
        </form>
        <p>Already have an account? <a href="../index.php">Login here</a></p>
    </div>

</body>
</html>
