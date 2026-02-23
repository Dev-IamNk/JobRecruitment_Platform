
<?php
require_once '../config/db.php';
redirectIfNotLoggedIn();

if ($_SERVER['REQUEST_METHOD'] == 'POST' && getUserType() == 'recruiter') {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $required_skills = trim($_POST['required_skills']);
    $location = trim($_POST['location']);
    $salary_range = trim($_POST['salary_range']);
    $recruiter_id = $_SESSION['user_id'];
    
    $stmt = $pdo->prepare("INSERT INTO jobs (recruiter_id, title, description, required_skills, location, salary_range) VALUES (?, ?, ?, ?, ?, ?)");
    
    if ($stmt->execute([$recruiter_id, $title, $description, $required_skills, $location, $salary_range])) {
        header('Location: ../pages/recruiter_dashboard.php?success=posted');
    } else {
        header('Location: ../pages/post_job.php?error=failed');
    }
}
?>