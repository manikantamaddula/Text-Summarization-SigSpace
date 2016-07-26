<?php

# Change there to your local settings
$dumpfile = "K:\\dewiki-20060327-pages-articles.xml" ;
$basedir = "C:" ;

#______________________________________________________________________________
# GLOBAL VARIABLES
$dir = "" ;
$namespaces = array () ;
$mem = array () ;
$tags = array () ;
$page_counter = 0 ;

# FUNCTIONS

require_once ( "global_functions.php" ) ;

function store_file ( &$loc , &$text , $mode = "text" ) {
	if ( $mode == "text" ) {
		if ( !$handle = fopen($loc->fullname.".txt", 'wb') ) {
			print "Failed to open {$loc->file}.txt!<br/>" ;
			flush () ;
		}
		fwrite($handle, $text) ;
		fclose ( $handle ) ;
	} else if ( $mode == "gzip" ) {
		if ( !$gz = gzopen($loc->fullname.".gz",'w9') ) {
			print "Failed to open {$loc->file}.gz!<br/>" ;
			flush () ;
		}
		gzwrite($gz, $text);
		gzclose($gz);		
	}
}

function microtime_float()
{
   list($usec, $sec) = explode(" ", microtime());
   return ((float)$usec + (float)$sec);
}

# Global functions for parsing

function XML2TXT_START($parser, $name, $attrs) {
	global $mem , $tags ;
	$mem["name"] = $name ;
	$tags[] = $name ;
	if ( $name == "NAMESPACE" ) {
		$mem['key'] = $attrs["KEY"] ;
	} else if ( $name == "TEXT" ) {
		$mem['text'] = "" ;
	}
}

function XML2TXT_END($parser, $name) {
	global $mem , $namespaces , $tags , $page_counter , $dir ;
	if ( $mem['name'] == 'NAMESPACE' ) {
		$namespaces[$mem['key']] = $mem['text'] ;
	} else if ( $mem['name'] == 'PAGE' ) {
		$loc = get_file_location_global ( $dir , $mem['namespace'] , $mem['title'] , true ) ;
		store_file ( $loc , $mem['text'] , 'text' ) ;
	
		$page_counter++ ;
		if ( $page_counter % 1000 == 0 ) {
			print '.' ;
			if ( $page_counter % 50000 == 0 ) print "<br/>" ;
			flush () ;
		}
	}

	array_pop ( $tags ) ;
	if ( count ( $tags ) > 0 ) {
		$mem['name'] = array_pop ( $tags ) ;
		$tags[] = $mem['name'] ;
	} else {
		$mem['name'] = "" ;
	}
}

function XML2TXT_DATA ( $parser, $data ) {
	global $mem , $namespaces ;
	if ( $mem['name'] == 'NAMESPACE' ) {
		$mem['text'] = $data ;
	} else if ( $mem['name'] == 'TITLE' ) {
		$ns = 0 ;
		foreach ( $namespaces AS $k => $v ) {
			if ( $k <= 0 ) continue ;
			if ( substr ( 0 , strlen ( $v ) + 1 ) != $v.":" ) continue ;
			$ns = $k ;
			$data = substr ( $data , strlen ( $v ) + 1 ) ;
			break ;
		}
		$mem['title'] = $data ;
		$mem['namespace'] = $ns ;
	} else if ( $mem['name'] == 'TEXT' ) {
		$mem['text'] .= $data ;
	}
}

function scan_xml_file ( $xml_filename ) {
	global $namespaces , $dir , $page_counter ;
	$xml_parser_handle = xml_parser_create();
	xml_set_element_handler($xml_parser_handle, "XML2TXT_START", "XML2TXT_END");
	xml_set_character_data_handler($xml_parser_handle, "XML2TXT_DATA"); 
	
	if (!($parse_handle = fopen($xml_filename, 'r'))) {
		die("FEHLER: Datei $xml_filename nicht gefunden.");
	}
	
	$t1 = microtime_float() ;
	while ($xml_data = fread($parse_handle, 8192)) {
		if (!xml_parse($xml_parser_handle, $xml_data, feof($parse_handle))) {
			die(sprintf('XML error: %s at line %d',
			xml_error_string(xml_get_error_code($xml_parser_handle)),
			xml_get_current_line_number($xml_parser_handle)));
		}
		
/*		if ( $page_counter % 100 == 0 ) {
			$t2 = microtime_float() - $t1 ;
			$t3 = $t2 * 1000 / $page_counter ;
			print $t3 . " sec/1000 pages<br/>" ; flush () ;
		}*/
	}
	$t2 = microtime_float() - $t1 ;
	print "Took {$t2} seconds total.<br/>" ; flush () ;

	xml_parser_free($xml_parser_handle); 
	
	$handle = fopen($dir."/namespaces.txt", 'wb') ;
	foreach ( $namespaces AS $ns => $nst ) {
		$t = "{$ns}:{$nst}\n" ;
		fwrite($handle, $t) ;
	}
	fclose ( $handle ) ;
	
}


# MAIN

$dir = array_pop ( explode ( "/" , str_replace ( "\\" , "/" , $dumpfile ) ) ) ;
$dir = $basedir . "/" . str_replace ( ".xml" , "" , $dir ) ;

@set_time_limit ( 0 ) ; # No time limit
#ini_set('user_agent','MSIE 4\.0b2;'); # Fake user agent
header ('Content-type: text/html; charset=utf-8');
@mkdir ( $dir ) ;
scan_xml_file ( $dumpfile ) ;

?>
