<?php
require_once '../config/db.php';
redirectIfNotLoggedIn();

if (getUserType() != 'recruiter') {
    header('Location: ../pages/candidate_dashboard.php');
    exit();
}

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $required_skills = trim($_POST['required_skills'] ?? '');
    $salary_range = trim($_POST['salary_range'] ?? '');

    // Basic validation
    if (empty($title)) $errors[] = "Job title is required.";
    if (empty($description)) $errors[] = "Job description is required.";
    if (empty($location)) $errors[] = "Location is required.";
    if (empty($required_skills)) $errors[] = "Required skills are required.";

    if (empty($errors)) {
        $stmt = $pdo->prepare("
            INSERT INTO jobs 
            (recruiter_id, title, description, required_skills, location, salary_range, status, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, 'open', NOW())
        ");
        $stmt->execute([$_SESSION['user_id'], $title, $description, $required_skills, $location, $salary_range]);
        $success = "Job posted successfully!";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Post a New Job</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="container">
    <div class="nav-bar">
        <h2>Post a New Job</h2>
        <div class="nav-links">
            <a href="recruiter_dashboard.php">Dashboard</a>
            <a href="../scripts/logout.php">Logout</a>
        </div>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-error">
            <ul>
                <?php foreach ($errors as $err): ?>
                    <li><?php echo htmlspecialchars($err); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <form action="" method="post">
        <div class="form-group">
            <label for="title">Job Title</label>
            <input type="text" name="title" id="title" value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>" required>
        </div>
        <div class="form-group">
            <label for="description">Job Description</label>
            <textarea name="description" id="description" rows="5" required><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
        </div>
        <div class="form-group">
            <label for="location">Location</label>
            <input type="text" name="location" id="location" value="<?php echo htmlspecialchars($_POST['location'] ?? ''); ?>" required>
        </div>
        <div class="form-group">
            <label for="required_skills">Required Skills (comma-separated)</label>
            <input type="text" name="required_skills" id="required_skills" value="<?php echo htmlspecialchars($_POST['required_skills'] ?? ''); ?>" required>
        </div>
        <div class="form-group">
            <label for="salary_range">Salary Range (optional)</label>
            <input type="text" name="salary_range" id="salary_range" value="<?php echo htmlspecialchars($_POST['salary_range'] ?? ''); ?>">
        </div>
        <button type="submit" class="btn">Post Job</button>
    </form>
</div>
</body>
</html>
