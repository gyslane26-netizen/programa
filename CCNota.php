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


$chave = trim($_REQUEST['chave']);
$motivo = str_replace(array("</br>", "<br>"), "\n", $_REQUEST['motivo']);
$modelo = $_REQUEST['modelo'];
$endpoint = $_REQUEST['endpoint'];
$nSeqEvento = $_REQUEST['sequencia'];

try {
   	    $tools = new Tools($configJson, Certificate::readPfx($content, $senhacert));
    
		$filename_au = "./xml/autorizadas/".$chave.".xml"; // apos assinar salva arquivo
		
		$myXMLData = file_get_contents($filename_au);
        $xml=simplexml_load_string($myXMLData) or die("<h2>Nota não encontrada</h2><br><a href='javascript:history.go(-1);'>Voltar</a>");
        

        $xCorrecao = ($motivo)? htmlspecialchars($motivo) : '';
        $nProt = strval($xml->protNFe->infProt->nProt);
        $modelo = $xml->NFe->infNFe->ide->mod;

        $tools->model(strval($modelo));

        if($xCorrecao==""){
            echo "<h2>Deve escrever um motivo para a correção.</h2>";
             echo "<br><a href='javascript:history.go(-1);'>Voltar</a>";
        }
        
        $response = $tools->sefazCCe($chave, $xCorrecao, $nSeqEvento);
    
            $stdCl = new Standardize($response);
            $std = $stdCl->toStd(); 
        
            $cStat = $std->retEvento->infEvento->cStat;
            $xMotivo = $std->retEvento->infEvento->xMotivo;

            if ($std->cStat != 128) {

                echo "<h2>".$std->xMotivo." (".$std->cStat."), ". $xMotivo." (".$cStat.")</h2>";
                 echo "<br><a href='javascript:history.go(-1);'>Voltar</a>";
                die;

            } else {
                $cStat = $std->retEvento->infEvento->cStat;
                if ($cStat == '135' || $cStat == '136') {
                    //SUCESSO PROTOCOLAR A SOLICITAÇÂO ANTES DE GUARDAR
                    $xml = Complements::toAuthorize($tools->lastRequest, $response);
                    $filename = "./xml/correcao/".$chave."-".$nSeqEvento.".xml"; 
                    
                    file_put_contents($filename, trim($xml)); // salva xml assinado
                    chmod($filename, 0777);

                    $data = array();
                    $data['status']  = "correcao";
                    $data['sequencia']  = $nSeqEvento;
                    $data['modelo']  = $modelo;
                    $data['ID']  = $_REQUEST['ID'];
                    $data['nfe']  = strval($_REQUEST['nfe']);
                    $data['chave']  = strval($chave);
                    $data['xml']  = "api-nfe/gerador/xml/correcao/".$chave.".xml";

                    $query = http_build_query($data);		
                    header("location: ".$endpoint."/pos/nfe_updatadados/?".$query);
                    exit; 


                    //grave o XML protocolado 
                } else {

                    echo "<h2>".$std->xMotivo." (".$std->cStat."), ". $xMotivo." (".$cStat.")</h2>";
                    echo "<br><a href='javascript:history.go(-1);'>Voltar</a>";
                    
                    die;
                }

            }    


} catch (\Exception $e) {
   
    //echo json_encode(array("error" => $e->getMessage()));
    echo "<h2>Erro: ".$e->getMessage()."</h2>";
     echo "<br><a href='javascript:history.go(-1);'>Voltar</a>";

    die;
}
