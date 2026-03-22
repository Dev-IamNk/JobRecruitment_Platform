<?php
// scripts/mark_email_sent.php
// UiPath calls this after successfully sending each email
// POST params: email_id, status (sent or failed)

require_once '../config/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email_id = intval($_POST['email_id'] ?? 0);
    $status   = in_array($_POST['status'] ?? '', ['sent', 'failed']) ? $_POST['status'] : 'sent';

    if (!$email_id) {
        echo json_encode(['error' => 'Invalid email_id']);
        exit;
    }

    $upd = $pdo->prepare("
        UPDATE email_queue 
        SET status = ?, sent_at = NOW() 
        WHERE id = ?
    ");
    $upd->execute([$status, $email_id]);

    // Also update applications.email_sent flag
    $app_upd = $pdo->prepare("
        UPDATE applications a
        JOIN email_queue eq ON eq.application_id = a.id
        SET a.email_sent = 1
        WHERE eq.id = ?
    ");
    $app_upd->execute([$email_id]);

    echo json_encode(['success' => true, 'email_id' => $email_id, 'status' => $status]);
} else {
    echo json_encode(['error' => 'POST required']);
}
?>
