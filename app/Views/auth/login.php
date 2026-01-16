<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Backup WebManager</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            min-height: 100vh;
            background: linear-gradient(135deg, #0d6efd 0%, #0099ff 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }
        
        .login-card {
            background: #fff;
            border-radius: 1rem;
            box-shadow: 0 1rem 3rem rgba(0,0,0,0.2);
            max-width: 420px;
            width: 100%;
            overflow: hidden;
        }
        
        .login-header {
            background: #212529;
            color: #fff;
            padding: 2rem;
            text-align: center;
        }
        
        .login-header i {
            font-size: 3rem;
            margin-bottom: 1rem;
            display: block;
        }
        
        .login-body {
            padding: 2rem;
        }
        
        .btn-microsoft {
            background: #2f2f2f;
            color: #fff;
            border: none;
            padding: 0.75rem 1.5rem;
            font-size: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
            width: 100%;
            transition: background 0.2s;
        }
        
        .btn-microsoft:hover {
            background: #1a1a1a;
            color: #fff;
        }
        
        .btn-microsoft img {
            width: 20px;
            height: 20px;
        }
        
        .login-footer {
            text-align: center;
            padding: 1.5rem;
            background: #f8f9fa;
            border-top: 1px solid #dee2e6;
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="login-header">
            <i class="bi bi-cloud-check"></i>
            <h3 class="mb-1">Backup WebManager</h3>
            <small class="opacity-75">World Informática</small>
        </div>
        
        <div class="login-body">
            <?php if (!empty($_SESSION['flash'])): ?>
                <?php foreach ($_SESSION['flash'] as $type => $message): ?>
                <div class="alert alert-<?= $type === 'error' ? 'danger' : $type ?> small mb-4">
                    <?= htmlspecialchars($message) ?>
                </div>
                <?php endforeach; ?>
                <?php unset($_SESSION['flash']); ?>
            <?php endif; ?>
            
            <p class="text-center text-muted mb-4">
                Faça login com sua conta Microsoft corporativa para acessar o sistema.
            </p>
            
            <a href="<?= path('/auth/redirect') ?>" class="btn btn-microsoft">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 23 23">
                    <path fill="#f35325" d="M1 1h10v10H1z"/>
                    <path fill="#81bc06" d="M12 1h10v10H12z"/>
                    <path fill="#05a6f0" d="M1 12h10v10H1z"/>
                    <path fill="#ffba08" d="M12 12h10v10H12z"/>
                </svg>
                Entrar com Microsoft
            </a>
        </div>
        
        <div class="login-footer">
            <small class="text-muted">
                Sistema de monitoramento centralizado de backups<br>
                &copy; <?= date('Y') ?> World Informática
            </small>
        </div>
    </div>
</body>
</html>
