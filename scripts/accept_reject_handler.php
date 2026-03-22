<?php
// scripts/accept_reject_handler.php
require_once '../config/db.php';
require_once '../scripts/email_helper.php';

header('Content-Type: application/json');

redirectIfNotLoggedIn();
if (getUserType() != 'recruiter') {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'POST required']);
    exit;
}

$application_id = intval($_POST['application_id'] ?? 0);
$decision       = $_POST['decision'] ?? ''; // 'selected' or 'rejected'

if (!$application_id || !in_array($decision, ['selected', 'rejected'])) {
    echo json_encode(['error' => 'Invalid parameters']);
    exit;
}

// Verify this job belongs to the recruiter
$check = $pdo->prepare("
    SELECT a.id FROM applications a
    JOIN jobs j ON j.id = a.job_id
    WHERE a.id = ? AND j.recruiter_id = ?
");
$check->execute([$application_id, $_SESSION['user_id']]);
if (!$check->fetch()) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Update application status
$upd = $pdo->prepare("UPDATE applications SET status = ? WHERE id = ?");
$upd->execute([$decision, $application_id]);

// Queue the appropriate email
if ($decision === 'selected') {
    queueSelectionEmail($pdo, $application_id);
} else {
    queueRejectionEmail($pdo, $application_id);
}

echo json_encode([
    'success'  => true,
    'decision' => $decision,
    'message'  => $decision === 'selected'
        ? 'Candidate selected! Selection email queued.'
        : 'Candidate rejected. Rejection email queued.'
]);
?>
