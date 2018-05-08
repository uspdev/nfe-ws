<?php

/*
 * Contém dados estáticos relacionados a NFE e códigos
 * todas as funções são estáticas
 */
class Storage
{
    // Operação com Consumidor Final
    public static function indFinal($id)
    {
        $indFinal[0] = 'Normal';
        $indFinal[1] = 'Consumidor Final';
        return $indFinal[$id];
    }

    // Identificação de operações internas, interestaduais e exterior
    public static function idDest($id)
    {
        $idDest = array(
            1 => 'Operação interna',
            2 => 'Operação interestadual',
            3 => 'Operação com exterior');
        return $idDest[$id];
    }

    // indicador de presença
    public static function indPres($id)
    {
        $indPres = array(
            0 => 'Não se aplica',
            1 => 'Operação presencial',
            2 => 'Operação não presencial, pela Internet',
            3 => 'Operação não presencial, Teleatendimento',
            4 => 'NFC-e em operação com entrega em domicílio',
            9 => 'Operação não presencial, outros');
        return $indPres[$id];
    }

    // tipo de operação da NF
    public static function tpNF($id)
    {
        $tpNF = array(
            0 => 'Entrada',
            1 => 'Saída');
        return $tpNF[$id];
    }

    // Finalidade de emissão da NF-e
    public static function finNFe($id)
    {
        $finNFe = array(
            1 => 'NF-e normal',
            2 => 'NF-e complementar',
            3 => 'NF-e de ajuste',
            4 => 'Devolução de mercadorias');
        return $finNFe[$id];
    }

    // Indicador da forma de pagamento
    public static function indPag($id)
    {
        $indPag = array(
            0 => 'pagamento à vista',
            1 => 'pagamento à prazo',
            2 => 'outros');
        return $indPag[$id];
    }

    // Identificador do processo de emissão da NF-e
    public static function procEmi($id)
    {
        $procEmi = array(
            0 => 'com aplicativo do contribuinte',
            1 => 'avulsa pelo Fisco',
            2 => 'avulsa, pelo contribuinte com seu certificado digital, através do site do Fisco',
            3 => 'pelo contribuinte com aplicativo fornecido pelo Fisco');

        return $procEmi[$id];
    }

    // Tipo de Emissão da NF-e
    public static function tpEmis($id)
    {
        $tpEmis = array(
            1 => 'Normal',
            2 => 'Contingência FS',
            3 => 'Contingência SCAN',
            4 => 'Contingência DPEC',
            5 => 'Contingência FS-DA');

        return $tpEmis[$id];
    }

    public static function tpAmb($id)
    {
        $tpAmb = array(
            1 => 'Produção',
            2 => 'Testes');
        return $tpAmb[$id];
    }

    // aqui só tem os cStats relevantes para uma NFe já emitida
    // a lista completa está no sped-nfe/storage/cstat.json
    public static function cStat($id)
    {
        $cStat = array(
            100 => 'Aprovada',
            101 => 'Cancelada',
            102 => 'Inutilizada',
            110 => 'Denegada');
        return $cStat[$id];
    }

}
