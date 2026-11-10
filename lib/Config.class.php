<?php

class Config
{
    public $local;
    public $certFile;
    public $certPwd;
    public $pwdFile;
    public $baseUrl;
    public $cacheSefazTtl;
    public $cachePdf;

    function __construct()
    {
        global $cfg;
        $this->local = $cfg['local'] ?? '';
        $this->certFile = $cfg['cert_file'] ?? '';
        $this->certPwd = $cfg['cert_pwd'] ?? '';

        $this->pwdFile = $cfg['pwdFile'] ?? '';
        $this->baseUrl = $cfg['baseUrl'] ?? '';

        $this->cacheSefazTtl = isset($cfg['cache_sefaz_ttl']) ? (int)$cfg['cache_sefaz_ttl'] : 600;
        $this->cachePdf = isset($cfg['cache_pdf']) ? (bool)$cfg['cache_pdf'] : true;
    }

    /**
     * Carrega e instancia o certificado digital ICP-Brasil.
     * Possui suporte a cifras legadas (OpenSSL 3+).
     *
     * @return \NFePHP\Common\Certificate
     */
    public function getCertificate()
    {
        if (!is_file($this->certFile)) {
            throw new \RuntimeException('Certificado não encontrado em: ' . $this->certFile);
        }

        $content = file_get_contents($this->certFile);
        try {
            return \NFePHP\Common\Certificate::readPfx($content, $this->certPwd);
        } catch (\Throwable $e) {
            // Em ambientes com OpenSSL 3+, certificados ICP-Brasil com cifras legadas (RC2/3DES)
            // falham no openssl_pkcs12_read com erro "unsupported". Fallback de conversão via openssl CLI:
            $cmd = sprintf(
                'openssl pkcs12 -in %s -passin %s -legacy -nodes 2>/dev/null | openssl pkcs12 -export -passout %s 2>/dev/null',
                escapeshellarg($this->certFile),
                escapeshellarg('pass:' . $this->certPwd),
                escapeshellarg('pass:' . $this->certPwd)
            );
            $converted = shell_exec($cmd);
            if (!empty($converted)) {
                return \NFePHP\Common\Certificate::readPfx($converted, $this->certPwd);
            }
            throw $e;
        }
    }

}