<?php
/**
 * Classe JWT (JSON Web Token) Nativa
 * 
 * Implementação simplificada de JWT sem dependências externas
 */

namespace App\Libraries;

class Jwt
{
    private static string $algorithm = 'HS256';
    private static string $secretKey = '';

    /**
     * Define a chave secreta
     */
    public static function setSecretKey(string $key): void
    {
        self::$secretKey = $key;
    }

    /**
     * Codifica um JWT
     */
    public static function encode(array $payload, ?string $key = null, string $algorithm = 'HS256'): string
    {
        if ($key === null) {
            $key = self::$secretKey;
        }

        if (empty($key)) {
            throw new \RuntimeException('Chave secreta não definida para JWT');
        }

        // Header
        $header = [
            'typ' => 'JWT',
            'alg' => $algorithm
        ];

        // Adiciona timestamp padrão
        if (!isset($payload['iat'])) {
            $payload['iat'] = time();
        }

        $headerEncoded = self::base64UrlEncode(json_encode($header));
        $payloadEncoded = self::base64UrlEncode(json_encode($payload));

        $signature = self::sign(
            $headerEncoded . '.' . $payloadEncoded,
            $key,
            $algorithm
        );

        return $headerEncoded . '.' . $payloadEncoded . '.' . $signature;
    }

    /**
     * Decodifica um JWT
     */
    public static function decode(string $token, ?string $key = null, bool $allowAlgorithms = false): ?object
    {
        if ($key === null) {
            $key = self::$secretKey;
        }

        if (empty($key)) {
            throw new \RuntimeException('Chave secreta não definida para JWT');
        }

        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            throw new \RuntimeException('Token inválido: formato incorreto');
        }

        [$headerEncoded, $payloadEncoded, $signatureProvided] = $parts;

        // Valida signature
        $signatureExpected = self::sign(
            $headerEncoded . '.' . $payloadEncoded,
            $key,
            self::$algorithm
        );

        if (!self::constantTimeEquals($signatureProvided, $signatureExpected)) {
            throw new \RuntimeException('Token inválido: assinatura incorreta');
        }

        // Decodifica payload
        $payload = json_decode(self::base64UrlDecode($payloadEncoded), false);

        if ($payload === null) {
            throw new \RuntimeException('Token inválido: payload corrompido');
        }

        // Valida expiração
        if (isset($payload->exp) && $payload->exp < time()) {
            throw new \RuntimeException('Token expirado');
        }

        return $payload;
    }

    /**
     * Assina a mensagem
     */
    private static function sign(string $message, string $key, string $algorithm): string
    {
        $hash = '';

        switch ($algorithm) {
            case 'HS256':
                $hash = hash_hmac('sha256', $message, $key, true);
                break;
            case 'HS384':
                $hash = hash_hmac('sha384', $message, $key, true);
                break;
            case 'HS512':
                $hash = hash_hmac('sha512', $message, $key, true);
                break;
            default:
                throw new \RuntimeException("Algoritmo não suportado: {$algorithm}");
        }

        return self::base64UrlEncode($hash);
    }

    /**
     * Comparação segura de strings (proteção contra timing attacks)
     */
    private static function constantTimeEquals(string $known, string $user): bool
    {
        if (function_exists('hash_equals')) {
            return hash_equals($known, $user);
        }

        $knownLen = strlen($known);
        $userLen = strlen($user);

        if ($knownLen !== $userLen) {
            return false;
        }

        $result = 0;
        for ($i = 0; $i < $knownLen; $i++) {
            $result |= ord($known[$i]) ^ ord($user[$i]);
        }

        return $result === 0;
    }

    /**
     * Encode Base64 URL-safe
     */
    private static function base64UrlEncode(string $data): string
    {
        return str_replace(
            ['+', '/', '='],
            ['-', '_', ''],
            base64_encode($data)
        );
    }

    /**
     * Decode Base64 URL-safe
     */
    private static function base64UrlDecode(string $data): string
    {
        $data .= str_repeat('=', (4 - (strlen($data) % 4)) % 4);

        return base64_decode(
            str_replace(
                ['-', '_'],
                ['+', '/'],
                $data
            )
        );
    }
}
