# LMS (Learning Management System) - Master Plan

## Project Overview

**Purpose:** LMS for university faculty to manage courses, upload materials, and conduct quizzes for students.

| Attribute | Value |
|-----------|-------|
| **Scale** | ~2500 students, 5 faculty, 1 admin |
| **Timeline** | 2 weeks |
| **Stack** | Core PHP + MySQL + JavaScript |
| **Deployment** | XAMPP (local) |
| **Architecture** | REST API backend + Separate frontend |

---

## User Roles

| Role | Capabilities |
|------|-------------|
| **Admin** | Create faculty accounts, manage everything |
| **Faculty** | Create courses, upload content, manage quizzes, approve students |
| **Student** | Join courses via invite link, view materials, attempt quizzes |

*One person can have multiple roles (e.g., Admin + Faculty)*

---

## Authentication & Security

| Feature | Implementation |
|---------|----------------|
| **Login** | Email + Password |
| **Registration (Student)** | Name, Email, Password, Roll Number (unique) |
| **Registration (Faculty)** | Name, University Email, Password (created by Admin) |
| **Password Strength** | Standard (8+ chars, mixed) |
| **Forgot Password** | Email reset link |
| **Session Duration** | 1 week |
| **Failed Login Block** | 30 minutes after 5 failed attempts |
| **Password Storage** | bcrypt hash |

---

## Content Hierarchy

```
Course
  └── Weeks (16 per semester)
        └── Lectures (multiple per week)
              └── Concepts (multiple per lecture)
                    └── Materials (PDF, Video links, HTML, PPT, ZIP - max 25MB)
```

---

## Core User Flows

### Student Registration Flow
1. Faculty creates course → Gets invite link
2. Faculty shares link to student groups
3. Student clicks link → Registers (email, password, roll number, name)
4. Student appears in faculty dashboard as "pending"
5. Faculty approves → Student can access course

### Quiz Flow
1. Faculty uploads materials to concept
2. Third-party system sends MCQ questions via API (master API key)
3. Faculty tags questions (difficulty: low/medium/high, include/exclude)
4. Faculty creates quiz (# questions, time frame)
5. Student sees "Start Quiz" button on concept
6. Student has 10 minutes to complete
7. **Tab switch = 5 sec wait → auto-submit → zero marks → violation flagged**
8. Results visible in student dashboard (shows correct/wrong answers)
9. Public leaderboard per concept

---

## Course Features

| Feature | Details |
|---------|---------|
| **Course Fields** | Name, Code, Description, Thumbnail, Header Image, Start Date, End Date |
| **Public Page** | Each course has guest-viewable webpage |
| **Invite Link** | One per course, never expires, unlimited students |
| **Ownership** | Single faculty per course |
| **Deletion** | Soft delete (preserves history) |

---

## Quiz Question Structure

Questions support multiple content types for both questions and options:

```json
{
  "id": 1,
  "type": "text|image|html|video",
  "content": "question content or media URL",
  "questionText": "optional text description for image/html/video types",
  "options": [
    { "id": 0, "type": "text|image|html", "content": "option content" },
    { "id": 1, "type": "text|image|html", "content": "option content" },
    { "id": 2, "type": "text|image|html", "content": "option content" },
    { "id": 3, "type": "text|image|html", "content": "option content" }
  ],
  "correctAnswer": 2
}
```

---

## Anti-Cheat System

| Trigger | Action |
|---------|--------|
| Student switches tab | Wait 5 seconds |
| After 5 seconds | Auto-submit all unanswered as empty |
| Result | Quiz marked as VIOLATION, zero marks awarded |
| Faculty View | Can see which students violated policy |

---

## Email Notifications

**SMTP Configuration:**
- Server: `murad.phd`
- Port: `465` (SSL)
- Sender: `ali@murad.phd`

**Email Events:**
| Event | Recipient |
|-------|-----------|
| Student registered | Student (confirmation) |
| Student approved | Student (notification) |
| New quiz available | All enrolled students |
| Quiz completed | Student (results) |
| Password reset requested | User (reset link) |
| Feedback received | Admin/Faculty |

---

## Feedback System

| Aspect | Details |
|--------|---------|
| **Student Feedback** | Course, Concept, Quiz, Faculty, General suggestions |
| **Faculty Feedback** | Bugs, Feature requests, Student reports |
| **Anonymous** | No - feedback shows who submitted |
| **Visibility** | Admin sees all, Faculty sees their course feedback |

---

## API Design

### Response Format
```json
{
  "success": true,
  "data": { },
  "message": "Operation completed"
}
```

### Authentication
- Session-based for web frontend
- Master API key for external question insertion

---

## Folder Structure

```
lms-core/
├── config.php                 # DB + SMTP + API key configuration
├── database.sql               # Database schema (import into phpMyAdmin)
├── README.md                  # Setup instructions
│
├── api/                       # REST API endpoints
│   ├── auth.php               # Login, register, logout, password reset
│   ├── users.php              # User management (admin)
│   ├── courses.php            # Course CRUD
│   ├── weeks.php              # Week management
│   ├── lectures.php           # Lecture management
│   ├── concepts.php           # Concept management
│   ├── materials.php          # Material upload/download
│   ├── enrollment.php         # Join course, approve students
│   ├── questions.php          # Question management (faculty)
│   ├── quizzes.php            # Quiz creation and attempts
│   ├── feedback.php           # Feedback submission/view
│   └── external/
│       └── questions.php      # External API for question insertion
│
├── modules/                   # Business logic (no HTML)
│   ├── auth/
│   │   ├── functions.php      # register, login, logout, reset password
│   │   └── middleware.php     # Session check, role check
│   ├── users/
│   │   └── functions.php      # User CRUD
│   ├── courses/
│   │   └── functions.php      # Course CRUD, invite links
│   ├── content/
│   │   └── functions.php      # Weeks, lectures, concepts, materials
│   ├── enrollment/
│   │   └── functions.php      # Enrollment logic
│   ├── quiz/
│   │   └── functions.php      # Questions, quizzes, attempts, grading
│   ├── feedback/
│   │   └── functions.php      # Feedback logic
│   └── email/
│       └── functions.php      # Email sending
│
├── public/                    # Frontend (HTML/JS/CSS)
│   ├── index.html             # Landing page / Router
│   ├── login.html
│   ├── register.html
│   ├── forgot-password.html
│   ├── reset-password.html
│   ├── student/
│   │   ├── dashboard.html
│   │   ├── course.html
│   │   ├── quiz.html
│   │   └── feedback.html
│   ├── faculty/
│   │   ├── dashboard.html
│   │   ├── course-manage.html
│   │   ├── content-manage.html
│   │   ├── questions-manage.html
│   │   ├── quiz-manage.html
│   │   ├── students-manage.html
│   │   └── feedback.html
│   ├── admin/
│   │   ├── dashboard.html
│   │   ├── users-manage.html
│   │   └── feedback-view.html
│   ├── course-public.html     # Guest-viewable course page
│   └── assets/
│       ├── css/
│       │   └── style.css
│       └── js/
│           └── app.js
│
├── uploads/                   # File storage (PDFs, images, etc.)
│   └── .gitkeep
│
└── tehreem-quiz-component/    # Existing quiz UI (to be integrated)
```

---

## Database Schema

### Table: users
```sql
- id (INT, PK, AUTO_INCREMENT)
- name (VARCHAR 255)
- email (VARCHAR 255, UNIQUE)
- password_hash (VARCHAR 255)
- role (ENUM: 'admin', 'faculty', 'student')
- roll_no (VARCHAR 50, UNIQUE, nullable - for students)
- is_blocked (BOOLEAN, default FALSE)
- blocked_until (DATETIME, nullable)
- created_at (DATETIME)
- updated_at (DATETIME)
- deleted_at (DATETIME, nullable - soft delete)
```

### Table: courses
```sql
- id (INT, PK, AUTO_INCREMENT)
- faculty_id (INT, FK -> users)
- name (VARCHAR 255)
- code (VARCHAR 50)
- description (TEXT)
- thumbnail (VARCHAR 255 - file path)
- header_image (VARCHAR 255 - file path)
- start_date (DATE)
- end_date (DATE)
- invite_code (VARCHAR 50, UNIQUE)
- created_at (DATETIME)
- updated_at (DATETIME)
- deleted_at (DATETIME, nullable)
```

### Table: weeks
```sql
- id (INT, PK, AUTO_INCREMENT)
- course_id (INT, FK -> courses)
- week_number (INT)
- title (VARCHAR 255)
- created_at (DATETIME)
- updated_at (DATETIME)
```

### Table: lectures
```sql
- id (INT, PK, AUTO_INCREMENT)
- week_id (INT, FK -> weeks)
- title (VARCHAR 255)
- order_index (INT)
- created_at (DATETIME)
- updated_at (DATETIME)
```

### Table: concepts
```sql
- id (INT, PK, AUTO_INCREMENT)
- lecture_id (INT, FK -> lectures)
- title (VARCHAR 255)
- order_index (INT)
- created_at (DATETIME)
- updated_at (DATETIME)
```

### Table: materials
```sql
- id (INT, PK, AUTO_INCREMENT)
- concept_id (INT, FK -> concepts)
- type (ENUM: 'pdf', 'video', 'html', 'ppt', 'zip', 'other')
- title (VARCHAR 255)
- file_path (VARCHAR 255, nullable)
- content (TEXT, nullable - for html type)
- external_url (VARCHAR 500, nullable - for video links)
- created_at (DATETIME)
- updated_at (DATETIME)
```

### Table: enrollments
```sql
- id (INT, PK, AUTO_INCREMENT)
- course_id (INT, FK -> courses)
- student_id (INT, FK -> users)
- status (ENUM: 'pending', 'approved', 'rejected')
- created_at (DATETIME)
- updated_at (DATETIME)
```

### Table: questions
```sql
- id (INT, PK, AUTO_INCREMENT)
- concept_id (INT, FK -> concepts)
- type (ENUM: 'text', 'image', 'html', 'video')
- content (TEXT)
- question_text (TEXT, nullable)
- options (JSON)
- correct_answer (INT)
- difficulty (ENUM: 'low', 'medium', 'high')
- is_active (BOOLEAN, default TRUE)
- created_at (DATETIME)
- updated_at (DATETIME)
```

### Table: quizzes
```sql
- id (INT, PK, AUTO_INCREMENT)
- concept_id (INT, FK -> concepts)
- title (VARCHAR 255)
- num_questions (INT)
- duration_minutes (INT, default 10)
- start_time (DATETIME)
- end_time (DATETIME)
- created_by (INT, FK -> users)
- created_at (DATETIME)
- updated_at (DATETIME)
```

### Table: quiz_attempts
```sql
- id (INT, PK, AUTO_INCREMENT)
- quiz_id (INT, FK -> quizzes)
- student_id (INT, FK -> users)
- question_ids (JSON - array of question IDs assigned)
- answers (JSON - student's answers)
- score (INT)
- max_score (INT)
- started_at (DATETIME)
- submitted_at (DATETIME, nullable)
- is_violation (BOOLEAN, default FALSE)
- violation_reason (VARCHAR 255, nullable)
```

### Table: feedback
```sql
- id (INT, PK, AUTO_INCREMENT)
- user_id (INT, FK -> users)
- feedback_type (ENUM: 'course', 'concept', 'quiz', 'faculty', 'system', 'general')
- target_id (INT, nullable - course_id, concept_id, etc.)
- content (TEXT)
- created_at (DATETIME)
```

### Table: password_resets
```sql
- id (INT, PK, AUTO_INCREMENT)
- email (VARCHAR 255)
- token (VARCHAR 255)
- created_at (DATETIME)
- expires_at (DATETIME)
- used (BOOLEAN, default FALSE)
```

### Table: login_attempts
```sql
- id (INT, PK, AUTO_INCREMENT)
- email (VARCHAR 255)
- ip_address (VARCHAR 45)
- success (BOOLEAN)
- attempted_at (DATETIME)
```

### Table: api_keys
```sql
- id (INT, PK, AUTO_INCREMENT)
- key_value (VARCHAR 255, UNIQUE)
- description (VARCHAR 255)
- is_active (BOOLEAN, default TRUE)
- created_at (DATETIME)
```

---

## Development Phases

### Phase 1: Foundation (Days 1-2)
- [x] Create folder structure
- [ ] Create database.sql with all tables
- [ ] Create config.php (DB, SMTP, API key)
- [ ] Implement auth module (register, login, logout)
- [ ] Implement password reset with email
- [ ] Implement login blocking
- [ ] Create basic API endpoints for auth
- [ ] Test with Postman

### Phase 2: User & Course Management (Days 3-4)
- [ ] Admin: Create/manage faculty accounts
- [ ] Faculty: Create/edit/delete courses
- [ ] Course images upload (thumbnail, header)
- [ ] Course public page (guest view)
- [ ] Invite link generation
- [ ] API endpoints for courses

### Phase 3: Enrollment System (Day 5)
- [ ] Student registration via invite link
- [ ] Faculty approval dashboard
- [ ] Approve/reject students
- [ ] Remove students
- [ ] API endpoints for enrollment

### Phase 4: Content Management (Days 6-7)
- [ ] Weeks CRUD (16 weeks per course)
- [ ] Lectures CRUD
- [ ] Concepts CRUD
- [ ] Material upload (25MB limit)
- [ ] Material types handling
- [ ] Material download
- [ ] API endpoints for content

### Phase 5: Quiz System (Days 8-10)
- [ ] External API for question insertion
- [ ] Faculty question management (add, edit, delete, tag)
- [ ] Quiz creation per concept
- [ ] Random question selection
- [ ] Quiz attempt with timer
- [ ] Tab-switch detection integration
- [ ] Violation handling
- [ ] Score calculation
- [ ] Results display
- [ ] API endpoints for quizzes

### Phase 6: Email Notifications (Day 11)
- [ ] Email module with SMTP
- [ ] Registration confirmation
- [ ] Approval notification
- [ ] Quiz available notification
- [ ] Results notification
- [ ] Password reset email

### Phase 7: Feedback System (Day 12)
- [ ] Feedback submission (student & faculty)
- [ ] Feedback viewing (admin & faculty)
- [ ] API endpoints for feedback

### Phase 8: Frontend & Dashboards (Days 13-14)
- [ ] Login/Register pages
- [ ] Student dashboard
- [ ] Faculty dashboard
- [ ] Admin dashboard
- [ ] Course view pages
- [ ] Quiz interface (integrate tehreem-quiz-component)
- [ ] Mobile-responsive design (basic)

### Phase 9: Testing & Polish
- [ ] Insert dummy data
- [ ] Test all flows
- [ ] Fix bugs
- [ ] Postman collection

---

## Dummy Data Plan

### Users
- 1 Admin (admin@murad.phd)
- 3 Faculty members
- 20 Sample students

### Courses
- 3 Sample courses with complete hierarchy
- Each course: 4 weeks, 2 lectures/week, 3 concepts/lecture

### Content
- Sample materials (placeholder PDFs, video links)

### Questions
- 50+ sample MCQ questions across concepts
- Mix of text, image, and HTML types

### Quizzes
- Sample quizzes for testing

---

## Configuration (config.php)

```php
<?php
// Database
define('DB_HOST', 'localhost');
define('DB_NAME', 'lms_db');
define('DB_USER', 'root');
define('DB_PASS', '');

// SMTP
define('SMTP_HOST', 'murad.phd');
define('SMTP_PORT', 465);
define('SMTP_USER', 'ali@murad.phd');
define('SMTP_PASS', 'YOUR_PASSWORD_HERE');
define('SMTP_FROM', 'ali@murad.phd');
define('SMTP_FROM_NAME', 'LMS System');

// API
define('MASTER_API_KEY', 'your-secure-api-key-here');

// App
define('APP_URL', 'http://localhost/lms-core');
define('UPLOAD_MAX_SIZE', 25 * 1024 * 1024); // 25MB
define('SESSION_LIFETIME', 7 * 24 * 60 * 60); // 1 week

// Security
define('LOGIN_MAX_ATTEMPTS', 5);
define('LOGIN_BLOCK_DURATION', 30 * 60); // 30 minutes
define('PASSWORD_RESET_EXPIRY', 60 * 60); // 1 hour
```

---

## Notes

1. **Quiz Component:** The `tehreem-quiz-component` folder contains existing JavaScript quiz UI with tab-switch detection. Will be integrated in Phase 8.

2. **File Storage:** All uploads stored in `uploads/` folder with organized subdirectories.

3. **Soft Delete:** Courses and users use soft delete (deleted_at timestamp) to preserve history.

4. **API-First:** All functionality exposed via REST API, frontend consumes API.

5. **Mobile-First:** Basic responsive design, students primarily use mobile phones.
