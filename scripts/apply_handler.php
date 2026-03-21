<!-- FILE: scripts/apply_handler.php (FINAL VERSION) -->
<?php
require_once '../config/db.php';
redirectIfNotLoggedIn();

if ($_SERVER['REQUEST_METHOD'] == 'POST' && getUserType() == 'candidate') {
    $job_id = intval($_POST['job_id']);
    $cover_letter = trim($_POST['cover_letter']);
    $candidate_id = $_SESSION['user_id'];
    
    // Get job details for required skills
    $stmt = $pdo->prepare("SELECT required_skills FROM jobs WHERE id = ?");
    $stmt->execute([$job_id]);
    $job = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$job) {
        header('Location: ../pages/view_jobs.php?error=jobnotfound');
        exit();
    }
    
    // Handle resume upload
    if (isset($_FILES['resume']) && $_FILES['resume']['error'] == 0) {
        $allowed = ['pdf', 'doc', 'docx', 'txt'];
        $filename = $_FILES['resume']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (!in_array($ext, $allowed)) {
            header('Location: ../pages/apply_job.php?id='.$job_id.'&error=filetype');
            exit();
        }
        
        $new_filename = 'resume_' . $candidate_id . '_' . time() . '.' . $ext;
        $upload_path = '../assets/uploads/resumes/' . $new_filename;
        
        if (move_uploaded_file($_FILES['resume']['tmp_name'], $upload_path)) {
            
            $full_path = realpath($upload_path);
            
            // Insert application first
            $stmt = $pdo->prepare("INSERT INTO applications (job_id, candidate_id, resume_path, cover_letter, status) VALUES (?, ?, ?, ?, 'pending')");
            
            if ($stmt->execute([$job_id, $candidate_id, $upload_path, $cover_letter])) {
                $application_id = $pdo->lastInsertId();
                
                // ===== INSTANT RESUME PROCESSING =====
                processResume($application_id, $full_path, $job['required_skills']);
                
                header('Location: ../pages/candidate_dashboard.php?success=applied');
            } else {
                header('Location: ../pages/apply_job.php?id='.$job_id.'&error=failed');
            }
        } else {
            header('Location: ../pages/apply_job.php?id='.$job_id.'&error=upload');
        }
    } else {
        header('Location: ../pages/apply_job.php?id='.$job_id.'&error=noresume');
    }
}

/**
 * Process resume immediately using Python
 */
function processResume($application_id, $resume_path, $required_skills) {
    global $pdo;
    
    // ===== YOUR PYTHON PATH =====
    // Use the exact path from "where python" command
    $python_exe = 'C:\\Users\\nandh\\AppData\\Local\\Programs\\Python\\Python310\\python.exe';
    
    // Path to Python script
    $python_script = realpath(__DIR__ . '/../python/resume_parser.py');
    
    // Escape arguments for Windows
    $resume_path_escaped = '"' . str_replace('"', '\"', $resume_path) . '"';
    $required_skills_escaped = '"' . str_replace('"', '\"', $required_skills) . '"';
    
    // Build command for Windows
    $command = "\"$python_exe\" \"$python_script\" $resume_path_escaped $required_skills_escaped 2>&1";
    
    // Execute Python script
    $output = shell_exec($command);
    
    // Parse JSON output
    $result = json_decode($output, true);
    
    if ($result && isset($result['score'])) {
        // Extract data
        $score = floatval($result['score']);
        $skills = isset($result['skills']) ? implode(', ', $result['skills']) : '';
        
        // Update application with score
        $stmt = $pdo->prepare("
            UPDATE applications 
            SET score = ?, 
                extracted_skills = ?, 
                status = 'scored'
            WHERE id = ?
        ");
        $stmt->execute([$score, $skills, $application_id]);
        
        // Update rank for this job
        $stmt = $pdo->prepare("SELECT job_id FROM applications WHERE id = ?");
        $stmt->execute([$application_id]);
        $job_id = $stmt->fetch(PDO::FETCH_ASSOC)['job_id'];
        
        updateRanks($job_id);
        
        return true;
    } else {
        // If processing fails, keep status as 'pending'
        error_log("Resume processing failed for application $application_id. Output: " . $output);
        return false;
    }
}

/**
 * Update candidate rankings for a job
 */
function updateRanks($job_id) {
    global $pdo;
    
    // Get all scored applications for this job
    $stmt = $pdo->prepare("
        SELECT id FROM applications 
        WHERE job_id = ? AND status != 'pending'
        ORDER BY score DESC, applied_at ASC
    ");
    $stmt->execute([$job_id]);
    $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Update rank
    $rank = 1;
    foreach ($applications as $app) {
        $stmt = $pdo->prepare("UPDATE applications SET rank = ? WHERE id = ?");
        $stmt->execute([$rank, $app['id']]);
        $rank++;
    }
}
?>