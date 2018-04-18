<?php

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
    global $config, $ok;
    if (!is_file('./configure.ini') or md5_file('./configure.ini') == md5_file('./configure.ini.sample')) {
        copy('./configure.ini.sample', './configure.ini');
        $msg = red('Erro!') . "\n";
        $msg .= "    Edite o arquivo configure.ini e corrija o caminho da aplicação.\n";
        $ok = false;
    } else {
        $config = parse_ini_file('./configure.ini');
        $msg = green("OK!");
    }
    return $msg;
}

function verifica_htaccess()
{
    global $config, $ok;
    if ($ok) {
        $file = realpath(dirname(__FILE__)) . '/api/.htaccess';
        $htaccess = file($file);
        $out = '';
        $save = false;
        foreach ($htaccess as $line) {
            $parms = explode(" ", $line);
            if ($parms[0] == 'RewriteBase') {
                if ($parms[1] == $config['apppath'] . '/api/' . "\n") {
                    $msg = green("OK -> ") . $config['apppath'] . '/api/';
                } else {
                    $line = $parms[0] . ' ' . $config['apppath'] . '/api/' . "\n";
                    $msg = green("corrigido OK-> ") . $config['apppath'] . '/api/';
                    $save = true;
                }
            }
            $out .= $line;
        }
        if ($save == true) {
            file_put_contents($file, $out);
        }
    } else {
        $msg = red("Erro!\n");
        $msg .= "    Depende do arquivo de configuração.";
    }
    return $msg;
}

function verifica_certificado()
{
    global $ok;
    $msg = red("Erro!") . PHP_EOL;
    $msg .= "    Voce deve ter um certificado ICP BRASIL válido no formato pfx na pasta data/certs\n";
    $msg .= "    para poder realizar consultas na Sefaz.\n";
    $ok = true;
    return $msg;
}

function seta_permissao()
{
    $msg = red("Não implementado ainda");
    return $msg;
}

// ------------------------------------------------------
$config = '';
$ok = true;

echo '* Configurador principal *' . PHP_EOL;

echo "lendo arquivo de configuração: ";
echo le_config() . "\n";

echo 'Verifica o htaccess da pasta API: ';
echo verifica_htaccess() . "\n";

echo "Verifica a estrutura de pastas: ";
echo red("Não implementado ainda") . PHP_EOL;


echo "Verifica o certificado: ";
echo verifica_certificado() . "\n";


echo "\n";
if ($ok) {
    echo "Já pode usar acessando http://<seu servidor>/" . $config['apppath'] . '/api/' . "\n\n";
} else {
    echo "** Há pendências a serem corrigidas!\n\n";
}

?>
