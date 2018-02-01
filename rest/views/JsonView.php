<?php

class JsonView extends ApiView {
    public function render($content) {
        header('Content-Type: application/json; charset=utf8');
		self::clean_from_xml($content);
		$json = json_encode($content);
		echo isset($_GET['callback']) ? "{$_GET['callback']}($json);" : $json;
        return true;
    }
}
