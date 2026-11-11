<?php

namespace Uspdev\NfeWs;

/**
 * Tabelas e dicionários de códigos do ecossistema NF-e / SEFAZ.
 * Mapeia identificadores numéricos para descrições textuais legíveis.
 */
class Tabelas
{
    /**
     * Operação com Consumidor Final
     */
    public static function indFinal($id): string
    {
        $map = [
            0 => 'Normal',
            1 => 'Consumidor Final',
        ];
        return $map[$id] ?? '';
    }

    /**
     * Identificação do destino da operação (interna, interestadual ou exterior)
     */
    public static function idDest($id): string
    {
        $map = [
            1 => 'Operação interna',
            2 => 'Operação interestadual',
            3 => 'Operação com exterior',
        ];
        return $map[$id] ?? '';
    }

    /**
     * Indicador de presença do comprador no estabelecimento comercial
     */
    public static function indPres($id): string
    {
        $map = [
            0 => 'Não se aplica',
            1 => 'Operação presencial',
            2 => 'Operação não presencial, pela Internet',
            3 => 'Operação não presencial, Teleatendimento',
            4 => 'NFC-e em operação com entrega em domicílio',
            5 => 'Operação presencial, fora do estabelecimento',
            9 => 'Operação não presencial, outros',
        ];
        return $map[$id] ?? '';
    }

    /**
     * Tipo de operação da NF (0=Entrada, 1=Saída)
     */
    public static function tpNF($id): string
    {
        $map = [
            0 => 'Entrada',
            1 => 'Saída',
        ];
        return $map[$id] ?? '';
    }

    /**
     * Finalidade de emissão da NF-e
     */
    public static function finNFe($id): string
    {
        $map = [
            1 => 'NF-e normal',
            2 => 'NF-e complementar',
            3 => 'NF-e de ajuste',
            4 => 'Devolução de mercadorias',
        ];
        return $map[$id] ?? '';
    }

    /**
     * Indicador da forma de pagamento
     */
    public static function indPag($id): string
    {
        $map = [
            0 => 'pagamento à vista',
            1 => 'pagamento à prazo',
            2 => 'outros',
        ];
        return $map[$id] ?? '';
    }

    /**
     * Identificador do processo de emissão da NF-e
     */
    public static function procEmi($id): string
    {
        $map = [
            0 => 'com aplicativo do contribuinte',
            1 => 'avulsa pelo Fisco',
            2 => 'avulsa, pelo contribuinte com seu certificado digital, através do site do Fisco',
            3 => 'pelo contribuinte com aplicativo fornecido pelo Fisco',
        ];
        return $map[$id] ?? '';
    }

    /**
     * Tipo de emissão da NF-e (Normal ou Contingências)
     */
    public static function tpEmis($id): string
    {
        $map = [
            1 => 'Normal',
            2 => 'Contingência FS',
            3 => 'Contingência SCAN',
            4 => 'Contingência EPEC',
            5 => 'Contingência FS-DA',
            6 => 'Contingência SVC-AN',
            7 => 'Contingência SVC-RS',
        ];
        return $map[$id] ?? '';
    }

    /**
     * Tipo de Ambiente da SEFAZ
     */
    public static function tpAmb($id): string
    {
        $map = [
            1 => 'Produção',
            2 => 'Testes',
        ];
        return $map[$id] ?? '';
    }

    /**
     * Códigos de status da SEFAZ para notas já emitidas/consultadas
     */
    public static function cStat($id): string
    {
        $map = [
            100 => 'Aprovada',
            101 => 'Cancelada',
            102 => 'Inutilizada',
            110 => 'Denegada',
            526 => 'Rejeição - atraso superior a 6 meses',
        ];
        return $map[$id] ?? '';
    }
}
