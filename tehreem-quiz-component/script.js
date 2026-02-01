document.addEventListener('DOMContentLoaded', () => {
    // State
    let questions = [];
    let currentQuestionIndex = 0;
    let answers = [];
    let testState = 'start'; // start, in-progress, complete
    
    // Timer Variables
    let totalDuration = 0;
    let totalTimeLeft = 0;
    let activeTimeElapsed = 0;
    let inactivityBucket = 15;
    let isSuspicious = false;
    let testStartTime = null;
    
    // Interval IDs
    let totalTimerId = null;
    let activeTimerId = null;
    let inactivityTimerId = null;
    let isWindowActive = true;

    // DOM Elements
    const screens = {
        start: document.getElementById('start-screen'),
        quiz: document.getElementById('quiz-screen'),
        result: document.getElementById('result-screen')
    };

    const els = {
        startBtn: document.getElementById('start-btn'),
        questionContainer: document.getElementById('question-content'),
        optionsGrid: document.getElementById('options-grid'),
        totalTimerDisplay: document.getElementById('total-timer'),
        inactivityTimerDisplay: document.getElementById('inactivity-timer'),
        questionProgress: document.getElementById('question-progress'),
        scoreDisplay: document.getElementById('score-display'),
        timeStat: document.getElementById('time-stat'),
        activeStat: document.getElementById('active-stat'),
        suspiciousBanner: document.getElementById('suspicious-banner'),
        restartBtn: document.getElementById('restart-btn')
    };

    // --- Initialization ---

    function init() {
        fetchQuestions();
        setupEventListeners();
    }

    async function fetchQuestions() {
        try {
            const response = await fetch('get_questions.php');
            const data = await response.json();
            questions = data.questions;
            totalDuration = data.duration_seconds;
            totalTimeLeft = totalDuration;
            
            document.getElementById('question-count').textContent = questions.length;
            document.getElementById('total-minutes').textContent = Math.floor(totalDuration / 60);
        } catch (error) {
            console.error('Error fetching questions:', error);
            alert('Failed to load quiz data.');
        }
    }

    function setupEventListeners() {
        els.startBtn.addEventListener('click', startTest);
        els.restartBtn.addEventListener('click', restartTest);
        
        // Tab Visibility / Focus Tracking
        document.addEventListener('visibilitychange', handleVisibilityChange);
        window.addEventListener('blur', () => handleFocusChange(false));
        window.addEventListener('focus', () => handleFocusChange(true));
    }

    // --- Core Test Logic ---

    function startTest() {
        if (questions.length === 0) return;

        testState = 'in-progress';
        switchScreen('quiz');
        
        // Reset counters
        currentQuestionIndex = 0;
        answers = new Array(questions.length).fill(null);
        totalTimeLeft = totalDuration;
        activeTimeElapsed = 0;
        inactivityBucket = 15;
        isSuspicious = false;
        testStartTime = Date.now();
        isWindowActive = true;

        updateTimerDisplays();
        renderQuestion();
        
        // Start Timers
        startTimers();
    }

    function startTimers() {
        // 1. Total Countdown
        clearInterval(totalTimerId);
        totalTimerId = setInterval(() => {
            totalTimeLeft--;
            updateTimerDisplays();
            
            if (totalTimeLeft <= 0) {
                completeTest();
            }
        }, 1000);

        // 2. Active Screen Timer (tracks how long user is ON screen)
        startActiveTimer();
    }

    function startActiveTimer() {
        clearInterval(activeTimerId);
        activeTimerId = setInterval(() => {
            if (isWindowActive) {
                activeTimeElapsed++;
            }
        }, 1000);
    }

    function handleFocusChange(focused) {
        if (testState !== 'in-progress') return;
        
        isWindowActive = focused;
        
        if (focused) {
            // User returned
            clearInterval(inactivityTimerId);
            inactivityTimerId = null;
            els.inactivityTimerDisplay.classList.remove('warning');
        } else {
            // User left - Start Inactivity Countdown
            if (inactivityTimerId) return; // Already running
            
            inactivityTimerId = setInterval(() => {
                inactivityBucket--;
                updateTimerDisplays();
                
                if (inactivityBucket <= 0) {
                    isSuspicious = true;
                    clearInterval(inactivityTimerId);
                    // We do NOT stop the test, just mark suspicious
                    // But we stop counting down negative numbers visually
                    inactivityBucket = 0; 
                    updateTimerDisplays();
                }
            }, 1000);
        }
    }

    function handleVisibilityChange() {
        if (document.hidden) {
            handleFocusChange(false);
        } else {
            handleFocusChange(true);
        }
    }

    function updateTimerDisplays() {
        // Total Timer formatted MM:SS
        const minutes = Math.floor(totalTimeLeft / 60);
        const seconds = totalTimeLeft % 60;
        els.totalTimerDisplay.textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;

        // Inactivity Bucket
        els.inactivityTimerDisplay.textContent = `${inactivityBucket}s`;
        if (inactivityBucket <= 5) {
            els.inactivityTimerDisplay.classList.add('warning');
        } else {
            els.inactivityTimerDisplay.classList.remove('warning');
        }
    }

    // --- Rendering ---

    function renderQuestion() {
        const q = questions[currentQuestionIndex];
        
        // Progress
        els.questionProgress.textContent = `Question ${currentQuestionIndex + 1} of ${questions.length}`;

        // Content
        els.questionContainer.innerHTML = '';
        
        // If there is specific question text separate from content (like for image questions)
        if (q.questionText) {
            const textEl = document.createElement('div');
            textEl.className = 'question-text';
            textEl.textContent = q.questionText;
            els.questionContainer.appendChild(textEl);
        }

        // Main Content
        const contentEl = createContentElement(q.type, q.content);
        // For text type questions that DON'T have separate questionText, the content IS the question
        if (q.type === 'text' && !q.questionText) {
            contentEl.className = 'question-text';
        }
        els.questionContainer.appendChild(contentEl);

        // Options
        els.optionsGrid.innerHTML = '';
        q.options.forEach(opt => {
            const btn = document.createElement('div');
            btn.className = 'option-card';
            btn.appendChild(createContentElement(opt.type, opt.content));
            
            btn.addEventListener('click', () => selectAnswer(opt.id));
            els.optionsGrid.appendChild(btn);
        });
    }

    function createContentElement(type, content) {
        if (type === 'image') {
            const img = document.createElement('img');
            img.src = content;
            img.alt = 'Question content';
            return img;
        } else if (type === 'html') {
            const div = document.createElement('div');
            div.innerHTML = content;
            return div;
        } else {
            const span = document.createElement('span');
            span.textContent = content;
            return span;
        }
    }

    function selectAnswer(selectedId) {
        answers[currentQuestionIndex] = selectedId;
        
        if (currentQuestionIndex < questions.length - 1) {
            currentQuestionIndex++;
            renderQuestion();
        } else {
            completeTest();
        }
    }

    // --- Completion ---

    function completeTest() {
        if (testState === 'complete') return;
        testState = 'complete';
        
        // Stop all timers
        clearInterval(totalTimerId);
        clearInterval(activeTimerId);
        clearInterval(inactivityTimerId);

        // Calculate Results
        let score = 0;
        questions.forEach((q, idx) => {
            if (answers[idx] === q.correctAnswer) {
                score++;
            }
        });

        // Show Results
        els.scoreDisplay.textContent = `${score} / ${questions.length}`;
        els.timeStat.textContent = formatDuration(totalDuration - totalTimeLeft); // Time taken
        els.activeStat.textContent = formatDuration(activeTimeElapsed);

        if (isSuspicious) {
            els.suspiciousBanner.classList.remove('hidden');
        } else {
            els.suspiciousBanner.classList.add('hidden');
        }

        switchScreen('result');
    }

    function restartTest() {
        testState = 'start';
        switchScreen('start');
    }

    // --- Helpers ---

    function switchScreen(screenName) {
        Object.values(screens).forEach(el => el.classList.remove('active'));
        screens[screenName].classList.add('active');
    }

    function formatDuration(seconds) {
        const m = Math.floor(seconds / 60);
        const s = seconds % 60;
        return `${m}m ${s}s`;
    }

    // Init
    init();
});
