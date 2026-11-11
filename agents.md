# Especificações Técnicas e Diretrizes do Projeto: nfe-ws

Este documento fornece as especificações atuais, arquitetura e convenções do projeto **nfe-ws** para guiar agentes de IA e desenvolvedores.

---

## 1. Visão Geral

O **nfe-ws** é um webservice em PHP para validação, consulta e geração de documentos de Notas Fiscais Eletrônicas (NF-e). É utilizado internamente para integração com o sistema DELOS (USP / EESC), garantindo o cumprimento da legislação de guarda e conferência fiscal.

### Principais Funcionalidades:
* Validação sintática e estrutural do XML da NF-e contra os schemas oficiais da SEFAZ.
* Consulta da situação da NF-e em tempo real junto aos servidores da SEFAZ via certificado digital ICP-Brasil A1.
* Geração do Documento Auxiliar da Nota Fiscal Eletrônica (DANFE em PDF).
* Geração de relatório de protocolo de autorização e eventos SEFAZ (PDF).
* Armazenamento e download de XMLs, DANFEs e protocolos gerados.

---

## 2. Stack Tecnológica

* **Linguagem:** PHP 8.x (compatível com 7.4+)
* **Micro-framework:** [FlightPHP](https://flightphp.com/) (`mikecao/flight: ^3.0`)
* **Bibliotecas NF-e:**
  * `nfephp-org/sped-nfe` (`^5.1`): Comunicação SOAP com a SEFAZ e manipulação de XML.
  * `nfephp-org/sped-da` (`^0.1.9`): Geração da DANFE.
* **Geração de PDF:** `tecnickcom/tcpdf` (`^6.4`)
* **Mecanismo de Template:** `raelgc/template` (`dev-master`)
* **Testes Automatizados:** `phpunit/phpunit` (`^10.5`)
* **Servidor Web:** Apache 2.4 com `mod_rewrite` habilitado (ou servidor embutido do PHP em desenvolvimento).

---

## 3. Estrutura de Diretórios e Arquivos

```text
nfe-ws/
├── api/
│   ├── .htaccess               # Regras de reescrita para Apache / FlightPHP
│   ├── sample.htaccess         # Template de configuração do .htaccess
│   └── index.php               # Ponto de entrada (Front Controller) e definição de rotas
├── src/                        # Classes PSR-4 (namespace Uspdev\NfeWs)
│   ├── ApiController.php       # Controlador das rotas da API e autenticação
│   ├── Auth.php                # Autenticação de API keys e controle de CORS
│   ├── AuthException.php       # Exceção personalizada de autenticação
│   ├── Config.php              # Carregamento e abstração das configurações via .env
│   ├── Logger.php              # Registro de logs de auditoria e acesso diários
│   ├── Nfe.php                 # Tratamento, validação e assinatura do XML da NF-e e geração de DANFE
│   ├── Sefaz.php               # Comunicação SOAP com a SEFAZ e gerenciamento de cache de protocolos
│   ├── Protocolo.php           # Extração/parse dos dados do protocolo SEFAZ e geração de PDF do protocolo
│   ├── Tabelas.php             # Tabelas e dicionários de códigos fiscais da NF-e e SEFAZ
│   ├── Tools.php               # Extensão utilitária de formatação e conversão
│   └── helpers.php             # Funções globais helper (env, config)
├── templates/                  # Templates e arquivos visuais de apresentação
│   ├── index.tpl               # Template HTML da página inicial e documentação básica
│   ├── protocolo.tpl           # Template HTML base para geração do PDF do protocolo SEFAZ
│   └── images/                 # Assets visuais usados em templates e relatórios
│       └── logo_usp.png        # Logo utilizado no rodapé do relatório SEFAZ
├── local/                      # Dados locais (ignorado no controle de versão)
│   ├── certs/                  # Certificado ICP-Brasil (.pfx)
│   ├── recebidas/              # Armazenamento de XMLs e PDFs gerados
│   ├── logs/                   # Logs de acesso com rotação diária (access-YYYY-MM-DD.log)
│   └── passwd.json             # Registro de API keys em formato JSON
├── testes/                     # Exemplos de XMLs e scripts manuais de verificação
├── tests/                      # Testes automatizados (PHPUnit)
│   └── ApiRoutesTest.php       # Testes de integração das rotas da API
├── .env                       # Configurações ativas do ambiente (não versionado)
├── .env.example               # Exemplo de variáveis de ambiente
├── setup.php                   # Script CLI para verificação e diagnóstico de requisitos
├── key.php                     # Script CLI para gerenciar credenciais de acesso
├── composer.json               # Gerenciador de dependências PHP
└── phpunit.xml                 # Configuração da suíte de testes do PHPUnit
```

---

## 4. Arquitetura da API e Endpoints

O arquivo [`api/index.php`](api/index.php) atua como Front Controller e delega o tratamento das requisições para [`ApiController.php`](src/ApiController.php).

### Rotas Disponíveis:

| Método | Rota | Autenticação | Descrição |
|---|---|---|---|
| `GET` | `/` | Pública | Exibe a página inicial com instruções de uso (`templates/index.tpl`). |
| `*` | `/*` | Requerida | Middleware de autenticação (`X-API-Key` ou `?api_key=`) e cabeçalhos CORS aplicado a todas as demais rotas. |
| `GET` | `/status` | Requerida | Informa a validade e dados do certificado digital ICP-Brasil instalado. |
| `GET` | `/@tipo/@file` | Requerida | Download de arquivos armazenados (`danfe`, `sefaz`, `xml`, `prot`). |
| `POST` | `/xml` | Requerida | Recebe string do XML via `$_POST['xml']`, valida, consulta SEFAZ e gera os PDFs. |

### Fluxo de Validação e Processamento (`POST /xml`):
1. Recebe a string XML do payload.
2. `nfe_ws::validaEstruturaXML`: Valida a integridade contra o schema oficial da SEFAZ. Se falhar, retorna `['status' => 'stop', 'erro' => ...]`.
3. `nfe_ws::import`: Extrai os dados principais (chave, emitente, destinatário, valores).
4. `nfe_ws::sefazConsultaChave`: Realiza a consulta online à SEFAZ utilizando o certificado A1 configurado.
5. `nfe_ws::geraDanfe`: Gera o arquivo DANFE em formato PDF.
6. `nfe_ws::geraProtocolo`: Preenche o template [`templates/protocolo.tpl`](templates/protocolo.tpl) com os dados da consulta, insere o logo [`templates/images/logo_usp.png`](templates/images/logo_usp.png) e salva o PDF do protocolo.
7. Retorna JSON com as URLs de download dos arquivos gerados.

---

## 5. Autenticação e Configuração

### Autenticação via API Key:
* Gerenciada diretamente pela aplicação no middleware de rota (`ApiController::authenticate`).
* O cliente envia a chave no cabeçalho HTTP `X-API-Key` (ou parâmetro de query string).
* As chaves são strings aleatórias seguras (64 hex) e armazenadas em `$cfg['pwdFile']` (`local/passwd.json`) como um array estruturado em formato JSON contendo `name`, `hash` (SHA-256), `description`, `created_at` e `revoked_at`.
* Todas as requisições autenticadas ou com falha de autenticação (`AUTH`) e as consultas com a SEFAZ (`SEFAZ`: status online, tempo em ms, hit de cache e falhas SOAP) geram logs dedicados com rotação diária em `local/logs/access-YYYY-MM-DD.log`.
* Gerenciamento via CLI:
  ```bash
  php key.php add <nome> [descricao]
  php key.php list
  php key.php revoke <nome>
  ```

### Arquivo `.env`:
* `NFE_LOCAL_PATH`: Caminho de gravação dos arquivos (`local/recebidas/`).
* `NFE_CERT_FILE`: Caminho do certificado A1 (`.pfx`).
* `NFE_CERT_PASSWORD`: Senha do certificado digital.
* `NFE_PASSWD_FILE`: Caminho do arquivo de senhas/API keys (`local/passwd.json`).
* `NFE_BASE_URL`: URL base da aplicação para composição dos links de retorno.
* `NFE_DEBUG`: Booleano para exibição de detalhes de erro.
* `NFE_CACHE_SEFAZ_TTL`: Tempo de vida (TTL) em segundos da consulta SEFAZ em cache local (padrão: `600`).
* `NFE_CACHE_PDF`: Booleano para reaproveitar PDFs já gerados em disco (DANFE e protocolo SEFAZ) (padrão: `true`).
* Parâmetros do NFePHP: `NFE_TP_AMB`, `NFE_RAZAO_SOCIAL`, `NFE_CNPJ`, `NFE_SIGLA_UF`, `NFE_SCHEMES`, `NFE_VERSAO`.

---

## 6. Comandos e Diagnóstico

* **Verificar ambiente e dependências:**
  ```bash
  php setup.php
  ```
* **Executar suíte de testes:**
  ```bash
  ./vendor/bin/phpunit
  ```
* **Verificação de sintaxe:**
  ```bash
  php -l src/ApiController.php
  php -l src/Nfe.php
  ```

---

## 7. Diretrizes para Agentes de IA

1. **Separação de Responsabilidades:**
   * Mantenha apenas classes PSR-4 em [`src/`](src/) com namespace `Uspdev\NfeWs`.
   * Todos os templates (HTML/TPL) devem residir em [`templates/`](templates/).
   * Imagens e assets visuais estáticos devem residir em [`templates/images/`](templates/images/).
2. **Controle de Acesso e Segurança:**
   * Nunca faça commit de certificados `.pfx`, senhas ou arquivos contidos em `local/`.
   * Mantenha validação rígida de parâmetros de entrada (como `$file` na rota de download para prevenir Directory Traversal).
3. **Compatibilidade:**
   * O código roda em ambiente PHP 8.x moderno, mas interage com bibliotecas que requerem tratamento cuidadoso de tipos e manipulação de nós DOM/XML.
