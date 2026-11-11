<?php

namespace Uspdev\NfeWs;

use NFePHP\Common\Soap\SoapCurl;
use NFePHP\NFe\Tools as NfeTools;

class Sefaz
{
    private Config $config;
    private NfeTools $tools;

    public function __construct(?Config $config = null, ?NfeTools $tools = null)
    {
        $this->config = $config ?? Config::getInstance();

        if ($tools !== null) {
            $this->tools = $tools;
        } else {
            $configJson = json_encode($this->config->nfeConfig);
            $certificate = $this->config->getCertificate();

            $soap = new SoapCurl($certificate);
            $soap->httpVersion('1.1');

            $this->tools = new NfeTools($configJson, $certificate);
            $this->tools->loadSoapClass($soap);
        }
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
            $start = microtime(true);
            try {
                $raw = $this->tools->sefazConsultaChave($chNFe);
                $durationMs = round((microtime(true) - $start) * 1000);
                file_put_contents($arq, $raw);
                $age = 0;

                preg_match('/<cStat>(\d+)<\/cStat>/', $raw, $mStat);
                preg_match('/<xMotivo>(.*?)<\/xMotivo>/', $raw, $mMotivo);
                $cStat = $mStat[1] ?? '-';
                $xMotivo = $mMotivo[1] ?? '-';

                Logger::log('SEFAZ', [
                    'status'  => 'ONLINE',
                    'message' => "chave={$chNFe} cStat={$cStat} tempo={$durationMs}ms - {$xMotivo}",
                ]);
            } catch (\Throwable $e) {
                $durationMs = round((microtime(true) - $start) * 1000);
                Logger::log('SEFAZ', [
                    'status'  => 'ERROR',
                    'message' => "chave={$chNFe} tempo={$durationMs}ms - Falha na comunicação SOAP: " . $e->getMessage(),
                ]);
                throw $e;
            }
        } else {
            $raw = file_get_contents($arq);
            Logger::log('SEFAZ', [
                'status'  => 'CACHE',
                'message' => "chave={$chNFe} age={$age}s",
            ]);
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
