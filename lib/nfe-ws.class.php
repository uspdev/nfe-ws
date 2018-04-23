<?php

use NFePHP\Common\Certificate;
//use NFePHP\NFe\Common\Complements;
use NFePHP\Common\Signer;
use NFePHP\DA\Legacy\Common;

// para danfe
use NFePHP\DA\NFe\Danfe;


class nfe_ws extends Common
{
    protected $local;
    protected $tools;
    protected $xml;
    protected $chNFe;
    public $prot;

    function __construct($cfg = array())
    {

        $this->c = new Config();
        $this->local = $this->c->local;
        //echo $this->local;exit;

        //$this->local = $cfg['local'];
        $arr = [
            "atualizacao" => "2016-11-03 18:01:21",
            "tpAmb" => 1,
            "razaosocial" => "Escola de Engenharia de São Carlos",
            "cnpj" => "63025530002824",
            "siglaUF" => "SP",
            "schemes" => "PL008i2",
            "versao" => '3.10',
            "tokenIBPT" => "AAAAAAA",
            "CSC" => "GPB0JBWLUR6HWFTVEAS6RJ69GPCROFPBBB8G",
            "CSCid" => "000001",
            "proxyConf" => [
                "proxyIp" => "",
                "proxyPort" => "",
                "proxyUser" => "",
                "proxyPass" => ""
            ]
        ];
        //monta o config.json
        $configJson = json_encode($arr);
        //carrega o conteudo do certificado.
        $cert = file_get_contents($cfg['cert_file']);

        $this->tools = new Tools($configJson, Certificate::readPfx($cert, $cfg['cert_pwd']));
    }

    /*
     * importa um xml de nfe, verifica e atribui os valores
     */
    public function import($xml)
    {
        // todo: tem de verificar se o XML que veio é NFE
        $this->xml = $xml;
        if (!empty($this->xml)) {
            $this->dom = new \DOMDocument('1.0', 'UTF-8');
            $this->dom->preserveWhiteSpace = false;
            $this->dom->formatOutput = false;
            $this->dom->loadXML($this->xml);

            $this->nfeProc = $this->dom->getElementsByTagName("nfeProc")->item(0);
            $this->infNFe = $this->dom->getElementsByTagName("infNFe")->item(0);
            $this->ide = $this->dom->getElementsByTagName("ide")->item(0);

            $this->total = $this->dom->getElementsByTagName("total")->item(0);

            $this->entrega = $this->dom->getElementsByTagName("entrega")->item(0);
            $this->retirada = $this->dom->getElementsByTagName("retirada")->item(0);
            $this->emit = $this->dom->getElementsByTagName("emit")->item(0);
            $this->dest = $this->dom->getElementsByTagName("dest")->item(0);
            $this->enderEmit = $this->dom->getElementsByTagName("enderEmit")->item(0);
            $this->enderDest = $this->dom->getElementsByTagName("enderDest")->item(0);
            $this->det = $this->dom->getElementsByTagName("det");
            $this->cobr = $this->dom->getElementsByTagName("cobr")->item(0);
            $this->dup = $this->dom->getElementsByTagName('dup');
            $this->ICMSTot = $this->dom->getElementsByTagName("ICMSTot")->item(0);
            $this->ISSQNtot = $this->dom->getElementsByTagName("ISSQNtot")->item(0);
            $this->transp = $this->dom->getElementsByTagName("transp")->item(0);
            $this->transporta = $this->dom->getElementsByTagName("transporta")->item(0);
            $this->veicTransp = $this->dom->getElementsByTagName("veicTransp")->item(0);
            $this->reboque = $this->dom->getElementsByTagName("reboque")->item(0);
            $this->infAdic = $this->dom->getElementsByTagName("infAdic")->item(0);
            $this->compra = $this->dom->getElementsByTagName("compra")->item(0);

            //$this->tpEmis = $this->ide->getElementsByTagName("tpEmis")->item(0)->nodeValue;
            //echo 'ok';exit;
            //$this->tpImp = $this->ide->getElementsByTagName("tpImp")->item(0)->nodeValue;

            //$this->infProt = $this->dom->getElementsByTagName("infProt")->item(0);


            //valida se o XML é uma NF-e modelo 55, pois não pode ser 65 (NFC-e)
            if ($this->pSimpleGetValue($this->ide, "mod") != '55') {
                throw new InvalidArgumentException("O xml do DANFE deve ser uma NF-e modelo 55");
            }

        }


        $nfeArq = $this->local . $this->retornaChave() . '-nfe.xml'; // nome e caminho do arquivo xml
        if (!file_exists($nfeArq)) {
            file_put_contents($nfeArq, $this->xml);
        }
        return true;
    }

    public function geraDanfe()
    {
        if (empty($this->xml)) {
            return false;
        }
        $chave = $this->retornaChave();
        $danfeArq = $this->local . $chave . '-danfe.pdf';

        $danfe = new Danfe($this->xml, 'P', 'A4', 'images/logo.jpg', 'I', '');
        $id = $danfe->montaDANFE();
        $pdf = $danfe->render();

        if (!file_exists($danfeArq)) {
            file_put_contents($danfeArq, $pdf);
        }

        $res['age'] = Tools::msgTempo(time(), filemtime($danfeArq));
        $res['file'] = $danfeArq;
        $res['url'] = $this->c->baseUrl . 'api/danfe/' . $chave . '-danfe.pdf';
        $res['status'] = 'ok';
        return $res;
    }

    // tem de verificar a integridade do XML
    // assinatura
    // estrutura
    // ???
    public function validaEstruturaXML($xml)
    {
        libxml_use_internal_errors(false);
        $dom = new DOMDocument('1.0', 'utf-8');
        $dom->preserveWhiteSpace = true;
        $dom->formatOutput = false;
        $res = [];
        try {
            $dom->loadXML($xml, LIBXML_NOBLANKS | LIBXML_NOEMPTYTAG);
        } catch (Exception $e) {
            $res['status'] = false;
            $res['msg'] = 'XML mal formado: ' . $e->getMessage();
            return $res;
        }
        $res['estrutura'] = 'Esrutura do XML está OK';

        try {
            Signer::existsSignature($xml);
        } catch (exception $e) {
            $res['msg'] = 'Sem assinatura';
            $res['status'] = false;
            return $res;
        };


        try {
            Signer::digestCheck($xml);
        } catch (exception $e) {
            $res['msg'] = 'Digest não confere';
            $res['status'] = false;
            return $res;
        }
        $res['assinatura-digest'] = 'Confere';

        try {
            Signer::signatureCheck($xml);
        } catch (exception $e) {
            $res['msg'] = 'Assinatura não confere';
            $res['status'] = false;
            return $res;
        }
        $res['assinatura'] = 'Assinado';

        $res['status'] = 'ok';

        return $res;
    }

    public function retornaChave()
    {
        $this->chNFe = str_replace('NFe', '', $this->infNFe->getAttribute("Id"));
        return $this->chNFe;
    }


    public function dadosParaProtocolo()
    {

    }

    public function geraProtocolo()
    {

        $debug = false;
        include_once 'functions.php';

        $arq_xml = $this->c->local . $this->chNFe . '-nfe.xml';
        $arq_proto_pdf = $this->c->local . $this->chNFe . '-prot.pdf';
        $arq_proto_url = $this->c->baseUrl .'api/sefaz/'. $this->chNFe . '-prot.pdf';

        $arq_proto = $this->c->local . $this->chNFe . '-prot.xml';

        $system = 'Sistema DELOS NFe - http://delos.eesc.usp.br/nfe';
        $script = 'Protocolo.pdf versão 1.1.0 de 11/05/2015.';
        $msg_consulta = 'Esta consulta foi realizada pelo Sistema DELOS-NFe em ';

        // -----------------------------------
        if ($debug) echo 'arq_xml = ' . $arq_xml;
        if ($debug) echo 'arq_proto_pdf = ' . $arq_proto_pdf;
        if ($debug) echo "\nGera protocolo: ";


        if (!file_exists($arq_xml)) {
            if ($debug) echo "Arquivo xml inexistente! Processo abortado.\n";
            return false;
        }

        require_once('codigos_nfe.php');
        require_once('SPDF.class.php');

        $tpl = new \raelgc\view\Template(__DIR__ . '/protocolo.tpl');

        $xml = new DomDocument();
        $xml->loadXml(file_get_contents($arq_xml));


        $tpl->chNFe = $this->pFormat($this->chNFe, '##-####-##.###.###/####-##-##-###-###.###.###-###.###.###-#');
        $tpl->nNF = $this->ide->getElementsByTagName('nNF')->item(0)->nodeValue;
        $tpl->versao = $this->dom->getElementsByTagName('infNFe')->item(0)->getAttribute('versao');

        $tpl->serie = $this->ide->getElementsByTagName('serie')->item(0)->nodeValue;

        //$tpl->mod = $this->ide->getElementsByTagName('mod')->item(0)->nodeValue;
        $tpl->mod = $this->pSimpleGetValue($this->ide, 'mod');

        if ($tpl->versao > 3) { // versão 3.1 é de um jeito, versão 2.00 é de outro. Nao sei os intermediários
            $tpl->dhEmi = date("d/m/Y - H:i:s", convertTime($this->ide->getElementsByTagName('dhEmi')->item(0)->nodeValue));
        } else {
            $tpl->dhEmi = date("d/m/Y", convertTime($this->ide->getElementsByTagName('dEmi')->item(0)->nodeValue . 'T00:00:00'));
        }

        // data e hora de saída pode não estar presente e pode ter formatos diferentes
        if ($tpl->versao > 3) {
            if ($this->ide->getElementsByTagName('dhSaiEnt')->item(0)) {
                $tpl->dhSaiEnt = $this->ide->getElementsByTagName('dhSaiEnt')->item(0)->nodeValue;
                $tpl->dhSaiEnt = date("d/m/Y H:i:s", convertTime($tpl->dhSaiEnt));
            } else {
                $tpl->dhSaiEnt = '';
            }
        } else {
            if (!$tpl->dhSaiEnt = $this->ide->getElementsByTagName('dSaiEnt')->item(0)->nodeValue)
                $tpl->dhSaiEnt = '';
            else
                $tpl->dhSaiEnt = date("d/m/Y H:i:s", convertTime($tpl->dhSaiEnt . 'T00:00:00'));
        }

        $tpl->indFinal = $this->ide->getElementsByTagName('indFinal')->item(0)->nodeValue;
        $tpl->indFinal = $tpl->indFinal . ' - ' . Storage::indFinal($tpl->indFinal);

        $tpl->indPres = $this->ide->getElementsByTagName('indPres')->item(0)->nodeValue;
        $tpl->indPres = $tpl->indPres . ' - ' . Storage::indPres($tpl->indPres);

        $tpl->idDest = $this->ide->getElementsByTagName('idDest')->item(0)->nodeValue;
        $tpl->idDest = $tpl->idDest . ' - ' . Storage::idDest($tpl->idDest);

        $tpl->vNF = str_replace('.', ',', $this->total->getElementsByTagName('vNF')->item(0)->nodeValue);

        //Pegando valor do CPF/CNPJ emitente
        if (!empty($this->emit->getElementsByTagName("CNPJ")->item(0)->nodeValue)) {
            $tmp = $this->emit->getElementsByTagName("CNPJ")->item(0)->nodeValue;
            $tpl->emitCNPJ = $this->pFormat($tmp, "###.###.###/####-##");
        } else {
            if (!empty($this->emit->getElementsByTagName("CPF")->item(0)->nodeValue)) {
                $tmp = $this->emit->getElementsByTagName("CPF")->item(0)->nodeValue;
                $tpl->emitCNPJ = $this->pFormat($tmp, "###.###.###-##");
            } else {
                $tpl->emitCNPJ = '';
            }
        }

        $tpl->emitNome = $this->emit->getElementsByTagName("xNome")->item(0)->nodeValue;
        $tpl->emitIE = $this->emit->getElementsByTagName("IE")->item(0)->nodeValue;
        $tpl->emitUF = $this->emit->getElementsByTagName("UF")->item(0)->nodeValue;

        //Pegando valor do CPF/CNPJ detinatário
        if (!empty($this->dest->getElementsByTagName("CNPJ")->item(0)->nodeValue)) {
            $tmp = $this->dest->getElementsByTagName("CNPJ")->item(0)->nodeValue;
            $tpl->destCNPJ = $this->pFormat($tmp, "###.###.###/####-##");
        } else {
            if (!empty($this->dest->getElementsByTagName("CPF")->item(0)->nodeValue)) {
                $tmp = $this->dest->getElementsByTagName("CPF")->item(0)->nodeValue;
                $tpl->destCNPJ = $this->pFormat($tmp, "###.###.###-##");
            } else {
                $tpl->destCNPJ = '';
            }
        }

        $tpl->destNome = $this->dest->getElementsByTagName("xNome")->item(0)->nodeValue;

        $tpl->destIE = !empty($this->dest->getElementsByTagName("IE")->item(0)->nodeValue) ? $this->dest->getElementsByTagName("IE")->item(0)->nodeValue : '-';
        $tpl->destUF = $this->dest->getElementsByTagName("UF")->item(0)->nodeValue;

        $tpl->procEmi = $this->ide->getElementsByTagName('procEmi')->item(0)->nodeValue;
        $tpl->procEmi = $tpl->procEmi . ' - ' . Storage::procEmi($tpl->procEmi);

        $tpl->verProc = $this->ide->getElementsByTagName('verProc')->item(0)->nodeValue;

        $tpl->tpEmis = $this->ide->getElementsByTagName('tpEmis')->item(0)->nodeValue;
        $tpl->tpEmis = $tpl->tpEmis . ' - ' . Storage::tpEmis($tpl->tpEmis);

        $tpl->finNFe = $this->ide->getElementsByTagName('finNFe')->item(0)->nodeValue;
        $tpl->finNFe = $tpl->finNFe . ' - ' . Storage::finNFe($tpl->finNFe);

        $tpl->natOp = $this->ide->getElementsByTagName('natOp')->item(0)->nodeValue;

        $tpl->tpNF = $this->ide->getElementsByTagName('tpNF')->item(0)->nodeValue;
        $tpl->tpNF = $tpl->tpNF . ' - ' . Storage::tpNF($tpl->tpNF);

        $tpl->indPag = $this->ide->getElementsByTagName('indPag')->item(0)->nodeValue;
        $tpl->indPag = $tpl->indPag . ' - ' . Storage::indPag($tpl->indPag);

        // nao bateu com o consultado na sefaz de SP
        // para NF 35180362640511000125550010000328311061546746
        $tpl->digestValue = $this->dom->getElementsByTagName('DigestValue')->item(0)->nodeValue;


        // pega os dados do protocolo para colocar no pdf
        if (empty($this->prot)) {
            return false;
        }

        $tpl->situacao = '';
        if ($this->prot['cStat'] == 100) {
            $tpl->situacao = 'APROVADA';
        }
        if ($this->prot['cStat'] == 101) {
            $tpl->situacao = 'CANCELADA';
        }

        $tpl->tpAmb = mb_strtoupper(Storage::tpAmb($this->prot['tpAmb']), 'UTF-8');

        foreach ($this->prot['eventos'] as $evento) {
            $tpl->descEvento = $evento['descEvento'];
            $tpl->nProt = $evento['nProt'];
            $tpl->dhEvento = $evento['dhEvento'];
            $tpl->block('EVENTOS_BLOCK');
        }

        $tpl->dhConsulta = $this->prot['dhConsulta'];
        $tpl->infoSistema = 'Sistema DELOS/NFE';

        $html = $tpl->parse();

        //echo $html;

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
        $pdf->setCellHeightRatio(1.5); // aumenta o espaçamento entre linhas

        // cabeçalho
        //$pdf->Image(__DIR__ . $logo, 15, 8, 50);
        $unidade = '';
        $cnpj = '';

        $gray = 128;
        $pdf->SetTextColor($gray);
        $pdf->setLineStyle(array('width' => 0.5, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => array($gray)));

        //$pdf->SetFont('times', 'I', 10, '', true);
        //$pdf->writeHTMLCell(0, 0, 33, 16, $unidade, 0, 0, 0, true, 'R');

        $pdf->SetFont('times', 'I', 8, '', true);
        //$pdf->writeHTMLCell(0, 0, 33, 20, $cnpj, 'B', 0, 0, true, 'R');
        $pdf->writeHTMLCell(0, 0, 20, 20, $cnpj, 'B', 0, 0, true, 'R');

        // Corpo
        $pdf->SetY(25);

        $pdf->SetTextColor(0);
        $pdf->SetFont('helvetica', '', 8, '', true);

        $pdf->writeHTML($html, true, false, true, false, '');

        $pdf->SetY(-15);

        // posiciona o rodapé
        $pdf->SetFont('times', '', 10, '', true);
        $pdf->SetTextColor($gray);
        $pdf->setLineStyle(array('width' => 0.5, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => array($gray)));

        $rodape = 'DELOS/NFE ';
        $rodape .= $tpl->dhConsulta;
        $pdf->writeHTMLCell(0, 0, '', '', $rodape, 'T', 0, 0, true, 'C');

        $pdf->Image(__DIR__ . '/logo_usp.png', 210 - 45, 297 - 14, 25);

        $pdf->Output($arq_proto_pdf, 'F');
        $ret['file'] = $arq_proto_pdf;
        $ret['url'] = $arq_proto_url;

        return $ret;


    }


}