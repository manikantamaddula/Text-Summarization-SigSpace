<?php

# PHP4 and early PHP5 bug workaround:
require_once ( "filter_named_entities.php" ) ;

require_once ( "global_functions.php" ) ;
require_once ( "wiki2xml.php" ) ;
require_once ( "content_provider.php" ) ;

# A funtion to remove directories and subdirectories
# Modified from php.net
function SureRemoveDir($dir) {
   if(!$dh = @opendir($dir)) return;
   while (($obj = readdir($dh))) {
     if($obj=='.' || $obj=='..') continue;
     if (!@unlink($dir.'/'.$obj)) {
         SureRemoveDir($dir.'/'.$obj);
     }
   }
   @closedir ( $dh ) ;
   @rmdir($dir) ;
}

/**
 * The main converter class
 */
class MediaWikiConverter {

	/**
	 * Converts a single article in MediaWiki format to XML
	 */
	function article2xml ( $title , &$text , $params = array () ) {
		global $content_provider , $wiki2xml_authors ;
		$ot = $title ;
		$title = urlencode ( $title ) ;
		$p = new wiki2xml ;
		$p->auto_fill_templates = $params['resolvetemplates'] ;
		$p->template_list = array () ; ;
		foreach ( $params['templates'] AS $x ) {
			$x = trim ( ucfirst ( $x ) ) ;
			if ( $x != "" ) $p->template_list[] = $x ;
		}
		$xml = '<article' ;
		if ( $title != "" ) {
			$xml .= " title='{$title}'" ;
			$content_provider->add_article ( urldecode ( $ot ) ) ;
		}
		$xml .= '>' ;
		$xml .= $p->parse ( $text ) ;
		if ( count ( $wiki2xml_authors ) > 0 ) {
			$xml .= "<authors>" ;
			foreach ( $wiki2xml_authors AS $author )
				$xml .= "<author>{$author}</author>" ;
			$xml .= "</authors>" ;
		}
		$xml .= "</article>" ;
		return $xml ;
	}
	
	/**
	 * Converts XML to plain text
	 */
	function articles2text ( &$xml , $params = array () ) {
		global $wiki2xml_authors ;
		require_once ( "xml2txt.php" ) ;

		$wiki2xml_authors = array () ;
		$x2t = new xml2php ;
		$tree = $x2t->scanString ( $xml ) ;
		if ( $params['plaintext_markup'] ) {
			$tree->bold = '*' ;
			$tree->italics = '/' ;
			$tree->underline = '_' ;
		}
		if ( $params['plaintext_prelink'] ) {
			$tree->pre_link = "&rarr;" ;
		}
		
		$text = trim ( $tree->parse ( $tree ) ) ;
		
		$authors = "" ;
		if ( count ( $wiki2xml_authors ) > 0 ) {
			asort ( $wiki2xml_authors ) ;
			$authors = "\n--------------------\nTHE ABOVE TEXT IS LICENSED UNDER THE GFDL. CONTRIBUTORS INCLUDE:\n\n" .
						implode ( ", " , $wiki2xml_authors ) ;
		}
		
		return $text . $authors ;
	}

	/**
	 * Converts XML to XHTML
	 */
	function articles2xhtml ( &$xml , $params = array () ) {
		global $xml2xhtml ;
		require_once ( "xml2xhtml.php" ) ;
		$lang = "EN" ; # Dummy

		$ret = "" ;
		$ret .= '<?xml version="1.0" encoding="UTF-8" ?>' ;
		$ret .= '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//' . $lang . '" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">' ;
		if ( !$params['xhtml_source'] )
			$ret .= '<html xmlns="http://www.w3.org/1999/xhtml">' ;
		else $ret .= '<html>' ;
		$ret .= '<head>' ;
		#$ret .= '<link rel="stylesheet" type="text/css" media="screen,projection" href="http://de.wikipedia.org/skins-1.5/monobook/main.css" />' ;
		#$ret .= '<link rel="stylesheet" type="text/css" media="print" href="http://en.wikipedia.org/skins-1.5/common/commonPrint.css" />' ;
		$ret .= '<link rel="stylesheet" type="text/css" href="href://' ;
		$ret .= $params["site_base_url"] . 'index.php?title=MediaWiki:Common.css&amp;action=raw" />' ;
		#$ret .= '<link rel="stylesheet" type="text/css" href="href://' ;
		#$ret .= $params["site_base_url"] . 'index.php?title=MediaWiki:Monobook.css&amp;action=raw" />' ;
		$ret .= '<title>' ;
		if ( isset ( $params['page_title'] ) ) $ret .= $params['page_title'] ;
		else $ret .= $params["book_title"] ;
		$ret .= '</title>' ;
		$ret .= '</head>' ;
		$ret .= '<body>' ;

		convert_xml_xhtml ( $xml ) ;
		$ret .= $xml2xhtml->s ;
		
#		$xml2xhtml = new XML2XHTML ;
#		$ret .= $xml2xhtml->scan_xml ( $xml ) ;
		
		$ret .= '</body>' ;
		$ret .= '</html>' ;
		return $ret ;
	}

	/**
	 * Converts XML to ODT XML
	 */
	function articles2odt ( &$xml , $params = array () , $use_gfdl = false ) {
		global $wiki2xml_authors , $xml2odt ;
		require_once ( "xml2odt.php" ) ;
		
		# XML text to tree
		$xml2odt = new XML2ODT ;
		$wiki2xml_authors = array () ;
		$x2t = new xml2php ;
		$tree = $x2t->scanString ( $xml ) ;

		# Tree to ODT
		$out = "<?xml version='1.0' encoding='UTF-8' ?>\n" ;
		$body = $tree->parse ( $tree ) ;
		$out .= $xml2odt->get_odt_start () ;
		$out .= '<office:body><office:text>' ;
		$out .= $body ;
		$out .= '</office:text></office:body>' ;
		$out .= "</office:document-content>" ;
		return $out ;
		}

	/**
	 * Converts XML to DocBook XML
	 */
	function articles2docbook_xml ( &$xml , $params = array () , $use_gfdl = false ) {
		global $wiki2xml_authors ;
		require_once ( "xml2docbook_xml.php" ) ;

		$wiki2xml_authors = array () ;
		$x2t = new xml2php ;
		$tree = $x2t->scanString ( $xml ) ;

		# Chosing DTD; parameter-given or default
		$dtd = "" ;
		if ( isset ( $params['docbook']['dtd'] ) )
			$dtd = $params['docbook']['dtd'] ;
		if ( $dtd == "" ) $dtd = 'http://www.oasis-open.org/docbook/xml/4.4/docbookx.dtd' ;

		$out = "<?xml version='1.0' encoding='UTF-8' ?>\n" ;
		$out .= '<!DOCTYPE book PUBLIC "-//OASIS//DTD DocBook XML V4.4//EN" "' . $dtd . '"' ;
		if ( $use_gfdl ) {
			$out .= "\n[<!ENTITY gfdl SYSTEM \"gfdl.xml\">]\n" ;
		}
		$out .= ">\n\n<book>\n" ;
		$out2 = trim ( $tree->parse ( $tree ) ) ;
		
		$out .= "<bookinfo>" ;
		$out .= "<title>" . $params['book_title'] . "</title>" ;
		if ( count ( $wiki2xml_authors ) > 0 ) {
			asort ( $wiki2xml_authors ) ;
			$out .= "<authorgroup>" ;
			foreach ( $wiki2xml_authors AS $author ) {
				$out .= "<author><othername>{$author}</othername></author>" ;
			}
			$out .= "</authorgroup>" ;
		}
		$out .= "<legalnotice><para>" ;
		$out .= "Permission to use, copy, modify and distribute this document under the GNU Free Documentation License (GFDL)." ;
		$out .= "</para></legalnotice>" ;
		$out .= "</bookinfo>" ;
		
		$out .= $out2 ;
/*		
		if ( count ( $wiki2xml_authors ) > 0 ) {
			asort ( $wiki2xml_authors ) ;
			$out .= "<appendix>" ;
			$out .= "<title>List of contributors</title>" ;
			$out .= "<para>All text in this document is licensed under the GFDL. The following is a list of contributors (anonymous editors are not listed).</para>" ;
			$out .= "<para>" ;
			$out .= implode ( ", " , $wiki2xml_authors ) ;
			$out .= "</para>" ;
			$out .= "</appendix>" ;
		}
*/
		if ( $use_gfdl ) {
			$out .= "\n&gfdl;\n" ;
		}
		
		$out .= "\n</book>\n" ;

		return $out ;
	}
	
	/**
	 * Converts XML to PDF via DocBook
	 * Requires special parameters in local.php to be set (see sample_local.php)
	 * Uses articles2docbook_xml
	 */
	function articles2docbook_pdf ( &$xml , $params = array () , $mode = "PDF" ) {
		global $xmlg ;
		$docbook_xml = $this->articles2docbook_xml ( $xml , $params , $params['add_gfdl'] ) ;
		
		# Create temporary directory
		$temp_dir = "MWC" ;
		$temp_dir .= substr ( mt_rand() , 0 , 4 ) ;
		$temp_dir = tempnam ( $params['docbook']['temp_dir'], $temp_dir ) ;
		$project = basename ( $temp_dir ) ;
		unlink ( $temp_dir ) ; # It is currently a file, so...
		mkdir ( $temp_dir ) ;
		
		# Write XML file
		$xml_file = $temp_dir . "/" . $project . ".xml" ;
		$handle = fopen ( $xml_file , 'wb' ) ;
		fwrite ( $handle , utf8_encode ( $docbook_xml ) ) ;
		fclose ( $handle ) ;
		if ( $params['add_gfdl'] ) {
			copy ( $xmlg['sourcedir'] . "/gfdl.xml" , $temp_dir . "/gfdl.xml" ) ;
		}

		if ( $params['docbook']['out_dir'] ) {
			$output_dir = $params['docbook']['out_dir'];
		} else {
			$output_dir = $params['docbook']['temp_dir'];
		}

		
		# Call converter
		if ( $mode == "PDF" ) {
			$command = str_replace ( "%1" , $xml_file , $params['docbook']['command_pdf'] ) ;
			$out_subdir = 'pdf' ;
		} else if ( $mode == "HTML" ) {
			$command = str_replace ( "%1" , $xml_file , $params['docbook']['command_html'] ) ;
			$out_subdir = 'html' ;
		}

		# PHP4 does not have recursive mkdir
		$output_dir = $output_dir . '/' . $out_subdir ;
		if ( ! file_exists( $output_dir ) ) {
			mkdir ( $output_dir ) ;
		}
		$output_dir = $output_dir . '/' . $project;
		if ( ! file_exists( $output_dir ) ) {
			mkdir ( $output_dir ) ;
		}

		$command = $command . ' --nochunks --output ' . $output_dir;

		exec ( $command ) ;
		
		# Cleanup xml file
		SureRemoveDir ( $temp_dir ) ;
		
		# Check if everything is OK
		$output_filename = $output_dir . '/' . $project . '.' . $out_subdir ;
		if ( !file_exists ( $output_filename ) ) {
			header('Content-type: text/html; charset=utf-8');
			print "ERROR : Document was not created: Docbook creator has failed! Command was: $command. output_filename = $output_filename" ;
		}
		
		# Return pdf filename
		return $output_filename ;
	}
}


?>
