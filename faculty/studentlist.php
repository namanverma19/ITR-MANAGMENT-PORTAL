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

$error_message = '';
$success_message = '';

// Handle message sending
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['send_message'])) {
    $message_subject = trim($_POST['message_subject']);
    $message_content = trim($_POST['message_content']);
    
    if (!empty($message_subject) && !empty($message_content)) {
        try {
            // Get all students who haven't submitted
            $stmt = mysqli_prepare($connection, "
                SELECT r.id, r.name, r.email 
                FROM itr_registeruser r
                LEFT JOIN project_submissions ps ON r.id = ps.student_id
                WHERE r.assigned_faculty_id = ? AND (ps.id IS NULL OR ps.submission_status = 'rejected')
            ");
            mysqli_stmt_bind_param($stmt, "i", $faculty_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            
            $not_submitted_students = [];
            while ($row = mysqli_fetch_assoc($result)) {
                $not_submitted_students[] = $row;
            }
            mysqli_stmt_close($stmt);
            
            // Insert notifications for each student
            $notification_count = 0;
            foreach ($not_submitted_students as $student) {
                $stmt = mysqli_prepare($connection, "
                    INSERT INTO notifications (student_id, faculty_id, subject, message, created_at, is_read) 
                    VALUES (?, ?, ?, ?, NOW(), 0)
                ");
                mysqli_stmt_bind_param($stmt, "iiss", $student['id'], $faculty_id, $message_subject, $message_content);
                if (mysqli_stmt_execute($stmt)) {
                    $notification_count++;
                }
                mysqli_stmt_close($stmt);
            }
            
            if ($notification_count > 0) {
                $success_message = "Message sent successfully to {$notification_count} students who haven't submitted their projects.";
            } else {
                $error_message = "No students found who haven't submitted their projects.";
            }
            
        } catch (Exception $e) {
            $error_message = "Failed to send messages: " . $e->getMessage();
        }
    } else {
        $error_message = "Please provide both subject and message content.";
    }
}

// Get faculty branches and sections for navigation
try {
    $stmt = mysqli_prepare($connection, "
        SELECT DISTINCT b.id, b.branch_name, r.section
        FROM itr_registeruser r
        JOIN branches b ON r.branch = b.id
        WHERE r.assigned_faculty_id = ?
        ORDER BY b.branch_name, r.section
    ");
    mysqli_stmt_bind_param($stmt, "i", $faculty_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $faculty_branches = [];
    $faculty_sections = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $branch_key = $row['branch_name'];
        if (!isset($faculty_branches[$branch_key])) {
            $faculty_branches[$branch_key] = [
                'id' => $row['id'],
                'name' => $row['branch_name'],
                'sections' => []
            ];
        }
        if (!in_array($row['section'], $faculty_branches[$branch_key]['sections'])) {
            $faculty_branches[$branch_key]['sections'][] = $row['section'];
        }
        
        $section_key = $row['branch_name'] . ' - Section ' . $row['section'];
        $faculty_sections[$section_key] = [
            'branch' => $row['branch_name'],
            'section' => $row['section']
        ];
    }
    mysqli_stmt_close($stmt);
} catch (Exception $e) {
    $faculty_branches = [];
    $faculty_sections = [];
}

$selected_branch = isset($_GET['branch']) ? $_GET['branch'] : '';
$selected_section = isset($_GET['section']) ? $_GET['section'] : '';

// Fetch students grouped by section
try {
    $base_query = "
        SELECT 
            r.id, r.name, r.email, r.college_id, 
            b.branch_name, r.section,
            ps.file_path,
            CASE 
                WHEN ps.id IS NULL THEN 'Not Submitted'
                WHEN ps.submission_status = 'pending' THEN 'Pending'
                WHEN ps.submission_status = 'accepted' THEN 'Accepted'
                WHEN ps.submission_status = 'rejected' THEN 'Rejected'
                ELSE 'Not Submitted'
            END as submission_status
        FROM itr_registeruser r
        LEFT JOIN branches b ON r.branch = b.id
        LEFT JOIN project_submissions ps ON r.id = ps.student_id
        WHERE r.assigned_faculty_id = ?";
    
    // Add branch filtering
    if ($selected_branch) {
        $base_query .= " AND b.branch_name = ?";
    }
    
    // Add section filtering
    if ($selected_section) {
        $section_parts = explode(' - Section ', $selected_section);
        if (count($section_parts) == 2) {
            $section_branch = $section_parts[0];
            $section_num = $section_parts[1];
            $base_query .= " AND b.branch_name = ? AND r.section = ?";
        }
    }
    
    $base_query .= " ORDER BY b.branch_name, r.section, r.name ASC";
    
    // Prepare statement based on filters
    if ($selected_branch && $selected_section && isset($section_branch) && isset($section_num)) {
        $stmt = mysqli_prepare($connection, $base_query);
        mysqli_stmt_bind_param($stmt, "isss", $faculty_id, $selected_branch, $section_branch, $section_num);
    } elseif ($selected_branch) {
        $stmt = mysqli_prepare($connection, $base_query);
        mysqli_stmt_bind_param($stmt, "is", $faculty_id, $selected_branch);
    } elseif ($selected_section && isset($section_branch) && isset($section_num)) {
        $stmt = mysqli_prepare($connection, $base_query);
        mysqli_stmt_bind_param($stmt, "iss", $faculty_id, $section_branch, $section_num);
    } else {
        $stmt = mysqli_prepare($connection, $base_query);
        mysqli_stmt_bind_param($stmt, "i", $faculty_id);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $students_by_section = [];
    
    while ($row = mysqli_fetch_assoc($result)) {
        $section_key = $row['branch_name'] . ' - Section ' . $row['section'];
        $students_by_section[$section_key][] = $row;
    }
    mysqli_stmt_close($stmt);
    
} catch (Exception $e) {
    $students_by_section = [];
    $error_message = "Failed to load student list: " . $e->getMessage();
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
    
    <style>
        body {
            font-family: 'Roboto', sans-serif;
            line-height: 1.6;
            color: #3E2723;
            background-color: #F8F5F2;
            margin: 0;
            padding: 0;
        }
        
        .logo {
            height: 48px;
            margin-right: 12px;
            border-radius: 60px;
        }
        
        .navbar-gradient {
            background: linear-gradient(to right, #1B5E20, #2E7D32, #388E3C);
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
        }
        
        .stats-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
            overflow: hidden;
            transition: all 0.3s ease;
        }
        
        .stats-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }
        
        .stats-header {
            background: linear-gradient(135deg, #1B5E20, #388E3C);
            color: white;
            padding: 20px;
            text-align: center;
        }
        
        .stats-body {
            padding: 30px 20px;
        }
        
        .stat-item {
            text-align: center;
            padding: 15px;
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            display: block;
            color: #1B5E20;
        }
        
        .stat-label {
            font-size: 1rem;
            color: #666;
            margin-top: 5px;
        }
        
        .section-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
            overflow: hidden;
            transition: all 0.3s ease;
        }
        
        .section-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }
        
        .section-header {
            background: linear-gradient(135deg, #1B5E20, #388E3C);
            color: white;
            padding: 20px;
            cursor: pointer;
        }
        
        .section-title {
            font-size: 1.3rem;
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        .section-info {
            font-size: 1rem;
            opacity: 0.9;
        }
        
        .students-table {
            margin: 20px;
            display: none;
        }
        
        .students-table.show {
            display: block;
        }
        
        .students-table th {
            background: linear-gradient(135deg, #1B5E20, #388E3C);
            color: white;
            border: none;
            font-weight: 600;
            padding: 15px 10px;
        }
        
        .students-table td {
            vertical-align: middle;
            border-bottom: 1px solid #e9ecef;
            padding: 12px 10px;
        }
        
        .btn-toggle {
            background: rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: white;
            padding: 8px 20px;
            border-radius: 20px;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            margin-top: 10px;
        }
        
        .btn-toggle:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-2px);
            color: white;
        }
        
        .status-accepted {
            background-color: #28a745;
            color: white;
            padding: 6px 12px;
            border-radius: 15px;
            font-size: 0.85em;
            font-weight: bold;
        }
        
        .status-pending {
            background-color: #ffc107;
            color: #212529;
            padding: 6px 12px;
            border-radius: 15px;
            font-size: 0.85em;
            font-weight: bold;
        }
        
        .status-not-submitted {
            background-color: #dc3545;
            color: white;
            padding: 6px 12px;
            border-radius: 15px;
            font-size: 0.85em;
            font-weight: bold;
        }
        
        .status-rejected {
            background-color: #6c757d;
            color: white;
            padding: 6px 12px;
            border-radius: 15px;
            font-size: 0.85em;
            font-weight: bold;
        }
        
        .bg-secondary {
            background-color: #1B5E20 !important;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }
        
        .empty-state i {
            font-size: 4rem;
            color: #1B5E20;
            margin-bottom: 20px;
        }
        
        .section-nav {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            padding: 20px;
            margin-bottom: 30px;
        }
        
        .section-nav h5 {
            color: #1B5E20;
            margin-bottom: 15px;
            font-weight: bold;
        }
        
        .btn-section {
            background: linear-gradient(135deg, #1B5E20, #388E3C);
            border: none;
            color: white;
            padding: 10px 20px;
            border-radius: 20px;
            font-size: 0.9rem;
            margin: 5px;
            transition: all 0.3s ease;
        }
        
        .btn-section:hover {
            background: linear-gradient(135deg, #0D47A1, #1565C0);
            transform: translateY(-2px);
            color: white;
        }
        
        .btn-section.active {
            background: linear-gradient(135deg, #0D47A1, #1565C0);
            transform: translateY(-2px);
        }
        
        .btn-download {
            background: linear-gradient(135deg, #28a745, #20c997);
            border: none;
            color: white;
            padding: 8px 15px;
            border-radius: 15px;
            font-size: 0.8rem;
            transition: all 0.3s ease;
        }
        
        .btn-download:hover {
            background: linear-gradient(135deg, #20c997, #17a2b8);
            transform: translateY(-2px);
            color: white;
        }
        
        .message-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
            overflow: hidden;
            transition: all 0.3s ease;
        }
        
        .message-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }
        
        .message-header {
            background: linear-gradient(135deg, #dc3545, #e74c3c);
            color: white;
            padding: 20px;
            text-align: center;
        }
        
        .message-body {
            padding: 30px 20px;
        }
        
        .btn-send-message {
            background: linear-gradient(135deg, #dc3545, #e74c3c);
            border: none;
            color: white;
            padding: 12px 30px;
            border-radius: 25px;
            font-size: 1rem;
            transition: all 0.3s ease;
            font-weight: bold;
        }
        
        .btn-send-message:hover {
            background: linear-gradient(135deg, #c82333, #dc3545);
            transform: translateY(-2px);
            color: white;
        }
    </style>
</head>
<body>
    <header>
        <nav class="navbar navbar-expand-lg navbar-gradient py-3">
            <div class="container-fluid px-4">
                <a class="navbar-brand fs-4 d-flex align-items-center" href="dashboard.php">
                    <img src="../img/Logo.png" alt="Portal Logo" class="logo">
                    <span class="text-white ms-2 fw-bold">ITR PORTAL</span>
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
                            <a class="nav-link text-white px-3" href="studentprojectlist.php">
                                <i class="fa fa-file-text" aria-hidden="true"></i> Project List
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
                                <li><a class="dropdown-item" href="dashboard.php"><i class="fa fa-user" aria-hidden="true"></i> My Profile</a></li>
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

            <!-- Page Title -->
            <div class="row">
                <div class="col-12">
                    <h2 class="mb-4 text-center" style="color: #1B5E20;">
                        <i class="fa fa-users"></i> My Students
                    </h2>
                </div>
            </div>

            <!-- Message to Non-Submitted Students -->
            <div class="message-card">
                <div class="message-header">
                    <h4 class="mb-0">
                        <i class="fa fa-envelope"></i> Send Message to Students Who Haven't Submitted
                    </h4>
                </div>
                <div class="message-body">
                    <form method="POST" action="">
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label for="message_subject" class="form-label fw-bold">Subject:</label>
                                <input type="text" class="form-control" id="message_subject" name="message_subject" 
                                       placeholder="Enter message subject" required>
                            </div>
                            <div class="col-md-12 mb-3">
                                <label for="message_content" class="form-label fw-bold">Message:</label>
                                <textarea class="form-control" id="message_content" name="message_content" rows="4" 
                                          placeholder="Enter your message to students who haven't submitted their projects..." required></textarea>
                            </div>
                            <div class="col-md-12 text-center">
                                <button type="submit" name="send_message" class="btn btn-send-message">
                                    <i class="fa fa-paper-plane"></i> Send Message to Non-Submitted Students
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Branch Selection -->
            <?php if (!empty($faculty_branches)): ?>
            <div class="section-nav">
                <h5><i class="fa fa-graduation-cap"></i> Select Branch to View Students:</h5>
                <div class="d-flex flex-wrap mb-3">
                    <a href="studentlist.php" class="btn btn-section <?php echo empty($selected_branch) && empty($selected_section) ? 'active' : ''; ?>">
                        <i class="fa fa-list"></i> All Branches
                    </a>
                    <?php foreach ($faculty_branches as $branch): ?>
                        <a href="studentlist.php?branch=<?php echo urlencode($branch['name']); ?>" 
                           class="btn btn-section <?php echo $selected_branch === $branch['name'] ? 'active' : ''; ?>">
                            <i class="fa fa-building"></i> <?php echo htmlspecialchars($branch['name']); ?>
                            <span class="badge bg-light text-dark ms-1">
                                Sections: <?php echo implode(', ', $branch['sections']); ?>
                            </span>
                        </a>
                    <?php endforeach; ?>
                </div>
                
                <?php if ($selected_branch): ?>
                    <div class="alert alert-info">
                        <i class="fa fa-info-circle"></i>
                        Showing students for <strong><?php echo htmlspecialchars($selected_branch); ?></strong> branch.
                    </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Section Navigation -->
            <?php if (!empty($faculty_sections)): ?>
            <div class="section-nav">
                <h5><i class="fa fa-users"></i> Select Section to View Students:</h5>
                <div class="d-flex flex-wrap">
                    <a href="studentlist.php" class="btn btn-section <?php echo empty($selected_section) && empty($selected_branch) ? 'active' : ''; ?>">
                        <i class="fa fa-list"></i> All Sections
                    </a>
                    <?php foreach ($faculty_sections as $section_key => $section_data): ?>
                        <a href="studentlist.php?section=<?php echo urlencode($section_key); ?>" 
                           class="btn btn-section <?php echo $selected_section == $section_key ? 'active' : ''; ?>">
                            <i class="fa fa-users"></i> <?php echo htmlspecialchars($section_data['branch']); ?>
                            <small>Section: <?php echo htmlspecialchars($section_data['section']); ?></small>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Students by Section -->
            <div class="row">
                <div class="col-12">
                    <?php if (empty($students_by_section)): ?>
                        <div class="section-card">
                            <div class="empty-state">
                                <i class="fa fa-users"></i>
                                <h5>No Students Assigned</h5>
                                <p>No students are currently assigned to your supervision.</p>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($students_by_section as $section => $students): ?>
                            <div class="section-card">
                                <div class="section-header" onclick="toggleStudents('students-<?php echo md5($section); ?>')">
                                    <div class="section-title">
                                        <i class="fa fa-graduation-cap"></i> <?php echo htmlspecialchars($section); ?>
                                    </div>
                                    <div class="section-info">
                                        <?php echo count($students); ?> Students • Click to view details
                                    </div>
                                    <button class="btn btn-toggle" type="button">
                                        <i class="fa fa-eye"></i> View Students
                                    </button>
                                </div>
                                
                                <div class="students-table" id="students-<?php echo md5($section); ?>">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Sr. No.</th>
                                                <th>Roll No.</th>
                                                <th>Student Name</th>
                                                <th>Email</th>
                                                <th>Submission Status</th>
                                                <th>Download PDF</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($students as $index => $student): ?>
                                                <tr>
                                                    <td><strong><?php echo $index + 1; ?></strong></td>
                                                    <td><strong><?php echo htmlspecialchars($student['college_id']); ?></strong></td>
                                                    <td><?php echo htmlspecialchars($student['name']); ?></td>
                                                    <td><?php echo htmlspecialchars($student['email']); ?></td>
                                                    <td>
                                                        <?php if ($student['submission_status'] == 'Accepted'): ?>
                                                            <span class="status-accepted">Accepted</span>
                                                        <?php elseif ($student['submission_status'] == 'Pending'): ?>
                                                            <span class="status-pending">Pending</span>
                                                        <?php elseif ($student['submission_status'] == 'Rejected'): ?>
                                                            <span class="status-rejected">Rejected</span>
                                                        <?php else: ?>
                                                            <span class="status-not-submitted">Not Submitted</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="text-center">
                                                        <?php if (!empty($student['file_path']) && $student['submission_status'] != 'Not Submitted'): ?>
                                                            <a href="../<?php echo htmlspecialchars($student['file_path']); ?>" 
                                                               class="btn btn-download btn-sm" target="_blank" title="Download Project PDF">
                                                                <i class="fa fa-download"></i>
                                                                <small>Download</small>
                                                            </a>
                                                        <?php else: ?>
                                                            <span class="text-muted">
                                                                <i class="fa fa-times-circle"></i>
                                                                <small>No File</small>
                                                            </span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        <?php endforeach; ?>
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
                            <li class="mb-2"><a href="dashboard.php" class="text-white-50 text-decoration-none">Dashboard</a></li>
                            <li class="mb-2"><a href="studentlist.php" class="text-white-50 text-decoration-none">Student List</a></li>
                            <li class="mb-2"><a href="studentprojectlist.php" class="text-white-50 text-decoration-none">Project List</a></li>
                            <li class="mb-2"><a href="projectreview.php" class="text-white-50 text-decoration-none">Project Review</a></li>
                        </ul>
                    </div>
                </div>

                <div class="col-lg-4 col-md-12">
                    <div class="footer-block">
                        <h5 class="text-uppercase fw-bold mb-3 text-center text-lg-start">Connect With Us</h5>
                        <div class="d-flex justify-content-center justify-content-lg-start">
                            <a href="https://www.instagram.com" target="_blank" class="text-white-50 mx-2">
                                <i class="fa fa-instagram fa-lg"></i>
                            </a>
                            <a href="https://www.youtube.com" target="_blank" class="text-white-50 mx-2">
                                <i class="fa fa-youtube-play fa-lg"></i>
                            </a>
                            <a href="https://www.linkedin.com" target="_blank" class="text-white-50 mx-2">
                                <i class="fa fa-linkedin-square fa-lg"></i>
                            </a>
                            <a href="https://www.twitter.com" target="_blank" class="text-white-50 mx-2">
                                <i class="fa fa-twitter-square fa-lg"></i>
                            </a>
                            <a href="https://www.facebook.com" target="_blank" class="text-white-50 mx-2">
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
        function toggleStudents(tableId) {
            const table = document.getElementById(tableId);
            const button = event.target.closest('.btn-toggle');
            const icon = button.querySelector('i');
            
            if (table.classList.contains('show')) {
                table.classList.remove('show');
                button.innerHTML = '<i class="fa fa-eye"></i> View Students';
            } else {
                table.classList.add('show');
                button.innerHTML = '<i class="fa fa-eye-slash"></i> Hide Students';
            }
        }
        
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
