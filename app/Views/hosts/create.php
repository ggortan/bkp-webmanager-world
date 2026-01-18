<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= path('/clientes') ?>">Clientes</a></li>
                <li class="breadcrumb-item"><a href="<?= path('/clientes/' . $cliente['id']) ?>"><?= htmlspecialchars($cliente['nome']) ?></a></li>
                <li class="breadcrumb-item"><a href="<?= path('/clientes/' . $cliente['id'] . '/hosts') ?>">Hosts</a></li>
                <li class="breadcrumb-item active">Novo Host</li>
            </ol>
        </nav>
        <h4 class="mt-2 mb-0">Novo Host</h4>
    </div>
    <a href="<?= path('/clientes/' . $cliente['id'] . '/hosts') ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Voltar
    </a>
</div>

<div class="card">
    <div class="card-body">
        <form method="POST" action="<?= path('/clientes/' . $cliente['id'] . '/hosts') ?>">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
            
            <?php include __DIR__ . '/_form.php'; ?>
        </form>
    </div>
</div>
