<!-- FILE: pages/apply_job.php (CHECK THIS FILE) -->
<?php 
require_once '../config/db.php';
redirectIfNotLoggedIn();
if (getUserType() != 'candidate') {
    header('Location: recruiter_dashboard.php');
    exit();
}

$job_id = intval($_GET['id'] ?? 0);

echo "<!-- DEBUG: Received job_id from URL = $job_id -->";

$stmt = $pdo->prepare("SELECT j.*, u.full_name as recruiter_name FROM jobs j JOIN users u ON j.recruiter_id = u.id WHERE j.id = ?");
$stmt->execute([$job_id]);
$job = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$job) {
    echo "<!-- DEBUG: Job not found for ID = $job_id -->";
    header('Location: view_jobs.php');
    exit();
}

echo "<!-- DEBUG: Job found - " . $job['title'] . " -->";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Apply for <?php echo htmlspecialchars($job['title']); ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="container">
        <div class="nav-bar">
            <h1>Apply for Job</h1>
            <div class="nav-links">
                <a href="view_jobs.php">Back to Jobs</a>
                <a href="candidate_dashboard.php">Dashboard</a>
            </div>
        </div>
        
        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-error">
                <?php 
                if ($_GET['error'] == 'filetype') echo 'Invalid file type! Please upload PDF, DOC, DOCX, or TXT.';
                elseif ($_GET['error'] == 'noresume') echo 'Please upload your resume!';
                elseif ($_GET['error'] == 'jobnotfound') echo 'Invalid job. The job may have been removed.';
                else echo 'Application failed. Please try again.';
                ?>
            </div>
        <?php endif; ?>
        
        <div class="job-card">
            <h2><?php echo htmlspecialchars($job['title']); ?></h2>
            <div class="job-meta">
                <strong>Company:</strong> <?php echo htmlspecialchars($job['recruiter_name']); ?> | 
                <strong>Location:</strong> <?php echo htmlspecialchars($job['location']); ?> | 
                <strong>Salary:</strong> <?php echo htmlspecialchars($job['salary_range']); ?>
            </div>
            <p><?php echo nl2br(htmlspecialchars($job['description'])); ?></p>
            <p><strong>Required Skills:</strong> <?php echo htmlspecialchars($job['required_skills']); ?></p>
        </div>
        
        <h3>Submit Your Application</h3>
        
        <!-- DEBUG INFO -->
        <div style="background: #fff3cd; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
            <strong>DEBUG INFO:</strong><br>
            Job ID being submitted: <strong><?php echo $job['id']; ?></strong><br>
            Job Title: <strong><?php echo htmlspecialchars($job['title']); ?></strong><br>
            Required Skills: <strong><?php echo htmlspecialchars($job['required_skills']); ?></strong>
        </div>
        
        <form action="../scripts/apply_handler.php" method="POST" enctype="multipart/form-data">
            <!-- IMPORTANT: This hidden field passes the job_id -->
            <input type="hidden" name="job_id" value="<?php echo $job['id']; ?>">
            
            <div class="form-group">
                <label>Upload Resume * (PDF, DOC, DOCX, TXT)</label>
                <input type="file" name="resume" required accept=".pdf,.doc,.docx,.txt">
                <small style="color: #666;">Max file size: 5MB</small>
            </div>
            
            <div class="form-group">
                <label>Cover Letter (Optional)</label>
                <textarea name="cover_letter" placeholder="Tell us why you're a great fit for this role..."></textarea>
            </div>
            
            <button type="submit">Submit Application</button>
            <a href="view_jobs.php" class="btn btn-secondary">Cancel</a>
        </form>
    </div>
</body>
</html>