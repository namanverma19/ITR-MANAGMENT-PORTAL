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

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $project_id = $_POST['project_id'];
    $action = $_POST['action'];
    $remarks = $_POST['remarks'];
    $presentation_date = $_POST['presentation_date'];
    
    try {
        $status = ($action == 'accept') ? 'accepted' : 'rejected';
        
        $stmt = mysqli_prepare($connection, "
            UPDATE project_submissions 
            SET submission_status = ?, faculty_remarks = ?, presentation_date = ?, reviewed_at = NOW(), faculty_id = ?
            WHERE id = ?
        ");
        mysqli_stmt_bind_param($stmt, "sssii", $status, $remarks, $presentation_date, $faculty_id, $project_id);
        
        if (mysqli_stmt_execute($stmt)) {
            $success_message = "Project " . ($action == 'accept' ? 'accepted' : 'rejected') . " successfully!";
        } else {
            $error_message = "Failed to update project status.";
        }
        mysqli_stmt_close($stmt);
    } catch (Exception $e) {
        $error_message = "Database error: " . $e->getMessage();
    }
}

try {
    $stmt = mysqli_prepare($connection, "
        SELECT 
            ps.*, 
            r.name as student_name, 
            r.college_id as student_roll,
            r.branch,
            r.section,
            b.branch_name
        FROM project_submissions ps
        JOIN itr_registeruser r ON ps.student_id = r.id
        LEFT JOIN branches b ON r.branch = b.id
        WHERE ( (ps.faculty_id = ?) OR (ps.faculty_id IS NULL AND r.assigned_faculty_id = ?) )
          AND (ps.submission_status IS NULL OR ps.submission_status = 'pending')
        ORDER BY ps.submitted_at DESC
    ");
    
    mysqli_stmt_bind_param($stmt, "ii", $faculty_id, $faculty_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $recent_projects = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $row['team_members'] = [];
        
        if (!empty($row['leader_name'])) {
            $row['team_members'][] = [
                'name' => $row['leader_name'],
                'roll' => $row['leader_roll'],
                'branch' => $row['leader_branch'],
                'role' => 'Leader'
            ];
        }
        
        if (!empty($row['member2_name'])) {
            $row['team_members'][] = [
                'name' => $row['member2_name'],
                'roll' => $row['member2_roll'],
                'branch' => $row['member2_branch'],
                'role' => 'Member'
            ];
        }
        
        if (!empty($row['member3_name'])) {
            $row['team_members'][] = [
                'name' => $row['member3_name'],
                'roll' => $row['member3_roll'],
                'branch' => $row['member3_branch'],
                'role' => 'Member'
            ];
        }
        
        if (!empty($row['member4_name'])) {
            $row['team_members'][] = [
                'name' => $row['member4_name'],
                'roll' => $row['member4_roll'],
                'branch' => $row['member4_branch'],
                'role' => 'Member'
            ];
        }
        
        $recent_projects[] = $row;
    }
    mysqli_stmt_close($stmt);
    
} catch (Exception $e) {
    $recent_projects = [];
    $error_message = "Failed to load project submissions: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Project Review - ITR Portal</title>
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
        
        .project-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
            overflow: hidden;
            transition: all 0.3s ease;
        }
        
        .project-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }
        
        .project-header {
            background: linear-gradient(135deg, #1B5E20, #388E3C);
            color: white;
            padding: 20px;
        }
        
        .project-title {
            font-size: 1.5rem;
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        .leader-info {
            font-size: 1.1rem;
            opacity: 0.9;
        }
        
        .team-table {
            margin: 20px 0;
        }
        
        .team-table th {
            background: linear-gradient(135deg, #1B5E20, #388E3C);
            color: white;
            border: none;
        }
        
        .evaluation-form {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-top: 20px;
        }
        
        .btn-accept {
            background: linear-gradient(135deg, #28a745, #20c997);
            border: none;
            color: white;
        }
        
        .btn-reject {
            background: linear-gradient(135deg, #dc3545, #c82333);
            border: none;
            color: white;
        }
        
        .btn-download {
            background: linear-gradient(135deg, #17a2b8, #138496);
            border: none;
            color: white;
        }
        
        .status-badge {
            position: absolute;
            top: 20px;
            right: 20px;
            padding: 10px 20px;
            border-radius: 25px;
            font-weight: bold;
        }
        
        .status-pending {
            background: #ffc107;
            color: #212529;
        }
        
        .bg-secondary {
            background-color: #1B5E20 !important;
        }
    </style>
</head>
<body>
    <header>
        <nav class="navbar navbar-expand-lg navbar-gradient py-3">
            <div class="container-fluid px-4">
                <a class="navbar-brand fs-4 d-flex align-items-center" href="dashboard.php">

                <img src="/itr_managment_portal/img/Logo.png" alt="Portal Logo" class="logo">
                    <span class="text-white ms-2 fw-bold">PROJECT REVIEW</span>
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
                            <a class="nav-link text-white px-3" href="studentlist.php">
                                <i class="fa fa-users" aria-hidden="true"></i> Student List
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white px-3" href="studentprojectlist.php">
                                <i class="fa fa-file-text" aria-hidden="true"></i> Project List
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white px-3 active" href="projectreview.php">
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

            <!-- Page Title -->
            <div class="row">
                <div class="col-12">
                    <h2 class="mb-4 text-center" style="color: #1B5E20;">
                        <i class="fa fa-clipboard"></i> Project Review
                    </h2>
                    <p class="text-center text-muted mb-5">
                        Review and evaluate recently submitted student projects
                    </p>
                </div>
            </div>

            <!-- Projects for Review -->
            <div class="row">
                <div class="col-12">
                    <?php if (empty($recent_projects)): ?>
                        <div class="alert alert-info text-center">
                            <i class="fa fa-info-circle fa-3x mb-3"></i>
                            <h5>No Projects for Review</h5>
                            <p class="mb-0">No project submissions are currently pending for your review.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($recent_projects as $index => $project): ?>
                            <div class="project-card">
                                <div class="project-header position-relative">
                                    <span class="badge status-pending status-badge">
                                        <i class="fa fa-clock-o"></i> Pending Review
                                    </span>
                                    <div class="project-title">
                                        <span class="badge bg-light text-dark me-2"><?php echo $index + 1; ?></span>
                                        <i class="fa fa-folder-open"></i> 
                                        <?php echo htmlspecialchars($project['project_name']); ?>
                                    </div>
                                    <div class="leader-info">
                                        <i class="fa fa-user"></i> 
                                        Team Leader: <?php echo htmlspecialchars($project['leader_name']); ?> 
                                        (<?php echo htmlspecialchars($project['leader_roll']); ?>)
                                    </div>
                                </div>
                                
                                <div class="card-body p-4">
                                    <div class="row">
                                        <div class="col-lg-8">
                                            <!-- Team Members Table -->
                                            <h6 class="text-muted mb-3">
                                                <i class="fa fa-users"></i> TEAM MEMBERS
                                            </h6>
                                            <table class="table table-bordered team-table">
                                                <thead>
                                                    <tr>
                                                        <th>Sr No</th>
                                                        <th>Member Name</th>
                                                        <th>Roll No</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <!-- Team Leader -->
                                                    <tr>
                                                        <td><strong>1</strong></td>
                                                        <td><strong><?php echo htmlspecialchars($project['leader_name']); ?> (Leader)</strong></td>
                                                        <td><strong><?php echo htmlspecialchars($project['leader_roll']); ?></strong></td>
                                                    </tr>
                                                    <!-- Team Members -->
                                                    <?php if (!empty($project['team_members'])): ?>
                                                        <?php foreach ($project['team_members'] as $i => $member): ?>
                                                            <tr>
                                                                <td><?php echo $i + 2; ?></td>
                                                                <td><?php echo htmlspecialchars($member['name']); ?></td>
                                                                <td><?php echo htmlspecialchars($member['roll']); ?></td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    <?php endif; ?>
                                                </tbody>
                                            </table>
                                            
                                            <!-- Project Details -->
                                            <div class="row mt-4">
                                                <div class="col-sm-6">
                                                    <strong>Branch:</strong> <?php echo htmlspecialchars($project['branch_name']); ?>
                                                </div>
                                                <div class="col-sm-6">
                                                    <strong>Section:</strong> <?php echo htmlspecialchars($project['section']); ?>
                                                </div>
                                                <div class="col-12 mt-2">
                                                    <strong>Submitted:</strong> 
                                                    <?php echo date('F j, Y \a\t g:i A', strtotime($project['submitted_at'])); ?>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="col-lg-4">
                                            <!-- Download PPT -->
                                            <div class="mb-4">
                                                <h6 class="text-muted mb-3">
                                                    <i class="fa fa-download"></i> PROJECT FILE
                                                </h6>
                                                <?php if (!empty($project['file_name'])): ?>
                                                    <a href="../uploads/projects/<?php echo htmlspecialchars($project['file_name']); ?>" 
                                                       class="btn btn-download w-100" target="_blank">
                                                        <i class="fa fa-file-pdf-o"></i> Download/View PDF
                                                    </a>
                                                <?php else: ?>
                                                    <div class="alert alert-warning">
                                                        <i class="fa fa-exclamation-triangle"></i> No file uploaded
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <!-- Evaluation Form -->
                                            <div class="evaluation-form">
                                                <h6 class="text-muted mb-3">
                                                    <i class="fa fa-edit"></i> EVALUATE PROJECT
                                                </h6>
                                                <form method="POST" action="">
                                                    <input type="hidden" name="project_id" value="<?php echo $project['id']; ?>">
                                                    
                                                    <div class="mb-3">
                                                        <label for="presentation_date_<?php echo $project['id']; ?>" class="form-label">
                                                            Presentation Date
                                                        </label>
                                                        <input type="date" 
                                                               class="form-control" 
                                                               name="presentation_date" 
                                                               id="presentation_date_<?php echo $project['id']; ?>"
                                                               min="<?php echo date('Y-m-d'); ?>">
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <label for="remarks_<?php echo $project['id']; ?>" class="form-label">
                                                            Remarks
                                                        </label>
                                                        <textarea class="form-control" 
                                                                  name="remarks" 
                                                                  id="remarks_<?php echo $project['id']; ?>"
                                                                  rows="3" 
                                                                  placeholder="Enter your feedback..."></textarea>
                                                    </div>
                                                    
                                                    <div class="d-grid gap-2">
                                                        <button type="submit" name="action" value="accept" class="btn btn-accept">
                                                            <i class="fa fa-check"></i> Accept Project
                                                        </button>
                                                        <button type="submit" name="action" value="reject" class="btn btn-reject">
                                                            <i class="fa fa-times"></i> Reject Project
                                                        </button>
                                                    </div>
                                                </form>
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
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }, 5000);
            });
        });
        
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function(e) {
                const remarksField = this.querySelector('textarea[name="remarks"]');
                const dateField = this.querySelector('input[name="presentation_date"]');
                
                if (!remarksField.value.trim()) {
                    e.preventDefault();
                    alert('Please enter remarks before submitting.');
                    remarksField.focus();
                    return;
                }
                
                if (!dateField.value) {
                    e.preventDefault();
                    alert('Please select a presentation date.');
                    dateField.focus();
                    return;
                }
                
                const action = e.submitter.value;
                const confirmMessage = action === 'accept' ? 
                    'Are you sure you want to accept this project?' : 
                    'Are you sure you want to reject this project?';
                    
                if (!confirm(confirmMessage)) {
                    e.preventDefault();
                }
            });
        });
    </script>
</body>
</html>