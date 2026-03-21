<!-- FILE: scripts/job_handler.php -->
<?php
require_once '../config/db.php';
redirectIfNotLoggedIn();

if ($_SERVER['REQUEST_METHOD'] == 'POST' && getUserType() == 'recruiter') {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $required_skills = trim($_POST['required_skills']);
    $location = trim($_POST['location']);
    $salary_range = trim($_POST['salary_range']);
    
    // NEW FIELDS
    $application_deadline = $_POST['application_deadline'];
    $shortlisting_mode = $_POST['shortlisting_mode'];
    $auto_shortlist_count = intval($_POST['auto_shortlist_count']);
    $interview_date = $_POST['interview_date'] ?? null;
    $interview_link = trim($_POST['interview_link']);
    
    $recruiter_id = $_SESSION['user_id'];
    
    // Validate required fields
    if (empty($title) || empty($description) || empty($required_skills) || empty($application_deadline)) {
        header('Location: ../pages/post_job.php?error=empty');
        exit();
    }
    
    // Insert job with new fields
    $stmt = $pdo->prepare("
        INSERT INTO jobs (
            recruiter_id, 
            title, 
            description, 
            required_skills, 
            location, 
            salary_range,
            application_deadline,
            shortlisting_mode,
            auto_shortlist_count,
            interview_date,
            interview_link
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    if ($stmt->execute([
        $recruiter_id, 
        $title, 
        $description, 
        $required_skills, 
        $location, 
        $salary_range,
        $application_deadline,
        $shortlisting_mode,
        $auto_shortlist_count,
        $interview_date,
        $interview_link
    ])) {
        $job_id = $pdo->lastInsertId();
        
        // Save test configurations
        saveTestConfigurations($job_id);
        
        header('Location: ../pages/recruiter_dashboard.php?success=posted');
    } else {
        header('Location: ../pages/post_job.php?error=failed');
    }
}

/**
 * Save test configurations for the job
 */
function saveTestConfigurations($job_id) {
    global $pdo;
    
    // ===== APTITUDE TEST =====
    if (isset($_POST['enable_aptitude']) && $_POST['enable_aptitude'] == '1') {
        $timer_type = $_POST['aptitude_timer_type'] ?? 'overall';
        $total_time = ($timer_type == 'overall') 
            ? intval($_POST['aptitude_overall_time']) 
            : intval($_POST['aptitude_sectional_time']) * 3; // 3 sections
        
        // Create test config
        $stmt = $pdo->prepare("INSERT INTO test_configs (job_id, test_type, timer_type, total_time_minutes) VALUES (?, 'aptitude', ?, ?)");
        $stmt->execute([$job_id, $timer_type, $total_time]);
        $test_config_id = $pdo->lastInsertId();
        
        // Save numerical topics
        saveAptitudeSection($test_config_id, 'numerical', 
            $_POST['numerical_topic'] ?? [], 
            $_POST['numerical_easy'] ?? [], 
            $_POST['numerical_medium'] ?? [], 
            $_POST['numerical_hard'] ?? []);
        
        // Save logical topics
        saveAptitudeSection($test_config_id, 'logical', 
            $_POST['logical_topic'] ?? [], 
            $_POST['logical_easy'] ?? [], 
            $_POST['logical_medium'] ?? [], 
            $_POST['logical_hard'] ?? []);
        
        // Save verbal topics
        saveAptitudeSection($test_config_id, 'verbal', 
            $_POST['verbal_topic'] ?? [], 
            $_POST['verbal_easy'] ?? [], 
            $_POST['verbal_medium'] ?? [], 
            $_POST['verbal_hard'] ?? []);
    }
    
    // ===== TECHNICAL TEST =====
    if (isset($_POST['enable_technical']) && $_POST['enable_technical'] == '1') {
        $tech_time = intval($_POST['technical_time']);
        
        $stmt = $pdo->prepare("INSERT INTO test_configs (job_id, test_type, timer_type, total_time_minutes) VALUES (?, 'technical', 'overall', ?)");
        $stmt->execute([$job_id, $tech_time]);
        $test_config_id = $pdo->lastInsertId();
        
        // Create technical section
        $stmt = $pdo->prepare("INSERT INTO test_sections (test_config_id, section_name, section_type, time_limit_minutes, total_questions) VALUES (?, ?, 'technical', ?, ?)");
        
        $technology = $_POST['technical_technology'];
        $total_questions = 0;
        
        // Count total questions
        foreach ($_POST['technical_easy'] as $key => $easy) {
            $total_questions += intval($easy) + intval($_POST['technical_medium'][$key]) + intval($_POST['technical_hard'][$key]);
        }
        
        $stmt->execute([$test_config_id, $technology, $tech_time, $total_questions]);
        $section_id = $pdo->lastInsertId();
        
        // Save technical topics
        $topics = $_POST['technical_topic'] ?? [];
        $easy = $_POST['technical_easy'] ?? [];
        $medium = $_POST['technical_medium'] ?? [];
        $hard = $_POST['technical_hard'] ?? [];
        
        foreach ($topics as $key => $topic) {
            $stmt = $pdo->prepare("INSERT INTO test_topics (section_id, topic_name, num_questions, difficulty_easy, difficulty_medium, difficulty_hard) VALUES (?, ?, ?, ?, ?, ?)");
            $num_q = intval($easy[$key]) + intval($medium[$key]) + intval($hard[$key]);
            $stmt->execute([$section_id, $topic, $num_q, intval($easy[$key]), intval($medium[$key]), intval($hard[$key])]);
        }
    }
    
    // ===== CODING TEST =====
    if (isset($_POST['enable_coding']) && $_POST['enable_coding'] == '1') {
        $num_problems = intval($_POST['coding_num_problems']);
        $time_per_problem = intval($_POST['coding_time_per_problem']);
        $total_time = $num_problems * $time_per_problem;
        
        $stmt = $pdo->prepare("INSERT INTO test_configs (job_id, test_type, timer_type, total_time_minutes) VALUES (?, 'coding', 'overall', ?)");
        $stmt->execute([$job_id, $total_time]);
        $test_config_id = $pdo->lastInsertId();
        
        // Create coding section
        $stmt = $pdo->prepare("INSERT INTO test_sections (test_config_id, section_name, section_type, time_limit_minutes, total_questions) VALUES (?, 'Coding Problems', 'coding', ?, ?)");
        $stmt->execute([$test_config_id, $total_time, $num_problems]);
        $section_id = $pdo->lastInsertId();
        
        // Save coding problem configurations
        $difficulties = $_POST['coding_difficulty'] ?? [];
        $topics = $_POST['coding_topic'] ?? [];
        
        foreach ($difficulties as $key => $difficulty) {
            $stmt = $pdo->prepare("INSERT INTO test_topics (section_id, topic_name, difficulty_easy, difficulty_medium, difficulty_hard) VALUES (?, ?, ?, ?, ?)");
            $easy = ($difficulty == 'easy') ? 1 : 0;
            $medium = ($difficulty == 'medium') ? 1 : 0;
            $hard = ($difficulty == 'hard') ? 1 : 0;
            $stmt->execute([$section_id, $topics[$key], $easy, $medium, $hard]);
        }
    }
}

/**
 * Save aptitude section topics
 */
function saveAptitudeSection($test_config_id, $section_type, $topics, $easy, $medium, $hard) {
    global $pdo;
    
    if (empty($topics)) return;
    
    $section_name = ucfirst($section_type) . ' Reasoning';
    $total_questions = 0;
    
    // Count total questions for this section
    foreach ($easy as $key => $e) {
        $total_questions += intval($e) + intval($medium[$key]) + intval($hard[$key]);
    }
    
    // Create section
    $stmt = $pdo->prepare("INSERT INTO test_sections (test_config_id, section_name, section_type, total_questions) VALUES (?, ?, ?, ?)");
    $stmt->execute([$test_config_id, $section_name, $section_type, $total_questions]);
    $section_id = $pdo->lastInsertId();
    
    // Save topics
    foreach ($topics as $key => $topic) {
        $stmt = $pdo->prepare("INSERT INTO test_topics (section_id, topic_name, num_questions, difficulty_easy, difficulty_medium, difficulty_hard) VALUES (?, ?, ?, ?, ?, ?)");
        $num_q = intval($easy[$key]) + intval($medium[$key]) + intval($hard[$key]);
        $stmt->execute([$section_id, $topic, $num_q, intval($easy[$key]), intval($medium[$key]), intval($hard[$key])]);
    }
}
?>