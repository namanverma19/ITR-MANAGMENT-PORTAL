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

try {
    $stmt = mysqli_prepare($connection, "
        SELECT DISTINCT b.id, b.branch_name, s.section_name
        FROM sections s
        JOIN branches b ON s.branch_id = b.id
        WHERE s.faculty_id = ?
        ORDER BY b.branch_name, s.section_name
    ");
    mysqli_stmt_bind_param($stmt, "i", $faculty_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $faculty_branches = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $branch_key = $row['branch_name'];
        if (!isset($faculty_branches[$branch_key])) {
            $faculty_branches[$branch_key] = [
                'id' => $row['id'],
                'name' => $row['branch_name'],
                'sections' => []
            ];
        }
        $faculty_branches[$branch_key]['sections'][] = $row['section_name'];
    }
    mysqli_stmt_close($stmt);
} catch (Exception $e) {
    $faculty_branches = [];
}

$selected_branch = isset($_GET['branch']) ? $_GET['branch'] : '';

try {
    if ($selected_branch) {
        $stmt = mysqli_prepare($connection, "
            SELECT 
                ps.*, 
                b.branch_name,
                r.section,
                r.name as student_name,
                r.college_id as student_roll
            FROM project_submissions ps
            JOIN itr_registeruser r ON ps.student_id = r.id
            JOIN branches b ON r.branch = b.id
            WHERE ps.faculty_id = ? AND ps.submission_status = 'accepted' AND b.branch_name = ?
            ORDER BY b.branch_name, r.section, ps.project_name ASC
        ");
        mysqli_stmt_bind_param($stmt, "is", $faculty_id, $selected_branch);
    } else {
        $stmt = mysqli_prepare($connection, "
            SELECT 
                ps.*, 
                b.branch_name,
                r.section,
                r.name as student_name,
                r.college_id as student_roll
            FROM project_submissions ps
            JOIN itr_registeruser r ON ps.student_id = r.id
            JOIN branches b ON r.branch = b.id
            WHERE ps.faculty_id = ? AND ps.submission_status = 'accepted'
            ORDER BY b.branch_name, r.section, ps.project_name ASC
        ");
        mysqli_stmt_bind_param($stmt, "i", $faculty_id);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $accepted_projects = [];
    $projects_by_section = [];
    
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
        $team_name = "Team " . $row['leader_name'];
        if (!empty($row['member2_name'])) {
            $team_name = "Team " . explode(' ', $row['leader_name'])[0];
        }
        $row['team_identifier'] = $team_name;
        $section_key = $row['branch_name'] . ' - Section ' . $row['section'];
        if (!isset($projects_by_section[$section_key])) {
            $projects_by_section[$section_key] = [];
        }
        $projects_by_section[$section_key][] = $row;
        $accepted_projects[] = $row;
    }
    mysqli_stmt_close($stmt);
    
} catch (Exception $e) {
    $accepted_projects = [];
    $projects_by_section = [];
    $error_message = "Failed to load project list.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Project List - ITR Portal</title>
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
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
            margin-bottom: 20px;
            border-left: 5px solid #1B5E20;
        }
        
        .project-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
        }
        
        .section-header {
            background: linear-gradient(135deg, #1B5E20, #388E3C);
            color: white;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .table-responsive {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            padding: 20px;
            margin-bottom: 30px;
        }
        
        .table th {
            background: linear-gradient(135deg, #1B5E20, #388E3C);
            color: white;
            border: none;
            font-weight: 600;
        }
        
        .table td {
            vertical-align: middle;
            border-bottom: 1px solid #e9ecef;
        }
        
        .btn-download {
            background: linear-gradient(135deg, #1565C0, #1976D2);
            border: none;
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.85rem;
            transition: all 0.3s ease;
        }
        
        .btn-download:hover {
            background: linear-gradient(135deg, #0D47A1, #1565C0);
            transform: translateY(-2px);
            color: white;
        }
        
        .team-members {
            font-size: 0.9rem;
            color: #666;
        }
        
        .message-box {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
        }
        
        .form-control, .form-select {
            border-radius: 10px;
            border: 1px solid rgba(255, 255, 255, 0.3);
            background: rgba(255, 255, 255, 0.9);
            transition: all 0.3s ease;
        }
        
        .form-control:focus, .form-select:focus {
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
        
        .status-accepted {
            background-color: #28a745;
            color: white;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.8em;
            font-weight: bold;
        }
        
        .status-pending {
            background-color: #ffc107;
            color: #212529;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.8em;
            font-weight: bold;
        }
        
        .status-not-submitted {
            background-color: #dc3545;
            color: white;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.8em;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <header>
        <nav class="navbar navbar-expand-lg navbar-gradient py-3">
            <div class="container-fluid px-4">
                <a class="navbar-brand fs-4 d-flex align-items-center" href="dashboard.php">
                    <img src="../img/Logo.png" alt="Portal Logo" class="logo">
                    <span class="text-white ms-2 fw-bold">STUDENT PROJECT LIST</span>
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
                            <a class="nav-link text-white px-3 active" href="studentprojectlist.php">
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

            <!-- Accepted Projects Section -->
            <div class="row">
                <div class="col-12">
                    <h2 class="mb-4 text-center" style="color: #1B5E20;">
                        <i class="fa fa-check-circle"></i> Accepted Projects - Details
                    </h2>
                    
                    <!-- Branch Selection -->
                    <?php if (!empty($faculty_branches)): ?>
                        <div class="row mb-4">
                            <div class="col-12">
                                <h5 class="mb-3">
                                    <i class="fa fa-graduation-cap"></i> Select Branch to View Projects:
                                </h5>
                                <div class="d-flex flex-wrap gap-2 mb-3">
                                    <a href="studentprojectlist.php" 
                                       class="btn <?php echo empty($selected_branch) ? 'btn-success' : 'btn-outline-success'; ?>">
                                        <i class="fa fa-list"></i> All Branches
                                    </a>
                                    <?php foreach ($faculty_branches as $branch): ?>
                                        <a href="studentprojectlist.php?branch=<?php echo urlencode($branch['name']); ?>" 
                                           class="btn <?php echo $selected_branch === $branch['name'] ? 'btn-success' : 'btn-outline-success'; ?>">
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
                                        Showing accepted projects for <strong><?php echo htmlspecialchars($selected_branch); ?></strong> branch.
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (empty($accepted_projects)): ?>
                        <div class="project-card text-center">
                            <div class="card-body py-5">
                                <i class="fa fa-file-text fa-3x mb-3" style="color: #1B5E20;"></i>
                                <h5>No Accepted Projects</h5>
                                <p class="text-muted">No projects have been accepted yet for your assigned students.</p>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- Projects Table by Branch and Section -->
                        <?php foreach ($projects_by_section as $section => $projects): ?>
                            <div class="section-header">
                                <h4 class="mb-0">
                                    <i class="fa fa-graduation-cap"></i> <?php echo htmlspecialchars($section); ?>
                                    <span class="badge bg-light text-dark ms-2"><?php echo count($projects); ?> Projects</span>
                                </h4>
                            </div>
                            
                            <div class="table-responsive mb-5">
                                <table class="table table-hover table-bordered">
                                    <thead class="table-dark">
                                        <tr>
                                            <th style="width: 8%;">Sr No</th>
                                            <th style="width: 20%;">Project Name</th>
                                            <th style="width: 15%;">Team</th>
                                            <th style="width: 15%;">Leader Name with Roll No</th>
                                            <th style="width: 20%;">Team Member Name and Roll No</th>
                                            <th style="width: 12%;">Date of Presentation</th>
                                            <th style="width: 10%;">Project Submit PDF Download Option</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($projects as $index => $project): ?>
                                            <tr>
                                                <td class="text-center"><strong><?php echo $index + 1; ?></strong></td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($project['project_name']); ?></strong>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars($project['project_description'] ?? 'No description'); ?></small>
                                                </td>
                                                <td>
                                                    <span class="badge bg-primary"><?php echo htmlspecialchars($project['team_identifier']); ?></span>
                                                    <br><small class="text-muted"><?php echo count($project['team_members']); ?> members</small>
                                                </td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($project['leader_name']); ?></strong>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars($project['leader_roll']); ?></small>
                                                </td>
                                                <td class="team-members">
                                                    <?php if (!empty($project['team_members']) && count($project['team_members']) > 1): ?>
                                                        <?php foreach ($project['team_members'] as $member): ?>
                                                            <?php if ($member['role'] !== 'Leader'): ?>
                                                                <div class="mb-1">
                                                                    <strong><?php echo htmlspecialchars($member['name']); ?></strong>
                                                                    <br><small class="text-muted"><?php echo htmlspecialchars($member['roll']); ?></small>
                                                                </div>
                                                            <?php endif; ?>
                                                        <?php endforeach; ?>
                                                    <?php else: ?>
                                                        <em class="text-muted">Individual Project</em>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center">
                                                    <?php if ($project['presentation_date']): ?>
                                                        <strong><?php echo date('M j, Y', strtotime($project['presentation_date'])); ?></strong>
                                                        <br><small class="text-muted"><?php echo date('D', strtotime($project['presentation_date'])); ?></small>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning text-dark">Not Scheduled</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center">
                                                    <?php if ($project['file_path']): ?>
                                                        <a href="<?php echo htmlspecialchars($project['file_path']); ?>" 
                                                           class="btn btn-success btn-sm" target="_blank" title="Download Project PDF">
                                                            <i class="fa fa-download"></i><br>
                                                            <small>Download PDF</small>
                                                        </a>
                                                    <?php else: ?>
                                                        <span class="text-muted">
                                                            <i class="fa fa-times-circle"></i><br>
                                                            <small>No File</small>
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
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
