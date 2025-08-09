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

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $submission_id = $_POST['submission_id'];
    $action = $_POST['action'];
    
    try {
        if ($action == 'accept') {
            $presentation_date = $_POST['presentation_date'] ?? null;
            $remarks = trim($_POST['remarks'] ?? '');
            
            $stmt = mysqli_prepare($connection, "
                UPDATE project_submissions 
                SET submission_status = 'accepted', faculty_remarks = ?, presentation_date = ?, reviewed_at = NOW()
                WHERE id = ? AND faculty_id = ?
            ");
            mysqli_stmt_bind_param($stmt, "ssii", $remarks, $presentation_date, $submission_id, $faculty_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            
        } elseif ($action == 'reject') {
            $remarks = trim($_POST['remarks'] ?? '');
            
            $stmt = mysqli_prepare($connection, "
                UPDATE project_submissions 
                SET submission_status = 'rejected', faculty_remarks = ?, reviewed_at = NOW()
                WHERE id = ? AND faculty_id = ?
            ");
            mysqli_stmt_bind_param($stmt, "sii", $remarks, $submission_id, $faculty_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
    } catch (Exception $e) {
        $error_message = "Error updating submission status.";
    }
}

try {
    $stmt = mysqli_prepare($connection, "
        SELECT ps.*, r.name as student_name, r.email as student_email
        FROM project_submissions ps
        JOIN itr_registeruser r ON ps.student_id = r.id
        WHERE ps.faculty_id = ?
        ORDER BY ps.submitted_at DESC
    ");
    mysqli_stmt_bind_param($stmt, "i", $faculty_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $submissions = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $submissions[] = $row;
    }
    mysqli_stmt_close($stmt);
} catch (Exception $e) {
    $submissions = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Faculty Dashboard - ITR Portal</title>
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
    border: 1px solid #E0E0DE;
    border-radius: 0.75rem;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    transition: all 0.3s ease;
    overflow: hidden;
    margin-bottom: 1rem;
}

.infocards:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.12);
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
    color: #1B5E20;
}
.infocards .card-title .fa {
    margin-right: 0.5rem;
    color: inherit;
}

.infocards .card-text,
.infocards ul {
    font-size: 0.95rem;
    line-height: 1.6;
    color: #5D4037;
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
    color: #5D4037;
    width: 100%;
}

.infocards .card-body.quick .d-flex .btn {
    flex-grow: 1;
    font-size: 1.05rem;
    padding: 0.8rem 1rem;
    background-color: #FFB300;
    border-color: #FFB300;
    color: #3E2723;
    transition: background-color 0.3s ease, border-color 0.3s ease, color 0.3s ease;
}

.infocards .card-body.quick .d-flex .btn:hover {
    background-color: #E69A00;
    border-color: #E69A00;
    color: #3E2723;
}

footer {
    font-family: 'Roboto', sans-serif;
    background-color: #2E7D32 !important;
    color: #E8F5E9;
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
    color: rgba(232, 245, 233, 0.6);
    transition: color 0.3s ease, filter 0.3s ease;
    filter: grayscale(100%) brightness(150%);
}

.social-icon:hover {
    color: #ffffff !important;
    filter: grayscale(0%) brightness(100%);
}

.bg-secondary {
    background-color: #1B5E20 !important;
    border-top: 1px solid rgba(255, 255, 255, 0.1) !important;
    font-size: 0.8rem;
    color: rgba(232, 245, 233, 0.5) !important;
}

.bg-secondary a {
    color: rgba(232, 245, 233, 0.5) !important;
}

.bg-secondary a:hover {
    color: #ffffff !important;
}

@media (max-width: 991.98px) {
    .navbar-brand .text-white {
        font-size: 1.4rem;
    }
    .navbar-nav {
        background-color: #2E7D32;
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

.main1{
    border-width: 3px;
    border-style: dashed;
}

.profile-card-container {
  display: flex;
  background: linear-gradient(to right, rgb(0, 0, 0), rgb(255, 255, 255));
  border-radius: 15px;
  padding: 20px;
  width: 600px; 
  margin: 20px auto;
}

.profile-section {
  background-color: rgb(30, 30, 30);
  color: rgb(255, 255, 255);
  border-radius: 10px;
  padding: 20px;
  text-align: center;
  flex: 1;
}

.profile-image-container {
  width: 120px;
  height: 120px;
  border-radius: 50%;
  overflow: hidden;
  margin: 0 auto 15px;
  border: 5px solid rgb(255, 255, 255);
}

.profile-image {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

.name {
  font-size: 24px; 
  margin-bottom: 5px;
}

.title {
  font-size: 14.4px;
  color: rgb(204, 204, 204);
}

.details-section {
  background-color: rgb(30, 30, 30);
  color: rgb(255, 255, 255);
  border-radius: 10px;
  padding: 20px;
  flex: 2;
  margin-left: 20px;
}

.details-title {
  font-size: 20.8px; 
  margin-bottom: 15px;
  color: rgb(72, 250, 101); 
  text-align: center;
}

.detail-row {
  display: flex;
  margin-bottom: 10px;
}

.label {
  font-weight: bold;
  width: 80px; 
  color: rgb(238, 238, 238);
}

.value {
  flex-grow: 1;
}

.made-with {
  text-align: center;
  margin-top: 15px;
  font-size: 12.8px; 
  color: rgb(204, 204, 204);
}

.submission-card {
    border: none;
    border-radius: 15px;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
    transition: transform 0.3s ease;
    margin-bottom: 20px;
}

.submission-card:hover {
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

    </style>
    
</head>
<body>
    <header>
        <nav class="navbar navbar-expand-lg navbar-gradient py-3">
            <div class="container-fluid px-4">
                <a class="navbar-brand fs-4 d-flex align-items-center" href="#">
                    <img src="../img/Logo.png" alt="Portal Logo" class="logo">
                    <span class="text-white ms-2 fw-bold">FACULTY DASHBOARD</span>
                </a>

                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav ms-auto">
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle text-white px-3" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fa fa-user-circle" aria-hidden="true"></i> <?php echo htmlspecialchars($faculty_name); ?>
                            </a>
                            <ul class="dropdown-menu" aria-labelledby="navbarDropdown">
                                <li><a class="dropdown-item" href="#profile"><i class="fa fa-user" aria-hidden="true"></i> My Profile</a></li>
                                <li><a class="dropdown-item" href="studentlist.php"><i class="fa fa-users" aria-hidden="true"></i> Student List</a></li>
                                <li><a class="dropdown-item" href="studentprojectlist.php"><i class="fa fa-file-text" aria-hidden="true"></i> Project List</a></li>
                                <li><a class="dropdown-item" href="projectreview.php"><i class="fa fa-clipboard" aria-hidden="true"></i> Project Review</a></li>
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
    
        <div class="profile-card-container" id="profile">
            <div class="profile-section">
                <div class="profile-image-container">
                    <img src="../img/Logo.png" alt="<?php echo htmlspecialchars($faculty_name); ?>" class="profile-image">
                </div>
                <div class="name"><?php echo htmlspecialchars($faculty_name); ?></div>
                <div class="title">Professor</div>
            </div>
            <div class="details-section">
                <div class="details-title">Profile Details</div>
                <div class="detail-row">
                    <div class="label">Name:</div>
                    <div class="value"><?php echo htmlspecialchars($faculty_name); ?></div>
                </div>
                <div class="detail-row">
                    <div class="label">Email:</div>
                    <div class="value"><?php echo htmlspecialchars($faculty_email); ?></div>
                </div>
                <div class="detail-row">
                    <div class="label">Role:</div>
                    <div class="value">Faculty Member</div>
                </div>
            </div>
        </div>

    
        <div class="container my-5">
            <div class="row">
                <div class="col-12">
                    <h2 class="mb-4 text-center" style="color: #1B5E20;">
                        <i class="fa fa-tasks"></i> Project Submissions Review
                    </h2>
                    
                    <?php if (empty($submissions)): ?>
                        <div class="card infocards text-center">
                            <div class="card-body">
                                <i class="fa fa-inbox fa-3x mb-3" style="color: #1B5E20;"></i>
                                <h5>No submissions yet</h5>
                                <p class="text-muted">Project submissions assigned to you will appear here.</p>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($submissions as $submission): ?>
                            <div class="card submission-card status-<?php echo $submission['submission_status']; ?>">
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-8">
                                            <h5 class="card-title">
                                                <i class="fa fa-folder-open"></i> 
                                                <?php echo htmlspecialchars($submission['project_name']); ?>
                                            </h5>
                                            <p class="card-text">
                                                <strong>Student:</strong> <?php echo htmlspecialchars($submission['student_name']); ?><br>
                                                <strong>Email:</strong> <?php echo htmlspecialchars($submission['student_email']); ?><br>
                                                <strong>Submitted:</strong> <?php echo date('M d, Y H:i', strtotime($submission['submitted_at'])); ?>
                                            </p>
                                            <p class="card-text">
                                                <strong>Project Description:</strong><br>
                                                <?php echo nl2br(htmlspecialchars($submission['project_description'])); ?>
                                            </p>
                                            
                                            
                                            <div class="mt-3">
                                                <strong>Team Members:</strong>
                                                <ul class="list-unstyled mt-2">
                                                    <li><i class="fa fa-user"></i> <strong>Leader:</strong> <?php echo htmlspecialchars($submission['leader_name']); ?> (<?php echo htmlspecialchars($submission['leader_email']); ?>)</li>
                                                    <?php if ($submission['member2_name']): ?>
                                                        <li><i class="fa fa-user"></i> <?php echo htmlspecialchars($submission['member2_name']); ?> (<?php echo htmlspecialchars($submission['member2_email']); ?>)</li>
                                                    <?php endif; ?>
                                                    <?php if ($submission['member3_name']): ?>
                                                        <li><i class="fa fa-user"></i> <?php echo htmlspecialchars($submission['member3_name']); ?> (<?php echo htmlspecialchars($submission['member3_email']); ?>)</li>
                                                    <?php endif; ?>
                                                    <?php if ($submission['member4_name']): ?>
                                                        <li><i class="fa fa-user"></i> <?php echo htmlspecialchars($submission['member4_name']); ?> (<?php echo htmlspecialchars($submission['member4_email']); ?>)</li>
                                                    <?php endif; ?>
                                                </ul>
                                            </div>
                                        </div>
                                        <div class="col-md-4 text-end">
                                            <span class="badge badge-<?php echo $submission['submission_status']; ?> mb-3">
                                                <?php echo ucfirst($submission['submission_status']); ?>
                                            </span>
                                            
                                            <?php if ($submission['submission_status'] == 'pending'): ?>
                                                <div class="mt-3">
                                                    <button class="btn btn-success btn-sm me-2" data-bs-toggle="modal" data-bs-target="#acceptModal<?php echo $submission['id']; ?>">
                                                        <i class="fa fa-check"></i> Accept
                                                    </button>
                                                    <button class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#rejectModal<?php echo $submission['id']; ?>">
                                                        <i class="fa fa-times"></i> Reject
                                                    </button>
                                                </div>
                                            <?php else: ?>
                                                <div class="mt-3">
                                                    <?php if ($submission['faculty_remarks']): ?>
                                                        <p><strong>Remarks:</strong> <?php echo htmlspecialchars($submission['faculty_remarks']); ?></p>
                                                    <?php endif; ?>
                                                    <?php if ($submission['presentation_date']): ?>
                                                        <p><strong>Presentation Date:</strong> <?php echo date('M d, Y', strtotime($submission['presentation_date'])); ?></p>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="modal fade" id="acceptModal<?php echo $submission['id']; ?>" tabindex="-1">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Accept Project</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <form method="POST">
                                            <div class="modal-body">
                                                <input type="hidden" name="submission_id" value="<?php echo $submission['id']; ?>">
                                                <input type="hidden" name="action" value="accept">
                                                
                                                <div class="mb-3">
                                                    <label for="presentation_date" class="form-label">Presentation Date (Optional)</label>
                                                    <input type="date" class="form-control" name="presentation_date">
                                                </div>
                                                
                                                <div class="mb-3">
                                                    <label for="remarks" class="form-label">Remarks (Optional)</label>
                                                    <textarea class="form-control" name="remarks" rows="3" placeholder="Add any comments..."></textarea>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn btn-success">Accept Project</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>

                            
                            <div class="modal fade" id="rejectModal<?php echo $submission['id']; ?>" tabindex="-1">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Reject Project</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <form method="POST">
                                            <div class="modal-body">
                                                <input type="hidden" name="submission_id" value="<?php echo $submission['id']; ?>">
                                                <input type="hidden" name="action" value="reject">
                                                
                                                <div class="mb-3">
                                                    <label for="remarks" class="form-label">Reason for Rejection</label>
                                                    <textarea class="form-control" name="remarks" rows="3" placeholder="Please provide reason for rejection..." required></textarea>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn btn-danger">Reject Project</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
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
                            <li class="mb-2"><a href="#" class="text-white-50 text-decoration-none">Dashboard</a></li>
                            <li class="mb-2"><a href="#" class="text-white-50 text-decoration-none">Project Review</a></li>
                            <li class="mb-2"><a href="#" class="text-white-50 text-decoration-none">Student Management</a></li>
                            <li class="mb-2"><a href="#" class="text-white-50 text-decoration-none">Reports</a></li>
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
</body>
</html>
