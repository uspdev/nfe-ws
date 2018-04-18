<?php


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
            3 => 'NF-e de ajuste');
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

}

class Tools
{

    /*
     * necessário validar quando recebe a chave do usuário
     * a chave é somente números com 44 digitos
     */
    public static function validaChNFe($chNFe)
    {
        if (preg_match("/^[0-9]{44}$/", str_replace(' ', '', $chNFe)) != 1) {
            return false;
        }
        return $chNFe;
    }

    public static function Dom2Array($root)
    {
        $array = array();
        //list attributes
        if ($root->hasAttributes()) {
            foreach ($root->attributes as $attribute) {
                $array['_attributes'][$attribute->name] = $attribute->value;
            }
        }
        //handle classic node
        if ($root->nodeType == XML_ELEMENT_NODE) {
            $array['_type'] = $root->nodeName;
            if ($root->hasChildNodes()) {
                $children = $root->childNodes;
                for ($i = 0; $i < $children->length; $i++) {
                    $child = Tools::Dom2Array($children->item($i));
                    //don't keep textnode with only spaces and newline
                    if (!empty($child)) {
                        $array['_children'][] = $child;
                    }
                }
            }
            //handle text node
        } elseif ($root->nodeType == XML_TEXT_NODE || $root->nodeType == XML_CDATA_SECTION_NODE) {
            $value = $root->nodeValue;
            if (!empty($value)) {
                $array['_type'] = '_text';
                $array['_content'] = $value;
            }
        }
        return $array;
    }


    public static function msgTempo($fim, $ini)
    {
        if ($fim - $ini < 1) {
            return 'agora mesmo';
        }
        if ($fim - $ini >= 1 && $fim - $ini < 60) {
            return 'menos de 1 minuto';
        }
        if ($fim - $ini >= 60 && $fim - $ini < 60 * 60) {
            return round(($fim - $ini) / 60) . ' minutos';
        }
        if ($fim - $ini >= 60 * 60 && $fim - $ini < 60 * 60 * 60) {
            return round(($fim - $ini) / 60 / 60) . ' horas';
        }
        if ($fim - $ini >= 60 * 60 * 60) {
            return round(($fim - $ini) / 60 / 60 / 60) . ' dias';
        }
    }
}