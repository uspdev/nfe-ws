<?php

namespace Uspdev\NfeWs\Controllers\V1;

use Uspdev\NfeWs\App;
use Uspdev\NfeWs\Controllers\BaseApiController;
use Uspdev\NfeWs\Services\Nfe;

class ApiController extends BaseApiController
{
    public const VERSION = 'v1';

    /**
     * Rota GET /v1/status: Consulta a validade do certificado digital e versões da API
     */
    public function status(): void
    {
        $this->emitStatusResponse([
            'versao_em_uso'   => static::VERSION,
            'versao_corrente' => App::API_VERSION,
        ]);
    }

    /**
     * Rota GET /v1/@tipo:[a-z]+/@file: Download/exibicao de arquivos
     *
     * @param string $tipo
     * @param string $file
     */
    public function download(string $tipo, string $file): void
    {
        $types = [
            'danfe' => 'application/pdf',
            'prot'  => 'application/xml',
            'sefaz' => 'application/pdf',
            'xml'   => 'application/xml',
        ];

        if (!isset($types[$tipo])) {
            echo 'erro';
            exit;
        }

        $file = basename($file);
        $arq = $this->config->local . $file;
        if (is_file($arq)) {
            $disposition = in_array($tipo, ['danfe', 'sefaz']) ? 'inline' : 'attachment';
            header('Content-Type: ' . $types[$tipo]);
            header('Content-Disposition: ' . $disposition . '; filename="' . $file . '"');
            readfile($arq);
        } else {
            http_response_code(404);
            echo 'Arquivo nao encontrado!';
        }
        exit;
    }

    /**
     * Rota POST /v1/xml: Processamento de XML ou consulta por chave
     */
    public function processXml(): void
    {
        $xml = $_POST['xml'] ?? null;
        $chave = $_POST['chave'] ?? null;

        // Suporte adicional a payload JSON (Content-Type: application/json)
        if ($xml === null && $chave === null) {
            $raw = file_get_contents('php://input');
            if (!empty($raw)) {
                $jsonData = json_decode($raw, true);
                if (is_array($jsonData)) {
                    $xml = $jsonData['xml'] ?? null;
                    $chave = $jsonData['chave'] ?? null;
                }
            }
        }

        if (empty($xml) && empty($chave)) {
            $this->jsonResponse(['status' => 'Erro: sem dados', 'url' => []]);
        }

        if (!empty($xml)) {
            $this->jsonResponse(Nfe::processarEnvioXml($xml));
        }

        if (!empty($chave)) {
            $this->jsonResponse(Nfe::processarConsultaChave($chave));
        }
    }
}
