<?php

/**
 * This file contains the /element/ class needed by xml2tree.php
 * to create a tree which is then converted into plain text
 */

class element {
	var $name = '';
	var $attrs = array ();
	var $children = array ();
	
	# Temporary variables for link tags
	var $link_target = "" ;
	var $link_trail = "" ;
	var $link_parts = array () ;
	
	# Variables only used by $tree root
	var $list = array () ;
	var $iter = 1 ;
	var $bold = "" ;
	var $italics = "" ;
	var $underline = "" ;
	var $pre_link = "" ;
	
	/**
	 * Parse the children ... why won't anybody think of the children?
	 */
	function sub_parse(& $tree) {
		$ret = '' ;
		foreach ($this->children as $key => $child) {
			if (is_string($child)) {
				$ret .= $child ;
			} elseif ($child->name != 'ATTRS') {
				$sub = $child->parse ( $tree ) ;
				if ( $this->name == 'LINK' ) {
					if ( $child->name == 'TARGET' ) $this->link_target = $sub ;
					else if ( $child->name == 'PART' ) $this->link_parts[] = $sub ;
					else if ( $child->name == 'TRAIL' ) $this->link_trail = $sub ;
				}
				$ret .= $sub ;
			}
		}
		return $ret ;
	}
	
	/* 
	 * Parse the tag
	 */
	function parse ( &$tree ) {
		global $content_provider , $wiki2xml_authors , $xmlg ;
		$ret = '';
		$tag = $this->name ;
		$is_root = ( $tree->iter == 1 ) ;
		$tree->iter++ ;
		
		if ( $tag == 'SPACE' ) $ret .= ' ' ;
		else if ( $tag == 'HEADING' ) $ret .= "\m\n";
		else if ( $tag == 'PARAGRAPH' ) $ret .= "\n";
		else if ( $tag == 'TABLECELL' ) $ret .= "\n";
		else if ( $tag == 'TABLECAPTION' ) $ret .= "\n";
		else if ( $tag == 'TEMPLATE' ) return "" ; # Ignore unresolved template
		else if ( $tag == 'AUTHOR' ) { # Catch author for display later
			$author = $this->sub_parse ( $tree ) ;
			if ( !in_array ( $author , $wiki2xml_authors ) )
				$wiki2xml_authors[] = $author ;
			return "" ;
		}

		if ( $tag == "LINK" ) {
			$sub = $this->sub_parse ( $tree ) ;
			$link = "" ;
			if ( isset ( $this->attrs['TYPE'] ) AND strtolower ( $this->attrs['TYPE'] ) == 'external' ) {
				if ( $sub != "" ) $link .= $sub . " " ;
				$link .= '[' . $this->attrs['HREF'] . ']' ;
			} else {
				if ( count ( $this->link_parts ) > 0 ) $link = array_pop ( $this->link_parts ) ;
				$link_text = $link ;
				if ( $link == "" ) $link = $this->link_target ;
				$link .= $this->link_trail ;
				
				$ns = $content_provider->get_namespace_id ( $this->link_target ) ;
				
				
				if ( $ns == 6 ) { # Surround image text with newlines
					if ( $xmlg['text_hide_images'] ) $link = '' ;
					else {
						$nstext = explode ( ":" , $this->link_target , 2 ) ;
						$nstext = "" ;
#						array_shift ( $nstext ) ;
						$link = "\m(" . $nstext . ":" . $link . ")\n" ;
					}
				} else if ( $ns == -9 ) { # Adding newline to interlanguage link
					$link = "\m" . $link ;
				} else if ( $ns == -8 ) { # Adding newline to category link
					if ( $link_text == "!" || $link_text == '*' ) $link = "" ;
					else $link = " ({$link})" ;
					$link = "\m" . $this->link_target . $link . "\n" ;
				} else {
					$link = $tree->pre_link . $link ;
				}
			}
			
			$ret .= $link ;
		} else if ( $tag == "LIST" ) {
			$type = strtolower ( $this->attrs['TYPE'] ) ;
			$k = '*' ; # Dummy
			if ( $type == 'bullet' ) $k = "*" ;
			else if ( $type == 'numbered' ) $k = "1" ;
			else if ( $type == 'ident' ) $k = ">" ;
			array_push ( $tree->list , $k ) ;
			$ret .= $this->sub_parse ( $tree ) ;
			array_pop ( $tree->list ) ;
		} else if ( $tag == "LISTITEM" ) {
			$r = "" ;
			foreach ( $tree->list AS $k => $l ) {
				if ( $l == '*' ) $r .= '-' ;
				else if ( $l == '>' ) $r .= '<dd/>' ;
				else {
					$r .= $l . "." ;
				}
			}
			$ret .= "\m" . $r . " " ;
			$ret .= $this->sub_parse ( $tree ) ;
			if ( $tag == "LISTITEM" ) {
				$x = array_pop ( $tree->list ) ;
				if ( $x == "*" || $x == ">" ) array_push ( $tree->list , $x ) ; # Keep bullet
				else array_push ( $tree->list , $x + 1 ) ; # Increase last counter
			}
		} else {
			if ( $tag == "ARTICLE" && isset ( $this->attrs["TITLE"] ) ) {
				$ret .= strtoupper ( urldecode ( $this->attrs["TITLE"] ) ) . "\n" ;
			}
			if ( $xmlg['text_hide_tables'] && ( substr ( $tag , 0 , 5 ) == 'TABLE' || 
				$tag == 'XHTML:TABLE' || 
				$tag == 'XHTML:TH' || 
				$tag == 'XHTML:CAPTION' || 
				$tag == 'XHTML:TD' || 
				$tag == 'XHTML:TR' ) ) {
				$ret = '' ;
			} else {
				$ret .= $this->sub_parse ( $tree ) ;
				if ( $tag == "TABLEHEAD" || $tag == "XHTML:B" || $tag == "XHTML:STRONG" || $tag == "BOLD" ) $ret = $tree->bold . $ret . $tree->bold ;
				else if ( $tag == "XHTML:I" || $tag == "XHTML:EM" || $tag == "ITALICS" ) $ret = $tree->italics . $ret . $tree->italics ;
				else if ( $tag == "XHTML:U" ) $ret = $tree->underline . $ret . $tree->underline ;
				if ( $tag == "TABLEHEAD" ) $ret = "\n" . $ret ;
			}
		}

		$tree->iter-- ; # Unnecessary, since not really used
		
		if ( $is_root ) {
			$ret = str_replace ( "\m\m" , "\m" , $ret ) ;
			$ret = str_replace ( "\n\m" , "\n" , $ret ) ;
			$ret = str_replace ( "\m" , "\n" , $ret ) ;
		}
		
		return $ret;
	}
}

require_once ( "xml2tree.php" ) ;



//_______________________________________________________________
/*
$infile = "Biology.xml" ;
$xml = @file_get_contents ( $infile ) ;

print htmlentities ( $xml ) . "<hr>" ;

$x2t = new xml2php ;
$tree = $x2t->scanString ( $xml ) ;

$odt = new xml2odt ;
$odt->parse ( $tree ) ;
*/
?>
