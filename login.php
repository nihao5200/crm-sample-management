<?php
/**
 * 登录页面
 */
require_once __DIR__ . '/config/functions.php';

// 已登录则跳转首页
if (isLoggedIn()) {
    redirect('index.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = '请输入账号和密码';
    } else {
        $user = fetchOne("SELECT * FROM admin WHERE username = ? AND status = 1", [$username]);
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['admin_id'] = $user['id'];
            $_SESSION['admin_username'] = $user['username'];
            $_SESSION['admin_role'] = $user['role'];
            
            execute("UPDATE admin SET last_login_time = NOW(), last_login_ip = ? WHERE id = ?", 
                [$_SERVER['REMOTE_ADDR'] ?? '', $user['id']]);
            
            setFlashMessage('success', '登录成功，欢迎回来！');
            redirect('index.php');
        } else {
            $error = '账号或密码错误';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>登录 - 客户订单样品管理系统</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #4e73df 0%, #224abe 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .login-container {
            width: 100%;
            max-width: 420px;
        }
        
        .login-card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            padding: 40px;
        }
        
        .login-logo {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .login-logo i {
            font-size: 48px;
            color: #4e73df;
        }
        
        .login-logo h3 {
            margin-top: 15px;
            font-weight: 600;
            color: #333;
            font-size: 22px;
        }
        
        .login-logo p {
            color: #888;
            font-size: 14px;
            margin-top: 5px;
        }
        
        .form-label {
            font-weight: 500;
            color: #555;
            font-size: 14px;
            margin-bottom: 8px;
        }
        
        .input-group {
            border: 1px solid #d1d3e2;
            border-radius: 10px;
            overflow: hidden;
            transition: all 0.2s;
        }
        
        .input-group:focus-within {
            border-color: #4e73df;
            box-shadow: 0 0 0 0.2rem rgba(78, 115, 223, 0.25);
        }
        
        .input-group-text {
            background: #f8f9fc;
            border: none;
            color: #4e73df;
            padding: 12px 15px;
        }
        
        .form-control {
            border: none;
            padding: 12px 15px;
            font-size: 14px;
        }
        
        .form-control:focus {
            box-shadow: none;
        }
        
        .btn-login {
            background: linear-gradient(135deg, #4e73df 0%, #224abe 100%);
            border: none;
            border-radius: 10px;
            padding: 14px;
            font-weight: 600;
            font-size: 16px;
            width: 100%;
            color: #fff;
            margin-top: 10px;
            transition: all 0.2s;
        }
        
        .btn-login:hover {
            opacity: 0.9;
            transform: translateY(-1px);
            color: #fff;
        }
        
        .alert {
            border-radius: 10px;
            border: none;
            padding: 12px 15px;
            margin-bottom: 20px;
        }
        
        .login-footer {
            text-align: center;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #e3e6f0;
        }
        
        .login-footer small {
            color: #888;
        }
        
        @media (max-width: 480px) {
            .login-card {
                padding: 30px 20px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-logo">
                <i class="bi bi-box-seam"></i>
                <h3>客户订单样品管理系统</h3>
                <p>CRM管理系统</p>
            </div>
            
            <?php if ($error): ?>
            <div class="alert alert-danger d-flex align-items-center">
                <i class="bi bi-exclamation-circle-fill me-2"></i>
                <?php echo $error; ?>
            </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="mb-3">
                    <label class="form-label">账号</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-person"></i></span>
                        <input type="text" name="username" class="form-control" placeholder="请输入账号" required autofocus>
                    </div>
                </div>
                <div class="mb-4">
                    <label class="form-label">密码</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-lock"></i></span>
                        <input type="password" name="password" class="form-control" placeholder="请输入密码" required>
                    </div>
                </div>
                <button type="submit" class="btn btn-login">
                    <i class="bi bi-box-arrow-in-right me-2"></i>登 录
                </button>
            </form>
            
            <div class="login-footer">
                <small>默认账号: admin / 密码: admin123</small>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
