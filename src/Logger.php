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
     * Registra uma linha de log com rotacao diaria (ex: access-2026-11-11.log)
     */
    public static function log(string $action, array $context = []): void
    {
        try {
            $dir = self::getLogDir();
            $date = date('Y-m-d');
            $file = $dir . "/access-{$date}.log";

            $timestamp = date('c');
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'CLI';
            $method = $_SERVER['REQUEST_METHOD'] ?? 'CLI';
            $uri = $_SERVER['REQUEST_URI'] ?? '-';

            $user = $context['user'] ?? '-';
            $status = $context['status'] ?? '-';
            $message = $context['message'] ?? '';

            $entry = sprintf(
                "[%s] [%s] [%s] user=%s method=%s uri=%s ip=%s %s\n",
                $timestamp,
                $action,
                $status,
                $user,
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
