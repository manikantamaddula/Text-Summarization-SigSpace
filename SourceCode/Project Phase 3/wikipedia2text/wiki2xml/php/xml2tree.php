<?php

/**
 * This class converts an XML string to a tree structure
 * based on the "element" class that must be defined outside
 * prior to including this file
*/

$ancStack = array (); // the stack with ancestral elements

// START Three global functions needed for parsing, sorry guys
/** @todo document */
function wgXMLstartElement($parser, $name, $attrs) {
	global $ancStack;

	$newElem = new element;
	$newElem->name = $name;
	$newElem->attrs = $attrs;

	array_push($ancStack, $newElem);
}

/** @todo document */
function wgXMLendElement($parser, $name) {
	global $ancStack, $rootElem;
	// pop element off stack
	$elem = array_pop($ancStack);
	if (count($ancStack) == 0)
		$rootElem = $elem;
	else
		// add it to its parent
		array_push($ancStack[count($ancStack) - 1]->children, $elem);
}

/** @todo document */
function wgXMLcharacterData($parser, $data) {
	global $ancStack;
	// add to parent if parent exists
	if ($ancStack && trim ( $data ) != "") {
		array_push($ancStack[count($ancStack) - 1]->children, $data);
	}
}
// END Three global functions needed for parsing, sorry guys

/**
 * Here's the class that generates a nice tree
 * @package MediaWiki
 * @subpackage Experimental
 */
class xml2php {

	/** @todo document */
	function & scanFile($filename) {
		global $ancStack, $rootElem;
		$ancStack = array ();

		$xml_parser = xml_parser_create();
		xml_set_element_handler($xml_parser, 'wgXMLstartElement', 'wgXMLendElement');
		xml_set_character_data_handler($xml_parser, 'wgXMLcharacterData');
		if (!($fp = fopen($filename, 'r'))) {
			die('could not open XML input');
		}
		while ($data = fread($fp, 4096)) {
			if (!xml_parse($xml_parser, $data, feof($fp))) {
				die(sprintf("XML error: %s at line %d", xml_error_string(xml_get_error_code($xml_parser)), xml_get_current_line_number($xml_parser)));
			}
		}
		xml_parser_free($xml_parser);

		// return the remaining root element we copied in the beginning
		return $rootElem;
	}

	/** @todo document */
	function scanString($input) {
		global $ancStack, $rootElem;
		$ancStack = array ();

		$xml_parser = xml_parser_create();
		xml_set_element_handler($xml_parser, 'wgXMLstartElement', 'wgXMLendElement');
		xml_set_character_data_handler($xml_parser, 'wgXMLcharacterData');
		
		if ( is_array ( $input ) ) {
			xml_parse($xml_parser, xml_articles_header() , false) ;
			while ( $x = xml_shift ( $input ) ) {
				xml_parse($xml_parser, $x, false) ;
			}

			xml_parse($xml_parser, '</articles>', true)  ;
		} else {
			xml_parse($xml_parser, xml_articles_header() , false) ;
			if (!xml_parse($xml_parser, $input, false)) {
				die(sprintf("XML error: %s at line %d", xml_error_string(xml_get_error_code($xml_parser)), xml_get_current_line_number($xml_parser)));
			}
			xml_parse($xml_parser, '</articles>', true)  ;
		}
		
		xml_parser_free($xml_parser);

		// return the remaining root element we copied in the beginning
		return $rootElem;
	}

}

?>
