<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= \App\Helpers\Security::generateCsrfToken() ?>">
    <title><?= htmlspecialchars($title ?? 'Backup WebManager') ?> - Backup WebManager</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        :root {
            --sidebar-width: 260px;
            --primary-color: #0d6efd;
            --sidebar-bg: #212529;
        }
        
        body {
            min-height: 100vh;
            background-color: #f8f9fa;
        }
        
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: var(--sidebar-width);
            height: 100vh;
            background: var(--sidebar-bg);
            padding: 0;
            z-index: 1000;
            transition: transform 0.3s ease;
        }
        
        .sidebar-brand {
            padding: 1.25rem 1rem;
            background: rgba(0,0,0,0.2);
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .sidebar-brand h4 {
            color: #fff;
            margin: 0;
            font-size: 1.1rem;
        }
        
        .sidebar-brand small {
            color: rgba(255,255,255,0.5);
            font-size: 0.75rem;
        }
        
        .sidebar-nav {
            padding: 1rem 0;
        }
        
        .sidebar-nav .nav-link {
            color: rgba(255,255,255,0.7);
            padding: 0.75rem 1.25rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            transition: all 0.2s;
            border-left: 3px solid transparent;
        }
        
        .sidebar-nav .nav-link:hover,
        .sidebar-nav .nav-link.active {
            color: #fff;
            background: rgba(255,255,255,0.05);
            border-left-color: var(--primary-color);
        }
        
        .sidebar-nav .nav-link i {
            font-size: 1.1rem;
            width: 24px;
        }
        
        .sidebar-section {
            padding: 0.5rem 1.25rem;
            color: rgba(255,255,255,0.4);
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 1rem;
        }
        
        .main-content {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
        }
        
        .topbar {
            background: #fff;
            padding: 0.75rem 1.5rem;
            border-bottom: 1px solid #dee2e6;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .content {
            padding: 1.5rem;
        }
        
        .card {
            border: none;
            box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075);
        }
        
        .card-header {
            background: #fff;
            border-bottom: 1px solid #dee2e6;
            font-weight: 600;
        }
        
        .stat-card {
            border-radius: 0.5rem;
            padding: 1.25rem;
            color: #fff;
        }
        
        .stat-card.success { background: linear-gradient(135deg, #198754, #20c997); }
        .stat-card.danger { background: linear-gradient(135deg, #dc3545, #fd7e14); }
        .stat-card.warning { background: linear-gradient(135deg, #ffc107, #fd7e14); }
        .stat-card.info { background: linear-gradient(135deg, #0dcaf0, #0d6efd); }
        
        .stat-card .stat-icon {
            font-size: 2.5rem;
            opacity: 0.3;
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
        }
        
        .stat-card .stat-value {
            font-size: 2rem;
            font-weight: 700;
        }
        
        .stat-card .stat-label {
            opacity: 0.8;
            font-size: 0.875rem;
        }
        
        .status-badge {
            padding: 0.35rem 0.65rem;
            border-radius: 0.375rem;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .status-sucesso { background: #d1e7dd; color: #0f5132; }
        .status-falha { background: #f8d7da; color: #842029; }
        .status-alerta { background: #fff3cd; color: #664d03; }
        .status-executando { background: #cff4fc; color: #055160; }
        
        .table th {
            font-weight: 600;
            color: #495057;
            border-bottom-width: 1px;
        }
        
        .dropdown-menu {
            box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.15);
            border: none;
        }
        
        .user-dropdown .dropdown-toggle::after {
            display: none;
        }
        
        @media (max-width: 991.98px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .sidebar.show {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <nav class="sidebar" id="sidebar">
        <div class="sidebar-brand">
            <h4><i class="bi bi-cloud-check me-2"></i>Backup WebManager</h4>
            <small>World Informática</small>
        </div>
        
        <div class="sidebar-nav">
            <a href="<?= path('/dashboard') ?>" class="nav-link <?= ($title ?? '') === 'Dashboard' ? 'active' : '' ?>">
                <i class="bi bi-speedometer2"></i>
                Dashboard
            </a>
            
            <div class="sidebar-section">Gestão</div>
            
            <a href="<?= path('/clientes') ?>" class="nav-link <?= str_contains(($title ?? ''), 'Cliente') ? 'active' : '' ?>">
                <i class="bi bi-building"></i>
                Clientes
            </a>
            
            <a href="<?= path('/backups') ?>" class="nav-link <?= str_contains(($title ?? ''), 'Backup') ? 'active' : '' ?>">
                <i class="bi bi-hdd-stack"></i>
                Histórico de Backups
            </a>
            
            <a href="<?= path('/relatorios') ?>" class="nav-link <?= str_contains(($title ?? ''), 'Relatório') ? 'active' : '' ?>">
                <i class="bi bi-file-earmark-bar-graph"></i>
                Relatórios
            </a>
            
            <?php if (($user['role'] ?? '') === 'admin'): ?>
            <div class="sidebar-section">Administração</div>
            
            <a href="<?= path('/usuarios') ?>" class="nav-link <?= str_contains(($title ?? ''), 'Usuário') ? 'active' : '' ?>">
                <i class="bi bi-people"></i>
                Usuários
            </a>
            <?php endif; ?>
        </div>
    </nav>
    
    <!-- Main Content -->
    <div class="main-content">
        <!-- Topbar -->
        <header class="topbar">
            <div class="d-flex align-items-center">
                <button class="btn btn-link text-dark d-lg-none me-2" onclick="document.getElementById('sidebar').classList.toggle('show')">
                    <i class="bi bi-list fs-4"></i>
                </button>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="<?= path('/dashboard') ?>">Home</a></li>
                        <?php if (!empty($title) && $title !== 'Dashboard'): ?>
                        <li class="breadcrumb-item active"><?= htmlspecialchars($title) ?></li>
                        <?php endif; ?>
                    </ol>
                </nav>
            </div>
            
            <div class="d-flex align-items-center gap-3">
                <div class="dropdown user-dropdown">
                    <button class="btn btn-link text-dark dropdown-toggle d-flex align-items-center gap-2" type="button" data-bs-toggle="dropdown">
                        <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center" style="width:36px;height:36px;">
                            <span class="text-white fw-bold"><?= strtoupper(substr($user['nome'] ?? 'U', 0, 1)) ?></span>
                        </div>
                        <div class="d-none d-md-block text-start">
                            <div class="fw-semibold lh-1"><?= htmlspecialchars($user['nome'] ?? 'Usuário') ?></div>
                            <small class="text-muted"><?= ucfirst($user['role'] ?? 'viewer') ?></small>
                        </div>
                        <i class="bi bi-chevron-down"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><span class="dropdown-item-text small text-muted"><?= htmlspecialchars($user['email'] ?? '') ?></span></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="<?= path('/logout') ?>"><i class="bi bi-box-arrow-right me-2"></i>Sair</a></li>
                    </ul>
                </div>
            </div>
        </header>
        
        <!-- Page Content -->
        <main class="content">
            <?php if (!empty($flash)): ?>
                <?php foreach ($flash as $type => $message): ?>
                <div class="alert alert-<?= $type === 'error' ? 'danger' : $type ?> alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($message) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
            
            <?= $content ?>
        </main>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // CSRF token para requisições AJAX
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
        
        // Adiciona token CSRF em todas as requisições fetch
        const originalFetch = window.fetch;
        window.fetch = function(url, options = {}) {
            if (options.method && options.method.toUpperCase() !== 'GET') {
                options.headers = options.headers || {};
                if (!options.headers['X-CSRF-TOKEN']) {
                    options.headers['X-CSRF-TOKEN'] = csrfToken;
                }
            }
            return originalFetch(url, options);
        };
        
        // Toast notification
        function showToast(message, type = 'success') {
            const toast = document.createElement('div');
            toast.className = `toast align-items-center text-bg-${type === 'error' ? 'danger' : type} border-0 position-fixed bottom-0 end-0 m-3`;
            toast.setAttribute('role', 'alert');
            toast.innerHTML = `
                <div class="d-flex">
                    <div class="toast-body">${message}</div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            `;
            document.body.appendChild(toast);
            new bootstrap.Toast(toast).show();
            setTimeout(() => toast.remove(), 5000);
        }
    </script>
</body>
</html>
