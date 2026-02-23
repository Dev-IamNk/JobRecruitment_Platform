<?php
require_once '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $full_name = trim($_POST['full_name']);
    $user_type = $_POST['user_type'];
    
    // Validate input
    if (empty($email) || empty($password) || empty($full_name) || empty($user_type)) {
        header('Location: ../pages/register.php?error=empty');
        exit();
    }
    
    // Check if email exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        header('Location: ../pages/register.php?error=exists');
        exit();
    }
    
    // Insert user
    $stmt = $pdo->prepare("INSERT INTO users (email, password, full_name, user_type) VALUES (?, MD5(?), ?, ?)");
    if ($stmt->execute([$email, $password, $full_name, $user_type])) {
        header('Location: ../pages/login.php?success=registered');
    } else {
        header('Location: ../pages/register.php?error=failed');
    }
}
?>