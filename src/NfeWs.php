<?php

namespace Uspdev\NfeWs;

/**
 * Classe mantida para retrocompatibilidade com código que referencia NfeWs ou nfe_ws.
 * A lógica principal reside agora em Nfe, Sefaz e Protocolo.
 */
#[\AllowDynamicProperties]
class NfeWs extends Nfe
{
    /**
     * @deprecated Utilize Protocolo::geraRelatorioPdf($prot, $nfe)
     */
    public function geraRelatorioSefaz($prot)
    {
        return Protocolo::geraRelatorioPdf($prot, $this);
    }
}
