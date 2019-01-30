#!/usr/bin/php
<?php

/*
apt install php-curl php-dom php-json php-gd php-mbstring php-mcrypt php-soap php-xml php-zip
 *
 * a2enmod rewrite
 */

if (php_sapi_name() != 'cli') {
    echo "Nao esta em linha de comando!";
    exit();
}

function green($str)
{
    return "\033[0;32m" . $str . "\033[0;37m";
}

function red($str)
{
    return "\033[0;31m" . $str . "\033[0;37m";
}

function le_config()
{
    global $config, $ok, $cfg;
    if (!is_file('./config.php')) {
        copy('./config.sample.php', './config.php');
        $msg = red('Erro!') . "\n";
        $msg .= "    Edite o arquivo config.php e corrija o caminho da aplicação.\n";
        $ok = false;
    } else {
        include_once './config.php';
        $cfg['url'] = parse_url($cfg['baseUrl']);
        $msg = green("OK!");
    }
    return $msg;
}

function composer()
{
    $sai = [];
    exec('composer install 2>&1', $sai);
    if ($sai[2] == 'Nothing to install or update') {
        $msg = green('OK!');
    } else {
        $msg = implode($sai, "\n");
    }
    return $msg;
}

function verifica_htaccess()
{
    global $config, $ok, $cfg;
    if ($ok) {
        $file = realpath(dirname(__FILE__)) . '/api/sample.htaccess';
        $htaccess = file($file);
        $out = '';
        $save = false;
        foreach ($htaccess as $line) {
            $parms = explode(" ", $line);
            if ($parms[0] == 'RewriteBase') {
                $line = $parms[0] . ' ' . $cfg['url']['path'] . 'api/' . "\n";
                $msg = green("OK -> ") . $cfg['url']['path'] . 'api/';
                $save = true;
            }
            $out .= $line;
        }
        if ($save == true) {
            file_put_contents(__DIR__ . '/api/.htaccess', $out);
        }
    } else {
        $msg = red("Erro!\n");
        $msg .= "    Depende do arquivo de configuração.";
    }
    return $msg;
}

function verifica_certificado()
{
    global $ok, $cfg;

    if (!is_file($cfg['cert_file'])) {
        $msg = red("Erro!") . PHP_EOL;
        $msg .= "    Voce deve ter um certificado ICP BRASIL válido no formato pfx na pasta data/certs\n";
        $msg .= "    para poder realizar consultas na Sefaz.\n";
        $ok = false;
    } else {
        $msg = green('OK');
    }

    return $msg;
}

function seta_permissao()
{
    $msg = red("Não implementado ainda");
    return $msg;
}

function verifica_pastas()
{
    global $cfg;

    if (!is_dir($cfg['local'])) {
        $msg = 'Criando ' . $cfg['local'] . ' ..';
        mkdir($cfg['local']);

    } else {
        $msg = 'Pasta já existe ..';
    }

    return $msg . green(' OK');
}

function verifica_permissao()
{
    return red('Ainda não implementado !!');
}

function verifica_usuario()
{
    global $ok, $cfg;

    if (!is_file($cfg['pwdFile'])) {
        $msg = red('Erro - sem arquivo de usuários');
        $ok = false;

    } else {
        $users = unserialize(file_get_contents($cfg['pwdFile']));
        if (empty($users)) {
            $msg = red('Erro - sem usuários cadastrados');
            $ok = false;
        }

        $msg = green('OK');
    }

    return $msg;
}

// ------------------------------------------------------
$config = '';
$cfg = [];
$ok = true;

echo '* Configurador principal NFE-WS *' . PHP_EOL . PHP_EOL;

echo "1. Lendo arquivo de configuração: ";
echo le_config() . PHP_EOL;

echo '2. Atualizando Composer: ';
echo composer() . PHP_EOL;

echo '3. Verifica o htaccess da pasta API: ';
echo verifica_htaccess() . PHP_EOL;

echo "4. Verifica a estrutura de pastas: ";
echo verifica_pastas() . PHP_EOL;

echo "5. Verifica permissão de acesso ao sistema de arquivos: ";
echo verifica_permissao() . PHP_EOL;

echo "6. Verifica o certificado: ";
echo verifica_certificado() . "\n";

echo '7. Verifica usuário: ';
echo verifica_usuario() . PHP_EOL;

echo "\n";
if ($ok) {
    echo "Já pode usar acessando http://<seu servidor>" . $cfg['url']['path'] . 'api/' . "\n\n";
} else {
    echo "** Há pendências a serem corrigidas!\n\n";
}
