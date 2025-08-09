<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['student_id'])) {
    header('Location: login.php');
    exit();
}

$student_id = $_SESSION['student_id'];

// Get student's project submissions
try {
    $stmt = mysqli_prepare($connection, "
        SELECT 
            ps.*,
            f.name as faculty_name, 
            f.email as faculty_email
        FROM project_submissions ps
        LEFT JOIN itr_facultyuser f ON ps.faculty_id = f.id
        WHERE ps.student_id = ?
        ORDER BY ps.submitted_at DESC
    ");
    mysqli_stmt_bind_param($stmt, "i", $student_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $submissions = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $submissions[] = $row;
    }
    mysqli_stmt_close($stmt);
} catch (Exception $e) {
    $submissions = [];
    $error_message = "Failed to load project status: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Project Status - ITR Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Poppins', sans-serif;
            margin: 0;
            padding: 0;
        }
        .navbar-gradient {
            background: linear-gradient(to right, #003366, #004080, #004d99);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
            margin: 0;
            padding: 0;
        }
        .navbar {
            margin: 0;
            border-radius: 0;
        }
        .logo {
            height: 48px;
            margin-right: 12px;
            border-radius: 60px;
        }
        .nav-link {
            color: white !important;
        }
        .nav-link:hover {
            color: #f0f0f0 !important;
        }
        .status-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }
        .status-card:hover {
            transform: translateY(-5px);
        }
        .status-pending {
            border-left: 5px solid #ffc107;
        }
        .status-accepted {
            border-left: 5px solid #28a745;
        }
        .status-rejected {
            border-left: 5px solid #dc3545;
        }
        .badge-pending {
            background-color: #ffc107;
            color: #000;
        }
        .badge-accepted {
            background-color: #28a745;
        }
        .badge-rejected {
            background-color: #dc3545;
        }
        .no-submissions {
            text-align: center;
            padding: 4rem 2rem;
            color: #6c757d;
        }
        .presentation-date {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            border-radius: 10px;
            padding: 15px;
            margin-top: 15px;
        }
    </style>
</head>
<body>
    <header>
        <nav class="navbar navbar-expand-lg navbar-gradient py-3">
            <div class="container-fluid px-4">
                <a class="navbar-brand fs-4 d-flex align-items-center" href="dashboard.php">
                    <img src="../img/Logo.png" alt="Portal Logo" class="logo">
                    <span class="text-white ms-2 fw-bold">PROJECT STATUS</span>
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
                            <a class="nav-link text-white px-3" href="projectsubmission.php">
                                <i class="fa fa-upload" aria-hidden="true"></i> Project Submit
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white px-3 active" href="projectstatus.php">
                                <i class="fa fa-tasks" aria-hidden="true"></i> Project Status
                            </a>
                        </li>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle text-white px-3" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fa fa-user-circle" aria-hidden="true"></i> <?php echo htmlspecialchars($_SESSION['student_name']); ?>
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

    <div class="container py-5">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="bi bi-clipboard-check"></i> Project Status Overview</h2>
                    <a href="projectsubmission.php" class="btn btn-primary">
                        <i class="bi bi-plus-circle"></i> Submit New Project
                    </a>
                </div>

                <?php if (empty($submissions)): ?>
                    <div class="card status-card">
                        <div class="card-body no-submissions">
                            <i class="bi bi-inbox" style="font-size: 4rem; color: #dee2e6;"></i>
                            <h4 class="mt-3">No Project Submissions</h4>
                            <p>You haven't submitted any projects yet. Start by submitting your first project!</p>
                            <a href="projectsubmission.php" class="btn btn-primary btn-lg">
                                <i class="bi bi-upload"></i> Submit Project
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($submissions as $submission): ?>
                        <div class="card status-card status-<?php echo $submission['submission_status']; ?> mb-4">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-8">
                                        <div class="d-flex justify-content-between align-items-start mb-3">
                                            <h5 class="card-title mb-0">
                                                <i class="bi bi-file-earmark-text"></i> 
                                                <?php echo htmlspecialchars($submission['project_name']); ?>
                                            </h5>
                                            <span class="badge badge-<?php echo $submission['submission_status']; ?> fs-6">
                                                <?php if ($submission['submission_status'] == 'pending'): ?>
                                                    <i class="bi bi-clock"></i> Pending Review
                                                <?php elseif ($submission['submission_status'] == 'accepted'): ?>
                                                    <i class="bi bi-check-circle"></i> Accepted
                                                <?php else: ?>
                                                    <i class="bi bi-x-circle"></i> Rejected
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                        
                                        <p class="card-text text-muted mb-3">
                                            <?php echo htmlspecialchars($submission['project_description']); ?>
                                        </p>
                                        
                                        <div class="row g-3 mb-3">
                                            <div class="col-sm-6">
                                                <small class="text-muted">Team Leader:</small><br>
                                                <strong><?php echo htmlspecialchars($submission['leader_name']); ?></strong>
                                                <br><small><?php echo htmlspecialchars($submission['leader_roll']); ?></small>
                                            </div>
                                            <div class="col-sm-6">
                                                <small class="text-muted">Branch:</small><br>
                                                <strong><?php echo htmlspecialchars($submission['leader_branch']); ?></strong>
                                            </div>
                                        </div>
                                        
                                        <!-- Faculty Assignment Info -->
                                        <div class="row mb-3">
                                            <div class="col-12">
                                                <div class="p-3 rounded" style="background-color: #f8f9fa; border-left: 4px solid <?php echo $submission['faculty_name'] ? '#28a745' : '#ffc107'; ?>;">
                                                    <div class="d-flex align-items-center">
                                                        <div class="me-3">
                                                            <i class="fa fa-user-tie fa-2x <?php echo $submission['faculty_name'] ? 'text-success' : 'text-warning'; ?>"></i>
                                                        </div>
                                                        <div class="flex-grow-1">
                                                            <h6 class="mb-1">Faculty Supervisor</h6>
                                                            <?php if ($submission['faculty_name']): ?>
                                                                <strong class="text-success"><?php echo htmlspecialchars($submission['faculty_name']); ?></strong>
                                                                <br><small class="text-muted"><?php echo htmlspecialchars($submission['faculty_email'] ?? ''); ?></small>
                                                                <div class="mt-2">
                                                                    <span class="badge bg-success">
                                                                        <i class="fa fa-check"></i> Assigned
                                                                    </span>
                                                                </div>
                                                            <?php else: ?>
                                                                <strong class="text-warning">Not Assigned Yet</strong>
                                                                <br><small class="text-muted">Faculty will be assigned based on your branch and section</small>
                                                                <div class="mt-2">
                                                                    <span class="badge bg-warning text-dark">
                                                                        <i class="fa fa-clock-o"></i> Pending Assignment
                                                                    </span>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                                                <?php if ($submission['faculty_remarks']): ?>
                                            <div class="alert alert-info border-start border-info border-4">
                                                <div class="d-flex align-items-start">
                                                    <div class="me-2">
                                                        <i class="fa fa-comment-o fa-lg text-info"></i>
                                                    </div>
                                                    <div>
                                                        <h6 class="alert-heading mb-2">
                                                            <i class="fa fa-user-tie"></i> Faculty Remarks
                                                        </h6>
                                                        <p class="mb-0"><?php echo nl2br(htmlspecialchars($submission['faculty_remarks'])); ?></p>
                                                        <?php if ($submission['reviewed_at']): ?>
                                                            <small class="text-muted d-block mt-2">
                                                                <i class="fa fa-calendar"></i> 
                                                                Reviewed on <?php echo date('F j, Y \a\t g:i A', strtotime($submission['reviewed_at'])); ?>
                                                            </small>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if ($submission['presentation_date'] && $submission['submission_status'] == 'accepted'): ?>
                                            <div class="presentation-date">
                                                <div class="d-flex align-items-center">
                                                    <div class="me-3">
                                                        <i class="fa fa-calendar fa-2x text-white"></i>
                                                    </div>
                                                    <div class="flex-grow-1">
                                                        <h6 class="mb-1">
                                                            <i class="fa fa-check-circle"></i> Presentation Scheduled
                                                        </h6>
                                                        <p class="mb-1">
                                                            <strong><?php echo date('l, F j, Y', strtotime($submission['presentation_date'])); ?></strong>
                                                        </p>
                                                        <small>
                                                            <?php 
                                                            $presentation_timestamp = strtotime($submission['presentation_date']);
                                                            $today = time();
                                                            $days_until = ceil(($presentation_timestamp - $today) / (60 * 60 * 24));
                                                            
                                                            if ($days_until > 0) {
                                                                echo "📅 " . $days_until . " day" . ($days_until > 1 ? "s" : "") . " to go! Make sure to prepare your presentation.";
                                                            } elseif ($days_until == 0) {
                                                                echo "📅 Presentation is TODAY! Good luck!";
                                                            } else {
                                                                echo "📅 Presentation was " . abs($days_until) . " day" . (abs($days_until) > 1 ? "s" : "") . " ago.";
                                                            }
                                                            ?>
                                                        </small>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="col-md-4">
                                        <div class="text-end">
                                            <small class="text-muted">Submitted:</small><br>
                                            <strong><?php echo date('M j, Y g:i A', strtotime($submission['submitted_at'])); ?></strong>
                                            
                                            <?php if ($submission['reviewed_at']): ?>
                                                <br><br>
                                                <small class="text-muted">Reviewed:</small><br>
                                                <strong><?php echo date('M j, Y g:i A', strtotime($submission['reviewed_at'])); ?></strong>
                                            <?php endif; ?>
                                            
                                            <?php if ($submission['file_name']): ?>
                                                <br><br>
                                                <div class="file-info">
                                                    <small class="text-muted">Attached File:</small><br>
                                                    <i class="bi bi-file-earmark"></i> 
                                                    <?php echo htmlspecialchars($submission['file_name']); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>