<?php
require_once 'auth.php';

if (!isLoggedIn()) {
    header('Content-Type: application/json');
    echo json_encode(['error' => '未登录']);
    exit;
}

require_once 'config.php';

$file_id = $_GET['file_id'] ?? null;
if (!$file_id) {
    header('Content-Type: application/json');
    echo json_encode(['error' => '文件ID不能为空']);
    exit;
}

$db = new SQLite3(Config::$db_file);
$username = getCurrentUser();

// 验证文件访问权限
if (!isAdmin()) {
    $stmt = $db->prepare("SELECT pf.* FROM project_files pf 
                         JOIN projects p ON pf.project_id = p.id 
                         WHERE pf.id = ? AND p.project_owner = ?");
    $stmt->bindValue(1, $file_id, SQLITE3_INTEGER);
    $stmt->bindValue(2, $username, SQLITE3_TEXT);
} else {
    $stmt = $db->prepare("SELECT * FROM project_files WHERE id = ?");
    $stmt->bindValue(1, $file_id, SQLITE3_INTEGER);
}

$result = $stmt->execute();
$file = $result->fetchArray(SQLITE3_ASSOC);

if (!$file) {
    header('Content-Type: application/json');
    echo json_encode(['error' => '文件不存在或没有访问权限']);
    exit;
}

header('Content-Type: application/json');
echo json_encode(['content' => $file['file_content']]);
?>