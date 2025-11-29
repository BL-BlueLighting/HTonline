<?php
require_once 'config.php';

// 从 URL 路径解析参数
$request_uri = $_SERVER['REQUEST_URI'];
$base_path = '/routers/';

// 检查是否是路由请求
if (strpos($request_uri, $base_path) === 0) {
    $path = substr($request_uri, strlen($base_path));
    $parts = explode('/', $path);
    
    $project_path = $parts[0] ?? '';
    
    // 如果路径以 / 结尾或者是项目目录，显示文件列表
    if (empty($parts[1]) || $parts[1] === '') {
        $file_name = '';
    } else {
        $file_name = $parts[1];
        // 移除查询参数
        $file_name = strtok($file_name, '?');
    }
} else {
    // 从 GET 参数获取（兼容性）
    $project_path = $_GET['project_path'] ?? '';
    $file_name = $_GET['file_name'] ?? '';
}

if (empty($project_path)) {
    http_response_code(404);
    die("项目路径不能为空");
}

$db = new SQLite3(Config::$db_file);

// 检查项目是否存在
$stmt = $db->prepare("SELECT * FROM projects WHERE project_path = ?");
$stmt->bindValue(1, $project_path, SQLITE3_TEXT);
$result = $stmt->execute();
$project = $result->fetchArray(SQLITE3_ASSOC);

if (!$project) {
    http_response_code(404);
    die("项目不存在");
}

// 如果指定了文件名，显示该文件
if (!empty($file_name)) {
    $stmt = $db->prepare("SELECT * FROM project_files WHERE project_id = ? AND file_name = ?");
    $stmt->bindValue(1, $project['id'], SQLITE3_INTEGER);
    $stmt->bindValue(2, $file_name, SQLITE3_TEXT);
    $result = $stmt->execute();
    $file = $result->fetchArray(SQLITE3_ASSOC);
    
    if ($file) {
        header('Content-Type: text/html; charset=utf-8');
        echo $file['file_content'];
    } else {
        http_response_code(404);
        die("文件不存在: " . htmlspecialchars($file_name));
    }
    exit;
}

// 如果没有指定文件名，显示项目文件列表
$stmt = $db->prepare("SELECT * FROM project_files WHERE project_id = ? ORDER BY file_name");
$stmt->bindValue(1, $project['id'], SQLITE3_INTEGER);
$result = $stmt->execute();

$files = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $files[] = $row;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>项目: <?php echo htmlspecialchars($project['project_name']); ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f5f5f5; padding: 40px; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { margin-bottom: 20px; color: #333; }
        .file-list { list-style: none; }
        .file-item { padding: 15px; border-bottom: 1px solid #eee; }
        .file-item:last-child { border-bottom: none; }
        .file-link { color: #007bff; text-decoration: none; font-size: 18px; }
        .file-link:hover { text-decoration: underline; }
        .empty { text-align: center; color: #6c757d; padding: 40px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>项目: <?php echo htmlspecialchars($project['project_name']); ?></h1>
        <p>所有者: <?php echo htmlspecialchars($project['project_owner']); ?></p>
        
        <h2 style="margin: 30px 0 15px 0;">文件列表</h2>
        
        <?php if (!empty($files)): ?>
            <ul class="file-list">
                <?php foreach ($files as $file): ?>
                    <li class="file-item">
                        <a href="/routers/<?php echo $project_path; ?>/<?php echo $file['file_name']; ?>" class="file-link">
                            <?php echo htmlspecialchars($file['file_name']); ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <div class="empty">该项目还没有文件</div>
        <?php endif; ?>
    </div>
</body>
</html>