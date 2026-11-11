<?php
// Use esta ferramenta na linha de comando para gerenciar API keys.

if (php_sapi_name() !== 'cli') {
    echo "Not running from command line!!\n";
    exit(1);
}

require __DIR__ . '/vendor/autoload.php';

use Uspdev\NfeWs\Config;

function showSyntax(): void
{
    echo "Sintaxe:\n";
    echo "  php key.php add <nome> [description]\n";
    echo "  php key.php revoke <nome>\n";
    echo "  php key.php list\n";
    exit(1);
}

if ($argc < 2) {
    showSyntax();
}

class KeyManager
{
    private Config $config;
    /** @var array<int, array{name: string, hash: string, description: string, created_at: string, revoked_at: ?string}> */
    private array $keys = [];

    public function __construct()
    {
        $this->config = new Config();
        $this->load();
    }

    private function load(): void
    {
        if (is_file($this->config->pwdFile)) {
            $content = file_get_contents($this->config->pwdFile);
            $decoded = json_decode($content, true);
            if (is_array($decoded)) {
                $this->keys = $decoded;
                return;
            }
        }
        $this->keys = [];
    }

    public function findIndex(string $name): ?int
    {
        foreach ($this->keys as $index => $key) {
            if (isset($key['name']) && strcasecmp($key['name'], $name) === 0) {
                return $index;
            }
        }
        return null;
    }

    public function exists(string $name): bool
    {
        return $this->findIndex($name) !== null;
    }

    public function add(string $name, string $description = ''): string
    {
        if ($this->exists($name)) {
            return "Erro: Já existe uma chave cadastrada com o nome '{$name}'.";
        }

        $apiKey = bin2hex(random_bytes(32));
        $hash = hash('sha256', $apiKey);

        $newEntry = [
            'name'        => $name,
            'hash'        => $hash,
            'description' => $description,
            'created_at'  => date('c'),
            'revoked_at'  => null,
        ];

        $this->keys[] = $newEntry;

        if (!$this->save()) {
            array_pop($this->keys);
            return "Erro: Falha ao gravar no arquivo {$this->config->pwdFile}.";
        }

        return "Chave cadastrada com sucesso!\n"
            . "Nome:        {$name}\n"
            . "Descrição:   " . ($description ?: '(nenhuma)') . "\n"
            . "Criada em:   {$newEntry['created_at']}\n"
            . "API Key:     {$apiKey}\n\n"
            . "ATENÇÃO: Guarde esta API key com segurança agora; ela não poderá ser recuperada posteriormente!";
    }

    public function revoke(string $name): string
    {
        $index = $this->findIndex($name);
        if ($index === null) {
            return "Erro: Nenhuma chave encontrada com o nome '{$name}'.";
        }

        if (!empty($this->keys[$index]['revoked_at'])) {
            return "Aviso: A chave '{$name}' já estava revogada desde {$this->keys[$index]['revoked_at']}.";
        }

        $this->keys[$index]['revoked_at'] = date('c');

        if (!$this->save()) {
            return "Erro: Falha ao salvar a revogação no arquivo {$this->config->pwdFile}.";
        }

        return "Chave '{$name}' revogada com sucesso em {$this->keys[$index]['revoked_at']}.";
    }

    /**
     * @return array<int, array{name: string, hash: string, description: string, created_at: string, revoked_at: ?string}>
     */
    public function list(): array
    {
        return $this->keys;
    }

    private function save(): bool
    {
        $dir = dirname($this->config->pwdFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $json = json_encode($this->keys, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            return false;
        }

        return file_put_contents($this->config->pwdFile, $json . PHP_EOL, LOCK_EX) !== false;
    }
}

$manager = new KeyManager();
$action = $argv[1];

if ($action === 'add') {
    if (!isset($argv[2]) || trim($argv[2]) === '') {
        echo "Erro: Especifique um nome para a API key!\n";
        echo "Uso: php key.php add <nome> [description]\n";
        exit(1);
    }

    $name = trim($argv[2]);
    $description = isset($argv[3]) ? trim($argv[3]) : '';
    echo $manager->add($name, $description) . PHP_EOL;
    exit(0);
}

if ($action === 'revoke') {
    if (!isset($argv[2]) || trim($argv[2]) === '') {
        echo "Erro: Especifique o nome da chave a ser revogada!\n";
        echo "Uso: php key.php revoke <nome>\n";
        exit(1);
    }

    $name = trim($argv[2]);
    echo $manager->revoke($name) . PHP_EOL;
    exit(0);
}

if ($action === 'list') {
    $keys = $manager->list();
    if (empty($keys)) {
        echo "Nenhuma API key cadastrada.\n";
        exit(0);
    }

    echo sprintf(
        "%-20s | %-30s | %-25s | %s\n",
        'NOME',
        'DESCRIÇÃO',
        'CRIADO EM',
        'STATUS'
    );
    echo str_repeat('-', 100) . PHP_EOL;

    foreach ($keys as $k) {
        $name = $k['name'] ?? '-';
        $desc = $k['description'] ?? '-';
        if (mb_strlen($desc) > 30) {
            $desc = mb_substr($desc, 0, 27) . '...';
        }
        $created = $k['created_at'] ?? '-';
        $status = empty($k['revoked_at']) ? 'Ativa' : 'Revogada em ' . $k['revoked_at'];

        echo sprintf(
            "%-20s | %-30s | %-25s | %s\n",
            $name,
            $desc,
            $created,
            $status
        );
    }
    echo PHP_EOL;
    exit(0);
}

echo "Comando '{$action}' desconhecido.\n\n";
showSyntax();
