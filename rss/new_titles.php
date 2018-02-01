<?php

//Change the base variable to match the logical base you are searching
$base = getenv('REST_ALEPH_LIBRARY') ?: "cma01";

//Change library_url to match the base url of your catalog
$library_url = getenv('REST_CATALOG_URI') ?: "localhost/opac";
$base_url = "$library_url/X?op=find&base=$base";
$call_num_search = "&request=cdl=";

$sort_url = "$library_url/X?op=sort-set&base=STACKS&library=.$base";
$sort_code = "&sort_code_1=08&sort_order_1=D&sort_code_2=03&sort_order_2=A";

$display_url = "$library_url/X?op=present&base=STACKS&library=.$base";
$set_entry = "&set_entry=001-";

/** 
 * Most of this RSS generation code comes from Peter Skalar's book PHP 5
 * All you really need to know is that it is the framework for creating the 
 * RSS feed
 *
 * Best to leave this part alone if you don't know what you're doing
 */
class RSS extends DomDocument {
    function __construct($title, $link, $description) {
        // Set this document up as XML 1.0 with a root
        // <rss> element that has a version="0.91" attribute
        parent::__construct('1.0');
        $rss = $this->createElement('rss');
        $rss->setAttribute('version', '0.91');
        $this->appendChild($rss);
        
        // Create a <channel> element with <title>, <link>,
        // and <description> sub-elements
        $channel = $this->createElement('channel');
        $channel->appendChild($this->makeTextNode('title', $title));
        $channel->appendChild($this->makeTextNode('link', $link));
        $channel->appendChild($this->makeTextNode('description', $description));

        // Add <channel> underneath <rss>
        $rss->appendChild($channel);

        // Set up output to print with linebreaks and spacing
        $this->formatOutput = true;
    }

    // This function adds an <item> to the <channel>
    function addItem($title, $link, $description) {
        // Create an <item> element with <title>, <link>
        // and <description> sub-elements
        $item = $this->createElement('item');
        $item->appendChild($this->makeTextNode('title', $title));
        $item->appendChild($this->makeTextNode('link', $link));

        // Add the <item> to the <channel>
        $channel = $this->getElementsByTagName('channel')->item(0);
        $channel->appendChild($item);
    }

    function addItems($items) {
	$item_url = $library_url."/F/?func=find-c&ccl_term=sys=";
		foreach ($items as $item) {
			$this->addItem($item->title, $item_url.$item->number, $item->subtitle);
        }
    }

    // A helper function to make elements that consist entirely
    // of text (no sub-elements)
    private function makeTextNode($name, $text) {
        $element = $this->createElement($name);
        $element->appendChild($this->createTextNode($text));
        return $element;
    }
}

/** 
 * The RSS feeds we're generating need 4 things (title, link, description, and 
 * a filename) but we're only going to pull 3 here
 *
 * filename, feed title, and a URL that will hit the Aleph X-server with the 
 * request we're looking for
 *
 * then in the next section with the set number from the request we'll get 
 * the other information
 */
$library_url= getenv('REST_ALEPH_X') ?: "$library_url/X";

/**
 * The actual request in the url can be modified in any number of ways - 
 * here i show how to do call numbers and format (cdl and wtp respectively) 
 * you can create complex queries but the x server can be very finicky in 
 * what format it will accept
 */
$feed = new stdClass();
$feed->filename = 'recent.rss';
$feed->feed_title = 'Fiction';
$feed->request_url = "$library_url?op=find&base=stacks&library=$base&request=%28+WIPC+%3D+MT";

$arr_feeds[]=$feed;

foreach ($arr_feeds as $feed) {
	$rss = new RSS($feed->feed_title, $library_url, 
           'Weekly updates to the catalog.');
	$result = simplexml_load_file($feed->request_url);
	$set_number = $result->set_number;
	$record_count = $result->no_records;
	
	if (empty($record_count))
		continue;
		
	simplexml_load_file($sort_url."&set_number=".$set_number.$sort_code);

	$result = simplexml_load_file($display_url.$set_entry.$record_count."&set_number=".$set_number);

	foreach ($result as $record){
		$doc_number_result = $record->xpath("doc_number");
		$doc_number = $doc_number_result[0];
		$title_result = $record->xpath("metadata/oai_marc/varfield[@id='245']/subfield[@label='a']");
		$subtitle_result = $record->xpath("metadata/oai_marc/varfield[@id='245']/subfield[@label='b']");

		$title = trim((empty($title_result[0]) ? '' : rtrim((string)$title_result[0],"/")) . (empty($subtitle_result[0]) ? '' :  rtrim((string)$subtitle_result[0],"/")));
		$callnumber_result = $record->xpath("metadata/oai_marc/varfield[@id='852']/subfield[@label='h']");
		$callnumber2_result = $record->xpath("metadata/oai_marc/varfield[@id='852']/subfield[@label='i']");
		$callnumber3_result = $record->xpath("metadata/oai_marc/varfield[@id='852']/subfield[@label='j']");
        $callnumber3_result = $record->xpath("metadata/oai_marc/varfield[@id='050']/subfield[@label='a']");
        $callnumber4_result = $record->xpath("metadata/oai_marc/varfield[@id='050']/subfield[@label='b']");
		$location_result = $record->xpath("metadata/oai_marc/varfield[@id='852']/subfield[@label='b']");
        if (empty($location_result)) {
            $location_result = $callnumber4_result[0];
        }

		$description = "Location: ".$location_result[0]." ".(empty($callnumber_result[0]) ? '' : (string)$callnumber_result[0]).(empty($callnumber2_result[0]) ? '' : (string)$callnumber2_result[0]).(empty($callnumber3_result[0]) ? '' : (string)$callnumber3_result[0]);

    if (!empty($title))
      $rss->addItem($title, $library_url."/F/?func=find-c&ccl_term=sys=".$doc_number, $description);
    }

	echo $rss->saveXML();
}
?>

