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

chdir(__DIR__);

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
    global $ok, $cfg;
    if (!is_file(__DIR__ . '/config.php')) {
        if (!copy(__DIR__ . '/config.sample.php', __DIR__ . '/config.php')) {
            $ok = false;
            return red('Erro!') . "\n    Não foi possível criar config.php.";
        }
        include_once __DIR__ . '/config.php';
        $cfg['url'] = parse_url($cfg['baseUrl']);
        $msg = red('Erro!') . "\n";
        $msg .= "    config.php foi criado. Edite as configuracoes locais antes de continuar.\n";
        $ok = false;
    } else {
        include_once __DIR__ . '/config.php';
        if (empty($cfg['baseUrl'])) {
            $ok = false;
            return red('Erro!') . "\n    baseUrl não definida em config.php.";
        }
        $cfg['url'] = parse_url($cfg['baseUrl']);
        $msg = green("OK!");
    }
    return $msg;
}

function composer()
{
    global $ok;
    $output = [];
    $status = 0;
    exec('composer install --no-interaction 2>&1', $output, $status);

    if ($status === 0) {
        return green('OK!');
    }

    $ok = false;
    return red('Erro!') . "\n" . implode("\n", $output);
}

function verifica_htaccess()
{
    global $ok;

    $destination = __DIR__ . '/api/.htaccess';

    if (!is_file($destination)) {
        $content = "RewriteEngine On\n\nRewriteRule .* - [E=HTTP_X_API_KEY:%{HTTP:X-API-Key}]\n\nRewriteCond %{REQUEST_FILENAME} !-f\nRewriteCond %{REQUEST_FILENAME} !-d\nRewriteRule ^(.*)$ index.php [QSA,L]\n";
        if (@file_put_contents($destination, $content) === false) {
            $ok = false;
            return red("Erro!") . " Arquivo {$destination} não encontrado e não foi possível criá-lo.";
        }
        return green("Criado com sucesso");
    }

    return green("OK");
}

function verifica_certificado()
{
    global $ok, $cfg;

    if (!is_file($cfg['cert_file'])) {
        $msg = red("Erro!") . PHP_EOL;
        $msg .= "    Voce deve ter um certificado ICP BRASIL valido em formato PFX.\n";
        $msg .= "    Caminho configurado: {$cfg['cert_file']}\n";
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
    global $cfg, $ok;

    $dirs = [$cfg['local'], dirname($cfg['cert_file']), dirname($cfg['pwdFile'])];
    foreach (array_unique($dirs) as $dir) {
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            $ok = false;
            return red('Erro!') . " Não foi possível criar {$dir}";
        }
    }

    return green('OK');
}

function verifica_permissao()
{
    global $ok, $cfg;

    if (empty($cfg['local'])) {
        return red('Ainda não configurado !!');
    }

    $paths = [$cfg['local'], dirname($cfg['pwdFile'])];
    foreach ($paths as $path) {
        if (!is_dir($path) || (!is_writable($path))) {
            $ok = false;
            return red("Erro!") . " Sem permissão de escrita em {$path}";
        }
    }

    return green('OK');
}

function verifica_usuario()
{
    global $ok, $cfg;

    if (!is_file($cfg['pwdFile'])) {
        $msg = red('Erro - sem arquivo de usuários');
        $ok = false;

    } else {
        $users = unserialize(file_get_contents($cfg['pwdFile']));
        if (!is_array($users) || empty($users)) {
            $msg = red('Erro - sem usuários cadastrados');
            $ok = false;
        } else {
            $msg = green('OK');
        }
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
    echo "Já pode usar acessando " . rtrim($cfg['baseUrl'], '/') . '/api/' . "\n\n";
} else {
    echo "** Há pendências a serem corrigidas!\n\n";
}
