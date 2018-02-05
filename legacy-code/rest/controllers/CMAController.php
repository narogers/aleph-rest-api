<?php

class CMAController
{
	public $mysqli = false;
	public $availableFunctions = array();
	public $retData = array();
	public $rootURL = '';
	
	public function __construct($host, $user, $pass, $database) {
		$this->rootURL = getenv('REST_HOSTNAME') ?: "localhost"
		$this->availableFunctions = array("aleph" => "Search the library's catalog (Aleph)");

		$this->mysqli = new mysqli($host, $user, $pass, $database);
		if (mysqli_connect_errno()) {
			printf("Connect failed: %s\n", mysqli_connect_error());
			exit();
		}
	}
	
	public function getAction($request='') {
		$this->retData["message"]["header"] = "Missing function";
		$this->showFunctions();
		return $this->retData;
	}
	
	public function showFunctions($root='',$qualifier='') {
		if ($qualifier == '') {
			$this->retData["message"]["body"][] = "Available functions:";
		}
		switch ($root) {
			case "aleph":
				if ($qualifier == '') {
					foreach ($this->availableFunctions as $a => $desc) {
						$this->retData["message"]["body"][] = " <a href=\"/export/rest/aleph/".$a."\">".$a."</a> => ".$desc;
					}
				} else {
					$this->retData["message"]["body"][] = "Your URL is missing the ".$qualifier." ID or search string.";
					$this->retData["message"]["body"][] = '';
					$this->retData["message"]["body"][] = "Optionally, append any of the following to specify a return format:";
					$this->retData["message"]["body"][] = '';
					foreach (array("JSON"=>"json","XML"=>"xml","PHP (var_export)"=>"php","HTML (generally a Chicago citation)"=>"html") as $desc => $v) {
						$this->retData["message"]["body"][] = $desc." => ?format=".$v;
					}
				}
				break;
			default:
				foreach ($this->availableFunctions as $a => $desc) {
					$this->retData["message"]["body"][] = " <a href=\"/export/rest/".$a."\">".$a."</a>";
				}
				break;
		}
	}
		
	public function __destruct() {
		/* close conn and return */
		$this->mysqli->close();
	}
}

interface caching
{
	function cacheResult($request,$fetch = true);
}
