<?php
// scripts/email_helper.php
// Call these functions from anywhere to queue an email for UiPath to send

// ── 1. Shortlisted → Send test link ──────────────────────────────────────────
function queueTestLinkEmail($pdo, $application_id) {
    // Get candidate + job details
    $q = $pdo->prepare("
        SELECT u.email, u.full_name, j.title as job_title, j.id as job_id
        FROM applications a
        JOIN users u ON u.id = a.candidate_id
        JOIN jobs j  ON j.id = a.job_id
        WHERE a.id = ?
    ");
    $q->execute([$application_id]);
    $data = $q->fetch(PDO::FETCH_ASSOC);
    if (!$data) return false;

    $test_link = "http://localhost/recruitment_rpa/pages/take_test.php?app_id={$application_id}";
    $name      = htmlspecialchars($data['full_name']);
    $job       = htmlspecialchars($data['job_title']);

    $subject   = "You've Been Shortlisted! Complete Your Online Test – {$data['job_title']}";
    $body      = "
    <div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;background:#f9f9f9;padding:30px;border-radius:10px;'>
        <div style='background:linear-gradient(135deg,#667eea,#764ba2);padding:30px;border-radius:8px;text-align:center;margin-bottom:24px;'>
            <h1 style='color:#fff;margin:0;font-size:24px;'>🎉 Congratulations!</h1>
            <p style='color:rgba(255,255,255,0.9);margin:8px 0 0;'>You have been shortlisted!</p>
        </div>
        <p style='color:#333;font-size:16px;'>Dear <strong>{$name}</strong>,</p>
        <p style='color:#555;line-height:1.7;'>We are pleased to inform you that your resume has been shortlisted for the position of <strong>{$job}</strong>.</p>
        <p style='color:#555;line-height:1.7;'>The next step is to complete our online assessment test. Please click the button below to begin:</p>
        <div style='text-align:center;margin:32px 0;'>
            <a href='{$test_link}' style='background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;padding:14px 36px;border-radius:8px;text-decoration:none;font-weight:bold;font-size:16px;display:inline-block;'>
                🚀 Start Online Test
            </a>
        </div>
        <div style='background:#fff3cd;border:1px solid #ffc107;border-radius:6px;padding:14px 18px;margin-bottom:20px;'>
            <strong>⚠ Important:</strong>
            <ul style='margin:8px 0 0 16px;color:#555;line-height:1.8;'>
                <li>The test includes Aptitude, Technical, and Coding rounds</li>
                <li>Ensure a stable internet connection</li>
                <li>Complete in one sitting — do not refresh the page</li>
            </ul>
        </div>
        <p style='color:#555;'>Best of luck!</p>
        <p style='color:#555;font-weight:bold;'>The Recruitment Team</p>
        <hr style='border:none;border-top:1px solid #eee;margin:24px 0;'>
        <p style='color:#aaa;font-size:12px;text-align:center;'>This is an automated message. Please do not reply.</p>
    </div>";

    return insertEmailQueue($pdo, $data['email'], $data['full_name'], 'test_link', $subject, $body, $application_id);
}

// ── 2. Test cleared → Send interview/meet link ────────────────────────────────
function queueTestClearedEmail($pdo, $application_id) {
    $q = $pdo->prepare("
        SELECT u.email, u.full_name, j.title as job_title,
               j.interview_date, j.interview_link,
               a.final_score, a.coding_score, a.aptitude_tech_score
        FROM applications a
        JOIN users u ON u.id = a.candidate_id
        JOIN jobs j  ON j.id = a.job_id
        WHERE a.id = ?
    ");
    $q->execute([$application_id]);
    $data = $q->fetch(PDO::FETCH_ASSOC);
    if (!$data) return false;

    $name        = htmlspecialchars($data['full_name']);
    $job         = htmlspecialchars($data['job_title']);
    $final_score = number_format(floatval($data['final_score']), 1);
    $meet_link   = $data['interview_link'] ?: 'https://meet.google.com';
    $interview_date = $data['interview_date']
        ? date('D, d M Y \a\t h:i A', strtotime($data['interview_date']))
        : 'To be confirmed — we will notify you shortly';

    $subject = "🎉 You've Cleared the Test Round! Interview Details – {$data['job_title']}";
    $body    = "
    <div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;background:#f9f9f9;padding:30px;border-radius:10px;'>
        <div style='background:linear-gradient(135deg,#00b09b,#96c93d);padding:30px;border-radius:8px;text-align:center;margin-bottom:24px;'>
            <h1 style='color:#fff;margin:0;font-size:24px;'>🏆 Test Round Cleared!</h1>
            <p style='color:rgba(255,255,255,0.9);margin:8px 0 0;'>Congratulations on your performance!</p>
        </div>
        <p style='color:#333;font-size:16px;'>Dear <strong>{$name}</strong>,</p>
        <p style='color:#555;line-height:1.7;'>Fantastic news! You have successfully cleared the online assessment for <strong>{$job}</strong>.</p>

        <div style='background:#fff;border:1px solid #e0e0e0;border-radius:8px;padding:20px;margin:20px 0;'>
            <h3 style='color:#333;margin:0 0 12px;'>📊 Your Test Score</h3>
            <p style='color:#667eea;font-size:28px;font-weight:bold;margin:0;'>{$final_score}%</p>
            <p style='color:#888;font-size:13px;margin:4px 0 0;'>Combined score (Resume + Aptitude + Coding)</p>
        </div>

        <div style='background:#fff;border:1px solid #e0e0e0;border-radius:8px;padding:20px;margin:20px 0;'>
            <h3 style='color:#333;margin:0 0 14px;'>📅 Interview Details</h3>
            <p style='color:#555;margin:0 0 6px;'><strong>Date & Time:</strong> {$interview_date}</p>
            <p style='color:#555;margin:0 0 16px;'><strong>Mode:</strong> Online (Google Meet)</p>
            <div style='text-align:center;'>
                <a href='{$meet_link}' style='background:#00b09b;color:#fff;padding:12px 30px;border-radius:8px;text-decoration:none;font-weight:bold;font-size:15px;display:inline-block;'>
                    📹 Join Interview
                </a>
            </div>
        </div>

        <div style='background:#e8f5e9;border:1px solid #a5d6a7;border-radius:6px;padding:14px 18px;margin-bottom:20px;'>
            <strong>💡 Interview Tips:</strong>
            <ul style='margin:8px 0 0 16px;color:#555;line-height:1.8;'>
                <li>Join 5 minutes early to test your connection</li>
                <li>Keep your resume handy</li>
                <li>Be prepared to discuss your projects and skills</li>
            </ul>
        </div>

        <p style='color:#555;'>We look forward to meeting you. Best of luck!</p>
        <p style='color:#555;font-weight:bold;'>The Recruitment Team</p>
        <hr style='border:none;border-top:1px solid #eee;margin:24px 0;'>
        <p style='color:#aaa;font-size:12px;text-align:center;'>This is an automated message. Please do not reply.</p>
    </div>";

    return insertEmailQueue($pdo, $data['email'], $data['full_name'], 'test_cleared', $subject, $body, $application_id);
}

// ── 3. Selected ───────────────────────────────────────────────────────────────
function queueSelectionEmail($pdo, $application_id) {
    $q = $pdo->prepare("
        SELECT u.email, u.full_name, j.title as job_title, j.location, j.salary_range
        FROM applications a
        JOIN users u ON u.id = a.candidate_id
        JOIN jobs j  ON j.id = a.job_id
        WHERE a.id = ?
    ");
    $q->execute([$application_id]);
    $data = $q->fetch(PDO::FETCH_ASSOC);
    if (!$data) return false;

    $name     = htmlspecialchars($data['full_name']);
    $job      = htmlspecialchars($data['job_title']);
    $location = htmlspecialchars($data['location'] ?? 'To be confirmed');
    $salary   = htmlspecialchars($data['salary_range'] ?? 'As per company norms');

    $subject = "🎊 Offer Letter – You're Selected for {$data['job_title']}!";
    $body    = "
    <div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;background:#f9f9f9;padding:30px;border-radius:10px;'>
        <div style='background:linear-gradient(135deg,#f093fb,#f5576c);padding:30px;border-radius:8px;text-align:center;margin-bottom:24px;'>
            <h1 style='color:#fff;margin:0;font-size:28px;'>🎊 You're Selected!</h1>
            <p style='color:rgba(255,255,255,0.9);margin:8px 0 0;font-size:16px;'>Welcome to the team!</p>
        </div>
        <p style='color:#333;font-size:16px;'>Dear <strong>{$name}</strong>,</p>
        <p style='color:#555;line-height:1.7;'>We are absolutely thrilled to inform you that you have been <strong>selected</strong> for the position of <strong>{$job}</strong>!</p>
        <p style='color:#555;line-height:1.7;'>After reviewing your performance across all rounds, we are confident you will be a great addition to our team.</p>

        <div style='background:#fff;border:1px solid #e0e0e0;border-radius:8px;padding:20px;margin:20px 0;'>
            <h3 style='color:#333;margin:0 0 14px;'>📋 Offer Details</h3>
            <table style='width:100%;border-collapse:collapse;'>
                <tr><td style='padding:8px 0;color:#888;width:140px;'>Position</td><td style='color:#333;font-weight:600;'>{$job}</td></tr>
                <tr><td style='padding:8px 0;color:#888;'>Location</td><td style='color:#333;font-weight:600;'>{$location}</td></tr>
                <tr><td style='padding:8px 0;color:#888;'>Salary</td><td style='color:#333;font-weight:600;'>{$salary}</td></tr>
            </table>
        </div>

        <p style='color:#555;line-height:1.7;'>Our HR team will contact you shortly with the formal offer letter and onboarding details.</p>
        <p style='color:#555;'>Once again, congratulations and welcome aboard! 🚀</p>
        <p style='color:#555;font-weight:bold;'>The Recruitment Team</p>
        <hr style='border:none;border-top:1px solid #eee;margin:24px 0;'>
        <p style='color:#aaa;font-size:12px;text-align:center;'>This is an automated message. Please do not reply.</p>
    </div>";

    return insertEmailQueue($pdo, $data['email'], $data['full_name'], 'selected', $subject, $body, $application_id);
}

// ── 4. Rejected ───────────────────────────────────────────────────────────────
function queueRejectionEmail($pdo, $application_id) {
    $q = $pdo->prepare("
        SELECT u.email, u.full_name, j.title as job_title
        FROM applications a
        JOIN users u ON u.id = a.candidate_id
        JOIN jobs j  ON j.id = a.job_id
        WHERE a.id = ?
    ");
    $q->execute([$application_id]);
    $data = $q->fetch(PDO::FETCH_ASSOC);
    if (!$data) return false;

    $name = htmlspecialchars($data['full_name']);
    $job  = htmlspecialchars($data['job_title']);

    $subject = "Update on Your Application – {$data['job_title']}";
    $body    = "
    <div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;background:#f9f9f9;padding:30px;border-radius:10px;'>
        <div style='background:linear-gradient(135deg,#667eea,#764ba2);padding:30px;border-radius:8px;text-align:center;margin-bottom:24px;'>
            <h1 style='color:#fff;margin:0;font-size:22px;'>Application Update</h1>
            <p style='color:rgba(255,255,255,0.9);margin:8px 0 0;'>{$job}</p>
        </div>
        <p style='color:#333;font-size:16px;'>Dear <strong>{$name}</strong>,</p>
        <p style='color:#555;line-height:1.7;'>Thank you for your time and interest in the <strong>{$job}</strong> position, and for completing all rounds of our recruitment process.</p>
        <p style='color:#555;line-height:1.7;'>After careful consideration, we regret to inform you that we will not be moving forward with your application at this time. This was a difficult decision as we had many strong candidates.</p>
        <p style='color:#555;line-height:1.7;'>We were impressed by your dedication and encourage you to apply for future openings that match your profile.</p>

        <div style='background:#f0f4ff;border:1px solid #c5cef7;border-radius:6px;padding:16px 18px;margin:20px 0;'>
            <p style='color:#555;margin:0;line-height:1.7;'>
                <strong>💪 Keep Going!</strong><br>
                Every interview is a learning experience. We wish you all the very best in your future endeavors.
            </p>
        </div>

        <p style='color:#555;'>Thank you again for your time.</p>
        <p style='color:#555;font-weight:bold;'>The Recruitment Team</p>
        <hr style='border:none;border-top:1px solid #eee;margin:24px 0;'>
        <p style='color:#aaa;font-size:12px;text-align:center;'>This is an automated message. Please do not reply.</p>
    </div>";

    return insertEmailQueue($pdo, $data['email'], $data['full_name'], 'rejected', $subject, $body, $application_id);
}

// ── Internal: Insert into email_queue ─────────────────────────────────────────
function insertEmailQueue($pdo, $email, $name, $type, $subject, $body, $application_id = null) {
    $ins = $pdo->prepare("
        INSERT INTO email_queue 
            (recipient_email, recipient_name, email_type, subject, body_html, application_id, status)
        VALUES 
            (?, ?, ?, ?, ?, ?, 'pending')
    ");
    return $ins->execute([$email, $name, $type, $subject, $body, $application_id]);
}
?>
