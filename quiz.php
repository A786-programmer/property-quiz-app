<?php
    include 'config.php';
    include 'email-files.php';
    $quizCode = mysqli_real_escape_string($con, $_GET['code']);
    $quiz = mysqli_query($con, "SELECT * FROM quizzes WHERE q_code='$quizCode'");
    if (mysqli_num_rows($quiz) == 0) {
        die("Invalid quiz Link");
    }
    $quizData = mysqli_fetch_assoc($quiz);
    if ($quizData['q_name'] && $quizData['q_email'] && $quizData['q_phone']) {
        die("Quiz Already Submitted using this Link");
    }
    $QuizUserId = $quizData['q_user_id'];
    $quizId = $quizData['q_id']; 

    $_SESSION['quiz_access'] = true;
    $_SESSION['QuizUserId'] = $QuizUserId;
    $_SESSION['quiz_code'] = $quizCode;
    $_SESSION['quiz_id'] = $quizId; 
    
    $userQuery = mysqli_query($con, "SELECT u_package_type, u_quiz_created FROM users WHERE u_id = '$QuizUserId'");
    $userData = mysqli_fetch_assoc($userQuery);
    $userType = $userData['u_package_type'];
    $quizSubmittedCount = $userData['u_quiz_created'];
    
    $_SESSION['qa_user'] = $QuizUserId;
    $_SESSION['userType'] = $userType;
    $_SESSION['quizSubmittedCount'] = $quizSubmittedCount;

function canSubmitQuiz($userType, $quizSubmittedCount) {
    if ($userType == 'basic') {
        return $quizSubmittedCount < 1;
    }
    return true;
}

if ($userType == 'basic' && $quizSubmittedCount >= 1) {
    echo "<h2>Quiz Submission Limit Reached</h2>";
    echo "<p>Basic package users can only submit 1 quiz. Please contact the quiz owner to upgrade.</p>";
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

    if (!canSubmitQuiz($userType, $quizSubmittedCount)) {
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

    $row = [];
    if ($isNew) {
        $headers = ['submitted_at','name','email','time_seconds','total_points','user_id','userType'];
        foreach ($payload['totals'] ?? [] as $cat => $v) $headers[] = $cat;
        fputcsv($fp, $headers);
    }
    $row[] = $payload['submitted_at'] ?? date('c');
    $row[] = $payload['name'] ?? '';
    $row[] = $payload['email'] ?? '';
    $row[] = $payload['time_seconds'] ?? '';
    $row[] = $payload['total_points'] ?? '';
    $row[] = $QuizUserId;
    $row[] = $userType;
    foreach ($payload['totals'] ?? [] as $cat => $v) $row[] = $v;
    fputcsv($fp, $row);
    fclose($fp);

    $new_count = $quizSubmittedCount + 1;
    mysqli_query($con, "UPDATE users SET u_quiz_created = '$new_count' WHERE u_id = '$QuizUserId'");

    echo json_encode(['ok' => true, 'saved_file' => 'results/results.csv', 'submission_count' => $new_count]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['image_data'])) {
    header('Content-Type: application/json; charset=utf-8');
    
    $imageData = $_POST['image_data'];
    $totalPoints = $_POST['total_points'] ?? 0;
    $quizId = $_SESSION['quiz_id'] ?? 0;
    
    $imageData = str_replace('data:image/png;base64,', '', $imageData);
    $imageData = base64_decode($imageData);
    
    $imageDir = __DIR__ . '/results';
    if (!is_dir($imageDir)) {
        mkdir($imageDir, 0775, true);
    }
    
    $filename = 'quiz_result_' . time() . '.png';
    $filePath = $imageDir . '/' . $filename;   // absolute path for saving
    $relativePath = $filename;                // relative path for DB
    
    if (file_put_contents($filePath, $imageData)) {
        $updateQuery = "UPDATE quizzes 
                        SET q_image = '$relativePath', q_result = '$totalPoints' 
                        WHERE q_id = '$quizId'";
        if (mysqli_query($con, $updateQuery)) {
            // ✅ Save path in session for later email attachment
            $_SESSION['quiz_result_image'] = $filePath;

            echo json_encode([
                'ok' => true,
                'image_path' => $relativePath
            ]);
        } else {
            echo json_encode([
                'ok' => false,
                'error' => 'Database update failed'
            ]);
        }
    } else {
        echo json_encode([
            'ok' => false,
            'error' => 'Image save failed'
        ]);
    }
}

if (isset($_POST['quizFrom'])) {    
    $_SESSION['name'] = mysqli_real_escape_string($con, $_POST['name']);
    $_SESSION['email'] = mysqli_real_escape_string($con, $_POST['email']);
    $_SESSION['phone'] = mysqli_real_escape_string($con, $_POST['phone']);

    $quizId = $_SESSION['quiz_id'];

    mysqli_query($con, "UPDATE quizzes 
        SET q_name='{$_SESSION['name']}', q_email='{$_SESSION['email']}', q_phone='{$_SESSION['phone']}' 
        WHERE q_id = '$quizId'");

    try {
        $mail->clearAddresses();
        $mail->addAddress($_SESSION['email'], $_SESSION['name']);
        $mail->isHTML(true);
        $mail->Subject = $websiteName . " - Quiz Submission Details";

        $bodyContent = "
            Hello {$_SESSION['name']},<br><br>
            Thank you for completing the quiz!<br><br>
            <strong>Your Details:</strong><br>
            Name: {$_SESSION['name']}<br>
            Email: {$_SESSION['email']}<br>
            Phone: {$_SESSION['phone']}<br><br>
            
            We have received your quiz submission successfully.<br><br>
            Please find your quiz result attached below.<br><br>
        ";

        $mail->Body = $bodyContent;

        // ✅ Get image path from session
        if (!empty($_SESSION['quiz_result_image']) && file_exists($_SESSION['quiz_result_image'])) {
            $mail->addAttachment($_SESSION['quiz_result_image'], 'QuizResult.png');
        } else {
            error_log("No quiz result image found to attach.");
        }

        if ($mail->send()) {
            error_log("Email sent successfully to: " . $_SESSION['email']);
        } else {
            error_log("Email sending failed: " . $mail->ErrorInfo);
        }
    } catch (Exception $e) {
        error_log("Mailer Error: " . $e->getMessage());
    }

    header("Location: https://demotestlink.com/form/quiz-complete/");
    exit;
}


?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <script type="text/javascript" src="https://translate.google.com/translate_a/element.js?cb=googleTranslateElementInit"></script>
    <title>15-Question Quiz</title>
    <link rel="stylesheet" href="data:,">
    <style>
        /* Your existing CSS styles remain the same */
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

        .dropdown {
            position: relative;
            display: inline-block;
            width: 100%;
            text-align: center;
            margin: 0 auto;
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
            left: 50%;
            transform: translateX(-50%);
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
<div id="google_translate_element" style="display:none;"></div>
<div class="wrap">
    <div class="card" id="app-card">
        <div id="limitWarning" class="limit-warning <?= ($userType == 'basic' && $quizSubmittedCount >= 1) ? '' : 'hidden' ?>">
            <strong>⚠️ Quiz Submission Limit Reached</strong><br>
            Your Basic package allows only 1 quiz submission. You have already submitted <?= $quizSubmittedCount ?> quiz(es). 
            Please contact the quiz owner to upgrade.
        </div>
        
        <header>
            <div>
                <h1>15-Question Quiz</h1>
                <div class="subtitle" id="subtitle">
                    Answer the questions. 5 per page. Questions are randomized.
                    <?php if($userType == 'basic'): ?>
                        <br><span style="color: #dc3545;">Basic Package: <?= (1 - $quizSubmittedCount) ?> submission(s) remaining</span>
                    <?php endif; ?>
                </div>
            </div>
            <div>
                <button class="btn-ghost" id="restartBtn">Restart Quiz</button>
            </div>
        </header>

        <div class="quiz-area" id="quizArea">
            <div class="main card" id="mainCard">
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
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
    const userType = '<?= $userType ?>';
    const quizSubmittedCount = <?= $quizSubmittedCount ?>;
    const canSubmitMore = userType !== 'basic' || quizSubmittedCount < 1;
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
    ];

    const CATEGORIES = RAW_CATEGORIES.map(cat => {
        const items = [...cat.items];
        let i = 0;
        while (items.length < 3) {
            const base = cat.items[i % cat.items.length];
            const clone = { text: base.text + " ", options: base.options.map(o => ({t:o.t, p:o.p})) };
            items.push(clone);
            i++;
        }
        return { name: cat.name, items: items.slice(0,3) };
    });

    const QUESTIONS = [];
    CATEGORIES.forEach(cat => {
        cat.items.forEach(q => {
            const opts = q.options.slice().sort((a,b)=> b.p - a.p);
            QUESTIONS.push({ text: q.text, options: opts, category: cat.name });
        });
    });

    function shuffleArray(a){
        for(let i=a.length-1;i>0;i--){
            const j=Math.floor(Math.random()*(i+1));
            [a[i],a[j]]=[a[j],a[i]];
        }
    }

    function prepareQuiz(){
        if(!localStorage.getItem('quiz_order')){
            const order = Array.from({length:QUESTIONS.length}, (_,i)=>i);
            shuffleArray(order);
            localStorage.setItem('quiz_order', JSON.stringify(order));
        }
    }

    const QUESTIONS_PER_PAGE = 5;
    let currentPage = parseInt(localStorage.getItem('quiz_page') || '0', 10);
    const totalPages = Math.ceil(QUESTIONS.length / QUESTIONS_PER_PAGE);

    let answers = JSON.parse(localStorage.getItem('quiz_answers') || '{}');

    prepareQuiz();
    let quizOrder = JSON.parse(localStorage.getItem('quiz_order'));

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

    function renderPage(){
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
            const qWrap = document.createElement('div');
            qWrap.className = 'question';
            const qTitle = document.createElement('div');
            qTitle.className = 'q-title';
            qTitle.innerHTML = `<strong>Q${start + idxOnPage + 1}.</strong> ${escapeHtml(q.text)}`;
            qWrap.appendChild(qTitle);

            const optsDiv = document.createElement('div');
            optsDiv.className = 'options';
            q.options.forEach((opt, oi) => {
                const label = document.createElement('label');
                const input = document.createElement('input');
                input.type = 'radio';
                input.name = 'q'+qIndex;
                input.value = opt.p;
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
            if (!checkQuizSubmission()) {
                alert('You have reached your quiz submission limit. Basic package users can only submit 1 quiz.');
                return;
            }
            await handleSubmit();
        }
    });

    restartBtn.addEventListener('click', () => {
        if(!confirm('Restart the quiz? All progress will be cleared.')) return;
        localStorage.removeItem('quiz_answers');
        localStorage.removeItem('quiz_order');
        localStorage.removeItem('quiz_page');

        prepareQuiz();
        quizOrder = JSON.parse(localStorage.getItem('quiz_order'));
        currentPage = 0;
        answers = {};
        localStorage.setItem('quiz_answers', JSON.stringify(answers));
        renderPage();
        window.scrollTo({top:0,behavior:'smooth'});
    });

    viewResultsBtn.addEventListener('click', () => {
        if(!localStorage.getItem('quiz_submitted')){
            alert('You must submit the quiz on the last page to view the results preview.');
            return;
        }
        showResultsPreview();
        window.scrollTo({top:document.body.scrollHeight, behavior:'smooth'});
    });

    function escapeHtml(unsafe) {
        return unsafe.replace(/[&<"'>]/g, function(m){ return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'})[m]; });
    }

    function computeCategoryTotals() {
        const totals = {};
        CATEGORIES.forEach(cat => totals[cat.name] = 0);
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
        if(points <= 2) return {grade:'Bad', color:getCssVar('--bad')};
        if(points <= 4) return {grade:'Okay', color:getCssVar('--okay')};
        return {grade:'Good', color:getCssVar('--good')};
    }

    function getCssVar(name){
        return getComputedStyle(document.documentElement).getPropertyValue(name) || '#000';
    }

    async function handleSubmit(){
        if (!canSubmitMore) {
            alert('You have reached your quiz submission limit. Basic package users can only submit 1 quiz.');
            return;
        }

        localStorage.setItem('quiz_submitted', '1');
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

        showThankYou(payload);
    }

    let chartInstance = null;
    function showThankYou(payload){
        document.getElementById('quizArea').style.display = 'none';
        thankYouArea.style.display = 'block';
        setTimeout(()=>renderResultsChart(payload), 60);
    }

    function showResultsPreview(){
        const totals = computeCategoryTotals();
        const payload = { totals: totals, total_points: Object.values(totals).reduce((a,b)=>a+b,0), submitted_at:new Date().toISOString() };
        document.getElementById('quizArea').style.display = 'none';
        thankYouArea.style.display = 'block';
        renderResultsChart(payload);
    }

    function renderResultsChart(payload){
        const catNames = CATEGORIES.map(c => c.name);
        const dataPoints = catNames.map(n => (payload.totals && payload.totals[n] !== undefined) ? payload.totals[n] : 0);

        const canvas = document.createElement('canvas');
        canvas.width = 800;
        canvas.height = 600;
        const ctx = canvas.getContext('2d');

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
                responsive: false,
                maintainAspectRatio: false,
                scales: {
                    y: { min:0, max:6 }
                },
                plugins: { legend:{ display:false } }
            }
        });

        setTimeout(() => {
            const imgData = canvas.toDataURL('image/png');
            
            saveImageToServer(imgData, payload.total_points);
        }, 500);
    }

    async function saveImageToServer(imageData, totalPoints) {
        try {
            const formData = new FormData();
            formData.append('image_data', imageData);
            formData.append('total_points', totalPoints);
            
            const response = await fetch(location.href, {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            if (result.ok) {
                console.log('Image saved successfully:', result.image_path);
            } else {
                console.error('Failed to save image:', result.error);
            }
        } catch (error) {
            console.error('Error saving image:', error);
        }
    }

    if(!localStorage.getItem('quiz_started')) localStorage.setItem('quiz_started', Date.now());

    renderPage();
    checkQuizSubmission();

    if(!localStorage.getItem('quiz_order')) prepareQuiz();

</script>
</body>
</html>