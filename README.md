<div align="center">
  <h1>HTonline</h1>
  <h4><i>用于部署 fake vhost 的网站</i></h4>
</div>

# Deploy it
clone 仓库，然后添加以下规则到您的 nginx 配置文件：

    # 路由系统：/routers/[项目名称]/[文件].html
    location ~ ^/routers/([a-zA-Z0-9_]+)/([a-zA-Z0-9_\-]+\.html)$ {
        try_files $uri $uri/ /router.php?project_path=$1&file_name=$2;
    }

    # 路由系统：/routers/[项目名称]/（显示项目文件列表）
    location ~ ^/routers/([a-zA-Z0-9_]+)/$ {
        try_files $uri $uri/ /router.php?project_path=$1;
    }

    # 防止访问敏感文件
    location ~ /\. {
        deny all;
    }

    location ~ /(config\.php|htonline\.db)$ {
        deny all;
    }

添加完后，打开网站，进入 /install.php，进行安装。
安装完成后会创建默认管理员账号，名称 admin，密码 admin123。
请一定要在您注册完后修改密码。

# Examples
<a href="https://webol.latingtude-studios.icu">官方示例站</a>
