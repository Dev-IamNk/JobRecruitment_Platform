<!-- FILE: pages/candidate_dashboard.php -->
<?php 
require_once '../config/db.php';
redirectIfNotLoggedIn();
if (getUserType() != 'candidate') {
    header('Location: recruiter_dashboard.php');
    exit();
}

$candidate_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT a.*, j.title as job_title, j.location, j.salary_range, u.full_name as recruiter_name 
                       FROM applications a 
                       JOIN jobs j ON a.job_id = j.id 
                       JOIN users u ON j.recruiter_id = u.id 
                       WHERE a.candidate_id = ? 
                       ORDER BY a.applied_at DESC");
$stmt->execute([$candidate_id]);
$applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Candidate Dashboard</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .app-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-left: 5px solid #667eea;
        }
        .app-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 15px;
        }
        .app-title {
            font-size: 20px;
            font-weight: bold;
            color: #333;
        }
        .app-meta {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #e0e0e0;
        }
        .meta-item {
            display: flex;
            flex-direction: column;
        }
        .meta-label {
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
            margin-bottom: 5px;
            font-weight: 600;
        }
        .meta-value {
            font-size: 15px;
            color: #333;
        }
        .company-info {
            color: #666;
            margin-bottom: 10px;
            font-size: 14px;
        }
        .status-message {
            padding: 12px;
            border-radius: 5px;
            margin-top: 15px;
            font-size: 14px;
        }
        .status-pending {
            background: #e7f3ff;
            color: #004085;
        }
        .status-scored {
            background: #d4edda;
            color: #155724;
        }
        .status-shortlisted {
            background: #d1ecf1;
            color: #0c5460;
        }
        .status-test_sent {
            background: #fff3cd;
            color: #856404;
        }
        .status-interview_scheduled {
            background: #cce5ff;
            color: #004085;
        }
        .status-selected {
            background: #d4edda;
            color: #155724;
            font-weight: bold;
        }
        .status-rejected {
            background: #f8d7da;
            color: #721c24;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="nav-bar">
            <h1>My Applications</h1>
            <div class="nav-links">
                <span>Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
                <a href="view_jobs.php">Browse Jobs</a>
                <a href="../scripts/logout.php">Logout</a>
            </div>
        </div>
        
        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success">
                ✅ Application submitted successfully! We'll review your application and get back to you soon.
            </div>
        <?php endif; ?>
        
        <h2>Applications (<?php echo count($applications); ?>)</h2>
        
        <?php if (empty($applications)): ?>
            <div class="alert" style="background: #e7f3ff; color: #004085; padding: 20px;">
                <p style="margin: 0; font-size: 16px;">
                    📋 You haven't applied to any jobs yet. 
                    <a href="view_jobs.php" style="font-weight: bold; color: #004085; text-decoration: underline;">Browse available jobs</a>
                </p>
            </div>
        <?php else: ?>
            <?php foreach ($applications as $app): ?>
                <div class="app-card">
                    <div class="app-header">
                        <div>
                            <div class="app-title"><?php echo htmlspecialchars($app['job_title']); ?></div>
                            <div class="company-info">
                                <?php echo htmlspecialchars($app['recruiter_name']); ?> • 
                                <?php echo htmlspecialchars($app['location']); ?>
                                <?php if ($app['salary_range']): ?>
                                    • <?php echo htmlspecialchars($app['salary_range']); ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div>
                            <span class="status-badge status-<?php echo $app['status']; ?>">
                                <?php 
                                $status_text = [
                                    'pending' => 'Under Review',
                                    'scored' => 'Under Review',
                                    'shortlisted' => 'Shortlisted',
                                    'test_sent' => 'Test Sent',
                                    'test_completed' => 'Test Completed',
                                    'interview_scheduled' => 'Interview Scheduled',
                                    'selected' => 'Selected',
                                    'rejected' => 'Not Selected'
                                ];
                                echo $status_text[$app['status']] ?? ucfirst($app['status']);
                                ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="app-meta">
                        <div class="meta-item">
                            <div class="meta-label">Applied On</div>
                            <div class="meta-value">
                                <?php echo date('F d, Y', strtotime($app['applied_at'])); ?>
                            </div>
                        </div>
                        
                        <div class="meta-item">
                            <div class="meta-label">Application Status</div>
                            <div class="meta-value">
                                <?php 
                                switch($app['status']) {
                                    case 'pending':
                                    case 'scored':
                                        echo 'Application received';
                                        break;
                                    case 'shortlisted':
                                        echo 'Shortlisted for next round';
                                        break;
                                    case 'test_sent':
                                        echo 'Assessment test sent';
                                        break;
                                    case 'test_completed':
                                        echo 'Test submitted';
                                        break;
                                    case 'interview_scheduled':
                                        echo 'Interview scheduled';
                                        break;
                                    case 'selected':
                                        echo '🎉 Congratulations!';
                                        break;
                                    case 'rejected':
                                        echo 'Thank you for applying';
                                        break;
                                    default:
                                        echo ucfirst($app['status']);
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Status-specific messages -->
                    <?php if ($app['status'] == 'pending' || $app['status'] == 'scored'): ?>
                        <div class="status-message status-pending">
                            ⏳ Your application is being reviewed by our recruitment team. We'll notify you about the next steps soon.
                        </div>
                    <?php elseif ($app['status'] == 'shortlisted'): ?>
                        <div class="status-message status-shortlisted">
                            ✅ Congratulations! You've been shortlisted. 
                            <?php 
                            // Check if test is configured for this job
                            $stmt_test = $pdo->prepare("SELECT COUNT(*) as test_count FROM test_configs WHERE job_id = ?");
                            $stmt_test->execute([$app['job_id']]);
                            $test_exists = $stmt_test->fetch(PDO::FETCH_ASSOC)['test_count'] > 0;
                            
                            if ($test_exists):
                                // Check if test already taken
                                $stmt_attempt = $pdo->prepare("SELECT status FROM test_attempts WHERE application_id = ?");
                                $stmt_attempt->execute([$app['id']]);
                                $test_attempt = $stmt_attempt->fetch(PDO::FETCH_ASSOC);
                                
                                if (!$test_attempt):
                            ?>
                                <br><br>
                                <a href="take_test.php?app_id=<?php echo $app['id']; ?>" class="btn" style="background: #28a745; display: inline-block; margin-top: 10px;">
                                    📝 Take Online Assessment
                                </a>
                            <?php elseif ($test_attempt['status'] == 'in_progress'): ?>
                                <br><br>
                                <a href="take_test.php?app_id=<?php echo $app['id']; ?>" class="btn" style="background: #ffc107; color: #333; display: inline-block; margin-top: 10px;">
                                    ⏸️ Resume Test
                                </a>
                            <?php elseif ($test_attempt['status'] == 'completed'): ?>
                                <br><br>
                                <span style="color: #28a745; font-weight: bold;">✓ Test Completed</span>
                            <?php endif; ?>
                            <?php else: ?>
                                You'll receive further instructions via email shortly.
                            <?php endif; ?>
                        </div>
                    <?php elseif ($app['status'] == 'test_sent'): ?>
                        <div class="status-message status-test_sent">
                            📧 An assessment test has been sent to your email. Please complete it within the given timeframe.
                        </div>
                    <?php elseif ($app['status'] == 'interview_scheduled'): ?>
                        <div class="status-message status-interview_scheduled">
                            📅 Your interview has been scheduled. Check your email for the meeting link and details.
                        </div>
                    <?php elseif ($app['status'] == 'selected'): ?>
                        <div class="status-message status-selected">
                            🎉 Congratulations! You have been selected for this position. Our HR team will contact you with the offer details.
                        </div>
                    <?php elseif ($app['status'] == 'rejected'): ?>
                        <div class="status-message status-rejected">
                            Thank you for your interest. While we won't be moving forward with your application at this time, we encourage you to apply for other positions that match your skills.
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</body>
</html>