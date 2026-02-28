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

try { 		

	$datacert = Certificate::readPfx($content, $senhacert);
	$tools = new Tools($configJson, $datacert);
	$tools->model(strval("55"));

	$array = (array) $datacert;
	$array2 = (array) $array['publicKey'];
	$array3 = (array) $array2['validTo'];

	echo json_encode(array("update" => 0, "status" => true, "validade" => $array3["date"]));
    die;

} catch (\Exception $e) {
    echo json_encode(array("update" => 0, "error" => "Certificado ou Senha invalida"));
    die;
}
