<?php
/**
 * Serviço de E-mail
 * 
 * Gerencia envio de e-mails via SMTP
 */

namespace App\Services;

class EmailService
{
    private array $config;

    public function __construct()
    {
        $this->config = require dirname(__DIR__, 2) . '/config/mail.php';
    }

    /**
     * Envia um e-mail
     */
    public function send(string|array $to, string $subject, string $body, bool $isHtml = true): bool
    {
        $recipients = is_array($to) ? $to : [$to];
        
        // Headers
        $headers = [];
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = $isHtml 
            ? 'Content-type: text/html; charset=UTF-8' 
            : 'Content-type: text/plain; charset=UTF-8';
        $headers[] = 'From: ' . $this->config['from']['name'] . ' <' . $this->config['from']['address'] . '>';
        
        $success = true;
        
        foreach ($recipients as $recipient) {
            if (!mail($recipient, $subject, $body, implode("\r\n", $headers))) {
                LogService::error('email', 'Falha ao enviar e-mail', [
                    'to' => $recipient,
                    'subject' => $subject
                ]);
                $success = false;
            }
        }
        
        if ($success) {
            LogService::info('email', 'E-mail enviado com sucesso', [
                'to' => $recipients,
                'subject' => $subject
            ]);
        }
        
        return $success;
    }

    /**
 * Envia e-mail usando SMTP nativo
 */
public function sendSmtp(string|array $to, string $subject, string $body, bool $isHtml = true): bool
{
    try {
        $smtp = new \App\Libraries\Smtp(
            $this->config['host'],
            $this->config['port'],
            $this->config['username'],
            $this->config['password'],
            $this->config['encryption']
        );
        
        $smtp->connect();
        
        $recipients = is_array($to) ? $to : [$to];
        
        $smtp->send(
            $this->config['from']['address'],
            $recipients,
            $subject,
            $body,
            ['From' => $this->config['from']['name']]
        );
        
        $smtp->disconnect();
        
        LogService::info('email', 'E-mail SMTP enviado com sucesso', [
            'to' => $recipients,
            'subject' => $subject
        ]);
        
        return true;
        
    } catch (\Exception $e) {
        LogService::error('email', 'Falha ao enviar e-mail SMTP', [
            'error' => $e->getMessage(),
            'to' => $to,
            'subject' => $subject
        ]);
        
        return false;
    }
}

    /**
     * Envia relatório de backup
     */
    public function sendBackupReport(array $data, array $recipients): bool
    {
        $subject = 'Relatório de Backup - ' . date('d/m/Y');
        $body = $this->buildBackupReportHtml($data);
        
        return $this->sendSmtp($recipients, $subject, $body);
    }

    /**
     * Constrói HTML do relatório de backup
     */
    private function buildBackupReportHtml(array $data): string
    {
        $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #0d6efd; color: white; padding: 20px; text-align: center; }
        .content { padding: 20px; background: #f8f9fa; }
        .stats { display: flex; justify-content: space-around; margin: 20px 0; }
        .stat { text-align: center; padding: 15px; background: white; border-radius: 8px; }
        .stat-value { font-size: 24px; font-weight: bold; }
        .success { color: #198754; }
        .failure { color: #dc3545; }
        .warning { color: #ffc107; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #dee2e6; }
        th { background: #e9ecef; }
        .footer { text-align: center; padding: 20px; color: #6c757d; font-size: 12px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Relatório de Backup</h1>
            <p>' . date('d/m/Y H:i') . '</p>
        </div>
        <div class="content">
            <div class="stats">
                <div class="stat">
                    <div class="stat-value success">' . ($data['stats']['sucesso'] ?? 0) . '</div>
                    <div>Sucesso</div>
                </div>
                <div class="stat">
                    <div class="stat-value failure">' . ($data['stats']['falha'] ?? 0) . '</div>
                    <div>Falhas</div>
                </div>
                <div class="stat">
                    <div class="stat-value warning">' . ($data['stats']['alerta'] ?? 0) . '</div>
                    <div>Alertas</div>
                </div>
            </div>';
        
        if (!empty($data['execucoes'])) {
            $html .= '<h3>Últimas Execuções</h3>
            <table>
                <thead>
                    <tr>
                        <th>Cliente</th>
                        <th>Servidor</th>
                        <th>Rotina</th>
                        <th>Status</th>
                        <th>Data</th>
                    </tr>
                </thead>
                <tbody>';
            
            foreach ($data['execucoes'] as $exec) {
                $statusClass = match($exec['status']) {
                    'sucesso' => 'success',
                    'falha' => 'failure',
                    default => 'warning'
                };
                
                $html .= '<tr>
                    <td>' . htmlspecialchars($exec['cliente_nome'] ?? '') . '</td>
                    <td>' . htmlspecialchars($exec['servidor_nome'] ?? '') . '</td>
                    <td>' . htmlspecialchars($exec['rotina_nome'] ?? '') . '</td>
                    <td class="' . $statusClass . '">' . ucfirst($exec['status']) . '</td>
                    <td>' . date('d/m/Y H:i', strtotime($exec['data_inicio'])) . '</td>
                </tr>';
            }
            
            $html .= '</tbody></table>';
        }
        
        $html .= '</div>
        <div class="footer">
            <p>Backup WebManager - World Informática</p>
            <p>Este é um e-mail automático, não responda.</p>
        </div>
    </div>
</body>
</html>';
        
        return $html;
    }
}
