# LMS - Learning Management System

A complete Learning Management System for faculty to manage courses, upload materials, and conduct quizzes for students.

## Features

- **Multi-role Authentication**: Admin, Faculty, and Student roles
- **Course Management**: Create courses with invite links
- **Content Hierarchy**: Courses → Weeks → Lectures → Concepts → Materials
- **Quiz System**: MCQ quizzes with anti-cheating (tab-switch detection)
- **Email Notifications**: Registration, approval, quiz alerts, results
- **Feedback System**: Students and faculty can submit feedback

## Requirements

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Apache with mod_rewrite (XAMPP recommended)

## Installation

### 1. Clone/Download

Place the `lms-core` folder in your `htdocs` directory:
```
C:\xampp\htdocs\lms-core\
```

### 2. Create Database

1. Open phpMyAdmin (http://localhost/phpmyadmin)
2. Create a new database called `lms_db`
3. Import the `database.sql` file:
   - Click on `lms_db`
   - Go to "Import" tab
   - Choose `database.sql` file
   - Click "Go"

### 3. Configure

Edit `config.php` if needed:
- Database credentials (default: root with no password)
- SMTP settings for email
- App URL

### 4. Access

Open in browser:
```
http://localhost/lms-core/public/
```

## Default Accounts

After importing the database, these accounts are available:

| Role | Email | Password |
|------|-------|----------|
| Admin | admin@murad.phd | Admin@123 |
| Faculty | ali@murad.phd | Admin@123 |
| Faculty | sarah@university.edu | Admin@123 |
| Student | student1@test.com | Admin@123 |

**Note**: The default password hash is for "Admin@123". Generate new hashes for production.

## API Documentation

### Authentication

**Login**
```
POST /api/auth.php?action=login
{
  "email": "user@example.com",
  "password": "password123"
}
```

**Register (Student via Invite)**
```
POST /api/enrollment.php?action=register-with-invite
{
  "name": "John Doe",
  "email": "john@example.com",
  "password": "password123",
  "roll_no": "2024-CS-001",
  "invite_code": "ABC123-2024"
}
```

### Courses

**List Courses**
```
GET /api/courses.php?action=list
```

**Create Course** (Faculty)
```
POST /api/courses.php?action=create
{
  "name": "Introduction to Programming",
  "code": "CS101",
  "description": "Learn programming basics"
}
```

### Content

**Get Course Content Hierarchy**
```
GET /api/content.php?action=hierarchy&course_id=1
```

**Upload Material** (Faculty)
```
POST /api/content.php?action=upload-material
FormData: file, concept_id, title
```

### Quizzes

**Get Available Quizzes** (Student)
```
GET /api/quizzes.php?action=available&course_id=1
```

**Start Quiz** (Student)
```
POST /api/quizzes.php?action=start
{
  "quiz_id": 1
}
```

**Submit Quiz** (Student)
```
POST /api/quizzes.php?action=submit
{
  "attempt_id": 1,
  "answers": [0, 2, 1, 3],
  "is_violation": false
}
```

### External API (Question Insertion)

**Bulk Insert Questions**
```
POST /api/external/questions.php?action=bulk-insert
Header: X-API-Key: lms-master-key-2024-secure
{
  "concept_id": 1,
  "questions": [
    {
      "type": "text",
      "content": "What is 2+2?",
      "options": [
        {"id": 0, "type": "text", "content": "3"},
        {"id": 1, "type": "text", "content": "4"},
        {"id": 2, "type": "text", "content": "5"},
        {"id": 3, "type": "text", "content": "6"}
      ],
      "correctAnswer": 1,
      "difficulty": "low"
    }
  ]
}
```

## Folder Structure

```
lms-core/
├── config.php              # Configuration
├── database.sql            # Database schema
├── api/                    # REST API endpoints
│   ├── auth.php
│   ├── users.php
│   ├── courses.php
│   ├── content.php
│   ├── enrollment.php
│   ├── quizzes.php
│   ├── feedback.php
│   └── external/
│       └── questions.php
├── modules/                # Business logic
│   ├── auth/
│   ├── users/
│   ├── courses/
│   ├── content/
│   ├── enrollment/
│   ├── quiz/
│   ├── feedback/
│   └── email/
├── public/                 # Frontend
│   ├── index.html
│   ├── login.html
│   └── assets/
└── uploads/                # File storage
```

## Security Notes

1. Change the default API key in `config.php`
2. Use strong passwords
3. Enable HTTPS in production
4. The system blocks users after 5 failed login attempts for 30 minutes

## Quiz Anti-Cheat

- Tab-switch detection: If student switches tab during quiz, a 5-second grace period starts
- After 5 seconds, quiz auto-submits with empty answers
- Marked as "violation" with zero marks
- Faculty can see which students violated the policy

## License

MIT License
