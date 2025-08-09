<?php
session_start();
require_once '../config/database.php';

$debug_mode = false;

if (!isset($_SESSION['student_id'])) {
    header('Location: login.php');
    exit();
}

$student_id = $_SESSION['student_id'];
$success_message = '';
$error_message = '';

if ($debug_mode) {
    echo "<div style='background: #f8f9fa; padding: 10px; margin: 10px; border: 1px solid #ccc;'>";
    echo "<h4>Debug Information</h4>";
    echo "<p>Student ID from session: " . $student_id . "</p>";
    echo "<p>Session data: " . print_r($_SESSION, true) . "</p>";
    echo "</div>";
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $stmt = mysqli_prepare($connection, "SELECT profile_updated FROM itr_registeruser WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $student_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $profile_status = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        
        if ($profile_status && $profile_status['profile_updated'] == 1) {
            $error_message = "Profile can only be updated once. Your profile has already been updated and is now locked.";
        } else {
            $name = trim($_POST['name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $college_id = trim($_POST['college_id'] ?? '');
            $branch = $_POST['branch'] ?? '';
            $section = $_POST['section'] ?? '';
            
            if (empty($name) || empty($email) || empty($college_id) || empty($branch) || empty($section)) {
                $error_message = "Please fill in all required fields.";
            } else {
            $assigned_faculty_id = null;
            if ($branch && $section) {
                $stmt = mysqli_prepare($connection, "
                    SELECT s.faculty_id, f.name as faculty_name
                    FROM sections s 
                    JOIN branches b ON s.branch_id = b.id 
                    JOIN itr_facultyuser f ON s.faculty_id = f.id
                    WHERE b.id = ? AND s.section_name = ?
                ");
                mysqli_stmt_bind_param($stmt, "is", $branch, $section);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                $faculty_info = mysqli_fetch_assoc($result);
                $assigned_faculty_id = $faculty_info ? $faculty_info['faculty_id'] : null;
                mysqli_stmt_close($stmt);
            }
            
            $stmt = mysqli_prepare($connection, "
                UPDATE itr_registeruser 
                SET name = ?, email = ?, college_id = ?, branch = ?, section = ?, 
                    assigned_faculty_id = ?, profile_updated = 1
                WHERE id = ?
            ");
            
            mysqli_stmt_bind_param($stmt, "ssssiii", $name, $email, $college_id, $branch, $section, $assigned_faculty_id, $student_id);
            $result = mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            
            if ($result) {
                $_SESSION['student_name'] = $name;
                $_SESSION['student_email'] = $email;
                $success_message = "Profile updated successfully! Your profile is now locked and cannot be changed again.";
                if (isset($faculty_info) && $faculty_info) {
                    $success_message .= " Your assigned faculty is: " . $faculty_info['faculty_name'];
                }
            } else {
                $error_message = "Failed to update profile. Please try again.";
            }
            }
        }
    } catch (Exception $e) {
        $error_message = "Database error occurred. Please try again.";
        error_log("Profile update error: " . $e->getMessage());
    }
}

$student = [];
$branches = [];

try {
    $stmt = mysqli_prepare($connection, "
        SELECT r.*, b.branch_name, f.name as faculty_name
        FROM itr_registeruser r
        LEFT JOIN branches b ON r.branch = b.id
        LEFT JOIN itr_facultyuser f ON r.assigned_faculty_id = f.id
        WHERE r.id = ?
    ");
    mysqli_stmt_bind_param($stmt, "i", $student_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $student = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    if (!$student) {
        $error_message = "Student profile not found. Please contact administrator.";
        $student = [
            'name' => '',
            'email' => '',
            'college_id' => '',
            'branch' => '',
            'section' => '',
            'branch_name' => '',
            'faculty_name' => '',
            'profile_updated' => 0
        ];
    }
    
    $stmt = mysqli_prepare($connection, "SELECT id, branch_name FROM branches ORDER BY branch_name");
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $branches = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $branches[] = $row;
    }
    mysqli_stmt_close($stmt);
    
} catch (Exception $e) {
    $error_message = "Failed to load profile data.";
    error_log("Profile load error: " . $e->getMessage());
    $student = [];
    $branches = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Profile - ITR Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <style>
        body {
            font-family: 'Arial', sans-serif;
            background-color: #f8f9fa;
            margin: 0;
            padding: 0;
            padding-top: 0;
        }
        .navbar-gradient {
            background: linear-gradient(to right, #003366, #004080, #004d99);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
            position: sticky;
            top: 0;
            z-index: 1020;
            margin: 0;
            padding: 0;
        }
        .navbar {
            margin: 0;
            border-radius: 0;
            padding: 12px 0;
        }
        .navbar .container-fluid {
            padding-left: 1rem;
            padding-right: 1rem;
        }
        .logo {
            height: 48px;
            margin-right: 12px;
            border-radius: 60px;
        }
        .bg-primary {
            background: linear-gradient(135deg, #003366, #004d99) !important;
        }
        .profile-container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 20px;
        }
        .profile-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            padding: 30px;
            margin-bottom: 20px;
        }
        .form-control, .form-select {
            margin-bottom: 15px;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        .form-control[readonly], .form-select[disabled] {
            background-color: #e9ecef;
            opacity: 0.7;
            cursor: not-allowed;
        }
        .form-control[readonly]:focus, .form-select[disabled]:focus {
            background-color: #e9ecef;
            box-shadow: none;
        }
        .btn-primary {
            background-color: #003366;
            border-color: #003366;
            padding: 10px 30px;
        }
        .btn-primary:hover {
            background-color: #004d99;
            border-color: #004d99;
        }
        .faculty-info {
            background-color: #d4edda;
            padding: 15px;
            border-radius: 5px;
            margin-top: 20px;
            border-left: 4px solid #28a745;
        }
        .active {
            background-color: rgba(255,255,255,0.2) !important;
            border-radius: 5px;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-gradient py-3">
        <div class="container-fluid px-4">
            <a class="navbar-brand fs-4 d-flex align-items-center" href="dashboard.php">
                <img src="../img/Logo.png" alt="Portal Logo" class="logo">
                <span class="text-white ms-2 fw-bold">STUDENT PROFILE</span>
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link text-white" href="dashboard.php">
                            <i class="fa fa-home"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white active" href="profile.php">
                            <i class="fa fa-user"></i> Profile
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white" href="projectsubmission.php">
                            <i class="fa fa-upload"></i> Project Submit
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white" href="projectstatus.php">
                            <i class="fa fa-tasks"></i> Project Status
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white" href="logout.php">
                            <i class="fa fa-sign-out"></i> Logout
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Profile Header Section -->
    <div class="bg-primary text-white py-4">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="mb-1">
                        <i class="fa fa-user-circle"></i> Student Profile
                    </h1>
                    <p class="mb-0">Update your profile information</p>
                </div>
                <div class="col-md-4 text-md-end">
                    <div class="d-flex align-items-center justify-content-md-end">
                        <i class="fa fa-user-circle fa-3x me-3"></i>
                        <div>
                            <h5 class="mb-0"><?php echo htmlspecialchars($student['name'] ?? 'Student'); ?></h5>
                            <small class="opacity-75"><?php echo htmlspecialchars($student['email'] ?? ''); ?></small>
                            <?php if ($student['branch_name'] && $student['section']): ?>
                                <br><small class="opacity-75"><?php echo htmlspecialchars($student['branch_name']); ?> - Section <?php echo htmlspecialchars($student['section']); ?></small>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="profile-container">
        <?php if ($success_message): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fa fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fa fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="profile-card">
            <h2 class="mb-4">
                <i class="fa fa-user"></i> Student Profile
            </h2>
            
            <form method="POST" action="profile.php">
                <div class="row">
                    <div class="col-md-6">
                        <label class="form-label">
                            <i class="fa fa-user"></i> Full Name *
                        </label>
                        <input type="text" class="form-control" name="name" 
                               value="<?php echo htmlspecialchars($student['name'] ?? ''); ?>" 
                               <?php echo ($student['profile_updated'] ?? 0) ? 'readonly' : 'required'; ?> 
                               placeholder="Enter your full name">
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label">
                            <i class="fa fa-envelope"></i> Email Address *
                        </label>
                        <input type="email" class="form-control" name="email" 
                               value="<?php echo htmlspecialchars($student['email'] ?? ''); ?>" 
                               <?php echo ($student['profile_updated'] ?? 0) ? 'readonly' : 'required'; ?> 
                               placeholder="Enter your email">
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <label class="form-label">
                            <i class="fa fa-id-card"></i> Roll Number *
                        </label>
                        <input type="text" class="form-control" name="college_id" 
                               value="<?php echo htmlspecialchars($student['college_id'] ?? ''); ?>" 
                               <?php echo ($student['profile_updated'] ?? 0) ? 'readonly' : 'required'; ?> 
                               placeholder="Enter your roll number">
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label">
                            <i class="fa fa-code-fork"></i> Branch *
                        </label>
                        <select class="form-select" name="branch" id="branch" 
                                <?php echo ($student['profile_updated'] ?? 0) ? 'disabled' : 'required'; ?> 
                                onchange="updateSections()">
                            <option value="">Select Your Branch</option>
                            <?php foreach ($branches as $branch): ?>
                                <option value="<?php echo $branch['id']; ?>" 
                                        <?php echo ($student['branch'] == $branch['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($branch['branch_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <label class="form-label">
                            <i class="fa fa-th-list"></i> Section *
                        </label>
                        <select class="form-select" name="section" id="section" 
                                <?php echo ($student['profile_updated'] ?? 0) ? 'disabled' : 'required'; ?>>
                            <option value="">Select Your Section</option>
                            <!-- Sections will be populated by JavaScript -->
                        </select>
                    </div>
                </div>
                
                <?php if ($student['profile_updated'] ?? 0): ?>
                    <div class="alert alert-info mt-3">
                        <i class="fa fa-lock"></i> <strong>Profile Locked:</strong> Your profile has been updated and is now locked. No further changes are allowed.
                    </div>
                <?php endif; ?>
                
                <div class="text-center mt-4">
                    <?php if (!($student['profile_updated'] ?? 0)): ?>
                        <button type="submit" class="btn btn-primary">
                            <i class="fa fa-save"></i> Update Profile
                        </button>
                        <div class="alert alert-warning mt-3">
                            <i class="fa fa-exclamation-triangle"></i> <strong>Important:</strong> You can only update your profile once. Make sure all information is correct before submitting.
                        </div>
                    <?php else: ?>
                        <button type="button" class="btn btn-secondary" disabled>
                            <i class="fa fa-lock"></i> Profile Locked
                        </button>
                    <?php endif; ?>
                </div>
            </form>
            
            <?php if ($student['profile_updated'] ?? 0): ?>
                <div class="mt-4 p-3 bg-light rounded">
                    <h6><i class="fa fa-info-circle"></i> Current Profile Information</h6>
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Name:</strong> <?php echo htmlspecialchars($student['name'] ?? 'Not set'); ?></p>
                            <p><strong>Email:</strong> <?php echo htmlspecialchars($student['email'] ?? 'Not set'); ?></p>
                            <p><strong>Roll Number:</strong> <?php echo htmlspecialchars($student['college_id'] ?? 'Not set'); ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Branch:</strong> <?php echo htmlspecialchars($student['branch_name'] ?? 'Not set'); ?></p>
                            <p><strong>Section:</strong> <?php echo htmlspecialchars($student['section'] ?? 'Not set'); ?></p>
                            <p><strong>Profile Status:</strong> <span class="badge bg-success">Completed</span></p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if ($student['faculty_name']): ?>
                <div class="faculty-info">
                    <h6><i class="fa fa-user-tie"></i> Assigned Faculty</h6>
                    <strong><?php echo htmlspecialchars($student['faculty_name']); ?></strong>
                    <p class="mb-0 mt-1 text-muted">Your projects will be reviewed by this faculty member</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const branchSections = {
            '1': ['A', 'B'],
            '2': ['A', 'B'],
            '3': ['A', 'B']
        };
        
        function updateSections() {
            const branchSelect = document.getElementById('branch');
            const sectionSelect = document.getElementById('section');
            const selectedBranch = branchSelect.value;
            
            sectionSelect.innerHTML = '<option value="">Select Your Section</option>';
            
            if (selectedBranch && branchSections[selectedBranch]) {
                branchSections[selectedBranch].forEach(section => {
                    const option = document.createElement('option');
                    option.value = section;
                    option.textContent = 'Section ' + section;
                    sectionSelect.appendChild(option);
                });
            }
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            updateSections();
            
            const currentSection = '<?php echo $student['section'] ?? ''; ?>';
            if (currentSection) {
                document.getElementById('section').value = currentSection;
            }
        });
    </script>
</body>
</html>
