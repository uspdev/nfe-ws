<?php

error_reporting(E_ALL);
ini_set('display_errors', 'On');
require_once '../vendor/autoload.php';

use NFePHP\NFe\Tools;
use NFePHP\Common\Certificate;
use NFePHP\NFe\Common\Standardize;
use NFePHP\NFe\Common\Complements;


$arr = [
    "atualizacao" => "2016-11-03 18:01:21",
    "tpAmb" => 2,
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
$content = file_get_contents('../local/certs/certificado2018-2019.pfx');


$nfe = file_get_contents('modelo-nfe2.xml');


$chave = '35180419257637000196550010000646311415652035';
$chave = '35180310205416000108550000000026171000026174';

$tools = new Tools($configJson, Certificate::readPfx($content, 'masaki2008'));

use NFePHP\Common\Signer;

if (Signer::isSigned($nfe)){
    $signed = true;
}
    ;
$dom = new \DOMDocument('1.0', 'utf-8');
$dom->formatOutput = false;
$dom->preserveWhiteSpace = false;
$dom->loadXML($nfe);
//verifica a validade no webservice da SEFAZ
$tpAmb = $dom->getElementsByTagName('tpAmb')->item(0)->nodeValue;  // 1 é produção
echo 'ambiente: ' . $tpAmb . PHP_EOL;
$infNFe = $dom->getElementsByTagName('infNFe')->item(0);
//echo $infNFe.PHP_EOL;
$chNFe = preg_replace('/[^0-9]/', '', $infNFe->getAttribute("Id"));
echo 'Chave: ' . $chNFe . PHP_EOL;

$protocol = $dom->getElementsByTagName('nProt')->item(0)->nodeValue;
echo 'protocol: ' . $protocol . PHP_EOL;

$digval = $dom->getElementsByTagName('DigestValue')->item(0)->nodeValue;
echo 'digval: ' . $digval . PHP_EOL;
//consulta a NFe
//$response = $tools->sefazConsultaChave($chNFe, $tpAmb);
//file_put_contents($chNFe.'-prot.xml',$response);
//exit;


//echo $response.PHP_EOL;

$response = file_get_contents('35180303846817000173550010000047001010008144-prot.xml');

$ret = new \DOMDocument('1.0', 'UTF-8');
$ret->preserveWhiteSpace = false;
$ret->formatOutput = false;
$ret->loadXML($response);
$retProt = $ret->getElementsByTagName('protNFe')->item(0);
if (!isset($retProt)) {
    echo 'O documento de resposta não contêm o NODE "protNFe".';
}
 if(!$infProt = $ret->getElementsByTagName('infProt')->item(0)) {
    echo 'sem infprot';exit;
 };
echo 'infprot: ';
//print_r($infProt);
//.PHP_EOL;

$cStat = $infProt->getElementsByTagName('cStat')->item(0)->nodeValue;

echo 'cstat; '.$cStat.PHP_EOL;

$xMotivo = $infProt->getElementsByTagName('xMotivo')->item(0)->nodeValue;
echo 'Motivo: '.$xMotivo.PHP_EOL;

$dig = $infProt->getElementsByTagName("digVal")->item(0);
$digProt = '000';
if (isset($dig)) {
    $digProt = $dig->nodeValue;
}
$chProt = $infProt->getElementsByTagName("chNFe")->item(0)->nodeValue;
$nProt = $infProt->getElementsByTagName("nProt")->item(0)->nodeValue;
if ($protocol == $nProt
    && $digval == $digProt
    && $chNFe == $chProt
)
exit;

$nferet = $tools->sefazValidate($nfe);
print_r($nferet);
exit;

$prot = $tools->sefazConsultaChave($chave);
file_put_contents($chave . '-prot.xml', $prot);
print_r($prot);