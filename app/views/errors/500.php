<?php
/**
 * Página de erro 500
 */
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Erro 500 - Erro Interno do Servidor</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        
        .container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            padding: 40px;
            max-width: 600px;
            text-align: center;
        }
        
        h1 {
            color: #e74c3c;
            font-size: 48px;
            margin: 0 0 10px 0;
        }
        
        .status {
            color: #7f8c8d;
            font-size: 24px;
            margin-bottom: 20px;
        }
        
        p {
            color: #34495e;
            line-height: 1.6;
            margin: 15px 0;
        }
        
        .back-link {
            display: inline-block;
            margin-top: 20px;
            padding: 10px 20px;
            background: #3498db;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            transition: background 0.3s;
        }
        
        .back-link:hover {
            background: #2980b9;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>500</h1>
        <div class="status">Erro Interno do Servidor</div>
        <p>Desculpe! Ocorreu um erro ao processar sua requisição.</p>
        <p>Por favor, tente novamente mais tarde ou entre em contato com o administrador.</p>
        <a href="/" class="back-link">Voltar ao Início</a>
    </div>
</body>
</html>
