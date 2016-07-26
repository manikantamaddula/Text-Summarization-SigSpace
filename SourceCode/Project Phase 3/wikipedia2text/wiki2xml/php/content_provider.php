<?php

# Abstract base class
class ContentProvider {
	var $load_time = 0 ; # Time to load text and templates, to judge actual parsing speed
	var $article_list = array () ;
	var $authors = array () ;
	var $block_file_download = false ;
	
	function get_wiki_text ( $title , $do_cache = false ) { return "" ; } # dummy
	function get_template_text ( $title ) { return "" ; } # dummy
	
	function add_article ( $title ) {
		$this->article_list[] = urlencode ( trim ( $title ) ) ;
	}
	
	function is_an_article ( $title ) {
		$title = urlencode ( trim ( $title ) ) ;
		return in_array ( $title , $this->article_list ) ;
	}

 	/**
	 * XXX TODO: why are some negative?
 	 * Gets the numeric namespace
	 * "6"  = images
 	 * "-8" = category link
 	 * "-9" = interlanguage link
	 * "11" = templates
 	 */	function get_namespace_id ( $text ) {
		$text = strtoupper ( $text ) ;
		$text = explode ( ":" , $text , 2 ) ;
		if ( count ( $text ) != 2 ) return 0 ;
		$text = trim ( array_shift ( $text ) ) ;
		if ( $text == "" ) return 0 ;		
		$ns = 0 ;
		
		if ( $text == "CATEGORY" || $text == "KATEGORIE" ) return -8 ; # Hackish, for category link
		if ( strlen ( $text ) < 4 ) return -9 ; # Hackish, for interlanguage link
		if ( $text == "SIMPLE" ) return -9 ;
		
		# Horrible manual hack, for now
		if ( $text == "IMAGE" || $text == "BILD" ) $ns = 6 ;
		if ( $text == "TEMPLATE" || $text == "VORLAGE" ) $ns = 11 ;
		
		return $ns ;
	}

	function copyimagefromwiki ( $name , $url = "" ) {
		global $xmlg ;
		$dir = $xmlg['image_destination'] ;
		if ( $url == "" )
			$url = $this->get_image_url ( name ) ;
		$fname = urlencode ( $name ) ;
		$target = $dir . "/" . $fname ;
		if ( !file_exists ( $target ) && !$this->block_file_download ) {
			@mkdir ( $dir ) ;
			# dub sez... use cURL
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $url);
			$fh = @fopen($target, 'w');
			curl_setopt($ch, CURLOPT_FILE, $fh);
			curl_exec($ch);
			curl_close($ch);
			@fclose($fh);
		}
		return $fname ;
	}

	function myurlencode ( $t ) {
		$t = str_replace ( " " , "_" , $t ) ;
		$t = urlencode ( $t ) ;
		return $t ;
	}
	

	function get_image_url ( $name ) {
		global $xmlg ;
		$site = $xmlg['site_base_url'] ;
		$parts = explode ( ".wikipedia.org/" , $site ) ;
		$parts2 = explode ( ".wikibooks.org/" , $site ) ;

		$image = utf8_encode ( $name ) ;
		$image2 = ucfirst ( str_replace ( " " , "_" , $name ) ) ;
		$m = md5( $image2 ) ;
		$m1 = substr ( $m , 0 , 1 ) ;
		$m2 = substr ( $m , 0 , 2 ) ;
		$i = "{$m1}/{$m2}/" . $this->myurlencode ( ucfirst ( $name ) ) ;


		if ( count ($parts ) > 1 ) {
			$lang = array_shift ( $parts ) ;
			$url = "http://upload.wikimedia.org/wikipedia/{$lang}/{$i}" ;
			$url2 = "http://upload.wikimedia.org/wikipedia/commons/{$i}" ;
			$h = @fopen ( $url , "r" ) ;
			if ( $h === false ) $url = $url2 ;
			else fclose ( $h ) ;
		} else if ( count ($parts2 ) > 1 ) {
			$lang = array_shift ( $parts2 ) ;
			$url = "http://upload.wikimedia.org/wikibooks/{$lang}/{$i}" ;
			$url2 = "http://upload.wikimedia.org/wikipedia/commons/{$i}" ;
			$h = @fopen ( $url , "r" ) ;
			if ( $h === false ) $url = $url2 ;
			else fclose ( $h ) ;
		} else {
			$url = "http://{$site}/images/{$i}" ;
		}
#		print "<a href='{$url}'>{$url}</a><br/>" ;
		return $url ;
	}
	
	function do_show_images () {
		return true ;
	}

}


# Access through HTTP protocol
class ContentProviderHTTP extends ContentProvider {
	var $article_cache = array () ;
	var $first_title = "" ;
	var $load_error ;
	
	function between_tag ( $tag , &$text ) {
		$a = explode ( "<{$tag}" , $text , 2 ) ;
		if ( count ( $a ) == 1 ) return "" ;
		$a = explode ( ">" , " " . array_pop ( $a ) , 2 ) ;
		if ( count ( $a ) == 1 ) return "" ;
		$a = explode ( "</{$tag}>" , array_pop ( $a ) , 2 ) ;
		if ( count ( $a ) == 1 ) return "" ;
		return array_shift ( $a ) ;
	}
	
	function do_get_contents ( $title ) {
		global $xmlg ;
		$use_se = false ;
		if ( isset ( $xmlg["use_special_export"] ) && $xmlg["use_special_export"] == 1 ) $use_se = true ;
		
		if ( $xmlg["useapi"] ) {
			$url = "http://" . $xmlg["site_base_url"] . "/api.php?format=php&action=query&prop=revisions&rvexpandtemplates=1&rvprop=timestamp|user|comment|content&titles=" . urlencode ( $title ) ;
			$data = @file_get_contents ( $url ) ;
			$data = unserialize ( $data ) ;
			$data = $data['query'] ; if ( !isset ( $data ) ) return "" ;
			$data = $data['pages'] ; if ( !isset ( $data ) ) return "" ;
			$data = array_shift ( $data ) ;
			$data = $data['revisions'] ; if ( !isset ( $data ) ) return "" ;
			$data = $data['0'] ; if ( !isset ( $data ) ) return "" ;
			$data = $data['*'] ; if ( !isset ( $data ) ) return "" ;
			return $data ;
#			$data = $data['page'] ; if ( !isset ( $data ) ) return "" ;
#			$data = $data['revision'] ; if ( !isset ( $data ) ) return "" ;
#			$data = $data['ref'] ; if ( !isset ( $data ) ) return "" ;
#print urldecode ( $url ) . "\n" ;
			print "<pre>" ; print_r ( $data ) ; print "</pre>" ; 
			exit ;
			$s = "Still here..." ;
			return $s ;
		} else if ( $use_se ) {
			$url = "http://" . $xmlg["site_base_url"] . "/index.php?listauthors=1&title=Special:Export/" . urlencode ( $title ) ;
		} else {
			if ( $xmlg["use_toolserver_url"] ) {
#				$url = "http://" . $xmlg["site_base_url"] . "/index.php?action=raw&title=" . urlencode ( $title ) ;
				$u = urlencode ( $title ) ;
				$site = array_shift ( explode ( "/" , $xmlg["site_base_url"] ) ) ;
				$url = "http://tools.wikimedia.de/~daniel/WikiSense/WikiProxy.php?wiki={$site}&title={$u}&rev=0&go=Fetch" ;
			} else {
				$url = "http://" . $xmlg["site_base_url"] . "/index.php?action=raw&title=" . urlencode ( $title ) ;
			}
		}
		$s = @file_get_contents ( $url ) ;

		if ( $use_se ) {
			$text = html_entity_decode ( $this->between_tag ( "text" , $s ) ) ;
			$this->authors = array () ;
			$authors = $this->between_tag ( "contributors" , $s ) ;
			$authors = explode ( "</contributor><contributor>" , $authors ) ;
			foreach ( $authors AS $author ) {
				$id = $this->between_tag ( "id" , $author ) ;
				if ( $id == '0' || $id == '' ) continue ; # Skipping IPs and (possibly) broken entries
				$name = $this->between_tag ( "username" , $author ) ;
				$this->authors[] = $name ;
			}
			$s = $text ;
		}
		return $s ;
	}
	
	function get_wiki_text ( $title , $do_cache = false ) {
		global $xmlg ;
		$load_error = false ;
		$title = trim ( $title ) ;
		if ( $title == "" ) return "" ; # Just in case...
		if ( isset ( $this->article_cache[$title] ) ) # Already in the cache
			return $this->article_cache[$title] ;
		
		if ( $this->first_title == "" ) $this->first_title = $title ;
		
		# Retrieve it
		$t1 = microtime_float() ;
		$s = $this->do_get_contents ( $title ) ;
		if ( strtoupper ( substr ( $s , 0 , 9 ) ) == "#REDIRECT" ) {
			$t2 = explode ( "[[" , $s , 2 ) ;
			$t2 = array_pop ( $t2 ) ;
			$t2 = explode ( "]]" , $t2 , 2 ) ;
			$t2 = array_shift ( $t2 ) ;
			$s = $this->do_get_contents ( $t2 ) ;
		}
		$this->load_time += microtime_float() - $t1 ;
		
		$comp = '<!DOCTYPE html PUBLIC "-//W3C//DTD' ;
		if ( substr ( $s , 0 , strlen ( $comp ) ) == $comp ) $s = "" ; # Catching wrong title error
		
		if ( $do_cache ) $this->article_cache[$title] = $s ;
		return $s ;
	}
	
	function get_local_url ( $title ) {
		return "/" . array_pop ( explode ( "/" , $this->get_var ( 'site_base_url' ) , 2 ) ) . "/index.php?title=" . urlencode ( $title ) ;
	}
	
	function get_server_url () {
		return "http://" . array_shift ( explode ( "/" , $this->get_var ( 'site_base_url' ) , 2 ) ) ;
	}
	
	function get_full_url ( $title ) {
		return $this->get_server_url () . $this->get_local_url ( $title ) ;
	}
	
	function get_namespace_template () {
		return $this->get_var ( 'namespace_template' ) ;
	}
	
	function get_var ( $var ) {
		global $xmlg ;
		if ( !isset ( $xmlg[$var] ) ) return false ;
		return $xmlg[$var] ;
	}
	
	function get_template_text ( $title ) {
		# Check for fix variables
		if ( $title == "PAGENAME" ) return $this->first_title ;
		if ( $title == "PAGENAMEE" ) return urlencode ( $this->first_title ) ;
		if ( $title == "SERVER" ) return $this->get_server_url () ;
		if ( $title == "CURRENTDAYNAME" ) return date ( "l" ) ;
		if ( strtolower ( substr ( $title , 0 , 9 ) ) == "localurl:" )
			return $this->get_local_url ( substr ( $title , 9 ) ) ;
		
		$title = trim ( $title ) ;
		if ( count ( explode ( ":" , $title , 2 ) ) == 1 ) # Does the template title contain a ":"?
			$title =  $this->get_namespace_template() . ":" . $title ;
		else if ( substr ( $title , 0 , 1 ) == ":" ) # Main namespace
			$title = substr ( $title , 1 ) ;
		return $this->get_wiki_text ( $title , true ) ; # Cache template texts
	}
	
	function get_internal_link ( $target , $text ) {
		return $text ; # Dummy
	}
}




# Access through text file structure
class ContentProviderTextFile extends ContentProviderHTTP {
	var $file_ending = ".txt" ;

	function do_get_contents ( $title ) {
		return $this->get_page_text ( $title ) ;
	}

	/**
	 Called from outside
	 Could probably remained unchanged from HTTP class, but this is shorter, and caching is irrelevant for text files (disk cache)
	 */
	function get_wiki_text ( $title , $do_cache = false ) {
		$title = trim ( $title ) ;
		if ( $title == "" ) return "" ; # Just in case...
		if ( $this->first_title == "" ) {
			$this->first_title = $title ;
		}
		$text = $this->get_page_text ( $title ) ;
		return $text ;
	}
	
	function get_file_location ( $ns , $title ) {
		return get_file_location_global ( $this->basedir , $ns , $title , false ) ;
	}
	
	function get_page_text ( $page , $allow_redirect = true ) {
		$filename = $this->get_file_location ( 0 , $page ) ;
		$filename = $filename->fullname . $this->file_ending ;
		if ( !file_exists ( $filename ) ) return "" ;
		$text = trim ( file_get_contents ( $filename ) ) ;
	
		# REDIRECT?
		if ( $allow_redirect && strtoupper ( substr ( $text , 0 , 9 ) ) == "#REDIRECT" ) {
			$text = substr ( $text , 9 ) ;
			$text = array_shift ( explode ( "\n" , $text , 2 ) ) ;
			$text = str_replace ( "[[" , "" , $text ) ;
			$text = str_replace ( "]]" , "" , $text ) ;
			$text = ucfirst ( trim ( $text ) ) ;
			$text = $this->get_page_text ( $text , false ) ;
		}
		return $text ;
	}

	function get_internal_link ( $target , $text ) {
		$file = $this->get_file_location ( 0 , $target ) ;
		if ( !file_exists ( $file->fullname.$this->file_ending ) ) return $text ;
		else return "<a href='browse_texts.php?title=" . urlencode ( $target ) . "'>{$text}</a>" ;
	}
	
	function do_show_images () {
		return false ;
	}

}

# Access through MySQL interface
# (Used via the extension via Special::wiki2XML)
class ContentProviderMySQL extends ContentProviderHTTP {

	function do_get_contents ( $title ) {
		return $this->get_page_text ( $title ) ;
	}

	/**
	 Called from outside
	 */
	function get_wiki_text ( $title , $do_cache = false ) {
		$title = trim ( $title ) ;
		if ( $title == "" ) return "" ; # Just in case...
		if ( $this->first_title == "" ) {
			$this->first_title = $title ;
		}
		$text = $this->get_page_text ( $title ) ;
		return $text ;
	}
	
	function get_file_location ( $ns , $title ) {
		return get_file_location_global ( $this->basedir , $ns , $title , false ) ;
	}
	
	function get_page_text ( $page , $allow_redirect = true ) {
		$title = Title::newFromText ( $page ) ;
		$article = new Article ( $title ) ;

		# article does not exist?
		if (!$article->exists()) {
			return "";
		}
		$text = $article->getContent () ;
	
		# REDIRECT?
		if ( $allow_redirect && strtoupper ( substr ( $text , 0 , 9 ) ) == "#REDIRECT" ) {
			$text = substr ( $text , 9 ) ;
			$text = array_shift ( explode ( "\n" , $text , 2 ) ) ;
			$text = str_replace ( "[[" , "" , $text ) ;
			$text = str_replace ( "]]" , "" , $text ) ;
			$text = ucfirst ( trim ( $text ) ) ;
			$text = $this->get_page_text ( $text , false ) ;
		}
		return $text ;
	}

	function get_internal_link ( $target , $text ) {
		$file = $this->get_file_location ( 0 , $target ) ;
		if ( !file_exists ( $file->fullname.$this->file_ending ) ) return $text ;
		else return "<a href='browse_texts.php?title=" . urlencode ( $target ) . "'>{$text}</a>" ;
	}
	
	function do_show_images () {
		return false ;
	}

}

?>
