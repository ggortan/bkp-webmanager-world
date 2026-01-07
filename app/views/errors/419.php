<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>419 - Sessão expirada</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f8f9fa;
        }
        .error-container {
            text-align: center;
            padding: 2rem;
        }
        .error-code {
            font-size: 8rem;
            font-weight: 700;
            color: #dee2e6;
            line-height: 1;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-code">419</div>
        <h2 class="mb-3">Sessão expirada</h2>
        <p class="text-muted mb-4">Sua sessão expirou. Por favor, recarregue a página e tente novamente.</p>
        <a href="javascript:location.reload()" class="btn btn-primary">
            <i class="bi bi-arrow-clockwise me-1"></i>Recarregar página
        </a>
    </div>
</body>
</html>
