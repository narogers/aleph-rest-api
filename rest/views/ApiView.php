<?php

class ApiView {
    protected function addCount($data) {
        if(empty($data)) {
            $data['meta']['count'] = 0;
        }
        return $data;
    }
	
	// Recursive function to remove XPath/XML-specific naming
    public function clean_from_xml(&$content) {
		foreach ($content as $k => $c) {
				if (array_key_exists("@value",$c)) {
						$content[$k]["value"] = $c["@value"];
						unset($content[$k]["@value"]);
				} else if (array_key_exists("@cdata",$c)) {
						$content[$k]["value"] = $c["@cdata"];
						unset($content[$k]["@cdata"]);
				} else if (is_array($c)) {
						self::clean_from_xml($c);
						$content[$k] = $c;
				}
		}
		return $content;
    }
}
