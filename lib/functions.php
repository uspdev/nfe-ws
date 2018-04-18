<?php


function FormatarCPF_CNPJ($campo, $formatado = true)
{
    //retira formato
    $codigoLimpo = preg_replace("[' '-./ t]", '', $campo);
    // pega o tamanho da string menos os digitos verificadores
    $tamanho = (strlen($codigoLimpo) - 2);
    //verifica se o tamanho do código informado é válido
    if ($tamanho != 9 && $tamanho != 12) {
        if ($debug) echo 'Número de dígitos incorreto.';
        return false;
    }

    if ($formatado) {
        // seleciona a máscara para cpf ou cnpj
        $mascara = ($tamanho == 9) ? '###.###.###-##' : '##.###.###/####-##';

        $indice = -1;
        for ($i = 0; $i < strlen($mascara); $i++) {
            if ($mascara[$i] == '#') $mascara[$i] = $codigoLimpo[++$indice];
        }
        //retorna o campo formatado
        $retorno = $mascara;

    } else {
        //se não quer formatado, retorna o campo limpo
        $retorno = $codigoLimpo;
    }
    return $retorno;
}

function ConvertTime($dataHora = '')
{
    $timestampDH = 0;
    if ($dataHora) {
        $aDH = explode('T', $dataHora);
        $adDH = explode('-', $aDH[0]);
        $atDH = explode(':', substr($aDH[1], 0, 8)); //substring para recuperar apenas a hora, sem o fuso horário
        $timestampDH = mktime($atDH[0], $atDH[1], $atDH[2], $adDH[1], $adDH[2], $adDH[0]);
    }
    return $timestampDH;
} //fim convertTime

?>