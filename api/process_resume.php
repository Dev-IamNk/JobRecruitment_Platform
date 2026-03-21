<!-- FILE: api/process_resume.php -->
<?php
require_once '../config/db.php';

header('Content-Type: application/json');

// Simple API key authentication
$api_key = $_GET['api_key'] ?? '';
if ($api_key !== 'RPA_SECRET_KEY_12345') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'get_pending':
        getPendingApplications();
        break;
    
    case 'update_score':
        updateApplicationScore();
        break;
    
    case 'get_stats':
        getStats();
        break;
    
    default:
        echo json_encode(['error' => 'Invalid action. Use: get_pending, update_score, or get_stats']);
}

function getPendingApplications() {
    global $pdo;
    
    $limit = intval($_GET['limit'] ?? 10);
    
    $stmt = $pdo->prepare("
        SELECT a.id, a.resume_path, a.candidate_id, 
               j.required_skills, j.title as job_title, j.id as job_id,
               u.email, u.full_name
        FROM applications a
        JOIN jobs j ON a.job_id = j.id
        JOIN users u ON a.candidate_id = u.id
        WHERE a.status = 'pending'
        ORDER BY a.applied_at ASC
        LIMIT ?
    ");
    
    $stmt->execute([$limit]);
    $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Convert relative paths to absolute Windows paths
    $base_path = realpath(__DIR__ . '/../');
    
    foreach ($applications as &$app) {
        // Convert path separators for Windows
        $relative_path = str_replace('../', '', $app['resume_path']);
        $app['resume_path_absolute'] = $base_path . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative_path);
        $app['resume_path_original'] = $app['resume_path'];
    }
    
    echo json_encode([
        'success' => true,
        'count' => count($applications),
        'applications' => $applications,
        'base_path' => $base_path
    ], JSON_PRETTY_PRINT);
}

function updateApplicationScore() {
    global $pdo;
    
    // Get JSON input
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!$data) {
        echo json_encode(['error' => 'Invalid JSON input']);
        return;
    }
    
    $app_id = intval($data['application_id'] ?? 0);
    $score = floatval($data['score'] ?? 0);
    $skills = $data['skills'] ?? [];
    $status = $data['status'] ?? 'scored';
    
    if (!$app_id) {
        echo json_encode(['error' => 'application_id is required']);
        return;
    }
    
    // Convert skills array to comma-separated string
    $skills_str = is_array($skills) ? implode(', ', $skills) : $skills;
    
    try {
        // Update application with score and extracted skills
        $stmt = $pdo->prepare("
            UPDATE applications 
            SET score = ?, 
                extracted_skills = ?, 
                status = ?
            WHERE id = ?
        ");
        
        $stmt->execute([$score, $skills_str, $status, $app_id]);
        
        // Get updated application
        $stmt = $pdo->prepare("SELECT * FROM applications WHERE id = ?");
        $stmt->execute([$app_id]);
        $application = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Update rank for this job
        updateRanks($application['job_id']);
        
        echo json_encode([
            'success' => true,
            'message' => 'Score updated successfully',
            'application' => $application
        ], JSON_PRETTY_PRINT);
        
    } catch (Exception $e) {
        echo json_encode([
            'error' => 'Database error: ' . $e->getMessage()
        ]);
    }
}

function updateRanks($job_id) {
    global $pdo;
    
    // Get all applications for this job ordered by score
    $stmt = $pdo->prepare("
        SELECT id FROM applications 
        WHERE job_id = ? 
        ORDER BY score DESC, applied_at ASC
    ");
    $stmt->execute([$job_id]);
    $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Update rank for each application
    $rank = 1;
    foreach ($applications as $app) {
        $stmt = $pdo->prepare("UPDATE applications SET rank = ? WHERE id = ?");
        $stmt->execute([$rank, $app['id']]);
        $rank++;
    }
}

function getStats() {
    global $pdo;
    
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as total_applications,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'scored' THEN 1 ELSE 0 END) as scored,
            AVG(score) as avg_score
        FROM applications
    ");
    
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'stats' => $stats
    ], JSON_PRETTY_PRINT);
}
?>