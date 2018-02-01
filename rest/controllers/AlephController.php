<?php

class AlephController extends CMAController implements caching
{
	public $webBase, $xbase, $xsuffix, $langcodes;
    private $worldcatKey;
	
	function __construct() {
		$db_host = getenv('REST_DB_HOST') ?: "localhost";
		$db_database = getenv('REST_DB_DATABASE') ?: "citations";
		$db_username = getenv('REST_DB_USERNAME') ?: "user";
		$db_password = getenv('REST_DB_PASSWORD') ?: "password";
		parent::__construct($db_host, $db_user, $db_password, $db_database);
		$this->webBase = getenv('REST_CATALOG_URI') ?: "localhost/opac";
		$this->xbase = getenv('REST_ALEPH_X') ?: "localhost:8991/x";
		$this->xsuffix = "&library=CMA01&base=STACKS";

                # List of functions available through the aleph/ endpoint
		$this->availableFunctions = array("alephid"=>"Search by Aleph ID",
										  "artist"=>"Search by artist name",
										  "mondaytable"=>"Recent acquisitions list",
										  "object"=>"Search by CMA accession number",
										  "oclc"=>"Search by OCLC number");

		// Worldcat API key
		$this->worldcatKey = getenv('REST_WORLDCAT_KEY') ?: "changeMe";
		
		// Convert 041 language codes to full, readable.  This converts eng to English, ger to German, etc.
		$this->langcodes = eval("return ".file_get_contents("library/041_langcodes.php"));
	}
	
	// The main handler for actions.  This takes a bit of the URL and passes it through / queries the appropriate X-server page(s).
	public function getAction($request='') {
		$timestart = microtime();

		if(isset($request->url_elements[2])) {
			$action = $request->url_elements[2];
			if(isset($this->availableFunctions[$action])) {
				if (strlen($request->url_elements[3])) {
					// Sanitize the querystring var
					switch($request->url_elements[2]) {
					case "mondaytable":
						$alephid = preg_replace("#[^0-9]#ms","",urldecode($request->url_elements[3]));
						if (!is_numeric($alephid)) { return array(); }
						$url = array("?op=find_doc&doc_num=".$alephid,"?func=find-b&find_code=SYS&request=");
						break;
					case 'nationality':
						$nationality = preg_replace("#[^a-z0-9\.\,\- ]#ims","",urldecode($request->url_elements[3]))." Artists";
						if ($request->url_elements[4]) {
							$century = preg_replace("#[^0-9a-z\.\,\- ]#ims","",urldecode($request->url_elements[4]));
							$url = array("?op=find&code=wsu&request=(".$nationality.") and (".$century.")",
								"?func=find-c&ccl_term=(WSU%3D".$nationality.")+and+(WSU%3D".$century.")");
						} else {
							$url = array("?op=find&code=wsu&request=".$nationality,
								"?func=find-c&ccl_term=WSU%3D".$nationality);
						}
						break;
					case 'object':
                        // Aleph has accession numbers indexed (tab11_word) as 1914.848, but searches in the OPAC or GUI
                        // for "1914.848" return nothing.  All the proper word_breaking instructions seem to be present,
                        // https://exlibrisgroup.my.salesforce.com/articles/bl_Non_KCS_Article/How-to-Check-Certain-Word-Index-Entries-Using-UTIL-F-4
                        // The instructions at the URL above implies that the accession numbers are properly indexed,
                        // but in order to get precise results, the period must be converted to a wildcard (*).
                        // A PHP query string rewrite workaround is in place on the OPAC to do the same.  The GUIs
                        // require "1914*848" entry.
                        $request->url_elements[3] = str_replace(".","*",$request->url_elements[3]);

						$object = preg_replace("#[^0-9\.\*]#ims","",trim(urldecode($request->url_elements[3])));
						$url = array("?op=find&code=cmaan&request=".$object,"?func=find-c&ccl_term=cmaan%3D".$object);
						break;
					case 'artist':
						$artist = preg_replace("#[^a-z0-9,\- \.']#ims","",trim(urldecode($request->url_elements[3])));
						$artist = urlencode(implode(" and ",explode(" ",$artist)));
						
						$url = array("?op=find&code=wsu&request=".$artist,"?func=find-c&ccl_term=wsu%3D".$artist);
						break;
					case 'alephid':
						$alephid = preg_replace("#[^0-9]#ms","",urldecode($request->url_elements[3]));
						if (!is_numeric($alephid)) { return array(); }
						$url = array("?op=find_doc&doc_num=".$alephid,"?func=find-b&find_code=SYS&request=");
						break;
					case 'oclc':
						$oclc = preg_replace("#[^0-9]#ms","",urldecode($request->url_elements[3]));
						if (!is_numeric($oclc)) { return array(); }
						$url = array('?op=find&code=OCL&request='.$oclc,"?func=find-b&find_code=SYS&request=");
						break;
					default:
						$this->retData["message"]["header"] = "Unsupported function.";
						$this->showFunctions('aleph');
						return $this->retData;
					}

					// Without local caching, we were getting Aleph 18 overload messages.  The maximum OPAC users is 25.
					// This may not apply to X-services requests under Aleph 21.  Without paying OCLC, the citation
					// service limits us to 1000 calls per day.  This local caching should make that limit moot.
					// Passing "?raw=true" (&raw=true...) will bypass the cache and return the full/raw XML response.
					if ($_GET['raw'] != 'true' && $_GET['force'] != 'true') {
						$this->cacheResult($url);
					}
				} else {
					$this->retData["message"]["header"] = "Invalid search string.";
					$this->showFunctions('aleph',$request->url_elements[2]);
					return $this->retData;
				}

				if (count($this->retData) == 0 || $_GET['force'] == 'true') {
					$doc = new DomDocument();
					@$doc->load($this->xbase.$url[0].$this->xsuffix);

					$ret_key = $request->url_elements[2];
					$this->retData["@attributes"] = array("date_requested" => time());
					$this->retData["search"] = array(
						"@attributes" => array("target" => "Ingalls Library Catalog"),
						"request" => array("@attributes" => array("type" => $ret_key),
											"@value" => urldecode($request->url_elements[3]))
					);

					switch ($ret_key) {
						case "mondaytable":
							$err = $doc->getElementsByTagName('error');
							if (!$err->item(0)->nodeValue) {
								$this->retData[$ret_key]['url'] = $this->xbase.$url[0].$this->xsuffix;
								$xpath = new DOMXpath($doc);
								$this->extractBasicMeta($xpath,$ret_key);
								$this->generateBackLinks($xpath,$alephid,$url[1],$ret_key);
							}
							break;
						case "nationality":
						case "artist":
						case "object":
							list($err, $recs, $set) = $this->checkDocErr($doc, "set");
								
							if ($err->item(0)->nodeValue != 'empty set' && ltrim($recs->item(0)->nodeValue,"0") > 0 && ltrim($set->item(0)->nodeValue) > 0) {
								$count = ltrim($recs->item(0)->nodeValue,"0");
								$this->retData["search"]["request"]["@value"] = urldecode($request->url_elements[3]);
								$this->retData["search"]["result"]["count"]["@value"] = $count;
								if ($ret_key == "nationality") {
									$this->retData["search"]["request"]["@value"] = $nationality.($century ? ", ".$century : '');
									$this->retData["search"]["result"]["link"]["text"]["@cdata"] = "Library materials for ".$this->retData["search"]["request"]["@value"];
								} else if ($ret_key == "artist") {
									$this->retData["search"]["result"]["link"]["text"]["@cdata"] = "Library materials about ".$this->retData["search"]["request"]["@value"];
								} else {
									$this->retData["search"]["result"]["link"]["text"]["@cdata"] = "Library materials about CMA object ".str_replace("*",".",$this->retData["search"]["request"]["@value"]);
								}
								if ($count) {
									$this->retData["search"]["result"]['link']["text"]["@cdata"] .= " (".$count.")";
								}
								$this->retData["search"]["result"]['link']["href"]["@value"] = $this->webBase.$url[1];
								$this->retData["search"]["result"]['link']["target"]["@value"] = "_blank";
							} else {
								return $this->retData;
							}
							break; 
						case "oclc":
						case "alephid":
							if ($ret_key == 'oclc') {
								list($err, $recs, $set) = $this->checkDocErr($doc, "set");

								if (ltrim($recs->item(0)->nodeValue,"0") >= 1) {
									@$doc->load($this->xbase."?op=present&set_entry=1&set_number=".$set->item(0)->nodeValue.$this->xsuffix);
									$xpath = new DOMXpath($doc);
									$aleph = $xpath->query("/present/record/metadata/oai_marc/fixfield[@id='001']");
									$alephid = preg_replace("#([^0-9]*)$#ims","",trim($aleph->item(0)->nodeValue));
								} else {
									$this->retData["message"]["header"] = "No results.";
								}
								@$doc->load($this->xbase."?op=find_doc&doc_num=".$alephid.$this->xsuffix);
							} else {
								@$doc->load($this->xbase.$url[0].$this->xsuffix);
							}
							$err = $doc->getElementsByTagName('error');
							if (!$err->item(0)->nodeValue) {
								$xpath = new DOMXpath($doc);
								
								$this->extractBasicMeta($xpath);
								$this->generateBackLinks($xpath,$alephid,$url[1]);
								
								if ($_REQUEST['raw']) {
									$this->retData['search']['result']["response_xml"]["@cdata"] = $doc->saveXML();
								}
								$this->fetchCitation($oclc, array("alephid" => $alephid));
							} else {
								$this->fetchCitation($oclc);
							}
							break;
					}
					$this->cacheResult($url,false);
				}
			} else {
				$this->retData["message"]["header"] = "Unsupported function.";
				$this->showFunctions("aleph");
				return $this->retData;
			}
		} else {
			$this->showFunctions("aleph");
		}
		$this->retData["search"]["@attributes"]['query_took'] = max(0.0001,round((microtime() - $timestart),6))." ms";
		return $this->retData;
	}
	
	# pragma mark - caching interface
	function cacheResult($request,$fetch = true) {
		$return = false;
		if (count($this->retData) == 0 && $fetch) {
			$sql = "select requestTimestamp, responseObject from restCache where requestObject = ";
			$obj = "'".$this->mysqli->real_escape_string(serialize($request))."'";
			if ($result = $this->mysqli->query($sql.$obj)) {
				$row = $result->fetch_row();
				if ($row[1]) {
					$this->retData = @unserialize($row[1]);
				}
			}
		} else if ($_GET['force'] == 'true') {
			$stmt = $this->mysqli->prepare("update restCache set responseObject = ? where requestObject = ?");
			$stmt->bind_param('ss',serialize($this->retData),serialize($request));
			$stmt->execute();
			$stmt->close();
		} else if (count($this->retData) != 0) {
			$stmt = $this->mysqli->prepare("insert into restCache (requestObject, responseObject) values (?,?)");
			$stmt->bind_param('ss',serialize($request),serialize($this->retData));
			$stmt->execute();
			$stmt->close();
		}
	}
	
	# pragma mark - MARC extraction
	private function extractBasicMeta($xpath, $root = 'search') {	
		// Author preference order: 100$a, 110$a, 130$a, 700$a, 710$a
		foreach (array(array("var","100","a"),array("var","110","a"),array("var","130","a"),array("var","700","a"),array("var","710","a")) as $a) {
			$xp = "/find-doc/record/metadata/oai_marc/varfield[@id='".$a[1]."']".($a[2] ? "/subfield[@label='".$a[2]."']" : '');
			$author = $xpath->query($xp);
			if ($author->item(0)->nodeValue) {
				$author = trim(preg_replace("#[/:,]$#ms","",$author->item(0)->nodeValue));
				$this->retData[$root]["result"]["bibliographic"]["author"] = $author;
				break;
			}
		}
		
		// Title preference order: 246$a, 245$a, and 001 as a failsafe
		foreach (array(array("var","246","a"),array("var","245","a"),array("fix","001")) as $a) {
			$xp = "/find-doc/record/metadata/oai_marc/".$a[0]."field[@id='".$a[1]."']".($a[2] ? "/subfield[@label='".$a[2]."']" : '');

			$title = $xpath->query($xp);
			if ($title->item(0)->nodeValue) {
				$title = trim(preg_replace("#[/:,]$#ms","",$title->item(0)->nodeValue));
				$this->retData[$root]["result"]["bibliographic"]["title"] = $title;
				$this->retData[$root]["result"]["bibliographic"]["link"]["@value"] = 'Catalog record for "'.$title.'"';
				$this->retData[$root]["result"]["holdings"]["link"]["@value"] = 'Library holdings for "'.$title.'"';
				break;
			}
		}
		
		// Publication preference order: 260$a$b$c, 261$a$b$c, 262$a$b$c, 264$a$b$c
		// Publication fields: 260$a (place), 260$b (publisher), and 260$c (year)
		foreach (array("260","261","262","264") as $marc) {
			foreach (array("location"=>array("var",$marc,"a"),"publisher"=>array("var",$marc,"b"),"year"=>array("var",$marc,"c")) as $k=>$a) {
				$xp = "/find-doc/record/metadata/oai_marc/".$a[0]."field[@id='".$a[1]."']".($a[2] ? "/subfield[@label='".$a[2]."']" : '');

				$pub = $xpath->query($xp);
				if ($pub->item(0)->nodeValue) {
					$pub = trim(preg_replace("#[/:,]$#ms","",$pub->item(0)->nodeValue));
					$this->retData[$root]["result"]["bibliographic"]["publication"][$k] = $pub;
				}
			}
			if ($this->retData[$root]["result"]["bibliographic"]["publication"]['location']) { break; }
		}

		// Control numbers: 001 (Aleph ID), 035 (OCLC), 020$a (ISBN)
		foreach (array('Aleph ID' => array('fix','001'),'OCLC Number' => array('var','035','','\(OCoLC\)'),"ISBN" => array('var','020','a')) as $k => $a) {
			$xp = "/find-doc/record/metadata/oai_marc/".$a[0]."field[@id='".$a[1]."']".($a[2] ? "/subfield[@label='".$a[2]."']" : '');
			
			$val = $xpath->query($xp);
			foreach ($val as $v) {
				$attr = array();
				if ($v->nodeValue) {
					if ($k == 'OCLC Number' && !stristr($v->nodeValue,'(OCoLC)')) { continue; }
					$nv = trim(str_replace(array("(OCoLC)"),"",$v->nodeValue));
					if ($k == 'ISBN') {
						$nv = explode("(",$nv);
						if (count($nv) == 2) {
							$attr = array("material_type"=>str_replace(array(")","'"," "),"",$nv[1]));
							$nv = $nv[0];
						} else {
							$nv = trim(str_replace(array("(OCoLC)"),"",$v->nodeValue));	
						}
					}
					$key = str_replace(" ","_",strtolower($k));
					$number = trim(preg_replace("#".($a[3] ? $a[3] : 'ZZ%ZZ')."$#ims","",$nv));
					$this->retData[$root]["result"]["bibliographic"][$key][] = (count($attr) ? array("@attributes" => $attr, "value"=>$number) : $number);
				}
			}
		}
		
		// Language extractor (041$a, whenever available)
		$xp = "/find-doc/record/metadata/oai_marc/varfield[@id='041']/subfield[@label='a']";
		$val = $xpath->query($xp);
		foreach ($val as $v) {
			if ($v->nodeValue) {
				if ($this->langcodes[$v->nodeValue]) {
					$this->retData[$root]["result"]["bibliographic"]["language"][] = array("@cdata"=>$this->langcodes[$v->nodeValue]);
				}
			}
		}
		// Language backup 008 extraction
		if (!is_array($this->retData[$root]["result"]["bibliographic"]["language"])) {
			$xp = "/find-doc/record/metadata/oai_marc/fixfield[@id='008']";
			$bibdata = $xpath->query($xp);
			if ($bibdata->item(0)->nodeValue) {
				$lang = $this->langcodes[substr($bibdata->item(0)->nodeValue,35,3)];
				if ($lang) {
					$this->retData[$root]["result"]["bibliographic"]["language"][] = array("@cdata"=>$lang);
				}
			}
		}
	}
	
	// Generate links back to library services
	private function generateBackLinks($xpath, $alephid, $qs, $root = 'search') {
		$holding = $xpath->query("/find-doc/record/metadata/oai_marc/varfield[@id='050']/subfield[@label='a']");				
		if ($holding->item(0)->nodeValue) {
			$this->retData[$root]["result"]["holdings"]["link"]["@attributes"]["href"] = $this->webBase."?func=item-global&doc_library=CMA01&doc_number=".$alephid;
			$this->retData[$root]["result"]["holdings"]['link']["@attributes"]["target"] = "_blank";
			$callNo = $xpath->query("/find-doc/record/metadata/oai_marc/varfield[@id='050']/subfield[@label='a']");
			$callNo2 = $xpath->query("/find-doc/record/metadata/oai_marc/varfield[@id='050']/subfield[@label='b']");
			$this->retData[$root]["result"]["holdings"]["call_number"]["@cdata"] = 
			$callNo->item(0)->nodeValue." ".$callNo2->item(0)->nodeValue;
		} else {
			unset($this->retData["search"]["result"]["holdings"]);
		}
		$this->retData[$root]["result"]['bibliographic']['link']["@attributes"]["href"] = $this->webBase.$qs.$alephid;
		$this->retData[$root]["result"]['bibliographic']['link']["@attributes"]["target"] = "_blank";

		$this->retData[$root]["result"]["holdings"]["callslip"]['link']["@value"] = "Submit a call slip to request this item";
		$this->retData[$root]["result"]["holdings"]["callslip"]["link"]["@attributes"]["href"] = $this->rootURL."/services/callslip?doc=".$alephid;
		$this->retData[$root]["result"]["holdings"]["callslip"]["link"]["@attributes"]["target"] = "_blank";
	}
	
	# Pragma mark Citations
	
	/**
         * Fetch a citation from Worldcat using the OCLC number
         */
	private function fetchCitation($oclc, $ids = array('alephid'=>'','oclc'=>''), $root = 'search') {
                $worldcat_uri = getenv("REST_WORLDCAT_API") ?: "http://www.worldcat.org/webservices";
		$url = "$worldcat_uri/catalog/content/citations/$oclc?cformat=chicago&wskey=${this->worldcatKey}";
		$sql = "select citation, callNo from citations where oclc = '".$oclc."'";
		
		if ($res = $this->mysqli->query($sql)) {
			$row = $res->fetch_assoc();

			$callNo = $this->mysqli->real_escape_string( $this->retData[$root]["result"]["holdings"]["call_number"]["@cdata"] );
			
			if (!$row['citation'] && $oclc) {
				$citation = file_get_contents($url);
				if (!stristr($citation, "Record does not exist")) {
					$citation = preg_replace("#Print\.([ ]*)</p>$#ims","</p>",trim($citation));
				}
												
				$stmt = $this->mysqli->prepare("insert into citations (citation, oclc, aleph, callNo) values (?,?,?,?) on duplicate key update oclc = ?, aleph = ?, callNo = ?");
				$stmt->bind_param("sssssss", $citation, $oclc, $ids['alephid'], $callNo, $oclc, $ids['alephid'], $callNo);
				
				$stmt->execute();
				$stmt->close();
			} else if ($oclc) {
				$citation = $row['citation'];
			}
			$res->free();
		}
		
		if ($citation && !stristr($citation,"Record does not exist")) {
			$citation = trim(strip_tags($citation,"<i><u><span>"));
			$this->retData[$root]["result"]['citation']["@cdata"] = trim(preg_replace("#<span>(.*?)</span>#ims","",$citation));
			$this->retData[$root]["result"]['citation']["@attributes"]["style"] = 'chicago';
		} else if (!$this->formatLocalCitation()) {
			$this->retData[$root]["result"]["citation"]["@value"] = 'Not available.';
		}
	}
	
        /**
         * Creates a local citation in case WorldCat fails to return a
         * result
         */
	private function formatLocalCitation($root = 'search') {
		if ($this->retData[$root]["result"]["bibliographic"]["author"] && $this->retData[$root]["result"]["bibliographic"]["title"]) {
			$this->retData[$root]["result"]['citation']["@attributes"]["style"] = "CMA (Worldcat citation unavailable)";

			$citref = $this->depunct($this->retData[$root]["result"]["bibliographic"]["author"]).". ";
			if ($this->retData[$root]["result"]["bibliographic"]["title"]) {
				$citref .= "<i>".$this->depunct($this->retData[$root]["result"]["bibliographic"]["title"]).".</i> ";
			}
			
			if ($this->retData[$root]["result"]["bibliographic"]["publication"]['location']) {
				$citref .= $this->retData[$root]["result"]["bibliographic"]["publication"]['location'].": ";
				$citref .= $this->retData[$root]["result"]["bibliographic"]["publication"]['publisher'].", ";
				$citref .= $this->retData[$root]["result"]["bibliographic"]["publication"]['year'];
			}
			
			$this->retData[$root]["result"]['citation']["@cdata"] = $citref;
			
			// Cache the result
			$ids['alephid'] = $this->retData[$root]["result"]["bibliographic"]['aleph_id'][0];
			$callNo = $this->mysqli->real_escape_string( $this->retData[$root]["result"]["holdings"]["call_number"]["@cdata"] );
			if ($this->retData[$root]["result"]["bibliographic"]["oclc_number"][0]) {
				$oclc = $this->retData[$root]["result"]["bibliographic"]["oclc_number"][0];
			}
			$stmt = $this->mysqli->prepare("insert into citations (citation, oclc, aleph, callNo) values (?,?,?,?) on duplicate key update citation = ?, oclc = ?, aleph = ?, callNo = ?");
			$stmt->bind_param("ssssssss", $citref, $oclc, $ids['alephid'], $callNo, $citref, $oclc, $ids['alephid'], $callNo);
			
			$stmt->execute();
			$stmt->close();
						
			return true;
		} else {
			return false;	
		}
	}
	
	# Pragma mark Generic reusable helpers
	
	// Check if a standard X-server error was thrown.
	private function checkDocErr($doc, $type) {
		if ($type == 'set') {
			$err = $doc->getElementsByTagName('error');
			$recs = $doc->getElementsByTagName('no_records');
			$set = $doc->getElementsByTagName('set_number');
			return array($err,$recs,$set);
		}
	}
	
	private function depunct($var) {
		return preg_replace("#\.$#","",trim($var));
	}
		
	function __destruct() {
		parent::__destruct();
	}
}
