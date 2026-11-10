# nfe-ws

Webservice para validação de NFEs

Para consumir o webservice utilize o cliente [nfe-ws-consumer](https://github.com/uspdev/nfe-ws-consumer).

Webservice utilizado para realizar consultas relacionadas à nota fiscal eletrônica - NFE:

- Verificar a estrutura do XML;
- Consultar a validade junto à SEFAZ e gerar relatório;
- Gerar DANFE;
- Armazenamento de notas fiscais eletrônicas em cumprimento à legislação específica (utilizado pelo sistema DELOS).

---

## Requisitos do Sistema

- **SO:** Linux
- **PHP:** 7.4+ ou 8.x
- **Extensões PHP:** `curl`, `dom`, `json`, `gd`, `mbstring`, `soap`, `xml`, `zip`, `openssl`
- **Servidor Web:** Apache 2.4 com `mod_rewrite` habilitado (ou servidor embutido do PHP para testes/desenvolvimento)
- **Certificado Digital:** Certificado ICP-BRASIL tipo A1 (`.pfx`) válido

---

## Configuração e Deploy

### 1. Instalação de Dependências

Instale as dependências via Composer:

```bash
composer install
```

### 2. Arquivo de Configuração (`config.php`)

Copie o arquivo de exemplo `config.sample.php` para `config.php`:

```bash
cp config.sample.php config.php
```

Edite as configurações de acordo com o seu ambiente:

#### Parâmetros do Webservice (`$cfg`)

- **`$cfg['local']`**: Diretório onde os arquivos de NF-e (XMLs, DANFEs em PDF e protocolos) são gravados (ex: `__DIR__ . '/local/recebidas/'`).
- **`$cfg['cert_file']`**: Caminho completo para o certificado digital A1 em formato `.pfx` (ex: `__DIR__ . '/local/certs/certificado.pfx'`).
- **`$cfg['cert_pwd']`**: Senha do certificado digital.
- **`$cfg['pwdFile']`**: Caminho do arquivo que armazena os hashes das API keys (ex: `__DIR__ . '/local/passwd.txt'`).
- **`$cfg['baseUrl']`**: URL base pública do webservice terminada em barra (ex: `http://servidor/nfe-ws/`). É utilizada na composição dos links de retorno para download de arquivos gerados.
- **`$cfg['debug']`**: Booleano (`true`/`false`) para exibir informações adicionais de depuração.
- **`$cfg['cache_sefaz_ttl']`**: Tempo em segundos de validade do cache local da consulta SEFAZ (evita Rejeição 656: Consumo Indevido). Padrão: `600` (10 minutos).
- **`$cfg['cache_pdf']`**: Booleano (`true`/`false`). Quando ativo, reutiliza arquivos PDF já gerados em disco (DANFE e protocolo SEFAZ), evitando re-renderização em requisições repetidas. Padrão: `true`.

#### Parâmetros da Biblioteca NFePHP (`$nfeConfig`)

- **`tpAmb`**: Tipo de ambiente (`1` para Produção, `2` para Homologação).
- **`razaosocial`**: Razão social associada ao certificado digital.
- **`cnpj`**: CNPJ da instituição/emitente.
- **`siglaUF`**: Sigla da unidade federativa (ex: `'SP'`).
- **`schemes`**: Versão dos schemas XML da SEFAZ (ex: `'PL009_V4'`).
- **`versao`**: Versão do leiaute da NF-e (ex: `'4.00'`).

### 3. Diagnóstico e Preparação do Ambiente (`setup.php`)

Execute o utilitário `setup.php` para verificar e preparar o ambiente automaticamente:

```bash
php setup.php
```

O script verifica:
1. Existência e integridade do `config.php` (incluindo validação de `baseUrl`);
2. Instalação das dependências do Composer;
3. Criação e integridade do arquivo `api/.htaccess` com regras de reescrita;
4. Criação das pastas de armazenamento e certificados;
5. Permissões de escrita no sistema de arquivos;
6. Leitura e validade do certificado digital;
7. Existência de API keys cadastradas.

---

## Autenticação e Gerenciamento de API Keys

A autenticação da API é realizada por **API key** enviada nas requisições através do header HTTP:

```http
X-API-Key: <sua-api-key>
```

> **Segurança:** O servidor armazena apenas o hash **SHA-256** das chaves geradas no arquivo configurado em `pwdFile` (`local/passwd.txt`).

O gerenciamento de credenciais é feito exclusivamente por linha de comando através do script `users.php`:

### Adicionar uma nova API key

```bash
php users.php add nome-do-cliente
```

Ao executar este comando, uma chave criptográfica de 64 caracteres hexadecimais será gerada e exibida no terminal. Guarde-a imediatamente em local seguro, pois ela não será exibida novamente.

### Listar clientes cadastrados

```bash
php users.php list
```

### Remover uma API key

```bash
php users.php del nome-do-cliente
```

---

## Configuração do Servidor Web

### Apache

A pasta `api/` contém o arquivo `.htaccess` responsável pelo roteamento do FlightPHP. Certifique-se de habilitar o módulo `mod_rewrite` e permitir sobrescrita de diretivas (`AllowOverride All`).

Exemplo de configuração com `Alias`:

```apache
Alias /nfe-ws/api /caminho/para/nfe-ws/api
<Directory /caminho/para/nfe-ws/api/>
    Options Indexes FollowSymLinks MultiViews
    AllowOverride All
    Require all granted
</Directory>
```

### Servidor Embutido do PHP (Desenvolvimento e Testes)

Para executar o servidor localmente durante desenvolvimento:

```bash
php -S 127.0.0.1:8000 -t api
```

---

## Endpoints da API

Todas as rotas da API (exceto a página inicial `GET /`) exigem autenticação via header HTTP `X-API-Key` (ou o parâmetro `?api_key=` na query string). Para acesso aos arquivos gerados (`/danfe`, `/sefaz`, `/xml`, `/prot`) em navegadores de usuários finais, recomenda-se que o sistema consumidor faça o proxy dos arquivos utilizando o método `obterArquivo()` da biblioteca cliente.

| Método | Rota | Autenticação | Descrição |
|---|---|---|---|
| `GET` | `/` | Pública | Página inicial com documentação resumida. |
| `GET` | `/status` | Requerida | Retorna a validade e dados do certificado digital instalado. |
| `POST` | `/xml` | Requerida | Envia o XML da nota fiscal (`$_POST['xml']`) ou a chave (`$_POST['chave']`) para validação, consulta na SEFAZ e geração dos PDFs (DANFE e protocolo). |
| `GET` | `/danfe/<arquivo>` | Requerida | Download do DANFE em PDF gerado. |
| `GET` | `/sefaz/<arquivo>` | Requerida | Download do relatório de protocolo da SEFAZ em PDF. |
| `GET` | `/xml/<arquivo>` | Requerida | Download do XML da nota fiscal armazenado. |
| `GET` | `/prot/<arquivo>` | Requerida | Download do XML de protocolo da SEFAZ. |

---

## Testes Automatizados

A suíte de testes de integração utiliza o PHPUnit:

```bash
./vendor/bin/phpunit
```

---

## Pessoas Envolvidas

- Masaki Kawabata Neto - kawabata em sc.usp.br
