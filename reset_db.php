<?php
// reset_db.php - 数据库重置脚本
require_once 'auth.php';

// 只有管理员可以重置数据库
if (!isAdmin()) {
    die("只有管理员可以执行此操作");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm'])) {
    // 删除现有数据库文件
    if (file_exists(Config::$db_file)) {
        if (!unlink(Config::$db_file)) {
            die("无法删除数据库文件");
        }
    }
    
    // 删除项目目录
    if (file_exists(Config::$upload_dir) && is_dir(Config::$upload_dir)) {
        deleteDirectory(Config::$upload_dir);
    }
    
    // 重新创建项目目录
    if (!file_exists(Config::$upload_dir)) {
        mkdir(Config::$upload_dir, 0777, true);
    }
    
    // 重新初始化数据库
    require_once 'config.php';
    if (initDatabase()) {
        $success = "数据库重置成功！";
    } else {
        $error = "数据库重置失败！";
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
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>重置数据库 - HTOnline</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f5f5f5; padding: 20px; }
        .container { max-width: 600px; margin: 50px auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { text-align: center; margin-bottom: 30px; color: #333; }
        .warning { background: #fff3cd; color: #856404; padding: 15px; border-radius: 4px; margin-bottom: 20px; border: 1px solid #ffeaa7; }
        .btn { padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 4px; border: none; cursor: pointer; display: inline-block; }
        .btn:hover { background: #0056b3; }
        .btn-danger { background: #dc3545; }
        .btn-success { background: #28a745; }
        .success { background: #d4edda; color: #155724; padding: 10px; border-radius: 4px; margin-bottom: 20px; }
        .error { background: #f8d7da; color: #721c24; padding: 10px; border-radius: 4px; margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>重置数据库</h1>
        
        <?php if (isset($success)): ?>
            <div class="success"><?php echo htmlspecialchars($success); ?></div>
            <p>默认管理员账户已创建:</p>
            <ul>
                <li>用户名: admin</li>
                <li>密码: admin123</li>
            </ul>
            <div style="margin-top: 20px;">
                <a href="index.php" class="btn">前往登录页面</a>
                <a href="test.php" class="btn btn-success">运行系统测试</a>
            </div>
        <?php elseif (isset($error)): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <div style="margin-top: 20px;">
                <a href="test.php" class="btn">返回测试页面</a>
            </div>
        <?php else: ?>
            <div class="warning">
                <h3>⚠️ 警告</h3>
                <p>此操作将：</p>
                <ul>
                    <li>删除所有用户数据</li>
                    <li>删除所有项目数据</li>
                    <li>删除所有文件数据</li>
                    <li>删除所有上传的文件</li>
                    <li>重新初始化数据库</li>
                </ul>
                <p><strong>此操作不可撤销！</strong></p>
            </div>
            
            <form method="POST">
                <p>请输入 "<strong>RESET</strong>" 以确认重置：</p>
                <input type="text" name="confirm" placeholder="输入 RESET 确认" required style="width: 100%; padding: 10px; margin: 10px 0; border: 1px solid #ddd; border-radius: 4px;">
                <div style="margin-top: 20px;">
                    <button type="submit" class="btn btn-danger" onclick="return confirm('确定要重置数据库吗？这将删除所有数据！')">确认重置数据库</button>
                    <a href="test.php" class="btn">取消</a>
                </div>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>