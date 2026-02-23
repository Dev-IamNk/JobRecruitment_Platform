
<?php
require_once '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    
    $stmt = $pdo->prepare("SELECT id, email, full_name, user_type FROM users WHERE email = ? AND password = MD5(?)");
    $stmt->execute([$email, $password]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['user_type'] = $user['user_type'];
        
        if ($user['user_type'] == 'recruiter') {
            header('Location: ../pages/recruiter_dashboard.php');
        } else {
            header('Location: ../pages/candidate_dashboard.php');
        }
    } else {
        header('Location: ../pages/login.php?error=invalid');
    }
}
?>