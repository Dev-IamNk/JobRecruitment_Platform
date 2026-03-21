<!-- FILE: pages/shortlist_candidates.php -->
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

// Check if deadline has passed
$deadline = strtotime($job['application_deadline']);
$now = time();
$deadline_passed = ($deadline < $now);

// Get all scored applications
$stmt = $pdo->prepare("
    SELECT a.*, u.full_name as candidate_name, u.email as candidate_email 
    FROM applications a 
    JOIN users u ON a.candidate_id = u.id 
    WHERE a.job_id = ? AND a.status IN ('scored', 'test_completed', 'shortlisted')
    ORDER BY a.score DESC, a.applied_at ASC
");
$stmt->execute([$job_id]);
$applications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Count shortlisted
$shortlisted_count = 0;
$test_completed_count = 0;
foreach ($applications as $app) {
    if ($app['status'] == 'shortlisted') $shortlisted_count++;
    if ($app['status'] == 'test_completed') $test_completed_count++;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shortlist Candidates - <?php echo htmlspecialchars($job['title']); ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .shortlist-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 30px;
        }
        .shortlist-header h2 {
            color: white;
            margin: 0 0 10px 0;
        }
        .mode-selector {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .mode-buttons {
            display: flex;
            gap: 15px;
            margin-top: 15px;
        }
        .mode-btn {
            flex: 1;
            padding: 15px;
            border: 2px solid #e0e0e0;
            background: white;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
        }
        .mode-btn:hover {
            border-color: #667eea;
            background: #f0f4ff;
        }
        .mode-btn.active {
            border-color: #667eea;
            background: #667eea;
            color: white;
        }
        .mode-btn h3 {
            margin: 0 0 10px 0;
            font-size: 18px;
        }
        .mode-btn p {
            margin: 0;
            font-size: 14px;
            opacity: 0.8;
        }
        .auto-settings {
            display: none;
            margin-top: 20px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        .auto-settings.active {
            display: block;
        }
        .manual-selection {
            display: none;
        }
        .manual-selection.active {
            display: block;
        }
        .candidate-row {
            display: flex;
            align-items: center;
            padding: 15px;
            background: white;
            border-radius: 8px;
            margin-bottom: 10px;
            border: 2px solid #e0e0e0;
            transition: all 0.3s;
            cursor: pointer;
        }
        .candidate-row:hover {
            border-color: #667eea;
            box-shadow: 0 2px 10px rgba(102, 126, 234, 0.1);
        }
        .candidate-row.selected {
            border-color: #28a745;
            background: #f0fff4;
        }
        .candidate-row.already-shortlisted {
            background: #e7f3ff;
            border-color: #004085;
        }
        .candidate-row.expanded {
            flex-direction: column;
            align-items: flex-start;
        }
        .candidate-main {
            display: flex;
            align-items: center;
            width: 100%;
        }
        .checkbox-col {
            margin-right: 15px;
        }
        .checkbox-col input[type="checkbox"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
        }
        .rank-col {
            width: 60px;
            font-weight: bold;
        }
        .candidate-info {
            flex: 1;
        }
        .candidate-name {
            font-size: 16px;
            font-weight: bold;
            color: #333;
        }
        .candidate-email {
            font-size: 13px;
            color: #666;
        }
        .score-col {
            width: 100px;
            text-align: center;
        }
        .skills-col {
            width: 250px;
            font-size: 13px;
            color: #666;
        }
        .action-buttons {
            position: sticky;
            bottom: 0;
            background: white;
            padding: 20px;
            border-top: 2px solid #e0e0e0;
            margin: 30px -30px -30px -30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .selected-count {
            font-size: 16px;
            color: #666;
        }
        .resume-details {
            display: none;
            width: 100%;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 2px solid #e0e0e0;
        }
        .resume-details.active {
            display: block;
        }
        .resume-info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 15px;
        }
        .resume-info-item {
            background: #f8f9fa;
            padding: 12px;
            border-radius: 5px;
        }
        .resume-info-label {
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
            font-weight: 600;
            margin-bottom: 5px;
        }
        .resume-info-value {
            font-size: 14px;
            color: #333;
        }
        .skills-list {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        .skill-tag {
            background: #667eea;
            color: white;
            padding: 5px 12px;
            border-radius: 15px;
            font-size: 13px;
        }
        .view-resume-btn {
            background: #17a2b8;
            color: white;
            padding: 8px 16px;
            border-radius: 5px;
            text-decoration: none;
            display: inline-block;
            font-size: 14px;
            margin-right: 10px;
        }
        .view-resume-btn:hover {
            background: #138496;
        }
        .expand-btn {
            background: #6c757d;
            color: white;
            padding: 6px 12px;
            border-radius: 5px;
            font-size: 12px;
            border: none;
            cursor: pointer;
            margin-left: auto;
        }
        .expand-btn:hover {
            background: #5a6268;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="nav-bar">
            <h1>Shortlist Candidates</h1>
            <div class="nav-links">
                <a href="recruiter_dashboard.php">Dashboard</a>
                <a href="view_applications.php?job_id=<?php echo $job_id; ?>">View All Applications</a>
            </div>
        </div>
        
        <div class="shortlist-header">
            <h2><?php echo htmlspecialchars($job['title']); ?></h2>
            <p>Total Applications: <strong><?php echo count($applications); ?></strong> | 
               Already Shortlisted: <strong><?php echo $shortlisted_count; ?></strong> | 
               Test Completed: <strong><?php echo $test_completed_count; ?></strong></p>
        </div>
        
        <?php if (!$deadline_passed): ?>
            <div class="alert alert-error">
                ⚠️ Application deadline has not passed yet. Deadline: <?php echo date('M d, Y h:i A', $deadline); ?>
            </div>
            <a href="recruiter_dashboard.php" class="btn btn-secondary">← Back to Dashboard</a>
        <?php elseif (count($applications) == 0): ?>
            <div class="alert alert-error">
                No scored applications available for shortlisting.
            </div>
            <a href="recruiter_dashboard.php" class="btn btn-secondary">← Back to Dashboard</a>
        <?php else: ?>
            
            <!-- Mode Selector -->
            <div class="mode-selector">
                <h3>Choose Shortlisting Method</h3>
                <div class="mode-buttons">
                    <div class="mode-btn" id="manual-mode-btn" onclick="selectMode('manual')">
                        <h3>✋ Manual Selection</h3>
                        <p>Review and select candidates yourself</p>
                    </div>
                    <div class="mode-btn active" id="auto-mode-btn" onclick="selectMode('auto')">
                        <h3>🤖 Automatic Selection</h3>
                        <p>System selects top-scoring candidates</p>
                    </div>
                </div>
                
                <!-- Auto Settings -->
                <div class="auto-settings active" id="auto-settings">
                    <label style="display: block; margin-bottom: 10px; font-weight: 600;">
                        How many candidates to shortlist?
                    </label>
                    <input type="number" id="auto-count" value="<?php echo min($job['auto_shortlist_count'], count($applications)); ?>" 
                           min="1" max="<?php echo count($applications); ?>" 
                           style="width: 150px; padding: 10px; font-size: 16px;">
                    <p style="margin-top: 10px; color: #666; font-size: 14px;">
                        System will automatically select the top <?php echo min($job['auto_shortlist_count'], count($applications)); ?> candidates based on their scores.
                    </p>
                </div>
            </div>
            
            <!-- Manual Selection -->
            <div class="manual-selection" id="manual-selection">
                <h3>Select Candidates to Shortlist</h3>
                <p style="color: #666; margin-bottom: 20px;">Check the boxes next to candidates you want to shortlist</p>
                
                <form id="manual-form" method="POST" action="../scripts/shortlist_handler.php">
                    <input type="hidden" name="job_id" value="<?php echo $job_id; ?>">
                    <input type="hidden" name="mode" value="manual">
                    
                    <?php foreach ($applications as $app): ?>
                        <div class="candidate-row <?php echo $app['status'] == 'shortlisted' ? 'already-shortlisted' : ''; ?>" id="candidate-<?php echo $app['id']; ?>">
                            <div class="candidate-main">
                                <div class="checkbox-col">
                                    <?php if ($app['status'] == 'shortlisted'): ?>
                                        <input type="checkbox" disabled checked>
                                    <?php else: ?>
                                        <input type="checkbox" name="selected[]" value="<?php echo $app['id']; ?>" 
                                               onchange="updateCount()" onclick="event.stopPropagation()">
                                    <?php endif; ?>
                                </div>
                                
                                <div class="rank-col">
                                    <span class="rank-badge rank-<?php echo min($app['rank'], 3); ?>">
                                        #<?php echo $app['rank']; ?>
                                    </span>
                                </div>
                                
                                <div class="candidate-info">
                                    <div class="candidate-name"><?php echo htmlspecialchars($app['candidate_name']); ?></div>
                                    <div class="candidate-email"><?php echo htmlspecialchars($app['candidate_email']); ?></div>
                                </div>
                                
                                <div class="score-col">
                                    <span class="score-badge score-<?php 
                                        echo $app['score'] >= 70 ? 'high' : ($app['score'] >= 50 ? 'medium' : 'low'); 
                                    ?>">
                                        <?php echo number_format($app['score'], 1); ?>%
                                    </span>
                                </div>
                                
                                <div class="skills-col">
                                    <?php 
                                    $skills = explode(', ', $app['extracted_skills']);
                                    echo htmlspecialchars(implode(', ', array_slice($skills, 0, 4)));
                                    if (count($skills) > 4) echo '...';
                                    ?>
                                </div>
                                
                                <?php if ($app['status'] == 'shortlisted'): ?>
                                    <span style="color: #004085; font-weight: bold; font-size: 12px;">✓ SHORTLISTED</span>
                                <?php else: ?>
                                    <button class="expand-btn" onclick="toggleResume(<?php echo $app['id']; ?>); event.stopPropagation();">
                                        👁️ View Resume
                                    </button>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Resume Details (Hidden by default) -->
                            <div class="resume-details" id="resume-<?php echo $app['id']; ?>">
                                <div class="resume-info-grid">
                                    <div class="resume-info-item">
                                        <div class="resume-info-label">Application Date</div>
                                        <div class="resume-info-value"><?php echo date('M d, Y h:i A', strtotime($app['applied_at'])); ?></div>
                                    </div>
                                    
                                    <div class="resume-info-item">
                                        <div class="resume-info-label">Resume Score</div>
                                        <div class="resume-info-value">
                                            <span class="score-badge score-<?php 
                                                echo $app['score'] >= 70 ? 'high' : ($app['score'] >= 50 ? 'medium' : 'low'); 
                                            ?>">
                                                <?php echo number_format($app['score'], 1); ?>%
                                            </span>
                                            (Rank #<?php echo $app['rank']; ?> out of <?php echo count($applications); ?>)
                                        </div>
                                    </div>
                                    
                                    <div class="resume-info-item" style="grid-column: 1 / -1;">
                                        <div class="resume-info-label">Extracted Skills (<?php echo count($skills); ?>)</div>
                                        <div class="skills-list">
                                            <?php foreach ($skills as $skill): ?>
                                                <span class="skill-tag"><?php echo htmlspecialchars($skill); ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    
                                    <?php if ($app['cover_letter']): ?>
                                    <div class="resume-info-item" style="grid-column: 1 / -1;">
                                        <div class="resume-info-label">Cover Letter</div>
                                        <div class="resume-info-value" style="line-height: 1.6;">
                                            <?php echo nl2br(htmlspecialchars($app['cover_letter'])); ?>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div style="margin-top: 15px;">
                                    <a href="<?php echo $app['resume_path']; ?>" target="_blank" class="view-resume-btn">
                                        📄 Download Resume
                                    </a>
                                    <?php if ($app['status'] != 'shortlisted'): ?>
                                        <button class="btn" style="padding: 8px 16px; font-size: 14px;" 
                                                onclick="quickShortlist(<?php echo $app['id']; ?>); event.stopPropagation();">
                                            ✅ Shortlist This Candidate
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </form>
            </div>
            
            <!-- Automatic Selection Preview -->
            <div class="manual-selection active" id="auto-preview">
                <h3>Top Candidates (Auto-Selection)</h3>
                <p style="color: #666; margin-bottom: 20px;">
                    These candidates will be automatically shortlisted based on their scores
                </p>
                
                <?php 
                $preview_count = min($job['auto_shortlist_count'], count($applications));
                for ($i = 0; $i < count($applications); $i++): 
                    $app = $applications[$i];
                    $will_be_selected = ($i < $preview_count && $app['status'] != 'shortlisted');
                    $skills = explode(', ', $app['extracted_skills']);
                ?>
                    <div class="candidate-row <?php echo $will_be_selected ? 'selected' : ''; ?> <?php echo $app['status'] == 'shortlisted' ? 'already-shortlisted' : ''; ?>" id="auto-candidate-<?php echo $app['id']; ?>">
                        <div class="candidate-main">
                            <div class="checkbox-col">
                                <?php if ($will_be_selected || $app['status'] == 'shortlisted'): ?>
                                    <span style="font-size: 20px;">✓</span>
                                <?php else: ?>
                                    <span style="font-size: 20px; opacity: 0.3;">○</span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="rank-col">
                                <span class="rank-badge rank-<?php echo min($app['rank'], 3); ?>">
                                    #<?php echo $app['rank']; ?>
                                </span>
                            </div>
                            
                            <div class="candidate-info">
                                <div class="candidate-name"><?php echo htmlspecialchars($app['candidate_name']); ?></div>
                                <div class="candidate-email"><?php echo htmlspecialchars($app['candidate_email']); ?></div>
                            </div>
                            
                            <div class="score-col">
                                <span class="score-badge score-<?php 
                                    echo $app['score'] >= 70 ? 'high' : ($app['score'] >= 50 ? 'medium' : 'low'); 
                                ?>">
                                    <?php echo number_format($app['score'], 1); ?>%
                                </span>
                            </div>
                            
                            <div class="skills-col">
                                <?php 
                                echo htmlspecialchars(implode(', ', array_slice($skills, 0, 4)));
                                if (count($skills) > 4) echo '...';
                                ?>
                            </div>
                            
                            <?php if ($app['status'] == 'shortlisted'): ?>
                                <span style="color: #004085; font-weight: bold; font-size: 12px;">✓ ALREADY SHORTLISTED</span>
                            <?php else: ?>
                                <button class="expand-btn" onclick="toggleResumeAuto(<?php echo $app['id']; ?>); event.stopPropagation();">
                                    👁️ View Resume
                                </button>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Resume Details (Hidden by default) -->
                        <div class="resume-details" id="auto-resume-<?php echo $app['id']; ?>">
                            <div class="resume-info-grid">
                                <div class="resume-info-item">
                                    <div class="resume-info-label">Application Date</div>
                                    <div class="resume-info-value"><?php echo date('M d, Y h:i A', strtotime($app['applied_at'])); ?></div>
                                </div>
                                
                                <div class="resume-info-item">
                                    <div class="resume-info-label">Resume Score</div>
                                    <div class="resume-info-value">
                                        <span class="score-badge score-<?php 
                                            echo $app['score'] >= 70 ? 'high' : ($app['score'] >= 50 ? 'medium' : 'low'); 
                                        ?>">
                                            <?php echo number_format($app['score'], 1); ?>%
                                        </span>
                                        (Rank #<?php echo $app['rank']; ?> out of <?php echo count($applications); ?>)
                                    </div>
                                </div>
                                
                                <div class="resume-info-item" style="grid-column: 1 / -1;">
                                    <div class="resume-info-label">Extracted Skills (<?php echo count($skills); ?>)</div>
                                    <div class="skills-list">
                                        <?php foreach ($skills as $skill): ?>
                                            <span class="skill-tag"><?php echo htmlspecialchars($skill); ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                
                                <?php if ($app['cover_letter']): ?>
                                <div class="resume-info-item" style="grid-column: 1 / -1;">
                                    <div class="resume-info-label">Cover Letter</div>
                                    <div class="resume-info-value" style="line-height: 1.6;">
                                        <?php echo nl2br(htmlspecialchars($app['cover_letter'])); ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <div style="margin-top: 15px;">
                                <a href="<?php echo $app['resume_path']; ?>" target="_blank" class="view-resume-btn">
                                    📄 Download Resume
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endfor; ?>
            </div>
            
            <!-- Action Buttons -->
            <div class="action-buttons">
                <div class="selected-count" id="selected-count">
                    0 candidates selected
                </div>
                <div>
                    <a href="recruiter_dashboard.php" class="btn btn-secondary">Cancel</a>
                    <button type="button" class="btn" id="shortlist-btn" onclick="submitShortlist()">
                        Shortlist Selected Candidates
                    </button>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <script>
        let currentMode = 'auto';
        
        function selectMode(mode) {
            currentMode = mode;
            
            // Update button styles
            document.getElementById('manual-mode-btn').classList.remove('active');
            document.getElementById('auto-mode-btn').classList.remove('active');
            
            if (mode === 'manual') {
                document.getElementById('manual-mode-btn').classList.add('active');
                document.getElementById('auto-settings').classList.remove('active');
                document.getElementById('manual-selection').classList.add('active');
                document.getElementById('auto-preview').classList.remove('active');
            } else {
                document.getElementById('auto-mode-btn').classList.add('active');
                document.getElementById('auto-settings').classList.add('active');
                document.getElementById('manual-selection').classList.remove('active');
                document.getElementById('auto-preview').classList.add('active');
            }
            
            updateCount();
        }
        
        function toggleResume(id) {
            const resumeDiv = document.getElementById('resume-' + id);
            const candidateRow = document.getElementById('candidate-' + id);
            
            if (resumeDiv.classList.contains('active')) {
                resumeDiv.classList.remove('active');
                candidateRow.classList.remove('expanded');
            } else {
                // Close all other resume details
                document.querySelectorAll('.resume-details').forEach(div => {
                    div.classList.remove('active');
                });
                document.querySelectorAll('.candidate-row').forEach(row => {
                    row.classList.remove('expanded');
                });
                
                resumeDiv.classList.add('active');
                candidateRow.classList.add('expanded');
            }
        }
        
        function toggleResumeAuto(id) {
            const resumeDiv = document.getElementById('auto-resume-' + id);
            const candidateRow = document.getElementById('auto-candidate-' + id);
            
            if (resumeDiv.classList.contains('active')) {
                resumeDiv.classList.remove('active');
                candidateRow.classList.remove('expanded');
            } else {
                // Close all other resume details
                document.querySelectorAll('.resume-details').forEach(div => {
                    div.classList.remove('active');
                });
                document.querySelectorAll('.candidate-row').forEach(row => {
                    row.classList.remove('expanded');
                });
                
                resumeDiv.classList.add('active');
                candidateRow.classList.add('expanded');
            }
        }
        
        function quickShortlist(appId) {
            if (confirm('Shortlist this candidate immediately?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '../scripts/shortlist_handler.php';
                
                const jobIdInput = document.createElement('input');
                jobIdInput.type = 'hidden';
                jobIdInput.name = 'job_id';
                jobIdInput.value = '<?php echo $job_id; ?>';
                form.appendChild(jobIdInput);
                
                const modeInput = document.createElement('input');
                modeInput.type = 'hidden';
                modeInput.name = 'mode';
                modeInput.value = 'manual';
                form.appendChild(modeInput);
                
                const selectedInput = document.createElement('input');
                selectedInput.type = 'hidden';
                selectedInput.name = 'selected[]';
                selectedInput.value = appId;
                form.appendChild(selectedInput);
                
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function updateCount() {
            if (currentMode === 'manual') {
                const checked = document.querySelectorAll('input[name="selected[]"]:checked').length;
                document.getElementById('selected-count').textContent = checked + ' candidate' + (checked !== 1 ? 's' : '') + ' selected';
            } else {
                const count = document.getElementById('auto-count').value;
                document.getElementById('selected-count').textContent = count + ' candidate' + (count !== '1' ? 's' : '') + ' will be shortlisted';
            }
        }
        
        function submitShortlist() {
            if (currentMode === 'manual') {
                const form = document.getElementById('manual-form');
                const checked = document.querySelectorAll('input[name="selected[]"]:checked').length;
                
                if (checked === 0) {
                    alert('Please select at least one candidate to shortlist.');
                    return;
                }
                
                if (confirm('Shortlist ' + checked + ' candidate(s)?')) {
                    form.submit();
                }
            } else {
                const count = document.getElementById('auto-count').value;
                
                if (confirm('Automatically shortlist top ' + count + ' candidates based on their scores?')) {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = '../scripts/shortlist_handler.php';
                    
                    const jobIdInput = document.createElement('input');
                    jobIdInput.type = 'hidden';
                    jobIdInput.name = 'job_id';
                    jobIdInput.value = '<?php echo $job_id; ?>';
                    form.appendChild(jobIdInput);
                    
                    const modeInput = document.createElement('input');
                    modeInput.type = 'hidden';
                    modeInput.name = 'mode';
                    modeInput.value = 'auto';
                    form.appendChild(modeInput);
                    
                    const countInput = document.createElement('input');
                    countInput.type = 'hidden';
                    countInput.name = 'count';
                    countInput.value = count;
                    form.appendChild(countInput);
                    
                    document.body.appendChild(form);
                    form.submit();
                }
            }
        }
        
        // Initialize count
        updateCount();
        
        // Update count when auto-count changes
        document.getElementById('auto-count').addEventListener('input', updateCount);
    </script>
</body>
</html>