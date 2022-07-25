<?php
error_reporting(E_ALL);
ini_set('display_errors', 'On');

require_once '../config.php';
require_once '../vendor/autoload.php';

use NFePHP\NFe\Tools;
use NFePHP\Common\Certificate;
use NFePHP\NFe\Common\Standardize;
use NFePHP\NFe\Common\Complements;


echo "OS: " . PHP_OS . "\n";
echo "uname: " . php_uname() . "\n";
echo "PHP version: " . phpversion() . "\n";
$curl_version = curl_version();
echo "curl version: " . $curl_version["version"] . "\n";
echo "SSL version: " . $curl_version["ssl_version"] . "\n";
echo "SSL version number: " . $curl_version["ssl_version_number"] . "\n";
echo "OPENSSL_VERSION_NUMBER: " . dechex(OPENSSL_VERSION_NUMBER) . "\n";

$arr = [
    "atualizacao" => "2022-07-25 10:26:00",
    "tpAmb" => 1, // producao
    "razaosocial" => "Escola de Engenharia de São Carlos",
    "cnpj" => "63025530002824",
    "siglaUF" => "SP",
    "schemes" => "PL009_V4",
    "versao" => '4.00'
];
$configJson = json_encode($arr);

try {

    $certificate = Certificate::readPfx(file_get_contents($cfg['cert_file']),  $cfg['cert_pwd']);
    $tools = new Tools($configJson, $certificate);

    $soap = new NFePHP\Common\Soap\SoapCurl($certificate);
    $soap->protocol($soap::SSL_TLSV1_2);
    $tools->loadSoapClass($soap);

    $tools->model('55');
    $response = $tools->sefazStatus();

    $stdCl = new Standardize($response);
    $json = $stdCl->toJson();
} catch (\Exception $e) {
    echo $e->getMessage();
    exit;
}

echo 'json: ', $json, "\n";