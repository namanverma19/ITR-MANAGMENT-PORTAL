<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['faculty_id'])) {
    header('Location: login.php');
    exit();
}

$faculty_id = $_SESSION['faculty_id'];
$faculty_name = $_SESSION['faculty_name'];
$faculty_email = $_SESSION['faculty_email'];

$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['send_message'])) {
    $message = trim($_POST['message']);
    $student_ids = $_POST['student_ids'] ?? [];
    
    if (!empty($message) && !empty($student_ids)) {
        try {
            $stmt = mysqli_prepare($connection, "
                INSERT INTO student_notifications (student_id, faculty_id, message, created_at) 
                VALUES (?, ?, ?, NOW())
            ");
            
            $sent_count = 0;
            foreach ($student_ids as $student_id) {
                mysqli_stmt_bind_param($stmt, "iis", $student_id, $faculty_id, $message);
                if (mysqli_stmt_execute($stmt)) {
                    $sent_count++;
                }
            }
            mysqli_stmt_close($stmt);
            
            if ($sent_count > 0) {
                $success_message = "Message sent to {$sent_count} student(s) successfully!";
            }
        } catch (Exception $e) {
            $error_message = "Failed to send message. Please try again.";
        }
    } else {
        $error_message = "Please enter a message and select students.";
    }
}

$filter_branch = $_GET['branch'] ?? '';
$filter_section = $_GET['section'] ?? '';


$branches = [];
try {
    $stmt = mysqli_prepare($connection, "SELECT id, branch_name FROM branches ORDER BY branch_name");
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $branches[] = $row;
    }
    mysqli_stmt_close($stmt);
} catch (Exception $e) {
    $branches = [];
}

try {
    $sql = "
        SELECT DISTINCT 
            r.id, r.name, r.email, r.college_id, 
            COALESCE(b.branch_name, 'Computer Science Engineering') as branch_name, 
            COALESCE(r.section, 'A') as section,
            ps.project_name,
            CASE 
                WHEN ps.id IS NULL THEN 'Not Submitted'
                WHEN ps.submission_status = 'pending' THEN 'Pending'
                WHEN ps.submission_status = 'accepted' THEN 'Accepted'
                WHEN ps.submission_status = 'rejected' THEN 'Rejected'
                ELSE 'Not Submitted'
            END as submission_status,
            CASE WHEN ps.id IS NOT NULL THEN 1 ELSE 0 END as has_submitted,
            ps.submitted_at
        FROM itr_registeruser r
        LEFT JOIN branches b ON r.branch = b.id
        LEFT JOIN project_submissions ps ON r.id = ps.student_id
        WHERE r.id IS NOT NULL
    ";
    
    $params = [];
    $types = "";
    
    
    if ($filter_branch) {
        $sql .= " AND (b.id = ? OR r.branch = ?)";
        $params[] = $filter_branch;
        $params[] = $filter_branch;
        $types .= "ss";
    }
    
    
    if ($filter_section) {
        $sql .= " AND r.section = ?";
        $params[] = $filter_section;
        $types .= "s";
    }
    
    $sql .= " ORDER BY COALESCE(b.branch_name, 'Computer Science Engineering'), r.section, r.name ASC";
    
    if (!empty($params)) {
        $stmt = mysqli_prepare($connection, $sql);
        mysqli_stmt_bind_param($stmt, $types, ...$params);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
    } else {
        $result = mysqli_query($connection, $sql);
    }
    
    $all_students = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $all_students[] = $row;
    }
    
    if (!empty($params)) {
        mysqli_stmt_close($stmt);
    }
    

    $students_by_section = [];
    $submitted_students = [];
    $not_submitted_students = [];
    
    foreach ($all_students as $student) {
        $section_key = $student['branch_name'] . ' - Section ' . $student['section'];
        $students_by_section[$section_key][] = $student;
        
        if ($student['has_submitted']) {
            $submitted_students[] = $student;
        } else {
            $not_submitted_students[] = $student;
        }
    }
    
} catch (Exception $e) {
    $all_students = [];
    $students_by_section = [];
    $submitted_students = [];
    $not_submitted_students = [];
    $error_message = "Failed to load student list.";
}

try {
    mysqli_query($connection, "
        CREATE TABLE IF NOT EXISTS student_notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_id INT NOT NULL,
            faculty_id INT NOT NULL,
            message TEXT NOT NULL,
            is_read TINYINT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (student_id) REFERENCES itr_registeruser(id),
            FOREIGN KEY (faculty_id) REFERENCES itr_facultyuser(id)
        )
    ");
} catch (Exception $e) {
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student List - ITR Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Roboto', sans-serif;
            line-height: 1.6;
            color: #3E2723;
            background-color: #F8F5F2;
        }
        
        .logo {
            height: 48px;
            margin-right: 12px;
            border-radius: 60px;
        }
        
        .navbar-gradient {
            background: linear-gradient(to right, #1B5E20, #2E7D32, #388E3C);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
        }
        
        .student-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
            margin-bottom: 20px;
            border-left: 5px solid #1B5E20;
        }
        
        .student-card.submitted {
            border-left-color: #28a745;
            background: linear-gradient(135deg, #f8fff9 0%, #e8f5e8 100%);
        }
        
        .student-card.not-submitted {
            border-left-color: #d81b2bff;
            background: linear-gradient(135deg, #fff8f8 0%, #ffeaea 100%);
        }
        
        .section-header {
            background: linear-gradient(135deg, #1B5E20, #388E3C);
            color: white;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .status-badge {
            font-size: 0.8em;
            padding: 5px 10px;
            border-radius: 15px;
            font-weight: bold;
        }
        
        .status-submitted {
            background-color: #28a745;
            color: white;
        }
        
        .status-pending {
            background-color: #dc3545;
            color: white;
        }
        
        .status-badge:contains("Accepted") {
            background-color: #28a745;
            color: white;
        }
        
        .status-badge:contains("Rejected") {
            background-color: #dc3545;
            color: white;
        }
        
        .status-badge:contains("Pending") {
            background-color: #ffc107;
            color: #212529;
        }
        
        .status-badge:contains("Not Submitted") {
            background-color: #6c757d;
            color: white;
        }
        
        .filters-container {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 30px;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .filter-group {
            margin-bottom: 15px;
        }
        
        .filter-group:last-child {
            margin-bottom: 0;
        }
        
        .form-select, .form-control {
            border-radius: 10px;
            border: 1px solid rgba(255, 255, 255, 0.3);
            background: rgba(255, 255, 255, 0.9);
            transition: all 0.3s ease;
        }
        
        .form-select:focus, .form-control:focus {
            border-color: #1B5E20;
            box-shadow: 0 0 0 0.2rem rgba(27, 94, 32, 0.25);
            background: white;
        }
        
        .btn {
            border-radius: 10px;
            transition: all 0.3s ease;
        }
        
        .btn:hover {
            transform: translateY(-2px);
        }
        
        .message-box {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
        }
        
        .student-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
        }
        
        .student-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #1B5E20, #388E3C);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
            font-weight: bold;
        }
        
        .bg-secondary {
            background-color: #1B5E20 !important;
        }
        
        .footer-block {
            padding-left: 8px;
            padding-right: 8px;
            width: 100%;
            display: flex;
            flex-direction: column;
            align-items: flex-start;
        }
        
        .social-icon {
            font-size: 1.7rem;
            color: rgba(232, 245, 233, 0.6);
            transition: color 0.3s ease, filter 0.3s ease;
            filter: grayscale(100%) brightness(150%);
        }
        
        .social-icon:hover {
            color: #ffffff !important;
            filter: grayscale(0%) brightness(100%);
        }
    </style>
</head>
<body>
    <header>
        <nav class="navbar navbar-expand-lg navbar-gradient py-3">
            <div class="container-fluid px-4">
                <a class="navbar-brand fs-4 d-flex align-items-center" href="dashboard.php">
                    <img src="../img/Logo.png" alt="Portal Logo" class="logo">
                    <span class="text-white ms-2 fw-bold">STUDENT LIST</span>
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav ms-auto">
                        <li class="nav-item">
                            <a class="nav-link text-white px-3" href="dashboard.php">
                                <i class="fa fa-tachometer" aria-hidden="true"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white px-3 active" href="studentlist.php">
                                <i class="fa fa-users" aria-hidden="true"></i> Student List
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white px-3" href="projectreview.php">
                                <i class="fa fa-clipboard" aria-hidden="true"></i> Project Review
                            </a>
                        </li>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle text-white px-3" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fa fa-user-circle" aria-hidden="true"></i> <?php echo htmlspecialchars($faculty_name); ?>
                            </a>
                            <ul class="dropdown-menu" aria-labelledby="navbarDropdown">
                                <li><a class="dropdown-item" href="dashboard.php#profile"><i class="fa fa-user" aria-hidden="true"></i> My Profile</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="logout.php"><i class="fa fa-sign-out" aria-hidden="true"></i> Logout</a></li>
                            </ul>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>
    </header>

    <main>
        <div class="container my-5">
            <?php if ($success_message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fa fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fa fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="row">
                <div class="col-12">
                    <h2 class="mb-4 text-center" style="color: #1B5E20;">
                        <i class="fa fa-users"></i> My Students
                    </h2>
                    <p class="text-center text-muted mb-4">Students assigned under your supervision</p>
                    
                    <?php if (empty($all_students)): ?>
                        <div class="card student-card text-center">
                            <div class="card-body py-5">
                                <i class="fa fa-users fa-3x mb-3" style="color: #1B5E20;"></i>
                                <h5>No Students Assigned</h5>
                                <p class="text-muted">No students are currently assigned to your supervision.</p>
                            </div>
                        </div>
                    <?php else: ?>
                        
                        <div class="row mb-4">
                            <div class="col-md-4">
                                <div class="card border-success">
                                    <div class="card-body text-center">
                                        <h5 class="card-title text-success">
                                            <i class="fa fa-check-circle"></i> Projects Submitted
                                        </h5>
                                        <h2 class="text-success"><?php echo count($submitted_students); ?></h2>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card border-danger">
                                    <div class="card-body text-center">
                                        <h5 class="card-title text-danger">
                                            <i class="fa fa-clock-o"></i> Pending Submissions
                                        </h5>
                                        <h2 class="text-danger"><?php echo count($not_submitted_students); ?></h2>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card border-primary">
                                    <div class="card-body text-center">
                                        <h5 class="card-title text-primary">
                                            <i class="fa fa-users"></i> Total Students
                                        </h5>
                                        <h2 class="text-primary"><?php echo count($all_students); ?></h2>
                                    </div>
                                </div>
                            </div>
                        </div>

                
                        <?php if (!empty($not_submitted_students)): ?>
                            <div class="message-box">
                                <h5 style="color: #1B5E20;">
                                    <i class="fa fa-envelope"></i> Send Message to Pending Students
                                </h5>
                                <form method="POST" action="">
                                    <div class="mb-3">
                                        <label for="message" class="form-label">Message:</label>
                                        <textarea class="form-control" id="message" name="message" rows="3" 
                                                placeholder="Enter your message for students who haven't submitted projects..." required></textarea>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Select Students:</label>
                                        <div class="row">
                                            <div class="col-md-3 mb-2">
                                                <label>
                                                    <input type="checkbox" id="selectAll" onchange="toggleAll()"> 
                                                    <strong>Select All Pending</strong>
                                                </label>
                                            </div>
                                            <?php foreach ($not_submitted_students as $student): ?>
                                                <div class="col-md-3 mb-2">
                                                    <label>
                                                        <input type="checkbox" name="student_ids[]" value="<?php echo $student['id']; ?>" class="student-checkbox">
                                                        <?php echo htmlspecialchars($student['name']); ?>
                                                    </label>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <button type="submit" name="send_message" class="btn btn-success">
                                        <i class="fa fa-paper-plane"></i> Send Message
                                    </button>
                                </form>
                            </div>
                        <?php endif; ?>

                    
                        <div class="card mb-4" style="background: linear-gradient(135deg, #E8F5E8, #F1F8E9);">
                            <div class="card-body">
                                <h5 style="color: #1B5E20;">
                                    <i class="fa fa-filter"></i> Filter Students
                                </h5>
                                <form method="GET" action="" id="filterForm">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <label for="branch" class="form-label">Branch:</label>
                                            <select class="form-select" id="branch" name="branch" onchange="applyFilter()">
                                                <option value="">All Branches</option>
                                                <?php foreach ($branches as $branch): ?>
                                                    <option value="<?php echo $branch['id']; ?>" 
                                                            <?php echo ($filter_branch == $branch['id']) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($branch['branch_name']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label for="section" class="form-label">Section:</label>
                                            <select class="form-select" id="section" name="section" onchange="applyFilter()">
                                                <option value="">All Sections</option>
                                                <option value="A" <?php echo ($filter_section == 'A') ? 'selected' : ''; ?>>Section A</option>
                                                <option value="B" <?php echo ($filter_section == 'B') ? 'selected' : ''; ?>>Section B</option>
                                                <option value="C" <?php echo ($filter_section == 'C') ? 'selected' : ''; ?>>Section C</option>
                                                <option value="D" <?php echo ($filter_section == 'D') ? 'selected' : ''; ?>>Section D</option>
                                                <option value="E" <?php echo ($filter_section == 'E') ? 'selected' : ''; ?>>Section E</option>
                                                <option value="F" <?php echo ($filter_section == 'F') ? 'selected' : ''; ?>>Section F</option>
                                            </select>
                                        </div>
                                        <div class="col-md-4 d-flex align-items-end">
                                            <button type="button" class="btn btn-secondary me-2" onclick="clearFilters()">
                                                <i class="fa fa-refresh"></i> Clear Filters
                                            </button>
                                            <span class="badge bg-success fs-6">
                                                <i class="fa fa-users"></i> <?php echo count($all_students); ?> Students Found
                                            </span>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>

                    
                        <ul class="nav nav-tabs mb-4" id="studentTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="section-tab" data-bs-toggle="tab" data-bs-target="#section-wise" type="button" role="tab">
                                    <i class="fa fa-th-list"></i> Section Wise
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="submitted-tab" data-bs-toggle="tab" data-bs-target="#submitted-students" type="button" role="tab">
                                    <i class="fa fa-check-circle text-success"></i> Submitted (<?php echo count($submitted_students); ?>)
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="pending-tab" data-bs-toggle="tab" data-bs-target="#pending-students" type="button" role="tab">
                                    <i class="fa fa-clock-o text-danger"></i> Pending (<?php echo count($not_submitted_students); ?>)
                                </button>
                            </li>
                        </ul>

                       
                        <div class="tab-content" id="studentTabContent">
                            
                            <div class="tab-pane fade show active" id="section-wise" role="tabpanel">
                                <?php foreach ($students_by_section as $section => $students): ?>
                                    <div class="section-header">
                                        <h4 class="mb-0">
                                            <i class="fa fa-graduation-cap"></i> <?php echo htmlspecialchars($section); ?>
                                            <span class="badge bg-light text-dark ms-2"><?php echo count($students); ?> Students</span>
                                        </h4>
                                    </div>
                                    
                                    <div class="row mb-5">
                                        <?php foreach ($students as $student): ?>
                                            <div class="col-md-6 col-lg-4 mb-4">
                                                <div class="card student-card <?php echo $student['has_submitted'] ? 'submitted' : 'not-submitted'; ?> h-100">
                                                    <div class="card-body">
                                                        <div class="d-flex align-items-center mb-3">
                                                            <div class="student-avatar me-3">
                                                                <?php echo strtoupper(substr($student['name'], 0, 1)); ?>
                                                            </div>
                                                            <div class="flex-grow-1">
                                                                <h5 class="card-title mb-1"><?php echo htmlspecialchars($student['name']); ?></h5>
                                                                <small class="text-muted">ID: <?php echo htmlspecialchars($student['college_id']); ?></small>
                                                            </div>
                                                            <span class="status-badge <?php echo $student['has_submitted'] ? 'status-submitted' : 'status-pending'; ?>">
                                                                <?php echo $student['submission_status']; ?>
                                                            </span>
                                                        </div>
                                                        
                                                        <div class="student-details">
                                                            <p class="mb-2">
                                                                <i class="fa fa-graduation-cap text-muted"></i> 
                                                                <strong><?php echo htmlspecialchars($student['branch_name']); ?></strong> - Section <?php echo $student['section']; ?>
                                                            </p>
                                                            
                                                            <?php if ($student['project_name']): ?>
                                                            <p class="mb-2">
                                                                <i class="fa fa-file-text text-muted"></i> 
                                                                <strong>Project:</strong><br>
                                                                <small><?php echo htmlspecialchars($student['project_name']); ?></small>
                                                            </p>
                                                            <?php endif; ?>
                                                            
                                                            <p class="mb-3">
                                                                <i class="fa fa-envelope text-muted"></i> 
                                                                <small><?php echo htmlspecialchars($student['email']); ?></small>
                                                            </p>
                                                            
                                                            <div class="d-grid">
                                                                <a href="projectreview.php?student_id=<?php echo $student['id']; ?>" 
                                                                   class="btn btn-outline-success btn-sm">
                                                                    <i class="fa fa-eye"></i> View Details
                                                                </a>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                           
                            <div class="tab-pane fade" id="submitted-students" role="tabpanel">
                                <div class="row">
                                    <?php foreach ($submitted_students as $student): ?>
                                        <div class="col-md-6 col-lg-4 mb-4">
                                            <div class="card student-card submitted h-100">
                                                <div class="card-body">
                                                    <div class="d-flex align-items-center mb-3">
                                                        <div class="student-avatar me-3">
                                                            <?php echo strtoupper(substr($student['name'], 0, 1)); ?>
                                                        </div>
                                                        <div class="flex-grow-1">
                                                            <h5 class="card-title mb-1"><?php echo htmlspecialchars($student['name']); ?></h5>
                                                            <small class="text-muted">ID: <?php echo htmlspecialchars($student['college_id']); ?></small>
                                                        </div>
                                                        <span class="status-badge status-submitted">
                                                            <?php echo $student['submission_status']; ?>
                                                        </span>
                                                    </div>
                                                    
                                                    <div class="student-details">
                                                        <p class="mb-2">
                                                            <i class="fa fa-graduation-cap text-muted"></i> 
                                                            <strong><?php echo htmlspecialchars($student['branch_name']); ?></strong> - Section <?php echo $student['section']; ?>
                                                        </p>
                                                        
                                                        <?php if ($student['project_name']): ?>
                                                        <p class="mb-2">
                                                            <i class="fa fa-file-text text-muted"></i> 
                                                            <strong>Project:</strong><br>
                                                            <small><?php echo htmlspecialchars($student['project_name']); ?></small>
                                                        </p>
                                                        <?php endif; ?>
                                                        
                                                        <p class="mb-3">
                                                            <i class="fa fa-envelope text-muted"></i> 
                                                            <small><?php echo htmlspecialchars($student['email']); ?></small>
                                                        </p>
                                                        
                                                        <div class="d-grid">
                                                            <a href="projectreview.php?student_id=<?php echo $student['id']; ?>" 
                                                               class="btn btn-success btn-sm">
                                                                <i class="fa fa-eye"></i> Review Project
                                                            </a>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            
                            <div class="tab-pane fade" id="pending-students" role="tabpanel">
                                <div class="row">
                                    <?php foreach ($not_submitted_students as $student): ?>
                                        <div class="col-md-6 col-lg-4 mb-4">
                                            <div class="card student-card not-submitted h-100">
                                                <div class="card-body">
                                                    <div class="d-flex align-items-center mb-3">
                                                        <div class="student-avatar me-3">
                                                            <?php echo strtoupper(substr($student['name'], 0, 1)); ?>
                                                        </div>
                                                        <div class="flex-grow-1">
                                                            <h5 class="card-title mb-1"><?php echo htmlspecialchars($student['name']); ?></h5>
                                                            <small class="text-muted">ID: <?php echo htmlspecialchars($student['college_id']); ?></small>
                                                        </div>
                                                        <span class="status-badge status-pending">
                                                            <?php echo $student['submission_status']; ?>
                                                        </span>
                                                    </div>
                                                    
                                                    <div class="student-details">
                                                        <p class="mb-2">
                                                            <i class="fa fa-graduation-cap text-muted"></i> 
                                                            <strong><?php echo htmlspecialchars($student['branch_name']); ?></strong> - Section <?php echo $student['section']; ?>
                                                        </p>
                                                        
                                                        <p class="mb-3">
                                                            <i class="fa fa-envelope text-muted"></i> 
                                                            <small><?php echo htmlspecialchars($student['email']); ?></small>
                                                        </p>
                                                        
                                                        <div class="d-grid">
                                                            <button class="btn btn-outline-danger btn-sm" disabled>
                                                                <i class="fa fa-times"></i> No Submission
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
     <footer class="text-center text-lg-start bg-dark mt-5 text-white py-5">
        <div class="container p-4">
            <div class="row g-4">
                <div class="col-lg-4 col-md-6 mb-4 mb-md-0">
                    <div class="footer-block d-flex flex-column align-items-center align-items-lg-start">
                        <div class="footer-block-heading">
                            <h5 class="text-uppercase fw-bold mb-2 text-center text-lg-start">
                                ITR<br>Management<br>Portal
                            </h5>
                        </div>
                        <p class="text-white-50 text-center text-lg-start mt-3 mb-0">
                            Your comprehensive solution for industrial training reports.
                        </p>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6 mb-4 mb-md-0">
                    <div class="footer-block">
                        <h5 class="text-uppercase fw-bold mb-3 text-center text-lg-start">Quick Links</h5>
                        <ul class="list-unstyled text-center text-lg-start">
                            <li class="mb-2"><a href="home.html" class="text-white-50 text-decoration-none">Dashboard</a></li>
                            <li class="mb-2"><a href="features.html" class="text-white-50 text-decoration-none">Project Submission</a></li>
                            <li class="mb-2"><a href="contact.html" class="text-white-50 text-decoration-none">Project Status</a></li>
                            <li class="mb-2"><a href="about.html" class="text-white-50 text-decoration-none">About Us</a></li>
                        </ul>
                    </div>
                </div>

                <div class="col-lg-4 col-md-12">
                    <div class="footer-block">
                        <h5 class="text-uppercase fw-bold mb-3 text-center text-lg-start">Connect With Us</h5>
                        <div class="d-flex justify-content-center justify-content-lg-start">
                            <a href="https://www.instagram.com" target="_blank" class="text-white-50 mx-2 social-icon">
                                <i class="fa fa-instagram fa-lg"></i>
                            </a>
                            <a href="https://www.youtube.com" target="_blank" class="text-white-50 mx-2 social-icon">
                                <i class="fa fa-youtube-play fa-lg"></i>
                            </a>
                            <a href="https://www.linkedin.com" target="_blank" class="text-white-50 mx-2 social-icon">
                                <i class="fa fa-linkedin-square fa-lg"></i>
                            </a>
                            <a href="https://www.twitter.com" target="_blank" class="text-white-50 mx-2 social-icon">
                                <i class="fa fa-twitter-square fa-lg"></i>
                            </a>
                            <a href="https://www.facebook.com" target="_blank" class="text-white-50 mx-2 social-icon">
                                <i class="fa fa-facebook-square fa-lg"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="text-center py-3 bg-secondary text-white-75 border-top border-secondary mt-4">
            © 2025 ITR Portal. All rights reserved.
            <span class="d-block d-md-inline-block ms-md-3 mt-2 mt-md-0">
                <a href="#" class="text-white-50 text-decoration-none mx-2">Privacy Policy</a>
                <a href="#" class="text-white-50 text-decoration-none mx-2">Terms of Use</a>
                <a href="#" class="text-white-50 text-decoration-none mx-2">Accessibility</a>
            </span>
        </div>
    </footer>
    
    <script>
        
        function applyFilter() {
            const branchFilter = document.getElementById('branch').value;
            const sectionFilter = document.getElementById('section').value;
            
           
            const params = new URLSearchParams();
            if (branchFilter) params.append('branch', branchFilter);
            if (sectionFilter) params.append('section', sectionFilter);
            
           
            const baseUrl = window.location.pathname;
            const newUrl = params.toString() ? `${baseUrl}?${params.toString()}` : baseUrl;
            window.location.href = newUrl;
        }
        
        function clearFilters() {
            document.getElementById('branch').value = '';
            document.getElementById('section').value = '';
            window.location.href = window.location.pathname;
        }
        
        
        function updateSections() {
            const branchFilter = document.getElementById('branch');
            const sectionFilter = document.getElementById('section');
            const selectedBranch = branchFilter.value;
            
            
            sectionFilter.innerHTML = '<option value="">All Sections</option>';
            
            if (selectedBranch) {
                const sections = ['A', 'B', 'C', 'D', 'E', 'F'];
                sections.forEach(section => {
                    const option = document.createElement('option');
                    option.value = section;
                    option.textContent = `Section ${section}`;
                    sectionFilter.appendChild(option);
                });
            }
        }
        
        
        document.addEventListener('DOMContentLoaded', function() {
            
            updateSections();
            
          
            const urlParams = new URLSearchParams(window.location.search);
            const branchParam = urlParams.get('branch');
            const sectionParam = urlParams.get('section');
            
            if (branchParam) {
                document.getElementById('branch').value = branchParam;
                updateSections();
            }
            if (sectionParam) {
                document.getElementById('section').value = sectionParam;
            }
            
            
            document.getElementById('branch').addEventListener('change', applyFilter);
            document.getElementById('section').addEventListener('change', applyFilter);
        });
        
        function toggleAll() {
            const selectAll = document.getElementById('selectAll');
            const checkboxes = document.querySelectorAll('.student-checkbox');
            
            checkboxes.forEach(checkbox => {
                checkbox.checked = selectAll.checked;
            });
        }
    
        document.addEventListener('DOMContentLoaded', function() {
            const checkboxes = document.querySelectorAll('.student-checkbox');
            const selectAll = document.getElementById('selectAll');
            
            if (checkboxes.length > 0 && selectAll) {
                checkboxes.forEach(checkbox => {
                    checkbox.addEventListener('change', function() {
                        const totalCheckboxes = checkboxes.length;
                        const checkedCheckboxes = document.querySelectorAll('.student-checkbox:checked').length;
                        
                        selectAll.checked = totalCheckboxes === checkedCheckboxes;
                        selectAll.indeterminate = checkedCheckboxes > 0 && checkedCheckboxes < totalCheckboxes;
                    });
                });
            }
        });
        
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }, 5000);
            });
        });
    </script>
</body>
</html>