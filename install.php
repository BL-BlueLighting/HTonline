<?php

include './config.php';

if (file_exists("./install.lock")) {
    echo "Installation application locked by install.lock.".PHP_EOL."If you want to reinstall HTOnline, please delete this file and retry.";
    die();
}

$step = false;
if (isset($_GET ["installit"])) {
    $step = true;
    initDatabase();
    file_put_contents("./install.lock", "./install.lock");
}

?>
<html>
    <head>
        <title>HTOnline Installation - Step 1</title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { font-family: Arial, sans-serif; background: #f5f5f5; padding: 20px; }
            .container { max-width: 400px; margin: 100px auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
            h1 { text-align: center; margin-bottom: 30px; color: #333; }
            .form-group { margin-bottom: 20px; }
            label { display: block; margin-bottom: 5px; color: #555; }
            input[type="text"], input[type="password"] { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 16px; }
            button { width: 100%; padding: 12px; background: #007bff; color: white; border: none; border-radius: 4px; font-size: 16px; cursor: pointer; }
            button:hover { background: #0056b3; }
            .btn-register { background: #28a745; margin-top: 10px; }
            .btn-register:hover { background: #1e7e34; }
            .error { background: #f8d7da; color: #721c24; padding: 10px; border-radius: 4px; margin-bottom: 20px; }
            .success { background: #d4edda; color: #155724; padding: 10px; border-radius: 4px; margin-bottom: 20px; }
            .tabs { display: flex; margin-bottom: 20px; }
            .tab { flex: 1; text-align: center; padding: 10px; background: #eee; cursor: pointer; }
            .tab.active { background: #007bff; color: white; }
            .tab-content { display: none; }
            .tab-content.active { display: block; }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>HTOnline</h1>
            
            <?php if (isset($error)): ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if (isset($success)): ?>
                <div class="success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <?php if (!$step) : ?>
            <p>Installation Step 1</p>
            <button onclick="javascript:window.location.href='./install.php?installit'">Continue installation</button>
            <?php else:?>
            <p>installation Step 2</p>
            <H4>Installing Database...</H4>
            <h4>Installed database successfully.</h4>
            <br/>
            <h4>Generating administrator account...</h4>
            <h4>Generation successfully. Password: admin123, please edit your password after your login action.</h4>
            <?php endif; ?>
        </div>
    </body>
</html>