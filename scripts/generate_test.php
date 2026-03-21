<?php
require_once '../config/db.php';

/**
 * Generate test questions based on job configuration
 * Returns array of questions for a specific application
 */
function generateTest($application_id) {
    global $pdo;
    
    // Get application details
    $stmt = $pdo->prepare("SELECT job_id, candidate_id FROM applications WHERE id = ?");
    $stmt->execute([$application_id]);
    $application = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$application) {
        return ['error' => 'Application not found'];
    }
    
    $job_id = $application['job_id'];
    
    // Get test configurations for this job
    $stmt = $pdo->prepare("SELECT * FROM test_configs WHERE job_id = ?");
    $stmt->execute([$job_id]);
    $test_configs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($test_configs)) {
        return ['error' => 'No tests configured for this job'];
    }
    
    $all_questions = [];
    $test_structure = [];
    
    foreach ($test_configs as $config) {
        $test_type = $config['test_type'];
        $config_id = $config['id'];
        
        // Get sections for this test config
        $stmt = $pdo->prepare("SELECT * FROM test_sections WHERE test_config_id = ?");
        $stmt->execute([$config_id]);
        $sections = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($sections as $section) {
            $section_id = $section['id'];
            $section_type = $section['section_type'];
            $section_name = $section['section_name'];
            
            // Get topics for this section
            $stmt = $pdo->prepare("SELECT * FROM test_topics WHERE section_id = ?");
            $stmt->execute([$section_id]);
            $topics = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $section_questions = [];
            
            foreach ($topics as $topic) {
                $topic_name = $topic['topic_name'];
                $easy_count = $topic['difficulty_easy'];
                $medium_count = $topic['difficulty_medium'];
                $hard_count = $topic['difficulty_hard'];
                
                // Get easy questions
                if ($easy_count > 0) {
                    $questions = getRandomQuestions($section_type, $topic_name, 'easy', $easy_count, $section_name);
                    $section_questions = array_merge($section_questions, $questions);
                }
                
                // Get medium questions
                if ($medium_count > 0) {
                    $questions = getRandomQuestions($section_type, $topic_name, 'medium', $medium_count, $section_name);
                    $section_questions = array_merge($section_questions, $questions);
                }
                
                // Get hard questions
                if ($hard_count > 0) {
                    $questions = getRandomQuestions($section_type, $topic_name, 'hard', $hard_count, $section_name);
                    $section_questions = array_merge($section_questions, $questions);
                }
            }
            
            // Shuffle questions within section
            shuffle($section_questions);
            
            $test_structure[] = [
                'test_type' => $test_type,
                'section_name' => $section_name,
                'section_type' => $section_type,
                'time_limit' => $section['time_limit_minutes'] ?? $config['total_time_minutes'],
                'questions' => $section_questions
            ];
            
            $all_questions = array_merge($all_questions, $section_questions);
        }
    }
    
    return [
        'application_id' => $application_id,
        'test_structure' => $test_structure,
        'total_questions' => count($all_questions),
        'timer_type' => $test_configs[0]['timer_type'],
        'total_time' => $test_configs[0]['total_time_minutes']
    ];
}

/**
 * Get random questions from question bank
 */
function getRandomQuestions($section_type, $topic, $difficulty, $count, $technology = null) {
    global $pdo;
    
    // Ensure count is an integer
    $count = intval($count);
    
    if ($count <= 0) {
        return [];
    }
    
    $sql = "SELECT * FROM question_bank 
            WHERE section_type = ? 
            AND topic = ? 
            AND difficulty = ?";
    
    $params = [$section_type, $topic, $difficulty];
    
    // For technical questions, filter by technology
    if ($section_type == 'technical' && $technology) {
        $sql .= " AND technology = ?";
        $params[] = $technology;
    }
    
    // LIMIT cannot use placeholder in MySQL/MariaDB, so we use intval() for safety
    $sql .= " ORDER BY RAND() LIMIT " . $count;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // If not enough questions found, log warning
    if (count($questions) < $count) {
        error_log("Warning: Only " . count($questions) . " questions found for $topic ($difficulty), needed $count");
    }
    
    return $questions;
}

/**
 * Create test attempt record
 */
function createTestAttempt($application_id, $test_config_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        INSERT INTO test_attempts (application_id, test_config_id, status) 
        VALUES (?, ?, 'in_progress')
    ");
    $stmt->execute([$application_id, $test_config_id]);
    
    return $pdo->lastInsertId();
}

/**
 * Save test questions for an attempt (for tracking)
 */
function saveTestQuestions($attempt_id, $questions) {
    global $pdo;
    
    foreach ($questions as $question) {
        // Store question ID mapping for this attempt
        // This allows us to track which questions were shown to which candidate
        $stmt = $pdo->prepare("
            INSERT INTO test_answers (attempt_id, question_id, selected_answer) 
            VALUES (?, ?, NULL)
        ");
        $stmt->execute([$attempt_id, $question['id']]);
    }
}
?>