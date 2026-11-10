#!/usr/bin/env php
<?php
// Script de configuracao e verificacao de pendencias para instalacao do NFE-WS

if (php_sapi_name() != 'cli') {
    echo "Execute a partir da linha de comando!\n";
    exit();
}

chdir(__DIR__);
require_once __DIR__ . '/vendor/autoload.php';

function green($str)
{
    return "\033[0;32m" . $str . "\033[0;37m";
}

function red($str)
{
    return "\033[0;31m" . $str . "\033[0;37m";
}

function le_config(&$config)
{
    global $ok;
    if (!is_file(__DIR__ . '/.env')) {
        if (!is_file(__DIR__ . '/.env.example') || !copy(__DIR__ . '/.env.example', __DIR__ . '/.env')) {
            $ok = false;
            return red('Erro!') . "\n    Não foi possível criar o arquivo .env a partir de .env.example.";
        }
        $config = new Config();
        $ok = false;
        $msg = red('Erro!') . "\n";
        $msg .= "    O arquivo .env foi criado a partir de .env.example. Edite as configurações antes de continuar.\n";
    } else {
        $config = new Config();
        if (empty($config->baseUrl)) {
            $ok = false;
            return red('Erro!') . "\n    NFE_BASE_URL não definida no .env.";
        }
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

function verifica_certificado($config)
{
    global $ok;

    if (!is_file($config->certFile)) {
        $msg = red("Erro!") . PHP_EOL;
        $msg .= "    Voce deve ter um certificado ICP BRASIL valido em formato PFX.\n";
        $msg .= "    Caminho configurado: {$config->certFile}\n";
        $ok = false;
    } else {
        $msg = green('OK');
    }

    return $msg;
}

function verifica_pastas($config)
{
    global $ok;

    $dirs = [$config->local, dirname($config->certFile), dirname($config->pwdFile)];
    foreach (array_unique($dirs) as $dir) {
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            $ok = false;
            return red('Erro!') . " Não foi possível criar {$dir}";
        }
    }

    return green('OK');
}

function verifica_permissao($config)
{
    global $ok;

    if (empty($config->local)) {
        return red('Ainda não configurado !!');
    }

    $paths = [$config->local, dirname($config->pwdFile)];
    foreach ($paths as $path) {
        if (!is_dir($path) || (!is_writable($path))) {
            $ok = false;
            return red("Erro!") . " Sem permissão de escrita em {$path}";
        }
    }

    return green('OK');
}

function verifica_usuario($config)
{
    global $ok;

    if (!is_file($config->pwdFile)) {
        $msg = red('Erro - sem arquivo de usuários/API keys');
        $ok = false;
    } else {
        $users = @unserialize(file_get_contents($config->pwdFile));
        if (!is_array($users) || empty($users)) {
            $msg = red('Erro - sem usuários cadastrados. Execute: php users.php add <nome>');
            $ok = false;
        } else {
            $msg = green('OK');
        }
    }

    return $msg;
}

// ------------------------------------------------------
$ok = true;
$config = null;

echo '* Configurador principal NFE-WS *' . PHP_EOL . PHP_EOL;

echo "1. Lendo arquivo de configuração (.env): ";
echo le_config($config) . PHP_EOL;

echo '2. Atualizando Composer: ';
echo composer() . PHP_EOL;

echo '3. Verifica o htaccess da pasta API: ';
echo verifica_htaccess() . PHP_EOL;

echo "4. Verifica a estrutura de pastas: ";
echo verifica_pastas($config) . PHP_EOL;

echo "5. Verifica permissão de acesso ao sistema de arquivos: ";
echo verifica_permissao($config) . PHP_EOL;

echo "6. Verifica o certificado: ";
echo verifica_certificado($config) . PHP_EOL;

echo '7. Verifica usuários/API keys: ';
echo verifica_usuario($config) . PHP_EOL;

echo "\n";
if ($ok) {
    echo "Já pode usar acessando " . rtrim($config->baseUrl, '/') . "/\n\n";
} else {
    echo "** Há pendências a serem corrigidas!\n\n";
}
