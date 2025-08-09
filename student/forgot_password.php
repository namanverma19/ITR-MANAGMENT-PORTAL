<?php
session_start();
require_once '../config/database.php';

$error = '';
$success = '';
$step = 1;

if ($_POST) {
    if (isset($_POST['step']) && $_POST['step'] == '1') {
        $email = trim($_POST['email']);
        
        if (empty($email)) {
            $error = "Please enter your email address!";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Please enter a valid email address!";
        } else {
            $stmt = mysqli_prepare($connection, "SELECT id, name FROM itr_registeruser WHERE email = ?");
            mysqli_stmt_bind_param($stmt, "s", $email);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $user = mysqli_fetch_assoc($result);
            
            if ($user) {
                $_SESSION['reset_user_id'] = $user['id'];
                $_SESSION['reset_email'] = $email;
                $step = 2;
                $success = "Email verified! Please enter your new password.";
            } else {
                $error = "Email not found in our records!";
            }
            mysqli_stmt_close($stmt);
        }
    } elseif (isset($_POST['step']) && $_POST['step'] == '2') {
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        if (empty($new_password) || empty($confirm_password)) {
            $error = "Please fill in both password fields!";
            $step = 2;
        } elseif ($new_password !== $confirm_password) {
            $error = "Passwords do not match!";
            $step = 2;
        } elseif (strlen($new_password) < 6) {
            $error = "Password must be at least 6 characters long!";
            $step = 2;
        } else {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = mysqli_prepare($connection, "UPDATE itr_registeruser SET password = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "si", $hashed_password, $_SESSION['reset_user_id']);
            
            if (mysqli_stmt_execute($stmt)) {
                unset($_SESSION['reset_user_id']);
                unset($_SESSION['reset_email']);
                $_SESSION['success_message'] = "Password reset successful! You can now login with your new password.";
                mysqli_stmt_close($stmt);
                header('Location: login.php');
                exit();
            } else {
                $error = "Failed to reset password! Please try again.";
                $step = 2;
            }
            mysqli_stmt_close($stmt);
        }
    }
}

if (isset($_SESSION['reset_user_id']) && $step == 1) {
    $step = 2;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Student - ITR Portal</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body class="general-page">
    <div class="container">
        <div class="login-form">
            <h2>Reset Password - Student</h2>
            
            <?php if ($error): ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <?php if ($step == 1): ?>
                <form method="POST" action="">
                    <input type="hidden" name="step" value="1">
                    <div class="form-group">
                        <label for="email">Enter your registered email address:</label>
                        <input type="email" id="email" name="email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
                    </div>
                    
                    <button type="submit" class="btn">Verify Email</button>
                </form>
            <?php else: ?>
                <form method="POST" action="">
                    <input type="hidden" name="step" value="2">
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($_SESSION['reset_email']); ?></p>
                    
                    <div class="form-group">
                        <label for="new_password">New Password:</label>
                        <input type="password" id="new_password" name="new_password" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">Confirm New Password:</label>
                        <input type="password" id="confirm_password" name="confirm_password" required>
                    </div>
                    
                    <button type="submit" class="btn">Reset Password</button>
                </form>
            <?php endif; ?>
            
            <div class="links">
                <a href="login.php">← Back to Login</a>
            </div>
        </div>
    </div>
</body>
</html>
