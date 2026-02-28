<?php
//error_reporting(E_ALL);
ini_set('display_errors', 'Off');

require_once '../bootstrap.php';
use NFePHP\NFe\Tools;
use NFePHP\Common\Certificate;
use NFePHP\NFe\Common\Standardize;
use NFePHP\NFe\Complements;
use NFePHP\NFe\Convert;

require_once './config.php'; // carrega configuracoes

header('Content-type: text/json');

$chave = trim($_REQUEST['chave']);
$motivo = $_REQUEST['motivo'];
$modelo = $_REQUEST['modelo'];
$endpoint = $_REQUEST['endpoint'];

try {
   	    $tools = new Tools($configJson, Certificate::readPfx($content, $senhacert));
    
		$filename_au = "./xml/autorizadas/".$chave.".xml"; // apos assinar salva arquivo
		
		$myXMLData = file_get_contents($filename_au);
		$xml=simplexml_load_string($myXMLData) or die(json_encode(array("error" => 'Nota não encontrada.')));

        $xJust = ($motivo)? $motivo : 'Desistencia do comprador';
        $nProt = strval($xml->protNFe->infProt->nProt);
        $modelo = $xml->NFe->infNFe->ide->mod;

        $tools->model(strval($modelo));
	
		// CANCELA A NOTA FISCAL
		$response = $tools->sefazCancela($chave, $xJust, $nProt);
    
            $stdCl = new Standardize($response);
            $std = $stdCl->toStd(); 
        
            $cStat = $std->retEvento->infEvento->cStat;
            $xMotivo = $std->retEvento->infEvento->xMotivo;
			
        if($cStat == 101 || $cStat == 135 || $cStat == 155){
					
             // $xml = Complements::toAuthorize(, $response);
              $xml = Complements::cancelRegister($myXMLData, $response);

              $filename = "./xml/canceladas/".$chave.".xml"; 

              file_put_contents($filename, trim($xml)); // salva xml assinado
              chmod($filename, 0777);

			  $data = array();
              $data['status']  = "cancelado";
              $data['modelo']  = $modelo;
              $data['ID']  = $_REQUEST['ID'];
              $data['nfe']  = strval($_REQUEST['nfe']);
              $data['chave']  = strval($chave);
              $data['xml']  = "gerador/xml/canceladas/".$chave.".xml";

              $query = http_build_query($data);		
              header("location: ".$endpoint."pos/nfe_updatadados/?".$query);
              exit;
          
        } else {
       
            echo json_encode(array("error" => $std->xMotivo." (".$std->cStat."), ". $xMotivo." (".$cStat.")"));
            echo "<br><a href='javascript:history.go(-1);'>Voltar</a>";
            die;
            
        }
   
} catch (\Exception $e) {
   
    echo json_encode(array("error" => $e->getMessage()));
    echo "<br><a href='javascript:history.go(-1);'>Voltar</a>";
    die;
}
