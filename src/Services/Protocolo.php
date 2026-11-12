<?php

namespace Uspdev\NfeWs\Services;

use DOMDocument;
use TCPDF;
use raelgc\view\Template;
use NFePHP\NFe\Common\Standardize;
use Uspdev\NfeWs\Config;
use Uspdev\NfeWs\Tabelas;
use Uspdev\NfeWs\Tools;

class Protocolo
{
    private Config $config;
    private Sefaz $sefaz;

    public function __construct(?Config $config = null, ?Sefaz $sefaz = null)
    {
        $this->config = $config ?? Config::getInstance();
        $this->sefaz = $sefaz ?? new Sefaz($this->config);
    }

    /**
     * Consulta a chave na SEFAZ e retorna os dados do protocolo parseados com URL pública
     *
     * @param string $chave
     * @return array [ 'prot' => array, 'url' => ?string ]
     */
    public function consultaComUrl(string $chave): array
    {
        try {
            $resultado = $this->sefaz->consultarChave($chave);
            $parsed = self::parse($resultado['raw'], $chave);

            return [
                'prot' => array_merge($parsed, [
                    'age' => $resultado['age'],
                    'raw' => $resultado['raw'],
                ]),
                'url'  => $resultado['url'],
            ];
        } catch (\Throwable $e) {
            return [
                'prot' => [
                    'status'  => 'erro',
                    'xMotivo' => 'Falha ao consultar SEFAZ: ' . $e->getMessage(),
                    'erro'    => $e->getMessage(),
                ],
                'url'  => null,
            ];
        }
    }

    /**
     * Faz o parse do XML retornado pela SEFAZ
     */
    public static function parse(string $rawXml, string $chave = ''): array
    {
        $ret = [];
        $cons = new DOMDocument('1.0', 'UTF-8');
        $cons->preserveWhiteSpace = false;
        $cons->formatOutput = false;
        if (!@$cons->loadXML($rawXml)) {
            return [
                'status'  => 'erro',
                'xMotivo' => 'XML de protocolo mal formado retornado pela SEFAZ',
            ];
        }

        $stdCl = new Standardize($rawXml);
        $prot = $stdCl->toArray();

        $ret['cStat'] = $prot['cStat'] ?? '';
        $ret['xMotivo'] = $prot['xMotivo'] ?? '';
        $ret['tpAmb'] = $prot['tpAmb'] ?? '';

        if (!empty($prot['dhRecbto'])) {
            $ret['dhConsulta'] = date('d/m/Y - H:i:s', Tools::pConvertTime($prot['dhRecbto']));
        } else {
            $config = Config::getInstance();
            $arq = $config->local . $chave . '-prot.xml';
            $ret['dhConsulta'] = is_file($arq) ? date('d/m/Y - H:i:s', filemtime($arq)) . ' (data do arquivo)' : date('d/m/Y - H:i:s');
        }

        if ($ret['cStat'] == 526) {
            $ret['status'] = 'Erro: a nfe consultada foi gerada há mais de 6 meses';
            return $ret;
        }

        if (!$cons->getElementsByTagName('infProt')->item(0)) {
            $ret['status'] = 'Erro: Retorno sem infProt';
            return $ret;
        }
        $ret['status'] = 'ok';

        $protNFe = $cons->getElementsByTagName('protNFe')->item(0);
        if ($protNFe) {
            $ret['eventos'][0]['tpEvento'] = $protNFe->getElementsByTagName('cStat')->item(0)->nodeValue ?? '';
            $ret['eventos'][0]['descEvento'] = $protNFe->getElementsByTagName('xMotivo')->item(0)->nodeValue ?? '';
            $ret['eventos'][0]['nProt'] = $protNFe->getElementsByTagName('nProt')->item(0)->nodeValue ?? '';
            $ret['eventos'][0]['dhEvento'] = !empty($protNFe->getElementsByTagName('dhRecbto')->item(0))
                ? date('d/m/Y - H:i:s', Tools::pConvertTime($protNFe->getElementsByTagName('dhRecbto')->item(0)->nodeValue))
                : '';
            $ret['eventos'][0]['digVal'] = $protNFe->getElementsByTagName('digVal')->item(0)->nodeValue ?? '';
        }

        $eventos = $cons->getElementsByTagName('procEventoNFe');
        foreach ($eventos as $evento) {
            $i = (int)($evento->getElementsByTagName('nSeqEvento')->item(0)->nodeValue ?? 1);
            $ret['eventos'][$i]['tpEvento'] = $evento->getElementsByTagName('tpEvento')->item(0)->nodeValue ?? '';
            $ret['eventos'][$i]['descEvento'] = $evento->getElementsByTagName('descEvento')->item(0)->nodeValue ?? '';
            $ret['eventos'][$i]['dhEvento'] = !empty($evento->getElementsByTagName('dhEvento')->item(0))
                ? date('d/m/Y - H:i:s', Tools::pConvertTime($evento->getElementsByTagName('dhEvento')->item(0)->nodeValue))
                : '';
            $retEvento = $evento->getElementsByTagName('retEvento')->item(0);
            $ret['eventos'][$i]['nProt'] = $retEvento ? ($retEvento->getElementsByTagName('nProt')->item(0)->nodeValue ?? '') : '';
        }

        return $ret;
    }

    /**
     * Gera o relatorio SEFAZ em PDF do protocolo (-prot.pdf)
     *
     * @param array $prot Dados parseados do protocolo
     * @param mixed $nfe Instancia da NF-e
     * @return array|false [ 'url' => string, 'cached' => bool ]
     */
    public static function geraRelatorioPdf(array $prot, $nfe)
    {
        $config = Config::getInstance();
        $chave = $nfe->retornaChave();

        $arqXml = $config->local . $chave . '-nfe.xml';
        $arqProtoPdf = $config->local . $chave . '-prot.pdf';
        $arqProtoUrl = $config->baseUrl . 'sefaz/' . $chave . '-prot.pdf';

        if (empty($prot) || ($prot['status'] ?? '') !== 'ok') {
            return false;
        }

        if ($config->cachePdf && is_file($arqProtoPdf) && filesize($arqProtoPdf) > 0 && !empty($prot['age'])) {
            return [
                'url'    => $arqProtoUrl,
                'cached' => true,
            ];
        }

        if (!file_exists($arqXml)) {
            if ($config->debug) {
                error_log('Protocolo: Arquivo XML inexistente para gerar PDF do protocolo: ' . $arqXml);
            }
            return false;
        }

        $tpl = new Template(__DIR__ . '/../../templates/protocolo.tpl');

        $tpl->chNFe = Tools::format($chave, '##-####-##.###.###/####-##-##-###-###.###.###-###.###.###-#');
        $tpl->nNF = $nfe->ide->getElementsByTagName('nNF')->item(0)->nodeValue ?? '';
        $tpl->versao = $nfe->versao;
        $tpl->serie = $nfe->ide->getElementsByTagName('serie')->item(0)->nodeValue ?? '';
        $tpl->mod = $nfe->ide->getElementsByTagName('mod')->item(0)->nodeValue ?? '';
        $tpl->dhEmi = $nfe->getdhEmi();
        $tpl->dhSaiEnt = $nfe->getdhSaiEnt();

        if ($nfe->versao > 3) {
            $indFinal = $nfe->ide->getElementsByTagName('indFinal')->item(0)->nodeValue ?? '';
            $tpl->indFinal = $indFinal !== '' ? $indFinal . ' - ' . Tabelas::indFinal($indFinal) : '';

            $indPres = $nfe->ide->getElementsByTagName('indPres')->item(0)->nodeValue ?? '';
            $tpl->indPres = $indPres !== '' ? $indPres . ' - ' . Tabelas::indPres($indPres) : '';

            $idDest = $nfe->ide->getElementsByTagName('idDest')->item(0)->nodeValue ?? '';
            $tpl->idDest = $idDest !== '' ? $idDest . ' - ' . Tabelas::idDest($idDest) : '';
        }

        $tpl->vNF = str_replace('.', ',', $nfe->total->getElementsByTagName('vNF')->item(0)->nodeValue ?? '0,00');

        // Emitente CNPJ/CPF
        if (!empty($nfe->emit->getElementsByTagName('CNPJ')->item(0)->nodeValue)) {
            $tpl->emitCNPJ = Tools::format($nfe->emit->getElementsByTagName('CNPJ')->item(0)->nodeValue, '###.###.###/####-##');
        } elseif (!empty($nfe->emit->getElementsByTagName('CPF')->item(0)->nodeValue)) {
            $tpl->emitCNPJ = Tools::format($nfe->emit->getElementsByTagName('CPF')->item(0)->nodeValue, '###.###.###-##');
        } else {
            $tpl->emitCNPJ = '';
        }

        $tpl->emitNome = $nfe->emit->getElementsByTagName('xNome')->item(0)->nodeValue ?? '';
        $tpl->emitIE = $nfe->emit->getElementsByTagName('IE')->item(0)->nodeValue ?? '';
        $tpl->emitUF = $nfe->emit->getElementsByTagName('UF')->item(0)->nodeValue ?? '';

        // Destinatario
        $tpl->destCNPJ = $nfe->getDestCNPJ(true);
        $tpl->destNome = $nfe->dest->getElementsByTagName('xNome')->item(0)->nodeValue ?? '';
        $tpl->destIE = !empty($nfe->dest->getElementsByTagName('IE')->item(0)->nodeValue) ? $nfe->dest->getElementsByTagName('IE')->item(0)->nodeValue : '-';
        $tpl->destUF = $nfe->dest->getElementsByTagName('UF')->item(0)->nodeValue ?? '';

        $procEmi = $nfe->ide->getElementsByTagName('procEmi')->item(0)->nodeValue ?? '';
        $tpl->procEmi = $procEmi !== '' ? $procEmi . ' - ' . Tabelas::procEmi($procEmi) : '';
        $tpl->verProc = $nfe->ide->getElementsByTagName('verProc')->item(0)->nodeValue ?? '';

        $tpEmis = $nfe->ide->getElementsByTagName('tpEmis')->item(0)->nodeValue ?? '';
        $tpl->tpEmis = $tpEmis !== '' ? $tpEmis . ' - ' . Tabelas::tpEmis($tpEmis) : '';

        $finNFe = $nfe->ide->getElementsByTagName('finNFe')->item(0)->nodeValue ?? '';
        $tpl->finNFe = $finNFe !== '' ? $finNFe . ' - ' . Tabelas::finNFe($finNFe) : '';

        $tpl->natOp = $nfe->ide->getElementsByTagName('natOp')->item(0)->nodeValue ?? '';

        $tpNF = $nfe->ide->getElementsByTagName('tpNF')->item(0)->nodeValue ?? '';
        $tpl->tpNF = $tpNF !== '' ? $tpNF . ' - ' . Tabelas::tpNF($tpNF) : '';

        if ($nfe->versao >= 4) {
            $tpl->indPag = '';
        } else {
            $indPag = $nfe->ide->getElementsByTagName('indPag')->item(0)->nodeValue ?? '';
            $tpl->indPag = $indPag !== '' ? $indPag . ' - ' . Tabelas::indPag($indPag) : '';
        }

        $tpl->digestValue = $nfe->dom->getElementsByTagName('DigestValue')->item(0)->nodeValue ?? '';
        $tpl->situacao = mb_strtoupper(Tabelas::cStat($prot['cStat'] ?? ''), 'UTF-8');
        $tpl->tpAmb = mb_strtoupper(Tabelas::tpAmb($prot['tpAmb'] ?? ''), 'UTF-8');

        if (!empty($prot['eventos']) && is_array($prot['eventos'])) {
            foreach ($prot['eventos'] as $evento) {
                $tpl->descEvento = $evento['descEvento'] ?? '';
                $tpl->nProt = $evento['nProt'] ?? '';
                $tpl->dhEvento = $evento['dhEvento'] ?? '';
                $tpl->block('EVENTOS_BLOCK');
            }
        }

        $tpl->dhConsulta = $prot['dhConsulta'] ?? date('d/m/Y - H:i:s');
        $tpl->infoSistema = 'Sistema DELOS/NFE ' . $config->appVersao;

        $html = $tpl->parse();

        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator(PDF_CREATOR);
        $pdf->SetAuthor('DELOS-NFE');
        $pdf->SetTitle('Relatório de Consulta à SEFAZ');
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(20, 20, 20, true);
        $pdf->SetAutoPageBreak(false, 5);
        $pdf->AddPage();
        $pdf->setFontSubsetting(true);
        $pdf->setCellHeightRatio(1.5);

        $gray = 128;
        $pdf->SetTextColor($gray);
        $pdf->setLineStyle(['width' => 0.5, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => [$gray]]);
        $pdf->SetFont('times', 'I', 8, '', true);
        $pdf->writeHTMLCell(0, 0, 20, 20, '', 'B', 0, 0, true, 'R');

        $pdf->SetY(25);
        $pdf->SetTextColor(0);
        $pdf->SetFont('helvetica', '', 8, '', true);
        $pdf->writeHTML($html, true, false, true, false, '');

        $pdf->SetY(-15);
        $pdf->SetFont('times', '', 10, '', true);
        $pdf->SetTextColor($gray);
        $pdf->setLineStyle(['width' => 0.5, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => [$gray]]);
        $rodape = 'DELOS/NFE ' . $tpl->dhConsulta;
        $pdf->writeHTMLCell(0, 0, '', '', $rodape, 'T', 0, 0, true, 'C');

        $logoPath = __DIR__ . '/../../templates/images/logo_usp.png';
        if (is_file($logoPath)) {
            $pdf->Image($logoPath, 210 - 45, 297 - 14, 25);
        }

        $pdf->Output($arqProtoPdf, 'F');

        return [
            'url' => $arqProtoUrl,
        ];
    }
}
