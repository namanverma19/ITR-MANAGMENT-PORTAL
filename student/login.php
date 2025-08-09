<?php
session_start();
require_once '../config/database.php';

if (isset($_SESSION['student_id'])) {
    header('Location: dashboard.php');
    exit();
}

$error = '';
$success = '';

if (isset($_SESSION['success_message'])) {
    $success = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

if (isset($_SESSION['registration_success'])) {
    $success = $_SESSION['registration_success'];
    unset($_SESSION['registration_success']);
}

if ($_POST) {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    
    if (empty($email) || empty($password)) {
        $error = "Please enter both email and password!";
    } else {
        $stmt = mysqli_prepare($connection, "SELECT id, name, email, password FROM itr_registeruser WHERE email = ?");
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user = mysqli_fetch_assoc($result);
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['student_id'] = $user['id'];
            $_SESSION['student_name'] = $user['name'];
            $_SESSION['student_email'] = $user['email'];
            
            mysqli_stmt_close($stmt);
            header('Location: dashboard.php');
            exit();
        } else {
            $error = "Invalid email or password!";
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
    <title>Student Login - ITR Portal</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body class="login-page">
    <div class="container">
        <div class="login-form">
            <h2>Student Login</h2>
            
            <?php if ($error): ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="email">Email Address:</label>
                    <input type="email" id="email" name="email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="password">Password:</label>
                    <input type="password" id="password" name="password" required>
                </div>
                
                <button type="submit" class="btn">Login</button>
            </form>
            
            <div class="links">
                <a href="register.php">Don't have an account? Register</a>
                <br>
                <a href="forgot_password.php">Forgot Password?</a>
                <br><br>
                <a href="../index.php">← Back to Home</a>
            </div>
        </div>
    </div>
</body>
</html>
