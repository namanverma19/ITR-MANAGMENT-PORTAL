<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['student_id'])) {
    header('Location: login.php');
    exit();
}

$student_name = $_SESSION['student_name'];
$student_email = $_SESSION['student_email'];
$student_id = $_SESSION['student_id'];

$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $project_name = trim($_POST['projectName']);
        $project_description = trim($_POST['projectDescription']);
        $leader_name = trim($_POST['leaderName']);
        $leader_roll = trim($_POST['leaderRoll']);
        
        $file_path = '';
        if (isset($_FILES['projectFile']) && $_FILES['projectFile']['error'] == UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/projects/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_extension = pathinfo($_FILES['projectFile']['name'], PATHINFO_EXTENSION);
            $file_name = $student_id . '_' . time() . '.' . $file_extension;
            $file_path = $upload_dir . $file_name;
            
            // Validate file type and size
            if (strtolower($file_extension) !== 'pdf') {
                throw new Exception('Only PDF files are allowed.');
            }
            
            if ($_FILES['projectFile']['size'] > 10 * 1024 * 1024) { // 10MB limit
                throw new Exception('File size must be less than 10MB.');
            }
            
            if (!move_uploaded_file($_FILES['projectFile']['tmp_name'], $file_path)) {
                throw new Exception('Failed to upload file.');
            }
        }
        
        // Optional members
        $member2_name = trim($_POST['name2'] ?? '');
        $member2_roll = trim($_POST['roll2'] ?? '');
        
        $member3_name = trim($_POST['name3'] ?? '');
        $member3_roll = trim($_POST['roll3'] ?? '');
        
        $member4_name = trim($_POST['name4'] ?? '');
        $member4_roll = trim($_POST['roll4'] ?? '');
        
        // Get student's assigned faculty (profile assignment or section assignment) and branch name
        $stmt = mysqli_prepare($connection, 
            "SELECT 
                COALESCE(r.assigned_faculty_id, s.faculty_id) AS faculty_id, 
                b.branch_name 
             FROM itr_registeruser r 
             LEFT JOIN branches b ON r.branch = b.id 
             LEFT JOIN sections s ON s.branch_id = b.id AND s.section_name = r.section 
             WHERE r.id = ?"
        );
        mysqli_stmt_bind_param($stmt, "i", $student_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $faculty_assignment = mysqli_fetch_assoc($result);
        $assigned_faculty_id = $faculty_assignment['faculty_id'] ?? null;
        $student_branch = $faculty_assignment['branch_name'] ?? 'Computer Science Engineering';
        mysqli_stmt_close($stmt);
        
        // Insert project submission
        $stmt = mysqli_prepare($connection, "
            INSERT INTO project_submissions (
                student_id, project_name, project_description, file_path, 
                leader_name, leader_roll, leader_branch,
                member2_name, member2_roll,
                member3_name, member3_roll,
                member4_name, member4_roll,
                faculty_id, submission_status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
        ");
        
        // Handle null values for MySQLi
        $file_path_param = $file_path ?: null;
        $member2_name_param = $member2_name ?: null;
        $member2_roll_param = $member2_roll ?: null;
        $member3_name_param = $member3_name ?: null;
        $member3_roll_param = $member3_roll ?: null;
        $member4_name_param = $member4_name ?: null;
        $member4_roll_param = $member4_roll ?: null;
        
        mysqli_stmt_bind_param($stmt, "issssssssssssi", 
            $student_id, $project_name, $project_description, $file_path_param,
            $leader_name, $leader_roll, $student_branch,
            $member2_name_param, $member2_roll_param,
            $member3_name_param, $member3_roll_param,
            $member4_name_param, $member4_roll_param,
            $assigned_faculty_id
        );
        
        $result = mysqli_stmt_execute($stmt);
        
        if ($result) {
            $success_message = "Project submission successful! Your project has been submitted and is pending review.";
        } else {
            $error_message = "Failed to submit project. Please try again.";
        }
        mysqli_stmt_close($stmt);
    } catch (Exception $e) {
        $error_message = "Database error: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Project Submission - ITR Portal</title>
    <!-- Latest compiled and minified CSS -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<!--Font Awesome icons-->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
<!-- Latest compiled JavaScript -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<link href="https://fonts.googleapis.com/css2?family=Poppins&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
<style>
  body {
    font-family: 'Poppins', sans-serif;
    background-color: #f8f9fa;
  }
  header {
      background: linear-gradient(to right, #007bff, #00c6ff);
      color: white;
    }
    .card {
      border: none;
      box-shadow: 0 0 15px rgba(0, 0, 0, 0.05);
    }
    .btn-info:hover {
      box-shadow: 0 0 10px rgba(0, 123, 255, 0.6);
      transform: scale(1.02);
    }
    .form-section-title {
      font-weight: 600;
      color: #007bff;
    }
body {
            font-family: 'Roboto', sans-serif;
            line-height: 1.6; 
            color: #333; 
            background-color: #f8f9fa; 
        }

        .logo {
            height: 48px; 
            margin-right: 12px; 
            border-radius: 60px; 
        }

        .navbar-gradient {
            background: linear-gradient(to right, #003366, #004080, #004d99);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
            position: sticky;
            top: 0;
            z-index: 1020;
            transition: all 0.3s ease;
        }

       

        .infocards {
            display: flex;
            flex-direction: column;
            justify-content: flex-start; 
            align-items: stretch;
            min-height: 250px;
            background-color: #ffffff;
            border: 1px solid #e0e0e0;
            border-radius: 0.75rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            overflow: hidden;
            margin-bottom: 1rem; 
        }

        .infocards:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
        }

        .infocards .card-body {
            flex-grow: 1; 
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
            align-items: center; 
            padding: 1.5rem;
            text-align: center; 
        }

      
        .infocards .card-body.quick {
            align-items: flex-start; 
            text-align: left;
        }

        .infocards .card-body.quick .card-text,
        .infocards .card-body.quick ul,
        .infocards .card-body.quick h6 {
            text-align: left; 
            width: 100%; 
        }


        .infocards .card-title {
            font-size: 1.4rem;
            font-weight: 700;
            margin-bottom: 1rem; 
            color: #007bff;  
        }
        .infocards .card-title .fa {
            margin-right: 0.5rem; 
            color: inherit; }


        .infocards .card-text,
        .infocards ul {
            font-size: 0.95rem;
            line-height: 1.6;
            color: #555; 
            width: 100%; 
        }

        .infocards ul {
            list-style: none;
            padding-left: 0;
            margin-bottom: 0;
        }

        .infocards ul li {
            margin-bottom: 0.4rem;
        }

        .infocards h6 {
            font-size: 1rem;
            line-height: 1.5;
            margin-bottom: 0.5rem;
            color: #555;
            width: 100%; 
        }

      
        .infocards .card-body.quick .d-flex .btn {
            flex-grow: 1; 
            font-size: 1.05rem; 
            padding: 0.8rem 1rem; 
        }


       
        footer {
            font-family: 'Roboto', sans-serif;
            background-color: #212529 !important;
            color: #f8f9fa;
            padding-top: 3rem;
            padding-bottom: 2rem;
        }

        .footer-block {
            padding-left: 8px;
            padding-right: 8px;
            width: 100%;
            display: flex;
            flex-direction: column;
            align-items: flex-start;
        }

        .footer-block-heading {
            font-size: 16px;
            font-family: 'Graphik', sans-serif, system-ui;
            font-weight: 600;
            line-height: 1;
            letter-spacing: -.02em;
        }

       
        .social-icon {
            font-size: 1.7rem;
            color: rgba(255, 255, 255, 0.6);
            transition: color 0.3s ease, filter 0.3s ease;
            filter: grayscale(100%) brightness(150%);
        }

        .social-icon:hover {
            color: #ffffff !important;
            filter: grayscale(0%) brightness(100%);
        }

        
        .bg-secondary {
            background-color: #1a1a1a !important;
            border-top: 1px solid rgba(255, 255, 255, 0.1) !important;
            font-size: 0.8rem;
            color: rgba(255, 255, 255, 0.5) !important;
        }

        .bg-secondary a {
            color: rgba(255, 255, 255, 0.5) !important;
        }

        .bg-secondary a:hover {
            color: #ffffff !important;
        }

        
        @media (max-width: 991.98px) {
            .navbar-brand .text-white {
                font-size: 1.4rem;
            }
            .navbar-nav {
                background-color: #0056b3; 
                border-radius: 0 0 8px 8px;
                margin-top: 10px;
                padding-bottom: 10px;
            }
            .nav-item {
                text-align: center;
            }
            .nav-link {
                padding-top: 10px !important;
                padding-bottom: 10px !important;
            }
            .hero-cta h1 {
                font-size: 2.5rem;
            }
            .hero-cta .lead {
                font-size: 1.1rem;
            }
            
            .footer-block .text-lg-start,
            .footer-block .align-items-lg-start,
            .footer-block .justify-content-lg-start {
                text-align: center !important;
                align-items: center !important;
                justify-content: center !important;
            }
        }

        @media (max-width: 767.98px) {
            .logo {
                height: 40px;
            }
            .navbar-brand .text-white {
                font-size: 1.2rem;
            }
            .hero-cta h1 {
                font-size: 2rem;
            }
            .hero-cta .lead {
                font-size: 1rem;
            }
            
            .card-container .col-12 {
                margin-bottom: 1.5rem;
            }
            .card-container .col-12:last-child {
                margin-bottom: 0;
            }
        }

</style>

</head>
<body>
      <header>
        <nav class="navbar navbar-expand-lg  navbar-gradient py-3">
            <div class="container-fluid px-4">
                <a class="navbar-brand fs-4 d-flex align-items-center" href="#">
                    <img src="../img/Logo.png" alt="Portal Logo" class="logo">
                    <span class="text-white ms-2 fw-bold">STUDENT DASHBOARD</span>
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
                            <a class="nav-link text-white px-3" href="profile.php">
                                <i class="fa fa-user" aria-hidden="true"></i> Profile
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white px-3 active" href="projectsubmission.php">
                                <i class="fa fa-upload" aria-hidden="true"></i> Project Submit
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white px-3" href="projectstatus.php">
                                <i class="fa fa-tasks" aria-hidden="true"></i> Project Status
                            </a>
                        </li>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle text-white px-3" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fa fa-user-circle" aria-hidden="true"></i> <?php echo htmlspecialchars($student_name); ?>
                            </a>
                            <ul class="dropdown-menu" aria-labelledby="navbarDropdown">
                                <li><a class="dropdown-item" href="profile.php"><i class="fa fa-user" aria-hidden="true"></i> My Profile</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="logout.php"><i class="fa fa-sign-out" aria-hidden="true"></i> Logout</a></li>
                            </ul>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>
    </header>





    <div class=" p-5 bg-light text-light text-center" style="background: linear-gradient(to right, #0f2027, #203a43, #2c5364);">
          <h1 class="display-5">Welcome to Project Allotment</h1>
    <p class="lead">Enter project and student info to proceed</p>
    </div>
    
    <?php if ($success_message): ?>
        <div class="container mt-3">
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fa fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        </div>
    <?php endif; ?>
    
    <?php if ($error_message): ?>
        <div class="container mt-3">
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fa fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        </div>
    <?php endif; ?>
    
    <div class="container-fluid mb-5 py-4 px-3 bg-light">
        <form action="projectsubmission.php" method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
            <!-- Project Details -->
            <div class="card mb-4 shadow-sm">
                <div class="card-body p-4">
                    <h4 class="form-section-title mb-4 text-primary">
                        <i class="fa fa-project-diagram"></i> Project Information
                    </h4>
                    
                    <div class="row g-4">
                        <div class="col-md-8">
                            <label for="projectName" class="form-label">
                                <i class="fa fa-code"></i> Project Name <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control form-control-lg" id="projectName" 
                                   name="projectName" placeholder="Enter your project name" required />
                            <div class="invalid-feedback">
                                Please provide a project name.
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <label for="projectFile" class="form-label">
                                <i class="fa fa-file-pdf-o"></i> Project PDF <span class="text-danger">*</span>
                            </label>
                            <input type="file" class="form-control form-control-lg" id="projectFile" 
                                   name="projectFile" accept=".pdf" required />
                            <div class="form-text">Upload your project document (PDF only, max 10MB)</div>
                            <div class="invalid-feedback">
                                Please upload a PDF file.
                            </div>
                        </div>
                        
                        <div class="col-12">
                            <label for="projectDescription" class="form-label">
                                <i class="fa fa-align-left"></i> Project Description <span class="text-danger">*</span>
                            </label>
                            <textarea class="form-control" id="projectDescription" name="projectDescription" 
                                      rows="2" placeholder="Briefly describe your project in 2 lines..." required></textarea>
                            <div class="form-text">Please provide a brief description of your project (maximum 2 lines)</div>
                            <div class="invalid-feedback">
                                Please provide a project description.
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Team Members -->
            <div class="card mb-4 shadow-sm">
                <div class="card-body p-4">
                    <h4 class="form-section-title mb-4 text-primary">
                        <i class="fa fa-users"></i> Team Members
                    </h4>
                    
                    <!-- Team Member 1 (Leader) -->
                    <div class="member-section mb-4 p-3 bg-light rounded">
                        <h5 class="text-secondary mb-3">
                            <i class="fa fa-star text-warning"></i> Team Leader
                        </h5>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Full Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="leaderName" 
                                       placeholder="Enter team leader name" required />
                                <div class="invalid-feedback">Please provide team leader name.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Roll Number <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="leaderRoll" 
                                       placeholder="Enter roll number" required />
                                <div class="invalid-feedback">Please provide roll number.</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Team Member 2 -->
                    <div class="member-section mb-4 p-3 bg-light rounded">
                        <h5 class="text-secondary mb-3">
                            <i class="fa fa-user"></i> Team Member 2
                        </h5>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Full Name</label>
                                <input type="text" class="form-control" name="name2" 
                                       placeholder="Enter team member name" />
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Roll Number</label>
                                <input type="text" class="form-control" name="roll2" 
                                       placeholder="Enter roll number" />
                            </div>
                        </div>
                    </div>
                    
                    <!-- Team Member 3 -->
                    <div class="member-section mb-4 p-3 bg-light rounded">
                        <h5 class="text-secondary mb-3">
                            <i class="fa fa-user"></i> Team Member 3
                        </h5>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Full Name</label>
                                <input type="text" class="form-control" name="name3" 
                                       placeholder="Enter team member name" />
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Roll Number</label>
                                <input type="text" class="form-control" name="roll3" 
                                       placeholder="Enter roll number" />
                            </div>
                        </div>
                    </div>
                    
                    <!-- Team Member 4 -->
                    <div class="member-section mb-3 p-3 bg-light rounded">
                        <h5 class="text-secondary mb-3">
                            <i class="fa fa-user"></i> Team Member 4
                        </h5>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Full Name</label>
                                <input type="text" class="form-control" name="name4" 
                                       placeholder="Enter team member name" />
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Roll Number</label>
                                <input type="text" class="form-control" name="roll4" 
                                       placeholder="Enter roll number" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="text-center">
                <button type="submit" class="btn btn-primary btn-lg px-5 py-3">
                    <i class="fa fa-paper-plane"></i> Submit Project
                </button>
            </div>
        </form>
    </div>  

    <footer class="text-center text-lg-start bg-dark text-white py-5">
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
    
    <!-- Bootstrap JS and Form Validation -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Bootstrap form validation
        (function() {
            'use strict';
            window.addEventListener('load', function() {
                var forms = document.getElementsByClassName('needs-validation');
                var validation = Array.prototype.filter.call(forms, function(form) {
                    form.addEventListener('submit', function(event) {
                        if (form.checkValidity() === false) {
                            event.preventDefault();
                            event.stopPropagation();
                        }
                        form.classList.add('was-validated');
                    }, false);
                });
            }, false);
        })();
        
        // File upload validation
        document.getElementById('projectFile').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                if (file.type !== 'application/pdf') {
                    alert('Please select a PDF file only.');
                    e.target.value = '';
                    return;
                }
                if (file.size > 10 * 1024 * 1024) {
                    alert('File size must be less than 10MB.');
                    e.target.value = '';
                    return;
                }
            }
        });
    </script>
</body>
</html>