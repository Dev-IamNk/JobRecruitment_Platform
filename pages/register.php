<?php require_once '../config/db.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Register - RPA Recruitment</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="auth-container">
    <h2>Register</h2>

    <?php
    if (isset($_GET['error'])) {
        if ($_GET['error'] == 'exists') {
            echo '<div class="alert alert-error">Email already exists!</div>';
        } elseif ($_GET['error'] == 'empty') {
            echo '<div class="alert alert-error">Please fill all fields!</div>';
        } elseif ($_GET['error'] == 'failed') {
            echo '<div class="alert alert-error">Registration failed! Please try again.</div>';
        }
    }
    ?>

    <form action="../scripts/register_handler.php" method="POST">
        <div class="form-group">
            <label for="full_name">Full Name</label>
            <input type="text" name="full_name" id="full_name" required>
        </div>

        <div class="form-group">
            <label for="email">Email</label>
            <input type="email" name="email" id="email" required>
        </div>

        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" name="password" id="password" required>
        </div>

        <div class="form-group">
            <label for="user_type">Register as</label>
            <select name="user_type" id="user_type" required>
                <option value="">Select...</option>
                <option value="candidate">Candidate</option>
                <option value="recruiter">Recruiter</option>
            </select>
        </div>

        <button type="submit">Register</button>
    </form>

    <p style="margin-top: 15px;">
        Already have an account? <a href="login.php">Login here</a>
    </p>
</div>
</body>
</html>
