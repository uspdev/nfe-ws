<?php
// arquivo de configuração local
// copie para config.php e altere as variáveis adequadamente

// local onde os arquivos de nfe são armazenados
$cfg['local'] = __DIR__ . '/local/recebidas/';

// dados do certificado
// certificado digital ICP-BRASIL tipo A1 para NFE
// Deve ser adquirido (certisign por exemplo)
// Tem validade de 1 ano
$cfg['cert_file'] = __DIR__ . '/local/certs/certificado2018-2019.pfx';
$cfg['cert_pwd'] = 'xxxxxxxx';

// Arquivo com hashes das API keys de acesso ao webservice
// Para manipular o arquivo use:
// >php users.php add nome
$cfg['pwdFile'] = __DIR__ . '/local/passwd.txt';

// o baseurl é usado para dar o caminho completo do arquivo pdf a ser baixado
// corresponde à url completa do webservice
$cfg['baseUrl'] = 'http://servidor/nfe-ws/';

// Se true vamos apresentar alguns erros de debug
$cfg['debug'] = false;

// Configurações de cache
// Tempo em segundos para cache da consulta à SEFAZ (evita Rejeição 656: Consumo Indevido). Padrão: 600 (10 minutos)
$cfg['cache_sefaz_ttl'] = 600;

// Reutilizar arquivos PDF já gerados em disco (DANFE e relatório SEFAZ)
// Se true, evita re-renderização caso o arquivo já exista em disco. Padrão: true
$cfg['cache_pdf'] = true;

// cofig no nfe-php
$nfeConfig = [
    "atualizacao" => "2022-07-25 10:26:00",
    "tpAmb" => 1, // producao
    "razaosocial" => "Escola de Engenharia de São Carlos",
    "cnpj" => "63025530002824",
    "siglaUF" => "SP",
    "schemes" => "PL009_V4",
    "versao" => '4.00'
];

// o apache deve ser configurado para apontar para a pasta api
/*
Alias /nfe-ws/api /home/sistemas/nfe-ws/api
<Directory /home/sistemas/nfe-ws/api/>
                Options Indexes FollowSymlinks MultiViews
                Require all granted
                AllowOverride All
</Directory>
*/
