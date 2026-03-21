<!-- FILE: pages/recruiter_dashboard.php -->
<?php 
require_once '../config/db.php';
redirectIfNotLoggedIn();
if (getUserType() != 'recruiter') {
    header('Location: candidate_dashboard.php');
    exit();
}

$recruiter_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM jobs WHERE recruiter_id = ? ORDER BY created_at DESC");
$stmt->execute([$recruiter_id]);
$jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recruiter Dashboard</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .job-info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            margin: 15px 0;
            font-size: 14px;
        }
        .info-item {
            background: white;
            padding: 10px;
            border-radius: 5px;
            border-left: 3px solid #667eea;
        }
        .info-label {
            font-weight: 600;
            color: #666;
            font-size: 12px;
            text-transform: uppercase;
        }
        .info-value {
            color: #333;
            font-size: 14px;
            margin-top: 5px;
        }
        .deadline-warning {
            background: #fff3cd;
            color: #856404;
            padding: 8px 12px;
            border-radius: 5px;
            display: inline-block;
            font-size: 13px;
            margin-top: 10px;
        }
        .deadline-passed {
            background: #f8d7da;
            color: #721c24;
        }
        .action-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 15px;
        }
        .btn-small {
            padding: 8px 16px;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="nav-bar">
            <h1>Recruiter Dashboard</h1>
            <div class="nav-links">
                <span>Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
                <a href="post_job.php">Post New Job</a>
                <a href="../scripts/logout.php">Logout</a>
            </div>
        </div>
        
        <?php if (isset($_GET['success'])): ?>
            <?php if ($_GET['success'] == 'posted'): ?>
                <div class="alert alert-success">✅ Job posted successfully!</div>
            <?php elseif ($_GET['success'] == 'shortlisted'): ?>
                <div class="alert alert-success">
                    ✅ Successfully shortlisted <?php echo intval($_GET['count'] ?? 0); ?> candidate(s)!
                    <a href="view_applications.php?job_id=<?php echo intval($_GET['job_id'] ?? 0); ?>&filter=shortlisted">
                        View shortlisted candidates
                    </a>
                </div>
            <?php endif; ?>
        <?php endif; ?>
        
        <h2>My Job Postings (<?php echo count($jobs); ?>)</h2>
        
        <?php if (empty($jobs)): ?>
            <p>You haven't posted any jobs yet. <a href="post_job.php">Post your first job</a></p>
        <?php else: ?>
            <?php foreach ($jobs as $job): 
                // Get application count
                $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM applications WHERE job_id = ?");
                $stmt->execute([$job['id']]);
                $app_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
                
                // Check if deadline passed
                $deadline = strtotime($job['application_deadline']);
                $now = time();
                $deadline_passed = ($deadline < $now);
                $days_left = ceil(($deadline - $now) / (60 * 60 * 24));
            ?>
                <div class="job-card">
                    <h3><?php echo htmlspecialchars($job['title']); ?></h3>
                    
                    <div class="job-info-grid">
                        <div class="info-item">
                            <div class="info-label">Location</div>
                            <div class="info-value"><?php echo htmlspecialchars($job['location']); ?></div>
                        </div>
                        
                        <div class="info-item">
                            <div class="info-label">Salary Range</div>
                            <div class="info-value"><?php echo htmlspecialchars($job['salary_range']); ?></div>
                        </div>
                        
                        <div class="info-item">
                            <div class="info-label">Application Deadline</div>
                            <div class="info-value">
                                <?php echo date('M d, Y h:i A', strtotime($job['application_deadline'])); ?>
                            </div>
                        </div>
                        
                        <div class="info-item">
                            <div class="info-label">Shortlisting Mode</div>
                            <div class="info-value">
                                <?php 
                                if ($job['shortlisting_mode'] == 'automatic') {
                                    echo "Automatic (Top " . $job['auto_shortlist_count'] . ")";
                                } else {
                                    echo "Manual Selection";
                                }
                                ?>
                            </div>
                        </div>
                        
                        <div class="info-item">
                            <div class="info-label">Interview Scheduled</div>
                            <div class="info-value">
                                <?php 
                                if ($job['interview_date']) {
                                    echo date('M d, Y h:i A', strtotime($job['interview_date']));
                                } else {
                                    echo "Not scheduled yet";
                                }
                                ?>
                            </div>
                        </div>
                        
                        <div class="info-item">
                            <div class="info-label">Total Applications</div>
                            <div class="info-value">
                                <strong style="font-size: 18px; color: #667eea;"><?php echo $app_count; ?></strong>
                            </div>
                        </div>
                    </div>
                    
                    <?php if (!$deadline_passed && $days_left > 0): ?>
                        <div class="deadline-warning">
                            ⏰ Deadline in <?php echo $days_left; ?> day<?php echo $days_left > 1 ? 's' : ''; ?>
                        </div>
                    <?php elseif ($deadline_passed): ?>
                        <div class="deadline-warning deadline-passed">
                            ❌ Deadline passed - Ready for shortlisting
                        </div>
                    <?php endif; ?>
                    
                    <p style="margin-top: 15px;"><strong>Required Skills:</strong> <?php echo htmlspecialchars($job['required_skills']); ?></p>
                    
                    <div class="action-btns">
                        <a href="test_results.php?job_id=<?php echo $job['id']; ?>" class="btn btn-small" style="background: #17a2b8;">
                            📊 View Test Results & Analytics
                        </a>
                        
                        <?php if ($deadline_passed && $app_count > 0): ?>
                            <a href="shortlist_candidates.php?job_id=<?php echo $job['id']; ?>" class="btn btn-small" style="background: #28a745;">
                                ✅ Shortlist Candidates (<?php echo $app_count; ?>)
                            </a>
                        <?php endif; ?>
                        
                        <?php 
                        // Count shortlisted candidates
                        $stmt_short = $pdo->prepare("SELECT COUNT(*) as count FROM applications WHERE job_id = ? AND status = 'shortlisted'");
                        $stmt_short->execute([$job['id']]);
                        $shortlisted = $stmt_short->fetch(PDO::FETCH_ASSOC)['count'];
                        
                        if ($shortlisted > 0): 
                        ?>
                            <a href="view_applications.php?job_id=<?php echo $job['id']; ?>&filter=shortlisted" class="btn btn-small" style="background: #17a2b8;">
                                👥 View Shortlisted (<?php echo $shortlisted; ?>)
                            </a>
                        <?php endif; ?>
                        
                        <a href="edit_job.php?id=<?php echo $job['id']; ?>" class="btn btn-small btn-secondary">
                            ✏️ Edit Job
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</body>
</html>