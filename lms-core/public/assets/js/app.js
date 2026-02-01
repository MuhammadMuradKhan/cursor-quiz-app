/**
 * LMS Application JavaScript
 */

// Determine API base path based on current location
const path = window.location.pathname;
const isInSubfolder = path.includes('/admin/') || path.includes('/faculty/') || path.includes('/student/');
const API_BASE = isInSubfolder ? '../../api' : '../api';

// =====================================================
// API HELPERS
// =====================================================

async function apiCall(endpoint, action, data = {}, method = 'POST') {
    const url = `${API_BASE}/${endpoint}?action=${action}`;
    
    const options = {
        method: method,
        credentials: 'include',
        headers: {
            'Content-Type': 'application/json',
        }
    };
    
    if (method !== 'GET') {
        options.body = JSON.stringify(data);
    }
    
    try {
        const response = await fetch(url, options);
        const result = await response.json();
        return result;
    } catch (error) {
        console.error('API Error:', error);
        return { success: false, message: 'Network error. Please try again.' };
    }
}

async function apiFormData(endpoint, action, formData) {
    const url = `${API_BASE}/${endpoint}?action=${action}`;
    
    try {
        const response = await fetch(url, {
            method: 'POST',
            credentials: 'include',
            body: formData
        });
        return await response.json();
    } catch (error) {
        console.error('API Error:', error);
        return { success: false, message: 'Network error. Please try again.' };
    }
}

// =====================================================
// AUTH FUNCTIONS
// =====================================================

async function checkAuth() {
    const result = await apiCall('auth.php', 'me');
    return result.success ? result.data : null;
}

async function login(email, password) {
    return await apiCall('auth.php', 'login', { email, password });
}

async function logout() {
    const result = await apiCall('auth.php', 'logout');
    if (result.success) {
        // Determine the base path based on current location
        const path = window.location.pathname;
        const isInSubfolder = path.includes('/admin/') || path.includes('/faculty/') || path.includes('/student/');
        const prefix = isInSubfolder ? '../' : '';
        window.location.href = prefix + 'login.html';
    }
    return result;
}

async function register(data) {
    return await apiCall('enrollment.php', 'register-with-invite', data);
}

async function forgotPassword(email) {
    return await apiCall('auth.php', 'forgot-password', { email });
}

async function resetPassword(token, password) {
    return await apiCall('auth.php', 'reset-password', { token, password });
}

// =====================================================
// COURSE FUNCTIONS
// =====================================================

async function getCourses() {
    return await apiCall('courses.php', 'list', {}, 'GET');
}

async function getCourse(courseId) {
    return await apiCall('courses.php', 'get', { id: courseId });
}

async function createCourse(data) {
    return await apiCall('courses.php', 'create', data);
}

async function updateCourse(courseId, data) {
    return await apiCall('courses.php', 'update', { id: courseId, ...data });
}

async function deleteCourse(courseId) {
    return await apiCall('courses.php', 'delete', { id: courseId });
}

async function getCourseByInvite(inviteCode) {
    return await apiCall('courses.php', 'by-invite', { invite_code: inviteCode });
}

// =====================================================
// CONTENT FUNCTIONS
// =====================================================

async function getContentHierarchy(courseId) {
    return await apiCall('content.php', 'hierarchy', { course_id: courseId });
}

async function createLecture(weekId, title) {
    return await apiCall('content.php', 'create-lecture', { week_id: weekId, title });
}

async function createConcept(lectureId, title) {
    return await apiCall('content.php', 'create-concept', { lecture_id: lectureId, title });
}

async function uploadMaterial(conceptId, file, title, type) {
    const formData = new FormData();
    formData.append('concept_id', conceptId);
    formData.append('file', file);
    formData.append('title', title);
    if (type) formData.append('type', type);
    return await apiFormData('content.php', 'upload-material', formData);
}

async function createMaterial(conceptId, type, title, content, externalUrl) {
    return await apiCall('content.php', 'create-material', {
        concept_id: conceptId,
        type,
        title,
        content,
        external_url: externalUrl
    });
}

// =====================================================
// ENROLLMENT FUNCTIONS
// =====================================================

async function getEnrollments(courseId, status = null) {
    const data = { course_id: courseId };
    if (status) data.status = status;
    return await apiCall('enrollment.php', 'list', data);
}

async function approveEnrollment(enrollmentId) {
    return await apiCall('enrollment.php', 'approve', { enrollment_id: enrollmentId });
}

async function rejectEnrollment(enrollmentId) {
    return await apiCall('enrollment.php', 'reject', { enrollment_id: enrollmentId });
}

async function removeEnrollment(enrollmentId) {
    return await apiCall('enrollment.php', 'remove', { enrollment_id: enrollmentId });
}

async function getMyEnrollments() {
    return await apiCall('enrollment.php', 'my-enrollments', {}, 'GET');
}

// =====================================================
// QUIZ FUNCTIONS
// =====================================================

async function getQuestions(conceptId, includeInactive = false) {
    return await apiCall('quizzes.php', 'questions', { 
        concept_id: conceptId, 
        include_inactive: includeInactive ? 'true' : 'false' 
    });
}

async function createQuestion(data) {
    return await apiCall('quizzes.php', 'create-question', data);
}

async function updateQuestion(questionId, data) {
    return await apiCall('quizzes.php', 'update-question', { question_id: questionId, ...data });
}

async function deleteQuestion(questionId) {
    return await apiCall('quizzes.php', 'delete-question', { question_id: questionId });
}

async function toggleQuestion(questionId) {
    return await apiCall('quizzes.php', 'toggle-question', { question_id: questionId });
}

async function getQuizzes(conceptId) {
    return await apiCall('quizzes.php', 'quizzes', { concept_id: conceptId });
}

async function createQuiz(data) {
    return await apiCall('quizzes.php', 'create-quiz', data);
}

async function getAvailableQuizzes(courseId) {
    return await apiCall('quizzes.php', 'available', { course_id: courseId });
}

async function startQuiz(quizId) {
    return await apiCall('quizzes.php', 'start', { quiz_id: quizId });
}

async function submitQuiz(attemptId, answers, isViolation = false, violationReason = null) {
    return await apiCall('quizzes.php', 'submit', {
        attempt_id: attemptId,
        answers,
        is_violation: isViolation,
        violation_reason: violationReason
    });
}

async function getMyResults(attemptId) {
    return await apiCall('quizzes.php', 'my-results', { attempt_id: attemptId });
}

async function getQuizResults(quizId) {
    return await apiCall('quizzes.php', 'quiz-results', { quiz_id: quizId });
}

async function getCourseLeaderboard(courseId) {
    return await apiCall('quizzes.php', 'course-leaderboard', { course_id: courseId });
}

// =====================================================
// FEEDBACK FUNCTIONS
// =====================================================

async function submitFeedback(feedbackType, content, targetId = null) {
    return await apiCall('feedback.php', 'submit', {
        feedback_type: feedbackType,
        content,
        target_id: targetId
    });
}

async function getMyFeedback() {
    return await apiCall('feedback.php', 'my-feedback', {}, 'GET');
}

async function getFacultyFeedback() {
    return await apiCall('feedback.php', 'faculty-feedback', {}, 'GET');
}

async function getAllFeedback(type = null) {
    return await apiCall('feedback.php', 'list', { type });
}

// =====================================================
// USER FUNCTIONS (Admin)
// =====================================================

async function getUsers(role = null) {
    const data = {};
    if (role) data.role = role;
    return await apiCall('users.php', 'list', data);
}

async function createUser(data) {
    return await apiCall('users.php', 'create', data);
}

async function deleteUser(userId) {
    return await apiCall('users.php', 'delete', { id: userId });
}

// =====================================================
// UI HELPERS
// =====================================================

function showAlert(message, type = 'info') {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type}`;
    alertDiv.textContent = message;
    
    const container = document.querySelector('.container') || document.body;
    container.insertBefore(alertDiv, container.firstChild);
    
    setTimeout(() => alertDiv.remove(), 5000);
}

function showLoading(container) {
    container.innerHTML = `
        <div class="loading">
            <div class="spinner"></div>
            <p>Loading...</p>
        </div>
    `;
}

function formatDate(dateStr) {
    if (!dateStr) return '-';
    const date = new Date(dateStr);
    return date.toLocaleDateString() + ' ' + date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// =====================================================
// NAVIGATION HELPERS
// =====================================================

function getUrlParam(param) {
    const urlParams = new URLSearchParams(window.location.search);
    return urlParams.get(param);
}

function setUrlParam(param, value) {
    const url = new URL(window.location);
    url.searchParams.set(param, value);
    window.history.pushState({}, '', url);
}

// =====================================================
// AUTH CHECK ON PAGE LOAD
// =====================================================

async function requireAuth(allowedRoles = null) {
    const user = await checkAuth();
    
    // Determine the base path based on current location
    const path = window.location.pathname;
    const isInSubfolder = path.includes('/admin/') || path.includes('/faculty/') || path.includes('/student/');
    const prefix = isInSubfolder ? '../' : '';
    
    if (!user) {
        window.location.href = prefix + 'login.html';
        return null;
    }
    
    if (allowedRoles && !allowedRoles.includes(user.role)) {
        window.location.href = prefix + getDashboardUrl(user.role);
        return null;
    }
    
    return user;
}

function getDashboardUrl(role) {
    // Check if we're already in a role subfolder
    const path = window.location.pathname;
    const isInSubfolder = path.includes('/admin/') || path.includes('/faculty/') || path.includes('/student/');
    const prefix = isInSubfolder ? '../' : '';
    
    switch (role) {
        case 'admin': return prefix + 'admin/dashboard.html';
        case 'faculty': return prefix + 'faculty/dashboard.html';
        case 'student': return prefix + 'student/dashboard.html';
        default: return prefix + 'login.html';
    }
}

// =====================================================
// TAB SWITCH DETECTION (For Quiz)
// =====================================================

let tabSwitchTimeout = null;
let tabSwitchCallback = null;

function initTabSwitchDetection(callback) {
    tabSwitchCallback = callback;
    
    document.addEventListener('visibilitychange', handleVisibilityChange);
    window.addEventListener('blur', handleWindowBlur);
}

function stopTabSwitchDetection() {
    document.removeEventListener('visibilitychange', handleVisibilityChange);
    window.removeEventListener('blur', handleWindowBlur);
    if (tabSwitchTimeout) {
        clearTimeout(tabSwitchTimeout);
    }
}

function handleVisibilityChange() {
    if (document.hidden) {
        startViolationTimer();
    } else {
        cancelViolationTimer();
    }
}

function handleWindowBlur() {
    startViolationTimer();
}

function startViolationTimer() {
    if (tabSwitchTimeout) return;
    
    console.warn('Tab switch detected! You have 5 seconds to return.');
    showAlert('Warning: Tab switch detected! Return within 5 seconds or quiz will be auto-submitted.', 'warning');
    
    tabSwitchTimeout = setTimeout(() => {
        console.error('Tab switch violation - auto submitting quiz');
        if (tabSwitchCallback) {
            tabSwitchCallback();
        }
    }, 5000);
}

function cancelViolationTimer() {
    if (tabSwitchTimeout) {
        clearTimeout(tabSwitchTimeout);
        tabSwitchTimeout = null;
    }
}
