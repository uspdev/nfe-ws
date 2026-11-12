<?php

namespace Uspdev\NfeWs\Services;

use DOMDocument;
use Exception;
use NFePHP\Common\Signer;
use NFePHP\NFe\Complements;
use NFePHP\DA\NFe\Danfe;
use Uspdev\NfeWs\Config;
use Uspdev\NfeWs\Tools;

#[\AllowDynamicProperties]
class Nfe
{
    public Config $c;
    public string $xml = '';
    public ?DOMDocument $dom = null;
    public $infNFe;
    public $ide;
    public $emit;
    public $dest;
    public $total;
    public $retirada;
    public $entrega;
    public $versao;
    public string $chNFe = '';

    public function __construct(string $xml = '')
    {
        $this->c = Config::getInstance();
        if (!empty($xml)) {
            $this->import($xml);
        }
    }

    /**
     * Importa um XML de NF-e, carrega o DOM e mapeia nós principais
     */
    public function import(string $xml): array
    {
        $this->xml = $xml;
        $res = [];

        if (empty($this->xml)) {
            return $res;
        }

        $this->dom = new DOMDocument('1.0', 'UTF-8');
        $this->dom->preserveWhiteSpace = false;
        $this->dom->formatOutput = false;
        $this->dom->loadXML($this->xml);

        $this->infNFe = $this->dom->getElementsByTagName('infNFe')->item(0);
        if (!$this->infNFe) {
            return $res;
        }

        $this->chNFe = str_replace('NFe', '', $this->infNFe->getAttribute('Id'));

        $this->ide = $this->dom->getElementsByTagName('ide')->item(0);
        $this->emit = $this->dom->getElementsByTagName('emit')->item(0);
        $this->dest = $this->dom->getElementsByTagName('dest')->item(0);
        $this->total = $this->dom->getElementsByTagName('total')->item(0);
        $this->retirada = $this->dom->getElementsByTagName('retirada')->item(0);
        $this->entrega = $this->dom->getElementsByTagName('entrega')->item(0);

        // Versão da NF-e
        $this->versao = $this->infNFe->getAttribute('versao');

        // Salva o XML em disco se ainda não existir
        $arqXml = $this->c->local . $this->chNFe . '-nfe.xml';
        if (!is_file($arqXml)) {
            file_put_contents($arqXml, $this->xml);
        }

        $res['url'] = $this->c->baseUrl . 'xml/' . $this->chNFe . '-nfe.xml';
        $res['versao'] = $this->versao;
        $res['status'] = 'ok';

        return $res;
    }

    /**
     * Valida a integridade do XML e sua assinatura digital
     */
    public function validaEstruturaXML(string $xml): array
    {
        libxml_use_internal_errors(false);
        $dom = new DOMDocument('1.0', 'utf-8');
        $dom->preserveWhiteSpace = true;
        $dom->formatOutput = false;

        $res = [
            'status' => true,
        ];

        try {
            $dom->loadXML($xml, LIBXML_NOBLANKS | LIBXML_NOEMPTYTAG);
            $res['estrutura'] = 'Estrutura do XML está OK';
        } catch (Exception $e) {
            $res['status'] = 'stop';
            $res['estrutura'] = 'Erro: XML mal formado: ' . $e->getMessage();
            return $res;
        }

        if ($dom->getElementsByTagName('nfeProc')->length === 0) {
            $res['nfeproc'] = 'Erro: O XML não está protocolado portanto sem valor fiscal!';
        }

        if ($dom->getElementsByTagName('infNFe')->length === 0) {
            $res['estrutura'] = 'Erro: Não tem infNFe';
            $res['status'] = 'stop';
            return $res;
        }

        try {
            $assinatura = Signer::existsSignature($xml);
        } catch (Exception $e) {
            $res['assinatura'] = 'Erro: Sem assinatura - ' . $e->getMessage();
            $res['status'] = 'stop';
            return $res;
        }

        if ($assinatura) {
            $res['assinatura'] = 'Assinatura existe';
            try {
                Signer::signatureCheck($xml);
                $res['assinatura'] = 'Assinatura OK';
            } catch (Exception $e) {
                $res['assinatura'] = 'Erro: Problema na assinatura: ' . $e->getMessage();
                $res['status'] = 'stop';
                return $res;
            }

            try {
                Signer::digestCheck($xml);
                $res['digest'] = 'Digest OK';
            } catch (Exception $e) {
                $res['digest'] = 'Erro: ' . $e->getMessage();
                $res['status'] = 'stop';
                return $res;
            }
        } else {
            $res['assinatura'] = 'Erro: Sem assinatura';
            $res['status'] = 'stop';
            return $res;
        }

        return $res;
    }

    /**
     * Retorna a chave de 44 dígitos da NF-e
     */
    public function retornaChave(): string
    {
        if (empty($this->chNFe) && $this->infNFe) {
            $this->chNFe = str_replace('NFe', '', $this->infNFe->getAttribute('Id'));
        }
        return $this->chNFe;
    }

    /**
     * Valida se a string é uma chave de NF-e válida (44 dígitos numéricos)
     *
     * @param string $chNFe
     * @return string|false
     */
    public static function validaChNFe($chNFe)
    {
        $limpo = str_replace(' ', '', (string)$chNFe);
        if (preg_match('/^[0-9]{44}$/', $limpo) !== 1) {
            return false;
        }
        return $limpo;
    }

    /**
     * Retorna detalhes estruturados da NF-e
     */
    public function detalhes(): array
    {
        $ret = [];

        $ide['nro'] = $this->ide ? $this->ide->getElementsByTagName('nNF')->item(0)->nodeValue : '';
        $ide['serie'] = $this->ide ? $this->ide->getElementsByTagName('serie')->item(0)->nodeValue : '';
        $ide['dataemi'] = $this->getdhEmi();
        $ide['total'] = $this->total ? str_replace('.', ',', $this->total->getElementsByTagName('vNF')->item(0)->nodeValue) : '0,00';
        $ret['ide'] = $ide;

        $emit['cnpj'] = $this->emit ? ($this->emit->getElementsByTagName('CNPJ')->item(0)->nodeValue ?? '') : '';
        $emit['nome'] = $this->emit ? ($this->emit->getElementsByTagName('xNome')->item(0)->nodeValue ?? '') : '';
        $emit['mun'] = $this->emit ? ($this->emit->getElementsByTagName('xMun')->item(0)->nodeValue ?? '') : '';
        $emit['uf'] = $this->emit ? ($this->emit->getElementsByTagName('UF')->item(0)->nodeValue ?? '') : '';
        $ret['emit'] = $emit;

        $dest['cnpj'] = $this->getDestCNPJ(false);
        $dest['nome'] = $this->dest ? ($this->dest->getElementsByTagName('xNome')->item(0)->nodeValue ?? '') : '';
        $ret['dest'] = $dest;

        $ret['infadic'] = $this->textoAdic();

        return $ret;
    }

    /**
     * Gera o arquivo PDF do DANFE (-danfe.pdf) via composição
     */
    public function geraDanfe(): array
    {
        if (empty($this->xml)) {
            return ['status' => 'erro', 'mensagem' => 'XML vazio'];
        }

        $chave = $this->retornaChave();
        $danfeArq = $this->c->local . $chave . '-danfe.pdf';

        if ($this->c->cachePdf && is_file($danfeArq) && filesize($danfeArq) > 0) {
            return [
                'url'    => $this->c->baseUrl . 'danfe/' . $chave . '-danfe.pdf',
                'status' => 'ok',
                'cached' => true,
            ];
        }

        $danfe = new Danfe($this->xml, 'P', 'A4', '', 'I', '');
        if (method_exists($danfe, 'montaDANFE')) {
            $danfe->montaDANFE();
        }
        $pdf = $danfe->render();

        file_put_contents($danfeArq, $pdf);

        return [
            'url'    => $this->c->baseUrl . 'danfe/' . $chave . '-danfe.pdf',
            'status' => 'ok',
        ];
    }

    /**
     * Aplica o registro de cancelamento no XML da NF-e
     */
    public function aplicarCancelamento(string $protocoloRawXml): void
    {
        $xmlCancelada = Complements::cancelRegister($this->xml, $protocoloRawXml);
        $this->import($xmlCancelada);
    }

    /**
     * Processa o envio de um XML completo de NF-e
     */
    public static function processarEnvioXml(string $nfe_xml): array
    {
        $res = [
            'status' => [],
            'url'    => [],
        ];

        $dom = new DOMDocument('1.0', 'utf-8');
        $dom->preserveWhiteSpace = true;
        $dom->formatOutput = false;
        if (!@$dom->loadXML($nfe_xml, LIBXML_NOBLANKS | LIBXML_NOEMPTYTAG) || $dom->getElementsByTagName('infNFe')->length === 0) {
            $res['status'] = 'Erro ao processar XML: Não tem infNFe ou XML mal formado';
            $res['xml'] = [
                'status'    => 'stop',
                'estrutura' => 'Erro: Não tem infNFe ou XML mal formado',
            ];
            return $res;
        }

        try {
            $nfe = new self($nfe_xml);
        } catch (\Throwable $e) {
            $res['status'] = 'Erro ao processar XML: ' . $e->getMessage();
            $res['xml'] = [
                'status'    => 'stop',
                'estrutura' => 'Erro ao processar XML: ' . $e->getMessage(),
            ];
            return $res;
        }

        $res['xml'] = $nfe->validaEstruturaXML($nfe_xml);

        if (($res['xml']['status'] ?? '') === 'stop') {
            return $res;
        }

        $res['xml'] = array_merge($res['xml'], $nfe->import($nfe_xml));
        if (!empty($res['xml']['url'])) {
            $res['url']['xml'] = $res['xml']['url'];
            unset($res['xml']['url']);
        }

        $res['chave'] = $nfe->retornaChave();

        // Consulta na SEFAZ via Protocolo
        $protocolo = new Protocolo();
        $consProt = $protocolo->consultaComUrl($res['chave']);
        $prot = $consProt['prot'];
        if (!empty($consProt['url'])) {
            $res['url']['proto'] = $consProt['url'];
        }

        if (!empty($prot)) {
            // Nota cancelada (cStat == 101)
            if (($prot['cStat'] ?? '') == '101') {
                try {
                    $nfe->aplicarCancelamento($prot['raw'] ?? '');
                } catch (\Throwable $e) {
                    $prot['Registro do Cancelamento'] = 'Erro: ' . $e->getMessage();
                }
            }
            $res['prot'] = $prot;

            if (($prot['status'] ?? '') === 'ok') {
                $sefaz = [
                    'age'        => $prot['age'] ?? 0,
                    'cStat'      => $prot['cStat'] ?? '',
                    'xMotivo'    => $prot['xMotivo'] ?? '',
                    'dhConsulta' => $prot['dhConsulta'] ?? '',
                    'tpAmb'      => $prot['tpAmb'] ?? '',
                    'versao'     => 'uspdev/NFE-WS ' . (defined('VERSAO') ? VERSAO : '2.0.8'),
                ];
                try {
                    $relat = Protocolo::geraRelatorioPdf($prot, $nfe);
                    if ($relat && !empty($relat['url'])) {
                        $res['url']['sefaz'] = $relat['url'];
                    }
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
        return $res;
    }

    /**
     * Processa consulta de NF-e diretamente pela chave de 44 dígitos
     */
    public static function processarConsultaChave(string $chaveInput): array
    {
        $res = [
            'status' => [],
            'url'    => [],
        ];

        if (!$chave = self::validaChNFe($chaveInput)) {
            $res['status'] = 'Chave incorreta ' . strlen($chaveInput);
            return $res;
        }

        $res['chave'] = $chave;

        $protocolo = new Protocolo();
        $consProt = $protocolo->consultaComUrl($res['chave']);
        $res['prot'] = $consProt['prot'];
        if (!empty($consProt['url'])) {
            $res['url']['proto'] = $consProt['url'];
        }

        $res['xml']['status'] = 'sem xml';
        $res['status'] = 'ok';
        return $res;
    }

    public function getdhEmi(): string
    {
        if (empty($this->ide)) {
            return '';
        }
        $node = $this->ide->getElementsByTagName('dhEmi')->item(0);
        if ($node) {
            return date('d/m/Y H:i:s', Tools::pConvertTime($node->nodeValue));
        }
        $node = $this->ide->getElementsByTagName('dEmi')->item(0);
        if ($node) {
            return date('d/m/Y', Tools::pConvertTime($node->nodeValue));
        }
        return '';
    }

    public function getdhSaiEnt(): string
    {
        if (empty($this->ide)) {
            return '';
        }
        $node = $this->ide->getElementsByTagName('dhSaiEnt')->item(0) ?? $this->ide->getElementsByTagName('dSaiEnt')->item(0);
        if ($node) {
            return date('d/m/Y H:i:s', Tools::pConvertTime($node->nodeValue));
        }
        return '';
    }

    public function getDestCNPJ(bool $formatado = true): string
    {
        if (empty($this->dest)) {
            return '';
        }
        if (!empty($this->dest->getElementsByTagName('CNPJ')->item(0)->nodeValue)) {
            $cnpj = $this->dest->getElementsByTagName('CNPJ')->item(0)->nodeValue;
            return $formatado ? Tools::format($cnpj, '###.###.###/####-##') : $cnpj;
        }
        if (!empty($this->dest->getElementsByTagName('CPF')->item(0)->nodeValue)) {
            $cpf = $this->dest->getElementsByTagName('CPF')->item(0)->nodeValue;
            return $formatado ? Tools::format($cpf, '###.###.###-##') : $cpf;
        }
        return '';
    }

    protected function textoAdic(): string
    {
        $textoAdic = '';
        if (isset($this->retirada)) {
            $txRetCNPJ = !empty($this->retirada->getElementsByTagName('CNPJ')->item(0)->nodeValue)
                ? $this->retirada->getElementsByTagName('CNPJ')->item(0)->nodeValue
                : '';
            $txRetxLgr = !empty($this->retirada->getElementsByTagName('xLgr')->item(0)->nodeValue)
                ? $this->retirada->getElementsByTagName('xLgr')->item(0)->nodeValue
                : '';
            $txRetnro = !empty($this->retirada->getElementsByTagName('nro')->item(0)->nodeValue)
                ? $this->retirada->getElementsByTagName('nro')->item(0)->nodeValue
                : 's/n';
            $txRetxBairro = !empty($this->retirada->getElementsByTagName('xBairro')->item(0)->nodeValue)
                ? $this->retirada->getElementsByTagName('xBairro')->item(0)->nodeValue
                : '';
            $txRetxMun = !empty($this->retirada->getElementsByTagName('xMun')->item(0)->nodeValue)
                ? $this->retirada->getElementsByTagName('xMun')->item(0)->nodeValue
                : '';
            $txRetUF = !empty($this->retirada->getElementsByTagName('UF')->item(0)->nodeValue)
                ? $this->retirada->getElementsByTagName('UF')->item(0)->nodeValue
                : '';
            $textoAdic .= "LOCAL DE RETIRADA : {$txRetCNPJ}-{$txRetxLgr}, {$txRetnro} - {$txRetxBairro} - {$txRetxMun} - {$txRetUF}\r\n";
        }
        return $textoAdic;
    }
}
