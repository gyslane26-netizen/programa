<?php

//error_reporting(E_ALL);
ini_set('display_errors', 'Off');

require_once '../bootstrap.php';

use NFePHP\NFe\Tools;
use NFePHP\Common\Certificate;
use NFePHP\Common\Soap\SoapCurl;
use NFePHP\NFe\Make;
use NFePHP\NFe\Exception\DocumentsException;
use NFePHP\NFe\Common\Standardize;
use NFePHP\NFe\Complements;
use NFePHP\NFe\Convert;
use NFePHP\NFe\Factories\Contingency;

include('./config.php'); // carrega configuracoes
include('./funcoes.php'); // carrega funcoes

$pasta = "xml/";

if(empty($content)){
	echo json_encode(array("update" => 0, "error" => "Certificado não encontrado, verifique se fez o upload do certificado digital corretamente."));
  	die;
}

try { 		
	$tools = new Tools($configJson, Certificate::readPfx($content, $senhacert));
	//$tools->soap->httpVersion('1.1');
	$tools->model(strval($_REQUEST['modelo']));
} catch (\Exception $e) {
    echo json_encode(array("update" => 0, "error" => "Certificado: Erro ao ler o certificado, verifique se o certificado e a senha estão corretos.", "error_descript" => $e->getMessage()));
   	die;
}

$name = 'contingencia.txt';
$dadosconti = file_get_contents($name);

$nfe = new Make();
$std = new stdClass();
$error = array();

$ambiente = $dadosempresa["tpAmb"];

if($_REQUEST["emissao"]!="conti" && $_REQUEST["emissao"]!="processa"){

if($_REQUEST["conti"]==1){
  
     $contingency = new Contingency();

    if($dadosconti==""){

      $acronym = $dadosempresa["siglaUF"];
      $motive = 'FALTA DE INTERNET';
      $type = 'OFFLINE';

     $contingencia = $contingency->activate($acronym, $motive, $type);

      file_put_contents($name, $contingencia);
      $dadosconti = $contingencia;

    }else{

      $contingency->load($dadosconti);
    
    }
    
    $varData = json_decode($dadosconti);
    $contdata = date("c", $varData->timestamp);
    $contigenciaativada = true;
    $emissaonotatipo = 9;
  
  } else {

    if($dadosconti!=""){
      $contingency = new Contingency($dadosconti);
      $status = $contingency->deactivate();
     file_put_contents($name, "");
    }

    $contigenciaativada = false;
    $contdata = null;
    $motive = null;
    $emissaonotatipo = 1;
  }


$nnf = $_REQUEST['NF'];

$std->versao = '4.00'; 
$std->Id = '';
$std->pk_nItem = null; 
$elem = $nfe->taginfNFe($std);

$std->cUF = $dadosempresa["codigoUF"]; 
$std->cNF = rand(1000,10000);
$std->natOp = ($_REQUEST['natureza_operacao']=="")? "Vendas" : $_REQUEST['natureza_operacao'];
$modelonf = $_REQUEST['modelo'];
$std->mod = strval($modelonf);
$seriedanota = ($_REQUEST['serie']=="")? 1 : $_REQUEST['serie'];
$std->serie = $seriedanota;
$std->nNF = ($nnf)? $nnf : 1; 
$std->dhEmi = date("c", strtotime($_REQUEST['data_emissao']." ".date("H:i:s"))); 
if($_REQUEST['data_entrada_saida']!="" && $modelonf=="55" && $_REQUEST['data_entrada_saida']!="auto"){
	$data_entrada_saida = date("c", strtotime($_REQUEST['data_entrada_saida']));
}elseif($modelonf=="55" && $_REQUEST['data_entrada_saida']=="auto"){
	$data_entrada_saida = date("c");
}else{
	$data_entrada_saida = 	NULL;
}
$std->dhSaiEnt = $data_entrada_saida;
$std->tpNF = ($_REQUEST['tipooperacao']=="")? 1 : $_REQUEST['tipooperacao'];  // entrada ou saida
$std->idDest = ($_REQUEST['destinooperacao']=="")? 1 : $_REQUEST['destinooperacao'];  
$std->cMunFG = $dadosempresa["ccidade"];
$std->tpImp =  $_REQUEST['impressao'];
$std->tpEmis = $emissaonotatipo; 
$std->cDV = 3;

$ambiente = $dadosempresa["tpAmb"];
$std->tpAmb = $ambiente; 
$std->finNFe = RmvString($_REQUEST['finalidade']); 
$std->indFinal = ($_REQUEST['consumidorfinal'] == "")? 0 : $_REQUEST['consumidorfinal']; // ??
$std->indPres = ($_REQUEST['pedido']['presenca']=="")? 1 : $_REQUEST['pedido']['presenca'];
$std->procEmi = '0';
$std->indIntermed = ($_REQUEST['pedido']['intermediario']!="")? RmvString($_REQUEST['pedido']['intermediario']) : null; // INTERMEDIARIO
$std->verProc = "TudoNet1";
$std->dhCont = $contdata;
$std->xJust =  $motive;
$elem = $nfe->tagide($std);

/**     REFERENCIA A NOTA                        **/  
if($_REQUEST['nfe_referenciada']!=""){
  $std->refNFe = strval($_REQUEST['nfe_referenciada']);
  $elem = $nfe->tagrefNFe($std);
}

$std->xNome = $dadosempresa["razaosocial"];
$std->xFant= $dadosempresa["fantasia"];
$std->IE = $dadosempresa["ie"];
$std->IEST = "";
$std->IM = $dadosempresa["im"];
$std->CNAE = $dadosempresa["cnae"];
$std->CRT = $dadosempresa["crt"];
$std->CNPJ = $dadosempresa["cnpj"];
//$std->CPF;
$elem = $nfe->tagemit($std);

$std->xLgr =  $dadosempresa["rua"];
$std->nro =  $dadosempresa["numero"];
$std->xCpl = $dadosempresa["compl"];
$std->xBairro =  $dadosempresa["bairro"];
$std->cMun =  $dadosempresa["ccidade"];
$std->xMun =  $dadosempresa["cidade"];
$std->UF =  $dadosempresa["siglaUF"];
$std->CEP =  $dadosempresa["cep"];
$std->cPais = "1058";
$std->xPais = "BRASIL";
$std->fone =  $dadosempresa["fone"];
$elem = $nfe->tagenderEmit($std);


/**  --- DADOS DO COMPRADOR  **/     
if($_REQUEST['cliente']['email']!=""){
	$std->email = strval($_REQUEST['cliente']['email']);   // E-mail do cliente para envio da NF-e ** OPC
}elseif($_REQUEST['cliente']['email']=="" && $modelonf == 55){
	$std->email = strval(RmvString($_REQUEST['cliente']['cnpj'].$_REQUEST['cliente']['cpf'])."@email.com.br");   // E-mail do cliente para envio da NF-e ** OPC
}

if($_REQUEST['cliente']['cnpj']=="" && $_REQUEST['cliente']['cpf']=="" && $_REQUEST['cliente']['id_estrangeiro']=="" && $modelonf == 55){	$error[] = "CPF, CNPJ ou ID estrangeiro obrigatório para NF"; }


if($_REQUEST['cliente']['indIEDest']!=""){ 
	$indIEDest = $_REQUEST['cliente']['indIEDest'];
}else{
	if(strtolower($_REQUEST['cliente']['ie'])=="isento"){ 
		$indIEDest = "2";
	}elseif($_REQUEST['cliente']['ie']=="" || $_REQUEST['cliente']['ie']=="0"){ 
		$indIEDest = "9";
	}else{ 
		$indIEDest = "1"; 
	}
}

if($_REQUEST['cliente']['tipoPessoa'] == "J"){ // TOMADOR DA NOTA PESSOA JURIDICA

	$nomem = $_REQUEST['cliente']['contato'];
	$std->xNome = $nomem; 
	$std->IE = ($_REQUEST['cliente']['ie']!="")? strtoupper(RmvString($_REQUEST['cliente']['ie'], 2)) : null; 
	//$std->ISUF;
	//substituto_tributario 
	//suframa
	$std->IM = NULL; 
	$std->indIEDest = $indIEDest; 
	$std->CPF =""; 
	$std->CNPJ = RmvString($_REQUEST['cliente']['cnpj'], 2); 	// (pessoa jurudica) Numero do CNPJ  	
	$elem = $nfe->tagdest($std);

}elseif($_REQUEST['cliente']['tipoPessoa'] == "F" && $_REQUEST['cliente']['cpf']!=""){ // TOMADOR DA NOTA PESSOA FISICA

	$nomem = $_REQUEST['cliente']['contato'];
	$std->xNome = ($nomem=="")? null : $nomem; 
	$std->IE = ($_REQUEST['cliente']['ie']!="")? strtoupper(RmvString($_REQUEST['cliente']['ie'], 2)) : null; 
	$std->IM = NULL; 
	$std->indIEDest = $indIEDest; 
	$std->CNPJ = "";
	$std->CPF = RmvString($_REQUEST['cliente']['cpf'], 2); 	
	$elem = $nfe->tagdest($std);

}elseif($_REQUEST['cliente']['tipoPessoa'] == "E"){ // TOMADOR DA NOTA ESTRANGEIRO

	$nomem = $_REQUEST['cliente']['contato'];
	$std->xNome = ($nomem=="")? null : $nomem; 
	$std->IE = NULL;  
	$std->IM = NULL; 
	$std->indIEDest = NULL; 
	$std->CNPJ = "";
	$std->CPF = ""; 
	$std->idEstrangeiro = ($_REQUEST['cliente']['id_estrangeiro']!="") ? $_REQUEST['cliente']['id_estrangeiro'] : null; 
	$exterior = true;
	$elem = $nfe->tagdest($std);

}else{

}

 
if($_REQUEST['cliente']['endereco']!=""){

	$std->xLgr = ($_REQUEST['cliente']['endereco'])? $_REQUEST['cliente']['endereco'] : $error[] = "Endereço não foi informado";   
	$std->nro = RmvString($_REQUEST['cliente']['numero']);      
	$std->xCpl = ($_REQUEST['cliente']['complemento'])? $_REQUEST['cliente']['complemento'] : null;
	$std->xBairro = ($_REQUEST['cliente']['bairro'])? $_REQUEST['cliente']['bairro'] : null;
	$std->UF =  ($exterior && $_REQUEST['cliente']['uf']=="")? "EX" : RmvString($_REQUEST['cliente']['uf']);
	$std->cMun = ($exterior && $_REQUEST['cliente']['cidade_cod']=="")? "9999999" : RmvString($_REQUEST['cliente']['cidade_cod']);
	$std->xMun = ($exterior && $_REQUEST['cliente']['cidade']!="")? "EXTERIOR" : RmvString($_REQUEST['cliente']['cidade']); 
	$std->CEP = (!$exterior && $_REQUEST['cliente']['cep']!="") ? RmvString($_REQUEST['cliente']['cep'], 2) : null;
	$std->cPais = ($_REQUEST['cliente']['cod_pais'])? $_REQUEST['cliente']['cod_pais'] : null;
	$std->xPais = ($_REQUEST['cliente']['nome_pais'])? $_REQUEST['cliente']['nome_pais'] : null;
	$std->fone = ($_REQUEST['cliente']['telefone']!="")? RmvString($_REQUEST['cliente']['telefone'], 1) : ""; 
	$elem = $nfe->tagenderDest($std);
	
}

$valortotal = 0.00;
$descontototal = 0.00;
$pesototal = 0.00;
$totalipi = 0.00;
$totalpis = 0.00;
$totalcofins = 0.00;
$outrostotal = 0.00;
$segurototal = 0.00;
$fretetotal = 0.00;
$vBCICMS = 0.00;

$x = 0;
$y = 0;

// total de produtos
if(!empty($_REQUEST['produtos'])){
	$y = count($_REQUEST['produtos']);
}

$valortotalProd = 0.00;
$valortotalServ = 0.00;
$vtotalIPI = 0.00;
$vtotalISS = 0.00;
$vtotalISS_BC = 0.00;
$vtotalISS_outros = 0.00;
$vtotalISS_deducao = 0.00;
$vtotalISS_DescIncond = 0.00;
$vtotalISS_DescCond = 0.00;
$vtotalISS_retencao = 0.00;
$vtotalISSdeducao = 0.00;
$vtotalPIS = 0.00;
$vtotalCONFINS = 0.00;
$vtotalII = 0.00;
$vtotalIOF = 0.00;
$vICMSTotal = 0.00;
$desconto_restante =0;

if($_REQUEST['pedido']['desconto']!="" && $_REQUEST['pedido']['desconto']>0){	
	$descontototal = $_REQUEST['pedido']['desconto'];
	$desconto_restante = $descontototal;
}

if($_REQUEST['pedido']['outras_despesas']!="" && $_REQUEST['pedido']['outras_despesas']>0){	
	$outrostotal = $_REQUEST['pedido']['outras_despesas'];

	$outrosPorItem = number_format((float)($outrostotal / $y), 2, '.', '');
	$outrosTotalCalculado = ($outrosPorItem * $y);
	$outrosItemFinal = number_format((float)($outrostotal - $outrosTotalCalculado ), 2, '.', '');
}else{
	$outrosPorItem = null;
	$outrosItemFinal = null;
}
	
if(RmvString($_REQUEST['pedido']['frete'])!=""  && $_REQUEST['pedido']['frete']>0){	
	$fretetotal = $_REQUEST['pedido']['frete'];	

	$fretePorItem = number_format((float)($fretetotal / $y), 2, '.', '');
	$freteTotalCalculado = ($fretePorItem * $y);
	$freteItemFinal = number_format((float)($fretetotal - $freteTotalCalculado ), 2, '.', '');
}else{
	$fretePorItem = null;
	$freteItemFinal = null;
}

if($_REQUEST['transporte']['seguro']!="" && $_REQUEST['transporte']['seguro']>0){	
	$segurototal = $_REQUEST['transporte']['seguro'];	

	$seguroPorItem = number_format((float)($segurototal / $y), 2, '.', '');
	$seguroTotalCalculado = ($seguroPorItem * $y);
	$seguroItemFinal = number_format((float)($segurototal - $seguroTotalCalculado ), 2, '.', '');
}else{
	$seguroPorItem = null;
	$seguroItemFinal = null;
}

foreach($_REQUEST['produtos'] as $prod){	

$item = $x + 1;

$valor_total_produtos = $_REQUEST['produtos'][$x]['total'];
$frete_item = ($item!=$y)? $fretePorItem : ((!empty($fretePorItem)) ? number_format((float)($fretePorItem + $freteeItemFinal), 2, '.', '') : null);
$seguro_item = ($item!=$y)? $seguroPorItem : ((!empty($seguroPorItem)) ? number_format((float)($seguroPorItem + $seguroItemFinal), 2, '.', '') : null);
if($desconto_restante>0){
	if($desconto_restante > $valor_total_produtos){
		$desconto_aplicar = $valor_total_produtos;
	} else { 
		$desconto_aplicar = $desconto_restante;
	}
	$desconto_restante -= $desconto_aplicar;
	$desconto_item = (float) number_format(round($desconto_aplicar, 2), 2, '.', '');
	
} else { 
	$desconto_item = null;
}

$outros_item = ($item!=$y)? $outrosPorItem : ((!empty($outrosPorItem)) ? number_format((float)($outrosPorItem + $outrosItemFinal), 2, '.', '') : null);

$codigo = RmvString($_REQUEST['produtos'][$x]['item'], 2);	
$nomeproduto = RmvString($_REQUEST['produtos'][$x]['nome']);   
$ncm = RmvString($_REQUEST['produtos'][$x]['ncm'], 2);     
$cfop = ($_REQUEST['produtos'][$x]["impostos"]["icms"]["codigo_cfop"]!="")? RmvString($_REQUEST['produtos'][$x]["impostos"]["icms"]["codigo_cfop"], 2) : '5102'; // CFOP
$valor = RmvString($_REQUEST['produtos'][$x]['subtotal']);     
$quantidade = RmvString($_REQUEST['produtos'][$x]['quantidade']);
$un = RmvString($_REQUEST['produtos'][$x]['unidade']);   
$ean = RmvString($_REQUEST['produtos'][$x]['ean']);  
$peso = 0.300; // sair da configuração

$std->item = $item; //item da NFe
$std->cProd = ($ean)? $ean : $codigo;
$std->cEAN = "SEM GTIN";
$std->xProd = $nomeproduto;
$std->NCM = $ncm;
$std->cBenf = ""; 
$std->EXTIPI = "";
$std->CFOP = $cfop;
$std->uCom = $un; 
$std->qCom = $quantidade;
$std->vUnCom = $valor; // valor unitario
$std->vProd = $valor_total_produtos; 
$std->cEANTrib = "SEM GTIN";
$std->uTrib = $un;
$std->qTrib = $quantidade;
$std->vUnTrib = $valor;  // valor unitario
$std->vFrete = (!empty($frete_item) && $frete_item>0)? $frete_item : '';
$std->vSeg = (!empty($seguro_item) && $seguro_item>0)? $seguro_item : '';
$std->vDesc = (!empty($desconto_item) && $desconto_item>0)? $desconto_item : null; // no acepta 0.00
$std->vOutro = (!empty($outros_item) && $outros_item>0)? $outros_item : '';
$std->indTot = 1; 
$std->xPed = $_REQUEST['pedido']['numero_interno']; // test
$std->nItemPed = $item; // test
$std->nFCI = ($_REQUEST['produtos'][$x]['nfci'])? $_REQUEST['produtos'][$x]['nfci'] : ""; 
$elem = $nfe->tagprod($std);
$valor_total_tributavel = $valor_total_produtos;

if($_REQUEST['produtos'][$x]["tipo_item"]=="2"){
	$valortotalServ += $valor_total_produtos; 
}else{
	$valortotalProd += $valor_total_produtos; 
}

/* INFORMACOES ADICIONAIS DO ITEM */
if($_REQUEST['produtos'][$x]['informacoes_adicionais']){
	$std->item = $item; //item da NFe
	$std->infAdProd = strval($_REQUEST['produtos'][$x]['informacoes_adicionais']);
	$elem = $nfe->taginfAdProd($std);
}

/**   INFORMACOES NVE     **/  
if($_REQUEST['produtos'][$x]['nve']){
	$std->item = $item; //item da NFe
	$std->NVE = $_REQUEST['produtos'][$x]['nve'];
	$elem = $nfe->tagNVE($std);
}

if($_REQUEST['produtos'][$x]['cest']!="" || $_REQUEST['produtos'][$x]['ind_escala']!="" || $_REQUEST['produtos'][$x]['cnpj_fabricante']!=""){
	$std->item = $item; //item da NFe
	$std->CEST = ($_REQUEST['produtos'][$x]['cest']=="")? NULL : substr(RmvString($_REQUEST['produtos'][$x]['cest'], 2), 0, 7);
	$std->indEscala = ($_REQUEST['produtos'][$x]['ind_escala']=="")? NULL : $_REQUEST['produtos'][$x]['ind_escala']; 
	$std->CNPJFab = ($_REQUEST['produtos'][$x]['cnpj_fabricante']=="")? NULL : RmvString($_REQUEST['produtos'][$x]['cnpj_fabricante'], 2);
	$nfe->tagCEST($std);
}

if($_REQUEST['produtos'][$x]['papelimune']['nRECOPI']){
	$std->item = $item; //item da NFe
	$std->nRECOPI = $_REQUEST['produtos'][$x]['papelimune']['nRECOPI'];
	$nfe->tagRECOPI($std);
}


/**  TAG Importacao  */
if(strval($_REQUEST['produtos'][$x]["ndoc_importacao"])){
	$std->item = $item;
	$std->nDI = strval($_REQUEST['produtos'][$x]["ndoc_importacao"]);
	$std->dDI = strval($_REQUEST['produtos'][$x]["ddoc_importacao"]);
	$std->xLocDesemb = $_REQUEST['produtos'][$x]["local_desembaracoo"];
	$std->UFDesemb = strval($_REQUEST['produtos'][$x]["uf_desembaraco"]);
	$std->dDesemb = strval($_REQUEST['produtos'][$x]["data_desembaraco"]);
	$std->tpViaTransp = strval($_REQUEST['produtos'][$x]["via_transporte"]);
	$std->vAFRMM = ($_REQUEST['produtos'][$x]["afrmm"]!="")? strval($_REQUEST['produtos'][$x]["afrmm"]) : null;
	$std->tpIntermedio = strval($_REQUEST['produtos'][$x]["intermediacao"]);
	$std->CNPJ = ($_REQUEST['produtos'][$x]["cnpj_terceiro"]!="")? strval($_REQUEST['produtos'][$x]["cnpj_terceiro"]) : null;
	$std->UFTerceiro = ($_REQUEST['produtos'][$x]["uf_terceiro"]!="")? strval($_REQUEST['produtos'][$x]["uf_terceiro"]) : null;
	$std->cExportador = ($_REQUEST['produtos'][$x]["cod_exportador"]!="")? strval($_REQUEST['produtos'][$x]["cod_exportador"]) : null;
	$elem = $nfe->tagDI($std);
	
	// TAG ADICOES
	$std->item = $item;
	$std->nDI = strval($_REQUEST['produtos'][$x]["ndoc_importacao"]);
	$std->nAdicao = strval($_REQUEST['produtos'][$x]["adicao"]);
	$std->nSeqAdic = strval($_REQUEST['produtos'][$x]["seq_adicao"]);
	$std->cFabricante = strval($_REQUEST['produtos'][$x]["fabricante"]);
	$std->vDescDI = null;
	$std->nDraw = null;
	$elem = $nfe->tagadi($std);
}

/**       EXPORTACAO   DRAWBACK        **/    
if($_REQUEST['produtos'][$x]["drawback"]){
	$std->item = $item; //item da NFe
	$std->nRE = ($_REQUEST['produtos'][$x]["reg_exportacao"]!="")? $_REQUEST['produtos'][$x]["reg_exportacao"] : null;
	$std->chNFe = ($_REQUEST['produtos'][$x]["nfe_exportacao"]!="")? $_REQUEST['produtos'][$x]["nfe_exportacao"] : null;
	$std->qExport = null;
	$std->nDraw = strval($_REQUEST['produtos'][$x]["drawback"]);
  	$elem = $nfe->tagdetExport($std);
  

/**         EXPORTACAOO INDIRETA       **/     
} elseif($_REQUEST['produtos'][$x]["drawback"] && ($_REQUEST['produtos'][$x]["reg_exportacao"] || $_REQUEST['produtos'][$x]["nfe_exportacao"] || $_REQUEST['produtos'][$x]["qtd_exportacao"])){
	$std->item = 1; //item da NFe
	$std->nRE = ($_REQUEST['produtos'][$x]["qtd_exportacao"]!="")? strval($_REQUEST['produtos'][$x]["reg_exportacao"]) : null;
	$std->chNFe = ($_REQUEST['produtos'][$x]["nfe_exportacao"]!="")? strval($_REQUEST['produtos'][$x]["nfe_exportacao"]): null;
	$std->qExport = ($_REQUEST['produtos'][$x]["qtd_exportacao"]!="")? strval($_REQUEST['produtos'][$x]["qtd_exportacao"]): null;
	$elem = $nfe->tagdetExportInd($std);
}


/* TAG VEICULO NOVO */
if($_REQUEST['produtos'][$x]['veiculos_novos']!=""){
  $std->item = $item; //item da NFe
  $std->tpOp = ($_REQUEST['produtos'][$x]['veiculos_novos']['tpOp']) ? $_REQUEST['produtos'][$x]['veiculos_novos']['tpOp'] : null;
  $std->chassi = ($_REQUEST['produtos'][$x]['veiculos_novos']['chassi']) ? $_REQUEST['produtos'][$x]['veiculos_novos']['chassi'] : null;
  $std->cCor= ($_REQUEST['produtos'][$x]['veiculos_novos']['cCor']) ? $_REQUEST['produtos'][$x]['veiculos_novos']['cCor'] : null;
  $std->xCor= ($_REQUEST['produtos'][$x]['veiculos_novos']['xCor']) ? $_REQUEST['produtos'][$x]['veiculos_novos']['xCor'] : null;
  $std->pot= ($_REQUEST['produtos'][$x]['veiculos_novos']['pot']) ? $_REQUEST['produtos'][$x]['veiculos_novos']['pot'] : null;
  $std->cilin= ($_REQUEST['produtos'][$x]['veiculos_novos']['cilin']) ? $_REQUEST['produtos'][$x]['veiculos_novos']['cilin'] : null;
  $std->pesoL= ($_REQUEST['produtos'][$x]['veiculos_novos']['pesoL']) ? $_REQUEST['produtos'][$x]['veiculos_novos']['pesoL'] : null;
  $std->pesoB= ($_REQUEST['produtos'][$x]['veiculos_novos']['pesoB']) ? $_REQUEST['produtos'][$x]['veiculos_novos']['pesoB'] : null;
  $std->nSerie= ($_REQUEST['produtos'][$x]['veiculos_novos']['nSerie']) ? $_REQUEST['produtos'][$x]['veiculos_novos']['nSerie'] : null;
  $std->tpComb= ($_REQUEST['produtos'][$x]['veiculos_novos']['tpComb']) ? $_REQUEST['produtos'][$x]['veiculos_novos']['tpComb'] : null;
  $std->nMotor= ($_REQUEST['produtos'][$x]['veiculos_novos']['nMotor']) ? $_REQUEST['produtos'][$x]['veiculos_novos']['nMotor'] : null;
  $std->CMT= ($_REQUEST['produtos'][$x]['veiculos_novos']['CMT']) ? $_REQUEST['produtos'][$x]['veiculos_novos']['CMT'] : null;
  $std->dist= ($_REQUEST['produtos'][$x]['veiculos_novos']['dist']) ? $_REQUEST['produtos'][$x]['veiculos_novos']['dist'] : null;
  $std->anoMod= ($_REQUEST['produtos'][$x]['veiculos_novos']['anoMod']) ? $_REQUEST['produtos'][$x]['veiculos_novos']['anoMod'] : null;
  $std->anoFab= ($_REQUEST['produtos'][$x]['veiculos_novos']['anoFab']) ? $_REQUEST['produtos'][$x]['veiculos_novos']['anoFab'] : null;
  $std->tpPint= ($_REQUEST['produtos'][$x]['veiculos_novos']['tpPint']) ? $_REQUEST['produtos'][$x]['veiculos_novos']['tpPint'] : null;
  $std->tpVeic= ($_REQUEST['produtos'][$x]['veiculos_novos']['tpVeic']) ? $_REQUEST['produtos'][$x]['veiculos_novos']['tpVeic'] : null;
  $std->espVeic= ($_REQUEST['produtos'][$x]['veiculos_novos']['espVeic']) ? $_REQUEST['produtos'][$x]['veiculos_novos']['espVeic'] : null;
  $std->VIN= ($_REQUEST['produtos'][$x]['veiculos_novos']['VIN']) ? $_REQUEST['produtos'][$x]['veiculos_novos']['VIN'] : null;
  $std->condVeic= ($_REQUEST['produtos'][$x]['veiculos_novos']['condVeic']) ? $_REQUEST['produtos'][$x]['veiculos_novos']['condVeic'] : null;
  $std->cMod= ($_REQUEST['produtos'][$x]['veiculos_novos']['cMod']) ? $_REQUEST['produtos'][$x]['veiculos_novos']['cMod'] : null;
  $std->cCorDENATRAN= ($_REQUEST['produtos'][$x]['veiculos_novos']['cCorDENATRAN']) ? $_REQUEST['produtos'][$x]['veiculos_novos']['cCorDENATRAN'] : null;
  $std->lota= ($_REQUEST['produtos'][$x]['veiculos_novos']['lota']) ? $_REQUEST['produtos'][$x]['veiculos_novos']['lota'] : null;
  $std->tpRest= ($_REQUEST['produtos'][$x]['veiculos_novos']['tpRest']) ? $_REQUEST['produtos'][$x]['veiculos_novos']['tpRest'] : null;
  $nfe->tagveicProd($std);
  }
  
  
  /* ARMAMENTO */
  if($_REQUEST['produtos'][$x]['armamentos']['tpArma']!="") {
  $std->item = $item; //item da NFe
  $std->nAR; //Indicativo de numero da arma
  $std->tpArma = ($_REQUEST['produtos'][$x]['armamentos']['tpArma']) ? $_REQUEST['produtos'][$x]['armamentos']['tpArma'] : null;
  $std->nSerie = ($_REQUEST['produtos'][$x]['armamentos']['nSerie']) ? $_REQUEST['produtos'][$x]['armamentos']['nSerie'] : null;
  $std->nCano = ($_REQUEST['produtos'][$x]['armamentos']['nCano']) ? $_REQUEST['produtos'][$x]['armamentos']['nCano'] : null;
  $std->descr = ($_REQUEST['produtos'][$x]['armamentos']['descr']) ? $_REQUEST['produtos'][$x]['armamentos']['descr'] : null;
  $nfe->tagarma($std);
  }
  
  
  /* COMBUSTIVEIS */
  if($_REQUEST['produtos'][$x]['combustiveis']!=""){
  $std->item = $item; //item da NFe
  $std->cProdANP = ($_REQUEST['produtos'][$x]['combustiveis']['cProdANP']) ? $_REQUEST['produtos'][$x]['combustiveis']['cProdANP'] : null;
  $std->descANP= ($_REQUEST['produtos'][$x]['combustiveis']['descANP']) ? $_REQUEST['produtos'][$x]['combustiveis']['descANP'] : null; //incluido no layout 4.00
  $std->pGLP = ($_REQUEST['produtos'][$x]['combustiveis']['pGLP']) ? $_REQUEST['produtos'][$x]['combustiveis']['pGLP'] : null;//incluido no layout 4.00
  $std->pGNn= ($_REQUEST['produtos'][$x]['combustiveis']['pGNn']) ? $_REQUEST['produtos'][$x]['combustiveis']['pGNn'] : null; //incluido no layout 4.00
  $std->pGNi= ($_REQUEST['produtos'][$x]['combustiveis']['pGNi']) ? $_REQUEST['produtos'][$x]['combustiveis']['pGNi'] : null;//incluido no layout 4.00
  $std->vPart= ($_REQUEST['produtos'][$x]['combustiveis']['vPart']) ? $_REQUEST['produtos'][$x]['combustiveis']['vPart'] : null; //incluido no layout 4.00
  $std->CODIF= ($_REQUEST['produtos'][$x]['combustiveis']['CODIF']) ? $_REQUEST['produtos'][$x]['combustiveis']['CODIF'] : null;
  $std->qTemp= ($_REQUEST['produtos'][$x]['combustiveis']['qTemp']) ? $_REQUEST['produtos'][$x]['combustiveis']['qTemp'] : null;
  $std->UFCons= ($_REQUEST['produtos'][$x]['combustiveis']['UFCons']) ? $_REQUEST['produtos'][$x]['combustiveis']['UFCons'] : null;
  $std->qBCProd= ($_REQUEST['produtos'][$x]['combustiveis']['qBCProd']) ? $_REQUEST['produtos'][$x]['combustiveis']['qBCProd'] : null;
  $std->vAliqProd= ($_REQUEST['produtos'][$x]['combustiveis']['vAliqProd']) ? $_REQUEST['produtos'][$x]['combustiveis']['vAliqProd'] : null;
  $std->vCIDE= ($_REQUEST['produtos'][$x]['combustiveis']['vCIDE']) ? $_REQUEST['produtos'][$x]['combustiveis']['vCIDE'] : null;
  $nfe->tagcomb($std);
  }



/**    IMPOSTOS  DO PRODUTO  **/      

// SERVIÇO -------------------------

if($_REQUEST['produtos'][$x]["tipo_item"]=="2"){

	/*    IMPOSTO SERVICOS DE QUALQUER NATUREZA  */                                                                           
  
	$valorISSQNali = ($_REQUEST['produtos'][$x]["impostos"]["issqn"]["vAliq"]!="")? (float) RmvString($_REQUEST['produtos'][$x]["impostos"]["issqn"]["vAliq"],3) : 0;
  
	//var_dump($valorISSQNali); 
	$std->item = $item; //item da NFe
	$std->vBC = ($valorISSQNali>0)? $valor_total_tributavel: 0.00;
	$std->vAliq = ($valorISSQNali>0)? $valorISSQNali : 0.00; 
	$std->vISSQN = ($valorISSQNali>0)? CalcularPorcent($valor_total_tributavel, $valorISSQNali) : 0.00; 
	$std->cMunFG = ($_REQUEST['produtos'][$x]["impostos"]["issqn"]["cMunFG"]!="")? $_REQUEST['produtos'][$x]["impostos"]["issqn"]["cMunFG"] : null; 
	$std->cListServ = ($_REQUEST['produtos'][$x]["impostos"]["issqn"]["cListServ"]!="")? $_REQUEST['produtos'][$x]["impostos"]["issqn"]["cListServ"] : null; 
	$std->vDeducao = ($_REQUEST['produtos'][$x]["impostos"]["issqn"]["vDeducao"]!="")? number_format((float)(RmvString($_REQUEST['produtos'][$x]["impostos"]["issqn"]["vDeducao"]) / $y), 2, '.', '') : null; 
	$std->vOutro  = ($_REQUEST['produtos'][$x]["impostos"]["issqn"]["vOutro"]!="")? number_format((float)(RmvString($_REQUEST['produtos'][$x]["impostos"]["issqn"]["vOutro"]) / $y), 2, '.', '') :  null; 
	$std->vDescIncond  = ($_REQUEST['produtos'][$x]["impostos"]["issqn"]["vDescIncond"]!="")? number_format((float)(RmvString($_REQUEST['produtos'][$x]["impostos"]["issqn"]["vDescIncond"]) / $y), 2, '.', '') : null;
	$std->vDescCond  = ($_REQUEST['produtos'][$x]["impostos"]["issqn"]["vDescCond"]!="")? number_format((float)(RmvString($_REQUEST['produtos'][$x]["impostos"]["issqn"]["vDescCond"]) / $y), 2, '.', '')  : null; 
	$std->vISSRet  = ($_REQUEST['produtos'][$x]["impostos"]["issqn"]["vISSRet"]!="")? number_format((float)(RmvString($_REQUEST['produtos'][$x]["impostos"]["issqn"]["vISSRet"]) / $y), 2, '.', '') : null; 
	$std->indISS = ($_REQUEST['produtos'][$x]["impostos"]["issqn"]["indISS"]!="")? $_REQUEST['produtos'][$x]["impostos"]["issqn"]["indISS"] : 2; 
	$std->cServico  = ($_REQUEST['produtos'][$x]["impostos"]["issqn"]["cServico"]!="")? $_REQUEST['produtos'][$x]["impostos"]["issqn"]["cServico"] : null; 
	$std->cMun  = ($_REQUEST['produtos'][$x]["impostos"]["issqn"]["cMun"]!="")? $_REQUEST['produtos'][$x]["impostos"]["issqn"]["cMun"] : null; 
	$std->cPais = ($_REQUEST['produtos'][$x]["impostos"]["issqn"]["cPais"]!="")? $_REQUEST['produtos'][$x]["impostos"]["issqn"]["cPais"] : null; 
	$std->nProcesso = ($_REQUEST['produtos'][$x]["impostos"]["issqn"]["nProcesso"]!="")? $_REQUEST['produtos'][$x]["impostos"]["issqn"]["nProcesso"] : null; 
	$std->indIncentivo =  ($_REQUEST['produtos'][$x]["impostos"]["issqn"]["indIncentivo"]!="")? $_REQUEST['produtos'][$x]["impostos"]["issqn"]["indIncentivo"] : null;
		  
	$vtotalISS += $std->vISSQN;
	$vtotalISS_BC += $std->vBC;
	$vtotalISS_outros += $std->vOutro;
	$vtotalISS_deducao += $std->vDeducao;
	$vtotalISS_DescIncond += $std->vDescIncond;
	$vtotalISS_DescCond += $std->vDescCond;
	$vtotalISS_retencao += $std->vISSRet;
  
	$elem = $nfe->tagISSQN($std);

} else { // PRODUTOS ---------------------

	/**    ICMS  **/                                                                                    
	// p.... significa alicota %
	// bc...
	// v... es valor -> para cacular usamos CalcularPorcent calula o valor baseado na base de calculo e alicota
	$std->item = $item;
	$std->orig = $_REQUEST['produtos'][$x]["impostos"]["icms"]["origem"]; 

	if($_REQUEST['produtos'][$x]["impostos"]["icms"]["situacao_tributaria"]<100){ 

		$std->CST = strval($_REQUEST['produtos'][$x]["impostos"]["icms"]["situacao_tributaria"]);
		$std->modBC = ($_REQUEST['produtos'][$x]["impostos"]["icms"]["modBC"]!="")? strval($_REQUEST['produtos'][$x]["impostos"]["icms"]["modBC"]) : null;
		$std->vBC = ($_REQUEST['produtos'][$x]["impostos"]["icms"]["pICMS"]!="")? $valor_total_tributavel : null;
		$std->pRedBC = ($_REQUEST['produtos'][$x]["impostos"]["icms"]["pRedBC"]!="")? RmvString($_REQUEST['produtos'][$x]["impostos"]["icms"]["pRedBC"],3) : null;
		$std->pICMS = ($_REQUEST['produtos'][$x]["impostos"]["icms"]["pICMS"]!="")? RmvString($_REQUEST['produtos'][$x]["impostos"]["icms"]["pICMS"],3) : null;
		$std->vICMS = ($_REQUEST['produtos'][$x]["impostos"]["icms"]["pICMS"]!="")? CalcularPorcent($valor_total_tributavel, RmvString($_REQUEST['produtos'][$x]["impostos"]["icms"]["pICMS"],3)) : null;
		$std->pFCP = null;
		$std->vFCP = null;
		$std->vBCFCP = null;
		$std->modBCST = ($_REQUEST['produtos'][$x]["impostos"]["icms"]["NmodBCST"]!="")? strval($_REQUEST['produtos'][$x]["impostos"]["icms"]["NmodBCST"]) : null;
		$std->pMVAST =  ($_REQUEST['produtos'][$x]["impostos"]["icms"]["NpMVAST"])!=""? RmvString($_REQUEST['produtos'][$x]["impostos"]["icms"]["NpMVAST"],3) : null;
		$std->pRedBCST = ($_REQUEST['produtos'][$x]["impostos"]["icms"]["NpRedBCST"]!="")? RmvString($_REQUEST['produtos'][$x]["impostos"]["icms"]["NpRedBCST"],3) : null; // 
		$std->vBCST = ($_REQUEST['produtos'][$x]["impostos"]["icms"]["aliquota_reducao_st"]!="")? $valor_total_tributavel : null; // ?????
		$std->pICMSST = ($_REQUEST['produtos'][$x]["impostos"]["icms"]["NpICMSST"]!="")? RmvString($_REQUEST['produtos'][$x]["impostos"]["icms"]["NpICMSST"],3) : null;
		$std->vICMSST = ($_REQUEST['produtos'][$x]["impostos"]["icms"]["NpICMSST"]!="")? CalcularPorcent($valor_total_tributavel, RmvString($_REQUEST['produtos'][$x]["impostos"]["icms"]["NpICMSST"],3)) : null;
		$std->vBCFCPST = ($_REQUEST['produtos'][$x]["impostos"]["icms"]["pFCPST"]!="")? $valor_total_tributavel : null;
		$std->pFCPST = ($_REQUEST['produtos'][$x]["impostos"]["icms"]["pFCPST"]!="")? RmvString($_REQUEST['produtos'][$x]["impostos"]["icms"]["pFCPST"],3) : null;
		$std->vFCPST = ($_REQUEST['produtos'][$x]["impostos"]["icms"]["pFCPST"]!="")? CalcularPorcent($valor_total_tributavel, RmvString($_REQUEST['produtos'][$x]["impostos"]["icms"]["pFCPST"],3)) : null;
		$std->vICMSDeson = null;
		$std->motDesICMS = null;
		$std->pRedBC = null;
		$std->vICMSOp = null;
		$std->pDif = null;
		$std->vICMSDif = null;
		$std->vBCSTRet = ($_REQUEST['produtos'][$x]["impostos"]["icms"]["vBCSTRet"]!="")? RmvString($_REQUEST['produtos'][$x]["impostos"]["icms"]["vBCSTRet"]) : '0.00'; // Base de Cálculo ICMS Retido na operação anterior
		$std->pST = ($_REQUEST['produtos'][$x]["impostos"]["icms"]["pST"]!="")? RmvString($_REQUEST['produtos'][$x]["impostos"]["icms"]["pST"], 3) : '0.00'; // Alíquota suportada pelo Consumidor Final
		$std->vBCFCPSTRet = null;
		$std->pFCPSTRet = null;
		$std->vFCPSTRet = null;
		$std->pRedBCEfet = null;
		$std->vBCEfet = null;
		$std->pICMSEfet = null;
		$std->vICMSEfet = null;
		$std->vICMSSubstituto = ($_REQUEST['produtos'][$x]["impostos"]["icms"]["vICMSSubstituto"]!="")? $_REQUEST['produtos'][$x]["impostos"]["icms"]["vICMSSubstituto"] : null; // Valor do ICMS próprio do Substituto
		$std->vICMSSTRet = ($_REQUEST['produtos'][$x]["impostos"]["icms"]["vICMSSTRet"]!="")? $_REQUEST['produtos'][$x]["impostos"]["icms"]["vICMSSTRet"] : null; // Valor do ICMS ST Retido na operação anterior
		$vBCICMS +=$std->vBC;
		$vICMSTotal += $std->vICMS;
	    $elem = $nfe->tagICMS($std);

	}else{ // SIMPLES NACIONAL

		$std->CSOSN = ($_REQUEST['produtos'][$x]["impostos"]["icms"]["situacao_tributaria"]!="")? strval($_REQUEST['produtos'][$x]["impostos"]["icms"]["situacao_tributaria"]) : "102";
		$std->pCredSN = ($_REQUEST['produtos'][$x]["impostos"]["icms"]["NpCredSN"]!="")? RmvString($_REQUEST['produtos'][$x]["impostos"]["icms"]["NpCredSN"],3) : null; // valor da porcentagem; 
		$std->vCredICMSSN = ($_REQUEST['produtos'][$x]["impostos"]["icms"]["NpCredSN"]!="")? CalcularPorcent($valor_total_tributavel, RmvString($_REQUEST['produtos'][$x]["impostos"]["icms"]["NpCredSN"],3)) : null;
		$std->modBCST = ($_REQUEST['produtos'][$x]["impostos"]["icms"]["NmodBCST"]!="")? strval($_REQUEST['produtos'][$x]["impostos"]["icms"]["NmodBCST"]) : null;
		$std->pMVAST =  ($_REQUEST['produtos'][$x]["impostos"]["icms"]["NpMVAST"]!="")? RmvString($_REQUEST['produtos'][$x]["impostos"]["icms"]["NpMVAST"],3) : null;
		$std->pRedBCST = ($_REQUEST['produtos'][$x]["impostos"]["icms"]["NpRedBCST"]!="")? RmvString($_REQUEST['produtos'][$x]["impostos"]["icms"]["NpRedBCST"],3) : null; // 
		$std->vBCST = ($_REQUEST['produtos'][$x]["impostos"]["icms"]["aliquota_reducao_st"]!="")? $valor_total_tributavel : null; // ?????
		$std->pICMSST = ($_REQUEST['produtos'][$x]["impostos"]["icms"]["NpICMSST"]!="")? RmvString($_REQUEST['produtos'][$x]["impostos"]["icms"]["NpICMSST"],3) : null;
		$std->vICMSST = ($_REQUEST['produtos'][$x]["impostos"]["icms"]["NpICMSST"]!="")? CalcularPorcent($valor_total_tributavel, RmvString($_REQUEST['produtos'][$x]["impostos"]["icms"]["NpICMSST"],3)) : null;
		$std->vBCFCPST = ($_REQUEST['produtos'][$x]["impostos"]["icms"]["pFCPST"]!="")? $valor_total_tributavel : null;
		$std->pFCPST = ($_REQUEST['produtos'][$x]["impostos"]["icms"]["pFCPST"]!="")? RmvString($_REQUEST['produtos'][$x]["impostos"]["icms"]["pFCPST"],3) : null;
		$std->vFCPST = ($_REQUEST['produtos'][$x]["impostos"]["icms"]["pFCPST"]!="")? CalcularPorcent($valor_total_tributavel, RmvString($_REQUEST['produtos'][$x]["impostos"]["icms"]["pFCPST"],3)) : null;
		$std->vBCSTRet = ($_REQUEST['produtos'][$x]["impostos"]["icms"]["vBCSTRet"]!="")? RmvString($_REQUEST['produtos'][$x]["impostos"]["icms"]["vBCSTRet"]) : null; // Base de Cálculo ICMS Retido na operação anterior
		$std->pST = ($_REQUEST['produtos'][$x]["impostos"]["icms"]["pST"]!="")? RmvString($_REQUEST['produtos'][$x]["impostos"]["icms"]["pST"], 3) : null; // Alíquota suportada pelo Consumidor Final
		$std->vICMSSTRet = null;
		$std->vBCFCPSTRet = null; 
		$std->pFCPSTRet = null;
		$std->vFCPSTRet = null;
		$std->pRedBCEfet = null;
		$std->vBCEfet = null;
		$std->pICMSEfet = null;
		$std->vICMSEfet = null;
		$std->modBC = ($_REQUEST['produtos'][$x]["impostos"]["icms"]["NmodBC"]!="")? strval($_REQUEST['produtos'][$x]["impostos"]["icms"]["NmodBC"]) : null;
		$std->vBC = ($_REQUEST['produtos'][$x]["impostos"]["icms"]["NpICMS"]!="")? $valor_total_tributavel : null;
		$std->pRedBC = ($_REQUEST['produtos'][$x]["impostos"]["icms"]["pRedBC"]!="")? RmvString($_REQUEST['produtos'][$x]["impostos"]["icms"]["pRedBC"],3) : null;
		$std->pICMS = ($_REQUEST['produtos'][$x]["impostos"]["icms"]["NpICMS"]!="")? RmvString($_REQUEST['produtos'][$x]["impostos"]["icms"]["NpICMS"],3) : null;
		$std->vICMS = ($_REQUEST['produtos'][$x]["impostos"]["icms"]["NpICMS"]!="")? CalcularPorcent($valor_total_tributavel, RmvString($_REQUEST['produtos'][$x]["impostos"]["icms"]["NpICMS"],3)) : null;
		$std->vICMSSubstituto = ($_REQUEST['produtos'][$x]["impostos"]["icms"]["vICMSSubstituto"]!="")? $_REQUEST['produtos'][$x]["impostos"]["icms"]["vICMSSubstituto"] : null; // Valor do ICMS próprio do Substituto
	    $vBCICMS +=$std->vBC;
		$vICMSTotal += $std->vICMS;
		$elem = $nfe->tagICMSSN($std);
	}


	/*
	 * Node com informações da partilha do ICMS entre a UF de origem e UF de destino ou a UF definida na legislação.
		$std->item = 1; //item da NFe
		$std->orig = 0;
		$std->CST = '90';
		$std->modBC = 0;
		$std->vBC = 1000.00;
		$std->pRedBC = null;
		$std->pICMS = 18.00;
		$std->vICMS = 180.00;
		$std->modBCST = 1000.00;
		$std->pMVAST = 40.00;
		$std->pRedBCST = null;
		$std->vBCST = 1400.00;
		$std->pICMSST = 10.00;
		$std->vICMSST = 140.00;
		$std->pBCOp = 10.00;
		$std->UFST = 'RJ';
		$nfe->tagICMSPart($std);
	 */


	/*
	// Node de informação do ICMS Interestadual do item na NFe
	$std->item = 1; //item da NFe
	$std->vBCUFDest = 100.00;
	$std->vBCFCPUFDest = 100.00;
	$std->pFCPUFDest = 1.00;
	$std->pICMSUFDest = 18.00;
	$std->pICMSInter = 12.00;
	$std->pICMSInterPart = 80.00;
	$std->vFCPUFDest = 1.00;
	$std->vICMSUFDest = 14.44;
	$std->vICMSUFRemet = 3.56;

	$nfe->tagICMSUFDest($std);
	*/


	/* IPI TAG */
	if($_REQUEST['produtos'][$x]["impostos"]["ipi"]["situacao_tributaria"]!="-1"){
		$std->item = $item;
		$std->clEnq = ($_REQUEST['produtos'][$x]["impostos"]["ipi"]["clEnq"]!="")? strval($_REQUEST['produtos'][$x]["impostos"]["ipi"]["clEnq"]) : null;
		$std->CNPJProd = ($_REQUEST['produtos'][$x]["cnpj_produtor"]!="")? RmvString($_REQUEST['produtos'][$x]["cnpj_produtor"]) : null;
		$std->cSelo = ($_REQUEST['produtos'][$x]["impostos"]["ipi"]["codigo_selo"]!="")? strval($_REQUEST['produtos'][$x]["impostos"]["ipi"]["codigo_selo"]) : null;
		$std->qSelo = ($_REQUEST['produtos'][$x]["impostos"]["ipi"]["qtd_selo"]!="")? strval($_REQUEST['produtos'][$x]["impostos"]["ipi"]["qtd_selo"]) : null;
		$std->cEnq = ($_REQUEST['produtos'][$x]["impostos"]["ipi"]["codigo_enquadramento"]!="")? strval($_REQUEST['produtos'][$x]["impostos"]["ipi"]["codigo_enquadramento"]) : "999";
		$std->CST = ($_REQUEST['produtos'][$x]["impostos"]["ipi"]["situacao_tributaria"]!="")? strval($_REQUEST['produtos'][$x]["impostos"]["ipi"]["situacao_tributaria"]) : "99";
		$std->vBC = ($_REQUEST['produtos'][$x]["impostos"]["ipi"]["aliquota"]!="")? $valor_total_tributavel: 0.00; 
		$std->pIPI = ($_REQUEST['produtos'][$x]["impostos"]["ipi"]["aliquota"]!="")? RmvString($_REQUEST['produtos'][$x]["impostos"]["ipi"]["aliquota"],3) : 0.00; 
		$std->vIPI = ($_REQUEST['produtos'][$x]["impostos"]["ipi"]["aliquota"]!="")? CalcularPorcent($valor_total_tributavel, RmvString($_REQUEST['produtos'][$x]["impostos"]["ipi"]["aliquota"],3)) : 0.00;
		$std->qUnid = null; // informar se for por unidade
		$std->vUnid = null; // 

		$vtotalIPI += $std->vIPI;
		$elem = $nfe->tagIPI($std);
	}


	/**   IMPOSTOS DE IMPORTACAO      **/  
	if($_REQUEST['produtos'][$x]["impostos"]["importacao"]["aliquota"]){
		$std->item = $item; //item da NFe
		$std->vBC = $valor_total_tributavel;
		$std->vDespAdu = $_REQUEST['pedido']["despesas_aduaneiras"];
		$std->vII = CalcularPorcent($valor_total_tributavel, RmvString($_REQUEST['produtos'][$x]["impostos"]["importacao"]["aliquota"],3));
		$std->vIOF = ($_REQUEST['produtos'][$x]["impostos"]["importacao"]["iof"]!="")? $_REQUEST['produtos'][$x]["impostos"]["importacao"]["iof"] : null;
		$vtotalII += $std->vII;
		$vtotalIOF += $std->vIOF;
		$elem = $nfe->tagII($std);
	}


}

/* TAG PIS  */
$std->item = $item;
$std->CST = ($_REQUEST['produtos'][$x]["impostos"]["pis"]["situacao_tributaria"]!="")? strval($_REQUEST['produtos'][$x]["impostos"]["pis"]["situacao_tributaria"]) : "99";
$std->vBC = ($_REQUEST['produtos'][$x]["impostos"]["pis"]["aliquota"]!="")? $valor_total_tributavel : 0.00; // Base de cálculo
$std->pPIS = ($_REQUEST['produtos'][$x]["impostos"]["pis"]["aliquota"]!="")? RmvString($_REQUEST['produtos'][$x]["impostos"]["pis"]["aliquota"],3) : 0.00;
$std->vPIS = ($_REQUEST['produtos'][$x]["impostos"]["pis"]["aliquota"]!="")? CalcularPorcent($valor_total_tributavel, RmvString($_REQUEST['produtos'][$x]["impostos"]["pis"]["aliquota"],3)) : 0.00;// em valor
$std->qBCProd = null;// em valor
$std->vAliqProd = null; // em valor
$vtotalPIS += $std->vPIS;
$elem = $nfe->tagPIS($std);

	
/*   TAG CONFIS */
$std->item = $item;
$std->CST = ($_REQUEST['produtos'][$x]["impostos"]["cofins"]["situacao_tributaria"]!="")? strval($_REQUEST['produtos'][$x]["impostos"]["cofins"]["situacao_tributaria"]) : "99";
$std->vBC = ($_REQUEST['produtos'][$x]["impostos"]["cofins"]["aliquota"]!="")? $valor_total_tributavel : 0.00; // Base de cálculo
$std->pCOFINS = ($_REQUEST['produtos'][$x]["impostos"]["cofins"]["aliquota"]!="")? RmvString($_REQUEST['produtos'][$x]["impostos"]["cofins"]["aliquota"],3) : 0.00; 
$std->vCOFINS = ($_REQUEST['produtos'][$x]["impostos"]["cofins"]["aliquota"]!="")? CalcularPorcent($valor_total_tributavel, RmvString($_REQUEST['produtos'][$x]["impostos"]["cofins"]["aliquota"],3)) : 0.00;
$std->qBCProd = null;
$std->vAliqProd = null;
$vtotalCONFIS += $std->vCOFINS;
$elem = $nfe->tagCOFINS($std);

$valortotal = $valortotal + ($valor_total_tributavel);
$pesototal = $pesototal + $peso;
	
/**         DADOS TOTAIS DA NOTA                **/
$impostos = DadosImpostos($dadosempresa["tokenIBPT"], $dadosempresa["cnpj"], $ncm, $dadosempresa["siglaUF"], '0', $nomeproduto, $un, $valor_total_tributavel, "SEM GTIN", 1);
$impostototal = $impostototal + $impostos;

/* Tributos incidentes no Produto ou Serviço do item da NFe */
$std->vTotTrib = tonormal($impostos);
$elem = $nfe->tagimposto($std);
$x++;

} // ate aqui o lopp dos produtos


/**          TOTAL DE IMPOSTOS         **/                     

$fretetotal = number_format((float)round($fretetotal, 2), 2, '.', '');
$segurototal = number_format((float)round($segurototal, 2), 2, '.', '');
$descontototal = number_format((float)round($descontototal, 2), 2, '.', '');
$outrostotal = number_format((float)round($outrostotal, 2), 2, '.', '');

// somar totais
$std->vBC = $vBCICMS;
$std->vICMS = $vICMSTotal;
$std->vICMSDesonv = 0.00;
$std->vFCP = 0.00; //incluso no layout 4.00
$std->vBCST = 0.00;
$std->vST = 0.00;
$std->vFCPST = 0.00; //incluso no layout 4.00
$std->vFCPSTRet = 0.00; //incluso no layout 4.00
$std->vProd = $valortotalProd;
$std->vFrete = $fretetotal;
$std->vSeg = $segurototal;
$std->vDesc = $descontototal;
$std->vOutro = $outrostotal;
$std->vII = number_format((float)round($vtotalII, 2), 2, '.', '');
$std->vIPI = number_format((float)round($vtotalIPI, 2), 2, '.', '');
$std->vIPIDevol = 0.00; //incluso no layout 4.00
$std->vPIS = number_format((float)round($vtotalPIS, 2), 2, '.', '');
$std->vCOFINS = number_format((float)round($vtotalCOFINS, 2), 2, '.', '');
$std->vTotTrib = tonormal($impostototal);
$std->vNF = (($valortotalProd) + ($valortotalServ) + ($std->vST) + ($std->vII) + ($std->vIPI) + ($std->vPIS) + ($std->vCOFINS) + 
	($fretetotal) + ($segurototal) + ($outrostotal)) - ($descontototal);
$elem = $nfe->tagICMSTot($std);

// Servicos
if($valortotalServ>0){
  
	$std->vServ = ($valortotalServ)? number_format((float)$valortotalServ, 2, '.', '') : 0.00;
	$std->vBC = ($vtotalISS_BC>0)? number_format((float)$vtotalISS_BC, 2, '.', '') : null;
	$std->vISS = ($vtotalISS>0)? number_format((float)$vtotalISS, 2, '.', '') : null;
	$std->vPIS = ($vtotalPIS>0)? number_format((float)$vtotalPIS, 2, '.', '') : null;
	$std->vCOFINS = ($vtotalCONFINS>0)? number_format((float)$vtotalCONFINS, 2, '.', '') : null;
	$std->dCompet = date("Y-m-d");
	$std->vDeducao = ($vtotalISS_deducao>0)? number_format((float)$vtotalISS_deducao, 2, '.', '') : null;
	$std->vOutro = ($vtotalISS_outros>0)? number_format((float)$vtotalISS_outros, 2, '.', ''): null;
	$std->vDescIncond = ($vtotalISS_DescIncond>0)? number_format((float)$vtotalISS_DescIncond, 2, '.', ''): null;
	$std->vDescCond = ($vtotalISS_DescCond>0)? number_format((float)$vtotalISS_DescCond, 2, '.', ''): null;
	$std->vISSRet =($vtotalISS_retencao>0)?  number_format((float)$vtotalISS_retencao, 2, '.', ''): null;
	$std->cRegTrib = $dadosempresa["crt"];  
  $elem = $nfe->tagISSQNTot($std);
}


/**          FRETE              **/                     
$std->modFrete = ($_REQUEST['pedido']['modalidade_frete']!="")? $_REQUEST['pedido']['modalidade_frete'] : 9; // Modalidade do frete 
$elem = $nfe->tagtransp($std);



/**     VOLUMES                                         **/ 
if($_REQUEST['transporte']['volume']!="" && $modelonf == 55){
	$std->item = ($_REQUEST['transporte']['numeracao'])? $_REQUEST['transporte']['numeracao'] : 1; //indicativo do numero do volume
	$std->qVol = ($_REQUEST['transporte']['volume'])? $_REQUEST['transporte']['volume'] : 1;
	$std->esp = $_REQUEST['transporte']['especie']; // CAIXA ...
	$std->marca = $_REQUEST['transporte']['marca'];
	$std->nVol = NULL; //$_REQUEST['transporte'][''];
	$std->pesoL = ($_REQUEST['transporte']['peso_bruto'])? $_REQUEST['transporte']['peso_bruto'] : 0.000;
	$std->pesoB = ($_REQUEST['transporte']['peso_liquido'])? $_REQUEST['transporte']['peso_liquido'] : 0.000;
	$elem = $nfe->tagvol($std);
}

/**        LACRES                                                               **/  
if($_REQUEST['transporte']['lacres'] && $modelonf == 55){
	$std->item = ($_REQUEST['transporte']['numeracao'])? $_REQUEST['transporte']['numeracao'] : 1; //indicativo do numero do volume
	$std->nLacre = strval($_REQUEST['transporte']['lacres']);
	$elem = $nfe->taglacres($std);
}


/**       TRANSPORTADORA             **/  
if($_REQUEST['transporte']['cnpj']){
	$std->CNPJ = strval(RmvString($_REQUEST['transporte']['cnpj']));
	$std->CPF = null;
	$std->xNome = strval($_REQUEST['transporte']['razao_social']);
	$std->IE = ($_REQUEST['transporte']['ie']=="")? null : strval($_REQUEST['transporte']['ie']);
	$std->xEnder = ($_REQUEST['transporte']['endereco']=="")? null : strval($_REQUEST['transporte']['endereco']);
	$std->xMun = ($_REQUEST['transporte']['cidade']=="")? null : strval($_REQUEST['transporte']['cidade']);
	$std->UF = ($_REQUEST['transporte']['uf']=="")? null : strval($_REQUEST['transporte']['uf']);
	$elem = $nfe->tagtransporta($std);
		
}elseif($_REQUEST['transporte']['cpf']){ 
	$std->CPF = strval(RmvString($_REQUEST['transporte']['cpf']));
	$std->CNPJ = null;
	$std->xNome = strval($_REQUEST['transporte']['nome_completo']);
	$std->IE = null;
	$std->xEnder = ($_REQUEST['transporte']['endereco']=="")? null : strval($_REQUEST['transporte']['endereco']);
	$std->xMun = ($_REQUEST['transporte']['cidade']=="")? null : strval($_REQUEST['transporte']['cidade']);
	$std->UF = ($_REQUEST['transporte']['uf']=="")? null : strval($_REQUEST['transporte']['uf']);
	$elem = $nfe->tagtransporta($std);
}

/**     TRANSPORTADORA VEICULO       **/  
if($_REQUEST['transporte']['placa']!=""){
	$std->placa =  strval($_REQUEST['transporte']['placa']);
	$std->UF = strval($_REQUEST['transporte']['uf_veiculo']);
	$std->RNTC = strval($_REQUEST['transporte']['rntc']);
	$elem = $nfe->tagveicTransp($std);
}
/*                                                                                       
$std->vServ = 240.00;
$std->vBCRet = 240.00;
$std->pICMSRet = 1.00;
$std->vICMSRet = 2.40;
$std->CFOP = '5353';
$std->cMunFG = '3518800';
$elem = $nfe->tagveicTransp($std);
 */

 // FATURAS
 $pg = 0;
 if(!empty($_REQUEST['fatura']['numero'])  && $modelonf == 55){
	 $std->nFat = strval($_REQUEST['fatura']['numero']);
	 $std->vOrig = number_format((float)$_REQUEST['fatura']['valor'], 2, '.', '');
	 $std->vDesc = ($_REQUEST['fatura']['desconto']!="")? number_format((float)$_REQUEST['fatura']['desconto'], 2, '.', ''): "";
	 $std->vLiq = number_format((float)$_REQUEST['fatura']['valor_liquido'], 2, '.', '');
	 $elem = $nfe->tagfat($std);
 }
 
 
 // DUPLICATAS
 $pg = 0;
 if(!empty($_REQUEST['duplicata']['numero'])  && $modelonf == 55){
 
	 if(is_array($_REQUEST['duplicata']['numero'])){
 
		 // loop pagamentos
		 foreach($_REQUEST['duplicata']['numero'] as $fac){
 
			 $std->nDup = strval($_REQUEST['duplicata']['numero'][$pg]);
			 $std->dVenc = strval($_REQUEST['duplicata']['vencimento'][$pg]); // '2017-08-22';
			 $std->vDup = $_REQUEST['duplicata']['valor'][$pg];
			 $elem = $nfe->tagdup($std);
			 $pg++;
		 }
 
	 }else{
 
		 $std->nDup = strval($_REQUEST['duplicata']['numero']);
		 $std->dVenc = strval($_REQUEST['duplicata']['vencimento']); // '2017-08-22';
		 $std->vDup = number_format((float)$_REQUEST['duplicata']['valor'], 2, '.', '');
		 $elem = $nfe->tagdup($std);
	 }
 }

/**             PAGAMENTO             
 * 
 * NOTA: para NFe (modelo 55), temos ...
* vPag=0.00 mas pode ter valor se a venda for a vista
* tPag é usualmente:
*14 = Duplicata Mercantil
*15 = Boleto Bancário
*90 = Sem pagamento
*99 = Outros
*Porém podem haver casos que os outros nodes e valores tenha de ser usados.
**/

$pg = 0;

  	$std->vTroco = $_REQUEST['pedido']['troco']; // TROCO
	$elem = $nfe->tagpag($std);  
	
if(is_array($_REQUEST['pedido']['forma_pagamento'])){

	// loop pagamentos
	foreach($_REQUEST['pedido']['forma_pagamento'] as $pagamento){
		$std->tPag = strval($pagamento);
		$std->vPag = $_REQUEST['pedido']['valor_pagamento'][$pg];
		if($pagamento=="99"){ 
			$std->xPag = ($pgto_motivo[$pg]!="")? $pgto_motivo[$pg] : "Outros"; 
		} else {
			$std->xPag = null;
		}

		if($_REQUEST['pedido']['forma_pagamento'][$pg]==03 || $_REQUEST['pedido']['forma_pagamento'][$pg]==04 &&
			($_REQUEST['pedido']['cnpj_credenciadora'][$pg]!="" && $_REQUEST['pedido']['bandeira'][$pg] && $_REQUEST['pedido']['autorizacao'][$pg])){
			$std->CNPJ = strval($_REQUEST['pedido']['cnpj_credenciadora'][$pg]); //Informar o CNPJ da Credenciadora de cartao de credito / debito.
			$std->tBand = strval($_REQUEST['pedido']['bandeira'][$pg]);
			$std->cAut = strval($_REQUEST['pedido']['autorizacao'][$pg]);
		}
		
		$tpag = $pagamento;
		
		//  Nota Técnica 2023.004 -v.1.10 - Publicada em 02/02/2024
		if($tpag=="03"||$tpag=="04"||$tpag=="10"||$tpag=="11"||$tpag=="12"||$tpag=="13"||$tpag=="15"||$tpag=="17"||$tpag=="18"){
			$std->tpIntegra = (@$_REQUEST['pedido']['tipo_integracao']!="")? @$_REQUEST['pedido']['tipo_integracao'] : "2"; // - 1 TEF, 2 POS
		}else{
			// Remover tag cartao
			$std->tpIntegra = null;
		}
		
		$elem = $nfe->tagdetPag($std); // modelo 4.00
		
		$pg++;
	}

}else{ // No es array

	$std->tPag = strval($_REQUEST['pedido']['forma_pagamento']);
	$std->vPag = $_REQUEST['pedido']['valor_pagamento'];
	if($_REQUEST['pedido']['forma_pagamento']=="99"){
		$std->xPag = ($pgto_motivo[$pg]!="")? $pgto_motivo[$pg] : "Outros"; 
	} else {
		$std->xPag = null;
	}
	if($_REQUEST['pedido']['forma_pagamento']==03 || $_REQUEST['pedido']['forma_pagamento']==04 && 
		($_REQUEST['pedido']['cnpj_credenciadora']!="" && $_REQUEST['pedido']['bandeira'] && $_REQUEST['pedido']['autorizacao']) ){
	$std->CNPJ = strval($_REQUEST['pedido']['cnpj_credenciadora']); 
	$std->tBand = strval($_REQUEST['pedido']['bandeira']);
	$std->cAut = strval($_REQUEST['pedido']['autorizacao']);
	}
	$elem = $nfe->tagdetPag($std); // modelo 4.00		

}

/**    EXPORTACAO TAG            **/     
if($_REQUEST['exportacao']['uf_embarque']){
  $std->UFSaidaPais = strval($_REQUEST['exportacao']['uf_embarque']);
  $std->xLocExporta = strval($_REQUEST['exportacao']['local_embarque']);
  $std->xLocDespacho = strval($_REQUEST['exportacao']['local_despacho']);
  $elem = $nfe->tagexporta($std);

}elseif( $std->tpNF=="1" && $std->idDest == "3"){

	$std->UFSaidaPais = strval($dadosempresa["siglaUF"] );
	$std->xLocExporta = strval($dadosempresa["cidade"]);
	$std->xLocDespacho = strval($dadosempresa["cidade"]);
	$elem = $nfe->tagexporta($std);
	
}
 

/**    INFORMAÇÕES ADICIONAIS   **/                     
$std->infAdFisco = ($_REQUEST['pedido']['informacoes_fisco'])? $_REQUEST['pedido']['informacoes_fisco'] : "";
$valoraprox = "";
//$valoraprox = 'Valor aproximado de tributos '.toMoney($impostototal).' ('.(int)(((($impostototal-$valortotal)/$valortotal)*100)+100).'%) - Fonte IBPT | ';
$infocompl = $_REQUEST['pedido']['informacoes_complementares'];
$infocompl = str_replace("{{IMPOSTO_NA_NOTA}}", $valoraprox, $infocompl);
$std->infCpl = ($infocompl)? $infocompl : "";
$elem = $nfe->taginfAdic($std);


/**  TECNICO RESPONSAVEL * */
if($_REQUEST['tecnico']['cnpj']!=""){
$std->CNPJ = ($_REQUEST['tecnico']['cnpj'])? strval($_REQUEST['tecnico']['cnpj']) : null; //CNPJ da pessoa jurídica responsável pelo sistema utilizado na emissão do documento fiscal eletrônico
$std->xContato= ($_REQUEST['tecnico']['contato'])? strval($_REQUEST['tecnico']['contato']) : null; //Nome da pessoa a ser contatada
$std->email = ($_REQUEST['tecnico']['email'])? strval($_REQUEST['tecnico']['email']) : null; //E-mail da pessoa jurídica a ser contatada
$std->fone = ($_REQUEST['tecnico']['fone'])? strval($_REQUEST['tecnico']['fone']) : null; //Telefone da pessoa jurídica/física a ser contatada
$std->CSRT = ($_REQUEST['tecnico']['csrt'])? strval($_REQUEST['tecnico']['csrt']) : null; //Código de Segurança do Responsável Técnico
$std->idCSRT = ($_REQUEST['tecnico']['idcsrt'])? strval($_REQUEST['tecnico']['idcsrt']) : null; //Identificador do CSRT
$elem = $nfe->taginfRespTec($std);
}

/**  Dados do intermediador * */
if($_REQUEST['intermediador']['cnpj']!=""){
$std->CNPJ = strval($_REQUEST['intermediador']['cnpj']); //CNPJ do intermediador: Mercado livre, 
$std->idCadIntTran = strval($_REQUEST['intermediador']['idcadastro']); //Nome da pessoa a ser contatada
$elem = $nfe->tagIntermed($std);
}

/*
 * Node de registro de pessoas autorizadas a acessar a NFe
 
$std->CNPJ = '12345678901234'; //indicar um CNPJ ou CPF
$std->CPF = null;
$nfe->tagautXML($std);
 */
/*
// retirada
// NOTA: Ajustado para NT 2018.005 Node indicativo de local de retirada diferente do endereço do emitente
$std->CNPJ = '12345678901234'; //indicar apenas um CNPJ ou CPF
$std->CPF = null;
$std->IE = '12345678901';
$std->xNome = 'Beltrano e Cia Ltda';
$std->xLgr = 'Rua Um';
$std->nro = '123';
$std->xCpl = 'sobreloja';
$std->xBairro = 'centro';
$std->cMun = '3550308';
$std->xMun = 'Sao Paulo';
$std->UF = 'SP';
$std->CEP = '01023000';
$std->cPais = '1058';
$std->xPais = 'BRASIL';
$std->fone = '1122225544';
$std->email = 'contato@beltrano.com.br';

$nfe->tagretirada($std);
*/

/*
// entrega
// NOTA: Ajustado para NT 2018.005 Node indicativo de local de entrega diferente do endereço do destinatário
$std->CNPJ; //indicar um CNPJ ou CPF
$std->CPF = null;
$std->IE = '12345678901';
$std->xNome = 'Beltrano e Cia Ltda';
$std->xLgr = 'Rua Um';
$std->nro = '123';
$std->xCpl = 'sobreloja';
$std->xBairro = 'centro';
$std->cMun = '3550308';
$std->xMun = 'Sao Paulo';
$std->UF = 'SP';
$std->CEP = '01023000';
$std->cPais = '1058';
$std->xPais = 'BRASIL';
$std->fone = '1122225544';
$std->email = 'contato@beltrano.com.br';
$nfe->tagentrega($std);
*/

$elem = $nfe->taginfNFeSupl($std);

try { 
		
	$result = $nfe->montaNFe();

} catch (\Exception $e) {
	
    if(is_array($nfe->getErrors())){
		foreach($nfe->getErrors() as $v){
			$error[] = $v;
		}
	}

}


$xml1 = $nfe->getXML();
$chave = $nfe->getChave();
$modelo = $nfe->getModelo();
 
if (!empty($error))
{
// reporta o erro para o usuario
  $erros = array($error);
  echo json_encode(array("error" => "Erro ao emitir nota", "log" => $erros));
  die;
	
}

$filename = $pasta."entradas/".$chave.".xml"; 
$filenameOriginal = $_REQUEST["endpoint"]."gerador/".$pasta."entradas/".$chave.".xml"; 
$msgXMLInvaalido = "<br><br><a download='".$chave.".xml' href='".$filenameOriginal."'>Baixar XML</a> e para mais detalhes, poderá analizar no site: <a href='https://www.sefaz.rs.gov.br/nfe/nfe-val.aspx' target='_blank'>Validador de XML do Projeto NF-e</a>";
/*
if (!is_writable($filename)) {
	echo json_encode(array("update" => 0, "error" => "Sem premissão de escrita na pasta 'gerador/xml'. Dê permissão 777"));
    die;
}
*/

file_put_contents($filename, trim($xml1));
chmod($filename, 0775);

try { 
		
	$response_assina = $tools->signNFe($xml1);
	$stdCl = new Standardize($response_assina);
	$arr = $stdCl->toArray();

	$filename_assina = $pasta."assinadas/".$chave.".xml";
	file_put_contents($filename_assina, trim($response_assina)); 
	chmod($filename_assina, 0775);

} catch (\Exception $e) {
	
	$errAssina = str_replace("{http://www.portalfiscal.inf.br/nfe}", "", $e->getMessage());
	$errAssina = str_replace("[facet 'pattern']", "", $errAssina);
	$errAssina = str_replace("The value", "", $errAssina);
	$errAssina = str_replace("is not accepted by the pattern", "não é um valido para o formato", $errAssina);
	$errAssina = str_replace("is not a valid value of the local atomic type", "não é um valido", $errAssina);
	$errAssina = str_replace("is not a valid value of the atomic type", "não é um tipo valido", $errAssina);
	$errAssina = str_replace("Element", "<br> - O elemento", $errAssina);

    echo json_encode(array("update" => 0, "error" => "Assina:<br>".$errAssina.$msgXMLInvaalido));
    die;

}
  

// teste
if($_REQUEST["teste"]=="ok"){
	echo json_encode(array("update" => 0, "teste" => "ok", "chave"  => $chave));
    die;
}

if($contigenciaativada){
    
    $data = array();
    $data['status']  = "contingencia";
    $data['ID']  = $_REQUEST['ID'];
    $data['nfe']  = strval($nnf);
    $data['serie']  = strval($seriedanota);
    $data['recibo']  = "";
    $data['chave']  = strval($chave);
	$data['protocolo']  = "";
	$data['tipoemissao']  = $emissaonotatipo;
    $data['xml']  = "api-nfe/gerador/xml/assinadas/".$chave.".xml";
    
 	echo json_encode($data);
    die;
}

} elseif($_REQUEST["emissao"]=="processa"){
    
	$nnf =	$_REQUEST['nfe'];
	$chave = $_REQUEST['chave'];
	$recibo_envio = $_REQUEST['recibo'];

  
} else {
  
   $nnf =	$_REQUEST['nfe'];
   $chave = $_REQUEST['chave'];
  
   if($dadosconti!=""){
     
     $contingency = new Contingency($dadosconti);
     $status = $contingency->deactivate();
     file_put_contents($name, "");

	}
		
}

		if($recibo_envio=="" || $recibo_envio==0){ // notas normais ou em procesamento
				
			try {
			
				$xml_assinado = file_get_contents($pasta."assinadas/".$chave.".xml"); 
				$idLote = substr(str_replace(',', '', number_format((float)microtime(true)*1000000, 0)), 0, 15);
				
				// NFC-e always 1
				if($modelonf=="65"){
					$modEnvio = 1;// 1 - sincrono (somente 1 nota) / 0 - acyntrocno
				}else{
					$modEnvio = 1;
					if($_REQUEST["transmissaoNFe"]!=""){
						$modEnvio = $_REQUEST["transmissaoNFe"];
					}
				}

				if($modEnvio==""){
					$modEnvio = 1;
				}
		
				$response_envio = $tools->sefazEnviaLote([$xml_assinado], $idLote, $modEnvio);
			
				$stdCl = new Standardize($response_envio);
				$arr_envio = $stdCl->toArray();
				// 103- asincrono
				// 104- sincrono
				if ($arr_envio['cStat'] == 103 || $arr_envio['cStat'] == 104) { // OK ENVIO
						
					$recibo_envio = $arr_envio['infRec']['nRec'];
				
				}else{
					
					echo json_encode(array("error" => "Envio 1: ".$arr_envio['xMotivo']." (".$arr_envio['cStat'].")".$msgXMLInvaalido));
					die;
				
				} 	
				
			} catch (\Exception $e) {	
					
				if(strpos($e->getMessage(), 'communication via soap') !== false) {
					echo json_encode( array("error" => "Não foi possivel comunicar com o Sefaz, tente novamente em algunas minutos."));
					die;
				}else{
					echo json_encode(array("error" => "Envio 2: ".$arr_envio['xMotivo']." (".$arr_envio['cStat']."). Erro:".$e->getMessage().$msgXMLInvaalido));
					die;
				}
				
			}

		}else{
			
			// em procesamento
			$modEnvio = 0;
			if($_REQUEST["transmissaoNFe"]!=""){
				$modEnvio = $_REQUEST["transmissaoNFe"];
			}
			$xml_assinado = file_get_contents($pasta."assinadas/".$chave.".xml"); 

		}


		if($modEnvio == 0){ // modo asincrono, necesita consulta mais adiante
			
			// delay para evitar notas em contingência
			if($modelonf=="65"){
				if($dadosempresa["siglaUF"]=="BA"){
					sleep(3);
				}else{
					sleep(1);
				}
			}else{
				if($dadosempresa["siglaUF"]=="BA"){
					sleep(4);
				}else{
					sleep(1);
				}
			}
	
			try{
				$response_protocolo = $tools->sefazConsultaRecibo($recibo_envio, $ambiente);
			} catch (\Exception $e) {	
				
				echo json_encode(array("error" => "Consulta Recibo: ".$e->getMessage().$msgXMLInvaalido));
				die;
				
			}
			
		}else {
			$response_protocolo = $response_envio;
		}

		$stdCl_prot = new Standardize($response_protocolo);
		$std_prot = $stdCl_prot->toArray();

		// outro processaamento, sefaz congestionado
		if($std_prot['cStat']==103){
			sleep(5); // esperamos um pouco
			$response_protocolo = $tools->sefazConsultaRecibo($recibo_envio, $ambiente);
			$stdCl_prot = new Standardize($response_protocolo);
			$std_prot = $stdCl_prot->toArray();
		}
		  		
 		if($std_prot['protNFe']['infProt']['cStat']==104  || $std_prot['protNFe']['infProt']['cStat']==103 || $std_prot['protNFe']['infProt']['cStat']==100){ // tudo ok

					try {
						
						$resposta_addprot = Complements::toAuthorize($xml_assinado, $response_protocolo); 
						
					} catch (\Exception $e) {
							
							if(strpos($e->getMessage(), 'communication via soap') !== false) {
								echo json_encode( array("error" => "Nao foi possivel comunicar com o Sefaz, tente novamente em algunas minutos."));
							}else{
								echo json_encode( array("error" => $e->getMessage()) );	
							}
							die;
					}
		
					$stdCl2 = new Standardize($resposta_addprot);
					$arr_prots = $stdCl2->toArray();
		
					if($arr_prots['protNFe']['infProt']['cStat']==100){ // Autorizada
			
						$filename = $pasta."autorizadas/".$chave.".xml";
						file_put_contents($filename, trim($resposta_addprot)); 
						chmod($filename, 0775);
					
						$data = array();
						$data['status']  = "aprovado";
            			$data['ID']  = $_REQUEST['ID'];
						$data['nfe']  = strval($nnf);
						$data['serie']  = strval($seriedanota);
						$data['recibo']  = strval($recibo_envio);
						$data['chave']  = strval($chave);
						$data['protocolo']  = strval($arr_prots['protNFe']['infProt']['nProt']);
						$data['tipoemissao']  = $emissaonotatipo; 
						$data['xml']  = "gerador/xml/autorizadas/".$chave.".xml";
									
						echo json_encode($data);
            			die;
														
					} else {

						echo json_encode(array("error" => "Rejeicao: ".$arr_prots['protNFe']['infProt']['xMotivo']." (".$arr_prots['protNFe']['infProt']['cStat'].")".$msgXMLInvaalido));
						die;
					}

		}elseif($std_prot['protNFe']['infProt']['cStat']==105 || $std_prot['cStat']==105){ // processamento


			$filename = $pasta."emprocessamento/".$chave.".xml";
			file_put_contents($filename, trim($xml_assinado)); 
			chmod($filename, 0775);

			$data = array();
			$data['status']  = "processamento";
			$data['ID']  = $_REQUEST['ID'];
			$data['nfe']  = strval($nnf);
			$data['serie']  = strval($seriedanota);
			$data['recibo']  = strval($recibo_envio);
			$data['chave']  = strval($chave);
			$data['protocolo']  = strval($std_prot['protNFe']['infProt']['nProt']);
			$data['tipoemissao']  = $emissaonotatipo;
			$data['xml']  = "gerador/xml/assinadas/".$chave.".xml";
			$data['arr'] = $std_prot;
						
			echo json_encode($data);
			die;
	
		} else {
								
			echo json_encode(array("error" => $std_prot['protNFe']['infProt']['xMotivo']." (".$std_prot['protNFe']['infProt']['cStat'].") ".$std_prot['xMotivo']." (".$std_prot['cStat'].")", "lote" => $std_prot['cStat']));
			die;
			
		} 