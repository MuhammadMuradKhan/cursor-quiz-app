<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Interactive Quiz</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

    <div id="app-container">
        
        <!-- Start Screen -->
        <div id="start-screen" class="screen active">
            <h1>Welcome to the Quiz</h1>
            <div style="text-align: center; color: #666; margin-bottom: 2rem;">
                <p>Total Questions: <span id="question-count">...</span></p>
                <p>Time Limit: <span id="total-minutes">...</span> minutes</p>
                <p style="font-size: 0.9rem; margin-top: 1rem;">
                    <strong>Note:</strong> Moving away from the test screen will trigger an inactivity timer. 
                    If you are inactive for more than 15 seconds, your session will be flagged.
                </p>
            </div>
            <button id="start-btn" class="btn">Start Quiz</button>
        </div>

        <!-- Quiz Screen -->
        <div id="quiz-screen" class="screen">
            <div class="quiz-header">
                <div class="timer-box">
                    <span class="timer-label">Time Left</span>
                    <span id="total-timer" class="timer-value">00:00</span>
                </div>
                <div class="timer-box">
                    <span class="timer-label">Inactivity Allowed</span>
                    <span id="inactivity-timer" class="timer-value">15s</span>
                </div>
            </div>

            <div style="text-align: center; color: #888; font-size: 0.9rem;" id="question-progress">
                Question 1 of 5
            </div>

            <div id="question-content" class="question-container">
                <!-- Content injected by JS -->
            </div>

            <div id="options-grid" class="options-grid">
                <!-- Options injected by JS -->
            </div>
        </div>

        <!-- Result Screen -->
        <div id="result-screen" class="screen">
            <h1>Test Completed</h1>
            
            <div class="result-stats">
                <div class="stat-item">
                    <span>Final Score:</span>
                    <span id="score-display" style="font-weight: bold; color: #4f46e5;">0 / 0</span>
                </div>
                <div class="stat-item">
                    <span>Total Time Taken:</span>
                    <span id="time-stat">0m 0s</span>
                </div>
                <div class="stat-item">
                    <span>Active Screen Time:</span>
                    <span id="active-stat">0m 0s</span>
                </div>
            </div>

            <div id="suspicious-banner" class="suspicious-flag hidden">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                    <line x1="12" y1="9" x2="12" y2="13"></line>
                    <line x1="12" y1="17" x2="12.01" y2="17"></line>
                </svg>
                <span>
                    <strong>Suspicious Activity Detected:</strong> You exceeded the allowed inactivity time limit. This session has been flagged for review.
                </span>
            </div>

            <button id="restart-btn" class="btn" style="margin-top: 1rem;">Take Test Again</button>
        </div>

    </div>

    <script src="script.js"></script>
</body>
</html>
