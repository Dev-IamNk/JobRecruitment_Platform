<?php 
require_once '../config/db.php';
redirectIfNotLoggedIn();
if (getUserType() != 'recruiter') {
    header('Location: candidate_dashboard.php');
    exit();
}

$job_id = intval($_GET['job_id'] ?? 0);
$recruiter_id = $_SESSION['user_id'];

// Get job details
$stmt = $pdo->prepare("SELECT * FROM jobs WHERE id = ? AND recruiter_id = ?");
$stmt->execute([$job_id, $recruiter_id]);
$job = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$job) {
    header('Location: recruiter_dashboard.php');
    exit();
}

// Get all applications with test results
$stmt = $pdo->prepare("
    SELECT a.*, u.full_name as candidate_name, u.email as candidate_email,
           ta.total_score as test_score, ta.status as test_status, ta.submitted_at as test_submitted
    FROM applications a 
    JOIN users u ON a.candidate_id = u.id 
    LEFT JOIN test_attempts ta ON ta.application_id = a.id
    WHERE a.job_id = ? AND a.status IN ('scored', 'test_completed', 'shortlisted')
    ORDER BY a.score DESC, a.applied_at ASC
");
$stmt->execute([$job_id]);
$applications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Function to get section-wise scores
function getSectionScores($application_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT qb.section_type, qb.topic,
               COUNT(*) as total_questions,
               SUM(ta.is_correct) as correct_answers
        FROM test_answers ta
        JOIN question_bank qb ON ta.question_id = qb.id
        JOIN test_attempts tat ON ta.attempt_id = tat.id
        WHERE tat.application_id = ?
        GROUP BY qb.section_type, qb.topic
    ");
    $stmt->execute([$application_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Results - <?php echo htmlspecialchars($job['title']); ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .results-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 25px;
            border-radius: 10px;
            text-align: center;
        }
        .stat-number {
            font-size: 36px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .stat-label {
            font-size: 14px;
            opacity: 0.9;
        }
        .candidate-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-left: 5px solid #667eea;
        }
        .candidate-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }
        .candidate-name {
            font-size: 24px;
            font-weight: bold;
            color: #333;
        }
        .scores-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }
        .score-box {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
        }
        .score-box.resume {
            border-left: 4px solid #667eea;
        }
        .score-box.test {
            border-left: 4px solid #28a745;
        }
        .score-box.combined {
            border-left: 4px solid #ffc107;
        }
        .score-box.rank {
            border-left: 4px solid #dc3545;
        }
        .score-title {
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
            margin-bottom: 8px;
        }
        .score-value {
            font-size: 28px;
            font-weight: bold;
            color: #333;
        }
        .section-breakdown {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .section-row {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr;
            padding: 12px;
            border-bottom: 1px solid #e0e0e0;
        }
        .section-row:last-child {
            border-bottom: none;
        }
        .section-row.header {
            font-weight: bold;
            background: #667eea;
            color: white;
            border-radius: 5px;
            margin-bottom: 10px;
        }
        .progress-circle {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: conic-gradient(#28a745 0deg, #28a745 calc(var(--progress) * 3.6deg), #e0e0e0 calc(var(--progress) * 3.6deg));
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }
        .progress-circle::before {
            content: '';
            position: absolute;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: white;
        }
        .progress-text {
            position: relative;
            z-index: 1;
            font-weight: bold;
            font-size: 16px;
        }
        .action-btns {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        .expand-btn {
            background: #667eea;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
        }
        .expand-btn:hover {
            background: #5568d3;
        }
        .details-section {
            display: none;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 2px solid #e0e0e0;
        }
        .details-section.active {
            display: block;
        }
        .tab-buttons {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        .tab-btn {
            padding: 10px 20px;
            background: #f0f0f0;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
        }
        .tab-btn.active {
            background: #667eea;
            color: white;
        }
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="nav-bar">
            <h1>Test Results & Analytics</h1>
            <div class="nav-links">
                <a href="recruiter_dashboard.php">Dashboard</a>
                <a href="../scripts/logout.php">Logout</a>
            </div>
        </div>
        
        <h2><?php echo htmlspecialchars($job['title']); ?></h2>
        
        <?php
        // Calculate statistics
        $total_candidates = count($applications);
        $test_completed = 0;
        $avg_test_score = 0;
        $avg_resume_score = 0;
        
        foreach ($applications as $app) {
            if ($app['test_status'] == 'completed') $test_completed++;
            $avg_test_score += $app['test_score'] ?? 0;
            $avg_resume_score += $app['score'];
        }
        
        $avg_test_score = $total_candidates > 0 ? round($avg_test_score / $total_candidates, 1) : 0;
        $avg_resume_score = $total_candidates > 0 ? round($avg_resume_score / $total_candidates, 1) : 0;
        ?>
        
        <!-- Statistics -->
        <div class="results-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $total_candidates; ?></div>
                <div class="stat-label">Total Candidates</div>
            </div>
            <div class="stat-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                <div class="stat-number"><?php echo $test_completed; ?></div>
                <div class="stat-label">Tests Completed</div>
            </div>
            <div class="stat-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                <div class="stat-number"><?php echo $avg_resume_score; ?>%</div>
                <div class="stat-label">Avg Resume Score</div>
            </div>
            <div class="stat-card" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                <div class="stat-number"><?php echo $avg_test_score; ?>%</div>
                <div class="stat-label">Avg Test Score</div>
            </div>
        </div>
        
        <!-- Candidates List -->
        <?php if (empty($applications)): ?>
            <div class="alert alert-error">No candidates available.</div>
        <?php else: ?>
            <?php foreach ($applications as $index => $app): 
                $section_scores = getSectionScores($app['id']);
                
                // Group by section type
                $numerical_scores = [];
                $logical_scores = [];
                $verbal_scores = [];
                $technical_scores = [];
                
                foreach ($section_scores as $section) {
                    switch ($section['section_type']) {
                        case 'numerical':
                            $numerical_scores[] = $section;
                            break;
                        case 'logical':
                            $logical_scores[] = $section;
                            break;
                        case 'verbal':
                            $verbal_scores[] = $section;
                            break;
                        case 'technical':
                            $technical_scores[] = $section;
                            break;
                    }
                }
            ?>
                <div class="candidate-card">
                    <div class="candidate-header">
                        <div>
                            <div class="candidate-name"><?php echo htmlspecialchars($app['candidate_name']); ?></div>
                            <div style="color: #666; margin-top: 5px;"><?php echo htmlspecialchars($app['candidate_email']); ?></div>
                        </div>
                        <div>
                            <span class="rank-badge rank-<?php echo min($index + 1, 3); ?>" style="font-size: 18px; padding: 8px 16px;">
                                Rank #<?php echo $index + 1; ?>
                            </span>
                        </div>
                    </div>
                    
                    <!-- Scores Row -->
                    <div class="scores-row">
                        <div class="score-box resume">
                            <div class="score-title">Resume Score</div>
                            <div class="score-value">
                                <?php 
                                // Calculate original resume score (before test)
                                $resume_only = $app['test_score'] ? round($app['score'] / 0.6 - ($app['test_score'] * 0.4 / 0.6), 1) : $app['score'];
                                echo number_format($resume_only, 1); 
                                ?>%
                            </div>
                        </div>
                        
                        <div class="score-box test">
                            <div class="score-title">Test Score</div>
                            <div class="score-value">
                                <?php echo $app['test_score'] ? number_format($app['test_score'], 1) : 'N/A'; ?>%
                            </div>
                        </div>
                        
                        <div class="score-box combined">
                            <div class="score-title">Combined Score</div>
                            <div class="score-value"><?php echo number_format($app['score'], 1); ?>%</div>
                        </div>
                        
                        <div class="score-box rank">
                            <div class="score-title">Status</div>
                            <div style="margin-top: 8px;">
                                <span class="status-badge status-<?php echo $app['status']; ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $app['status'])); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <?php if ($app['test_status'] == 'completed'): ?>
                        <!-- Test Breakdown Preview -->
                        <div class="section-breakdown">
                            <h4 style="margin: 0 0 15px 0;">Test Performance Overview</h4>
                            <div class="section-row header">
                                <div>Section / Topic</div>
                                <div>Questions</div>
                                <div>Correct</div>
                                <div>Score</div>
                            </div>
                            
                            <?php if (!empty($numerical_scores)): ?>
                                <div style="font-weight: bold; margin-top: 10px; color: #667eea;">Numerical Reasoning</div>
                                <?php foreach ($numerical_scores as $score): ?>
                                    <div class="section-row">
                                        <div><?php echo ucwords(str_replace('_', ' ', $score['topic'])); ?></div>
                                        <div><?php echo $score['total_questions']; ?></div>
                                        <div><?php echo $score['correct_answers']; ?></div>
                                        <div>
                                            <span class="score-badge score-<?php 
                                                $pct = ($score['correct_answers'] / $score['total_questions']) * 100;
                                                echo $pct >= 70 ? 'high' : ($pct >= 50 ? 'medium' : 'low');
                                            ?>">
                                                <?php echo round($pct, 1); ?>%
                                            </span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            
                            <?php if (!empty($logical_scores)): ?>
                                <div style="font-weight: bold; margin-top: 10px; color: #667eea;">Logical Reasoning</div>
                                <?php foreach ($logical_scores as $score): ?>
                                    <div class="section-row">
                                        <div><?php echo ucwords(str_replace('_', ' ', $score['topic'])); ?></div>
                                        <div><?php echo $score['total_questions']; ?></div>
                                        <div><?php echo $score['correct_answers']; ?></div>
                                        <div>
                                            <span class="score-badge score-<?php 
                                                $pct = ($score['correct_answers'] / $score['total_questions']) * 100;
                                                echo $pct >= 70 ? 'high' : ($pct >= 50 ? 'medium' : 'low');
                                            ?>">
                                                <?php echo round($pct, 1); ?>%
                                            </span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            
                            <?php if (!empty($verbal_scores)): ?>
                                <div style="font-weight: bold; margin-top: 10px; color: #667eea;">Verbal Reasoning</div>
                                <?php foreach ($verbal_scores as $score): ?>
                                    <div class="section-row">
                                        <div><?php echo ucwords(str_replace('_', ' ', $score['topic'])); ?></div>
                                        <div><?php echo $score['total_questions']; ?></div>
                                        <div><?php echo $score['correct_answers']; ?></div>
                                        <div>
                                            <span class="score-badge score-<?php 
                                                $pct = ($score['correct_answers'] / $score['total_questions']) * 100;
                                                echo $pct >= 70 ? 'high' : ($pct >= 50 ? 'medium' : 'low');
                                            ?>">
                                                <?php echo round($pct, 1); ?>%
                                            </span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            
                            <?php if (!empty($technical_scores)): ?>
                                <div style="font-weight: bold; margin-top: 10px; color: #667eea;">Technical MCQ</div>
                                <?php foreach ($technical_scores as $score): ?>
                                    <div class="section-row">
                                        <div><?php echo ucwords(str_replace('_', ' ', $score['topic'])); ?></div>
                                        <div><?php echo $score['total_questions']; ?></div>
                                        <div><?php echo $score['correct_answers']; ?></div>
                                        <div>
                                            <span class="score-badge score-<?php 
                                                $pct = ($score['correct_answers'] / $score['total_questions']) * 100;
                                                echo $pct >= 70 ? 'high' : ($pct >= 50 ? 'medium' : 'low');
                                            ?>">
                                                <?php echo round($pct, 1); ?>%
                                            </span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="alert" style="background: #fff3cd; color: #856404;">
                            ⏳ Test not yet completed
                        </div>
                    <?php endif; ?>
                    
                    <!-- Action Buttons -->
                    <div class="action-btns">
                        <a href="<?php echo $app['resume_path']; ?>" target="_blank" class="btn btn-small">
                            📄 View Resume
                        </a>
                        <?php if ($app['status'] != 'shortlisted'): ?>
                            <form action="../scripts/shortlist_handler.php" method="POST" style="display: inline;">
                                <input type="hidden" name="job_id" value="<?php echo $job_id; ?>">
                                <input type="hidden" name="mode" value="manual">
                                <input type="hidden" name="selected[]" value="<?php echo $app['id']; ?>">
                                <button type="submit" class="btn btn-small" style="background: #28a745;">
                                    ✅ Shortlist Candidate
                                </button>
                            </form>
                        <?php else: ?>
                            <span style="color: #28a745; font-weight: bold;">✓ Already Shortlisted</span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
        
        <div style="margin-top: 30px;">
            <a href="recruiter_dashboard.php" class="btn btn-secondary">← Back to Dashboard</a>
        </div>
    </div>
</body>
</html>