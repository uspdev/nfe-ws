<?php

namespace Uspdev\NfeWs\Models;

use DomainException;
use InvalidArgumentException;
use RuntimeException;
use Uspdev\NfeWs\Config;

class ApiKey
{
    private string $filePath;
    /** @var array<int, array{name: string, hash: string, description: string, created_at: string, revoked_at: ?string}> */
    private array $keys = [];

    public function __construct(?Config $config = null, ?string $filePath = null)
    {
        $config = $config ?? Config::getInstance();
        $this->filePath = $filePath ?? $config->pwdFile;
        $this->load();
    }

    /**
     * Carrega as credenciais a partir do arquivo JSON
     */
    public function load(): array
    {
        if (is_file($this->filePath)) {
            $content = file_get_contents($this->filePath);
            $decoded = json_decode($content, true);
            if (is_array($decoded)) {
                $this->keys = $decoded;
                return $this->keys;
            }
        }
        $this->keys = [];
        return $this->keys;
    }

    /**
     * Retorna todas as chaves cadastradas
     *
     * @return array<int, array{name: string, hash: string, description: string, created_at: string, revoked_at: ?string}>
     */
    public function all(): array
    {
        return $this->keys;
    }

    /**
     * Localiza o índice da chave pelo nome (case-insensitive)
     */
    public function findIndex(string $name): ?int
    {
        foreach ($this->keys as $index => $key) {
            if (isset($key['name']) && strcasecmp($key['name'], $name) === 0) {
                return $index;
            }
        }
        return null;
    }

    /**
     * Localiza o registro da chave pelo nome
     */
    public function findByName(string $name): ?array
    {
        $index = $this->findIndex($name);
        return $index !== null ? $this->keys[$index] : null;
    }

    /**
     * Verifica se existe chave com o nome fornecido
     */
    public function exists(string $name): bool
    {
        return $this->findIndex($name) !== null;
    }

    /**
     * Valida a integridade física e de formato do repositório de credenciais
     *
     * @throws RuntimeException
     */
    public function validateStorage(): void
    {
        if (!is_file($this->filePath)) {
            throw new RuntimeException('Este webservice ainda não foi configurado!', 500);
        }

        $content = file_get_contents($this->filePath);
        $decoded = json_decode($content, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Formato do arquivo de credenciais inválido!', 500);
        }
    }

    /**
     * Valida e autentica uma API key em texto plano
     *
     * @param string $plainKey
     * @return array
     * @throws RuntimeException Se o arquivo de credenciais não existir ou estiver corrompido
     * @throws DomainException Se a credencial for inválida ou estiver revogada
     */
    public function authenticate(string $plainKey): array
    {
        $this->validateStorage();
        $this->load();

        $entry = $this->findByPlainKey($plainKey);
        if (!$entry) {
            throw new DomainException('Credenciais inválidas', 401);
        }

        if ($this->isRevoked($entry)) {
            throw new DomainException('Credenciais revogadas', 401);
        }

        return $entry;
    }

    /**
     * Verifica se uma entrada de credencial está revogada
     */
    public function isRevoked(array $entry): bool
    {
        return !empty($entry['revoked_at']);
    }

    /**
     * Localiza a chave correspondente a uma API Key em texto plano
     */
    public function findByPlainKey(string $plainKey): ?array
    {
        if (trim($plainKey) === '') {
            return null;
        }

        $hash = hash('sha256', $plainKey);

        foreach ($this->keys as $entry) {
            if (is_array($entry) && isset($entry['hash']) && hash_equals($entry['hash'], $hash)) {
                return $entry;
            }
        }

        return null;
    }

    /**
     * Cria e persiste uma nova API Key
     *
     * @param string $name
     * @param string $description
     * @return array{entry: array, plainKey: string}
     * @throws DomainException
     * @throws RuntimeException
     */
    public function create(string $name, string $description = ''): array
    {
        $name = trim($name);
        if ($name === '') {
            throw new InvalidArgumentException('O nome da API key não pode ser vazio.');
        }

        if ($this->exists($name)) {
            throw new DomainException("Já existe uma chave cadastrada com o nome '{$name}'.");
        }

        $plainKey = bin2hex(random_bytes(32));
        $hash = hash('sha256', $plainKey);

        $entry = [
            'name'        => $name,
            'hash'        => $hash,
            'description' => trim($description),
            'created_at'  => date('c'),
            'revoked_at'  => null,
        ];

        $this->keys[] = $entry;

        if (!$this->save()) {
            array_pop($this->keys);
            throw new RuntimeException("Falha ao gravar no arquivo {$this->filePath}.");
        }

        return [
            'entry'    => $entry,
            'plainKey' => $plainKey,
        ];
    }

    /**
     * Marca uma chave como revogada
     *
     * @param string $name
     * @return array
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public function revoke(string $name): array
    {
        $index = $this->findIndex($name);
        if ($index === null) {
            throw new InvalidArgumentException("Nenhuma chave encontrada com o nome '{$name}'.");
        }

        if (empty($this->keys[$index]['revoked_at'])) {
            $this->keys[$index]['revoked_at'] = date('c');

            if (!$this->save()) {
                throw new RuntimeException("Falha ao gravar a revogação no arquivo {$this->filePath}.");
            }
        }

        return $this->keys[$index];
    }

    /**
     * Remove fisicamente uma chave do repositório
     */
    public function delete(string $name): bool
    {
        $index = $this->findIndex($name);
        if ($index === null) {
            return false;
        }

        array_splice($this->keys, $index, 1);
        return $this->save();
    }

    /**
     * Salva o conteúdo em disco com bloqueio exclusivo
     */
    private function save(): bool
    {
        $dir = dirname($this->filePath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $json = json_encode($this->keys, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            return false;
        }

        return file_put_contents($this->filePath, $json . PHP_EOL, LOCK_EX) !== false;
    }
}
