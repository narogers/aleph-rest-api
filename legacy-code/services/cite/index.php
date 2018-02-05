<?php
$worldcat_key = getenv('REST_WORLDCAT_KEY') ?: "worldcat_api_key";
$worldcat_api = getenv('REST_WORLDCAT_API') ?: "http://www.worldcat.org/webservices";

$isbn = isset($_GET["isbn"]) ? $_GET["isbn"] : 0;
$url[] = "";

if ($isbn != 0) {
	$url[] = "$worldcat_api/catalog/content/citations/isbn/$isbn?cformat=chicago&wskey=$worldcat_key";
	$ids = array("isbn"=>$isbn);
} else {
	$ids['isbn'] = '';
}
foreach (array("issn","oclc","aleph","lc") as $a) {
	$ids[$a] = '';

	if (($a == 'issn') &&
            isset($_GET[$a]) && 
            preg_match("#^[0-9X]{4}-[0-9X]{4}$#",trim($_GET[$a]))) {
	      $ids[$a] = isset($_GET[$a]) ? $_GET[$a] : 0;
	      if ($_GET[$a] > 0) {
	        $url[] = "$worldcat_api/catalog/content/citations/issn/${_GET[$a]}?cformat=chicago&wskey=$worldcat_key";
			}
	} else if (($a == 'oclc' || $a == 'aleph') &&
            isset($_GET[$a]) && 
            is_numeric($_GET[$a])) {
	  $ids[$a] = isset($_GET[$a]) ? $_GET[$a] : 0;
			
	  if ($a == 'oclc' && $_GET[$a] > 0) {
	    $url[] = "$worldcat_api/catalog/content/citations/${_GET[$a]}?cformat=chicago&wskey=$worldcat_key";
		}
	} else if ($a == 'lc' && 
            isset($_GET[$a]) &&
            is_numeric($_GET[$a])) {
	  if ($_GET[$a] > 0) {
	  $url[] = "$worldcat_api/catalog/content/citations/sn/${_GET[$a]}?cformat=chicago&wskey=$worldcat_key";
	}
    }
}

// Worldcat limits us to 1000 requests per day.
// Try to get the citation from the local database cache first.
// Fallback to make the API request.
$content = fetch_citation($ids, $url);
print $content;

function fetch_citation($ids, $url) {
        $db_host = getenv('REST_DB_HOST') ?: "localhost";
        $db_database = getenv('REST_DB_DATABASE') ?: "citations";
        $db_user = getenv('REST_DB_USERNAME') ?: "user";
        $db_password = getenv('REST_DB_PASSWORD') ?: "password";

	$mysqli = @mysqli_connect($db_host, $db_user, $db_password, $db_database);
	if (mysqli_connect_errno()) {
		printf("Connect failed: %s\n", mysqli_connect_error());
		return '';
	}
	$sql = "select citation, callNo from citations where ";
	foreach ($ids as $k => $v) {
		if (is_numeric($v)) {
			$sql .= $k."='".$v."' and ";
		}
	}

	$sql = substr($sql,0,-4);

	if ($res = mysqli_query($mysqli, $sql)) {
		$row = mysqli_fetch_assoc($res);

                if (isset($_GET["callNo"])) { 
		  $callNo = mysqli_real_escape_string($mysqli, mb_convert_encoding($_GET['callNo'], "UTF-8"));
		} else {
                  $callNo = 0;                
                }

		if (!$row['citation']) {
			foreach ($url as $u) {
				$content = file_get_contents($u);
				if (!stristr($content, "Record does not exist")) { break; }
			}
			if ($callNo == '0') { $callNo = ''; }
			if (!stristr($content, "Record does not exist")) {
				$content = preg_replace("#Print\.([ ]*)</p>$#ims","</p>",trim($content));
				//$content = str_replace("</p>","  <span>".$callNo."</span></p>",$content); 
			}
			$stmt = mysqli_prepare($mysqli,"insert into citations (citation, isbn, issn, oclc, aleph, callNo) values (?,?,?,?,?,?)");
			mysqli_stmt_bind_param($stmt, "ssssss", $content, $ids['isbn'], $ids['issn'], $ids['oclc'], $ids['aleph'], $callNo);
			
			mysqli_stmt_execute($stmt);

			/* close statement */
			mysqli_stmt_close($stmt);
		} else {
			$content = $row['citation'];
		}
		// If this block executes, it  likely means the item has recently
		// been assigned a new/first Call Number.
		if (!$row['callNo'] && $row['citation']) {
			$stmt = mysqli_prepare($mysqli,"update citations set callNo = ?, citation = ? where aleph = ?");
			mysqli_stmt_bind_param($stmt, "ss", $callNo, preg_replace("#<span>.*?</span>#ims","<span>".$callNo."</span>",$row['citation']),$ids['aleph']);
			mysqli_stmt_execute($stmt);
			mysqli_stmt_close($stmt);
		}
		
		mysqli_free_result($res);
		// Cache failed queries, to prevent unnecessary API calls, but return a
		// blank string to prevent overwriting the default Aleph citation.
		if (stristr($content, "Record does not exist")) {
			$content = "";
		}
	}
	mysqli_close($mysqli);
	return $content;
}
?>
