<?php
require_once 'auth.php';

if (!isLoggedIn()) {
    header('Location: index.php');
    exit;
}

require_once 'config.php';
$db = new SQLite3(Config::$db_file);

// 处理密码修改
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $password_error = "所有字段都必须填写";
    } elseif ($new_password !== $confirm_password) {
        $password_error = "新密码和确认密码不匹配";
    } elseif (strlen($new_password) < 6) {
        $password_error = "新密码至少需要6个字符";
    } else {
        $result = changePassword(getCurrentUserId(), $current_password, $new_password);
        if ($result === true) {
            $password_success = "密码修改成功";
        } else {
            $password_error = $result;
        }
    }
}

// 获取用户项目
$username = getCurrentUser();
$stmt = $db->prepare("SELECT * FROM projects WHERE project_owner = ? ORDER BY created_at DESC");
$stmt->bindValue(1, $username, SQLITE3_TEXT);
$result = $stmt->execute();

$projects = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $projects[] = $row;
}

// 获取项目文件数量
$projectFileCounts = [];
foreach ($projects as $project) {
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM project_files WHERE project_id = ?");
    $stmt->bindValue(1, $project['id'], SQLITE3_INTEGER);
    $result = $stmt->execute();
    $count = $result->fetchArray(SQLITE3_ASSOC)['count'];
    $projectFileCounts[$project['id']] = $count;
}

$max_projects = isProUser() ? Config::$max_projects_pro : Config::$max_projects_free;
$max_files = isProUser() ? Config::$max_files_pro : Config::$max_files_free;
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HTOnline - 控制台</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f5f5f5; }
        .header { background: white; padding: 20px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        .user-info { float: right; }
        .stats { background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
        .projects-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; }
        .project-card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .btn { padding: 10px 15px; background: #007bff; color: white; text-decoration: none; border-radius: 4px; border: none; cursor: pointer; }
        .btn:hover { background: #0056b3; }
        .btn-success { background: #28a745; }
        .btn-danger { background: #dc3545; }
        .btn-warning { background: #ffc107; color: #212529; }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; }
        .modal-content { background: white; margin: 100px auto; padding: 20px; width: 400px; border-radius: 8px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-group input { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
        .error { background: #f8d7da; color: #721c24; padding: 10px; border-radius: 4px; margin-bottom: 15px; }
        .success { background: #d4edda; color: #155724; padding: 10px; border-radius: 4px; margin-bottom: 15px; }
    </style>
</head>
<body>
    <div class="header">
        <div class="container">
            <h1>HTOnline 控制台</h1>
            <div class="user-info">
                欢迎, <?php echo htmlspecialchars($username); ?>
                <?php if (isProUser()): ?><span style="color: gold;">★ PRO</span><?php endif; ?>
                <?php if (isAdmin()): ?><span style="color: red;">⚙️ 管理员</span><?php endif; ?>
                <button onclick="showChangePasswordModal()" class="btn btn-warning">修改密码</button>
                <a href="logout.php" class="btn btn-danger">退出</a>
                <?php if (isAdmin()): ?>
                    <a href="admin.php" class="btn">管理面板</a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="stats">
            <h3>项目统计</h3>
            <p>项目: <?php echo count($projects); ?>/<?php echo $max_projects; ?></p>
            <p>状态: <?php echo isProUser() ? 'PRO用户' : '免费用户'; ?></p>
            <button onclick="showCreateProjectModal()" class="btn" <?php echo count($projects) >= $max_projects ? 'disabled' : ''; ?>>创建新项目</button>
        </div>

        <h2>我的项目</h2>
        <div class="projects-grid">
            <?php foreach ($projects as $project): ?>
                <div class="project-card">
                    <h3><?php echo htmlspecialchars($project['project_name']); ?></h3>
                    <p>路径: <?php echo htmlspecialchars($project['project_path']); ?></p>
                    <p>文件: <?php echo $projectFileCounts[$project['id']]; ?>/<?php echo $max_files; ?></p>
                    <p>创建时间: <?php echo $project['created_at']; ?></p>
                    <div style="margin-top: 15px;">
                        <a href="project.php?id=<?php echo $project['id']; ?>" class="btn">管理文件</a>
                        <a href="/routers/<?php echo $project['project_path']; ?>/" target="_blank" class="btn btn-success">访问</a>
                    </div>
                </div>
            <?php endforeach; ?>
            
            <?php if (empty($projects)): ?>
                <p>还没有项目，点击上方按钮创建一个吧！</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- 创建项目模态框 -->
    <div id="createProjectModal" class="modal">
        <div class="modal-content">
            <h3>创建新项目</h3>
            <form method="POST" action="create_project.php">
                <div class="form-group">
                    <label>项目名称:</label>
                    <input type="text" name="project_name" required>
                </div>
                <div class="form-group">
                    <label>项目路径:</label>
                    <input type="text" name="project_path" required>
                    <small>只能包含字母、数字、下划线</small>
                </div>
                <button type="submit" class="btn">创建</button>
                <button type="button" class="btn btn-danger" onclick="hideCreateProjectModal()">取消</button>
            </form>
        </div>
    </div>

    <!-- 修改密码模态框 -->
    <div id="changePasswordModal" class="modal">
        <div class="modal-content">
            <h3>修改密码</h3>
            
            <?php if (isset($password_error)): ?>
                <div class="error"><?php echo htmlspecialchars($password_error); ?></div>
            <?php endif; ?>
            
            <?php if (isset($password_success)): ?>
                <div class="success"><?php echo htmlspecialchars($password_success); ?></div>
            <?php endif; ?>
            
            <form method="POST" id="change-password-form">
                <input type="hidden" name="action" value="change_password">
                
                <div class="form-group">
                    <label>当前密码:</label>
                    <input type="password" name="current_password" required>
                </div>
                
                <div class="form-group">
                    <label>新密码:</label>
                    <input type="password" name="new_password" id="new-password" required>
                    <small>至少6个字符</small>
                </div>
                
                <div class="form-group">
                    <label>确认新密码:</label>
                    <input type="password" name="confirm_password" id="confirm-password" required>
                </div>
                
                <div style="margin-top: 20px;">
                    <button type="submit" class="btn">修改密码</button>
                    <button type="button" class="btn btn-danger" onclick="hideChangePasswordModal()">取消</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function showCreateProjectModal() {
            document.getElementById('createProjectModal').style.display = 'block';
        }
        
        function hideCreateProjectModal() {
            document.getElementById('createProjectModal').style.display = 'none';
        }
        
        function showChangePasswordModal() {
            // 重置表单
            document.getElementById('change-password-form').reset();
            document.getElementById('changePasswordModal').style.display = 'block';
        }
        
        function hideChangePasswordModal() {
            document.getElementById('changePasswordModal').style.display = 'none';
        }
        
        // 点击模态框外部关闭
        window.onclick = function(event) {
            var modal = document.getElementById('createProjectModal');
            if (event.target == modal) {
                modal.style.display = 'none';
            }
            
            var passwordModal = document.getElementById('changePasswordModal');
            if (event.target == passwordModal) {
                passwordModal.style.display = 'none';
            }
        }
        
        // 密码确认验证
        document.getElementById('change-password-form').addEventListener('submit', function(e) {
            var newPassword = document.getElementById('new-password').value;
            var confirmPassword = document.getElementById('confirm-password').value;
            
            if (newPassword.length < 6) {
                e.preventDefault();
                alert('新密码至少需要6个字符');
                return;
            }
            
            if (newPassword !== confirmPassword) {
                e.preventDefault();
                alert('新密码和确认密码不匹配');
                return;
            }
        });
    </script>
</body>
</html>