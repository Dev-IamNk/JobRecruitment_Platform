<!-- FILE: pages/post_job.php (UPDATED WITH TEST CONFIG) -->
<?php 
require_once '../config/db.php';
redirectIfNotLoggedIn();
if (getUserType() != 'recruiter') {
    header('Location: candidate_dashboard.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Post Job</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .form-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .form-section h3 {
            margin-top: 0;
            color: #667eea;
            border-bottom: 2px solid #667eea;
            padding-bottom: 10px;
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        #auto_count_field {
            display: none;
        }
        .test-section {
            border: 2px solid #e0e0e0;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            background: white;
        }
        .test-section.enabled {
            border-color: #667eea;
            background: #f0f4ff;
        }
        .test-toggle {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }
        .test-toggle input[type="checkbox"] {
            width: 20px;
            height: 20px;
            margin-right: 10px;
        }
        .test-toggle label {
            font-size: 18px;
            font-weight: 600;
            margin: 0;
        }
        .test-config {
            display: none;
            margin-top: 15px;
        }
        .test-config.active {
            display: block;
        }
        .topic-row {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr 1fr 60px;
            gap: 10px;
            align-items: center;
            margin-bottom: 10px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 5px;
        }
        .topic-row label {
            margin: 0;
            font-size: 13px;
            font-weight: 600;
        }
        .topic-row input, .topic-row select {
            padding: 8px;
            font-size: 14px;
        }
        .topic-header {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr 1fr 60px;
            gap: 10px;
            margin-bottom: 5px;
            padding: 0 10px;
            font-weight: 600;
            font-size: 13px;
            color: #666;
            text-transform: uppercase;
        }
        .remove-btn {
            background: #dc3545;
            color: white;
            border: none;
            padding: 8px;
            border-radius: 4px;
            cursor: pointer;
        }
        .add-topic-btn {
            background: #28a745;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            margin-top: 10px;
        }
        .timer-options {
            display: flex;
            gap: 20px;
            margin-top: 10px;
        }
        .timer-option {
            display: flex;
            align-items: center;
            gap: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="nav-bar">
            <h1>Post New Job</h1>
            <div class="nav-links">
                <a href="recruiter_dashboard.php">Dashboard</a>
                <a href="../scripts/logout.php">Logout</a>
            </div>
        </div>
        
        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-error">
                <?php 
                if ($_GET['error'] == 'empty') echo 'Please fill all required fields!';
                else echo 'Failed to post job. Please try again.';
                ?>
            </div>
        <?php endif; ?>
        
        <form action="../scripts/job_handler.php" method="POST" id="job-form">
            
            <!-- SECTION 1: Basic Job Details -->
            <div class="form-section">
                <h3>📋 Basic Job Information</h3>
                
                <div class="form-group">
                    <label>Job Title *</label>
                    <input type="text" name="title" required placeholder="e.g. Senior Python Developer">
                </div>
                
                <div class="form-group">
                    <label>Job Description *</label>
                    <textarea name="description" required placeholder="Describe the role, responsibilities, and requirements..." rows="6"></textarea>
                </div>
                
                <div class="form-group">
                    <label>Required Skills * (comma-separated)</label>
                    <input type="text" name="required_skills" required placeholder="e.g. Python, Django, MySQL, REST API">
                    <small style="color: #666;">These skills will be matched with candidate resumes</small>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Location *</label>
                        <input type="text" name="location" required placeholder="e.g. New York, Remote">
                    </div>
                    
                    <div class="form-group">
                        <label>Salary Range</label>
                        <input type="text" name="salary_range" placeholder="e.g. $80,000 - $120,000">
                    </div>
                </div>
            </div>
            
            <!-- SECTION 2: Application Settings -->
            <div class="form-section">
                <h3>⚙️ Application Settings</h3>
                
                <div class="form-group">
                    <label>Application Deadline *</label>
                    <input type="datetime-local" name="application_deadline" required>
                    <small style="color: #666;">Applications will close after this date</small>
                </div>
                
                <div class="form-group">
                    <label>Shortlisting Mode *</label>
                    <select name="shortlisting_mode" id="shortlisting_mode" required>
                        <option value="manual">Manual Selection (I will review and select)</option>
                        <option value="automatic">Automatic (System selects top candidates)</option>
                    </select>
                </div>
                
                <div class="form-group" id="auto_count_field">
                    <label>Auto-select Top N Candidates</label>
                    <input type="number" name="auto_shortlist_count" value="10" min="1" max="100">
                    <small style="color: #666;">System will automatically shortlist this many top-scoring candidates</small>
                </div>
            </div>
            
            <!-- SECTION 3: Interview Schedule -->
            <div class="form-section">
                <h3>📅 Interview Schedule (Optional)</h3>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Interview Date & Time</label>
                        <input type="datetime-local" name="interview_date">
                        <small style="color: #666;">Schedule can be set later</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Interview Link (Zoom/Google Meet)</label>
                        <input type="url" name="interview_link" placeholder="https://zoom.us/j/123456789">
                        <small style="color: #666;">Meeting link will be sent to shortlisted candidates</small>
                    </div>
                </div>
            </div>
            
            <!-- SECTION 4: Test Configuration -->
            <div class="form-section">
                <h3>📝 Online Assessment Tests (Optional)</h3>
                <p style="color: #666; margin-bottom: 20px;">Configure tests for shortlisted candidates</p>
                
                <!-- Aptitude Test -->
                <div class="test-section" id="aptitude-section">
                    <div class="test-toggle">
                        <input type="checkbox" id="enable-aptitude" name="enable_aptitude" value="1" onchange="toggleTest('aptitude')">
                        <label for="enable-aptitude">Enable Aptitude Test</label>
                    </div>
                    
                    <div class="test-config" id="aptitude-config">
                        <input type="hidden" name="aptitude_topics" id="aptitude-topics-data">
                        
                        <h4 style="margin-top: 0;">Numerical Reasoning</h4>
                        <div class="topic-header">
                            <span>Topic</span>
                            <span>Easy</span>
                            <span>Medium</span>
                            <span>Hard</span>
                            <span>Total</span>
                            <span></span>
                        </div>
                        <div id="numerical-topics">
                            <div class="topic-row">
                                <select name="numerical_topic[]">
                                    <option value="time_work">Time & Work</option>
                                    <option value="profit_loss">Profit & Loss</option>
                                    <option value="percentage">Percentages</option>
                                    <option value="ratio_proportion">Ratio & Proportion</option>
                                    <option value="simple_interest">Simple & Compound Interest</option>
                                </select>
                                <input type="number" name="numerical_easy[]" placeholder="Easy" min="0" max="20" value="2">
                                <input type="number" name="numerical_medium[]" placeholder="Medium" min="0" max="20" value="2">
                                <input type="number" name="numerical_hard[]" placeholder="Hard" min="0" max="20" value="1">
                                <span style="font-weight: 600; text-align: center;">Total: <span class="topic-total">5</span></span>
                                <button type="button" class="remove-btn" onclick="removeRow(this)">✕</button>
                            </div>
                        </div>
                        <button type="button" class="add-topic-btn" onclick="addNumericalTopic()">+ Add Topic</button>
                        
                        <h4 style="margin-top: 20px;">Logical Reasoning</h4>
                        <div class="topic-header">
                            <span>Topic</span>
                            <span>Easy</span>
                            <span>Medium</span>
                            <span>Hard</span>
                            <span>Total</span>
                            <span></span>
                        </div>
                        <div id="logical-topics">
                            <div class="topic-row">
                                <select name="logical_topic[]">
                                    <option value="patterns">Number/Letter Patterns</option>
                                    <option value="puzzles">Puzzles</option>
                                    <option value="blood_relations">Blood Relations</option>
                                    <option value="syllogism">Syllogism</option>
                                    <option value="coding_decoding">Coding-Decoding</option>
                                </select>
                                <input type="number" name="logical_easy[]" placeholder="Easy" min="0" max="20" value="3">
                                <input type="number" name="logical_medium[]" placeholder="Medium" min="0" max="20" value="2">
                                <input type="number" name="logical_hard[]" placeholder="Hard" min="0" max="20" value="0">
                                <span style="font-weight: 600; text-align: center;">Total: <span class="topic-total">5</span></span>
                                <button type="button" class="remove-btn" onclick="removeRow(this)">✕</button>
                            </div>
                        </div>
                        <button type="button" class="add-topic-btn" onclick="addLogicalTopic()">+ Add Topic</button>
                        
                        <h4 style="margin-top: 20px;">Verbal Reasoning</h4>
                        <div class="topic-header">
                            <span>Topic</span>
                            <span>Easy</span>
                            <span>Medium</span>
                            <span>Hard</span>
                            <span>Total</span>
                            <span></span>
                        </div>
                        <div id="verbal-topics">
                            <div class="topic-row">
                                <select name="verbal_topic[]">
                                    <option value="synonyms">Synonyms</option>
                                    <option value="antonyms">Antonyms</option>
                                    <option value="sentence_correction">Sentence Correction</option>
                                    <option value="comprehension">Reading Comprehension</option>
                                    <option value="fill_blanks">Fill in the Blanks</option>
                                </select>
                                <input type="number" name="verbal_easy[]" placeholder="Easy" min="0" max="20" value="3">
                                <input type="number" name="verbal_medium[]" placeholder="Medium" min="0" max="20" value="2">
                                <input type="number" name="verbal_hard[]" placeholder="Hard" min="0" max="20" value="0">
                                <span style="font-weight: 600; text-align: center;">Total: <span class="topic-total">5</span></span>
                                <button type="button" class="remove-btn" onclick="removeRow(this)">✕</button>
                            </div>
                        </div>
                        <button type="button" class="add-topic-btn" onclick="addVerbalTopic()">+ Add Topic</button>
                        
                        <h4 style="margin-top: 20px;">Timer Settings</h4>
                        <div class="timer-options">
                            <div class="timer-option">
                                <input type="radio" name="aptitude_timer_type" value="overall" id="apt-overall" checked>
                                <label for="apt-overall">Overall Timer:</label>
                                <input type="number" name="aptitude_overall_time" value="60" min="10" max="180" style="width: 80px;"> minutes
                            </div>
                            <div class="timer-option">
                                <input type="radio" name="aptitude_timer_type" value="sectional" id="apt-sectional">
                                <label for="apt-sectional">Sectional Timer:</label>
                                <input type="number" name="aptitude_sectional_time" value="20" min="5" max="60" style="width: 80px;"> minutes per section
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Technical MCQ Test -->
                <div class="test-section" id="technical-section">
                    <div class="test-toggle">
                        <input type="checkbox" id="enable-technical" name="enable_technical" value="1" onchange="toggleTest('technical')">
                        <label for="enable-technical">Enable Technical MCQ Test</label>
                    </div>
                    
                    <div class="test-config" id="technical-config">
                        <div class="form-group">
                            <label>Select Technology/Language</label>
                            <select name="technical_technology" id="tech-technology">
                                <option value="python">Python</option>
                                <option value="java">Java</option>
                                <option value="javascript">JavaScript</option>
                                <option value="php">PHP</option>
                                <option value="cpp">C++</option>
                                <option value="sql">SQL/Database</option>
                            </select>
                        </div>
                        
                        <h4>Technical Concepts</h4>
                        <div class="topic-header">
                            <span>Concept</span>
                            <span>Easy</span>
                            <span>Medium</span>
                            <span>Hard</span>
                            <span>Total</span>
                            <span></span>
                        </div>
                        <div id="technical-topics">
                            <div class="topic-row">
                                <select name="technical_topic[]" class="tech-topic-select">
                                    <!-- Options will be populated by JavaScript based on technology -->
                                </select>
                                <input type="number" name="technical_easy[]" placeholder="Easy" min="0" max="20" value="3">
                                <input type="number" name="technical_medium[]" placeholder="Medium" min="0" max="20" value="3">
                                <input type="number" name="technical_hard[]" placeholder="Hard" min="0" max="20" value="2">
                                <span style="font-weight: 600; text-align: center;">Total: <span class="topic-total">8</span></span>
                                <button type="button" class="remove-btn" onclick="removeRow(this)">✕</button>
                            </div>
                        </div>
                        <button type="button" class="add-topic-btn" onclick="addTechnicalTopic()">+ Add Concept</button>
                        
                        <div class="form-group" style="margin-top: 20px;">
                            <label>Test Duration (minutes)</label>
                            <input type="number" name="technical_time" value="30" min="10" max="120" style="width: 150px;">
                        </div>
                    </div>
                </div>
                
                <!-- Coding Test -->
                <div class="test-section" id="coding-section">
                    <div class="test-toggle">
                        <input type="checkbox" id="enable-coding" name="enable_coding" value="1" onchange="toggleTest('coding')">
                        <label for="enable-coding">Enable Coding Test</label>
                    </div>
                    
                    <div class="test-config" id="coding-config">
                        <div class="form-group">
                            <label>Number of Coding Problems</label>
                            <select name="coding_num_problems" id="num-problems" onchange="updateCodingProblems()">
                                <option value="1">1 Problem</option>
                                <option value="2" selected>2 Problems</option>
                                <option value="3">3 Problems</option>
                            </select>
                        </div>
                        
                        <div id="coding-problems">
                            <div class="coding-problem">
                                <h4>Problem 1</h4>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label>Difficulty</label>
                                        <select name="coding_difficulty[]">
                                            <option value="easy">Easy</option>
                                            <option value="medium" selected>Medium</option>
                                            <option value="hard">Hard</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>Topic</label>
                                        <select name="coding_topic[]">
                                            <option value="arrays">Arrays</option>
                                            <option value="strings">Strings</option>
                                            <option value="linked_lists">Linked Lists</option>
                                            <option value="trees">Trees</option>
                                            <option value="graphs">Graphs</option>
                                            <option value="dynamic_programming">Dynamic Programming</option>
                                            <option value="sorting_searching">Sorting & Searching</option>
                                            <option value="greedy">Greedy Algorithms</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="coding-problem">
                                <h4>Problem 2</h4>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label>Difficulty</label>
                                        <select name="coding_difficulty[]">
                                            <option value="easy">Easy</option>
                                            <option value="medium">Medium</option>
                                            <option value="hard" selected>Hard</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>Topic</label>
                                        <select name="coding_topic[]">
                                            <option value="arrays">Arrays</option>
                                            <option value="strings">Strings</option>
                                            <option value="linked_lists">Linked Lists</option>
                                            <option value="trees">Trees</option>
                                            <option value="graphs">Graphs</option>
                                            <option value="dynamic_programming" selected>Dynamic Programming</option>
                                            <option value="sorting_searching">Sorting & Searching</option>
                                            <option value="greedy">Greedy Algorithms</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Time Per Problem (minutes)</label>
                            <input type="number" name="coding_time_per_problem" value="30" min="15" max="60" style="width: 150px;">
                        </div>
                    </div>
                </div>
            </div>
            
            <button type="submit">Post Job</button>
            <a href="recruiter_dashboard.php" class="btn btn-secondary">Cancel</a>
        </form>
    </div>
    
    <script>
        // Technical topics by technology
        const technicalTopics = {
            python: ['OOP Concepts', 'Data Structures', 'Django/Flask', 'NumPy/Pandas', 'Exception Handling', 'Decorators', 'Generators'],
            java: ['OOP Concepts', 'Collections Framework', 'Spring Boot', 'Multithreading', 'Exception Handling', 'JDBC', 'Servlets/JSP'],
            javascript: ['ES6 Features', 'DOM Manipulation', 'Async/Promises', 'React/Vue', 'Node.js', 'Event Loop', 'Closures'],
            php: ['OOP in PHP', 'Laravel Framework', 'MySQL Integration', 'Sessions/Cookies', 'RESTful APIs', 'Security'],
            cpp: ['OOP Concepts', 'STL', 'Pointers', 'Memory Management', 'Templates', 'Exception Handling'],
            sql: ['SQL Queries', 'Joins', 'Indexing', 'Normalization', 'Stored Procedures', 'Transactions']
        };
        
        // Show/hide test config
        function toggleTest(testType) {
            const checkbox = document.getElementById(`enable-${testType}`);
            const config = document.getElementById(`${testType}-config`);
            const section = document.getElementById(`${testType}-section`);
            
            if (checkbox.checked) {
                config.classList.add('active');
                section.classList.add('enabled');
            } else {
                config.classList.remove('active');
                section.classList.remove('enabled');
            }
        }
        
        // Update total questions
        function updateTotal(row) {
            const inputs = row.querySelectorAll('input[type="number"]');
            let total = 0;
            inputs.forEach(input => {
                total += parseInt(input.value) || 0;
            });
            row.querySelector('.topic-total').textContent = total;
        }
        
        // Add event listeners to all topic rows
        document.querySelectorAll('.topic-row').forEach(row => {
            row.querySelectorAll('input[type="number"]').forEach(input => {
                input.addEventListener('input', () => updateTotal(row));
            });
        });
        
        // Remove topic row
        function removeRow(btn) {
            btn.closest('.topic-row').remove();
        }
        
        // Add numerical topic
        function addNumericalTopic() {
            const container = document.getElementById('numerical-topics');
            const row = container.querySelector('.topic-row').cloneNode(true);
            row.querySelectorAll('input').forEach(input => input.value = '0');
            updateTotal(row);
            row.querySelectorAll('input').forEach(input => {
                input.addEventListener('input', () => updateTotal(row));
            });
            container.appendChild(row);
        }
        
        // Add logical topic
        function addLogicalTopic() {
            const container = document.getElementById('logical-topics');
            const row = container.querySelector('.topic-row').cloneNode(true);
            row.querySelectorAll('input').forEach(input => input.value = '0');
            updateTotal(row);
            row.querySelectorAll('input').forEach(input => {
                input.addEventListener('input', () => updateTotal(row));
            });
            container.appendChild(row);
        }
        
        // Add verbal topic
        function addVerbalTopic() {
            const container = document.getElementById('verbal-topics');
            const row = container.querySelector('.topic-row').cloneNode(true);
            row.querySelectorAll('input').forEach(input => input.value = '0');
            updateTotal(row);
            row.querySelectorAll('input').forEach(input => {
                input.addEventListener('input', () => updateTotal(row));
            });
            container.appendChild(row);
        }
        
        // Add technical topic
        function addTechnicalTopic() {
            const container = document.getElementById('technical-topics');
            const row = container.querySelector('.topic-row').cloneNode(true);
            row.querySelectorAll('input').forEach(input => input.value = '0');
            updateTotal(row);
            row.querySelectorAll('input').forEach(input => {
                input.addEventListener('input', () => updateTotal(row));
            });
            updateTechnicalTopics(row.querySelector('.tech-topic-select'));
            container.appendChild(row);
        }
        
        // Update technical topics based on technology
        function updateTechnicalTopics(selectElement = null) {
            const technology = document.getElementById('tech-technology').value;
            const topics = technicalTopics[technology];
            
            const selects = selectElement ? [selectElement] : document.querySelectorAll('.tech-topic-select');
            
            selects.forEach(select => {
                select.innerHTML = '';
                topics.forEach(topic => {
                    const option = document.createElement('option');
                    option.value = topic.toLowerCase().replace(/\s+/g, '_').replace(/\//g, '_');
                    option.textContent = topic;
                    select.appendChild(option);
                });
            });
        }
        
        // Initialize technical topics
        updateTechnicalTopics();
        document.getElementById('tech-technology').addEventListener('change', () => updateTechnicalTopics());
        
        // Update coding problems
        function updateCodingProblems() {
            const num = parseInt(document.getElementById('num-problems').value);
            const container = document.getElementById('coding-problems');
            const currentProblems = container.querySelectorAll('.coding-problem').length;
            
            if (num > currentProblems) {
                for (let i = currentProblems; i < num; i++) {
                    const problem = container.querySelector('.coding-problem').cloneNode(true);
                    problem.querySelector('h4').textContent = `Problem ${i + 1}`;
                    container.appendChild(problem);
                }
            } else if (num < currentProblems) {
                const problems = container.querySelectorAll('.coding-problem');
                for (let i = num; i < currentProblems; i++) {
                    problems[i].remove();
                }
            }
        }
        
        // Show/hide auto shortlist count
        document.getElementById('shortlisting_mode').addEventListener('change', function() {
            const autoCountField = document.getElementById('auto_count_field');
            if (this.value === 'automatic') {
                autoCountField.style.display = 'block';
            } else {
                autoCountField.style.display = 'none';
            }
        });
    </script>
</body>
</html>