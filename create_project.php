<?php
require_once 'auth.php';

if (!isLoggedIn()) {
    header('Location: index.php');
    exit;
}

require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $project_name = $_POST['project_name'] ?? '';
    $project_path = $_POST['project_path'] ?? '';
    $username = getCurrentUser();
    
    // 验证输入
    if (empty($project_name) || empty($project_path)) {
        die("项目名称和路径不能为空");
    }
    
    // 验证路径格式
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $project_path)) {
        die("项目路径只能包含字母、数字和下划线");
    }
    
    $db = new SQLite3(Config::$db_file);
    
    // 检查项目数量限制
    $max_projects = isProUser() ? Config::$max_projects_pro : Config::$max_projects_free;
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM projects WHERE project_owner = ?");
    $stmt->bindValue(1, $username, SQLITE3_TEXT);
    $result = $stmt->execute();
    $count = $result->fetchArray(SQLITE3_ASSOC)['count'];
    
    if ($count >= $max_projects) {
        die("已达到项目数量限制");
    }
    
    // 检查路径是否已存在
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM projects WHERE project_path = ?");
    $stmt->bindValue(1, $project_path, SQLITE3_TEXT);
    $result = $stmt->execute();
    $path_count = $result->fetchArray(SQLITE3_ASSOC)['count'];
    
    if ($path_count > 0) {
        die("项目路径已存在");
    }
    
    // 创建项目
    $stmt = $db->prepare("INSERT INTO projects (project_name, project_path, project_owner) VALUES (?, ?, ?)");
    $stmt->bindValue(1, $project_name, SQLITE3_TEXT);
    $stmt->bindValue(2, $project_path, SQLITE3_TEXT);
    $stmt->bindValue(3, $username, SQLITE3_TEXT);
    
    if ($stmt->execute()) {
        // 创建项目目录
        $project_dir = Config::$upload_dir . $project_path;
        if (!file_exists($project_dir)) {
            mkdir($project_dir, 0777, true);
        }
        
        header('Location: dashboard.php');
        exit;
    } else {
        die("创建项目失败");
    }
}
?>