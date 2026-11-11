<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use Uspdev\NfeWs\Tabelas;

class TabelasTest extends TestCase
{
    public function testIndFinalReturnsCorrectDescription(): void
    {
        $this->assertSame('Normal', Tabelas::indFinal(0));
        $this->assertSame('Consumidor Final', Tabelas::indFinal(1));
        $this->assertSame('', Tabelas::indFinal(999));
    }

    public function testIdDestReturnsCorrectDescription(): void
    {
        $this->assertSame('Operação interna', Tabelas::idDest(1));
        $this->assertSame('Operação interestadual', Tabelas::idDest(2));
        $this->assertSame('Operação com exterior', Tabelas::idDest(3));
        $this->assertSame('', Tabelas::idDest(999));
    }

    public function testIndPresReturnsCorrectDescription(): void
    {
        $this->assertSame('Operação presencial', Tabelas::indPres(1));
        $this->assertSame('Operação não presencial, pela Internet', Tabelas::indPres(2));
        $this->assertSame('', Tabelas::indPres(999));
    }

    public function testTpNFReturnsCorrectDescription(): void
    {
        $this->assertSame('Entrada', Tabelas::tpNF(0));
        $this->assertSame('Saída', Tabelas::tpNF(1));
        $this->assertSame('', Tabelas::tpNF(999));
    }

    public function testFinNFeReturnsCorrectDescription(): void
    {
        $this->assertSame('NF-e normal', Tabelas::finNFe(1));
        $this->assertSame('Devolução de mercadorias', Tabelas::finNFe(4));
        $this->assertSame('', Tabelas::finNFe(999));
    }

    public function testIndPagReturnsCorrectDescription(): void
    {
        $this->assertSame('pagamento à vista', Tabelas::indPag(0));
        $this->assertSame('pagamento à prazo', Tabelas::indPag(1));
        $this->assertSame('', Tabelas::indPag(999));
    }

    public function testProcEmiReturnsCorrectDescription(): void
    {
        $this->assertSame('com aplicativo do contribuinte', Tabelas::procEmi(0));
        $this->assertSame('', Tabelas::procEmi(999));
    }

    public function testTpEmisReturnsCorrectDescription(): void
    {
        $this->assertSame('Normal', Tabelas::tpEmis(1));
        $this->assertSame('Contingência SVC-RS', Tabelas::tpEmis(7));
        $this->assertSame('', Tabelas::tpEmis(999));
    }

    public function testTpAmbReturnsCorrectDescription(): void
    {
        $this->assertSame('Produção', Tabelas::tpAmb(1));
        $this->assertSame('Testes', Tabelas::tpAmb(2));
        $this->assertSame('', Tabelas::tpAmb(999));
    }

    public function testCStatReturnsCorrectDescription(): void
    {
        $this->assertSame('Aprovada', Tabelas::cStat(100));
        $this->assertSame('Cancelada', Tabelas::cStat(101));
        $this->assertSame('Rejeição - atraso superior a 6 meses', Tabelas::cStat(526));
        $this->assertSame('', Tabelas::cStat(999));
    }
}
