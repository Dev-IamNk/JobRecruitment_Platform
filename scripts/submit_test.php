<?php
require_once '../config/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $application_id = intval($_POST['application_id']);
    $answers_json   = $_POST['answers'];
    $time_taken     = intval($_POST['time_taken']);

    $answers = json_decode($answers_json, true);

    if (!$answers) {
        echo json_encode(['error' => 'Invalid answers format']);
        exit();
    }

    // Get test attempt
    $stmt = $pdo->prepare("SELECT * FROM test_attempts WHERE application_id = ? AND status = 'in_progress'");
    $stmt->execute([$application_id]);
    $attempt = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$attempt) {
        echo json_encode(['error' => 'Test attempt not found']);
        exit();
    }

    $attempt_id    = $attempt['id'];
    $correct_count = 0;

    // ✅ CHANGED: Get TOTAL questions assigned to this attempt from test_answers table
    // This includes ALL questions (answered + unanswered/skipped)
    $total_stmt = $pdo->prepare("SELECT COUNT(*) as total FROM test_answers WHERE attempt_id = ?");
    $total_stmt->execute([$attempt_id]);
    $total_row         = $total_stmt->fetch(PDO::FETCH_ASSOC);
    $total_in_test     = intval($total_row['total']); // total questions in test

    // Process each answered question
    foreach ($answers as $question_id => $selected_answer) {
        // Get correct answer
        $stmt = $pdo->prepare("SELECT correct_answer FROM question_bank WHERE id = ?");
        $stmt->execute([$question_id]);
        $question = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($question) {
            $is_correct = ($selected_answer == $question['correct_answer']) ? 1 : 0;

            if ($is_correct) {
                $correct_count++;
            }

            // Update answered question in test_answers
            $stmt = $pdo->prepare("
                UPDATE test_answers 
                SET selected_answer = ?, is_correct = ? 
                WHERE attempt_id = ? AND question_id = ?
            ");
            $stmt->execute([$selected_answer, $is_correct, $attempt_id, $question_id]);
        }
    }

    // ✅ CHANGED: Score = correct / TOTAL questions in test (not just attended)
    // Skipped questions count as wrong
    $score = ($total_in_test > 0)
        ? round(($correct_count / $total_in_test) * 100, 2)
        : 0;

    // How many were actually attended (submitted an answer)
    $attended = count($answers);
    $skipped  = $total_in_test - $attended;

    // Update test attempt
    $stmt = $pdo->prepare("
        UPDATE test_attempts 
        SET total_score = ?, 
            max_score   = 100,
            round_type  = 'aptitude_technical',
            status      = 'completed',
            submitted_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$score, $attempt_id]);

    // Update application status
    $stmt = $pdo->prepare("
        UPDATE applications 
        SET status              = 'test_completed',
            aptitude_tech_score = ?
        WHERE id = ?
    ");
    $stmt->execute([$score, $application_id]);

    // Return result with redirect to coding test
    echo json_encode([
        'success'    => true,
        'score'      => $score,
        'correct'    => $correct_count,
        'total'      => $total_in_test,  // ✅ now shows real total
        'attended'   => $attended,        // ✅ how many they answered
        'skipped'    => $skipped,         // ✅ how many they skipped
        'time_taken' => $time_taken,
        'redirect'   => '../pages/coding_test.php?application_id=' . $application_id
    ]);

} else {
    echo json_encode(['error' => 'Invalid request method']);
}
?>