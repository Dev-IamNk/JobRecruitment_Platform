<?php
require_once '../config/db.php';
redirectIfNotLoggedIn();

if (getUserType() != 'candidate') {
    header('Location: ../pages/recruiter_dashboard.php');
    exit();
}

// Fetch open jobs
$stmt = $pdo->query("SELECT j.*, u.full_name as recruiter_name FROM jobs j JOIN users u ON j.recruiter_id = u.id WHERE j.status='open' ORDER BY j.created_at DESC");
$jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Browse Jobs</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="container">
    <div class="nav-bar">
        <h2>Available Jobs</h2>
        <div class="nav-links">
            <a href="candidate_dashboard.php">Dashboard</a>
            <a href="../scripts/logout.php">Logout</a>
        </div>
    </div>

    <?php if (empty($jobs)): ?>
        <p>No jobs available at the moment. Please check back later.</p>
    <?php else: ?>
        <?php foreach ($jobs as $job): ?>
            <div class="job-card">
                <h3><?php echo htmlspecialchars($job['title']); ?></h3>
                <p>Company: <?php echo htmlspecialchars($job['recruiter_name']); ?> | Location: <?php echo htmlspecialchars($job['location']); ?> | Salary: <?php echo htmlspecialchars($job['salary_range'] ?? 'Not specified'); ?></p>
                <p>Required Skills: <?php echo htmlspecialchars($job['required_skills']); ?></p>

                <form action="../scripts/apply_handler.php" method="post" enctype="multipart/form-data">
                    <input type="hidden" name="job_id" value="<?php echo $job['id']; ?>">
                    <label>Upload Resume (PDF):</label>
                    <input type="file" name="resume" accept=".pdf" required>
                    <label>Cover Letter (optional):</label>
                    <textarea name="cover_letter"></textarea>
                    <button type="submit">Apply Now</button>
                </form>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
</body>
</html>
