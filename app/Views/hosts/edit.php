<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= path('/clientes') ?>">Clientes</a></li>
                <li class="breadcrumb-item"><a href="<?= path('/clientes/' . $cliente['id']) ?>"><?= htmlspecialchars($cliente['nome']) ?></a></li>
                <li class="breadcrumb-item"><a href="<?= path('/clientes/' . $cliente['id'] . '/hosts') ?>">Hosts</a></li>
                <li class="breadcrumb-item active">Editar Host</li>
            </ol>
        </nav>
        <h4 class="mt-2 mb-0">Editar Host: <?= htmlspecialchars($host['nome']) ?></h4>
    </div>
    <a href="<?= path('/clientes/' . $cliente['id'] . '/hosts/' . $host['id']) ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Voltar
    </a>
</div>

<div class="card">
    <div class="card-body">
        <form method="POST" action="<?= path('/clientes/' . $cliente['id'] . '/hosts/' . $host['id']) ?>">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
            
            <?php include __DIR__ . '/_form.php'; ?>
        </form>
        
        <?php if ($host['updated_at']): ?>
        <div class="mt-3 text-muted small">
            <i class="bi bi-clock-history me-1"></i>
            Última atualização: <?= date('d/m/Y H:i', strtotime($host['updated_at'])) ?>
        </div>
        <?php endif; ?>
    </div>
</div>
