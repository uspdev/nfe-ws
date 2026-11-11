<?php

namespace Uspdev\NfeWs;

class Tools
{
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

    // esta deprecado pois dá para usar o pConvertTime do comon
    public static function ConvertTime($dataHora = '')
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

    /**
     * pConvertTime
     * copiado de https://github.com/nfephp-org/nfephp/blob/master/libs/Extras/CommonNFePHP.class.php
     * Converte a informação de data e tempo contida na NFe
     *
     * @param  string $DH Informação de data e tempo extraida da NFe
     * @return timestamp UNIX Para uso com a funçao date do php
     */
    public static function pConvertTime($DH = '')
    {
        if ($DH == '') {
            return '';
        }
        $DH = str_replace('+', '-', $DH);
        $aDH = explode('T', $DH);
        $adDH = explode('-', $aDH[0]);
        if (count($aDH) > 1) {
            $inter = explode('-', $aDH[1]);
            $atDH = explode(':', $inter[0]);
            $timestampDH = mktime($atDH[0], $atDH[1], $atDH[2], $adDH[1], $adDH[2], $adDH[0]);
        } else {
            $timestampDH = mktime($month = $adDH[1], $day =  $adDH[2], $year = $adDH[0]);
        }
        return $timestampDH;
    }

    /**
     * Aplica uma mascara onde # e o caractere curinga
     */
    public static function format($campo = '', $mascara = '')
    {
        if ($campo === '' || $mascara === '') {
            return $campo;
        }
        $sLimpo = preg_replace('/[^0-9A-Za-z]/', '', (string)$campo);
        $sRetorno = '';
        $sIdx = 0;
        $tCampo = strlen($sLimpo);
        for ($i = 0; $i < strlen($mascara); $i++) {
            if ($mascara[$i] === '#') {
                if ($sIdx < $tCampo) {
                    $sRetorno .= $sLimpo[$sIdx++];
                }
            } else {
                if ($sIdx < $tCampo) {
                    $sRetorno .= $mascara[$i];
                }
            }
        }
        return $sRetorno;
    }
}
