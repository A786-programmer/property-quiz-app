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
    $_SESSION['quiz_id'] = $quizData['q_id']; 

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['image_data'])) {
        header('Content-Type: application/json; charset=utf-8');
        
        $imageData = $_POST['image_data'];
        $totalPoints = $_POST['total_points'] ?? 0;
        $quizId = $_SESSION['quiz_id'] ?? 0;
        
        $imageData = str_replace('data:image/png;base64,', '', $imageData);
        $imageData = base64_decode($imageData);

        $filename = 'quiz_result_' . time() . '.png';
        $filePath = 'results/' . $filename;   // absolute path for saving
        $relativePath = $filename;                // relative path for DB
        
        if (file_put_contents($filePath, $imageData)) {
            $updateQuery = "UPDATE quizzes SET q_image = '$relativePath', q_result = '$totalPoints' WHERE q_id = '$quizId'";
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

            header("Location: https://demotestlink.com/form/quiz-complete/");
            exit;
        } catch (Exception $e) {
            error_log("Mailer Error: " . $e->getMessage());
        }
    }
?>
<!doctype html>
<html lang="en">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width,initial-scale=1" />
        <script type="text/javascript" src="https://translate.google.com/translate_a/element.js?cb=googleTranslateElementInit"></script>
        <title>15-Question Quiz</title>
        <link rel="stylesheet" href="quiz-style.css">
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
                <header>
                    <div>
                        <h1>15-Question Quiz</h1>
                        <div class="subtitle" id="subtitle">
                            Answer the questions. 5 per page. Questions are randomized.
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
            const thankYouArea = document.getElementById('thankYouArea');
            const appCard = document.getElementById('app-card');
            const subtitle = document.getElementById('subtitle');
            const limitWarning = document.getElementById('limitWarning');

            pageTotalEl.textContent = totalPages;

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

                showThankYou(payload);
            }

            let chartInstance = null;
            function showThankYou(payload){
                document.getElementById('quizArea').style.display = 'none';
                thankYouArea.style.display = 'block';
                setTimeout(()=>renderResultsChart(payload), 60);
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
            if(!localStorage.getItem('quiz_order')) prepareQuiz();
        </script>
    </body>
</html>