<?php

use NFePHP\NFe\Complements;

class ApiController
{
    private $config;

    public function __construct()
    {
        $this->config = new Config();
    }

    /**
     * Rota GET /: Exibe a pagina inicial / documentacao
     */
    public function index()
    {
        readfile(__DIR__ . '/../templates/index.tpl');
    }

    /**
     * Rota *: Middleware de autenticacao e validacoes basicas
     */
    public function authenticate()
    {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, X-API-Key, Authorization');

        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS' || isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'])) {
            echo 'OK';
            exit;
        }

        $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? $_SERVER['X_API_KEY'] ?? $_GET['api_key'] ?? $_GET['key'] ?? '';
        if ($apiKey === '' && function_exists('getallheaders')) {
            $headers = getallheaders();
            foreach ($headers as $name => $value) {
                if (strcasecmp($name, 'X-API-Key') === 0) {
                    $apiKey = $value;
                    break;
                }
            }
        }

        if ($apiKey === '') {
            header('HTTP/1.0 401 Unauthorized');
            echo "Você deve informar uma API key válida para acessar este recurso\n";
            exit;
        }

        if (!is_file($this->config->pwdFile)) {
            echo 'Este webservice ainda não foi configurado!';
            exit();
        }

        $usrs = unserialize(file_get_contents($this->config->pwdFile));
        $valid = false;
        $apiKeyHash = hash('sha256', $apiKey);
        foreach ($usrs as $storedHash) {
            if (is_string($storedHash) && hash_equals($storedHash, $apiKeyHash)) {
                $valid = true;
                break;
            }
        }
        if (!$valid) {
            header('HTTP/1.0 401 Unauthorized');
            echo 'Credenciais inválidas';
            exit();
        }

        if (!is_dir($this->config->local)) {
            echo 'A pasta de dados não está configurada!';
            exit();
        }

        return true;
    }

    /**
     * Rota GET /status: Consulta a validade do certificado digital
     */
    public function status()
    {
        echo 'status<br>';
        try {
            $certificado = $this->config->getCertificate();
            echo $certificado->getCnpj() . ' - ';
            echo $certificado->getCompanyName() . '<br>';
            $validTo = $certificado->getValidTo();
            echo 'Validade: ' . $validTo->format('d/m/Y') . '<br>';

            if ($certificado->isExpired()) {
                echo 'Certificado VENCIDO! Não é possivel mais usá-lo!';
            } else {
                echo 'Certificado VÁLIDO!';
            }
        } catch (\Throwable $e) {
            echo 'Erro ao carregar certificado: ' . $e->getMessage();
        }
    }

    /**
     * Rota GET /@tipo:[a-z]+/@file: Download/exibicao de arquivos
     *
     * @param string $tipo
     * @param string $file
     */
    public function download($tipo, $file)
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
            header('HTTP/1.0 404 Not Found');
            echo 'Arquivo nao encontrado!';
        }
        exit;
    }

    /**
     * Rota POST /xml: Processamento de XML ou consulta por chave
     */
    public function processXml()
    {
        $res = [
            'status' => [],
            'url'    => [],
        ];

        if (!isset($_POST['xml']) && !isset($_POST['chave'])) {
            $res['status'] = 'Erro: sem dados';
            echo json_encode($res);
            exit();
        }

        if (!empty($_POST['xml'])) {
            $this->processaEnvioXml($_POST['xml'], $res);
            exit;
        }

        if (!empty($_POST['chave'])) {
            $this->processaConsultaChave($_POST['chave'], $res);
            exit;
        }
    }

    /**
     * Processa recebimento de XML completo
     */
    private function processaEnvioXml($nfe_xml, array $res)
    {
        global $cfg;

        $dom = new \DOMDocument('1.0', 'utf-8');
        $dom->preserveWhiteSpace = true;
        $dom->formatOutput = false;
        if (!@$dom->loadXML($nfe_xml, LIBXML_NOBLANKS | LIBXML_NOEMPTYTAG) || $dom->getElementsByTagName("infNFe")->length == 0) {
            $res['status'] = 'Erro ao processar XML: Não tem infNFe ou XML mal formado';
            $res['xml'] = [
                'status'    => 'stop',
                'estrutura' => 'Erro: Não tem infNFe ou XML mal formado',
            ];
            echo json_encode($res);
            return;
        }

        try {
            $nfe = new nfe_ws($nfe_xml);
        } catch (\Throwable $e) {
            $res['status'] = 'Erro ao processar XML: ' . $e->getMessage();
            $res['xml'] = [
                'status'    => 'stop',
                'estrutura' => 'Erro ao processar XML: ' . $e->getMessage(),
            ];
            echo json_encode($res);
            return;
        }

        $res['xml'] = $nfe->validaEstruturaXML($nfe_xml);

        if ($res['xml']['status'] == 'stop') {
            echo json_encode($res);
            return;
        }

        $res['xml'] = array_merge($res['xml'], $nfe->import($nfe_xml));
        if (!empty($res['xml']['url'])) {
            $res['url']['xml'] = $res['xml']['url'];
            unset($res['xml']['url']);
        }

        $res['chave'] = $nfe->retornaChave();

        $prot = null;
        try {
            $protocoloObj = new Protocolo();
            $prot = $protocoloObj->consulta($res['chave']);
        } catch (\Throwable $e) {
            $prot = [
                'status'  => 'erro',
                'xMotivo' => 'Falha ao consultar SEFAZ: ' . $e->getMessage(),
                'erro'    => $e->getMessage(),
            ];
        }

        if (!empty($prot)) {
            if (!empty($prot['url'])) {
                $res['url']['proto'] = $prot['url'];
                unset($prot['url']);
            }

            if (($prot['cStat'] ?? '') == '101') {
                try {
                    $nfe_xml_cancelada = Complements::cancelRegister($nfe_xml, $prot['raw'] ?? '');
                    $nfe->import($nfe_xml_cancelada);
                } catch (\Throwable $e) {
                    $prot['Registro do Cancelamento'] = 'Erro: ' . $e->getMessage();
                }
            }
            $res['prot'] = $prot;

            if (($prot['status'] ?? '') == 'ok') {
                $sefaz = [
                    'age'        => $prot['age'] ?? 0,
                    'cStat'      => $prot['cStat'] ?? '',
                    'xMotivo'    => $prot['xMotivo'] ?? '',
                    'dhConsulta' => $prot['dhConsulta'] ?? '',
                    'tpAmb'      => $prot['tpAmb'] ?? '',
                    'versao'     => 'uspdev/NFE-WS ' . VERSAO,
                ];
                try {
                    $relat = $nfe->geraRelatorioSefaz($prot);
                    $res['url']['sefaz'] = $relat['url'];
                } catch (\Throwable $e) {
                    $sefaz['erro_relatorio'] = $e->getMessage();
                }
            } else {
                $sefaz = [
                    'status' => 'Não disponível',
                    'info'   => $prot['xMotivo'] ?? 'Como há problemas no protocolo, não foi possível gerar relatório de consulta à Sefaz.',
                ];
            }
            $res['sefaz'] = $sefaz;
        }

        try {
            $danfe = $nfe->geraDanfe();
            $res['url']['danfe'] = $danfe['url'];
        } catch (\Throwable $e) {
            $res['danfe_erro'] = 'Erro ao gerar DANFE: ' . $e->getMessage();
        }

        try {
            $res['nfe'] = $nfe->detalhes();
        } catch (\Throwable $e) {
            $res['detalhes_erro'] = 'Erro ao obter detalhes: ' . $e->getMessage();
        }

        $res['status'] = 'ok';

        echo json_encode($res);
    }

    /**
     * Processa consulta com chave da NFe
     */
    private function processaConsultaChave($chaveInput, array $res)
    {
        if (!$chave = nfe_ws::validaChNFe($chaveInput)) {
            $res['status'] = 'Chave incorreta ' . strlen($chaveInput);
            echo json_encode($res);
            return;
        }

        $res['chave'] = $chave;

        try {
            $protocoloObj = new Protocolo();
            $prot = $protocoloObj->consulta($res['chave']);
            if (!empty($prot['url'])) {
                $res['url']['proto'] = $prot['url'];
                unset($prot['url']);
            }
            $res['prot'] = $prot;
        } catch (\Throwable $e) {
            $res['prot'] = [
                'status'  => 'erro',
                'xMotivo' => 'Falha ao consultar SEFAZ: ' . $e->getMessage(),
                'erro'    => $e->getMessage(),
            ];
        }

        $res['xml']['status'] = 'sem xml';
        $res['status'] = 'ok';
        echo json_encode($res);
    }
}
