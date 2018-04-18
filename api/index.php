<?php
require_once '../config.php';
require_once '../vendor/autoload.php';
require_once '../lib/Tools.class.php';
require_once '../lib/Protocolo.class.php';
require_once '../lib/nfe-ws.class.php';


error_reporting(E_ALL);
ini_set('display_errors', 'On');
//Flight::set('flight.log_errors', true);

Flight::route('GET /', function () {
    readfile('../lib/index.tpl');

});

Flight::route('*', function () {
    global $data, $local;
    /*    if (!preg_match('/^[0-9]{1,}$\.(pdf)/', $file)) {
            header('HTTP/1.0 404 Not Found');
            echo 'nada';
            exit;
        }*/
    if (!isset($_SERVER['PHP_AUTH_USER'])) {

        header('WWW-Authenticate: Basic realm="use this hash key to encode"');
        header('HTTP/1.0 401 Unauthorized');
        echo 'Cancel button clicked. Not logged in!';
        //print_r($_SERVER);
        exit;
    } else {
        if (!is_file('../local/passwd.txt')) {
            //header('HTTP/1.0 401 Unauthorized');
            echo 'Plz configure user before use!';
            exit();
        }
        $usrs = unserialize(file_get_contents($local . '/passwd.txt'));
        if (!isset($usrs[$_SERVER['PHP_AUTH_USER']]) or $usrs[$_SERVER['PHP_AUTH_USER']] != md5($_SERVER['PHP_AUTH_PW'])) {
            header('HTTP/1.0 401 Unauthorized');
            exit();
        }
        // aqui tem de verificar username e senha com
        // $_SERVER['PHP_AUTH_USER'] e $_SERVER['PHP_AUTH_PW']
        //echo "<p>Olá, {$_SERVER['PHP_AUTH_USER']}.</p>";
        // echo "<p>Você digitou {$_SERVER['PHP_AUTH_PW']} como sua senha.</p>";
    }
    // teste se a pasta data está ok
    if (!is_dir($data)) {
        //header('HTTP/1.0 500 Internal Server Error');
        echo 'Plz configure folder before use!';
        exit();
    }

    return true;
});

Flight::route('GET /danfe/@file', function ($file) {


    global $local;
    $arq = $local . $file;

    //global $data;
    //$arq = $data . '/danfe/' . $file;
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

Flight::route('GET /NFe/@chave:[0-9]{44}/sefaz', function ($chave) {
    global $cfg;

    $nfe = new nfe_ws($cfg);
    if (!$nfe->validaChave($chave)) {
        $ret['status'] = 'chave inválida';
        echo json_encode($ret);
        exit;
    }
    $pdf = $nfe->geraProtocolo($chave);
    echo 'ok';

});

Flight::route('GET /sefaz/@arq', function ($file) {

    global $local;
    $arq = $local . $file;


    //$nfe = new nfe_ws($cfg);

    /*if (substr($file, 0, 5) == 'Sefaz') {
        $chave = substr($file, 5, -4); // poderia verificar se a chave é numero
    }*/
    //geraProtocolo($chave);



    // aqui tem de verificar se o protocolo da sefaz é antigo, se for tem de gerar outro

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
    global $data;
    $arq = $data . '/xml/' . $file;
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

// aqui está pronto
Flight::route('POST /xml', function () {

    global $cfg;

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
        $prot = new Protocolo($cfg);
        if (!$chave = Tools::validaChNFe($_POST['chave'])) {
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
        $nfe = new nfe_ws($cfg);
        $prot = new Protocolo($cfg);

        $nfe_xml = $_POST['xml'];

        $res['xml'] = $nfe->validaEstruturaXML($nfe_xml);
        if ($res['xml']['status'] != 'ok') {
            echo json_encode($res);
            exit;
        };

        $nfe->import($nfe_xml);
        $res['chave'] = $nfe->retornaChave();
        $res['danfe'] = $nfe->geraDanfe();

        $parts = pathinfo($res['danfe']['file']);
        $arq = $parts['filename'] . '.' . $parts['extension'];
        $res['url']['danfe'] = 'api/danfe/' . $arq;

        //$res['url']['danfe'] = $res['danfe']['file'];

        // modelo de url
        // $res['url']['sefaz'] = 'api/sefaz/' . 'Sefaz' . $chave . '.pdf';

        $res['prot'] = $prot->consulta($res['chave']);

        // gera o protocolo
        $nfe->prot = $res['prot'];

        $protoArq = $nfe->geraProtocolo();
        $parts = pathinfo($protoArq);
        $arq = $parts['filename'] . '.' . $parts['extension'];
        $res['url']['sefaz'] = 'api/sefaz/' . $arq;

        echo json_encode($res);
        exit;
    }

    if (true) { //sefaz
        //require_once('../lib/nfephp/libs/NFe/ToolsNFePHP.class.php');
        $nfe = new ToolsNFePHP;
        $resp = array();
        $tpAmb = '';
        //$nfe->getProtocol('', $chave, $tpAmb, $resp);
        //print_r($resp);exit;

        if (!is_array($resp['aProt'])) {
            $msg = "Falha no retorno dos dados, retornado sem o protocolo !!";
            //throw new nfephpException($msg);
            $res['status']['sefaz'] = 'erro sefaz2';
            $res['status']['msg'] = $msg;;
            echo json_encode($res);
            exit();
        }

        if ($resp['cStat'] != '100') {
            $msg = "NF com problemas no SEFAZ!! cStat =" . $resp['cStat'] . ' - ' . $resp['xMotivo'] . "";
            $res['status']['sefaz'] = 'erro sefaz'; //aqui ainda deve gerar url do protocolo do sefaz
            $res['status']['msg'] = $msg;
        }

        if ($resp['cStat'] == '101') {
            $msg = "NF cancelada no SEFAZ!! cStat =" . $resp['cStat'] . ' - ' . $resp['xMotivo'] . "";
            //throw new nfephpException($msg);
            $res['status']['sefaz'] = 'cancelado'; //aqui ainda deve gerar url do protocolo do sefaz
            $res['status']['msg'] = $msg;
        }

        if ($resp['cStat'] == '100') {
            $res['status']['sefaz'] = 'ok';
        }

        if ($_POST['xml'] != '') {
            $res['url']['sefaz'] = 'api/sefaz/' . 'Sefaz' . $chave . '.pdf';
        } else {
            $res['status']['msg'] = 'Consultado a sefaz mas não é possivel gerar protocolo por falta do arquivo xml. Cstat = ' . $resp['cStat'];
        }
    }

    echo json_encode($res);
});

Flight::start();
?>
