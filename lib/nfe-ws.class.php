<?php

use NFePHP\Common\Signer;
use NFePHP\DA\Legacy\Common;

// para danfe
use NFePHP\DA\NFe\Danfe;


class nfe_ws extends Danfe
{
    protected $local;
    protected $tools;
    protected $xml;
    protected $chNFe;
    public $prot;

    function __construct()
    {
        $this->c = new Config();
        $this->local = $this->c->local;
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


            //verifica se o XML é uma NF-e modelo 55, pois não pode ser 65 (NFC-e)
            if ($this->pSimpleGetValue($this->ide, "mod") != '55') {
                $res['modelo'] = 'Não é NFe modelo 55.';
                $res['status'] = 'Erro';
                return $res;
            } else {
                $res['modelo'] = '55';
            }
        }

        $nfeArq = $this->local . $this->retornaChave() . '-nfe.xml'; // nome e caminho do arquivo xml
        //if (!file_exists($nfeArq)) {
        // sempre salva o arquivo novo enviado sobrescrevendo o já existente
        file_put_contents($nfeArq, $this->xml);
        //}
        $this->versao = $this->dom->getElementsByTagName('infNFe')->item(0)->getAttribute('versao');
        $res['url'] = $this->c->baseUrl . 'api/xml/' . $this->retornaChave() . '-nfe.xml';
        $res['import'] = 'Importado com sucesso';
        $res['versao'] = $this->versao;
        $res['status'] = 'ok';
        return $res;
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

        file_put_contents($danfeArq, $pdf);

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
        $res['status'] = true;
        try {
            $dom->loadXML($xml, LIBXML_NOBLANKS | LIBXML_NOEMPTYTAG);
            $res['estrutura'] = 'Estrutura do XML está OK';
        } catch (Exception $e) {
            $res['status'] = 'stop';
            $res['estrutura'] = 'Erro: XML mal formado: ' . $e->getMessage();
            return $res;
        }

        if ($dom->getElementsByTagName("nfeProc")->length == 0) {
            // sem nfeProc quer dizer que nao foi adicionado o protocolo da sefaz: sem valor fiscal
            // mas ainda dá para tentar gerar a danfe, o que não é correto
            $res['nfeproc'] = 'Erro: O XML não está protocolado portanto sem valor fiscal!';
            //$res['status'] = 'stop';
            //return $res;
        }

        if ($dom->getElementsByTagName("infNFe")->length == 0) {
            // nesse caso é um xml mas não de NFE (sem a tag inicial)
            // tem um caso de nfe que passa no validador RS mas nao tem nfeproc
            // entao vamos consultar a tag infNfe
            $res['estrutura'] = 'Erro: Não tem infNFe';
            $res['status'] = 'stop';
            return $res;
        }

        try {
            $assinatura = Signer::existsSignature($xml);
        } catch (exception $e) {
            $res['assinatura'] = 'Erro: Sem assinatura - ' . $e->getMessage();
            $res['status'] = false;
            // dá para continuar sem assinatura mas já não tem validade fiscal
        }

        try {
            if ($assinatura) {
                Signer::signatureCheck($xml);
                $res['assinatura'] = 'Assinatura ok';
            }
        } catch (exception $e) {
            $res['assinatura'] = 'Erro: Assinatura não confere - ' . $e->getMessage();
            $res['status'] = false;
        }

        try {
            if (!empty($dom->getElementsByTagName('Signature')->item(0))) {
                Signer::digestCheck($xml);
                $res['digest'] = 'Digest ok';
            } else {
                $res['digest'] = 'Erro: Sem assinatura';
                $res['status'] = 'stop';
                return $res; // vamos parar aqui se o xml estiver muito ruim
            }

        } catch (exception $e) {
            $res['digest'] = 'Erro: ' . $e->getMessage();
            $res['status'] = false;
        }


        if ($res['status']) {
            $res['status'] = 'ok';
        }

        return $res;
    }

    public function retornaChave()
    {
        $this->chNFe = str_replace('NFe', '', $this->infNFe->getAttribute("Id"));
        return $this->chNFe;
    }

    /*
     * necessário validar quando recebe a chave do usuário
     * a chave é somente números com 44 digitos
     */
    public static function validaChNFe($chNFe)
    {
        if (preg_match("/^[0-9]{44}$/", str_replace(' ', '', $chNFe)) != 1) {
            return false;
        }
        return $chNFe;
    }

    /*
     * Retorna detalhes da danfe para cadastrar no BD de NFEs
     */
    public function detalhes()
    {
        $ide['nro'] = $this->ide->getElementsByTagName('nNF')->item(0)->nodeValue;
        $ide['serie'] = $this->ide->getElementsByTagName('serie')->item(0)->nodeValue;
        $ide['dataemi'] = $this->getdhEmi();
        $ide['total'] = str_replace('.', ',', $this->total->getElementsByTagName('vNF')->item(0)->nodeValue);
        $ret['ide'] = $ide;

        $emit['cnpj'] = $this->emit->getElementsByTagName('CNPJ')->item(0)->nodeValue;
        $emit['nome'] = $this->emit->getElementsByTagName('xNome')->item(0)->nodeValue;
        $emit['mun'] = $this->emit->getElementsByTagName('xMun')->item(0)->nodeValue;
        $emit['uf'] = $this->emit->getElementsByTagName('UF')->item(0)->nodeValue;
        $ret['emit'] = $emit;

        $dest['cnpj'] = $this->getDestCNPJ(false);
        $dest['nome'] = $this->dest->getElementsByTagName('xNome')->item(0)->nodeValue;
        $ret['dest'] = $dest;

        $ret['infadic'] = $this->textoAdic();


        return $ret;
    }


    protected function textoAdic()
    {
        $textoAdic = '';
        if (isset($this->retirada)) {
            $txRetCNPJ = !empty($this->retirada->getElementsByTagName("CNPJ")->item(0)->nodeValue) ?
                $this->retirada->getElementsByTagName("CNPJ")->item(0)->nodeValue :
                '';
            $txRetxLgr = !empty($this->retirada->getElementsByTagName("xLgr")->item(0)->nodeValue) ?
                $this->retirada->getElementsByTagName("xLgr")->item(0)->nodeValue :
                '';
            $txRetnro = !empty($this->retirada->getElementsByTagName("nro")->item(0)->nodeValue) ?
                $this->retirada->getElementsByTagName("nro")->item(0)->nodeValue :
                's/n';
            $txRetxCpl = $this->pSimpleGetValue($this->retirada, "xCpl", " - ");
            $txRetxBairro = !empty($this->retirada->getElementsByTagName("xBairro")->item(0)->nodeValue) ?
                $this->retirada->getElementsByTagName("xBairro")->item(0)->nodeValue :
                '';
            $txRetxMun = !empty($this->retirada->getElementsByTagName("xMun")->item(0)->nodeValue) ?
                $this->retirada->getElementsByTagName("xMun")->item(0)->nodeValue :
                '';
            $txRetUF = !empty($this->retirada->getElementsByTagName("UF")->item(0)->nodeValue) ?
                $this->retirada->getElementsByTagName("UF")->item(0)->nodeValue :
                '';
            $textoAdic .= "LOCAL DE RETIRADA : " .
                $txRetCNPJ .
                '-' .
                $txRetxLgr .
                ', ' .
                $txRetnro .
                ' ' .
                $txRetxCpl .
                ' - ' .
                $txRetxBairro .
                ' ' .
                $txRetxMun .
                ' - ' .
                $txRetUF .
                "\r\n";
        }
        //dados do local de entrega da mercadoria
        if (isset($this->entrega)) {
            $txRetCNPJ = !empty($this->entrega->getElementsByTagName("CNPJ")->item(0)->nodeValue) ?
                $this->entrega->getElementsByTagName("CNPJ")->item(0)->nodeValue : '';
            $txRetxLgr = !empty($this->entrega->getElementsByTagName("xLgr")->item(0)->nodeValue) ?
                $this->entrega->getElementsByTagName("xLgr")->item(0)->nodeValue : '';
            $txRetnro = !empty($this->entrega->getElementsByTagName("nro")->item(0)->nodeValue) ?
                $this->entrega->getElementsByTagName("nro")->item(0)->nodeValue : 's/n';
            $txRetxCpl = $this->pSimpleGetValue($this->entrega, "xCpl", " - ");
            $txRetxBairro = !empty($this->entrega->getElementsByTagName("xBairro")->item(0)->nodeValue) ?
                $this->entrega->getElementsByTagName("xBairro")->item(0)->nodeValue : '';
            $txRetxMun = !empty($this->entrega->getElementsByTagName("xMun")->item(0)->nodeValue) ?
                $this->entrega->getElementsByTagName("xMun")->item(0)->nodeValue : '';
            $txRetUF = !empty($this->entrega->getElementsByTagName("UF")->item(0)->nodeValue) ?
                $this->entrega->getElementsByTagName("UF")->item(0)->nodeValue : '';
            if ($textoAdic != '') {
                $textoAdic .= ". \r\n";
            }
            $textoAdic .= "LOCAL DE ENTREGA : " . $txRetCNPJ . '-' . $txRetxLgr . ', ' . $txRetnro . ' ' . $txRetxCpl .
                ' - ' . $txRetxBairro . ' ' . $txRetxMun . ' - ' . $txRetUF . "\r\n";
        }
        //informações adicionais
        $textoAdic .= $this->pGeraInformacoesDasNotasReferenciadas();
        if (isset($this->infAdic)) {
            $i = 0;
            if ($textoAdic != '') {
                $textoAdic .= ". \r\n";
            }
            $textoAdic .= !empty($this->infAdic->getElementsByTagName("infCpl")->item(0)->nodeValue) ?
                'Inf. Contribuinte: ' .
                trim($this->pAnfavea($this->infAdic->getElementsByTagName("infCpl")->item(0)->nodeValue)) : '';
            $infPedido = $this->pGeraInformacoesDaTagCompra();
            if ($infPedido != "") {
                $textoAdic .= $infPedido;
            }
            $textoAdic .= $this->pSimpleGetValue($this->dest, "email", ' Email do Destinatário: ');
            $textoAdic .= !empty($this->infAdic->getElementsByTagName("infAdFisco")->item(0)->nodeValue) ?
                "\r\n Inf. fisco: " .
                trim($this->infAdic->getElementsByTagName("infAdFisco")->item(0)->nodeValue) : '';
            $obsCont = $this->infAdic->getElementsByTagName("obsCont");
            if (isset($obsCont)) {
                foreach ($obsCont as $obs) {
                    $campo = $obsCont->item($i)->getAttribute("xCampo");
                    $xTexto = !empty($obsCont->item($i)->getElementsByTagName("xTexto")->item(0)->nodeValue) ?
                        $obsCont->item($i)->getElementsByTagName("xTexto")->item(0)->nodeValue : '';
                    $textoAdic .= "\r\n" . $campo . ':  ' . trim($xTexto);
                    $i++;
                }
            }
        }
        //INCLUSO pela NT 2013.003 Lei da Transparência
        //verificar se a informação sobre o valor aproximado dos tributos
        //já se encontra no campo de informações adicionais
        if ($this->exibirValorTributos) {
            $flagVTT = strpos(strtolower(trim($textoAdic)), 'valor');
            $flagVTT = $flagVTT || strpos(strtolower(trim($textoAdic)), 'vl');
            $flagVTT = $flagVTT && strpos(strtolower(trim($textoAdic)), 'aprox');
            $flagVTT = $flagVTT && (strpos(strtolower(trim($textoAdic)), 'trib') ||
                    strpos(strtolower(trim($textoAdic)), 'imp'));
            $vTotTrib = $this->pSimpleGetValue($this->ICMSTot, 'vTotTrib');
            if ($vTotTrib != '' && !$flagVTT) {
                $textoAdic .= "\n Valor Aproximado dos Tributos : R$ " . number_format($vTotTrib, 2, ",", ".");
            }
        }
        //fim da alteração NT 2013.003 Lei da Transparência
        $textoAdic = str_replace(";", "\n", $textoAdic);
        return $textoAdic;
    }

    /*
     * Combina informações do XML e do protocolo para gerar um documento
     * similar ao da consulta de NFE na sefaz
     * retorna o caminho completo (url) do arquivo gerado
     */
    public function geraProtocolo($prot)
    {
        $this->prot = $prot;
        $debug = false;

        $arq_xml = $this->c->local . $this->chNFe . '-nfe.xml';
        $arq_proto_pdf = $this->c->local . $this->chNFe . '-prot.pdf';
        $arq_proto_url = $this->c->baseUrl . 'api/sefaz/' . $this->chNFe . '-prot.pdf';

        // -----------------------------------
        if ($debug) echo 'arq_xml = ' . $arq_xml;
        if ($debug) echo 'arq_proto_pdf = ' . $arq_proto_pdf;
        if ($debug) echo "\nGera protocolo: ";


        if (!file_exists($arq_xml)) {
            if ($debug) echo "Arquivo xml inexistente! Processo abortado.\n";
            return false;
        }

        $tpl = new \raelgc\view\Template(__DIR__ . '/protocolo.tpl');

        $xml = new DomDocument();
        $xml->loadXml(file_get_contents($arq_xml));


        $tpl->chNFe = $this->pFormat($this->chNFe, '##-####-##.###.###/####-##-##-###-###.###.###-###.###.###-#');
        $tpl->nNF = $this->ide->getElementsByTagName('nNF')->item(0)->nodeValue;


        $tpl->versao = $this->versao;

        $tpl->serie = $this->ide->getElementsByTagName('serie')->item(0)->nodeValue;

        //$tpl->mod = $this->ide->getElementsByTagName('mod')->item(0)->nodeValue;
        $tpl->mod = $this->pSimpleGetValue($this->ide, 'mod');

        // versão 3.1 é de um jeito, versão 2.00 é de outro. Nao sei os intermediários
        $tpl->dhEmi = $this->getdhEmi();

        // data e hora de saída pode não estar presente e pode ter formatos diferentes
        $tpl->dhSaiEnt = $this->getdhSaiEnt();

        if ($this->versao > 3) {
            $tpl->indFinal = $this->ide->getElementsByTagName('indFinal')->item(0)->nodeValue;
            $tpl->indFinal = $tpl->indFinal . ' - ' . Storage::indFinal($tpl->indFinal);

            $tpl->indPres = $this->ide->getElementsByTagName('indPres')->item(0)->nodeValue;
            $tpl->indPres = $tpl->indPres . ' - ' . Storage::indPres($tpl->indPres);

            $tpl->idDest = $this->ide->getElementsByTagName('idDest')->item(0)->nodeValue;
            $tpl->idDest = $tpl->idDest . ' - ' . Storage::idDest($tpl->idDest);
        }

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
        $tpl->destCNPJ = $this->getDestCNPJ(true);

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

        if ($this->versao >= 4) { // indPag foi removido
            $tpl->indPag = '';
        } else {
            $tpl->indPag = $this->ide->getElementsByTagName('indPag')->item(0)->nodeValue;
            $tpl->indPag = $tpl->indPag . ' - ' . Storage::indPag($tpl->indPag);
        }

        $tpl->digestValue = $this->dom->getElementsByTagName('DigestValue')->item(0)->nodeValue;


        // pega os dados do protocolo para colocar no pdf
        if (empty($this->prot) || $this->prot['status'] != 'ok') {
            return false;
        }

        //print_r($this->prot);exit;

        $tpl->situacao = mb_strtoupper(Storage::cStat($this->prot['cStat']), 'UTF-8');

        $tpl->tpAmb = mb_strtoupper(Storage::tpAmb($this->prot['tpAmb']), 'UTF-8');

        foreach ($this->prot['eventos'] as $evento) {
            $tpl->descEvento = $evento['descEvento'];
            $tpl->nProt = $evento['nProt'];
            $tpl->dhEvento = $evento['dhEvento'];
            $tpl->block('EVENTOS_BLOCK');
        }

        $tpl->dhConsulta = $this->prot['dhConsulta'];
        $tpl->infoSistema = 'Sistema DELOS/NFE '.VERSAO;

        $html = $tpl->parse();
        // Aqui termina a geração do HTML

        //Aqui começa a geração do PDF
        // tcpdf é do vendor tecnickcom mas não usa namespaces
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
        //$ret['file'] = $arq_proto_pdf;
        $ret['url'] = $arq_proto_url;

        return $ret;
    }

    protected function getdhSaiEnt()
    {
        // data e hora de saída pode não estar presente e pode ter formatos diferentes
        if ($this->versao > 3) {
            if ($this->ide->getElementsByTagName('dhSaiEnt')->item(0)) {
                $dhSaiEnt = $this->ide->getElementsByTagName('dhSaiEnt')->item(0)->nodeValue;
                return date("d/m/Y H:i:s", $this->pConvertTime($dhSaiEnt));
            } else {
                return '';

            }
        } else {
            if (empty($this->ide->getElementsByTagName('dSaiEnt')->item(0)->nodeValue)) {
                return '';
            } else {
                $dhSaiEnt = $this->ide->getElementsByTagName('dSaiEnt')->item(0)->nodeValue;
                return date("d/m/Y H:i:s", $this->pConvertTime($dhSaiEnt));
            }
        }
    }

    protected function getdhEmi()
    {
        if ($this->versao > 3) {
            return date("d/m/Y - H:i:s", $this->pConvertTime($this->ide->getElementsByTagName('dhEmi')->item(0)->nodeValue));
        } else {
            return date("d/m/Y", $this->pConvertTime($this->ide->getElementsByTagName('dEmi')->item(0)->nodeValue . 'T00:00:00'));
        }
    }

    // naverdade pode ser o cnpj ou cpf, já formatados
    protected function getDestCNPJ($formatado = true)
    {
        if (!empty($this->dest->getElementsByTagName("CNPJ")->item(0)->nodeValue)) {
            $tmp = $this->dest->getElementsByTagName("CNPJ")->item(0)->nodeValue;
            if ($formatado)
                return $this->pFormat($tmp, "###.###.###/####-##");
            else return $tmp;
        } else {
            if (!empty($this->dest->getElementsByTagName("CPF")->item(0)->nodeValue)) {
                $tmp = $this->dest->getElementsByTagName("CPF")->item(0)->nodeValue;
                if ($formatado)
                    return $this->pFormat($tmp, "###.###.###-##");
                else return $tmp;
            } else {
                return '';
            }
        }

    }
}
