<?php
// scripts/export_email_queue.php
// UiPath calls this URL to get pending emails as JSON
// After reading, UiPath calls mark_email_sent.php to update status

require_once '../config/db.php';

header('Content-Type: application/json');

// Fetch all pending emails
$q = $pdo->prepare("
    SELECT id, recipient_email, recipient_name, email_type, subject, body_html
    FROM email_queue
    WHERE status = 'pending'
    ORDER BY created_at ASC
");
$q->execute();
$emails = $q->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'count'  => count($emails),
    'emails' => $emails
]);
?>
