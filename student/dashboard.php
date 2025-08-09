<?php
session_start();

require_once '../config/database.php';

if (!isset($_SESSION['student_id'])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['mark_notifications_read'])) {
    try {
        $stmt = mysqli_prepare($connection, "UPDATE student_notifications SET is_read = 1 WHERE student_id = ? AND is_read = 0");
        mysqli_stmt_bind_param($stmt, "i", $_SESSION['student_id']);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        echo "success";
        exit();
    } catch (Exception $e) {
        echo "error";
        exit();
    }
}

try {
    $stmt = mysqli_prepare($connection, "SELECT name, email FROM itr_registeruser WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $_SESSION['student_id']);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $student = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    if (!$student) {
        session_destroy();
        header("Location: login.php");
        exit();
    }
    
    $project_data = null;
    $stmt = mysqli_prepare($connection, "
        SELECT ps.*, f.name as faculty_name
        FROM project_submissions ps
        LEFT JOIN itr_facultyuser f ON ps.faculty_id = f.id
        WHERE ps.student_id = ?
        ORDER BY ps.submitted_at DESC
        LIMIT 1
    ");
    mysqli_stmt_bind_param($stmt, "i", $_SESSION['student_id']);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $project_data = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    $stmt = mysqli_prepare($connection, "
        SELECT sn.message, sn.created_at, f.name as faculty_name, sn.is_read
        FROM student_notifications sn
        JOIN itr_facultyuser f ON sn.faculty_id = f.id
        WHERE sn.student_id = ?
        ORDER BY sn.created_at DESC
        LIMIT 5
    ");
    mysqli_stmt_bind_param($stmt, "i", $_SESSION['student_id']);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $notifications = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $notifications[] = $row;
    }
    mysqli_stmt_close($stmt);
    
    $stmt = mysqli_prepare($connection, "SELECT COUNT(*) as unread_count FROM student_notifications WHERE student_id = ? AND is_read = 0");
    mysqli_stmt_bind_param($stmt, "i", $_SESSION['student_id']);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $unread_data = mysqli_fetch_assoc($result);
    $unread_count = $unread_data['unread_count'];
    mysqli_stmt_close($stmt);
    
} catch (Exception $e) {
    error_log($e->getMessage());
    $student = ['name' => 'Student', 'email' => ''];
    $notifications = [];
    $unread_count = 0;
    $project_data = null;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - ITR Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Roboto', sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f8f9fa;
            margin: 0;
            padding: 0;
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
            margin: 0;
            padding: 0;
        }
        
        .navbar {
            margin: 0;
            border-radius: 0;
        }

        .card-container {
            padding-left: 1rem;
            padding-right: 1rem;
            max-width: 1200px;
            margin-inline: auto;
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
            color: inherit; 
        }

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

        .welcome-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .user-info {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            padding: 15px;
            margin-top: 15px;
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

        .logout-btn {
            background: linear-gradient(45deg, #dc3545, #c82333);
            border: none;
            transition: all 0.3s ease;
        }

        .logout-btn:hover {
            background: linear-gradient(45deg, #c82333, #a71e2a);
            transform: translateY(-2px);
        }

        .infocards .progress {
            height: 8px;
            border-radius: 10px;
        }
        
        .infocards .badge {
            font-size: 0.85rem;
            padding: 0.5rem 1rem;
            border-radius: 20px;
        }
        
        .status-accepted { background-color: #28a745; }
        .status-pending { background-color: #ffc107; }
        .status-rejected { background-color: #dc3545; }
        .status-not-submitted { background-color: #6c757d; }

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
        <nav class="navbar navbar-expand-lg navbar-light navbar-gradient py-3">
            <div class="container-fluid px-4">
                <a class="navbar-brand fs-4 d-flex align-items-center" href="dashboard.php">
                    <img src="../img/Logo.png" alt="Portal Logo" class="logo">
                    <span class="text-white ms-2 fw-bold">STUDENT DASHBOARD</span>
                </a>

                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav ms-auto">
                        <li class="nav-item">
                            <a class="nav-link text-white px-3 active" href="dashboard.php">
                                <i class="fa fa-tachometer" aria-hidden="true"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white px-3" href="profile.php">
                                <i class="fa fa-user" aria-hidden="true"></i> Profile
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white px-3" href="projectsubmission.php">
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
                                <i class="fa fa-user-circle" aria-hidden="true"></i> <?php echo htmlspecialchars($student['name']); ?>
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

    <main>
        <section class="hero-intro bg-white py-5">
            <div class="container welcome px-4">
                <div class="card shadow-lg border-0 rounded-4 p-4 welcome-card">
                    <div class="card-body text-center">
                        <h2 class="card-title fw-bold mb-3">
                            <i class="fa fa-home"></i> Welcome back, <?php echo htmlspecialchars($student['name']); ?>!
                        </h2>
                        <p class="card-text fs-5 mb-3">This section gives an overview of your current project status and submission updates.</p>
                        <div class="user-info">
                            <div class="row">
                                <div class="col-md-6">
                                    <p class="mb-1"><i class="fa fa-envelope"></i> <strong>Email:</strong> <?php echo htmlspecialchars($student['email']); ?></p>
                                </div>
                                <div class="col-md-6">
                                    <p class="mb-1"><i class="fa fa-calendar"></i> <strong>Last Login:</strong> <?php echo date('M d, Y H:i'); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="info-cards-section py-5 bg-light">
            <div class="container">
                <div class="card-container row g-4 justify-content-center">

                    <div class="col-12 col-md-4">
                        <div class="card shadow-lg border-0 rounded-4 infocards">
                            <div class="card-body">
                                <h5 class="card-title">
                                    <i class="fa fa-calendar-times-o" aria-hidden="true"></i> Presentation Date
                                </h5>
                                <?php if ($project_data && $project_data['presentation_date']): ?>
                                    <p class="card-text mt-3">Your presentation is scheduled for <strong><?php echo date('F j, Y', strtotime($project_data['presentation_date'])); ?></strong>.</p>
                                    <div class="mt-auto">
                                        <?php 
                                        $presentation_date = strtotime($project_data['presentation_date']);
                                        $current_date = time();
                                        $days_diff = ceil(($presentation_date - $current_date) / (60*60*24));
                                        
                                        if ($days_diff > 0): ?>
                                            <span class="badge bg-warning text-dark"><?php echo $days_diff; ?> days remaining</span>
                                        <?php elseif ($days_diff == 0): ?>
                                            <span class="badge bg-danger">Today!</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Completed</span>
                                        <?php endif; ?>
                                    </div>
                                <?php elseif ($project_data): ?>
                                    <?php if ($project_data['submission_status'] == 'accepted'): ?>
                                        <p class="card-text mt-3">Your project has been accepted. Presentation date will be assigned soon.</p>
                                        <div class="mt-auto">
                                            <span class="badge bg-success">Awaiting schedule</span>
                                        </div>
                                    <?php elseif ($project_data['submission_status'] == 'pending'): ?>
                                        <p class="card-text mt-3">Project under review. Presentation date will be assigned after approval.</p>
                                        <div class="mt-auto">
                                            <span class="badge bg-warning text-dark">Under review</span>
                                        </div>
                                    <?php else: ?>
                                        <p class="card-text mt-3">Please revise and resubmit your project for presentation scheduling.</p>
                                        <div class="mt-auto">
                                            <span class="badge bg-danger">Needs revision</span>
                                        </div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <p class="card-text mt-3">Submit your project to get a presentation date assigned.</p>
                                    <div class="mt-auto">
                                        <span class="badge bg-secondary">Not submitted</span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-md-4">
                        <div class="card shadow-lg border-0 rounded-4 infocards">
                            <div class="card-body">
                                <h5 class="card-title">
                                    <i class="fa fa-chart-line" aria-hidden="true"></i> Project Status
                                </h5>
                                <div class="mt-3">
                                    <?php if ($project_data): ?>
                                        <?php
                                        $status = $project_data['submission_status'];
                                        $status_text = '';
                                        $status_class = '';
                                        $progress_width = 0;
                                        
                                        switch($status) {
                                            case 'pending':
                                                $status_text = 'Submitted - Under Review';
                                                $status_class = 'bg-warning';
                                                $progress_width = 50;
                                                break;
                                            case 'accepted':
                                                $status_text = 'Accepted';
                                                $status_class = 'bg-success';
                                                $progress_width = 100;
                                                break;
                                            case 'rejected':
                                                $status_text = 'Rejected - Needs Revision';
                                                $status_class = 'bg-danger';
                                                $progress_width = 25;
                                                break;
                                            default:
                                                $status_text = 'Not Submitted';
                                                $status_class = 'bg-secondary';
                                                $progress_width = 0;
                                        }
                                        ?>
                                        <div class="progress mb-2">
                                            <div class="progress-bar <?php echo $status_class; ?>" role="progressbar" style="width: <?php echo $progress_width; ?>%" aria-valuenow="<?php echo $progress_width; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                        </div>
                                        <p class="card-text"><?php echo $status_text; ?></p>
                                        <?php if ($project_data['faculty_remarks']): ?>
                                            <small class="text-muted">
                                                <strong>Faculty Remarks:</strong> <?php echo htmlspecialchars($project_data['faculty_remarks']); ?>
                                            </small>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <div class="progress mb-2">
                                            <div class="progress-bar bg-secondary" role="progressbar" style="width: 0%" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                                        </div>
                                        <p class="card-text">Not Submitted</p>
                                        <small class="text-muted">Please submit your project to see status updates.</small>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-md-4">
                        <div class="card shadow-lg border-0 rounded-4 infocards">
                            <div class="card-body">
                                <h5 class="card-title">
                                    <i class="fa fa-bell-o" aria-hidden="true"></i> Notifications
                                    <?php if ($unread_count > 0): ?>
                                        <span class="badge bg-danger"><?php echo $unread_count; ?></span>
                                    <?php endif; ?>
                                </h5>
                                <div class="mt-3">
                                    <?php if (empty($notifications)): ?>
                                        <div class="alert alert-info py-2 mb-2">
                                            <small><i class="fa fa-info-circle"></i> Welcome to your dashboard!</small>
                                        </div>
                                        <p class="card-text"><small class="text-muted">No new notifications.</small></p>
                                    <?php else: ?>
                                        <?php foreach ($notifications as $notification): ?>
                                            <div class="alert <?php echo $notification['is_read'] ? 'alert-secondary' : 'alert-warning'; ?> py-2 mb-2">
                                                <small>
                                                    <strong><i class="fa fa-user"></i> <?php echo htmlspecialchars($notification['faculty_name']); ?>:</strong><br>
                                                    <?php echo htmlspecialchars($notification['message']); ?>
                                                    <br><em class="text-muted"><?php echo date('M j, Y g:i A', strtotime($notification['created_at'])); ?></em>
                                                </small>
                                            </div>
                                        <?php endforeach; ?>
                                        <p class="card-text">
                                            <small class="text-muted">
                                                <?php if ($unread_count > 0): ?>
                                                    You have <?php echo $unread_count; ?> unread message(s).
                                                <?php else: ?>
                                                    All messages have been read.
                                                <?php endif; ?>
                                            </small>
                                        </p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </section>

        <section class="cta-section text-center py-5">
            <div class="container">
                <h1 class="display-4 fw-bold text-dark mb-3">Student Project Submission Portal</h1>
                <p class="lead text-muted fs-5 mb-5">INDUSTRIAL TRAINING REPORT</p>
                <div class="row mt-4 justify-content-center g-4">
                    <div class="col-md-4">
                        <a href="profile.php" class="btn btn-primary btn-lg w-100 py-3 rounded-pill shadow-sm">
                            <i class="fa fa-user" aria-hidden="true"></i> My Profile
                        </a>
                    </div>
                    <div class="col-md-4">
                        <a href="projectstatus.php" class="btn btn-warning btn-lg w-100 py-3 rounded-pill shadow-sm">
                            <i class="fa fa-check-square-o" aria-hidden="true"></i> Project Status
                        </a>
                    </div>
                    <div class="col-md-4">
                        <a href="projectsubmission.php" class="btn btn-success btn-lg w-100 py-3 rounded-pill shadow-sm">
                            <i class="fa fa-cloud-upload" aria-hidden="true"></i> Project Submission
                        </a>
                    </div>
                </div>
            </div>
        </section>
    </main>

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
                            <li class="mb-2"><a href="dashboard.php" class="text-white-50 text-decoration-none">Dashboard</a></li>
                            <li class="mb-2"><a href="projectsubmission.php" class="text-white-50 text-decoration-none">Project Submission</a></li>
                            <li class="mb-2"><a href="projectstatus.php" class="text-white-50 text-decoration-none">Project Status</a></li>
                            <li class="mb-2"><a href="profile.php" class="text-white-50 text-decoration-none">My Profile</a></li>
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
        setTimeout(function() {
            fetch('dashboard.php?mark_read=1', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'mark_notifications_read=1'
            }).catch(error => {
                console.log('Error marking notifications as read:', error);
            });
        }, 3000);
    </script>
</body>
</html>