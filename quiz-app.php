<?php
// quiz_app.php
// Single-file Quiz system (Phase 1 + Phase 2 merged)
// - 5 categories x 3 questions = 15
// - Random shuffle, 5 questions per page
// - Category-wise scoring and grade-based Chart.js graph preview
// - Saves results to results/results.csv (server-side), ready for email integration
// =====================================================

// Handle server-side POST when JS submits results (saves CSV)
// This endpoint accepts a form field 'payload' (JSON string).
include 'config.php';
include 'header-files.php';
if (isset($_SESSION['qa_user'])) {
    
    // Get current user details
    $user_id = $_SESSION['qa_user'];
    $user_query = mysqli_query($con, "SELECT u_type, u_quiz_submitted FROM users WHERE u_id = '$user_id'");
    $user_data = mysqli_fetch_assoc($user_query);
    $user_type = $user_data['u_type'];
    $quiz_submitted_count = $user_data['u_quiz_submitted'];
    
    // Check quiz submission limit based on user type
    function canSubmitQuiz($user_type, $quiz_submitted_count) {
        if ($user_type == 'basic') {
            return $quiz_submitted_count < 1; // Basic users can only submit once
        }
        return true; // Silver, Gold, Platinum users can submit unlimited times
    }
    
    // Check if user has reached their submission limit
    if ($user_type == 'basic' && $quiz_submitted_count >= 1) {
        $_SESSION['toastr_message'] = "Basic package users can only submit 1 quiz. Please upgrade to submit more quizzes.";
        $_SESSION['toastr_type'] = "warning";
        header("Location: settings.php");
        exit();
    }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['payload'])) {
    header('Content-Type: application/json; charset=utf-8');
    $payload = json_decode($_POST['payload'], true);
    if (!is_array($payload)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Invalid payload']);
        exit;
    }

    // Check quiz submission limit before saving
    if (!canSubmitQuiz($user_type, $quiz_submitted_count)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Quiz submission limit reached for your package']);
        exit;
    }

    // Save CSV
    $dir = __DIR__ . '/results';
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    $file = $dir . '/results.csv';
    $isNew = !file_exists($file);
    $fp = @fopen($file, 'a');
    if (!$fp) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Cannot open results file']);
        exit;
    }

    // Flatten the payload for CSV (timestamp, name, email, total, category scores as columns)
    // We expect payload to contain: name, email, time_seconds, submitted_at, totals: {category: points}
    $row = [];
    if ($isNew) {
        // header
        $headers = ['submitted_at','name','email','time_seconds','total_points','user_id','user_type'];
        // add category columns
        foreach ($payload['totals'] ?? [] as $cat => $v) $headers[] = $cat;
        fputcsv($fp, $headers);
    }
    $row[] = $payload['submitted_at'] ?? date('c');
    $row[] = $payload['name'] ?? '';
    $row[] = $payload['email'] ?? '';
    $row[] = $payload['time_seconds'] ?? '';
    $row[] = $payload['total_points'] ?? '';
    $row[] = $user_id; // Add user ID
    $row[] = $user_type; // Add user type
    foreach ($payload['totals'] ?? [] as $cat => $v) $row[] = $v;
    fputcsv($fp, $row);
    fclose($fp);

    // Update quiz submission count in users table
    $new_count = $quiz_submitted_count + 1;
    mysqli_query($con, "UPDATE users SET u_quiz_submitted = '$new_count' WHERE u_id = '$user_id'");

    echo json_encode(['ok' => true, 'saved_file' => 'results/results.csv', 'submission_count' => $new_count]);
    exit;
}

if(isset($_POST['quizFrom'])){
    $_SESSION['name'] = $_POST['name'];
    $_SESSION['email'] = $_POST['email'];
    $_SESSION['email1'] = $_POST['email1'];
    $_SESSION['phone'] = $_POST['phone'];

    header("Location: https://demotestlink.com/form/quiz-complete/");
    exit;
}

// Otherwise render the single-page app (front-end + all logic)
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <script type="text/javascript" src="https://translate.google.com/translate_a/element.js?cb=googleTranslateElementInit"></script>
    <title>15-Question Quiz</title>
    <link rel="stylesheet" href="data:,">
    <style>
        :root{
            --bg:#f6f8fb;
            --card:#fff;
            --muted:#6b7280;
            --primary:#0b79d0;
            --good:#16a34a;
            --okay:#f59e0b;
            --bad:#ef4444;
            --radius:12px;
            --shadow: 0 10px 25px rgba(2,6,23,.08);
        }
        *{box-sizing:border-box}
        body{margin:0;font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,"Helvetica Neue",Arial;background:var(--bg);color:#0f172a}
        .wrap{max-width:1100px;margin:28px auto;padding:18px}
        .card{background:var(--card);border-radius:var(--radius);padding:18px;box-shadow:var(--shadow)}
        header{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:18px}
        header h1{margin:0;font-size:20px}
        .subtitle{color:var(--muted);font-size:13px}
        .quiz-area{display:flex;gap:18px;align-items:flex-start}
        .main{flex:1}
        .side{width:300px}
        .question{background:#fbfdff;border-radius:10px;padding:12px;margin-bottom:12px;border:1px solid rgba(2,6,23,.04)}
        .q-title{font-weight:600;margin:0 0 8px}
        .options label{display:block;padding:10px;border-radius:8px;background:#fff;border:1px solid rgba(2,6,23,.06);margin-bottom:8px;cursor:pointer}
        .options input{margin-right:8px}
        .pager{display:flex;align-items:center;justify-content:space-between;margin-top:12px;gap:12px}
        button{border:0;padding:10px 14px;border-radius:8px;cursor:pointer;font-weight:700}
        .btn-primary{background:var(--primary);color:#fff}
        .btn-ghost{background:transparent;border:1px solid rgba(2,6,23,.06)}
        .muted{color:var(--muted);font-size:13px}
        .center{display:flex;align-items:center;justify-content:center}
        .thank-card{text-align:center;padding:36px}
        .small{font-size:13px;color:var(--muted)}
        /* Quiz limit warning */
        .limit-warning {
            background: #fef3cd;
            border: 1px solid #ffc107;
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 18px;
            color: #856404;
        }
        .limit-warning.hidden {
            display: none;
        }
        /* Chart container */
        #result-chart { width:100%; max-height:420px; }
        /* responsive */
        .quiz-form {
            max-width: 350px;
            margin: 20px auto;
            font-family: Arial, sans-serif;
        }

        .quiz-form input,
        .quiz-form button {
            width: 100%;
            padding: 10px;
            margin: 6px 0;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 14px;
        }

        .quiz-form button {
            background: #28a745;
            color: #fff;
            border: none;
            cursor: pointer;
        }

        .quiz-form button:hover {
            background: #218838;
        }
        /* Center the dropdown button and dropdown content */
        .dropdown {
            position: relative;
            display: inline-block;
            width: 100%;
            text-align: center; /* Center the content */
            margin: 0 auto; /* Center the dropdown itself */
        }

        .dropbtn {
            padding: 10px 16px;
            font-size: 16px;
            cursor: pointer;
            border: 1px solid #ccc;
            background: #fff;
            border-radius: 5px;
        }

        .dropdown-content {
            display: none;
            position: absolute;
            background: #fff;
            border: 1px solid #ccc;
            min-width: 150px;
            z-index: 9999;
            left: 50%; /* Center the dropdown content */
            transform: translateX(-50%); /* Adjust positioning */
        }

        .dropdown-content div {
            padding: 8px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .dropdown-content div:hover {
            background: #f0f0f0;
        }

        .dropdown img {
            width: 24px;
            height: 16px;
        }

        .dropdown:hover .dropdown-content {
            display: block;
        }

        @media (max-width:900px){
            .quiz-area{flex-direction:column}
            .side{width:100%}
        }
    </style>
</head>
<body>
<div class="dropdown">
    <button class="dropbtn">🌐 Select Language</button>
    <div class="dropdown-content">
        <div onclick="changeLang('en')"><img src="https://flagcdn.com/w20/us.png"> English</div>
        <div onclick="changeLang('ar')"><img src="https://flagcdn.com/w20/sa.png"> Arabic</div>
        <div onclick="changeLang('es')"><img src="https://flagcdn.com/w20/es.png"> Spanish</div>
        <div onclick="changeLang('fr')"><img src="https://flagcdn.com/w20/fr.png"> French</div>
        <div onclick="changeLang('zh-CN')"><img src="https://flagcdn.com/w20/cn.png"> Chinese</div>
        <div onclick="changeLang('so')"><img src="https://flagcdn.com/w20/so.png"> Somali</div>
    </div>
</div>
<!-- Google Translate Widget (hidden) -->
<div id="google_translate_element" style="display:none;"></div>
<div class="wrap">
    <div class="card" id="app-card">
        <!-- Quiz Limit Warning -->
        <div id="limitWarning" class="limit-warning <?= ($user_type == 'basic' && $quiz_submitted_count >= 1) ? '' : 'hidden' ?>">
            <strong>⚠️ Quiz Submission Limit Reached</strong><br>
            Your Basic package allows only 1 quiz submission. You have already submitted <?= $quiz_submitted_count ?> quiz(es). 
            Please upgrade your package to submit more quizzes.
        </div>
        
        <header>
            <div>
                <h1>15-Question Quiz</h1>
                <div class="subtitle" id="subtitle">
                    Answer the questions. 5 per page. Questions are randomized.
                    <?php if($user_type == 'basic'): ?>
                        <br><span style="color: #dc3545;">Basic Package: <?= (1 - $quiz_submitted_count) ?> submission(s) remaining</span>
                    <?php endif; ?>
                </div>
            </div>
            <div>
                <button class="btn-ghost" id="restartBtn">Restart Quiz</button>
                <a href="settings.php" class="btn btn-login">Dashboard</a>
            </div>
        </header>

        <div class="quiz-area" id="quizArea">
            <div class="main card" id="mainCard">
                <!-- Question pages render here -->
                <div id="pageInfo" class="muted small" style="margin-bottom:8px">Page <span id="pageNum">1</span> / <span id="pageTotal">3</span></div>
                <div id="questionsList"></div>

                <div class="pager">
                    <button id="prevBtn" class="">← Previous</button>
                    <div class="center" style="gap:12px">
                        <div class="muted small" id="progressInfo"></div>
                        <button id="nextBtn" class="btn-primary">Next →</button>
                    </div>
                </div>
            </div>

            <aside class="side card" id="sideCard" style="display: none;">
                <div style="display:flex;flex-direction:column;gap:12px">
                    <div>
                        <div style="font-weight:700;margin-bottom:6px">Quick info</div>
                        <div class="small">5 questions per page • Questions randomized • Categories hidden during quiz</div>
                    </div>
                    <div>
                        <div style="font-weight:700;margin-bottom:6px">Quiz Limits</div>
                        <div class="small">
                            <strong>Basic:</strong> 1 submission<br>
                            <strong>Silver+:</strong> Unlimited submissions
                        </div>
                    </div>
                    <div style="display:flex;gap:8px;flex-wrap:wrap">
                        <div style="display:flex;gap:8px;align-items:center"><span style="width:12px;height:12px;background:var(--good);display:inline-block;border-radius:3px"></span><span class="small">Good (≥15)</span></div>
                        <div style="display:flex;gap:8px;align-items:center"><span style="width:12px;height:12px;background:var(--okay);display:inline-block;border-radius:3px"></span><span class="small">Okay (7–14)</span></div>
                        <div style="display:flex;gap:8px;align-items:center"><span style="width:12px;height:12px;background:var(--bad);display:inline-block;border-radius:3px"></span><span class="small">Bad (≤6)</span></div>
                    </div>

                    <div style="margin-top:10px">
                        <button id="viewResultsBtn" class="btn-ghost">View Results (after submit)</button>
                    </div>
                    <div style="margin-top:6px" class="small muted">Your progress auto-saves in this browser.</div>
                </div>
            </aside>
        </div>

        <!-- thank-you and results preview area (hidden until submit) -->
        <div id="thankYouArea" class="hide" style="margin-top:18px;display:none">
            <div class="card thank-card" id="thankCard">
                <h2>Thank you for completing the quiz!</h2>
                <form method="post" class="quiz-form">
                    <div>
                        <lable style="margin-bottom:20px;"><b>Tenant or Property Manager Email</b></lable>
                        <input type="email" name="email" placeholder="Tenant or Property Manager Email" required>
                    </div>
                    <div style="margin-top:20px;">
                        <label for=""><b>Personal Details</b></label>
                        <input type="text" name="name" placeholder="Name" required>
                        <input type="text" name="phone" placeholder="Phone" required>
                    </div>
                    <button type="submit" name="quizFrom">Submit</button>
                </form>
            </div>
        </div>

    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<script>
    // ============================
    // Front-end JS: quiz + results
    // ============================

    // User type and submission count from PHP
    const userType = '<?= $user_type ?>';
    const quizSubmittedCount = <?= $quiz_submitted_count ?>;
    const canSubmitMore = userType !== 'basic' || quizSubmittedCount < 1;

    // ========== 1) Raw data extracted from PDF (reduced/cleaned),
    // We'll keep 5 categories with 3 questions each = 15 total questions
    const RAW_CATEGORIES = [
        {
            name: "Honesty",
            items: [
                { text: "Your spouse buys a premium perfume but you don't like it. How do you respond?", options:[
                        {t:"Politely explain you don't like the fragrance.", p:2},
                        {t:"I greatly appreciate the gift but I just feel I can't accept it.", p:1},
                        {t:"Ooh, thank you sooo much!", p:0},
                        {t:"What a surprise! How did you find my favorite?", p:0}
                    ]},
                { text: "Mother-in-law demands to come over while cooking. How do you respond?", options:[
                        {t:"Send a quick text: Busy. Call you later.", p:2},
                        {t:"Give her a quick call.", p:1},
                        {t:"Ignore and find an excuse.", p:0},
                        {t:"Finish cooking first then text: Down in bed.", p:0}
                    ]},
                { text: "Kids surprise you with a birthday at a fancy restaurant with poor service. How react?", options:[
                        {t:"Express gratefulness and politely mention issues to staff.", p:2},
                        {t:"Address it with humor without dampening mood.", p:1},
                        {t:"Downplay the service and focus on celebration.", p:0},
                        {t:"Say everything is beautiful.", p:0}
                    ]}
            ]
        },

        {
            name: "Responsibility",
            items: [
                { text:"You wake up overwhelmed and anxious by a busy day. You:", options:[
                        {t:"Focus on priorities to manage stress.", p:2},
                        {t:"Shift to positive thoughts and productive actions.", p:1},
                        {t:"Can't stop stress and let it overwhelm you.", p:0},
                        {t:"Struggle to manage thoughts and emotions positively.", p:0}
                    ]},
                { text:"Stuck in traffic on way to an important meeting. You:", options:[
                        {t:"Call ahead to inform them of the delay.", p:2},
                        {t:"Breathe deeply, accept delay, listen to a podcast.", p:1},
                        {t:"Let anger and stress ruin your day.", p:0},
                        {t:"Blame other drivers.", p:0}
                    ]},
                { text:"During a team meeting, a colleague presents an idea you disagree with. You:", options:[
                        {t:"Express your concerns respectfully and constructively.", p:2},
                        {t:"Consider their perspective and ask thoughtful questions.", p:1},
                        {t:"Dismiss it and avoid effective communication.", p:0},
                        {t:"Interrupt and create a tense atmosphere.", p:0}
                    ]}
            ]
        },

        {
            name: "Cleaning",
            items: [
                { text:"Do you have difficulty deciding what to keep or throw away?", options:[
                        {t:"No", p:2},{t:"No comment", p:1},{t:"Yes", p:0},{t:"Prefer not say", p:0}
                    ]},
                { text:"Daily/weekly first cleaning task you prioritize?", options:[
                        {t:"Kitchen",p:2},{t:"Livingroom",p:1},{t:"Bathroom",p:0},{t:"Bedroom",p:0},{t:"Floor",p:0}
                    ]},
                { text:"How easy is it to maintain cleanliness of your home?", options:[
                        {t:"Easy",p:2},{t:"Very easy",p:1},{t:"Difficult",p:0},{t:"Very difficult",p:0}
                    ]}
            ]
        },

        {
            name: "Financial Decisions",
            items: [
                { text:"You usually make financial choices:", options:[
                        {t:"Based on informed decisions",p:2},{t:"Based on customer reviews",p:1},{t:"Impulsive decisions",p:0},{t:"Peer pressure",p:0}
                    ]},
                { text:"If you received $10,000 unexpectedly, you:", options:[
                        {t:"Save as emergency fund",p:2},{t:"Pay off debt",p:1},{t:"Make investments",p:0},{t:"Make a large purchase",p:0}
                    ]},
                { text:"What's your number one financial priority?", options:[
                        {t:"Housing & basic needs",p:2},{t:"Education/self development",p:1},{t:"Lifestyle experiences",p:0},{t:"Showing off wealth",p:0}
                    ]}
            ]
        },

        {
            name: "Integrity & Ethics",
            items: [
                { text:"If you found a wallet with cash and no ID, what do you do?", options:[
                        {t:"Try to return it to police",p:2},{t:"Admit to returning it to bank",p:1},{t:"Keep the wallet",p:0}
                    ]},
                { text:"If a colleague asks you to cover for them when they made a mistake, how handle?", options:[
                        {t:"Respect work policy above relationships",p:2},{t:"Tell coworker to take responsibility",p:1},{t:"Do what's convenient for colleague",p:0}
                    ]},
                { text:"Supervisor asks you to do something unethical. You:", options:[
                        {t:"Refuse and stay true to ethics",p:2},{t:"Deny and suggest someone else",p:1},{t:"Do it for favor",p:0}
                    ]}
            ]
        }
    ]; // end RAW_CATEGORIES

    // Ensure exactly 5 categories with 3 questions each
    const CATEGORIES = RAW_CATEGORIES.map(cat => {
        const items = [...cat.items];
        // If we have fewer than 3 questions, duplicate to reach 3
        let i = 0;
        while (items.length < 3) {
            const base = cat.items[i % cat.items.length];
            // create a shallow clone to avoid reference issues
            const clone = { text: base.text + " ", options: base.options.map(o => ({t:o.t, p:o.p})) };
            items.push(clone);
            i++;
        }
        // If more than 3 (unlikely), slice
        return { name: cat.name, items: items.slice(0,3) };
    });

    // Flatten 15 questions into array, but keep 'category' property for scoring later.
    const QUESTIONS = [];
    CATEGORIES.forEach(cat => {
        cat.items.forEach(q => {
            // Ensure options sorted so 2 points appear first
            const opts = q.options.slice().sort((a,b)=> b.p - a.p);
            QUESTIONS.push({ text: q.text, options: opts, category: cat.name });
        });
    });

    // Shuffle at quiz start
    function shuffleArray(a){
        for(let i=a.length-1;i>0;i--){
            const j=Math.floor(Math.random()*(i+1));
            [a[i],a[j]]=[a[j],a[i]];
        }
    }

    // only shuffle once when user starts a fresh attempt (or on restart)
    function prepareQuiz(){
        // We'll create an order array and store it in localStorage so pagination persists
        if(!localStorage.getItem('quiz_order')){
            const order = Array.from({length:QUESTIONS.length}, (_,i)=>i);
            shuffleArray(order);
            localStorage.setItem('quiz_order', JSON.stringify(order));
        }
    }

    // Pagination - now 5 questions per page for 15 total questions (3 pages)
    const QUESTIONS_PER_PAGE = 5;
    let currentPage = parseInt(localStorage.getItem('quiz_page') || '0', 10);
    const totalPages = Math.ceil(QUESTIONS.length / QUESTIONS_PER_PAGE);

    // Answers stored as mapping: qIndex -> selectedPoints
    let answers = JSON.parse(localStorage.getItem('quiz_answers') || '{}');

    // If no order prepared yet, do it now:
    prepareQuiz();
    let quizOrder = JSON.parse(localStorage.getItem('quiz_order'));

    // DOM elements
    const questionsList = document.getElementById('questionsList');
    const pageNumEl = document.getElementById('pageNum');
    const pageTotalEl = document.getElementById('pageTotal');
    const prevBtn = document.getElementById('prevBtn');
    const nextBtn = document.getElementById('nextBtn');
    const progressInfo = document.getElementById('progressInfo');
    const restartBtn = document.getElementById('restartBtn');
    const viewResultsBtn = document.getElementById('viewResultsBtn');
    const thankYouArea = document.getElementById('thankYouArea');
    const appCard = document.getElementById('app-card');
    const subtitle = document.getElementById('subtitle');
    const limitWarning = document.getElementById('limitWarning');

    pageTotalEl.textContent = totalPages;

    // Check if user can submit quiz
    function checkQuizSubmission() {
        if (!canSubmitMore) {
            limitWarning.classList.remove('hidden');
            nextBtn.disabled = true;
            nextBtn.textContent = 'Submission Limit Reached';
            nextBtn.style.backgroundColor = '#6c757d';
            nextBtn.style.cursor = 'not-allowed';
            return false;
        }
        return true;
    }

    // Render current page
    function renderPage(){
        // Refresh global variables from localStorage (in case of restart)
        quizOrder = JSON.parse(localStorage.getItem('quiz_order') || '[]');
        answers = JSON.parse(localStorage.getItem('quiz_answers') || '{}');

        if(currentPage < 0) currentPage = 0;
        if(currentPage > totalPages-1) currentPage = totalPages-1;
        pageNumEl.textContent = (currentPage+1);
        const start = currentPage * QUESTIONS_PER_PAGE;
        const end = Math.min(start + QUESTIONS_PER_PAGE, QUESTIONS.length);
        const pageIndexes = quizOrder.slice(start, end);

        questionsList.innerHTML = '';
        pageIndexes.forEach((qIndex, idxOnPage) => {
            const q = QUESTIONS[qIndex];
            // build question DOM
            const qWrap = document.createElement('div');
            qWrap.className = 'question';
            const qTitle = document.createElement('div');
            qTitle.className = 'q-title';
            qTitle.innerHTML = `<strong>Q${start + idxOnPage + 1}.</strong> ${escapeHtml(q.text)}`;
            qWrap.appendChild(qTitle);

            const optsDiv = document.createElement('div');
            optsDiv.className = 'options';
            q.options.forEach((opt, oi) => {
                // Each radio name is the global qIndex, to persist selection
                const label = document.createElement('label');
                const input = document.createElement('input');
                input.type = 'radio';
                input.name = 'q'+qIndex;
                input.value = opt.p;
                // check if previously answered
                if(answers['q'+qIndex] !== undefined && String(answers['q'+qIndex]) === String(opt.p)){
                    input.checked = true;
                }
                input.addEventListener('change', (e) => {
                    answers['q'+qIndex] = parseInt(e.target.value, 10);
                    localStorage.setItem('quiz_answers', JSON.stringify(answers));
                    updateProgressText();
                });
                label.appendChild(input);
                const textSpan = document.createElement('span');
                textSpan.innerHTML = escapeHtml(opt.t) + ` <span class="muted" style="font-size:12px">(${opt.p} pts)</span>`;
                label.appendChild(textSpan);
                optsDiv.appendChild(label);
            });
            qWrap.appendChild(optsDiv);
            questionsList.appendChild(qWrap);
        });

        prevBtn.disabled = currentPage === 0;
        nextBtn.textContent = (currentPage === totalPages - 1) ? 'Submit' : 'Next →';
        
        // Check submission limit on last page
        if (currentPage === totalPages - 1) {
            checkQuizSubmission();
        } else {
            nextBtn.disabled = false;
            nextBtn.style.backgroundColor = '';
            nextBtn.style.cursor = '';
        }
        
        updateProgressText();
    }

    function updateProgressText(){
        const answeredCount = Object.keys(answers).length;
        progressInfo.textContent = `${answeredCount} answered • Page ${currentPage+1}/${totalPages}`;
    }

    // Navigation
    prevBtn.addEventListener('click', () => {
        if(currentPage > 0){
            currentPage--;
            localStorage.setItem('quiz_page', currentPage);
            renderPage();
            window.scrollTo({top:0,behavior:'smooth'});
        }
    });

    nextBtn.addEventListener('click', async () => {
        if(currentPage < totalPages - 1){
            currentPage++;
            localStorage.setItem('quiz_page', currentPage);
            renderPage();
            window.scrollTo({top:0,behavior:'smooth'});
        } else {
            // Submit flow - Check if user can submit
            if (!checkQuizSubmission()) {
                alert('You have reached your quiz submission limit. Basic package users can only submit 1 quiz.');
                return;
            }
            await handleSubmit();
        }
    });

    // Restart quiz
    restartBtn.addEventListener('click', () => {
        if(!confirm('Restart the quiz? All progress will be cleared.')) return;
        localStorage.removeItem('quiz_answers');
        localStorage.removeItem('quiz_order');
        localStorage.removeItem('quiz_page');
        // prepare new order and reset page
        prepareQuiz();
        quizOrder = JSON.parse(localStorage.getItem('quiz_order'));
        currentPage = 0;
        answers = {};
        localStorage.setItem('quiz_answers', JSON.stringify(answers));
        renderPage();
        window.scrollTo({top:0,behavior:'smooth'});
    });

    viewResultsBtn.addEventListener('click', () => {
        // Only allow view if quiz submitted
        if(!localStorage.getItem('quiz_submitted')){
            alert('You must submit the quiz on the last page to view the results preview.');
            return;
        }
        // Show results preview
        showResultsPreview();
        window.scrollTo({top:document.body.scrollHeight, behavior:'smooth'});
    });

    // Escape HTML helper
    function escapeHtml(unsafe) {
        return unsafe.replace(/[&<"'>]/g, function(m){ return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'})[m]; });
    }

    // ========== Scoring and submit
    function computeCategoryTotals() {
        // Prepare totals keyed by category name
        const totals = {};
        CATEGORIES.forEach(cat => totals[cat.name] = 0);
        // For every answered q, add points to its category
        Object.keys(answers).forEach(k => {
            if(!k.startsWith('q')) return;
            const qi = parseInt(k.slice(1), 10);
            if(Number.isNaN(qi) || qi < 0 || qi >= QUESTIONS.length) return;
            const pts = parseInt(answers[k], 10) || 0;
            const cat = QUESTIONS[qi].category;
            totals[cat] = (totals[cat] || 0) + pts;
        });
        return totals;
    }

    function googleTranslateElementInit() {
        new google.translate.TranslateElement({
            pageLanguage: 'en',
            includedLanguages: 'en,ar,es,fr,zh-CN,so',
            autoDisplay: false
        }, 'google_translate_element');
    }

    // Trigger Google Translate language change
    function changeLang(lang) {
        var select = document.querySelector(".goog-te-combo");
        if (select) {
            select.value = lang;
            select.dispatchEvent(new Event("change"));
        } else {
            alert("Google Translate widget load nahi hua");
        }
    }

    function gradeForPoints(points){
        // Adjusted for 3 questions per category (max 6 points)
        if(points <= 2) return {grade:'Bad', color:getCssVar('--bad')};
        if(points <= 4) return {grade:'Okay', color:getCssVar('--okay')};
        return {grade:'Good', color:getCssVar('--good')};
    }

    function getCssVar(name){
        return getComputedStyle(document.documentElement).getPropertyValue(name) || '#000';
    }

    async function handleSubmit(){
        // Check submission limit again before proceeding
        if (!canSubmitMore) {
            alert('You have reached your quiz submission limit. Basic package users can only submit 1 quiz.');
            return;
        }

        // Mark as submitted (preview)
        localStorage.setItem('quiz_submitted', '1');
        // compute totals
        const totals = computeCategoryTotals();
        const sumPoints = Object.values(totals).reduce((a,b)=>a+b, 0);
        const timeSeconds = Math.floor((Date.now() - (parseInt(localStorage.getItem('quiz_started')||Date.now()))) / 1000);
        const payload = {
            name: '', email: '',
            totals: totals,
            total_points: sumPoints,
            time_seconds: timeSeconds,
            submitted_at: new Date().toISOString()
        };

        // Save server-side via POST
        try {
            const form = new FormData();
            form.append('payload', JSON.stringify(payload));
            const resp = await fetch(location.href, { method:'POST', body: form });
            const json = await resp.json();
            if(!json.ok) {
                if (json.error && json.error.includes('limit')) {
                    alert('Quiz submission limit reached for your package');
                    return;
                }
                console.warn('Server-side save problem', json);
            }
        } catch(err){
            console.warn('Server not reachable or saving failed', err);
        }

        // Show thank-you message and results preview
        showThankYou(payload);
    }

    // Show Thank you area + results preview
    let chartInstance = null;
    function showThankYou(payload){
        // Hide quiz area and show thank you
        document.getElementById('quizArea').style.display = 'none';
        thankYouArea.style.display = 'block';
        // Build chart
        setTimeout(()=>renderResultsChart(payload), 60);
    }

    function showResultsPreview(){
        // For convenience show saved results in localStorage
        const totals = computeCategoryTotals();
        const payload = { totals: totals, total_points: Object.values(totals).reduce((a,b)=>a+b,0), submitted_at:new Date().toISOString() };
        document.getElementById('quizArea').style.display = 'none';
        thankYouArea.style.display = 'block';
        renderResultsChart(payload);
    }

    // Render Chart.js with background banding for grade zones
    function renderResultsChart(payload){
        const catNames = CATEGORIES.map(c => c.name);
        const dataPoints = catNames.map(n => (payload.totals && payload.totals[n] !== undefined) ? payload.totals[n] : 0);

        // Offscreen canvas create (user ko nahi dikhega)
        const offCanvas = document.createElement('canvas');
        offCanvas.width = 800;
        offCanvas.height = 600;
        const ctx = offCanvas.getContext('2d');

        // Chart render
        const chart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: catNames,
                datasets: [{
                    label: 'Points (0–6)',
                    data: dataPoints,
                    backgroundColor: catNames.map(n => {
                        const pts = payload.totals[n] || 0;
                        const g = gradeForPoints(pts);
                        return g.color.trim();
                    }),
                    borderRadius: 6,
                    barThickness: 36
                }]
            },
            options: {
                responsive: false, // offscreen ke liye responsive disable karo
                maintainAspectRatio: false,
                scales: {
                    y: { min:0, max:6 } // Adjusted max to 6 (3 questions × 2 points each)
                },
                plugins: { legend:{ display:false } }
            }
        });

        // jab render ho jaye tab image le lo
        setTimeout(() => {
            const imgData = offCanvas.toDataURL('image/png');

            fetch('email-files.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    image: imgData,
                    payload: payload
                })
            })
                .then(res => res.text())
                .then(resp => console.log("Email status:", resp))
                .catch(err => console.error(err));
        }, 500);
    }

    // Set start timestamp if not set
    if(!localStorage.getItem('quiz_started')) localStorage.setItem('quiz_started', Date.now());

    // On initial load render page
    renderPage();
    checkQuizSubmission();

    // Ensure quiz_order exists; if not (e.g. after restart) prepare
    // (prepareQuiz called earlier, but ensure again)
    if(!localStorage.getItem('quiz_order')) prepareQuiz();

</script>
</body>
</html>
<?php
} else {
	$_SESSION['toastr_message'] = "Please Login First!";
	$_SESSION['toastr_type'] = "info";
	header("Location: login.php");
	exit();
}
?>