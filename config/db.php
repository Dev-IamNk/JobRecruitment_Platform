
<?php
session_start();

$host = 'localhost';
$dbname = 'recruitment_rpa';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function getUserType() {
    return $_SESSION['user_type'] ?? null;
}

function redirectIfNotLoggedIn() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit();
    }
}
?>