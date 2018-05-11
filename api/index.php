<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: authorization');

if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'])) {
    echo 'OK';
    exit;
}
if (!isset($_SERVER['PHP_AUTH_USER'])) {
    header('HTTP/1.0 401 Unauthorized');
    header('WWW-Authenticate: Basic realm="use this hash key to encode"');
    echo 'Você deve digitar um login e senha válidos para acessar este recurso\n';
    exit;
}

require_once '../config.php';
require_once '../vendor/autoload.php';
require_once '../lib/Config.class.php';
require_once '../lib/Tools.class.php';
require_once '../lib/Storage.class.php';
require_once '../lib/Protocolo.class.php';
require_once '../lib/nfe-ws.class.php';

error_reporting(E_ALL);
ini_set('display_errors', 'On');
//Flight::set('flight.log_errors', true);

Flight::route('GET /', function () {
    readfile('../lib/index.tpl');

});

Flight::route('*', function () {

    $c = new Config;

    if (!is_file($c->pwdFile)) {
        //header('HTTP/1.0 401 Unauthorized');
        echo 'Este webservice ainda não foi configurado!';
        exit();
    }
    $usrs = unserialize(file_get_contents($c->pwdFile));
    if (!isset($usrs[$_SERVER['PHP_AUTH_USER']]) or $usrs[$_SERVER['PHP_AUTH_USER']] != md5($_SERVER['PHP_AUTH_PW'])) {
        header('HTTP/1.0 401 Unauthorized');
        echo 'Credenciais inválidas';
        exit();
    }

    // testa se a pasta data está ok
    if (!is_dir($c->local)) {
        //header('HTTP/1.0 500 Internal Server Error');
        echo 'A pasta de dados não está configurada!';
        exit();
    }

    return true;
});

// está desativado??
/*Flight::route('GET /NFe/@chave:[0-9]{44}/sefaz', function ($chave) {

    $nfe = new nfe_ws();
    if (!$nfe->validaChave($chave)) {
        $ret['status'] = 'chave inválida';
        echo json_encode($ret);
        exit;
    }
    $pdf = $nfe->geraProtocolo($chave);
    echo 'ok';

});*/

Flight::route('GET /danfe/@file', function ($file) {

    $c = new Config();
    $arq = $c->local . $file;

    // tem de verificar o nome do arquivo, somente numeros.pdf
    //echo 'Aqui envia o arquivo pdf da danfe em: ' . $file;
    if (is_file($arq)) {
        header("Content-type:application/pdf");
        header("Content-Disposition:attachment;filename=$file");
        readfile($arq);
    } else {
        header('HTTP/1.0 404 Not Found');
        echo 'Arquivo nao encontrado!';
        exit();
    }
});

Flight::route('GET /sefaz/@arq', function ($file) {

    $c = new Config();
    $arq = $c->local . $file;

    if (is_file($arq)) {
        header('Content-Type: application/pdf; charset=utf-8');
        header("Content-Disposition:attachment;filename=$file");
        readfile($arq);
    } else {
        header('HTTP/1.0 404 Not Found');
        echo 'Arquivo nao encontrado!';
        exit();
    }
});

Flight::route('GET /xml/@arq', function ($file) {

    $c = new Config();
    $arq = $c->local . $file;

    if (is_file($arq)) {
        header('Content-Type: application/xml; charset=utf-8');
        header("Content-Disposition:attachment;filename=$file");
        readfile($arq);
    } else {
        header('HTTP/1.0 404 Not Found');
        echo 'Arquivo nao encontrado!';
        exit();
    }
});

Flight::route('POST /xml', function () {

    $res = array(); // nao pode usar [] no php5.3 que está no delos
    $res['status'] = array();
    $res['url'] = array();

    if (!isset($_POST['xml']) or !isset($_POST['chave'])) {
        $res['status']['xml'] = 'erro post';
        $res['status']['msg'] = 'No post data';
        echo json_encode($res);
        exit();
    }

    // se vier somente a chave
    if ($_POST['chave'] != '') {
        $prot = new Protocolo();
        if (!$chave = nfe_ws::validaChNFe($_POST['chave'])) {
            $res['status'] = 'Chave incorreta ' . strlen($_POST['chave']);
            echo json_encode($res);
            exit();
        }
        $res['chave'] = $chave;
        $res['prot'] = $prot->consulta($res['chave']);
        $res['xml'] = 'sem xml';
        echo json_encode($res);
        exit;
    }

    // se vier o xml
    if ($_POST['xml'] != '') {
        $nfe = new nfe_ws();
        $prot = new Protocolo();

        $nfe_xml = $_POST['xml'];

        $res['xml'] = $nfe->validaEstruturaXML($nfe_xml);
        if ($res['xml']['status'] != 'ok') {
            echo json_encode($res);
            exit;
        };

        $res['xml2'] = $nfe->import($nfe_xml);
        if ($res['xml2']['status'] != 'ok') {
            echo json_encode($res);
            exit;
        };
        $res['url']['xml'] = $res['xml2']['url'];

        $res['chave'] = $nfe->retornaChave();
        $res['danfe'] = $nfe->geraDanfe();

        $res['url']['danfe'] = $res['danfe']['url'];

        $prot = $prot->consulta($res['chave']);

        // gera o protocolo
        $res['prot'] = $nfe->geraProtocolo($prot);

        // vamos mostrar algumas informações para o usuário
        $res['prot']['age'] = $prot['age'];
        $res['prot']['cStat'] = $prot['cStat'];
        $res['prot']['xMotivo'] = $prot['xMotivo'];
        $res['prot']['dhConsulta'] = $prot['dhConsulta'];
        $res['prot']['tpAmb'] = $prot['tpAmb'];

        $res['url']['sefaz'] = $res['prot']['url'];

        $res['detalhes'] = $nfe->detalhes();


        $res['status'] = 'ok';
        echo json_encode($res);
        exit;
    }
});

Flight::start();
?>
