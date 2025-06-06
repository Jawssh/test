<?php
session_start();
require_once 'php/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    // Validate credentials
    $sql = "SELECT User_ID, fname, lname, username, password, role FROM users WHERE username = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

        // Verify hashed password
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['User_ID'];
            $_SESSION['fname'] = $user['fname'];
            $_SESSION['lname'] = $user['lname'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];

            // Redirect based on user role
            if ($user['role'] === 'user') {
                header("Location: php/home.php");
            } elseif ($user['role'] === 'admin') {
                header("Location: php/map.php");
            } elseif ($user['role'] === 'superadmin') {
                header("Location: php/map.php");
            }
            exit;
        } else {
            $error = "Invalid username or password.";
        }
    } else {
        $error = "Invalid username or password.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Page</title>
    <link rel="stylesheet" href="css/index.css">
</head>

<body>

    <div class="login-container">
        <div class="logo-container">
            <img src="png/logo.png" alt="">
        </div>

        <h2>Welcome Back, User!</h2>
        <form id="loginForm" method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" placeholder="Enter username" required>
                <div id="usernameError" class="error"></div>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" placeholder="••••••••" required>
                <div id="passwordError" class="error"></div>
            </div>
            <?php if (isset($error)) : ?>
                <div class="errorMessage"><?php echo $error; ?></div>
            <?php endif; ?>
            <button type="submit" class="btn">Login</button>
        </form>
    </div>

    <script>
        const loginForm = document.getElementById("loginForm");

        loginForm.addEventListener("submit", function(event) {
            const username = document.getElementById("username").value.trim();
            const password = document.getElementById("password").value.trim();

            if (username === "" || password === "") {
                event.preventDefault();
                if (username === "") {
                    document.getElementById("usernameError").textContent = "Username is required.";
                }
                if (password === "") {
                    document.getElementById("passwordError").textContent = "Password is required.";
                }
            }
        });
    </script>
</body>

</html>
