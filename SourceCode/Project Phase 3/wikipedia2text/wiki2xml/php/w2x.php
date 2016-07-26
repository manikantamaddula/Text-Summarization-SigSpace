<?php
# Copyright by Magnus Manske (2005 - 2006)
# Released under GPL

@set_time_limit ( 0 ) ; # No time limit
ini_set ('memory_limit', 1024*1024*64); # 64MB upper limit
ini_set('user_agent','MSIE 4\.0b2;'); # Fake user agent

if( !defined( 'MEDIAWIKI' ) ) { # Stand-alone
	include_once ( "default.php" ) ; # Which will include local.php, if available
}

require_once ( "mediawiki_converter.php" ) ;

## TIMER FUNCTION

function microtime_float()
{
   list($usec, $sec) = explode(" ", microtime());
   return ((float)$usec + (float)$sec);
}

function get_form ( $as_extension = false ) {
	global $xmlg ;

	$_wt = get_param ( 'whatsthis' , 'articlelist' ) ;
	$wt['wikitext'] = $wt['articlelist'] = $wt['listpagename'] = "" ;
	$wt[$_wt] = 'checked' ;
	
	$xmlg["site_base_url"] = get_param('site',$xmlg["site_base_url"]) ;
	$_out = get_param ( 'output_format' , 'xml' ) ;
	$out['xml'] = $out['text'] = $out['translated_text'] = $out['xhtml'] = $out['docbook_xml'] = $out['docbook_pdf'] = $out['docbook_html'] = 
	$out['odt_xml'] = $out['odt'] = '' ;
	$out[$_out] = 'checked' ;
	$useapi_checked = $xmlg["useapi"] ? 'checked' : '' ;
	
	$optional = array () ;
	if ( isset ( $xmlg['docbook']['command_pdf'] ) ) {
		$optional[] = "<INPUT {$out['docbook_pdf']} type='radio' name='output_format' value='docbook_pdf'>DocBook PDF" ;
	}
	if ( isset ( $xmlg['docbook']['command_html'] ) ) {
		$optional[] = "<INPUT {$out['docbook_html']} type='radio' name='output_format' value='docbook_html'>DocBook HTML" ;
	}
	if ( isset ( $xmlg['zip_odt'] ) ) {
		$optional[] = "<INPUT {$out['odt_xml']} type='radio' name='output_format' value='odt_xml'>OpenOffice XML" ;
		$optional[] = "<INPUT {$out['odt']} type='radio' name='output_format' value='odt'>OpenOffice ODT" .
			"<input type='checkbox' name='odt_footnote' value='1' ".(get_param('odt_footnote',false)?'checked':'').">References as endnotes (instead of footnotes)" ;
	}
	$optional = "<br/>" . implode ( "<br/>" , $optional ) ;
	
	# dub sez... working images
	if ( $as_extension ) $site = "<input type='hidden' name='site' value='".$xmlg["site_base_url"]."'/>" ;
	else $site = "Site : http://<input type='text' name='site' value='".$xmlg["site_base_url"]."'/>/index.php<br/>" ;

	$additional = array() ;
	if ( $xmlg['allow_get'] ) {
		$additional[] = "This page can be called with parameters: w2x.php?doit=1&whatsthis=articlelist&site=en.wikipedia.org/w&output_format=odt&text=Biochemistry" ;
		$additional[] = "For additional parameters, see <a href='README'>here</a>" ;
	}
	
	# Plain text translation options
	$a = array (
		'en' => 'English',
		'de' => 'German',
		'fr' => 'French',
		'es' => 'Spanish',
		'it' => 'Italian',
	) ;
	asort ( $a ) ;
	$tttlo = "" ;
	foreach ( $a AS $b => $c ) {
		$tttlo .= "<option value='{$b}'>{$c}</option>" ;
	}
	
	$additional = "<div style='text-align:center; border-top:1px solid black;width:100%;font-size:12px'>" .
					implode ( "<br/>" , $additional ) .
					"</div>" ;

return "<form method='post'>
<h2>Paste article list or wikitext here</h2>
<table border='0' width='100%'><tr>
<td valign='top'><textarea rows='20' cols='80' style='width:100%' name='text'>" . 
get_param ( 'text' , '' ) . 
"</textarea></td>
<td width='200px' valign='top' nowrap>
<INPUT checked type='radio' name='use_templates' value='all'>Use all templates<br/>
<INPUT type='radio' name='use_templates' value='none'>Do not use templates<br/>
<INPUT type='radio' name='use_templates' value='these'>Use these templates<br/>
<INPUT type='radio' name='use_templates' value='notthese'>Use all but these templates<br/>
<textarea rows='15' cols='30' style='width:100%' name='templates'></textarea>
</td></tr></table>
<table border='0'><tr>
<td valign='top'>
This is<br/>
<INPUT {$wt['wikitext']} type='radio' name='whatsthis' value='wikitext'>raw wikitext <br/>
<INPUT {$wt['articlelist']} type='radio' name='whatsthis' value='articlelist'>a list of articles<br/>
<INPUT {$wt['listpagename']} type='radio' name='whatsthis' value='listpagename'>the name of an article with a list of pages<br/>

{$site}
<input type='checkbox' name='useapi' value='1' id='useapi' $useapi_checked /><label for='useapi'>Use API</label><br/>
Title : <input type='text' name='document_title' value='' size=40/><br/>
<input type='checkbox' name='add_gfdl' value='1' checked>Include GFDL (for some output formats)</input><br/>
<input type='checkbox' name='keep_categories' value='1' checked>Keep categories</input><br/>
<input type='checkbox' name='keep_interlanguage' value='1' checked>Keep interlanguage links</input><br/>
<input type='submit' name='doit' value='Convert'/>
</td><td valign='top' style='border-left:1px black solid'>
<b>Output</b>
<br/><INPUT {$out['xml']} type='radio' name='output_format' value='xml'>XML 
<br/><INPUT {$out['text']} type='radio' name='output_format' value='text'>Plain text 
 <input type='checkbox' name='plaintext_markup' value='1' ".(get_param('plaintext_markup',true)?'checked':'').">Use *_/ markup</input>
 <input type='checkbox' name='plaintext_prelink' value='1' ".(get_param('plaintext_prelink',true)?'checked':'').">Put &rarr; before internal links</input>
<br/><INPUT {$out['translated_text']} type='radio' name='output_format' value='translated_text'>Plain text, google-translated to
 <select name='translated_text_target_language'>{$tttlo}</select> (works only for wikipedia/wikibooks)
<br/><INPUT {$out['xhtml']} type='radio' name='output_format' value='xhtml'>XHTML 
 <input type='checkbox' name='xhtml_justify' value='1' ".(get_param('xhtml_justify',true)?'checked':'').">Align paragraphs as 'justify'</input>
 <input type='checkbox' name='xhtml_logical_markup' value='1' ".(get_param('xhtml_logical_markup',true)?'checked':'').">Use logical markup (e.g., 'strong' instead of 'b')</input>
 <input type='checkbox' name='xhtml_source' value='1' ".(get_param('xhtml_source',false)?'checked':'').">Return source XHTML</input>
<br/><INPUT {$out['docbook_xml']} type='radio' name='output_format' value='docbook_xml'>DocBook XML 
{$optional}
</tr></table>
</form>
<p>
Known issues:
<ul>
<li>In templates, {{{variables}}} used within &lt;nowiki&gt; tags will be replaced as well (too lazy to strip them)</li>
<li>HTML comments are removed (instead of converted into XML tags)</li>
</ul>{$additional}
</p>" ;
}

function get_param ( $s , $default = NULL ) {
	global $xmlg ;
	if ( $xmlg['allow_get'] ) {
		if ( isset ( $_REQUEST[$s] ) ) {
			return $_REQUEST[$s] ;
		} else {
			return $default ;
		}
	} else {
		if ( isset ( $_POST[$s] ) ) {
			return $_POST[$s] ;
		} else {
			return $default ;
		}
	}
}

# add one article to the stack of to-be-converted articles
function push_article ( &$aArticles, $article ) {

	# convert _ to ' '
	$a = trim( $article ); 
	if ( $a != "" ) {
		$aArticles[] = preg_replace( '/_/', ' ', $a ); 
		}

}

# Append XML, or links to XML temporary files
function append_to_xml ( &$xml , $new_xml ) {
	global $xmlg ;
	if ( $xmlg["use_xml_temp_files"] ) { # Use temp files
		if ( !is_array ( $xml ) ) $xml = array () ;
		
		do {
			$tmp_file_name = tempnam ( $xmlg["temp_dir"] , "XMLTMP" ) ;
			$tmp_file = fopen($tmp_file_name, 'wb') ;
		} while ( $tmp_file === false ) ;
		fwrite ( $tmp_file , $new_xml ) ;
		fclose ( $tmp_file ) ;
		
		$xml[] = $tmp_file_name ;
	} else { # Do not use temp files
		$xml .= $new_xml ;
	}
}

# Returns the next article XML, or false
function xml_shift ( &$xml ) {
	if ( !is_array ( $xml ) ) { # Do not use temp files
		if ( $xml != '' ) {
			$x = $xml ;
			$xml = array () ;
			return $x ;
		}
		return false ;
	} else { # Use temp files
		if ( count ( $xml ) == 0 ) return false ;
		$x = array_shift ( $xml ) ;
		$ret = file_get_contents ( $x ) ;
		unlink ( $x ) ;
		return $ret ;
	}
}

# Free temporary XML files, if any
# Should not be necessary if xml_shift was used
function xml_cleanup ( &$xml ) {
	global $xmlg ;
	if ( !$xmlg["use_xml_temp_files"] ) return ; # not using temp files
	if ( !is_array ( $xml ) ) return false ;
	foreach ( $xml AS $x ) {
		unlink ( $x ) ;
	}
	$xml = array () ;
}


## MAIN PROGRAM

if ( get_param('doit',false) ) { # Process
	$wikitext = stripslashes ( get_param('text') ) ;
	
	if( !defined( 'MEDIAWIKI' ) ) { # Stand-alone
		$content_provider = new ContentProviderHTTP ;
	} else { # MediaWiki extension
		$content_provider = new ContentProviderMySQL ;
	}
	$converter = new MediaWikiConverter ;

	$xmlg["useapi"] = isset ( $_REQUEST['useapi'] ) ;
	$xmlg["book_title"] = get_param('document_title');
	$xmlg["site_base_url"] = get_param('site') ;
	$xmlg["resolvetemplates"] = get_param('use_templates','all') ;
	$xmlg['templates'] = explode ( "\n" , get_param('templates','') ) ;
	$xmlg['add_gfdl'] = get_param('add_gfdl',false) ;
	$xmlg['keep_interlanguage'] = get_param('keep_interlanguage',false) ;
	$xmlg['keep_categories'] = get_param('keep_categories',false) ;

	# the article list
	$aArticles = array () ;
	
	$t = microtime_float() ;
	$xml = "" ;
	
	$format = get_param('output_format') ;
	$whatsthis = get_param('whatsthis') ;
	
	# Catch listnamepage
	if ( $whatsthis == "listpagename" ) {
		$listpage = trim ( array_shift ( explode ( "\n" , $wikitext ) ) ) ;
		$wikitext = $content_provider->get_wiki_text ( $listpage ) ;
		$lines = explode ( "\n" , $wikitext ) ;
		$wikitext = array () ;
		foreach ( $lines AS $l ) {
			$l1 = substr ( $l , 0 , 1 ) ;
			if ( $l1 != '*' && $l1 != '#' && $l1 != ':' ) continue ;
			$l = explode ( '[[' , $l , 2 ) ;
			$l = trim ( array_shift ( explode ( ']]' , array_pop ( $l ) , 2 ) ) ) ;
			if ( $l == '' ) continue ;
			$wikitext[] = $l ;
		}
		$wikitext = implode ( "\n" , $wikitext ) ;
		$whatsthis = 'articlelist' ;
	}
	
	# QUICK HACK! NEEDS TO WORK!
	if ( $format == "odt" || $format == "odt_xml" || $format == "docbook_pdf" || $format == "docbook_html" || $format == "docbook_xml" ) {
		$xmlg["allow_xml_temp_files"] = false ;
	}

	if ( $whatsthis == "wikitext" ) {
    $content_provider->first_title = "Raw wikitext page" ;
		$wiki2xml_authors = array () ;
		$xml = $converter->article2xml ( "" , $wikitext , $xmlg ) ;
	} else {
		if ( $xmlg['allow_xml_temp_files'] ) $xmlg['use_xml_temp_files'] = true ;
		
		foreach ( explode ( "\n" , $wikitext ) AS $a ) {
			push_article( $aArticles, $a );
		}

		# set the first article name as the default title
		if ($xmlg["book_title"] == '') {
			$xmlg["book_title"] = $aArticles[0];
		}
		# as long as we have articles to convert (this might change in between!)
		while ( $a = array_shift( $aArticles ) ) {
			$wiki2xml_authors = array () ;

			# Article page|Article name
			$a = explode ( '|' , $a ) ;
			if ( count ( $a ) == 1 ) $a[] = $a[0] ;
			$title_page = trim ( array_shift ( $a ) ) ;
			$title_name = trim ( array_pop ( $a ) ) ;
			
			$wikitext = $content_provider->get_wiki_text ( $title_page ) ;
			add_authors ( $content_provider->authors ) ;
			append_to_xml ( $xml , $converter->article2xml ( $title_name , $wikitext , $xmlg, $aArticles ) ) ;
			#$xml .= $converter->article2xml ( $title_name , $wikitext , $xmlg, &$aArticles ) ;
		}
	}
	$t = microtime_float() - $t ;
	$tt = round( $t, 3 ) ;
	$lt = round( $content_provider->load_time, 3 ) ;
	$t = round( $t - $lt, 3) ;
	
	$xmlg['xml_articles_header'] = "<articles xmlns:xhtml=\" \" loadtime='{$lt} sec' rendertime='{$t} sec' totaltime='{$tt} sec'>" ;

	# Output format
	if ( $format == "xml" ) {
		header('Content-type: text/xml; charset=utf-8');
		print "<?xml version='1.0' encoding='UTF-8' ?>\n" ;
		print xml_articles_header() ;
		while ( $x = xml_shift ( $xml ) ) print $x ;
		print "</articles>" ;
	} else if ( $format == "text" ) {
		$xmlg['plaintext_markup'] = get_param('plaintext_markup',false) ;
		$xmlg['plaintext_prelink'] = get_param('plaintext_prelink',false)  ;
		$out = $converter->articles2text ( $xml , $xmlg ) ;
		$out = str_replace ( "\n" , "<br/>" , $out ) ;
		header('Content-type: text/html; charset=utf-8');
		print $out ;

	} else if ( $format == "translated_text" ) {
		$xmlg['plaintext_markup'] = false ;
		$xmlg['plaintext_prelink'] = false ;
		$out = $converter->articles2text ( $xml , $xmlg ) ;
		#$out = str_replace ( "\n" , "<br/>" , $out ) ;
		#header('Content-type: text/html; charset=utf-8');
		#print $out ;
		$out = explode ( "\n" , $out ) ;
		array_shift ( $out ) ;
		$out = trim ( implode ( "\n" , $out ) ) ;
		$source_language = array_shift ( explode ( '.' , $xmlg["site_base_url"] ) ) ;
		$target_language = get_param ( 'translated_text_target_language' , 'en' ) ;
		$langpair = urlencode ( "{$source_language}|{$target_language}" ) ;
		$url = "http://www.google.com/translate_t?langpair={$langpair}&text=" . urlencode ( utf8_decode ( $out ) ) ;
		echo file_get_contents ( $url ) ;

	} else if ( $format == "xhtml" ) {
		$xmlg['xhtml_justify'] = get_param ( 'xhtml_justify' , false ) ;
		$xmlg['xhtml_logical_markup'] = get_param ( 'xhtml_logical_markup' , false ) ;
		$xmlg['xhtml_source'] = get_param ( 'xhtml_source' , false ) ;

		if ( $xmlg['xhtml_source'] ) {
			header('Content-type: text/xml; charset=utf-8');
			#header('Content-type: text/html; charset=utf-8');
			$s = $converter->articles2xhtml ( $xml , $xmlg ) ;
			$s = str_replace ( '>' , ">\n" , $s ) ;
			$s = str_replace ( '<' , "\n<" , $s ) ;
			$s = str_replace ( "\n\n" , "\n" , $s ) ;
			echo trim ( $s ) ;
			#echo str_replace ( "\n" , '<br/>' , htmlentities ( trim ( $s ) ) ) ;
		} else {
			# Header hack for IE
			if ( stristr($_SERVER["HTTP_ACCEPT"],"application/xhtml+xml") ) {
			  header("Content-type: application/xhtml+xml");
			} else {
			  header("Content-type: text/html");
			}
			echo $converter->articles2xhtml ( $xml , $xmlg ) ;
		}

	} else if ( $format == "odt" || $format == "odt_xml" ) {
		if ( isset ( $_REQUEST['odt_footnote'] ) ) $xmlg["odt_footnote"] = 'endnote' ;
		if ( $xmlg['sourcedir'] == '.' ) $cwd = getcwd() ;
		else $cwd = $xmlg['sourcedir'] ;
		$template_file = $cwd . '/template.odt' ;

		$dir_file = tempnam($xmlg["temp_dir"], "ODD");
		$dir = $dir_file . "-DIR" ;
		$xmlg['image_destination'] = $dir . "/Pictures" ;

		$zipdir = $cwd ;
		if ( isset ( $xmlg["zip_odt_path"] ) ) # Windows strange bug workaround
			$zipdir = $xmlg["zip_odt_path"] ;
		
		chdir ( $zipdir ) ;

		# Unzip template
		$cmd = $xmlg['unzip_odt'] ;
		$cmd = str_replace ( '$1' , escapeshellarg ( $template_file ) , $cmd ) ;
		$cmd = str_replace ( '$2' , escapeshellarg ( $dir ) , $cmd ) ;
		exec ( $cmd ) ;

		# Convert XML to ODT
		chdir ( $cwd ) ;
		if ( $format == "odt_xml" ) $content_provider->block_file_download = true ;
		$out = $converter->articles2odt ( $xml , $xmlg ) ;
		chdir ( $zipdir ) ;

		# Create ODT structure
		$handle = fopen ( $dir . "/content.xml" , "w" ) ;
		if ($handle) {
			fwrite ( $handle , $out ) ;
			fclose ( $handle ) ;
			# Generate temporary ODT file
			$out_file = tempnam('', "ODT");
			$cmd = $xmlg['zip_odt'] ;
			$cmd = str_replace ( '$1' , escapeshellarg ( $out_file ) , $cmd ) ;
			
			if ( $xmlg['is_windows'] ) {
				$cmd = str_replace ( '$2' , escapeshellarg ( $dir . "/" ) , $cmd ) ;
			} else {
				$cmd = str_replace ( '$2' , escapeshellarg ( './' ) , $cmd ) ;
				# linux/unix zip needs to be in the directory, otherwise it will
				# include needless parts into the directory structure
				chdir ($dir);
				# remove the output if it for some reason already exists
			}
			
			@unlink ( $out_file ) ;
			exec ( $cmd ) ;
		
			if ( $format == "odt" ) { # Return ODT file
				$filename = $xmlg["book_title"] ;
				if (!preg_match('/\.[a-zA-Z]{3}$/',$filename)) { $filename .= '.odt'; }
				if (!preg_match('/\.[a-zA-Z]{3}$/',$out_file)) { $out_file .= '.zip'; }
				header('Content-type: application/vnd.oasis.opendocument.text; charset=utf-8');
				header('Content-Disposition: inline; filename="'.$filename.'"');
				# XXX TODO: error handling here
				$handle = fopen($out_file, 'rb');
				fpassthru ( $handle ) ;
				fclose ( $handle ) ;
			} else { # Return XML
				header('Content-type: text/xml; charset=utf-8');
				print str_replace ( ">" , ">\n" , $out ) ;
			}

			# Cleanup
			SureRemoveDir ( $dir ) ;
			@rmdir ( $dir ) ;
			@unlink ( $dir_file ) ;
			@unlink ( $out_file ) ;
			chdir ( $cwd ) ;
		}	# error occured

	} else if ( $format == "docbook_xml" ) {
		$out = $converter->articles2docbook_xml ( $xml , $xmlg ) ;
		header('Content-type: text/xml; charset=utf-8');
		print $out ;
	} else if ( $format == "docbook_pdf" || $format == "docbook_html" ) {
		$filetype = substr ( $format , 8 ) ;
		$filename = $converter->articles2docbook_pdf ( $xml , $xmlg , strtoupper ( $filetype ) ) ;
		
		if ( file_exists ( $filename ) ) {
			$fp = fopen($filename, 'rb');
			if ( $format == "docbook_pdf" ) {
				header('Content-Type: application/pdf');
				header("Content-Length: " . (string) filesize($filename));
				header('Content-Disposition: attachment; filename="'.$xmlg["book_title"].'.pdf"');
			} else if ( $format == "docbook_html" ) {
				header('Content-Type: text/html');
				header("Content-Length: " . (string) filesize($filename));
				header('Content-Disposition: inline; filename="'.$xmlg["book_title"].'.html"');
			}
			fpassthru($fp);
			fclose ( $fp ) ;
		}
		
		# Cleanup
		$pdf_dir = dirname ( dirname ( $filename ) ) ;
		SureRemoveDir ( $pdf_dir ) ;
		@rmdir ( $pdf_dir ) ;
	}
	xml_cleanup ( $xml ) ;
	exit ;
} else { # Show the form
	if( !defined( 'MEDIAWIKI' ) ) { # Stand-alone
		header('Content-type: text/html; charset=utf-8');
		print "
<html><head></head><body>
<h1>Magnus' magic MediaWiki-to-XML-to-stuff converter</h1>
<p>All written in PHP - so portable, <s>so incredibly slow...</s> <i>about as fast as the original MediaWiki parser!</i> <small>(For the source, see <a href='http://www.mediawiki.org/wiki/Subversion'>here</a>, trunk 'wiki2xml', directory 'php')</small></p>" ;
		$xmlg["useapi"] = 1 ;
		print get_form () ;
		print "</body></html>" ;
	} else { # MediaWiki extension
		$out = get_form ( true ) ;
	}
	
}

#<input type='checkbox' name='resolvetemplates' value='1' checked>Automatically resolve templates</input><br/>

?>
