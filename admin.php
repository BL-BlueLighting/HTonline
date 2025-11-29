<?php
require_once 'auth.php';

if (!isAdmin()) {
    header('Location: dashboard.php');
    exit;
}

require_once 'config.php';
$db = new SQLite3(Config::$db_file);

// 处理用户管理操作
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_user') {
        $user_id = $_POST['user_id'] ?? '';
        $isPro = isset($_POST['isPro']) ? 1 : 0;
        $isAdmin = isset($_POST['isAdmin']) ? 1 : 0;
        
        // 防止管理员取消自己的管理员权限
        if ($user_id == $_SESSION['user_id'] && $isAdmin == 0) {
            $error = "不能取消自己的管理员权限";
        } else {
            $stmt = $db->prepare("UPDATE users SET isPro = ?, isAdmin = ? WHERE id = ?");
            $stmt->bindValue(1, $isPro, SQLITE3_INTEGER);
            $stmt->bindValue(2, $isAdmin, SQLITE3_INTEGER);
            $stmt->bindValue(3, $user_id, SQLITE3_INTEGER);
            
            if ($stmt->execute()) {
                $success = "用户信息更新成功";
            } else {
                $error = "更新用户信息失败";
            }
        }
    } elseif ($action === 'delete_user') {
        $user_id = $_POST['user_id'] ?? '';
        
        // 防止删除自己
        if ($user_id == $_SESSION['user_id']) {
            $error = "不能删除自己的账户";
        } else {
            // 获取用户信息
            $stmt = $db->prepare("SELECT username FROM users WHERE id = ?");
            $stmt->bindValue(1, $user_id, SQLITE3_INTEGER);
            $result = $stmt->execute();
            $user = $result->fetchArray(SQLITE3_ASSOC);
            
            if (!$user) {
                $error = "用户不存在";
            } else {
                $username = $user['username'];
                
                // 开始事务
                $db->exec('BEGIN TRANSACTION');
                
                try {
                    // 获取用户的所有项目
                    $stmt = $db->prepare("SELECT id, project_path FROM projects WHERE project_owner = ?");
                    $stmt->bindValue(1, $username, SQLITE3_TEXT);
                    $result = $stmt->execute();
                    
                    $projects_to_delete = [];
                    while ($project = $result->fetchArray(SQLITE3_ASSOC)) {
                        $projects_to_delete[] = $project;
                    }
                    
                    // 删除项目文件记录
                    foreach ($projects_to_delete as $project) {
                        $stmt = $db->prepare("DELETE FROM project_files WHERE project_id = ?");
                        $stmt->bindValue(1, $project['id'], SQLITE3_INTEGER);
                        $stmt->execute();
                    }
                    
                    // 删除项目记录
                    $stmt = $db->prepare("DELETE FROM projects WHERE project_owner = ?");
                    $stmt->bindValue(1, $username, SQLITE3_TEXT);
                    $stmt->execute();
                    
                    // 删除用户
                    $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
                    $stmt->bindValue(1, $user_id, SQLITE3_INTEGER);
                    
                    if ($stmt->execute()) {
                        // 删除实际的项目文件目录
                        foreach ($projects_to_delete as $project) {
                            $project_dir = Config::$upload_dir . $project['project_path'];
                            if (file_exists($project_dir) && is_dir($project_dir)) {
                                // 删除目录及其所有内容
                                deleteDirectory($project_dir);
                            }
                        }
                        
                        $db->exec('COMMIT');
                        $success = "用户 '{$username}' 及其所有项目和文件已成功删除";
                    } else {
                        $db->exec('ROLLBACK');
                        $error = "删除用户失败";
                    }
                } catch (Exception $e) {
                    $db->exec('ROLLBACK');
                    $error = "删除用户时发生错误: " . $e->getMessage();
                }
            }
        }
    } elseif ($action === 'delete_project') {
        $project_id = $_POST['project_id'] ?? '';
        
        // 获取项目信息
        $stmt = $db->prepare("SELECT project_name, project_path, project_owner FROM projects WHERE id = ?");
        $stmt->bindValue(1, $project_id, SQLITE3_INTEGER);
        $result = $stmt->execute();
        $project = $result->fetchArray(SQLITE3_ASSOC);
        
        if (!$project) {
            $error = "项目不存在";
        } else {
            // 开始事务
            $db->exec('BEGIN TRANSACTION');
            
            try {
                // 删除项目文件记录
                $stmt = $db->prepare("DELETE FROM project_files WHERE project_id = ?");
                $stmt->bindValue(1, $project_id, SQLITE3_INTEGER);
                $stmt->execute();
                
                // 删除项目记录
                $stmt = $db->prepare("DELETE FROM projects WHERE id = ?");
                $stmt->bindValue(1, $project_id, SQLITE3_INTEGER);
                
                if ($stmt->execute()) {
                    // 删除实际的项目文件目录
                    $project_dir = Config::$upload_dir . $project['project_path'];
                    if (file_exists($project_dir) && is_dir($project_dir)) {
                        deleteDirectory($project_dir);
                    }
                    
                    $db->exec('COMMIT');
                    $success = "项目 '{$project['project_name']}' 及其所有文件已成功删除";
                } else {
                    $db->exec('ROLLBACK');
                    $error = "删除项目失败";
                }
            } catch (Exception $e) {
                $db->exec('ROLLBACK');
                $error = "删除项目时发生错误: " . $e->getMessage();
            }
        }
    } elseif ($action === 'change_user_password') {
        $user_id = $_POST['user_id'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        
        if (empty($new_password)) {
            $error = "新密码不能为空";
        } elseif (strlen($new_password) < 6) {
            $error = "新密码至少需要6个字符";
        } else {
            $hashed_password = md5($new_password);
            $stmt = $db->prepare("UPDATE users SET keypass = ? WHERE id = ?");
            $stmt->bindValue(1, $hashed_password, SQLITE3_TEXT);
            $stmt->bindValue(2, $user_id, SQLITE3_INTEGER);
            
            if ($stmt->execute()) {
                $success = "用户密码修改成功";
            } else {
                $error = "修改用户密码失败";
            }
        }
    }
}

// 递归删除目录的函数
function deleteDirectory($dir) {
    if (!file_exists($dir)) {
        return true;
    }
    
    if (!is_dir($dir)) {
        return unlink($dir);
    }
    
    foreach (scandir($dir) as $item) {
        if ($item == '.' || $item == '..') {
            continue;
        }
        
        if (!deleteDirectory($dir . DIRECTORY_SEPARATOR . $item)) {
            return false;
        }
    }
    
    return rmdir($dir);
}

// 获取当前标签页
$tab = $_GET['tab'] ?? 'projects';

// 项目管理分页
$page = max(1, $_GET['page'] ?? 1);
$limit = 30;
$offset = ($page - 1) * $limit;

// 获取所有项目总数
$result = $db->query("SELECT COUNT(*) as total FROM projects");
$total_projects = $result->fetchArray(SQLITE3_ASSOC)['total'];
$total_project_pages = ceil($total_projects / $limit);

// 获取所有项目（带分页）
$stmt = $db->prepare("SELECT p.*, u.username, u.isPro FROM projects p 
                     JOIN users u ON p.project_owner = u.username 
                     ORDER BY p.created_at DESC 
                     LIMIT ? OFFSET ?");
$stmt->bindValue(1, $limit, SQLITE3_INTEGER);
$stmt->bindValue(2, $offset, SQLITE3_INTEGER);
$result = $stmt->execute();

$projects = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $projects[] = $row;
}

// 用户管理分页
$user_page = max(1, $_GET['user_page'] ?? 1);
$user_limit = 30;
$user_offset = ($user_page - 1) * $user_limit;

// 获取所有用户总数
$users_result = $db->query("SELECT COUNT(*) as total_users FROM users");
$total_users = $users_result->fetchArray(SQLITE3_ASSOC)['total_users'];
$total_user_pages = ceil($total_users / $user_limit);

// 获取所有用户（带分页）
$stmt = $db->prepare("SELECT * FROM users ORDER BY created_at DESC LIMIT ? OFFSET ?");
$stmt->bindValue(1, $user_limit, SQLITE3_INTEGER);
$stmt->bindValue(2, $user_offset, SQLITE3_INTEGER);
$users_result = $stmt->execute();

$users = [];
while ($row = $users_result->fetchArray(SQLITE3_ASSOC)) {
    $users[] = $row;
}

// 获取用户统计
$pro_users_result = $db->query("SELECT COUNT(*) as pro_users FROM users WHERE isPro = 1");
$pro_users = $pro_users_result->fetchArray(SQLITE3_ASSOC)['pro_users'];

$admin_users_result = $db->query("SELECT COUNT(*) as admin_users FROM users WHERE isAdmin = 1");
$admin_users = $admin_users_result->fetchArray(SQLITE3_ASSOC)['admin_users'];
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HTOnline - 管理面板</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f5f5f5; }
        .header { background: white; padding: 20px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); text-align: center; }
        .tabs { display: flex; margin-bottom: 20px; background: white; border-radius: 8px; overflow: hidden; }
        .tab { padding: 15px 20px; cursor: pointer; border: none; background: #f8f9fa; flex: 1; text-align: center; }
        .tab.active { background: #007bff; color: white; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        .projects-table, .users-table { background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f8f9fa; font-weight: bold; }
        .btn { padding: 8px 12px; background: #007bff; color: white; text-decoration: none; border-radius: 4px; border: none; cursor: pointer; font-size: 14px; }
        .btn:hover { background: #0056b3; }
        .btn-success { background: #28a745; }
        .btn-danger { background: #dc3545; }
        .btn-warning { background: #ffc107; color: #212529; }
        .btn-info { background: #17a2b8; }
        .pagination { margin-top: 20px; text-align: center; }
        .pagination a { display: inline-block; padding: 8px 12px; margin: 0 2px; background: white; border: 1px solid #ddd; border-radius: 4px; text-decoration: none; color: #333; }
        .pagination a.active { background: #007bff; color: white; border-color: #007bff; }
        .error { background: #f8d7da; color: #721c24; padding: 10px; border-radius: 4px; margin-bottom: 20px; }
        .success { background: #d4edda; color: #155724; padding: 10px; border-radius: 4px; margin-bottom: 20px; }
        .user-form { background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: inline-block; margin-right: 10px; }
        .checkbox-group { display: flex; gap: 20px; }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; }
        .modal-content { background: white; margin: 100px auto; padding: 20px; width: 500px; border-radius: 8px; }
        .danger-zone { border: 2px solid #dc3545; padding: 15px; border-radius: 8px; margin-top: 20px; }
        .danger-zone h4 { color: #dc3545; margin-bottom: 10px; }
    </style>
</head>
<body>
    <div class="header">
        <div class="container">
            <h1>HTOnline - 管理面板</h1>
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

        <div class="stats-grid">
            <div class="stat-card">
                <h3>总用户数</h3>
                <p style="font-size: 24px; font-weight: bold; color: #007bff;"><?php echo $total_users; ?></p>
            </div>
            <div class="stat-card">
                <h3>PRO用户</h3>
                <p style="font-size: 24px; font-weight: bold; color: #28a745;"><?php echo $pro_users; ?></p>
            </div>
            <div class="stat-card">
                <h3>管理员</h3>
                <p style="font-size: 24px; font-weight: bold; color: #dc3545;"><?php echo $admin_users; ?></p>
            </div>
            <div class="stat-card">
                <h3>总项目数</h3>
                <p style="font-size: 24px; font-weight: bold; color: #ffc107;"><?php echo $total_projects; ?></p>
            </div>
        </div>

        <div class="tabs">
            <button class="tab <?php echo $tab === 'projects' ? 'active' : ''; ?>" onclick="switchTab('projects')">项目管理</button>
            <button class="tab <?php echo $tab === 'users' ? 'active' : ''; ?>" onclick="switchTab('users')">用户管理</button>
        </div>

        <!-- 项目管理标签页 -->
        <div id="projects-tab" class="tab-content <?php echo $tab === 'projects' ? 'active' : ''; ?>">
            <h2>所有项目 (第 <?php echo $page; ?> 页，共 <?php echo $total_project_pages; ?> 页)</h2>
            
            <div class="projects-table">
                <table>
                    <thead>
                        <tr>
                            <th>项目名称</th>
                            <th>项目路径</th>
                            <th>所有者</th>
                            <th>用户类型</th>
                            <th>创建时间</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($projects as $project): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($project['project_name']); ?></td>
                                <td><?php echo htmlspecialchars($project['project_path']); ?></td>
                                <td><?php echo htmlspecialchars($project['username']); ?></td>
                                <td>
                                    <?php if ($project['isPro']): ?>
                                        <span style="color: gold;">PRO</span>
                                    <?php else: ?>
                                        <span style="color: #6c757d;">免费</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $project['created_at']; ?></td>
                                <td>
                                    <a href="project.php?id=<?php echo $project['id']; ?>" class="btn">查看</a>
                                    <a href="/routers/<?php echo $project['project_path']; ?>/" target="_blank" class="btn btn-success">访问</a>
                                    <button onclick="confirmDeleteProject(<?php echo $project['id']; ?>, '<?php echo htmlspecialchars($project['project_name']); ?>', '<?php echo htmlspecialchars($project['username']); ?>')" class="btn btn-danger">删除</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="pagination">
                <?php for ($i = 1; $i <= $total_project_pages; $i++): ?>
                    <a href="?tab=projects&page=<?php echo $i; ?>" class="<?php echo $i == $page ? 'active' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>
            </div>
        </div>

        <!-- 用户管理标签页 -->
        <div id="users-tab" class="tab-content <?php echo $tab === 'users' ? 'active' : ''; ?>">
            <h2>用户管理 (第 <?php echo $user_page; ?> 页，共 <?php echo $total_user_pages; ?> 页)</h2>
            
            <div class="users-table">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>用户名</th>
                            <th>PRO用户</th>
                            <th>管理员</th>
                            <th>注册时间</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?php echo $user['id']; ?></td>
                                <td>
                                    <?php echo htmlspecialchars($user['username']); ?>
                                    <?php if ($user['id'] == $_SESSION['user_id']): ?>
                                        <span style="color: #007bff;">(当前用户)</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($user['isPro']): ?>
                                        <span style="color: gold;">是</span>
                                    <?php else: ?>
                                        否
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($user['isAdmin']): ?>
                                        <span style="color: red;">是</span>
                                    <?php else: ?>
                                        否
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $user['created_at']; ?></td>
                                <td>
                                    <button onclick="editUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>', <?php echo $user['isPro']; ?>, <?php echo $user['isAdmin']; ?>)" class="btn btn-warning">编辑</button>
                                    <button onclick="changeUserPassword(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')" class="btn btn-info">改密</button>
                                    <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                        <button onclick="confirmDeleteUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')" class="btn btn-danger">删除</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="pagination">
                <?php for ($i = 1; $i <= $total_user_pages; $i++): ?>
                    <a href="?tab=users&user_page=<?php echo $i; ?>" class="<?php echo $i == $user_page ? 'active' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>
            </div>
        </div>
    </div>

    <!-- 编辑用户模态框 -->
    <div id="editUserModal" class="modal">
        <div class="modal-content">
            <h3>编辑用户: <span id="edit-username"></span></h3>
            <form method="POST" id="edit-user-form">
                <input type="hidden" name="action" value="update_user">
                <input type="hidden" name="user_id" id="edit-user-id">
                
                <div class="form-group">
                    <div class="checkbox-group">
                        <label>
                            <input type="checkbox" name="isPro" id="edit-isPro" value="1"> PRO用户
                        </label>
                        <label>
                            <input type="checkbox" name="isAdmin" id="edit-isAdmin" value="1"> 管理员
                        </label>
                    </div>
                </div>
                
                <div style="margin-top: 20px;">
                    <button type="submit" class="btn">保存</button>
                    <button type="button" class="btn btn-danger" onclick="hideEditUserModal()">取消</button>
                </div>
            </form>
        </div>
    </div>

    <!-- 修改用户密码模态框 -->
    <div id="changeUserPasswordModal" class="modal">
        <div class="modal-content">
            <h3>修改用户密码: <span id="change-password-username"></span></h3>
            <form method="POST" id="change-user-password-form">
                <input type="hidden" name="action" value="change_user_password">
                <input type="hidden" name="user_id" id="change-password-user-id">
                
                <div class="form-group">
                    <label>新密码:</label>
                    <input type="password" name="new_password" id="admin-new-password" required>
                    <small>至少6个字符</small>
                </div>
                
                <div class="form-group">
                    <label>确认新密码:</label>
                    <input type="password" id="admin-confirm-password" required>
                </div>
                
                <div style="margin-top: 20px;">
                    <button type="submit" class="btn">修改密码</button>
                    <button type="button" class="btn btn-danger" onclick="hideChangeUserPasswordModal()">取消</button>
                </div>
            </form>
        </div>
    </div>

    <!-- 删除用户确认模态框 -->
    <div id="deleteUserModal" class="modal">
        <div class="modal-content">
            <h3>确认删除用户</h3>
            <p>确定要删除用户 "<span id="delete-username"></span>" 吗？</p>
            
            <div class="danger-zone">
                <h4>⚠️ 危险操作警告</h4>
                <p>此操作将永久删除：</p>
                <ul>
                    <li>用户账户</li>
                    <li>用户的所有项目</li>
                    <li>项目中的所有文件</li>
                    <li>项目目录和文件</li>
                </ul>
                <p><strong>此操作无法撤销！</strong></p>
            </div>
            
            <form method="POST" id="delete-user-form">
                <input type="hidden" name="action" value="delete_user">
                <input type="hidden" name="user_id" id="delete-user-id">
                
                <div style="margin-top: 20px;">
                    <button type="submit" class="btn btn-danger">确认删除</button>
                    <button type="button" class="btn" onclick="hideDeleteUserModal()">取消</button>
                </div>
            </form>
        </div>
    </div>

    <!-- 删除项目确认模态框 -->
    <div id="deleteProjectModal" class="modal">
        <div class="modal-content">
            <h3>确认删除项目</h3>
            <p>确定要删除项目 "<span id="delete-project-name"></span>" 吗？</p>
            <p>所有者: <span id="delete-project-owner"></span></p>
            
            <div class="danger-zone">
                <h4>⚠️ 危险操作警告</h4>
                <p>此操作将永久删除：</p>
                <ul>
                    <li>项目记录</li>
                    <li>项目中的所有文件</li>
                    <li>项目目录和文件</li>
                </ul>
                <p><strong>此操作无法撤销！</strong></p>
            </div>
            
            <form method="POST" id="delete-project-form">
                <input type="hidden" name="action" value="delete_project">
                <input type="hidden" name="project_id" id="delete-project-id">
                
                <div style="margin-top: 20px;">
                    <button type="submit" class="btn btn-danger">确认删除</button>
                    <button type="button" class="btn" onclick="hideDeleteProjectModal()">取消</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function switchTab(tabName) {
            // 更新URL参数
            const url = new URL(window.location);
            url.searchParams.set('tab', tabName);
            window.history.replaceState({}, '', url);
            
            // 切换标签页
            document.querySelectorAll('.tab').forEach(tab => tab.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
            
            event.target.classList.add('active');
            document.getElementById(tabName + '-tab').classList.add('active');
        }
        
        function editUser(userId, username, isPro, isAdmin) {
            document.getElementById('edit-user-id').value = userId;
            document.getElementById('edit-username').textContent = username;
            document.getElementById('edit-isPro').checked = Boolean(isPro);
            document.getElementById('edit-isAdmin').checked = Boolean(isAdmin);
            document.getElementById('editUserModal').style.display = 'block';
        }
        
        function hideEditUserModal() {
            document.getElementById('editUserModal').style.display = 'none';
        }
        
        function changeUserPassword(userId, username) {
            document.getElementById('change-password-user-id').value = userId;
            document.getElementById('change-password-username').textContent = username;
            document.getElementById('changeUserPasswordModal').style.display = 'block';
        }
        
        function hideChangeUserPasswordModal() {
            document.getElementById('changeUserPasswordModal').style.display = 'none';
        }
        
        function confirmDeleteUser(userId, username) {
            document.getElementById('delete-user-id').value = userId;
            document.getElementById('delete-username').textContent = username;
            document.getElementById('deleteUserModal').style.display = 'block';
        }
        
        function hideDeleteUserModal() {
            document.getElementById('deleteUserModal').style.display = 'none';
        }
        
        function confirmDeleteProject(projectId, projectName, projectOwner) {
            document.getElementById('delete-project-id').value = projectId;
            document.getElementById('delete-project-name').textContent = projectName;
            document.getElementById('delete-project-owner').textContent = projectOwner;
            document.getElementById('deleteProjectModal').style.display = 'block';
        }
        
        function hideDeleteProjectModal() {
            document.getElementById('deleteProjectModal').style.display = 'none';
        }
        
        // 点击模态框外部关闭
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                document.getElementById('editUserModal').style.display = 'none';
                document.getElementById('changeUserPasswordModal').style.display = 'none';
                document.getElementById('deleteUserModal').style.display = 'none';
                document.getElementById('deleteProjectModal').style.display = 'none';
            }
        }
        
        // 页面加载时根据URL参数激活正确的标签页
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const tab = urlParams.get('tab');
            if (tab) {
                document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
                
                const activeTab = document.querySelector(`.tab[onclick="switchTab('${tab}')"]`);
                if (activeTab) {
                    activeTab.classList.add('active');
                    document.getElementById(tab + '-tab').classList.add('active');
                }
            }
            
            // 密码确认验证
            document.getElementById('change-user-password-form').addEventListener('submit', function(e) {
                var newPassword = document.getElementById('admin-new-password').value;
                var confirmPassword = document.getElementById('admin-confirm-password').value;
                
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
        });
    </script>
</body>
</html>