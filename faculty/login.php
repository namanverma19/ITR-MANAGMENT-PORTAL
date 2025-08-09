<?php
session_start();
require_once '../config/database.php';

// Redirect if already logged in
if (isset($_SESSION['faculty_id'])) {
    header('Location: dashboard.php');
    exit();
}

$error = '';
$success = '';

// Check for success message from password reset
if (isset($_SESSION['success_message'])) {
    $success = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

if ($_POST) {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    
    if (empty($email) || empty($password)) {
        $error = "Please enter both email and password!";
    } else {
        // Check faculty credentials
        $stmt = mysqli_prepare($connection, "SELECT id, name, email, password FROM itr_facultyuser WHERE email = ?");
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $faculty = mysqli_fetch_assoc($result);
        
        if ($faculty && password_verify($password, $faculty['password'])) {
            // Login successful
            $_SESSION['faculty_id'] = $faculty['id'];
            $_SESSION['faculty_name'] = $faculty['name'];
            $_SESSION['faculty_email'] = $faculty['email'];
            
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
    <title>Faculty Login - ITR Portal</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body class="login-page">
    <div class="container">
        <div class="login-form">
            <h2>Faculty Login</h2>
            
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
                <a href="forgot_password.php">Forgot Password?</a>
                <br><br>
                <a href="../index.php">← Back to Home</a>
            </div>
        </div>
    </div>
</body>
</html>
