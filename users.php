<?php
// Use esta ferramenta na linha de comando para gerenciar API keys.

if (php_sapi_name() !== 'cli') {
    echo "Not running from command line!!";
    exit();
}

require 'config.php';
require 'vendor/autoload.php';

if ($argc < 2) {
    echo "Sintaxe:\n";
    echo "php users.php add nome\n";
    echo "php users.php list\n";
    echo "php users.php del nome\n";
    exit();
}
$c = new Config();

#[\AllowDynamicProperties]
class Users
{
    public $c;
    public $users = [];

    function __construct()
    {
        $this->c = new Config();
        if (is_file($this->c->pwdFile)) {
            $this->users = unserialize(file_get_contents($this->c->pwdFile));
        } else {
            $this->users = [];
        }
    }

    public function add($usr)
    {
        if ($this->isUser($usr)) {
            return "Usuário já cadastrado";
        }
        $apiKey = bin2hex(random_bytes(32));
        $this->users[$usr] = hash('sha256', $apiKey);
        $result = $this->save();
        if ($result !== 'Salvo com sucesso.') {
            unset($this->users[$usr]);
            return $result;
        }
        return $result . PHP_EOL . "API key (guarde-a agora; ela não será exibida novamente): " . $apiKey;
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
    if (!isset($argv[2]) || trim($argv[2]) === '') {
        echo "Especifique um nome para a API key!" . PHP_EOL;
        exit;
    }
    $usr = $argv[2];
    if ($users->isUser($usr)) {
        echo 'Usuário já existe.' . PHP_EOL;
        exit;
    }
    echo $users->add($usr) . PHP_EOL;
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
