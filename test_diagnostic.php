<!-- FILE: test_diagnostic.php (Put this in root folder) -->
<?php
require_once 'config/db.php';

echo "<h1>Recruitment System Diagnostic</h1>";
echo "<hr>";

// Test 1: Check database connection
echo "<h2>1. Database Connection</h2>";
try {
    $pdo->query("SELECT 1");
    echo "✅ Database connected successfully<br>";
} catch (Exception $e) {
    echo "❌ Database connection failed: " . $e->getMessage() . "<br>";
}

// Test 2: Check jobs table
echo "<h2>2. Jobs in Database</h2>";
$jobs = $pdo->query("SELECT id, title, required_skills FROM jobs")->fetchAll();
if (count($jobs) > 0) {
    echo "✅ Found " . count($jobs) . " job(s):<br>";
    echo "<table border='1' cellpadding='10' style='margin-top: 10px;'>";
    echo "<tr><th>Job ID</th><th>Title</th><th>Required Skills</th></tr>";
    foreach ($jobs as $job) {
        echo "<tr>";
        echo "<td>" . $job['id'] . "</td>";
        echo "<td>" . htmlspecialchars($job['title']) . "</td>";
        echo "<td>" . htmlspecialchars($job['required_skills']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "⚠️ No jobs found in database. Please post a job first.<br>";
}

// Test 3: Check uploads folder
echo "<h2>3. Upload Directory</h2>";
$upload_dir = 'assets/uploads/resumes/';
if (is_dir($upload_dir)) {
    echo "✅ Upload directory exists: $upload_dir<br>";
    if (is_writable($upload_dir)) {
        echo "✅ Upload directory is writable<br>";
    } else {
        echo "❌ Upload directory is NOT writable<br>";
    }
} else {
    echo "⚠️ Upload directory does not exist. Creating...<br>";
    if (mkdir($upload_dir, 0777, true)) {
        echo "✅ Directory created successfully<br>";
    } else {
        echo "❌ Failed to create directory<br>";
    }
}

// Test 4: Check Python
echo "<h2>4. Python Installation</h2>";
$python_paths = ['python', 'python3', 'C:\\Python310\\python.exe', 'C:\\Python39\\python.exe'];
$python_found = false;
foreach ($python_paths as $path) {
    $test = shell_exec("$path --version 2>&1");
    if ($test && strpos($test, 'Python') !== false) {
        echo "✅ Found Python: $path - $test<br>";
        $python_found = true;
        $working_python = $path;
        break;
    }
}
if (!$python_found) {
    echo "❌ Python not found in PATH<br>";
}

// Test 5: Check Python packages
if ($python_found) {
    echo "<h2>5. Python Packages</h2>";
    $packages = ['PyPDF2', 'docx'];
    foreach ($packages as $pkg) {
        $test = shell_exec("$working_python -c \"import " . ($pkg == 'docx' ? 'docx' : $pkg) . "; print('OK')\" 2>&1");
        if (strpos($test, 'OK') !== false) {
            echo "✅ Package '$pkg' is installed<br>";
        } else {
            echo "❌ Package '$pkg' is NOT installed<br>";
            echo "&nbsp;&nbsp;&nbsp;Install with: pip install " . ($pkg == 'docx' ? 'python-docx' : $pkg) . "<br>";
        }
    }
}

// Test 6: Check Python script
echo "<h2>6. Python Resume Parser</h2>";
$python_script = 'python/resume_parser.py';
if (file_exists($python_script)) {
    echo "✅ Python script exists: $python_script<br>";
} else {
    echo "❌ Python script NOT found: $python_script<br>";
}

// Test 7: Check applications
echo "<h2>7. Recent Applications</h2>";
$apps = $pdo->query("SELECT a.*, j.title, u.full_name FROM applications a 
                     JOIN jobs j ON a.job_id = j.id 
                     JOIN users u ON a.candidate_id = u.id 
                     ORDER BY a.id DESC LIMIT 5")->fetchAll();
if (count($apps) > 0) {
    echo "✅ Found " . count($apps) . " recent application(s):<br>";
    echo "<table border='1' cellpadding='10' style='margin-top: 10px;'>";
    echo "<tr><th>ID</th><th>Candidate</th><th>Job</th><th>Status</th><th>Score</th><th>Skills</th></tr>";
    foreach ($apps as $app) {
        echo "<tr>";
        echo "<td>" . $app['id'] . "</td>";
        echo "<td>" . htmlspecialchars($app['full_name']) . "</td>";
        echo "<td>" . htmlspecialchars($app['title']) . "</td>";
        echo "<td>" . $app['status'] . "</td>";
        echo "<td>" . $app['score'] . "</td>";
        echo "<td>" . htmlspecialchars(substr($app['extracted_skills'], 0, 50)) . "...</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "⚠️ No applications found yet.<br>";
}

// Test 8: Test Python script directly
if ($python_found && file_exists($python_script)) {
    echo "<h2>8. Test Python Script</h2>";
    
    // Create a test file
    $test_content = "SKILLS: Python, MySQL, PHP, HTML, CSS, JavaScript, Django";
    $test_file = 'test_resume_temp.txt';
    file_put_contents($test_file, $test_content);
    
    $test_command = "$working_python $python_script \"$test_file\" \"Python, MySQL, PHP\" 2>&1";
    echo "Running: <code>$test_command</code><br><br>";
    
    $output = shell_exec($test_command);
    echo "<strong>Output:</strong><br>";
    echo "<pre>" . htmlspecialchars($output) . "</pre>";
    
    $result = json_decode($output, true);
    if ($result && isset($result['score'])) {
        echo "✅ Python script working correctly<br>";
        echo "Score: " . $result['score'] . "%<br>";
        echo "Skills found: " . implode(', ', $result['skills']) . "<br>";
    } else {
        echo "❌ Python script failed to return valid JSON<br>";
    }
    
    // Clean up
    unlink($test_file);
}

echo "<hr>";
echo "<h2>Summary</h2>";
echo "<a href='pages/login.php' style='padding: 10px 20px; background: #667eea; color: white; text-decoration: none; border-radius: 5px;'>Go to Login</a>";
?>

<style>
    body {
        font-family: Arial, sans-serif;
        max-width: 1200px;
        margin: 0 auto;
        padding: 20px;
        background: #f5f5f5;
    }
    h1 {
        color: #667eea;
    }
    h2 {
        color: #333;
        margin-top: 30px;
        padding: 10px;
        background: #e7f3ff;
        border-left: 4px solid #667eea;
    }
    table {
        background: white;
        border-collapse: collapse;
        width: 100%;
    }
    th {
        background: #667eea;
        color: white;
    }
    code {
        background: #f0f0f0;
        padding: 2px 6px;
        border-radius: 3px;
    }
    pre {
        background: #f0f0f0;
        padding: 15px;
        border-radius: 5px;
        overflow-x: auto;
    }
</style>