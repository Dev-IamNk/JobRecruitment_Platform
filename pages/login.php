<?php require_once '../config/db.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login - RPA Recruitment</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="auth-container">
    <h2>Login</h2>

    <?php
    if (isset($_GET['error'])) {
        if ($_GET['error'] == 'invalid') {
            echo '<div class="alert alert-error">Invalid email or password!</div>';
        }
    } elseif (isset($_GET['success']) && $_GET['success'] == 'registered') {
        echo '<div class="alert alert-success">Registration successful! Please login.</div>';
    }
    ?>

    <form action="../scripts/login_handler.php" method="POST">
        <div class="form-group">
            <label for="email">Email</label>
            <input type="email" name="email" id="email" required>
        </div>

        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" name="password" id="password" required>
        </div>

        <button type="submit">Login</button>
    </form>

    <p style="margin-top: 15px;">
        Don't have an account? <a href="register.php">Register here</a>
    </p>
</div>
</body>
</html>
