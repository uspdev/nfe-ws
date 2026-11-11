<?php

namespace Uspdev\NfeWs;

use NFePHP\Common\Soap\SoapCurl;
use NFePHP\NFe\Tools as NfeTools;

class Sefaz
{
    private Config $config;
    private NfeTools $tools;

    public function __construct(?Config $config = null)
    {
        $this->config = $config ?? Config::getInstance();

        $configJson = json_encode($this->config->nfeConfig);
        $certificate = $this->config->getCertificate();

        $soap = new SoapCurl($certificate);
        $soap->httpVersion('1.1');

        $this->tools = new NfeTools($configJson, $certificate);
        $this->tools->loadSoapClass($soap);
    }

    /**
     * Consulta a chave na SEFAZ com cache local em disco para evitar Rejeicao 656
     *
     * @param string $chave Chave de 44 digitos da NF-e
     * @param int|null $maxAge Tempo maximo em segundos do cache local
     * @return array [ 'raw' => string, 'age' => int, 'url' => string, 'chave' => string ]
     */
    public function consultarChave(string $chave, ?int $maxAge = null): array
    {
        $chNFe = Nfe::validaChNFe($chave);
        if (!$chNFe) {
            throw new \InvalidArgumentException('Chave de NF-e inválida: ' . $chave);
        }

        $maxAge = $maxAge ?? $this->config->cacheSefazTtl;
        $arq = $this->config->local . $chNFe . '-prot.xml';
        $age = is_file($arq) ? time() - filemtime($arq) : 0;

        if (!is_file($arq) || $age > $maxAge) {
            $raw = $this->tools->sefazConsultaChave($chNFe);
            file_put_contents($arq, $raw);
            $age = 0;
        } else {
            $raw = file_get_contents($arq);
        }

        return [
            'raw'   => $raw,
            'age'   => $age,
            'url'   => $this->config->baseUrl . 'prot/' . $chNFe . '-prot.xml',
            'chave' => $chNFe,
        ];
    }

    /**
     * Retorna a instancia subjacente do NFePHP Tools
     */
    public function getTools(): NfeTools
    {
        return $this->tools;
    }
}
