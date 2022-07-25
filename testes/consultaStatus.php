<?php
error_reporting(E_ALL);
ini_set('display_errors', 'On');

require_once '../config.php';
require_once '../vendor/autoload.php';

use NFePHP\NFe\Tools;
use NFePHP\Common\Certificate;
use NFePHP\NFe\Common\Standardize;
use NFePHP\NFe\Common\Complements;

$arr = [
    "atualizacao" => "2022-07-25 10:26:00",
    "tpAmb" => 1, // producao
    "razaosocial" => "Escola de Engenharia de São Carlos",
    "cnpj" => "63025530002824",
    "siglaUF" => "SP",
    //"schemes" => "PL008i2",
    "schemes" => "PL009_V4",
    "versao" => '4.00'
];
//monta o config.json
$configJson = json_encode($arr);
//carrega o conteudo do certificado.
$content = file_get_contents($cfg['cert_file']);

try {

    $certificate = Certificate::readPfx($content,  $cfg['cert_pwd']);
    $tools = new Tools($configJson, $certificate);
    $tools->model('55');
    $uf = 'SP';
    $tpAmb = 2;
    $response = $tools->sefazStatus($uf, $tpAmb);
    //este método não requer parametros, são opcionais, se nenhum parametro for 
    //passado serão usados os contidos no $configJson
    //$response = $tools->sefazStatus();

    //você pode padronizar os dados de retorno atraves da classe abaixo
    //de forma a facilitar a extração dos dados do XML
    //NOTA: mas lembre-se que esse XML muitas vezes será necessário, 
    //      quando houver a necessidade de protocolos
    $stdCl = new Standardize($response);
    //nesse caso $std irá conter uma representação em stdClass do XML
    $std = $stdCl->toStd();
    //nesse caso o $arr irá conter uma representação em array do XML
    $arr = $stdCl->toArray();
    //nesse caso o $json irá conter uma representação em JSON do XML
    $json = $stdCl->toJson();
    
} catch (\Exception $e) {
    echo $e->getMessage();
}

echo $json;