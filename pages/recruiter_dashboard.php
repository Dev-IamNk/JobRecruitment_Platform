<?php
require_once '../config/db.php';
require_once '../config/functions.php';

redirectIfNotLoggedIn();

// Only recruiters can access
if (getUserType() != 'recruiter') {
    header('Location: ../pages/candidate_dashboard.php');
    exit();
}

$recruiter_id = $_SESSION['user_id'];

// Fetch all applications for recruiter jobs
$stmt = $pdo->prepare("
    SELECT 
        a.id AS app_id,
        a.resume_path,
        a.cover_letter,
        a.extracted_skills,
        a.score,
        a.status,
        a.applied_at,
        j.id AS job_id,
        j.title AS job_title,
        j.location,
        j.salary_range,
        u.full_name AS candidate_name,
        u.email AS candidate_email
    FROM applications a
    JOIN jobs j ON a.job_id = j.id
    JOIN users u ON a.candidate_id = u.id
    WHERE j.recruiter_id = ?
    ORDER BY a.applied_at DESC
");
$stmt->execute([$recruiter_id]);
$applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Recruiter Dashboard - RPA Recruitment</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="container">
    <div class="nav-bar">
        <h2>Recruiter Dashboard</h2>
        <div class="nav-links">
            <a href="post_job.php">Post Job</a>
            <a href="../scripts/logout.php">Logout</a>
        </div>
    </div>

    <h3>Applications Received (<?php echo count($applications); ?>)</h3>

    <?php if (count($applications) == 0): ?>
        <p>No applications yet. Please check back later.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Candidate Name</th>
                    <th>Email</th>
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
                        <td><?php echo htmlspecialchars($app['candidate_name']); ?></td>
                        <td><?php echo htmlspecialchars($app['candidate_email']); ?></td>
                        <td>
                            <?php if ($app['resume_path']): ?>
                                <a href="../scripts/download.php?file=<?php echo urlencode($app['resume_path']); ?>">Download</a>
                            <?php else: ?>
                                Not uploaded
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($app['cover_letter'] ?: ''); ?></td>
                        <td><?php echo htmlspecialchars($app['extracted_skills'] ?: ''); ?></td>
                        <td>
                            <?php 
                                if ($app['score'] !== null && $app['score'] !== '') {
                                    echo number_format($app['score'], 2) . '%';
                                } else {
                                    echo '0.00%';
                                }
                            ?>
                        </td>
                        <td><?php echo ucfirst($app['status'] ?: 'Pending'); ?></td>
                        <td><?php echo date('d M Y, H:i', strtotime($app['applied_at'])); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
</body>
</html>
