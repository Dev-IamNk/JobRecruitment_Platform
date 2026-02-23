<?php
require_once '../config/db.php';
redirectIfNotLoggedIn();

if (getUserType() != 'recruiter') {
    header('Location: ../pages/candidate_dashboard.php');
    exit();
}

$job_id = $_GET['job_id'] ?? null;
$recruiter_id = $_SESSION['user_id'];

if (!$job_id) {
    die("Job ID is required.");
}

// Verify that this job belongs to the logged-in recruiter
$stmt = $pdo->prepare("SELECT * FROM jobs WHERE id = ? AND recruiter_id = ?");
$stmt->execute([$job_id, $recruiter_id]);
$job = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$job) {
    die("Job not found or you don't have permission to view its applications.");
}

// Fetch all applications for this job
$stmt = $pdo->prepare("
    SELECT a.*, u.full_name as candidate_name, u.email as candidate_email
    FROM applications a
    JOIN users u ON a.candidate_id = u.id
    WHERE a.job_id = ?
    ORDER BY a.applied_at DESC
");
$stmt->execute([$job_id]);
$applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Applications for <?php echo htmlspecialchars($job['title']); ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="container">
    <div class="nav-bar">
        <h2>Applications for "<?php echo htmlspecialchars($job['title']); ?>"</h2>
        <div class="nav-links">
            <a href="recruiter_dashboard.php">Back to Dashboard</a>
            <a href="../scripts/logout.php">Logout</a>
        </div>
    </div>

    <?php if (count($applications) == 0): ?>
        <p>No applications received yet for this job.</p>
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
                                <a href="<?php echo htmlspecialchars($app['resume_path']); ?>" target="_blank">Download</a>
                            <?php else: ?>
                                N/A
                            <?php endif; ?>
                        </td>
                        <td><?php echo nl2br(htmlspecialchars($app['cover_letter'])); ?></td>
                        <td><?php echo htmlspecialchars($app['extracted_skills']); ?></td>
                        <td>
                            <?php 
                            if ($app['score'] !== null) {
                                $score_class = $app['score'] >= 70 ? 'score-high' : ($app['score'] >= 40 ? 'score-medium' : 'score-low');
                                echo '<span class="score-badge ' . $score_class . '">' . $app['score'] . '%</span>';
                            } else {
                                echo 'Pending';
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
