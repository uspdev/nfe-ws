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
├── lib/                        # Classes de lógica e domínio (exclusivamente PHP)
│   ├── ApiController.class.php # Controlador das rotas da API e autenticação
│   ├── Config.class.php        # Carregamento e abstração das configurações ($cfg e $nfeConfig)
│   ├── nfe-ws.class.php        # Lógica principal de validação, consulta SEFAZ e geração de PDF
│   ├── Protocolo.class.php     # Extração e normalização de dados do protocolo da SEFAZ
│   ├── Storage.class.php       # Utilitários de mapeamento de códigos da NF-e e SEFAZ
│   └── Tools.class.php         # Extensão utilitária de comunicação com a SEFAZ
├── templates/                  # Templates e arquivos visuais de apresentação
│   ├── index.tpl               # Template HTML da página inicial e documentação básica
│   ├── protocolo.tpl           # Template HTML base para geração do PDF do protocolo SEFAZ
│   └── images/                 # Assets visuais usados em templates e relatórios
│       └── logo_usp.png        # Logo utilizado no rodapé do relatório SEFAZ
├── local/                      # Dados locais (ignorado no controle de versão)
│   ├── certs/                  # Certificado ICP-Brasil (.pfx)
│   ├── recebidas/              # Armazenamento de XMLs e PDFs gerados
│   └── passwd.txt              # Usuários e senhas serializados
├── testes/                     # Exemplos de XMLs e scripts manuais de verificação
├── tests/                      # Testes automatizados (PHPUnit)
│   └── ApiRoutesTest.php       # Testes de integração das rotas da API
├── config.php                  # Configurações ativas do ambiente (não versionado)
├── config.sample.php           # Exemplo de configuração
├── setup.php                   # Script CLI para verificação e diagnóstico de requisitos
├── users.php                   # Script CLI para gerenciar credenciais de acesso
├── composer.json               # Gerenciador de dependências PHP
└── phpunit.xml                 # Configuração da suíte de testes do PHPUnit
```

---

## 4. Arquitetura da API e Endpoints

O arquivo [`api/index.php`](api/index.php) atua como Front Controller e delega o tratamento das requisições para [`ApiController.class.php`](lib/ApiController.class.php).

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
* O cliente envia a chave no cabeçalho HTTP `X-API-Key`.
* As chaves são strings aleatórias seguras (64 hex) e apenas seus hashes SHA-256 são armazenados em `$cfg['pwdFile']` (`local/passwd.txt`) como um array associativo serializado.
* Gerenciamento via CLI:
  ```bash
  php users.php add <nome>
  php users.php list
  php users.php del <nome>
  ```

### Arquivo `config.php`:
* `$cfg['local']`: Caminho de gravação dos arquivos (`local/recebidas/`).
* `$cfg['cert_file']`: Caminho do certificado A1 (`.pfx`).
* `$cfg['cert_pwd']`: Senha do certificado digital.
* `$cfg['pwdFile']`: Caminho do arquivo de senhas (`local/passwd.txt`).
* `$cfg['baseUrl']`: URL base da aplicação para composição dos links de retorno.
* `$cfg['debug']`: Booleano para exibição de detalhes de erro.
* `$cfg['cache_sefaz_ttl']`: Tempo de vida (TTL) em segundos da consulta SEFAZ em cache local (padrão: `600`).
* `$cfg['cache_pdf']`: Booleano para reaproveitar PDFs já gerados em disco (DANFE e protocolo SEFAZ) (padrão: `true`).
* `$nfeConfig`: Parâmetros do NFePHP (ambiente de produção/homologação, CNPJ emitente, UF, versão do schema `4.00`).

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
  php -l lib/ApiController.class.php
  php -l lib/nfe-ws.class.php
  ```

---

## 7. Diretrizes para Agentes de IA

1. **Separação de Responsabilidades:**
   * Mantenha apenas classes PHP com padrão `NomeClasse.class.php` em [`lib/`](lib/).
   * Todos os templates (HTML/TPL) devem residir em [`templates/`](templates/).
   * Imagens e assets visuais estáticos devem residir em [`templates/images/`](templates/images/).
2. **Controle de Acesso e Segurança:**
   * Nunca faça commit de certificados `.pfx`, senhas ou arquivos contidos em `local/`.
   * Mantenha validação rígida de parâmetros de entrada (como `$file` na rota de download para prevenir Directory Traversal).
3. **Compatibilidade:**
   * O código roda em ambiente PHP 8.x moderno, mas interage com bibliotecas que requerem tratamento cuidadoso de tipos e manipulação de nós DOM/XML.
