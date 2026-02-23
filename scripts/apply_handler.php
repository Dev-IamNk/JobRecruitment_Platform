<?php
session_start();
require_once "../config/db.php";          // DB connection
require_once "../config/functions.php";  // calculateScore() lives here

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Invalid request");
}

$candidate_id = $_SESSION['user_id'] ?? null;
$job_id       = $_POST['job_id'] ?? null;
$cover_letter = $_POST['cover_letter'] ?? '';

if (!$candidate_id || !$job_id) {
    die("Missing candidate or job details");
}

/* -------------------------
   1️⃣ HANDLE FILE UPLOAD
--------------------------*/
if (!isset($_FILES['resume']) || $_FILES['resume']['error'] !== UPLOAD_ERR_OK) {
    die("Resume upload failed");
}

$upload_dir = realpath(__DIR__ . "/../assets/uploads");
if (!$upload_dir) {
    die("Upload directory missing");
}

$file_name = time() . "_" . basename($_FILES['resume']['name']);
$save_path = $upload_dir . DIRECTORY_SEPARATOR . $file_name;

if (!move_uploaded_file($_FILES['resume']['tmp_name'], $save_path)) {
    die("File save failed");
}

/* -------------------------
   2️⃣ INSERT BASE APPLICATION
--------------------------*/
$stmt = $pdo->prepare("
    INSERT INTO applications 
    (job_id, candidate_id, resume_path, cover_letter, extracted_skills, score, status, email_sent, applied_at)
    VALUES (?, ?, ?, ?, '', 0, 'Pending', 0, NOW())
");
$stmt->execute([$job_id, $candidate_id, $file_name, $cover_letter]);

$application_id = $pdo->lastInsertId();

/* -------------------------
   3️⃣ RUN PYTHON NLP SCRIPT
--------------------------*/
$script_path = realpath(__DIR__ . "/../python/resume_parser.py");

// REAL Python path (the one that worked in CMD)
$python_path = "C:\\Users\\nandh\\AppData\\Local\\Programs\\Python\\Python310\\python.exe";

$resume_full_path = $save_path;

// command (quotes are IMPORTANT)
$python_cmd = "\"$python_path\" \"$script_path\" \"$resume_full_path\"";

// run command
$extracted_skills = trim(shell_exec($python_cmd));

/* ---- Debug Logging (temporary) ---- */
file_put_contents(
    __DIR__ . '/../python/debug.log',
    "=== APPLY HANDLER RUN ===\n".
    "Command: $python_cmd\n".
    "Output: $extracted_skills\n\n",
    FILE_APPEND
);

/* -------------------------
   4️⃣ CALCULATE SCORE
--------------------------*/
$stmt = $pdo->prepare("SELECT required_skills FROM jobs WHERE id = ?");
$stmt->execute([$job_id]);
$job = $stmt->fetch(PDO::FETCH_ASSOC);

$score = 0;

if (!empty($extracted_skills) && !empty($job['required_skills'])) {
    $score = calculateScore($job['required_skills'], $extracted_skills);
}

/* -------------------------
   5️⃣ UPDATE APPLICATION
--------------------------*/
$stmt = $pdo->prepare("
    UPDATE applications 
    SET extracted_skills = ?, score = ?, status = 'Processed'
    WHERE id = ?
");
$stmt->execute([$extracted_skills, $score, $application_id]);

/* -------------------------
   6️⃣ DONE
--------------------------*/
header("Location: ../pages/candidate_dashboard.php?msg=Application submitted successfully! RPA will process it soon.");
exit;
