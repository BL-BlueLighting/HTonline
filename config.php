<?php

error_reporting(0); // no error reporting
class Config {
    public static $db_file = "htonline.db";
    public static $upload_dir = "routers/";
    public static $max_projects_free = 3;
    public static $max_projects_pro = 50;
    public static $max_files_free = 3;
    public static $max_files_pro = 300;
}

// 初始化数据库
function initDatabase() {
    $db = new SQLite3(Config::$db_file);
    
    // 创建用户表
    $db->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE NOT NULL,
        keypass TEXT NOT NULL,
        isPro BOOLEAN DEFAULT 0,
        isAdmin BOOLEAN DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    
    // 创建项目表
    $db->exec("CREATE TABLE IF NOT EXISTS projects (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        project_name TEXT NOT NULL,
        project_path TEXT UNIQUE NOT NULL,
        project_owner TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (project_owner) REFERENCES users(username)
    )");
    
    // 创建文件表
    $db->exec("CREATE TABLE IF NOT EXISTS project_files (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        project_id INTEGER NOT NULL,
        file_name TEXT NOT NULL,
        file_content TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (project_id) REFERENCES projects(id)
    )");
    
    // 创建默认管理员账户
    $admin_keypass = md5('admin123');
    $stmt = $db->prepare("INSERT OR IGNORE INTO users (username, keypass, isAdmin) VALUES (?, ?, 1)");
    $stmt->bindValue(1, 'admin', SQLITE3_TEXT);
    $stmt->bindValue(2, $admin_keypass, SQLITE3_TEXT);
    $stmt->execute();
    // 在 initDatabase() 函数中添加
    $db->exec("PRAGMA foreign_keys = ON");
    
    return $db;
}

// 创建项目目录
if (!file_exists(Config::$upload_dir)) {
    mkdir(Config::$upload_dir, 0777, true);
}
?>