<?php

namespace Uspdev\NfeWs\Controllers;

use Uspdev\NfeWs\Config;
use Uspdev\NfeWs\Middleware\Auth;

/**
 * Controller base com utilitários compartilhados entre versões da API.
 */
abstract class BaseApiController
{
    protected Config $config;

    public function __construct()
    {
        $this->config = new Config();
    }

    /**
     * Monta e emite a resposta JSON de status da API e, se autenticado, do certificado digital.
     */
    protected function emitStatusResponse(array $versionInfo): void
    {
        if (!Auth::tryAuthenticate()) {
            $this->jsonResponse(array_merge([
                'status'      => 'ok',
                'autenticado' => false,
                'mensagem'    => 'Autentique-se com API Key para visualizar os dados do certificado.',
            ], $versionInfo));
        }

        try {
            $certificado = $this->config->getCertificate();
            $validTo = $certificado->getValidTo();
            $expirado = $certificado->isExpired();

            $this->jsonResponse(array_merge([
                'status'      => $expirado ? 'expirado' : 'ok',
                'autenticado' => true,
                'cnpj'        => $certificado->getCnpj(),
                'empresa'     => $certificado->getCompanyName(),
                'validade'    => $validTo->format('d/m/Y'),
                'valid_to'    => $validTo->format(\DateTimeInterface::ATOM),
                'expirado'    => $expirado,
                'mensagem'    => $expirado ? 'Certificado VENCIDO! Não é possivel mais usá-lo!' : 'Certificado VÁLIDO!',
            ], $versionInfo));
        } catch (\Throwable $e) {
            $this->jsonResponse(array_merge([
                'status'      => 'erro',
                'autenticado' => true,
                'mensagem'    => 'Erro ao carregar certificado: ' . $e->getMessage(),
                'erro'        => $e->getMessage(),
            ], $versionInfo), 500);
        }
    }

    /**
     * Emite resposta em formato JSON com headers adequados.
     */
    protected function jsonResponse(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data);
        exit;
    }
}
