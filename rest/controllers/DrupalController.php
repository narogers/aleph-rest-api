<?php

class DrupalController extends CMAController
{
    public function getAction($request) {
        if(isset($request->url_elements[2])) {
		$action = $request->url_elements[2];
		$base = getenv('REST_HOSTNAME') ?: "localhost";
		switch($request->url_elements[2]) {
			case "blog":
				$xml = file_get_contents($base."blog/rss/");
				$x = simplexml_load_string($xml);
				if(count($x) == 0)
				    return;

				foreach($x->channel->item as $item) {
					$data[] = array("url" => (string) $item->link, 
							"title" => (string) trim($item->title),
							"pubDate" => (string) $item->pubDate,
							"timestamp" => strtotime((string) $item->pubDate),
							"excerpt" => (string) trim($item->description),
							"author" => (string) $item->author,
							"guid" => (string) $item->guid);
				}
				break;
			default:
				$data["message"] = "Unsupported function.";
				return $data;
				break;
		}
        } else {
                $data["message"] = "Available functions: <ul><li><strong><a href=\"./artist\">blog</a></strong></li></ul>";
        }
        return $data;
    }

    public function postAction($request) {
        $data = $request->parameters;
        $data['message'] = "This data was submitted";
        return $data;
    }
}
