<?php
function calculateScore($required_skills, $extracted_skills) {
    if (empty($required_skills) || empty($extracted_skills)) return 0;

    $required = array_map('trim', explode(',', strtolower($required_skills)));
    $extracted = array_map('trim', explode(',', strtolower($extracted_skills)));

    $matched = array_intersect($required, $extracted);
    $score = (count($matched) / count($required)) * 100;

    return round($score, 2);
}
?>
