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

### 2. Arquivo de Configuração (`.env`)

Copie o arquivo de exemplo `.env.example` para `.env`:

```bash
cp .env.example .env
```

Edite as configurações de acordo com o seu ambiente:

#### Parâmetros do Webservice (`.env`)

- **`NFE_LOCAL_PATH`**: Diretório onde os arquivos de NF-e (XMLs, DANFEs em PDF e protocolos) são gravados (ex: `local/recebidas/`).
- **`NFE_CERT_FILE`**: Caminho para o certificado digital A1 em formato `.pfx` (ex: `local/certs/certificado.pfx`).
- **`NFE_CERT_PASSWORD`**: Senha do certificado digital.
- **`NFE_PASSWD_FILE`**: Caminho do arquivo que armazena os registros e hashes das API keys em formato JSON (ex: `local/passwd.json`).
- **`NFE_BASE_URL`**: URL base pública do webservice terminada em barra (ex: `https://servidor/nfe-ws/api/`). É utilizada na composição dos links de retorno para download de arquivos gerados.
- **`NFE_DEBUG`**: Booleano (`true`/`false`) para exibir informações adicionais de depuração.
- **`NFE_CACHE_SEFAZ_TTL`**: Tempo em segundos de validade do cache local da consulta SEFAZ (evita Rejeição 656: Consumo Indevido). Padrão: `600` (10 minutos).
- **`NFE_CACHE_PDF`**: Booleano (`true`/`false`). Quando ativo, reutiliza arquivos PDF já gerados em disco (DANFE e protocolo SEFAZ), evitando re-renderização em requisições repetidas. Padrão: `true`.

#### Parâmetros da Biblioteca NFePHP

- **`NFE_TP_AMB`**: Tipo de ambiente (`1` para Produção, `2` para Homologação).
- **`NFE_RAZAO_SOCIAL`**: Razão social associada ao certificado digital.
- **`NFE_CNPJ`**: CNPJ da instituição/emitente.
- **`NFE_SIGLA_UF`**: Sigla da unidade federativa (ex: `'SP'`).
- **`NFE_SCHEMES`**: Versão dos schemas XML da SEFAZ (ex: `'PL009_V4'`).
- **`NFE_VERSAO`**: Versão do leiaute da NF-e (ex: `'4.00'`).

### 3. Diagnóstico e Preparação do Ambiente (`setup.php`)

Execute o utilitário `setup.php` para verificar e preparar o ambiente automaticamente:

```bash
php setup.php
```

O script verifica:
1. Existência e integridade do `.env` (incluindo validação de `NFE_BASE_URL`);
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

> **Segurança e Auditoria:** O servidor armazena apenas o hash **SHA-256** das chaves geradas no arquivo configurado em `pwdFile` (`local/passwd.json`).
> Além disso, todas as tentativas de autenticação (`AUTH`) e comunicações com a SEFAZ (`SEFAZ`: online com tempo de resposta, cache ou erro) são registradas em logs diários em `local/logs/access-YYYY-MM-DD.log`.

O gerenciamento de credenciais é feito exclusivamente por linha de comando através do script `key.php`:

### Adicionar uma nova API key

```bash
php key.php add nome-do-cliente "Descrição opcional"
```

Ao executar este comando, uma chave criptográfica de 64 caracteres hexadecimais será gerada e exibida no terminal. Guarde-a imediatamente em local seguro, pois ela não será exibida novamente.

### Listar API keys cadastradas

```bash
php key.php list
```

Exibe o nome, descrição, data de criação e status (Ativa ou Revogada).

### Revogar uma API key

```bash
php key.php revoke nome-do-cliente
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

---

## Endpoints da API

Todas as rotas da API (exceto a página inicial `GET /`) exigem autenticação via header HTTP `X-API-Key` (ou o parâmetro `?api_key=` na query string). Os endpoints principais são versionados sob `/v1` (ex.: `/v1/status`, `/v1/xml`), mantendo `/status` sem prefixo apenas para compatibilidade retroativa. Para acesso aos arquivos gerados (`/v1/danfe`, `/v1/sefaz`, `/v1/xml`, `/v1/prot`) em navegadores de usuários finais, recomenda-se que o sistema consumidor faça o proxy dos arquivos utilizando o método `obterArquivo()` da biblioteca cliente.

| Método | Rota | Autenticação | Descrição |
|---|---|---|---|
| `GET` | `/` | Pública | Página inicial com documentação resumida. |
| `GET` | `/v1/status` | Opcional | Retorna `versao_em_uso` e `versao_corrente` sem autenticação; com API Key, inclui os dados do certificado digital. |
| `GET` | `/status` | Opcional | Rota legada de compatibilidade retroativa (`versao_em_uso` em `modo de compatibilidade`); dados do certificado exigem API Key. |
| `POST` | `/v1/xml` | Requerida | Envia o XML da nota fiscal (`xml`) ou a chave (`chave`) via POST form ou JSON body para validação, consulta na SEFAZ e geração dos PDFs (DANFE e protocolo). |
| `GET` | `/v1/danfe/<arquivo>` | Requerida | Download do DANFE em PDF gerado. |
| `GET` | `/v1/sefaz/<arquivo>` | Requerida | Download do relatório de protocolo da SEFAZ em PDF. |
| `GET` | `/v1/xml/<arquivo>` | Requerida | Download do XML da nota fiscal armazenado. |
| `GET` | `/v1/prot/<arquivo>` | Requerida | Download do XML de protocolo da SEFAZ. |

---

## Testes Automatizados

A suíte de testes de integração utiliza o PHPUnit:

```bash
./vendor/bin/phpunit
```

---

## Pessoas Envolvidas

- Masaki Kawabata Neto - kawabata em sc.usp.br
