<?php

namespace Uspdev\NfeWs;

class Logger
{
    private static ?string $logDir = null;

    /**
     * Define ou inicializa o diretorio de logs
     */
    public static function getLogDir(): string
    {
        if (self::$logDir !== null) {
            return self::$logDir;
        }

        $config = Config::getInstance();
        $baseDir = dirname($config->pwdFile);
        $logsDir = $baseDir . '/logs';

        if (!is_dir($logsDir)) {
            @mkdir($logsDir, 0775, true);
        }

        self::$logDir = $logsDir;
        return self::$logDir;
    }

    /**
     * Obtém o IP real do cliente considerando possíveis proxies reversos
     */
    public static function getClientIp(): string
    {
        $headers = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'HTTP_CLIENT_IP',
            'REMOTE_ADDR',
        ];

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                // Header como X-Forwarded-For pode conter múltiplos IPs separados por vírgula (ex: "client, proxy1, proxy2")
                $ips = explode(',', (string) $_SERVER[$header]);
                foreach ($ips as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP)) {
                        return $ip;
                    }
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? 'CLI';
    }

    /**
     * Registra uma linha de log com rotacao diaria (ex: access-2026-11-11.log)
     */
    public static function log(string $action, array $context = []): void
    {
        try {
            $dir = self::getLogDir();
            $date = date('Y-m-d');
            $file = $dir . "/access-{$date}.log";

            $timestamp = date('c');
            $ip = self::getClientIp();
            $method = $_SERVER['REQUEST_METHOD'] ?? 'CLI';
            $uri = $_SERVER['REQUEST_URI'] ?? '-';

            $name = $context['name'] ?? $context['user'] ?? null;
            if ($name === null && class_exists(\Flight::class) && \Flight::has('currentUser')) {
                $currentUser = \Flight::get('currentUser');
                $name = is_array($currentUser) ? ($currentUser['name'] ?? null) : null;
            }
            $name = $name ?? '-';
            $status = $context['status'] ?? '-';
            $message = $context['message'] ?? '';

            $entry = sprintf(
                "[%s] [%s] [%s] name=%s method=%s uri=%s ip=%s %s\n",
                $timestamp,
                $action,
                $status,
                $name,
                $method,
                $uri,
                $ip,
                $message ? "- {$message}" : ''
            );

            @file_put_contents($file, $entry, FILE_APPEND | LOCK_EX);
        } catch (\Throwable $e) {
            // Em caso de falha de permissão no log, não interrompe a requisição principal
        }
    }
}
