<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <title>NFE-WS - API de serviços para NFE</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body {
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background-color: #f8f9fa;
            color: #333;
            margin: 0;
            padding: 20px;
            line-height: 1.5;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: #fff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        h1 {
            color: #1a365d;
            font-size: 1.75rem;
            margin-top: 0;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 10px;
        }
        h2 {
            color: #2b6cb0;
            font-size: 1.25rem;
            margin-top: 25px;
            margin-bottom: 10px;
        }
        p, li {
            font-size: 0.95rem;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
            font-size: 0.9rem;
        }
        th, td {
            border: 1px solid #e2e8f0;
            padding: 10px 12px;
            text-align: left;
        }
        th {
            background-color: #edf2f7;
            color: #2d3748;
            font-weight: 600;
        }
        tr:nth-child(even) {
            background-color: #f7fafc;
        }
        code {
            background: #edf2f7;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 0.88em;
            color: #c53030;
            font-family: monospace;
        }
        a {
            color: #2b6cb0;
            text-decoration: none;
        }
        a:hover {
            text-decoration: underline;
        }
        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: bold;
            text-transform: uppercase;
        }
        .badge-get { background: #ebf8ff; color: #2b6cb0; border: 1px solid #bee3f8; }
        .badge-post { background: #f0fff4; color: #276749; border: 1px solid #c6f6d5; }
        .badge-public { background: #feebc8; color: #7b341e; }
        .badge-auth { background: #fed7d7; color: #9b2c2c; }
        .footer {
            margin-top: 30px;
            padding-top: 15px;
            border-top: 1px solid #e2e8f0;
            font-size: 0.85rem;
            color: #718096;
        }
    </style>
</head>
<body>
<div class="container">
    <h1>API de serviços para NFE</h1>
    <p>Webservice REST para recepção, validação, consulta junto à SEFAZ e geração de DANFE e protocolo de Notas Fiscais Eletrônicas.</p>

    <h2>Endpoints Gerais / Compatibilidade</h2>
    <table>
        <thead>
            <tr>
                <th style="width: 80px;">Método</th>
                <th>Rota</th>
                <th style="width: 100px;">Acesso</th>
                <th>Descrição</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><span class="badge badge-get">GET</span></td>
                <td><code>/</code></td>
                <td><span class="badge badge-public">Pública</span></td>
                <td>Página inicial com a documentação dos endpoints.</td>
            </tr>
            <tr>
                <td><span class="badge badge-get">GET</span></td>
                <td><code>/status</code></td>
                <td><span class="badge badge-public">Pública</span> <span class="badge badge-auth">API Key*</span></td>
                <td>Rota de compatibilidade retroativa para consulta de status (retorna <code>versao_em_uso</code> como <code>modo de compatibilidade</code>). Dados do certificado somente com API Key.</td>
            </tr>
        </tbody>
    </table>

    <h2>Endpoints da API v1 (<code>/v1</code>)</h2>
    <table>
        <thead>
            <tr>
                <th style="width: 80px;">Método</th>
                <th>Rota</th>
                <th style="width: 100px;">Acesso</th>
                <th>Descrição</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><span class="badge badge-get">GET</span></td>
                <td><code>/v1/status</code></td>
                <td><span class="badge badge-public">Pública</span> <span class="badge badge-auth">API Key*</span></td>
                <td>Consulta a versão em uso (<code>v1</code>) e a versão mais atual da API; com API Key, retorna também a validade e status do certificado digital.</td>
            </tr>
            <tr>
                <td><span class="badge badge-post">POST</span></td>
                <td><code>/v1/xml</code></td>
                <td><span class="badge badge-auth">API Key</span></td>
                <td>Envia o XML da NF-e para validação, consulta na SEFAZ e geração dos links para DANFE, protocolo e SEFAZ em PDF/XML.</td>
            </tr>
            <tr>
                <td><span class="badge badge-get">GET</span></td>
                <td><code>/v1/danfe/{arquivo}.pdf</code></td>
                <td><span class="badge badge-auth">API Key</span></td>
                <td>Download ou visualização do DANFE em formato PDF.</td>
            </tr>
            <tr>
                <td><span class="badge badge-get">GET</span></td>
                <td><code>/v1/sefaz/{arquivo}.pdf</code></td>
                <td><span class="badge badge-auth">API Key</span></td>
                <td>Download do relatório de consulta e protocolo da SEFAZ em formato PDF.</td>
            </tr>
            <tr>
                <td><span class="badge badge-get">GET</span></td>
                <td><code>/v1/prot/{arquivo}.xml</code></td>
                <td><span class="badge badge-auth">API Key</span></td>
                <td>Download do XML de protocolo retornado pela SEFAZ.</td>
            </tr>
            <tr>
                <td><span class="badge badge-get">GET</span></td>
                <td><code>/v1/xml/{arquivo}.xml</code></td>
                <td><span class="badge badge-auth">API Key</span></td>
                <td>Download do arquivo XML da NF-e armazenado no servidor.</td>
            </tr>
        </tbody>
    </table>

    <h2>Consumo da API</h2>
    <p>
        Para consumir esta API, utilize a biblioteca cliente <a href="https://github.com/uspdev/nfe-ws-consumer" target="_blank"><strong>uspdev/nfe-ws-consumer</strong></a>.
        Consulte a documentação e exemplos de uso diretamente no repositório do GitHub:
        <br>
        <a href="https://github.com/uspdev/nfe-ws-consumer" target="_blank">https://github.com/uspdev/nfe-ws-consumer</a>
    </p>

</div>
</body>
</html>
