<?php
require_once 'auth.php';

if (!isLoggedIn()) {
    header('Location: index.php');
    exit;
}

require_once 'config.php';

$project_id = $_GET['id'] ?? null;
if (!$project_id) {
    die("项目ID不能为空");
}

$db = new SQLite3(Config::$db_file);
$username = getCurrentUser();

// 验证项目所有权（管理员可以访问所有项目）
if (!isAdmin()) {
    $stmt = $db->prepare("SELECT * FROM projects WHERE id = ? AND project_owner = ?");
    $stmt->bindValue(1, $project_id, SQLITE3_INTEGER);
    $stmt->bindValue(2, $username, SQLITE3_TEXT);
} else {
    $stmt = $db->prepare("SELECT * FROM projects WHERE id = ?");
    $stmt->bindValue(1, $project_id, SQLITE3_INTEGER);
}

$result = $stmt->execute();
$project = $result->fetchArray(SQLITE3_ASSOC);

if (!$project) {
    die("项目不存在或没有访问权限");
}

// 处理文件操作
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create_file') {
        $file_name = $_POST['file_name'] ?? '';
        
        if (empty($file_name)) {
            $error = "文件名不能为空";
        } else {
            // 检查文件数量限制
            $max_files = isProUser() ? Config::$max_files_pro : Config::$max_files_free;
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM project_files WHERE project_id = ?");
            $stmt->bindValue(1, $project_id, SQLITE3_INTEGER);
            $result = $stmt->execute();
            $file_count = $result->fetchArray(SQLITE3_ASSOC)['count'];
            
            if ($file_count >= $max_files) {
                $error = "已达到文件数量限制";
            } else {
                // 创建文件
                $stmt = $db->prepare("INSERT INTO project_files (project_id, file_name, file_content) VALUES (?, ?, '')");
                $stmt->bindValue(1, $project_id, SQLITE3_INTEGER);
                $stmt->bindValue(2, $file_name, SQLITE3_TEXT);
                
                if ($stmt->execute()) {
                    // 创建实际文件
                    $file_path = Config::$upload_dir . $project['project_path'] . '/' . $file_name;
                    file_put_contents($file_path, '');
                    
                    header('Location: project.php?id=' . $project_id);
                    exit;
                } else {
                    $error = "创建文件失败";
                }
            }
        }
    } elseif ($action === 'save_file') {
        $file_id = $_POST['file_id'] ?? '';
        $file_content = $_POST['file_content'] ?? '';
        
        $stmt = $db->prepare("UPDATE project_files SET file_content = ? WHERE id = ? AND project_id = ?");
        $stmt->bindValue(1, $file_content, SQLITE3_TEXT);
        $stmt->bindValue(2, $file_id, SQLITE3_INTEGER);
        $stmt->bindValue(3, $project_id, SQLITE3_INTEGER);
        
        if ($stmt->execute()) {
            // 更新实际文件
            $stmt = $db->prepare("SELECT file_name FROM project_files WHERE id = ?");
            $stmt->bindValue(1, $file_id, SQLITE3_INTEGER);
            $result = $stmt->execute();
            $file = $result->fetchArray(SQLITE3_ASSOC);
            
            if ($file) {
                $file_path = Config::$upload_dir . $project['project_path'] . '/' . $file['file_name'];
                file_put_contents($file_path, $file_content);
            }
            
            $success = "文件保存成功";
        } else {
            $error = "保存文件失败";
        }
    }
}

// 获取项目文件
$stmt = $db->prepare("SELECT * FROM project_files WHERE project_id = ? ORDER BY created_at DESC");
$stmt->bindValue(1, $project_id, SQLITE3_INTEGER);
$result = $stmt->execute();

$files = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $files[] = $row;
}

$max_files = isProUser() ? Config::$max_files_pro : Config::$max_files_free;
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理项目 - <?php echo htmlspecialchars($project['project_name']); ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f5f5f5; }
        .header { background: white; padding: 20px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        .btn { padding: 10px 15px; background: #007bff; color: white; text-decoration: none; border-radius: 4px; border: none; cursor: pointer; }
        .btn:hover { background: #0056b3; }
        .btn-success { background: #28a745; }
        .btn-danger { background: #dc3545; }
        .files-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .file-card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .editor-container { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        textarea { width: 100%; height: 400px; padding: 15px; border: 1px solid #ddd; border-radius: 4px; font-family: monospace; resize: vertical; }
        .error { background: #f8d7da; color: #721c24; padding: 10px; border-radius: 4px; margin-bottom: 20px; }
        .success { background: #d4edda; color: #155724; padding: 10px; border-radius: 4px; margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="header">
        <div class="container">
            <h1><?php echo htmlspecialchars($project['project_name']); ?></h1>
            <p>路径: <?php echo htmlspecialchars($project['project_path']); ?></p>
            <h4>请注意：本站不允许用户创建非法网站，不允许创建重定向网站（比如进到首页就跳转）管理员有权删除用户和用户的网站。</h4>
            <a href="dashboard.php" class="btn">返回控制台</a>
        </div>
    </div>

    <div class="container">
        <?php if (isset($error)): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if (isset($success)): ?>
            <div class="success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <div style="margin-bottom: 20px;">
            <h2>文件管理 (<?php echo count($files); ?>/<?php echo $max_files; ?>)</h2>
            <form method="POST" style="display: inline-block;">
                <input type="hidden" name="action" value="create_file">
                <input type="text" name="file_name" placeholder="文件名 (例如: index.html)" required>
                <button type="submit" class="btn" <?php echo count($files) >= $max_files ? 'disabled' : ''; ?>>创建文件</button>
            </form>
        </div>

        <div class="files-grid">
            <?php foreach ($files as $file): ?>
                <div class="file-card">
                    <h3><?php echo htmlspecialchars($file['file_name']); ?></h3>
                    <p>创建时间: <?php echo $file['created_at']; ?></p>
                    <div style="margin-top: 15px;">
                        <button onclick="editFile(<?php echo $file['id']; ?>, '<?php echo htmlspecialchars($file['file_name']); ?>')" class="btn">编辑</button>
                        <a href="/routers/<?php echo $project['project_path']; ?>/<?php echo $file['file_name']; ?>" target="_blank" class="btn btn-success">查看</a>
                    </div>
                </div>
            <?php endforeach; ?>
            
            <?php if (empty($files)): ?>
                <p>还没有文件，创建一个吧！</p>
            <?php endif; ?>
        </div>

        <!-- 文件编辑器 -->
        <div id="editor" class="editor-container" style="display: none;">
            <h3>编辑文件: <span id="current-file-name"></span></h3>
            <form method="POST" id="file-form">
                <input type="hidden" name="action" value="save_file">
                <input type="hidden" name="file_id" id="file-id">
                <textarea name="file_content" id="file-content" placeholder="输入HTML内容..."></textarea>
                <div style="margin-top: 15px;">
                    <button type="submit" class="btn">保存</button>
                    <button type="button" class="btn btn-danger" onclick="hideEditor()">取消</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function editFile(fileId, fileName) {
            // 获取文件内容
            fetch('get_file_content.php?file_id=' + fileId)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('file-id').value = fileId;
                    document.getElementById('current-file-name').textContent = fileName;
                    document.getElementById('file-content').value = data.content;
                    document.getElementById('editor').style.display = 'block';
                    window.scrollTo(0, document.body.scrollHeight);
                })
                .catch(error => {
                    alert('获取文件内容失败');
                });
        }
        
        function hideEditor() {
            document.getElementById('editor').style.display = 'none';
        }
    </script>
</body>
</html>