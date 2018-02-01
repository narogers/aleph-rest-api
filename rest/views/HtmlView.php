<?php

class HtmlView extends ApiView {
    public function render($content) {
        header('Content-Type: text/html; charset=utf8');
		if ($content["search"]["result"]['citation']["@cdata"]) {
			print $content["search"]["result"]['citation']["@cdata"];
		} else {
			//print_r($content);
			if ($content["search"]["result"]["link"]["href"]["@value"] && $content["search"]["result"]["link"]["text"]["@cdata"]) {
				print '<a href="'.$content["search"]["result"]["link"]["href"]["@value"].'" target="'.$content["search"]["result"]["link"]["target"]["@value"].'"'.
				' rel="nofollow">'.$content["search"]["result"]["link"]["text"]["@cdata"].$content["search"]["result"]["link"]["text"]["@value"].'</a>';
			} else if (
			$content["alephid"]["result"]['holdings']["link"]["#href"] && $content["alephid"]["result"]['holdings']["link"]["text"]
			|| $content["oclc"]["result"]['holdings']["link"]["#href"] && $content["oclc"]["result"]['holdings']["link"]["text"]) {
				
				if (is_array($content["alephid"])) { $ret_key = "alephid"; } else { $ret_key = "oclc"; }
				
				print '<ul><li><a href="'.$content[$ret_key]["result"]['holdings']["link"]["#href"].'" rel="nofollow" target="'.$content[$ret_key]["result"]['holdings']["link"]["#target"].'">'.$content[$ret_key]["result"]['holdings']["link"]["text"].'</a></li>'."\n";
				print '<li><a href="'.$content[$ret_key]["result"]['bib']["link"]["#href"].'" rel="nofollow" target="'.$content[$ret_key]["result"]['bib']["link"]["#target"].'">'.$content[$ret_key]["result"]['bib']["link"]["text"].'</a></li>'."\n";
				print '<li><a href="'.$content[$ret_key]["result"]['callslip']["link"]["#href"].'" rel="nofollow" target="'.$content[$ret_key]["result"]['callslip']["link"]["#target"].'">'.$content[$ret_key]["result"]['callslip']["link"]["text"].'</a></li>';
				if ($content[$ret_key]["result"]["citation"]) {
					print "<li>".$content[$ret_key]["result"]["citation"]["mla"]."</li>";
				}
				print "</ul>";
			} else if ($content["nationality"]["result"]["link"]["#href"]) {
				print '<a href="'.$content["nationality"]["result"]["link"]["#href"].'" target="'.$content["nationality"]["result"]["link"]["#target"].'" rel="nofollow">'.$content["nationality"]["result"]["link"]["text"].'</a>';
			} else if ($content["message"]["header"]) {
				print "<h3>".$content["message"]["header"]."</h3>";
				print implode("<br />",$content["message"]["body"]);
			}
				return true;
			}
		}
}
