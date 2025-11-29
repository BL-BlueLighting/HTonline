<?php
require_once 'config.php';

session_start();

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function getCurrentUser() {
    return $_SESSION['username'] ?? null;
}

function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

function isProUser() {
    return $_SESSION['isPro'] ?? false;
}

function isAdmin() {
    return $_SESSION['isAdmin'] ?? false;
}

function loginUser($username, $keypass) {
    $db = new SQLite3(Config::$db_file);
    $hashed_keypass = md5($keypass);
    
    $stmt = $db->prepare("SELECT * FROM users WHERE username = ? AND keypass = ?");
    $stmt->bindValue(1, $username, SQLITE3_TEXT);
    $stmt->bindValue(2, $hashed_keypass, SQLITE3_TEXT);
    
    $result = $stmt->execute();
    $user = $result->fetchArray(SQLITE3_ASSOC);
    
    if ($user) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['isPro'] = (bool)$user['isPro'];
        $_SESSION['isAdmin'] = (bool)$user['isAdmin'];
        return true;
    }
    
    return false;
}

function logoutUser() {
    session_destroy();
    header('Location: index.php');
    exit;
}

function checkUsernameExists($username) {
    $db = new SQLite3(Config::$db_file);
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE username = ?");
    $stmt->bindValue(1, $username, SQLITE3_TEXT);
    $result = $stmt->execute();
    $row = $result->fetchArray(SQLITE3_ASSOC);
    return $row['count'] > 0;
}

function registerUser($username, $keypass) {
    $db = new SQLite3(Config::$db_file);
    
    // 检查用户名是否已存在
    if (checkUsernameExists($username)) {
        return false;
    }
    
    $hashed_keypass = md5($keypass);
    
    try {
        $stmt = $db->prepare("INSERT INTO users (username, keypass) VALUES (?, ?)");
        $stmt->bindValue(1, $username, SQLITE3_TEXT);
        $stmt->bindValue(2, $hashed_keypass, SQLITE3_TEXT);
        
        if ($stmt->execute()) {
            return true;
        } else {
            return false;
        }
    } catch (Exception $e) {
        error_log("注册错误: " . $e->getMessage());
        return false;
    }
}

function changePassword($user_id, $current_password, $new_password) {
    $db = new SQLite3(Config::$db_file);
    
    // 验证当前密码
    $hashed_current_password = md5($current_password);
    $stmt = $db->prepare("SELECT id FROM users WHERE id = ? AND keypass = ?");
    $stmt->bindValue(1, $user_id, SQLITE3_INTEGER);
    $stmt->bindValue(2, $hashed_current_password, SQLITE3_TEXT);
    
    $result = $stmt->execute();
    $user = $result->fetchArray(SQLITE3_ASSOC);
    
    if (!$user) {
        return "当前密码不正确";
    }
    
    // 更新密码
    $hashed_new_password = md5($new_password);
    $stmt = $db->prepare("UPDATE users SET keypass = ? WHERE id = ?");
    $stmt->bindValue(1, $hashed_new_password, SQLITE3_TEXT);
    $stmt->bindValue(2, $user_id, SQLITE3_INTEGER);
    
    if ($stmt->execute()) {
        return true;
    } else {
        return "密码更新失败";
    }
}

// 初始化数据库（如果不存在）
function ensureDatabaseInitialized() {
    if (!file_exists(Config::$db_file)) {
        initDatabase();
    }
}

// 调用初始化检查
ensureDatabaseInitialized();
?>