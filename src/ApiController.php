<?php

namespace Uspdev\NfeWs;

class ApiController
{
    private Config $config;

    public function __construct()
    {
        $this->config = new Config();
    }

    /**
     * Rota GET /: Exibe a pagina inicial / documentacao
     */
    public function index(): void
    {
        readfile(__DIR__ . '/../templates/index.tpl');
    }

    /**
     * Rota *: Middleware de autenticacao e validacoes basicas
     */
    public function authenticate()
    {
        Auth::handleCors();

        try {
            $apiKey = Auth::extractApiKey();
            $auth = new Auth($this->config);
            $auth->authenticate($apiKey);
        } catch (AuthException $e) {
            http_response_code($e->getCode());
            echo $e->getMessage();
            exit();
        }

        if (!is_dir($this->config->local)) {
            http_response_code(500);
            echo 'A pasta de dados não está configurada!';
            exit();
        }

        return true;
    }

    /**
     * Rota GET /status: Consulta a validade do certificado digital em formato JSON
     */
    public function status(): void
    {
        try {
            $certificado = $this->config->getCertificate();
            $validTo = $certificado->getValidTo();
            $expirado = $certificado->isExpired();

            $this->jsonResponse([
                'status'   => $expirado ? 'expirado' : 'ok',
                'cnpj'     => $certificado->getCnpj(),
                'empresa'  => $certificado->getCompanyName(),
                'validade' => $validTo->format('d/m/Y'),
                'valid_to' => $validTo->format(\DateTimeInterface::ATOM),
                'expirado' => $expirado,
                'mensagem' => $expirado ? 'Certificado VENCIDO! Não é possivel mais usá-lo!' : 'Certificado VÁLIDO!',
            ]);
        } catch (\Throwable $e) {
            $this->jsonResponse([
                'status'   => 'erro',
                'mensagem' => 'Erro ao carregar certificado: ' . $e->getMessage(),
                'erro'     => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Rota GET /@tipo:[a-z]+/@file: Download/exibicao de arquivos
     *
     * @param string $tipo
     * @param string $file
     */
    public function download(string $tipo, string $file): void
    {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, OPTIONS');

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
     * Rota POST /xml: Processamento de XML ou consulta por chave
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

    /**
     * Emite resposta em formato JSON com headers adequados
     */
    private function jsonResponse(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data);
        exit;
    }
}
