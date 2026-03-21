<?php
// Manual test script to process one resume

$resume_path = "C:/xampp/htdocs/recruitment_rpa/assets/uploads/resumes/resume_2_1234567890.pdf";
$required_skills = "Python, Django, MySQL";

// Run Python script
$command = "python C:/xampp/htdocs/recruitment_rpa/python/resume_parser.py \"$resume_path\" \"$required_skills\"";
$output = shell_exec($command);

echo "Python Output:\n";
echo $output;

// Parse JSON
$result = json_decode($output, true);
if ($result) {
    echo "\n\nParsed Results:\n";
    echo "Score: " . $result['score'] . "%\n";
    echo "Skills: " . implode(", ", $result['skills']) . "\n";
} else {
    echo "\nError parsing JSON\n";
}
?>