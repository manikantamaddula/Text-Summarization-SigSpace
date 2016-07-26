<?php

# Add authors to global list
function add_authors ( $authors ) {
	global $wiki2xml_authors ;
	foreach ( $authors AS $author ) {
		if ( !in_array ( $author , $wiki2xml_authors ) ) {
			$wiki2xml_authors[] = $author ;
		}
	}
}

function add_author ( $author ) {
	add_authors ( array ( $author ) ) ;
	}


# For text file structure creation and browsing
function get_file_location_global ( $basedir , $ns , $title , $make_dirs = false ) {
	$title = urlencode ( $title ) ;
	$title = str_replace ( ":" , "_" , $title ) ;
	$m = md5 ( $title ) ;
	$ret = "" ;
	$ret->file = $title ;
	if ( $ret->file == "Con" ) $ret->file = "_Con" ; # Windows can't create files named "con.txt" (!), workaround
	$ret->dir = $basedir . "/" . $ns ;
	if ( $make_dirs ) @mkdir ( $ret->dir ) ;
	$ret->dir .= "/" . substr ( $m , 0 , 1 ) ;
	if ( $make_dirs ) @mkdir ( $ret->dir ) ;
	$ret->dir .= "/" . substr ( $m , 1 , 2 ) ;
	if ( $make_dirs ) @mkdir ( $ret->dir ) ;
	$ret->fullname = $ret->dir . "/" . $ret->file ;
	return $ret ;
}


function xml_articles_header() {
	global $xmlg ;
#	if ( !isset ( $xmlg['xml_articles_header'] ) ) return "" ;
	return $xmlg['xml_articles_header'] ;
}

?>
