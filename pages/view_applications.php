<!-- FILE: pages/view_applications.php -->
<?php 
require_once '../config/db.php';
redirectIfNotLoggedIn();
if (getUserType() != 'recruiter') {
    header('Location: candidate_dashboard.php');
    exit();
}

$job_id = intval($_GET['job_id'] ?? 0);
$recruiter_id = $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT * FROM jobs WHERE id = ? AND recruiter_id = ?");
$stmt->execute([$job_id, $recruiter_id]);
$job = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$job) {
    header('Location: recruiter_dashboard.php');
    exit();
}

// Get applications with ranking
$stmt = $pdo->prepare("
    SELECT a.*, u.full_name as candidate_name, u.email as candidate_email 
    FROM applications a 
    JOIN users u ON a.candidate_id = u.id 
    WHERE a.job_id = ? 
    ORDER BY a.rank ASC, a.score DESC, a.applied_at DESC
");
$stmt->execute([$job_id]);
$applications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Count by status
$pending = 0;
$scored = 0;
$shortlisted = 0;
foreach ($applications as $app) {
    if ($app['status'] == 'pending') $pending++;
    if ($app['status'] == 'scored') $scored++;
    if ($app['status'] == 'shortlisted') $shortlisted++;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Applications for <?php echo htmlspecialchars($job['title']); ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
        }
        .stat-number {
            font-size: 32px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .stat-label {
            font-size: 14px;
            opacity: 0.9;
        }
        .rank-badge {
            display: inline-block;
            background: #ffd700;
            color: #333;
            padding: 4px 10px;
            border-radius: 12px;
            font-weight: bold;
            font-size: 13px;
        }
        .rank-1 { background: #ffd700; } /* Gold */
        .rank-2 { background: #c0c0c0; } /* Silver */
        .rank-3 { background: #cd7f32; color: white; } /* Bronze */
        table {
            font-size: 14px;
        }
        .skills-cell {
            max-width: 200px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .action-btn {
            padding: 5px 10px;
            font-size: 12px;
            margin: 2px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="nav-bar">
            <h1>Applications</h1>
            <div class="nav-links">
                <a href="recruiter_dashboard.php">Dashboard</a>
                <a href="../scripts/logout.php">Logout</a>
            </div>
        </div>
        
        <h2><?php echo htmlspecialchars($job['title']); ?></h2>
        <p style="color: #666; margin-bottom: 20px;">
            <strong>Required Skills:</strong> <?php echo htmlspecialchars($job['required_skills']); ?>
        </p>
        
        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo count($applications); ?></div>
                <div class="stat-label">Total Applications</div>
            </div>
            <div class="stat-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                <div class="stat-number"><?php echo $pending; ?></div>
                <div class="stat-label">Pending Processing</div>
            </div>
            <div class="stat-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                <div class="stat-number"><?php echo $scored; ?></div>
                <div class="stat-label">Scored</div>
            </div>
            <div class="stat-card" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                <div class="stat-number"><?php echo $shortlisted; ?></div>
                <div class="stat-label">Shortlisted</div>
            </div>
        </div>
        
        <?php if (empty($applications)): ?>
            <div class="alert alert-error">No applications received yet.</div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Rank</th>
                        <th>Candidate Name</th>
                        <th>Email</th>
                        <th>Score</th>
                        <th>Extracted Skills</th>
                        <th>Status</th>
                        <th>Applied On</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($applications as $app): ?>
                        <tr style="<?php echo $app['rank'] <= 3 ? 'background: #fffbea;' : ''; ?>">
                            <td>
                                <?php if ($app['rank'] > 0): ?>
                                    <span class="rank-badge rank-<?php echo min($app['rank'], 3); ?>">
                                        #<?php echo $app['rank']; ?>
                                    </span>
                                <?php else: ?>
                                    <span style="color: #999;">-</span>
                                <?php endif; ?>
                            </td>
                            <td><strong><?php echo htmlspecialchars($app['candidate_name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($app['candidate_email']); ?></td>
                            <td>
                                <?php if ($app['score'] > 0): ?>
                                    <span class="score-badge score-<?php 
                                        echo $app['score'] >= 70 ? 'high' : ($app['score'] >= 50 ? 'medium' : 'low'); 
                                    ?>">
                                        <?php echo number_format($app['score'], 1); ?>%
                                    </span>
                                <?php else: ?>
                                    <span style="color: #999;">Processing...</span>
                                <?php endif; ?>
                            </td>
                            <td class="skills-cell" title="<?php echo htmlspecialchars($app['extracted_skills']); ?>">
                                <?php echo htmlspecialchars($app['extracted_skills'] ?: 'Processing...'); ?>
                            </td>
                            <td>
                                <span class="status-badge status-<?php echo $app['status']; ?>">
                                    <?php echo ucfirst($app['status']); ?>
                                </span>
                            </td>
                            <td><?php echo date('M d, Y', strtotime($app['applied_at'])); ?></td>
                            <td>
                                <a href="<?php echo $app['resume_path']; ?>" target="_blank" class="btn action-btn">
                                    📄 Resume
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        
        <div style="margin-top: 30px;">
            <a href="recruiter_dashboard.php" class="btn btn-secondary">← Back to Dashboard</a>
        </div>
    </div>
</body>
</html>