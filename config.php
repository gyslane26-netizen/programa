<?php
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

/* DADOS DO EMITENTE DA NOTA FISCAL, OU SEJA A EMPRESA QUE VAI EMITIR */
function limparString($str){
	if($str!=""){
		return str_replace(array(".", "/", "-", " ", "(", ")"), "", $str);
	}else{
		return "";
	}
}

		
$dadosempresa = [
    "atualizacao" => "202-08-01 13:00:00",
    "tpAmb" => (int) ($_REQUEST["tpAmb"]),
    "razaosocial" => ($_POST["razaosocial"]!="")? $_POST["razaosocial"] : $_GET["razaosocial"],
    "cnpj" => limparString(($_POST["cnpj"]!="")? $_POST["cnpj"] : $_GET["cnpj"]),
	"fantasia" => $_REQUEST["fantasia"], 
	"ie" => limparString($_REQUEST["ie"]), 
	"im" => limparString($_REQUEST["im"]), 
	"cnae" => limparString($_REQUEST["cnae"]),
	"crt" => $_REQUEST["crt"],
	"rua" => $_REQUEST["rua"],
	"numero" => $_REQUEST["numero"],
	"bairro" => $_REQUEST["bairro"],
    "cidade" => $_REQUEST["cidade"],
	"ccidade" => $_REQUEST["ccidade"], 
	"cep" => limparString($_REQUEST["cep"]), 
	"siglaUF" => limparString($_REQUEST["siglaUF"]), 
	"codigoUF" => $_REQUEST["codigoUF"], 
	"fone" => $_REQUEST["fone"],
    "schemes" => "PL_009_V4",
	"versao" => '4.00',
    "tokenIBPT" => $_REQUEST["tokenIBPT"], 
    "CSC" => $_REQUEST["CSC"],
    "CSCid" => $_REQUEST["CSCid"],
    "proxyConf" => [
        "proxyIp" => "",
        "proxyPort" => "",
        "proxyUser" => "",
        "proxyPass" => ""
    ]   
];

//monta o config.json
$configJson = json_encode($dadosempresa);

//carrega o conteudo do certificado.
$content = file_get_contents('../../app/controllers/cert_digitais/'.$_REQUEST["certificado"]); // ENDEREÇO PARA O CERTIFICADO DIGITAL
$senhacert = $_REQUEST["certificadosenha"]; // SENHA DO CERTIFICADO

if(function_exists('date_default_timezone_set') && $_REQUEST["timezone"]!="") date_default_timezone_set($_REQUEST["timezone"]);
