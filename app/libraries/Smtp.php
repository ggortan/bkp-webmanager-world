<?php
/**
 * Classe SMTP Nativa
 * 
 * Implementação simplificada de SMTP para envio de e-mails sem PHPMailer
 */

namespace App\Libraries;

class Smtp
{
    private string $host;
    private int $port;
    private string $username;
    private string $password;
    private string $encryption;
    private $connection = null;
    private bool $connected = false;

    public function __construct(string $host, int $port, string $username, string $password, string $encryption = 'tls')
    {
        $this->host = $host;
        $this->port = $port;
        $this->username = $username;
        $this->password = $password;
        $this->encryption = $encryption;
    }

    /**
     * Conecta ao servidor SMTP
     */
    public function connect(): bool
    {
        try {
            if ($this->encryption === 'tls') {
                $context = stream_context_create([
                    'ssl' => [
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                    ]
                ]);
                $this->connection = stream_socket_client(
                    "tcp://{$this->host}:{$this->port}",
                    $errno,
                    $errstr,
                    10,
                    STREAM_CLIENT_CONNECT,
                    $context
                );
            } else {
                $this->connection = fsockopen($this->host, $this->port, $errno, $errstr, 10);
            }

            if (!$this->connection) {
                throw new \RuntimeException("Erro ao conectar ao SMTP: {$errstr}");
            }

            stream_set_blocking($this->connection, true);

            // Lê resposta inicial
            $this->read();

            // EHLO
            $this->command('EHLO ' . gethostname());

            // Inicia TLS se necessário
            if ($this->encryption === 'tls') {
                $this->command('STARTTLS');
                stream_socket_enable_crypto($this->connection, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                $this->command('EHLO ' . gethostname());
            }

            // Autenticação
            $this->command('AUTH LOGIN');
            $this->command(base64_encode($this->username));
            $this->command(base64_encode($this->password));

            $this->connected = true;
            return true;

        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * Envia um e-mail
     */
    public function send(string $from, array|string $to, string $subject, string $body, array $headers = []): bool
    {
        if (!$this->connected) {
            $this->connect();
        }

        $recipients = is_array($to) ? $to : [$to];

        try {
            // De
            $this->command("MAIL FROM:<{$from}>");

            // Para
            foreach ($recipients as $recipient) {
                $this->command("RCPT TO:<{$recipient}>");
            }

            // Dados
            $this->command('DATA');

            // Constrói a mensagem
            $message = "Subject: {$subject}\r\n";
            $message .= "From: {$from}\r\n";
            $message .= "To: " . implode(', ', $recipients) . "\r\n";
            $message .= "MIME-Version: 1.0\r\n";
            $message .= "Content-Type: text/html; charset=UTF-8\r\n";

            foreach ($headers as $name => $value) {
                $message .= "{$name}: {$value}\r\n";
            }

            $message .= "\r\n{$body}\r\n.\r\n";

            fwrite($this->connection, $message);
            $this->read();

            return true;

        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * Desconecta do servidor SMTP
     */
    public function disconnect(): void
    {
        if ($this->connected && $this->connection) {
            $this->command('QUIT');
            fclose($this->connection);
            $this->connected = false;
        }
    }

    /**
     * Envia um comando SMTP
     */
    private function command(string $command): string
    {
        fwrite($this->connection, $command . "\r\n");
        return $this->read();
    }

    /**
     * Lê resposta do servidor SMTP
     */
    private function read(): string
    {
        $response = '';

        while ($line = fgets($this->connection, 512)) {
            $response .= $line;

            if (substr($line, 3, 1) === ' ') {
                break;
            }
        }

        $code = (int)substr($response, 0, 3);

        if ($code >= 400) {
            throw new \RuntimeException("Erro SMTP ({$code}): {$response}");
        }

        return $response;
    }

    /**
     * Destrutor
     */
    public function __destruct()
    {
        $this->disconnect();
    }
}
