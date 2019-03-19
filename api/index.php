<?php

// Aparecerá na resposta referente a sefaz
define('VERSAO', 'v2.0.6');

require_once '../config.php';
require_once '../vendor/autoload.php';
require_once '../lib/Config.class.php';
require_once '../lib/Tools.class.php';
require_once '../lib/Storage.class.php';
require_once '../lib/Protocolo.class.php';
require_once '../lib/nfe-ws.class.php';

use NFePHP\NFe\Complements;

error_reporting(E_ALL);
ini_set('display_errors', 'On');
//Flight::set('flight.log_errors', true);

// Esta rota não precisa de autenticação
Flight::route('GET /', function () {
    readfile('../lib/index.tpl');

});

Flight::route('*', function () {

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

Flight::route('GET /status', function() {
    echo 'status<br>';
    $c = new Config();
    $cert = file_get_contents($c->certFile);
    $certificado = NFePHP\Common\Certificate::readPfx($cert, $c->certPwd);
    echo $certificado->getCnpj().' - ';
    echo $certificado->getCompanyName().'<br>';
    $validTo = $certificado->getValidTo();
    echo 'Validade: ' . $validTo->format('d/m/Y').'<br>';

    if ($certificado->isExpired()) {
        echo "Certificado VENCIDO! Não é possivel mais usá-lo!";
    } else {
        echo "Certificado VÁLIDO!";
    }
    
    //print_r($certificado);
});

Flight::route('GET /@tipo:[a-z]+/@file', function ($tipo, $file) {

    switch ($tipo) {
        case 'danfe':
            $ctype = 'application/pdf';
            break;
        case 'prot':
            $ctype = 'application/xml';
            break;
        case 'sefaz':
            $ctype = 'application/pdf';
            break;
        case 'xml':
            $ctype = 'application/xml';
            break;
        default:
            //header('HTTP/1.0 404 Not Found');
            echo 'erro';
            exit;
    }
    // de fato, o tipo não é necessário pois pelo nome do arquivo dá para retornar o
    // content type adequado.

    $c = new Config();
    $arq = $c->local . $file;

    // tem de verificar o nome do arquivo, somente numeros.pdf
    //echo 'Aqui envia o arquivo pdf da danfe em: ' . $file;
    if (is_file($arq)) {
        header('Content-type:' . $ctype);
        header('Content-Disposition:attachment;filename=' . $file);
        readfile($arq);
    } else {
        header('HTTP/1.0 404 Not Found');
        echo 'Arquivo nao encontrado!';
    }
    exit;
});

Flight::route('POST /xml', function () {

    $res = array(); // nao pode usar [] no php5.3 que está no delos
    $res['status'] = array();
    $res['url'] = array();

    // tem de vir o XML ou a chave para continuar
    // posteriormente podemos aceitar upload de arquivo.
    if (isset($_POST['xml']) or isset($_POST['chave'])) {
        // continua
    } else {
        $res['status'] = 'Erro: sem dados';
        echo json_encode($res);
        exit();
    }

    // se vier a chave
    if (!empty($_POST['chave'])) {
        $prot = new Protocolo();
        if (!$chave = nfe_ws::validaChNFe($_POST['chave'])) {
            $res['status'] = 'Chave incorreta ' . strlen($_POST['chave']);
            echo json_encode($res);
            exit();
        }
        $res['chave'] = $chave;
        $res['prot'] = $prot->consulta($res['chave']);

        $res['url']['proto'] = $res['prot']['url'];
        unset($res['prot']['url']);

        $res['xml']['status'] = 'sem xml';
        echo json_encode($res);
        exit;
    }

    // se vier o xml
    if (!empty($_POST['xml'])) {
        $nfe = new nfe_ws();
        $prot = new Protocolo();

        $nfe_xml = $_POST['xml'];

        $res['xml'] = $nfe->validaEstruturaXML($nfe_xml);

        // caso tenha um erro crítico vamos parar o processo
        if ($res['xml']['status'] == 'stop') {
            echo json_encode($res);
            exit;
        }

        // como parece que é uma nfe, vamos tentar extrair todos os dados
        $res['xml'] = array_merge($res['xml'], $nfe->import($nfe_xml));

        if ($res['xml']['url']) {
            $res['url']['xml'] = $res['xml']['url'];
            unset($res['xml']['url']);
        }

        // começamos sempre pela chave
        $res['chave'] = $nfe->retornaChave();

        // pega o protocolo de consulta da sefaz
        $prot = $prot->consulta($res['chave']);
        if ($prot['url']) {
            $res['url']['proto'] = $prot['url'];
            unset($prot['url']);
        }

        // se está cancelada, vamos anexar o protocolo de cancelamento
        if ($prot['cStat'] == '101') {
            try {
                $nfe_xml_cancelada = Complements::cancelRegister($nfe_xml, $prot['raw']);
                $nfe->import($nfe_xml_cancelada); // salva a versao cancelada do XML
            } catch (Exception $e) {
                $prot['Registro do Cancelamento'] = "Erro: " . $e->getMessage();
            }
        }
        $res['prot'] = $prot;

        // vamos gerar o relatório da sefaz se o protocolo permitir
        if ($prot['status'] == 'ok') {
            $sefaz['age'] = $prot['age'];
            $sefaz['cStat'] = $prot['cStat'];
            $sefaz['xMotivo'] = $prot['xMotivo'];
            $sefaz['dhConsulta'] = $prot['dhConsulta'];
            $sefaz['tpAmb'] = $prot['tpAmb'];
            $sefaz['versao'] = 'uspdev/NFE-WS ' . VERSAO;

            $relat = $nfe->geraRelatorioSefaz($prot);
            $res['url']['sefaz'] = $relat['url'];

        } else {
            $sefaz['status'] = 'Não disponível';
            $sefaz['info'] = 'Como há problemas no protocolo, não foi possível gerar relatório de consulta à Sefaz.';
        }
        $res['sefaz'] = $sefaz;

        // vamos gerar a danfe aqui
        $danfe = $nfe->geraDanfe();
        $res['url']['danfe'] = $danfe['url'];

        $res['nfe'] = $nfe->detalhes();

        $res['status'] = 'ok';
        echo json_encode($res);
        exit;
    }
});

Flight::start();
