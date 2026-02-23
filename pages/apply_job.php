<?php
require_once '../config/db.php';
redirectIfNotLoggedIn();

// Only candidates can apply
if (getUserType() != 'candidate') {
    header('Location: ../pages/recruiter_dashboard.php');
    exit();
}

// Get job_id from GET
$job_id = $_GET['job_id'] ?? null;

if (!$job_id) {
    die("Invalid job.");
}

// Fetch job details
$stmt = $pdo->prepare("SELECT * FROM jobs WHERE id = ?");
$stmt->execute([$job_id]);
$job = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$job) {
    die("Job not found.");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Apply for Job - <?php echo htmlspecialchars($job['title']); ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="container">
    <div class="nav-bar">
        <h2>Apply for: <?php echo htmlspecialchars($job['title']); ?></h2>
        <div class="nav-links">
            <a href="candidate_dashboard.php">Dashboard</a>
            <a href="../scripts/logout.php">Logout</a>
        </div>
    </div>

    <form action="../scripts/apply_handler.php" method="post" enctype="multipart/form-data">
        <input type="hidden" name="job_id" value="<?php echo htmlspecialchars($job_id); ?>">

        <div class="form-group">
            <label for="resume">Upload Your Resume (PDF only)</label>
            <input type="file" name="resume" id="resume" accept=".pdf" required>
        </div>

        <div class="form-group">
            <label for="cover_letter">Cover Letter (optional)</label>
            <textarea name="cover_letter" id="cover_letter" rows="5"></textarea>
        </div>

        <button type="submit" class="btn">Submit Application</button>
    </form>
</div>
</body>
</html>
