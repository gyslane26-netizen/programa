<?php

//ini_set('display_errors', 1);
//ini_set('display_startup_errors', 1);
//error_reporting(E_ALL);


/* creates a compressed zip file */
function create_zip($files = array(),$destination = '', $overwrite = false) {
	//if the zip file already exists and overwrite is false, return false

	//echo $destination;
	if(file_exists($destination) && !$overwrite) { return false; }
	//vars

	$valid_files = array();
	//if files were passed in...
	if(is_array($files)) {
		//cycle through each file
		foreach($files as $file) {
			//make sure the file exists
			if(file_exists($file)) {
				$valid_files[] = $file;
			}
		}
	}
	//if we have good files...
	if(count($valid_files)) {
		//create the archive
		$zip = new ZipArchive();
		if($zip->open($destination, $overwrite ? ZIPARCHIVE::OVERWRITE : ZIPARCHIVE::CREATE) !== true) {
			return false;
		}
		//add the files
		foreach($valid_files as $file) {
			$zip->addFile($file,$file);
		}
		//debug
		//echo 'The zip archive contains ',$zip->numFiles,' files with a status of ',$zip->status;
		
		//close the zip -- done!
		$zip->close();
		
		//check to make sure the file exists
		return file_exists($destination);
	}
	else
	{
		return false;
	}
}

$cnpj = $_GET["cnpj"];
$mes = ($_GET["mes"])? $_GET["mes"] : date("m");
$ano = ($_GET["ano"])? $_GET["ano"] : date("y");
$modelo = ($_GET["modelo"])? $_GET["modelo"] : 65;
$nomecomeza = $_GET["codigoUF"].$ano.$mes.$cnpj.$modelo;
$files_to_zip = array();

$canceladas = array();
$directory2 = './xml/canceladas';
$scanned_directory2 = scandir($directory2);
foreach($scanned_directory2 as $d2){
    if (strpos($d2, $nomecomeza) !== false) {
		$canceladas[] = $d2;
        $files_to_zip[] = $directory2.'/'.$d2;
    } 
}

// nao incluidamos como autorizadas as que foram canceladas
$directory = './xml/autorizadas';
$scanned_directory = scandir($directory);
foreach($scanned_directory as $d){
    if (strpos($d, $nomecomeza) !== false && !in_array($d,$canceladas)) {
        $files_to_zip[] = $directory.'/'.$d;
    } 
}

$arrMeses = array("01" => "JAN","02" => "FEV","03" => "MAR","04" => "ABR","05" => "MAI","06" => "JUN","07" => "JUL","08" => "AGO","09" => "SET","10" => "OUT","11" => "NOV","12" => "DEZ");

$modeloshow = ($modelo=="55") ? "NF" : "NFC";
$nameA = '/zip/'.$modeloshow.'_'.$cnpj.'_'.$arrMeses[$mes].'_20'.$ano.'_'.date("dmYHi").'.zip';
$result = create_zip($files_to_zip, ".".$nameA, false);

$url = "";
if($result) $url = $nameA; 

//echo json_encode(array("result" => $result, "url" => $url));

  header("Content-Type: application/json");
         echo $_GET['callback'] . '(' . "{'result' : ".$result.", 'url': '".$url."'}" . ')';
         
