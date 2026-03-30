<!-- FILE: pages/apply_job.php -->
<?php 
require_once '../config/db.php';
redirectIfNotLoggedIn();
if (getUserType() != 'candidate') {
    header('Location: recruiter_dashboard.php');
    exit();
}

$job_id = intval($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT j.*, u.full_name as recruiter_name FROM jobs j JOIN users u ON j.recruiter_id = u.id WHERE j.id = ?");
$stmt->execute([$job_id]);
$job = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$job) {
    header('Location: view_jobs.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Apply — <?php echo htmlspecialchars($job['title']); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;1,400&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --bg: #0d0d0f;
            --surface: #141418;
            --border: rgba(255,255,255,0.07);
            --accent: #c8a96e;
            --accent-dim: rgba(200,169,110,0.1);
            --text: #f0ece4;
            --muted: #7a7670;
        }

        html, body {
            min-height: 100%;
            background: var(--bg);
            color: var(--text);
            font-family: 'DM Sans', sans-serif;
            font-weight: 300;
        }

        body {
            background-image:
                radial-gradient(ellipse 70% 50% at 80% 5%, rgba(200,169,110,0.05) 0%, transparent 55%),
                radial-gradient(ellipse 40% 60% at 5% 95%, rgba(80,60,130,0.04) 0%, transparent 55%);
        }

        body::before {
            content: '';
            position: fixed; inset: 0;
            background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='0.035'/%3E%3C/svg%3E");
            pointer-events: none; z-index: 0;
        }

        .page {
            position: relative; z-index: 1;
            max-width: 900px; margin: 0 auto;
            padding: 32px 24px 72px;
        }

        /* Nav */
        .nav-bar {
            display: flex; align-items: center; justify-content: space-between;
            padding-bottom: 28px; border-bottom: 1px solid var(--border);
            margin-bottom: 40px;
        }
        .nav-brand { display: flex; align-items: center; gap: 12px; }
        .logo-mark {
            width: 34px; height: 34px; border-radius: 9px; background: var(--accent);
            display: flex; align-items: center; justify-content: center;
            font-family: 'Playfair Display', serif; font-size: 16px; font-weight: 600; color: #0d0d0f;
        }
        .nav-title { font-family: 'Playfair Display', serif; font-size: 16px; color: var(--text); }
        .nav-links { display: flex; align-items: center; gap: 6px; }
        .nav-links a {
            color: var(--muted); text-decoration: none; font-size: 13px;
            padding: 7px 14px; border-radius: 8px; border: 1px solid transparent;
            transition: color 0.2s, border-color 0.2s;
        }
        .nav-links a:hover { color: var(--text); border-color: var(--border); }
        .nav-links a.logout { color: rgba(224,92,92,0.7); }
        .nav-links a.logout:hover { color: #e05c5c; border-color: rgba(224,92,92,0.2); }

        /* Alert */
        .alert {
            padding: 14px 18px; border-radius: 10px; font-size: 13px;
            margin-bottom: 28px; border: 1px solid;
            animation: fadeUp 0.4s cubic-bezier(0.22,1,0.36,1) both;
        }
        .alert-error { background: rgba(224,92,92,0.1); border-color: rgba(224,92,92,0.25); color: #e87878; }

        /* Layout */
        .content-grid {
            display: grid;
            grid-template-columns: 1fr 380px;
            gap: 24px;
            animation: fadeUp 0.5s cubic-bezier(0.22,1,0.36,1) both;
        }

        /* Job info panel */
        .job-panel {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 16px;
            overflow: hidden;
        }

        .job-panel-header {
            padding: 28px 28px 24px;
            border-bottom: 1px solid var(--border);
        }

        .eyebrow {
            font-size: 11px; letter-spacing: 0.1em; text-transform: uppercase;
            color: var(--accent); margin-bottom: 10px;
        }

        .job-title {
            font-family: 'Playfair Display', serif;
            font-size: 22px; font-weight: 400; color: var(--text);
            margin-bottom: 16px; line-height: 1.3;
        }

        .job-badges {
            display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 16px;
        }

        .badge {
            display: inline-flex; align-items: center; gap: 6px;
            font-size: 12px; color: var(--muted);
            padding: 5px 10px; border-radius: 20px;
            background: rgba(255,255,255,0.03);
            border: 1px solid var(--border);
        }

        .badge svg { opacity: 0.5; flex-shrink: 0; }

        .skills-row { display: flex; flex-wrap: wrap; gap: 6px; }
        .skill-chip {
            font-size: 11px; padding: 3px 9px; border-radius: 20px;
            background: var(--accent-dim); border: 1px solid rgba(200,169,110,0.15);
            color: var(--accent);
        }

        .job-description {
            padding: 24px 28px;
            border-bottom: 1px solid var(--border);
        }

        .desc-label {
            font-size: 11px; letter-spacing: 0.08em; text-transform: uppercase;
            color: var(--muted); margin-bottom: 10px;
        }

        .desc-text {
            font-size: 13px; line-height: 1.75; color: rgba(240,236,228,0.7);
        }

        .job-recruiter {
            padding: 18px 28px;
            display: flex; align-items: center; gap: 12px;
        }

        .recruiter-avatar {
            width: 36px; height: 36px; border-radius: 50%;
            background: var(--accent-dim);
            border: 1px solid rgba(200,169,110,0.2);
            display: flex; align-items: center; justify-content: center;
            font-family: 'Playfair Display', serif; font-size: 15px; color: var(--accent);
            flex-shrink: 0;
        }

        .recruiter-label { font-size: 11px; color: var(--muted); margin-bottom: 2px; letter-spacing: 0.04em; text-transform: uppercase; }
        .recruiter-name { font-size: 13px; color: var(--text); }

        /* Application form */
        .form-panel {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 16px;
            overflow: hidden;
            align-self: start;
        }

        .form-panel-header {
            padding: 24px 24px 20px;
            border-bottom: 1px solid var(--border);
        }

        .form-panel-header h2 {
            font-family: 'Playfair Display', serif;
            font-size: 18px; font-weight: 400; color: var(--text);
            margin-bottom: 4px;
        }

        .form-panel-header p { font-size: 12px; color: var(--muted); }

        .form-body { padding: 24px; }

        .form-group { margin-bottom: 20px; }

        label {
            display: block; font-size: 11px; letter-spacing: 0.08em;
            text-transform: uppercase; color: var(--muted); margin-bottom: 8px;
        }

        .file-input-wrap { position: relative; }

        .file-input-wrap input[type="file"] {
            position: absolute; inset: 0; opacity: 0;
            cursor: pointer; width: 100%; height: 100%; z-index: 2;
        }

        .file-display {
            display: flex; align-items: center; gap: 10px;
            padding: 14px 16px;
            background: rgba(255,255,255,0.03);
            border: 1px dashed rgba(200,169,110,0.25);
            border-radius: 10px;
            font-size: 13px; color: var(--muted);
            transition: border-color 0.2s, background 0.2s, color 0.2s;
            cursor: pointer;
        }

        .file-input-wrap:hover .file-display {
            border-color: rgba(200,169,110,0.45);
            background: var(--accent-dim); color: var(--accent);
        }

        .file-formats { font-size: 11px; color: var(--muted); margin-top: 6px; opacity: 0.6; }
        .file-name-display { font-size: 12px; color: var(--accent); margin-top: 6px; display: none; }

        textarea {
            width: 100%;
            background: rgba(255,255,255,0.04);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 12px 14px;
            font-family: 'DM Sans', sans-serif;
            font-size: 13px; color: var(--text);
            resize: vertical; min-height: 110px;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        textarea:focus {
            border-color: rgba(200,169,110,0.4);
            box-shadow: 0 0 0 3px rgba(200,169,110,0.07);
        }
        textarea::placeholder { color: rgba(122,118,112,0.4); }

        .form-actions { display: flex; flex-direction: column; gap: 10px; margin-top: 4px; }

        .btn-submit {
            width: 100%; padding: 13px;
            background: var(--accent); color: #0d0d0f; border: none;
            border-radius: 10px; font-family: 'DM Sans', sans-serif;
            font-size: 13px; font-weight: 500; letter-spacing: 0.04em;
            cursor: pointer; transition: opacity 0.2s, transform 0.15s;
        }
        .btn-submit:hover { opacity: 0.86; transform: translateY(-1px); }
        .btn-submit:active { transform: translateY(0); }

        .btn-cancel {
            width: 100%; padding: 12px;
            background: transparent; color: var(--muted);
            border: 1px solid var(--border); border-radius: 10px;
            font-family: 'DM Sans', sans-serif; font-size: 13px;
            text-align: center; text-decoration: none; cursor: pointer;
            transition: color 0.2s, border-color 0.2s;
            display: block;
        }
        .btn-cancel:hover { color: var(--text); border-color: rgba(255,255,255,0.15); }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(16px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        @media (max-width: 720px) {
            .content-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<div class="page">

    <nav class="nav-bar">
        <div class="nav-brand">
            <div class="logo-mark">R</div>
            <span class="nav-title">RPA Recruitment</span>
        </div>
        <div class="nav-links">
            <a href="view_jobs.php">Jobs</a>
            <a href="candidate_dashboard.php">Dashboard</a>
        </div>
    </nav>

    <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-error">
            <?php
            if ($_GET['error'] == 'filetype') echo 'Invalid file type. Please upload a PDF, DOC, DOCX, or TXT file.';
            elseif ($_GET['error'] == 'noresume') echo 'Please upload your resume to continue.';
            elseif ($_GET['error'] == 'jobnotfound') echo 'This job could not be found. It may have been removed.';
            else echo 'Something went wrong. Please try again.';
            ?>
        </div>
    <?php endif; ?>

    <div class="content-grid">

        <!-- Left: Job Details -->
        <div class="job-panel">
            <div class="job-panel-header">
                <div class="eyebrow">Open Position</div>
                <h1 class="job-title"><?php echo htmlspecialchars($job['title']); ?></h1>

                <div class="job-badges">
                    <?php if (!empty($job['location'])): ?>
                    <span class="badge">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
                        <?php echo htmlspecialchars($job['location']); ?>
                    </span>
                    <?php endif; ?>
                    <?php if (!empty($job['salary_range'])): ?>
                    <span class="badge">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>
                        <?php echo htmlspecialchars($job['salary_range']); ?>
                    </span>
                    <?php endif; ?>
                </div>

                <div class="skills-row">
                    <?php foreach (explode(',', $job['required_skills']) as $skill): ?>
                        <span class="skill-chip"><?php echo htmlspecialchars(trim($skill)); ?></span>
                    <?php endforeach; ?>
                </div>
            </div>

            <?php if (!empty($job['description'])): ?>
            <div class="job-description">
                <div class="desc-label">About the Role</div>
                <div class="desc-text"><?php echo nl2br(htmlspecialchars($job['description'])); ?></div>
            </div>
            <?php endif; ?>

            <div class="job-recruiter">
                <div class="recruiter-avatar"><?php echo strtoupper(substr($job['recruiter_name'], 0, 1)); ?></div>
                <div>
                    <div class="recruiter-label">Posted by</div>
                    <div class="recruiter-name"><?php echo htmlspecialchars($job['recruiter_name']); ?></div>
                </div>
            </div>
        </div>

        <!-- Right: Application Form -->
        <div class="form-panel">
            <div class="form-panel-header">
                <h2>Submit Application</h2>
                <p>Your resume will be reviewed by the recruiter</p>
            </div>

            <div class="form-body">
                <form action="../scripts/apply_handler.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="job_id" value="<?php echo $job['id']; ?>">

                    <div class="form-group">
                        <label>Resume <span style="color:var(--accent)">*</span></label>
                        <div class="file-input-wrap">
                            <input type="file" name="resume" required accept=".pdf,.doc,.docx,.txt"
                                onchange="showFilename(this)">
                            <div class="file-display">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                                Click to upload your resume
                            </div>
                        </div>
                        <div class="file-formats">PDF, DOC, DOCX or TXT · Max 5 MB</div>
                        <div class="file-name-display" id="filename-display"></div>
                    </div>

                    <div class="form-group">
                        <label>Cover Letter <span style="opacity:0.4;font-size:10px;text-transform:none;">(optional)</span></label>
                        <textarea name="cover_letter" placeholder="Tell us why you're a great fit for this role…"></textarea>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn-submit">Submit Application →</button>
                        <a href="view_jobs.php" class="btn-cancel">Cancel</a>
                    </div>
                </form>
            </div>
        </div>

    </div>
</div>

<script>
function showFilename(input) {
    const display = document.getElementById('filename-display');
    if (input.files && input.files[0]) {
        display.textContent = '📎 ' + input.files[0].name;
        display.style.display = 'block';
    }
}
</script>
</body>
</html>