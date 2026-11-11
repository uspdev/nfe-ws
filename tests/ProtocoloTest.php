<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use Uspdev\NfeWs\Protocolo;

class ProtocoloTest extends TestCase
{
    public function testParseReturnsErrorOnMalformedXml(): void
    {
        $res = Protocolo::parse('not-a-valid-xml');

        $this->assertSame('erro', $res['status']);
        $this->assertStringContainsString('mal formado', $res['xMotivo']);
    }

    public function testParseReturnsErrorOnCStat526(): void
    {
        $xml = <<<XML
<retConsSitNFe xmlns="http://www.portalfiscal.inf.br/nfe" versao="4.00">
    <tpAmb>1</tpAmb>
    <verAplic>SP_NFE_PL009_V4</verAplic>
    <cStat>526</cStat>
    <xMotivo>Rejeicao: Consulta a uma Chave de Acesso muito antiga</xMotivo>
    <cUF>35</cUF>
    <dhRecbto>2026-11-11T12:00:00-03:00</dhRecbto>
    <chNFe>35261168995539000161550020000005341262696215</chNFe>
</retConsSitNFe>
XML;

        $res = Protocolo::parse($xml);
        $this->assertStringContainsString('mais de 6 meses', $res['status']);
    }

    public function testParseReturnsErrorWhenNoInfProt(): void
    {
        $xml = <<<XML
<retConsSitNFe xmlns="http://www.portalfiscal.inf.br/nfe" versao="4.00">
    <tpAmb>1</tpAmb>
    <verAplic>SP_NFE_PL009_V4</verAplic>
    <cStat>100</cStat>
    <xMotivo>Autorizado o uso da NF-e</xMotivo>
    <cUF>35</cUF>
    <dhRecbto>2026-11-11T12:00:00-03:00</dhRecbto>
    <chNFe>35261168995539000161550020000005341262696215</chNFe>
</retConsSitNFe>
XML;

        $res = Protocolo::parse($xml);
        $this->assertStringContainsString('sem infProt', $res['status']);
    }

    public function testParseSuccessWithInfProt(): void
    {
        $xml = <<<XML
<retConsSitNFe xmlns="http://www.portalfiscal.inf.br/nfe" versao="4.00">
    <tpAmb>1</tpAmb>
    <verAplic>SP_NFE_PL009_V4</verAplic>
    <cStat>100</cStat>
    <xMotivo>Autorizado o uso da NF-e</xMotivo>
    <cUF>35</cUF>
    <dhRecbto>2026-11-11T12:00:00-03:00</dhRecbto>
    <chNFe>35261168995539000161550020000005341262696215</chNFe>
    <protNFe versao="4.00">
        <infProt>
            <tpAmb>1</tpAmb>
            <verAplic>SP_NFE_PL009_V4</verAplic>
            <chNFe>35261168995539000161550020000005341262696215</chNFe>
            <dhRecbto>2026-11-11T12:00:00-03:00</dhRecbto>
            <nProt>135260000000001</nProt>
            <digVal>ABCDEF123456=</digVal>
            <cStat>100</cStat>
            <xMotivo>Autorizado o uso da NF-e</xMotivo>
        </infProt>
    </protNFe>
</retConsSitNFe>
XML;

        $res = Protocolo::parse($xml);
        $this->assertSame('ok', $res['status']);
        $this->assertSame('100', $res['cStat']);
        $this->assertNotEmpty($res['eventos']);
        $this->assertSame('135260000000001', $res['eventos'][0]['nProt']);
    }
}
