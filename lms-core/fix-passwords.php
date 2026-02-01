<?php
/**
 * Password Fix Script
 * Run this once after importing database.sql to set correct passwords
 * 
 * DELETE THIS FILE AFTER USE!
 */

require_once 'config.php';

echo "<!DOCTYPE html><html><head><title>Fix Passwords</title>";
echo "<style>body{font-family:Arial,sans-serif;max-width:600px;margin:50px auto;padding:20px;}";
echo ".success{background:#d4edda;border:1px solid #c3e6cb;padding:15px;border-radius:5px;margin:10px 0;}";
echo ".warning{background:#fff3cd;border:1px solid #ffeeba;padding:15px;border-radius:5px;margin:10px 0;}";
echo "table{width:100%;border-collapse:collapse;margin:20px 0;}";
echo "th,td{border:1px solid #ddd;padding:10px;text-align:left;}";
echo "th{background:#f5f5f5;}</style></head><body>";

echo "<h1>LMS Password Fix</h1>";

try {
    $pdo = getDBConnection();
    
    // Generate correct password hash for "Admin@123"
    $password = 'Admin@123';
    $hash = password_hash($password, PASSWORD_BCRYPT);
    
    // Update all users with the correct hash AND unblock all users
    $stmt = $pdo->prepare("UPDATE users SET password_hash = ?, is_blocked = FALSE, blocked_until = NULL");
    $stmt->execute([$hash]);
    $count = $stmt->rowCount();
    
    // Clear login attempts table
    $pdo->exec("TRUNCATE TABLE login_attempts");
    
    echo "<div class='success'>";
    echo "<strong>Success!</strong> Updated {$count} user passwords.";
    echo "</div>";
    
    // Show users
    $stmt = $pdo->query("SELECT name, email, role FROM users WHERE deleted_at IS NULL ORDER BY role, name");
    $users = $stmt->fetchAll();
    
    echo "<h2>Login Credentials</h2>";
    echo "<p>All accounts now use password: <strong>Admin@123</strong></p>";
    
    echo "<table>";
    echo "<tr><th>Role</th><th>Name</th><th>Email</th><th>Password</th></tr>";
    foreach ($users as $user) {
        echo "<tr>";
        echo "<td>" . ucfirst($user['role']) . "</td>";
        echo "<td>{$user['name']}</td>";
        echo "<td>{$user['email']}</td>";
        echo "<td>Admin@123</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<div class='warning'>";
    echo "<strong>Security Warning:</strong> Delete this file immediately after use!<br>";
    echo "File location: <code>" . __FILE__ . "</code>";
    echo "</div>";
    
    echo "<p><a href='public/login.html' style='display:inline-block;background:#4f46e5;color:white;padding:10px 20px;text-decoration:none;border-radius:5px;'>Go to Login Page</a></p>";
    
} catch (Exception $e) {
    echo "<div style='background:#f8d7da;border:1px solid #f5c6cb;padding:15px;border-radius:5px;'>";
    echo "<strong>Error:</strong> " . $e->getMessage();
    echo "<br><br>Make sure you have:";
    echo "<ol>";
    echo "<li>Started MySQL in XAMPP</li>";
    echo "<li>Created database 'lms_db' in phpMyAdmin</li>";
    echo "<li>Imported database.sql into lms_db</li>";
    echo "</ol>";
    echo "</div>";
}

echo "</body></html>";
?>
