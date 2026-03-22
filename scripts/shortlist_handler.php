<?php
// FILE: scripts/shortlist_handler.php
require_once '../config/db.php';
require_once '../scripts/email_helper.php'; // ✅ NEW: for email queue

redirectIfNotLoggedIn();

if ($_SERVER['REQUEST_METHOD'] == 'POST' && getUserType() == 'recruiter') {
    $job_id       = intval($_POST['job_id']);
    $mode         = $_POST['mode']; // 'manual' or 'auto'
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
        header('Location: ../pages/shortlist_candidates.php?job_id=' . $job_id . '&error=deadline_not_passed');
        exit();
    }

    $shortlisted_count = 0;

    if ($mode === 'manual') {
        // Manual shortlisting
        $selected = $_POST['selected'] ?? [];

        if (empty($selected)) {
            header('Location: ../pages/shortlist_candidates.php?job_id=' . $job_id . '&error=no_selection');
            exit();
        }

        foreach ($selected as $app_id) {
            $app_id = intval($app_id);
            $stmt   = $pdo->prepare("
                UPDATE applications 
                SET status = 'shortlisted' 
                WHERE id = ? AND job_id = ? AND status IN ('scored', 'test_completed')
            ");
            if ($stmt->execute([$app_id, $job_id]) && $stmt->rowCount() > 0) {
                $shortlisted_count++;

                // ✅ NEW: Queue test link email for this candidate
                queueTestLinkEmail($pdo, $app_id);
            }
        }

    } elseif ($mode === 'auto') {
        // Automatic shortlisting
        $count = intval($_POST['count']);

        if ($count < 1) {
            header('Location: ../pages/shortlist_candidates.php?job_id=' . $job_id . '&error=invalid_count');
            exit();
        }

        // Get top N candidates by score
        $stmt = $pdo->prepare("
            SELECT id FROM applications 
            WHERE job_id = ? AND status = 'scored'
            ORDER BY score DESC, applied_at ASC
            LIMIT " . $count
        );
        $stmt->execute([$job_id]);
        $top_candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($top_candidates as $candidate) {
            $stmt = $pdo->prepare("UPDATE applications SET status = 'shortlisted' WHERE id = ?");
            if ($stmt->execute([$candidate['id']]) && $stmt->rowCount() > 0) {
                $shortlisted_count++;

                // ✅ NEW: Queue test link email for this candidate
                queueTestLinkEmail($pdo, $candidate['id']);
            }
        }
    }

    // Redirect with success message
    header('Location: ../pages/recruiter_dashboard.php?success=shortlisted&count=' . $shortlisted_count . '&job_id=' . $job_id);

} else {
    header('Location: ../pages/recruiter_dashboard.php');
}
?>
