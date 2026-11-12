<?php
// Use esta ferramenta na linha de comando para gerenciar API keys.

if (php_sapi_name() !== 'cli') {
    echo "Not running from command line!!\n";
    exit(1);
}

require __DIR__ . '/vendor/autoload.php';

use Uspdev\NfeWs\Models\ApiKey;

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

$apiKeyModel = new ApiKey();
$action = $argv[1];

if ($action === 'add') {
    if (!isset($argv[2]) || trim($argv[2]) === '') {
        echo "Erro: Especifique um nome para a API key!\n";
        echo "Uso: php key.php add <nome> [description]\n";
        exit(1);
    }

    $name = trim($argv[2]);
    $description = isset($argv[3]) ? trim($argv[3]) : '';

    try {
        $result = $apiKeyModel->create($name, $description);
        echo "Chave cadastrada com sucesso!\n"
            . "Nome:        {$name}\n"
            . "Descrição:   " . ($description ?: '(nenhuma)') . "\n"
            . "Criada em:   {$result['entry']['created_at']}\n"
            . "API Key:     {$result['plainKey']}\n\n"
            . "ATENÇÃO: Guarde esta API key com segurança agora; ela não poderá ser recuperada posteriormente!\n";
        exit(0);
    } catch (\Throwable $e) {
        echo "Erro: " . $e->getMessage() . PHP_EOL;
        exit(1);
    }
}

if ($action === 'revoke') {
    if (!isset($argv[2]) || trim($argv[2]) === '') {
        echo "Erro: Especifique o nome da chave a ser revogada!\n";
        echo "Uso: php key.php revoke <nome>\n";
        exit(1);
    }

    $name = trim($argv[2]);

    try {
        $key = $apiKeyModel->revoke($name);
        echo "Chave '{$name}' revogada com sucesso em {$key['revoked_at']}.\n";
        exit(0);
    } catch (\Throwable $e) {
        echo "Erro: " . $e->getMessage() . PHP_EOL;
        exit(1);
    }
}

if ($action === 'list') {
    $keys = $apiKeyModel->all();
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
