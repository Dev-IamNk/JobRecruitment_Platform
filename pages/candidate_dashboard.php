<?php
require_once '../config/db.php';
require_once '../config/functions.php';
redirectIfNotLoggedIn();

if (getUserType() != 'candidate') {
    header('Location: ../pages/recruiter_dashboard.php');
    exit();
}

$candidate_id = $_SESSION['user_id'];

$stmt = $pdo->prepare("
    SELECT a.*, j.title as job_title, j.location, j.salary_range, u.full_name as recruiter_name 
    FROM applications a 
    JOIN jobs j ON a.job_id = j.id 
    JOIN users u ON j.recruiter_id = u.id 
    WHERE a.candidate_id = ? 
    ORDER BY a.applied_at DESC
");
$stmt->execute([$candidate_id]);
$applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Candidate Dashboard - RPA Recruitment</title>
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="container">
    <div class="nav-bar">
        <h2>Candidate Dashboard</h2>
        <div class="nav-links">
            <a href="view_jobs.php">Browse Jobs</a>
            <a href="../scripts/logout.php">Logout</a>
        </div>
    </div>

    <?php if (isset($_GET['success']) && $_GET['success'] === 'applied'): ?>
        <div class="alert alert-success">Application submitted successfully! RPA will process it soon.</div>
    <?php endif; ?>

    <h3>My Applications (<?php echo count($applications); ?>)</h3>

    <?php if (count($applications) === 0): ?>
        <p>No applications yet. <a href="view_jobs.php">Browse available jobs</a></p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Job Title</th>
                    <th>Location</th>
                    <th>Recruiter</th>
                    <th>Salary</th>
                    <th>Resume</th>
                    <th>Cover Letter</th>
                    <th>Extracted Skills</th>
                    <th>Score</th>
                    <th>Status</th>
                    <th>Applied On</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($applications as $app): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($app['job_title']); ?></td>
                        <td><?php echo htmlspecialchars($app['location']); ?></td>
                        <td><?php echo htmlspecialchars($app['recruiter_name']); ?></td>
                        <td><?php echo htmlspecialchars($app['salary_range']); ?></td>
                        <td>
                            <?php if ($app['resume_path']): ?>
                               <a href="../scripts/download.php?file=<?php echo urlencode($app['resume_path']); ?>">Download</a>


                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($app['cover_letter']); ?></td>
                        <td><?php echo htmlspecialchars($app['extracted_skills']); ?></td>
                        <td>
                            <?php 
                            if ($app['score'] !== null) {
                                echo number_format($app['score'], 2) . '%';
                            } else {
                                echo '0.00%';
                            }
                            ?>
                        </td>
                        <td><?php echo ucfirst($app['status']); ?></td>
                        <td><?php echo date('d M Y, H:i', strtotime($app['applied_at'])); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
</body>
</html>
