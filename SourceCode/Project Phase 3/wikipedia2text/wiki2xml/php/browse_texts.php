<?php

require_once ( "default.php" ) ;
require_once ( "global_functions.php" ) ;
require_once ( "filter_named_entities.php" ) ;
require_once ( "content_provider.php" ) ;
require_once ( "wiki2xml.php" ) ;
require_once ( "xml2xhtml.php" ) ;
require_once ( "mediawiki_converter.php" ) ;

# FUNCTIONS

function get_param ( $key , $default = "" ) {
	if ( !isset ( $_REQUEST[$key] ) ) return $default ;
	return $_REQUEST[$key] ;
}

# MAIN

@set_time_limit ( 0 ) ; # No time limit

$xmlg = array (
	'site_base_url' => "SBU" ,
	'resolvetemplates' => true ,
	'templates' => array () ,
	'namespace_template' => 'Vorlage' ,
) ;

$content_provider = new ContentProviderTextFile ;
$converter = new MediaWikiConverter ;

$title = urldecode ( get_param ( 'title' , urlencode ( 'Main Page' ) ) ) ;
$xmlg['page_title'] = $title ;

$format = strtolower ( get_param ( 'format' , 'xhtml' ) ) ;
$content_provider->basedir = $base_text_dir ;

$text = $content_provider->get_wiki_text ( $title ) ;
$xml = $converter->article2xml ( $title , $text , $xmlg ) ;

if ( $format =="xml" ) {
	# XML
	header('Content-type: text/xml; charset=utf-8');
	print "<?xml version='1.0' encoding='UTF-8' ?>\n" ;
	print $xml ;
} else if ( $format == "text" ) {
	# Plain text
	$xmlg['plaintext_markup'] = true ;
	$xmlg['plaintext_prelink'] = true ;
	$out = $converter->articles2text ( $xml , $xmlg ) ;
	$out = str_replace ( "\n" , "<br/>" , $out ) ;
	header('Content-type: text/html; charset=utf-8');
	print $out ;
} else {
	# XHTML
	if ( stristr($_SERVER["HTTP_ACCEPT"],"application/xhtml+xml") ) {
		# Skipping the "strict" part ;-)
		header("Content-type: text/html; charset=utf-8");
#		header("Content-type: application/xhtml+xml");
	} else {
		# Header hack for IE
		header("Content-type: text/html; charset=utf-8");
	}
	print $converter->articles2xhtml ( $xml , $xmlg ) ;
}

?>
