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

// arquivo com senha de acesso ao webservice
// Para manipular o arquivo use:
// >php passwd.php
$cfg['pwdFile'] = __DIR__ . '/local/passwd.txt';

// o baseurl é usado para dar o caminho completo do arquivo pdf a ser baixado
// corresponde à url completa do webservice
$cfg['baseUrl'] = 'http://servidor/nfe-ws/';

// o apache deve ser configurado para apontar para a pasta api
/*
Alias /nfe-ws/api /home/sistemas/nfe-ws/api
<Directory /home/sistemas/nfe-ws/api/>
                Options Indexes FollowSymlinks MultiViews
                Require all granted
                AllowOverride All
</Directory>
*/

