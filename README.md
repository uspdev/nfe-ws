# nfe-ws
Webservice para validação de NFEs

Aplicação e webservice utilizado para realizar consultas relacionadas à nota fiscal eletrônica - NFE:

- Consultar a validade junto à SEFAZ;
- Verificar a estrutura do XML;
- Gerar DANFE

É composto por uma api de webservice e uma aplicação simples para testes.

Esta api É utilizada pelo sistema delos, para armazenamento de notas fiscais eletrônicas 
em cumprimento à legislação específica.

A autenticação é por http basic mas é gerenciado pelo próprio php.

### Bibliotecas ###

#### API ####
A API faz uso di diversas bibliotecas sendo a principal a nfephp, de onde aproveita as funcionalidades propostas

- nfephp - https://github.com/nfephp-org/nfephp
- flightphp - http://flightphp.com/

#### APP ####

O APP é uma aplicação em angularjs e utiliza boostrap. font-awesome, jquery e jqueri-ui.
Roda direto no navegador do cliente consultando a API.

Desenvolvido no php 5.5 mas com compatibilidade do php 5.3

### Requisitos necessários ###

A necessidade de processamento e armazenamento é relativamente baixa. Arquivos XML e DANFES PDF são pequenos e não é necessário um grande storage.

- SO: Linux
- Servidor apache
- PHP
- Certificado ICP-BRASIL


### Backup ###




### Deploy ###

Para realizar consultas na SEFAZ é necessário ter um certificado digital ICP-BRASIL válido.
A senha do webservice fica em config/passwd.txt e pode ser gerada pela sintaxe:

- php passwd.php



### Pessoas envolvidas ###

Masaki Kawabata Neto - kawabata em sc.usp.br