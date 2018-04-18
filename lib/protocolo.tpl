<style>
    .body {
        font-size: 9px;
        font-family: helvetica;
    }

    .linha1 {
        font-weight: bold;
    }

    .linha2 {
        width: 100%;
        color: red;
        background-color: #ededed;
        border-top: 1px solid gray;
        border-bottom: 1px solid gray;
    }

    .linha3 {
        font-size: 6px;
    }

    table {
        width: 100%;
        border-spacing: 2px;
    }

    .valor_campo {
        background-color: #ededed;
    }

</style>
<div class="body">
    <h2 style="text-align: center;">Consulta resumida - NFE</h2>
    <div>
        <table>
            <tr class="linha1">
                <td style="width: 75%">Informação do sistema</td>
                <td style="width: 25%">Data da consulta</td>
            </tr>
            <tr class="valor_campo">
                <td>{infoSistema}</td>
                <td>{dhConsulta}</td>
            </tr>
        </table>
        <br><br>
        <table>
            <tr class="linha1">
                <td style="width: 70%">Chave de acesso</td>
                <td style="width: 15%">Número NF-e</td>
                <td style="width: 15%">Versão XML</td>
            </tr>
            <tr class="valor_campo">
                <td>{chNFe}</td>
                <td>{nNF}</td>
                <td>{versao}</td>
            </tr>
        </table>
        <br><br>
        <table>
            <tr class="linha1">
                <td colspan="4" class="linha2">DADOS DA NF-E</td>
            </tr>
            <tr class="linha3">
                <td style="width: 25%">Modelo / Série / Número</td>
                <td style="width: 25%">Data de Emissão</td>
                <td style="width: 25%">Data/Hora de Saída</td>
                <td style="width: 25%">Valor Total da Nota Fiscal</td>
            </tr>
            <tr class="valor_campo">
                <td>{mod} / {serie} / {nNF}</td>
                <td>{dhEmi}</td>
                <td>{dhSaiEnt}</td>
                <td>{vNF}</td>
            </tr>
        </table>
        <br><br>
        <table>
            <tr class="linha1">
                <td colspan="4" class="linha2">EMITENTE</td>
            </tr>
            <tr class="linha3">
                <td style="width: 20%">CNPJ</td>
                <td style="width: 55%">Nome/Razão Social</td>
                <td style="width: 20%">Inscrição Estadual</td>
                <td style="width: 5%">UF</td>
            </tr>
            <tr class="valor_campo">
                <td>{emitCNPJ}</td>
                <td>{emitNome}</td>
                <td>{emitIE}</td>
                <td>{emitUF}</td>
            </tr>
        </table>
        <br><br>
        <table>
            <tr class="linha1">
                <td colspan="4" class="linha2">DESTINATÁRIO</td>
            </tr>
            <tr class="linha3">
                <td style="width: 20%">CPF/CNPJ</td>
                <td style="width: 55%">Nome/Razão Social</td>
                <td style="width: 20%">Inscrição Estadual</td>
                <td style="width: 5%">UF</td>
            </tr>
            <tr class="valor_campo">
                <td>{destCNPJ}</td>
                <td>{destNome}</td>
                <td>{destIE}</td>
                <td>{destUF}</td>
            </tr>
        </table>
        <table>
            <tr class="linha3">
                <td>Destino da Operação</td>
                <td>Consumidor final</td>
                <td colspan="2">Presença do consumidor</td>
            </tr>
            <tr class="valor_campo">
                <td>{idDest}</td>
                <td>{indFinal}</td>
                <td colspan="2">{indPres}</td>
            </tr>
        </table>
        <br><br>
        <table>
            <tr class="linha1">
                <td colspan="4" class="linha2">EMISSÃO</td>
            </tr>
            <tr class="linha3">
                <td style="width: 40%">Processo</td>
                <td style="width: 20%">Versão do Processo</td>
                <td style="width: 20%">Tipo de Emissão</td>
                <td style="width: 20%">Finalidade</td>
            </tr>
            <tr class="valor_campo">
                <td>{procEmi}</td>
                <td>{verProc}</td>
                <td>{tpEmis}</td>
                <td>{finNFe}</td>
            </tr>
        </table>
        <table>
            <tr class="linha3">
                <td style="width: 25%">Natureza da Operação</td>
                <td style="width: 15%">Tipo da Operação</td>
                <td style="width: 25%">Forma de Pagamento</td>
                <td style="width: 35%">Digest da NF-e</td>
            </tr>
            <tr class="valor_campo">
                <td>{natOp}</td>
                <td>{tpNF}</td>
                <td>{indPag}</td>
                <td>{digestValue}</td>
            </tr>
        </table>
        <br><br>
        <table>
            <tr class="linha1">
                <td colspan="4" class="linha2">SITUAÇÃO ATUAL: {situacao} (Ambiente de autorização: {tpAmb})</td>
            </tr>
            <tr class="linha3">
                <td style="width: 55%">Eventos da NF-e</td>
                <td style="width: 20%">Protocolo</td>
                <td style="width: 25%">Data Autorização</td>
            </tr>
            <!-- BEGIN EVENTOS_BLOCK -->
            <tr class="valor_campo">
                <td>{descEvento}</td>
                <td>{nProt}</td>
                <td>{dhEvento}</td>
            </tr>
            <!-- END EVENTOS_BLOCK -->

        </table>
    </div>
    <p>As informações apresentadas neste documento foram obtidas a partir da NFE (XML)
        e do retorno da consulta da NFE junto à SEFAZ autorizadora.</p>

    <p>A autenticidade pode ser verificada no portal nacional da NF-e
        http://www.nfe.fazenda.gov.br/portal ou no site da sefaz autorizadora.</p>
</div>