<?php
session_start();
require_once '../config/database.php';

$error = '';

if ($_POST) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (empty($name) || empty($email) || empty($password)) {
        $error = "All fields are required!";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match!";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters long!";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address!";
    } else {
        $stmt = mysqli_prepare($connection, "SELECT id FROM itr_registeruser WHERE email = ?");
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if (mysqli_num_rows($result) > 0) {
            $error = "Email already registered! Please login instead.";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt2 = mysqli_prepare($connection, "INSERT INTO itr_registeruser (name, email, password, created_at) VALUES (?, ?, ?, NOW())");
            mysqli_stmt_bind_param($stmt2, "sss", $name, $email, $hashed_password);
            
            if (mysqli_stmt_execute($stmt2)) {
                $_SESSION['registration_success'] = "Registration successful! Please login with your credentials.";
                header('Location: login.php');
                exit();
            } else {
                $error = "Registration failed! Please try again.";
            }
            mysqli_stmt_close($stmt2);
        }
        mysqli_stmt_close($stmt);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Registration - ITR Portal</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body class="general-page">
    <div class="container">
        <div class="login-form">
            <h2>Student Registration</h2>
            
            <?php if ($error): ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="name">Full Name:</label>
                    <input type="text" id="name" name="name" value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="email">Email Address:</label>
                    <input type="email" id="email" name="email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="password">Password:</label>
                    <input type="password" id="password" name="password" required>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Confirm Password:</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                </div>
                
                <button type="submit" class="btn">Register</button>
            </form>
            
            <div class="links">
                <a href="login.php">Already have an account? Login</a>
                <br><br>
                <a href="../index.php">← Back to Home</a>
            </div>
        </div>
    </div>
</body>
</html>
