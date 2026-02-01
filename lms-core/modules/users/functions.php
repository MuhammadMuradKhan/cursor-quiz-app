<?php
/**
 * User Management Functions
 */

require_once __DIR__ . '/../../config.php';

/**
 * Get all users with optional filters
 */
function getAllUsers($role = null, $search = null, $includeDeleted = false) {
    $pdo = getDBConnection();
    
    $sql = "SELECT id, name, email, role, roll_no, is_blocked, created_at, deleted_at FROM users WHERE 1=1";
    $params = [];
    
    if (!$includeDeleted) {
        $sql .= " AND deleted_at IS NULL";
    }
    
    if ($role) {
        $sql .= " AND role = ?";
        $params[] = $role;
    }
    
    if ($search) {
        $sql .= " AND (name LIKE ? OR email LIKE ? OR roll_no LIKE ?)";
        $searchTerm = "%{$search}%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    $sql .= " ORDER BY created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    return $stmt->fetchAll();
}

/**
 * Get users by role
 */
function getUsersByRole($role) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("
        SELECT id, name, email, role, roll_no, created_at 
        FROM users 
        WHERE role = ? AND deleted_at IS NULL 
        ORDER BY name ASC
    ");
    $stmt->execute([$role]);
    return $stmt->fetchAll();
}

/**
 * Update user
 */
function updateUser($userId, $updates) {
    $pdo = getDBConnection();
    
    // Check if user exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    if (!$stmt->fetch()) {
        return ['success' => false, 'message' => 'User not found'];
    }
    
    // Check email uniqueness if updating email
    if (isset($updates['email'])) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ? AND deleted_at IS NULL");
        $stmt->execute([$updates['email'], $userId]);
        if ($stmt->fetch()) {
            return ['success' => false, 'message' => 'Email already in use'];
        }
    }
    
    // Check roll_no uniqueness if updating roll_no
    if (isset($updates['roll_no']) && $updates['roll_no']) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE roll_no = ? AND id != ? AND deleted_at IS NULL");
        $stmt->execute([$updates['roll_no'], $userId]);
        if ($stmt->fetch()) {
            return ['success' => false, 'message' => 'Roll number already in use'];
        }
    }
    
    // Build update query
    $setClauses = [];
    $params = [];
    
    foreach ($updates as $field => $value) {
        $setClauses[] = "{$field} = ?";
        $params[] = $value;
    }
    
    $setClauses[] = "updated_at = NOW()";
    $params[] = $userId;
    
    $sql = "UPDATE users SET " . implode(', ', $setClauses) . " WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    return ['success' => true, 'message' => 'User updated successfully'];
}

/**
 * Delete user (soft delete)
 */
function deleteUser($userId) {
    $pdo = getDBConnection();
    
    $stmt = $pdo->prepare("UPDATE users SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL");
    $stmt->execute([$userId]);
    
    if ($stmt->rowCount() > 0) {
        return ['success' => true, 'message' => 'User deleted successfully'];
    }
    
    return ['success' => false, 'message' => 'User not found or already deleted'];
}

/**
 * Restore deleted user
 */
function restoreUser($userId) {
    $pdo = getDBConnection();
    
    $stmt = $pdo->prepare("UPDATE users SET deleted_at = NULL WHERE id = ? AND deleted_at IS NOT NULL");
    $stmt->execute([$userId]);
    
    if ($stmt->rowCount() > 0) {
        return ['success' => true, 'message' => 'User restored successfully'];
    }
    
    return ['success' => false, 'message' => 'User not found or not deleted'];
}

/**
 * Get user statistics
 */
function getUserStats() {
    $pdo = getDBConnection();
    
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) as admins,
            SUM(CASE WHEN role = 'faculty' THEN 1 ELSE 0 END) as faculty,
            SUM(CASE WHEN role = 'student' THEN 1 ELSE 0 END) as students
        FROM users 
        WHERE deleted_at IS NULL
    ");
    
    return $stmt->fetch();
}

/**
 * Block user manually
 */
function blockUserManually($userId, $duration = null) {
    $pdo = getDBConnection();
    
    $blockedUntil = $duration ? date('Y-m-d H:i:s', time() + $duration) : null;
    
    $stmt = $pdo->prepare("UPDATE users SET is_blocked = TRUE, blocked_until = ? WHERE id = ?");
    $stmt->execute([$blockedUntil, $userId]);
    
    return ['success' => true, 'message' => 'User blocked successfully'];
}

/**
 * Unblock user manually
 */
function unblockUser($userId) {
    $pdo = getDBConnection();
    
    $stmt = $pdo->prepare("UPDATE users SET is_blocked = FALSE, blocked_until = NULL WHERE id = ?");
    $stmt->execute([$userId]);
    
    return ['success' => true, 'message' => 'User unblocked successfully'];
}
