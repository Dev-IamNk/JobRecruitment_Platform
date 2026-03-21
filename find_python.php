<!-- FILE: find_python.php (Put in root folder) -->
<!DOCTYPE html>
<html>
<head>
    <title>Find Python Path</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 900px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .result {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin: 10px 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .success {
            border-left: 5px solid #28a745;
        }
        .error {
            border-left: 5px solid #dc3545;
        }
        .warning {
            border-left: 5px solid #ffc107;
        }
        code {
            background: #f0f0f0;
            padding: 3px 8px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
        }
        h1 {
            color: #667eea;
        }
        .copy-btn {
            background: #667eea;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            margin-left: 10px;
        }
        .copy-btn:hover {
            background: #5568d3;
        }
    </style>
</head>
<body>
    <h1>🔍 Find Python Installation</h1>
    
    <?php
    echo "<h2>Testing Common Python Paths...</h2>";
    
    $python_paths = [
        'python',
        'py',
        'python3',
        'C:\\Python312\\python.exe',
        'C:\\Python311\\python.exe',
        'C:\\Python310\\python.exe',
        'C:\\Python39\\python.exe',
        'C:\\Python38\\python.exe',
        'C:\\Users\\' . get_current_user() . '\\AppData\\Local\\Programs\\Python\\Python312\\python.exe',
        'C:\\Users\\' . get_current_user() . '\\AppData\\Local\\Programs\\Python\\Python311\\python.exe',
        'C:\\Users\\' . get_current_user() . '\\AppData\\Local\\Programs\\Python\\Python310\\python.exe',
        'C:\\Users\\' . get_current_user() . '\\AppData\\Local\\Programs\\Python\\Python39\\python.exe',
    ];
    
    $found_python = false;
    $working_path = '';
    
    foreach ($python_paths as $path) {
        $test = @shell_exec("\"$path\" --version 2>&1");
        
        if ($test && stripos($test, 'Python') !== false) {
            echo "<div class='result success'>";
            echo "<h3>✅ FOUND WORKING PYTHON!</h3>";
            echo "<p><strong>Path:</strong> <code id='python-path'>$path</code> ";
            echo "<button class='copy-btn' onclick=\"copyToClipboard('$path')\">Copy Path</button></p>";
            echo "<p><strong>Version:</strong> " . trim($test) . "</p>";
            echo "</div>";
            
            if (!$found_python) {
                $found_python = true;
                $working_path = $path;
            }
        } else {
            echo "<div class='result error'>";
            echo "<p>❌ Not found: <code>$path</code></p>";
            echo "</div>";
        }
    }
    
    if (!$found_python) {
        echo "<div class='result warning'>";
        echo "<h3>⚠️ Python Not Found!</h3>";
        echo "<p>Python is not installed or not in the system PATH.</p>";
        echo "<h4>Solutions:</h4>";
        echo "<ol>";
        echo "<li><strong>Install Python:</strong> Download from <a href='https://www.python.org/downloads/' target='_blank'>python.org</a></li>";
        echo "<li><strong>Check 'Add Python to PATH'</strong> during installation</li>";
        echo "<li>After installation, restart Apache in XAMPP</li>";
        echo "</ol>";
        echo "</div>";
    } else {
        echo "<div class='result success'>";
        echo "<h3>✅ What to Do Next:</h3>";
        echo "<ol>";
        echo "<li>Copy the Python path above: <code>$working_path</code></li>";
        echo "<li>Open: <code>scripts/apply_handler.php</code></li>";
        echo "<li>Find the line: <code>\$python_paths = [</code></li>";
        echo "<li>Add your working path at the TOP of the array:</li>";
        echo "<pre style='background: #f0f0f0; padding: 15px; border-radius: 5px;'>\$python_paths = [\n    '$working_path',  // <-- Add this line\n    'python',\n    'py',\n    ...];</pre>";
        echo "<li>Save the file and try applying again!</li>";
        echo "</ol>";
        echo "</div>";
        
        // Test Python packages
        echo "<h2>Testing Python Packages...</h2>";
        
        $packages = [
            ['name' => 'PyPDF2', 'import' => 'PyPDF2', 'install' => 'PyPDF2'],
            ['name' => 'python-docx', 'import' => 'docx', 'install' => 'python-docx'],
        ];
        
        foreach ($packages as $pkg) {
            $test = @shell_exec("\"$working_path\" -c \"import {$pkg['import']}; print('OK')\" 2>&1");
            
            if (stripos($test, 'OK') !== false) {
                echo "<div class='result success'>";
                echo "<p>✅ Package <strong>{$pkg['name']}</strong> is installed</p>";
                echo "</div>";
            } else {
                echo "<div class='result error'>";
                echo "<p>❌ Package <strong>{$pkg['name']}</strong> is NOT installed</p>";
                echo "<p><strong>Install with:</strong> <code>pip install {$pkg['install']}</code></p>";
                echo "</div>";
            }
        }
        
        // Test the resume parser
        echo "<h2>Testing Resume Parser Script...</h2>";
        
        $script_path = realpath(__DIR__ . '/python/resume_parser.py');
        
        if (file_exists($script_path)) {
            echo "<div class='result success'>";
            echo "<p>✅ Resume parser script found at: <code>$script_path</code></p>";
            
            // Create test file
            $test_content = "SKILLS: Python, Java, MySQL, JavaScript, HTML, CSS";
            $test_file = 'test_resume_temp.txt';
            file_put_contents($test_file, $test_content);
            
            $test_command = "\"$working_path\" \"$script_path\" \"$test_file\" \"Python, Java, MySQL\" 2>&1";
            $output = shell_exec($test_command);
            
            echo "<p><strong>Test Command:</strong><br><code>$test_command</code></p>";
            echo "<p><strong>Output:</strong></p>";
            echo "<pre style='background: #f0f0f0; padding: 15px; border-radius: 5px; max-height: 300px; overflow-y: auto;'>" . htmlspecialchars($output) . "</pre>";
            
            $result = json_decode($output, true);
            if ($result && isset($result['score'])) {
                echo "<p style='color: green; font-weight: bold;'>✅ Resume parser is working correctly!</p>";
                echo "<p><strong>Score:</strong> {$result['score']}%</p>";
                echo "<p><strong>Skills found:</strong> " . implode(', ', $result['skills']) . "</p>";
            } else {
                echo "<p style='color: red; font-weight: bold;'>❌ Resume parser failed to return valid JSON</p>";
            }
            
            unlink($test_file);
            echo "</div>";
        } else {
            echo "<div class='result error'>";
            echo "<p>❌ Resume parser script not found at: <code>$script_path</code></p>";
            echo "</div>";
        }
    }
    ?>
    
    <hr>
    <p><a href="test_diagnostic.php" style="padding: 10px 20px; background: #667eea; color: white; text-decoration: none; border-radius: 5px;">Run Full Diagnostic</a></p>
    <p><a href="pages/login.php" style="padding: 10px 20px; background: #28a745; color: white; text-decoration: none; border-radius: 5px;">Go to Login</a></p>
    
    <script>
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(function() {
                alert('Python path copied to clipboard!');
            }, function(err) {
                alert('Failed to copy: ' + err);
            });
        }
    </script>
</body>
</html>