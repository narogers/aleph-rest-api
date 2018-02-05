<?php

class PhpView extends ApiView {
    public function render($content) {
        header('Content-Type: text/plain; charset=utf8');
		self::clean_from_xml($content);
		print var_export($content,true);
        return true;
    }
}
