<?php
// esta senha deve ser utilizada em linha de comando no servidor
// para gerenciar os usuários e senhas que acessam o webservice.
// em principio haverão poucos usuarios, que são os sistemas clientes.
// os usuarios propriamente deverao ser autenticados na aplicação e nao na api
// gera par se senha e usuário e salva no arquivo

if (php_sapi_name() !== 'cli') {
    echo "Not running from command line!!";
    exit();
}

require 'config.php';

if ($argc < 2) {
    echo "Sintaxe:\n";
    echo "php passwd.php add username <password>\n";
    echo "php passwd.php list\n";
    echo "php passwd.php del username\n";
    exit();
}
$c = new Config();
function getcfg()
{
    global $c;

    if (is_file($c->pwdFile)) {
        return unserialize(file_get_contents($c->pwdFile));
    } else {
        return array();
    }
}

if ($argv[1] == 'add') {
    global $local;
    $usr = $argv[2];

    if (isset($argv[3]))
        $pwd = $argv[3];
    else
        $pwd = '';

    while (!$pwd) {
        $pwd = readline('Digite a senha para o usuário ' . $usr . ': ');

        if (strlen($pwd) < 5) {
            echo "Senha muito curta.\n";
            $pwd = '';
        }
    }

    $cfg = getcfg();
    $cfg[$usr] = md5($pwd);
    file_put_contents($c->pwdFile, serialize($cfg));
    echo "Usuário registrado com sucesso!\n";
    exit();
}

if ($argv[1] == 'list') {
    $cfg = getcfg();
    print_r($cfg);
}
if ($argv[1] == 'del') {
    if (!isset($argv[2])) {
        echo "Especifique um usuário!\n";
        exit();
    }
    $usr = $argv[2];
    $cfg = getcfg();
    if (!isset($cfg[$usr])) {
        echo "Usuário não encontrado\n";
        exit();
    }
    unset($cfg[$usr]);
    file_put_contents('passwd.txt', serialize($cfg));
    echo "usuário " . $usr . " apagado.\n";
}
?>