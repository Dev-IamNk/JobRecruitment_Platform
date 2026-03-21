<?php
require_once '../config/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $application_id = intval($_POST['application_id']);
    $answers_json = $_POST['answers'];
    $time_taken = intval($_POST['time_taken']);
    
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
    
    $attempt_id = $attempt['id'];
    $correct_count = 0;
    $total_questions = 0;
    
    // Process each answer
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
            
            // Update answer in database
            $stmt = $pdo->prepare("
                UPDATE test_answers 
                SET selected_answer = ?, is_correct = ? 
                WHERE attempt_id = ? AND question_id = ?
            ");
            $stmt->execute([$selected_answer, $is_correct, $attempt_id, $question_id]);
            
            $total_questions++;
        }
    }
    
    // Calculate score
    $score = ($total_questions > 0) ? round(($correct_count / $total_questions) * 100, 2) : 0;
    
    // Update test attempt
    $stmt = $pdo->prepare("
        UPDATE test_attempts 
        SET total_score = ?, 
            status = 'completed',
            submitted_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$score, $attempt_id]);
    
    // Update application status
    $stmt = $pdo->prepare("
        UPDATE applications 
        SET status = 'test_completed'
        WHERE id = ?
    ");
    $stmt->execute([$application_id]);
    
    echo json_encode([
        'success' => true,
        'score' => $score,
        'correct' => $correct_count,
        'total' => $total_questions,
        'time_taken' => $time_taken
    ]);
    
} else {
    echo json_encode(['error' => 'Invalid request method']);
}
?>