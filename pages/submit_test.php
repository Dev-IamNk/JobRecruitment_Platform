<!-- FILE: scripts/shortlist_handler.php -->
<?php
require_once '../config/db.php';
redirectIfNotLoggedIn();

if ($_SERVER['REQUEST_METHOD'] == 'POST' && getUserType() == 'recruiter') {
    $job_id = intval($_POST['job_id']);
    $mode = $_POST['mode']; // 'manual' or 'auto'
    $recruiter_id = $_SESSION['user_id'];
    
    // Verify job belongs to this recruiter
    $stmt = $pdo->prepare("SELECT * FROM jobs WHERE id = ? AND recruiter_id = ?");
    $stmt->execute([$job_id, $recruiter_id]);
    $job = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$job) {
        header('Location: ../pages/recruiter_dashboard.php?error=invalid_job');
        exit();
    }
    
    // Check if deadline has passed
    if (strtotime($job['application_deadline']) > time()) {
        header('Location: ../pages/shortlist_candidates.php?job_id='.$job_id.'&error=deadline_not_passed');
        exit();
    }
    
    $shortlisted_count = 0;
    
    if ($mode === 'manual') {
        // Manual shortlisting
        $selected = $_POST['selected'] ?? [];
        
        if (empty($selected)) {
            header('Location: ../pages/shortlist_candidates.php?job_id='.$job_id.'&error=no_selection');
            exit();
        }
        
        // Update selected applications to 'shortlisted'
        foreach ($selected as $app_id) {
            $stmt = $pdo->prepare("
                UPDATE applications 
                SET status = 'shortlisted' 
                WHERE id = ? AND job_id = ? AND status IN ('scored', 'test_completed')
            ");
            if ($stmt->execute([$app_id, $job_id])) {
                $shortlisted_count++;
            }
        }
        
    } elseif ($mode === 'auto') {
        // Automatic shortlisting
        $count = intval($_POST['count']);
        
        if ($count < 1) {
            header('Location: ../pages/shortlist_candidates.php?job_id='.$job_id.'&error=invalid_count');
            exit();
        }
        
        // Get top N candidates by score
        // Note: LIMIT cannot use prepared statement placeholder in some MySQL/MariaDB versions
        // So we validate $count as integer and use it directly
        $stmt = $pdo->prepare("
            SELECT id FROM applications 
            WHERE job_id = ? AND status = 'scored'
            ORDER BY score DESC, applied_at ASC
            LIMIT " . $count
        );
        $stmt->execute([$job_id]);
        $top_candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Update them to shortlisted
        foreach ($top_candidates as $candidate) {
            $stmt = $pdo->prepare("UPDATE applications SET status = 'shortlisted' WHERE id = ?");
            if ($stmt->execute([$candidate['id']])) {
                $shortlisted_count++;
            }
        }
    }
    
    // Redirect with success message
  //  header('Location: ../pages/recruiter_dashboard.php?success=shortlisted&count='.$shortlisted_count.'&job_id='.$job_id);
    // Instead of redirecting to dashboard or results, go to coding test
header("Location: ../pages/coding_test.php?application_id=" . $application_id);
exit;
    
} else {
    header('Location: ../pages/recruiter_dashboard.php');
}
?>