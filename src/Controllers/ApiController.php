<?php

namespace Uspdev\NfeWs\Controllers;

use Uspdev\NfeWs\App;

/**
 * Controller para a rota pública "/" e rota de compatibilidade "/status".
 */
class ApiController extends BaseApiController
{
    /**
     * Rota GET /: Exibe a pagina inicial / documentacao
     */
    public function index(): void
    {
        readfile(__DIR__ . '/../../templates/index.tpl');
    }

    /**
     * Rota GET /status (legado): Consulta em modo de compatibilidade
     */
    public function status(): void
    {
        $this->emitStatusResponse([
            'modo_compatibilidade' => true,
            'versao_em_uso'        => 'modo de compatibilidade',
            'versao_corrente'      => App::API_VERSION,
        ]);
    }
}
