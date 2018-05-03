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
require 'lib/Config.class.php';

if ($argc < 2) {
    echo "Sintaxe:\n";
    echo "php users.php add username <password>\n";
    echo "php users.php list\n";
    echo "php users.php del username\n";
    exit();
}
$c = new Config();

class Users
{
    function __construct()
    {
        $this->c = new Config();
        if (is_file($this->c->pwdFile)) {
            $this->users = unserialize(file_get_contents($this->c->pwdFile));
        } else {
            $this->users = [];
        }
    }

    public function add($usr, $pwd)
    {
        if ($this->isUser($usr)) {
            return "Usuário já cadastrado";
        }
        $this->users[$usr] = md5($pwd);
        return $this->save();
    }

    public function del($usr)
    {
        if (!isset($this->users[$usr])) {
            return "Usuário não encontrado";
        }
        unset($this->users[$usr]);
        return $this->save();
    }

    public function list()
    {
        return $this->users;
    }

    public function isUser($usr)
    {
        if (isset($this->users[$usr])) {
            return true;
        }
        return false;
    }

    protected function save()
    {
        $res = file_put_contents($this->c->pwdFile, serialize($this->users));
        if ($res !== false) {
            return 'Salvo com sucesso.';
        }
        return 'Falha.';
    }

}

$users = new Users;

if ($argv[1] == 'add') {
    $usr = $argv[2];
    if ($users->isUser($usr)) {
        echo 'Usuário já existe.' . PHP_EOL;
        exit;
    }

    $pwd = isset($argv[3]) ? $argv[3] : '';

    while (!$pwd) {
        echo "Senha: ";
        system('stty -echo');
        $pwd = trim(fgets(STDIN));
        system('stty echo');
        echo PHP_EOL; // add a new line since the users CR didn't echo

        //$pwd = readline('Digite a senha para o usuário ' . $usr . ': ');

        if (strlen($pwd) < 5) {
            echo "Senha muito curta.\n";
            $pwd = '';
        }
    }

    echo $users->add($usr, $pwd) . PHP_EOL;
    exit();

}

if ($argv[1] == 'list') {
    print_r($users->list());
    exit;
}

if ($argv[1] == 'del') {
    if (!isset($argv[2])) {
        echo "Especifique um usuário!" . PHP_EOL;
        exit();
    }
    $usr = $argv[2];
    echo $users->del($usr) . PHP_EOL;
    exit;
}

echo "Comando " . $argv[1] . " desconhecido." . PHP_EOL;
?>
