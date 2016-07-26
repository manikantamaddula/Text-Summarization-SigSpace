<?php
# Copyright by Magnus Manske (2005)
# Released under GPL

$wiki2xml_authors = array () ;

class wiki2xml
	{
	var $cnt = 0 ; # For debugging purposes
	var $protocols = array ( "http" , "https" , "news" , "ftp" , "irc" , "mailto" ) ;
	var $errormessage = "ERROR!" ;
	var $compensate_markup_errors = true;
	var $auto_fill_templates = 'all' ; # Will try and replace templates right inline, instead of using <template> tags; requires global $content_provider
	var $use_space_tag = true ; # Use <space/> instead of spaces before and after tags
	var $allowed = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ01234567890 +-#:;.,%="\'\\' ;
	var $directhtmltags = array (
		"b" => "xhtml:b",
		"i" => "xhtml:i",
		"u" => "xhtml:u",
		"s" => "xhtml:s",
		"p" => "xhtml:p",
		"br" => "xhtml:br",
		"em" => "xhtml:em",
		"div" => "xhtml:div",
		"span" => "xhtml:span",
		"big" => "xhtml:big",
		"small" => "xhtml:small",
		"sub" => "xhtml:sub",
		"sup" => "xhtml:sup",
		"font" => "xhtml:font",
		"center" => "xhtml:center",
		"table" => "xhtml:table",
		"tr" => "xhtml:tr",
		"th" => "xhtml:th",
		"td" => "xhtml:td",
		"pre" => "xhtml:pre",
		"code" => "xhtml:code",
		"caption" => "xhtml:caption",
		"cite" => "xhtml:cite",
		"ul" => "xhtml:ul",
		"ol" => "xhtml:ol",
		"li" => "xhtml:li",
		"tt" => "xhtml:tt",
		"h1" => "xhtml:h1",
		"h2" => "xhtml:h2",
		"h3" => "xhtml:h3",
		"h4" => "xhtml:h4",
		"h5" => "xhtml:h5",
		"h6" => "xhtml:h6",
		"h7" => "xhtml:h7",
		"h8" => "xhtml:h8",
		"h9" => "xhtml:h9",
		) ;
		
	var $w ; # The wiki text
	var $wl ; # The wiki text length
	var $bold_italics ;
	var $tables = array () ; # List of open tables
	var $profile = array () ;

	# Some often used functions
	
	/**
	 * Inherit settings from an existing parser
	*/
	function inherit ( &$base )
		{
			$this->protocols = $base->protocols ;
			$this->errormessage = $base->errormessage ;
			$this->compensate_markup_errors = $base->compensate_markup_errors ;
			$this->auto_fill_templates = $base->auto_fill_templates ;
			$this->use_space_tag = $base->use_space_tag ;
			$this->compensate_markup_errors = $base->compensate_markup_errors ;
			$this->allowed = $base->allowed ;
			$this->directhtmltags = $base->directhtmltags ;
		}
	
	/**
	 * Matches a function to the current text (default:once)
	*/
	function once ( &$a , &$xml , $f , $atleastonce = true , $many = false )
		{
		$f = "p_{$f}" ;
		$cnt = 0 ;
#		print $f . " : " . htmlentities ( substr ( $this->w , $a , 20 ) ) . "<br/>" ; flush () ;
#		if ( !isset ( $this->profile[$f] ) ) $this->profile[$f] = 0 ; # PROFILING
		do {
#			$this->profile[$f]++ ; # PROFILING
			$matched = $this->$f ( $a , $xml ) ;
			if ( $matched && $many ) $again = true ;
			else $again = false ;
			if ( $matched ) $cnt++ ;
			} while ( $again ) ;
		if ( !$atleastonce ) return true ;
		if ( $cnt > 0 ) return true ;
		return false ;
		}

	function onceormore ( &$a , &$xml , $f )
		{
		return $this->once ( $a , $xml , $f , true , true ) ;
		}

	function nextis ( &$a , $t , $movecounter = true )
		{
		if ( substr ( $this->w , $a , strlen ( $t ) ) != $t ) return false ;
		if ( $movecounter ) $a += strlen ( $t ) ;
		return true ;
		}

	function nextchar ( &$a , &$x )
		{
		if ( $a >= $this->wl ) return false ;
		$x .= htmlspecialchars ( $this->w[$a] ) ;
		$a++ ;
		return true ;
		}

	function ischaracter ( $c )
		{
		if ( $c >= 'A' && $c <= 'Z' ) return true ;
		if ( $c >= 'a' && $c <= 'z' ) return true ;
		return false ;
		}
		
	function skipblanks ( &$a , $blank = " " )
		{
		while ( $a < $this->wl )
			{
			if ( $this->w[$a] != $blank ) return ;
			$a++ ;
			}
		}
		
	##############

	
	function p_internal_link_target ( &$a , &$xml , $closeit = "]]" )
		{
		return $this->p_internal_link_text ( $a , $xml , true , $closeit ) ;
		}
		
	function p_internal_link_text2 ( &$a , &$xml , $closeit )
		{
		$bi = $this->bold_italics ;
		$ret = $this->p_internal_link_text ( $a , $xml , false , $closeit , false ) ;
		if ( $closeit == ']]' && '' != $this->bold_italics ) $ret = false ; # Dirty hack to ensure good XML; FIXME!!!
		return $ret ;
		}
	
	function p_internal_link_text ( &$a , &$xml , $istarget = false , $closeit = "]]" , $mark = true )
		{
		$b = $a ;
		$x = "" ;
		if ( $b >= $this->wl ) return false ;
		$bi = $this->bold_italics ;
		$this->bold_italics = '' ;
		$closeit1 = $closeit[0] ;
		while ( 1 )
			{
			if ( $b >= $this->wl ) {
				$this->bold_italics = $bi ;
				return false ;
			}
			$c = $this->w[$b] ;
			if ( $closeit != "}}" && $c == "\n" ) {
				$this->bold_italics = $bi ;
				return false ;
			}
			if ( $c == "|" ) break ;
			if ( $c == $closeit1 && $this->nextis ( $b , $closeit , false ) ) break ;
			if ( !$istarget ) {
				if ( $c == "[" && $this->once ( $b , $x , "internal_link" ) ) continue ;
				if ( $c == "[" && $this->once ( $b , $x , "external_link" ) ) continue ;
				if ( $c == "{" && $this->once ( $b , $x , "template_variable" ) ) continue ;
				if ( $c == "{" && $this->once ( $b , $x , "template" ) ) continue ;
				if ( $c == "<" && $this->once ( $b , $x , "html" ) ) continue ;
				if ( $c == "'" && $this->p_bold ( $b , $x , "internal_link_text2" , $closeit ) ) { break ; }
				if ( $c == "'" && $this->p_italics ( $b , $x , "internal_link_text2" , $closeit ) ) { break ; }
				if ( $b + 10 < $this->wl && 
						( $this->w[$a+5] == '/' && $this->w[$a+7] == '/' ) &&
						$this->once ( $b , $x , "external_freelink" ) ) continue ;
			} else {
				if ( $c == "{" && $this->once ( $b , $x , "template" ) ) continue ;
			}
			$x .= htmlspecialchars ( $c ) ;
			$b++ ;
/*			if ( $b >= $this->wl ) {
				$this->bold_italics = $bi ;
				return false ;
			}*/
		}
		
		if ( $closeit == "}}" && !$istarget ) {
			$xml .= substr ( $this->w , $a , $b - $a ) ;
			$a = $b ;
			$this->bold_italics = $bi ;
			return true ;
		}
		
		$x = trim ( str_replace ( "\n" , "" , $x ) ) ;
		if ( $mark )
			{
			if ( $istarget ) $xml .= "<target>{$x}</target>" ;
			else $xml .= "<part>{$x}</part>" ;
				
			}
		else $xml .= $x ;
		$a = $b ;
		$this->bold_italics = $bi ;
		return true ;
		}

	function p_internal_link_trail ( &$a , &$xml )
		{
		$b = $a ;
		$x = "" ;
		while ( 1 )
			{
			$c = "" ;
			
			if ( !$this->nextchar ( $b , $c ) ) break ;
			
			if ( $this->ischaracter ( $c ) )
				{
				$x .= $c ;
				}
			else
				{
				$b-- ;
				break ;
				}
			}
		if ( $x == "" ) return false ; # No link trail
		$xml .= "<trail>{$x}</trail>" ;
		$a = $b ;
		return true ;
		}
	
	function p_internal_link ( &$a , &$xml )
		{
		$x = "" ;
		$b = $a ;
		if ( !$this->nextis ( $b , "[[" ) ) return false ;
		if ( !$this->p_internal_link_target ( $b , $x , "]]" ) ) return false ;
		while ( 1 )
			{
			if ( $this->nextis ( $b , "]]" ) ) break ;
			if ( !$this->nextis ( $b , "|" ) ) return false ;
			if ( !$this->p_internal_link_text ( $b , $x , false , "]]" ) ) return false ;
			}
		$this->p_internal_link_trail ( $b , $x ) ;
		$xml .= "<link>{$x}</link>" ;
		$a = $b ;
		return true ;
		}

	function p_magic_variable ( &$a , &$xml )
		{
		$x = "" ;
		$b = $a ;
		if ( !$this->nextis ( $b , "__" ) ) return false ;
		$varname = "" ;
		for ( $c = $b ; $c < $this->wl && $this->w[$c] != '_' ; $c++ )
			$varname .= $this->w[$c] ;
		if ( !$this->nextis ( $c , "__" ) ) return false ;
		$xml .= "<magic_variable>{$varname}</magic_variable>" ;
		$a = $c ;
		return true ;
		}
		
	# Template and template variable, utilizing parts of the internal link methods
	function p_template ( &$a , &$xml )
		{
		global $content_provider , $xmlg ;
		if ( $xmlg["useapi"] ) return false ; # API already resolved templates
		
		$x = "" ;
		$b = $a ;
		if ( !$this->nextis ( $b , "{{" ) ) return false ;
#		if ( $this->nextis ( $b , "{" , false ) ) return false ; # Template names may not start with "{"
		if ( !$this->p_internal_link_target ( $b , $x , "}}" ) ) return false ;
		$target = $x ;
		$variables = array () ;
		$varnames = array () ;
		$vcount = 1 ;
		while ( 1 )
			{
			if ( $this->nextis ( $b , "}}" ) ) break ;
			if ( !$this->nextis ( $b , "|" ) ) return false ;
			$l1 = strlen ( $x ) ;
			if ( !$this->p_internal_link_text ( $b , $x , false , "}}" ) ) return false ;
			$v = substr ( $x , $l1 ) ;
			$v = explode ( "=" , $v ) ;
			if ( count ( $v ) < 2 ) $vk = $vcount ;
			else {
				$vk = trim ( array_shift ( $v ) ) ;
				$varnames[$vcount] = $vk; 
			}
			$vv = array_shift ( $v ) ;
			$variables[$vk] = $vv ;
			if ( !isset ( $variables[$vcount] ) ) $variables[$vcount] = $vv ;
			$vcount++ ;
			}
		
		$target = array_pop ( @explode ( ">" , $target , 2 ) ) ;
		$target = array_shift ( @explode ( "<" , $target , 2 ) ) ;
		if ( $this->auto_fill_templates == 'all' ) $replace_template = true ;
		else if ( $this->auto_fill_templates == 'none' ) $replace_template = false ;
		else {
			$found = in_array ( ucfirst ( $target ) , $this->template_list ) ;
			if ( $found AND $this->auto_fill_templates == 'these' ) $replace_template = true ;
			else if ( !$found AND $this->auto_fill_templates == 'notthese' ) $replace_template = true ;
			else $replace_template = false ;
		}
		
    if ( substr ( $target , 0 , 1 ) == '#' ) { # Try template logic
      $between = $this->process_template_logic ( $target , $variables ) ;
			# Change source (!)
			$w1 = substr ( $this->w , 0 , $a ) ;
			$w2 = substr ( $this->w , $b ) ;
			$this->w = $w1 . $between . $w2 ;
			$this->wl = strlen ( $this->w ) ;
    } else if ( $replace_template ) { # Do not generate <template> sections, but rather replace the template call with the template text

      # Get template text
      $between = trim ( $content_provider->get_template_text ( $target ) ) ;
      add_authors ( $content_provider->authors ) ;
			
			# Removing <noinclude> stuff
			$between = preg_replace( '?<noinclude>.*</noinclude>?msU', '', $between);
			$between = str_replace ( "<include>" , "" , $between ) ;
			$between = str_replace ( "</include>" , "" , $between ) ;
			$between = str_replace ( "<includeonly>" , "" , $between ) ;
			$between = str_replace ( "</includeonly>" , "" , $between ) ;

      # Remove HTML comments
      $between = str_replace ( "-->\n" , "-->" , $between ) ;
      $between = preg_replace( '?<!--.*-->?msU', '', $between) ;

			# Replacing template variables.
			# ATTENTION: Template variables within <nowiki> sections of templates will be replaced as well!
			
			if ( $a > 0 && substr ( $between , 0 , 2 ) == '{|' )
				$between = "\n" . $between ;
			
			$this->replace_template_variables ( $between , $variables ) ;
			
			# Change source (!)
			$w1 = substr ( $this->w , 0 , $a ) ;
			$w2 = substr ( $this->w , $b ) ;
			$this->w = $w1 . $between . $w2 ;
			$this->wl = strlen ( $this->w ) ;
		} else {
			$xml .= "<template><target>{$target}</target>";
			for ( $i = 1 ; $i < $vcount ; $i++ ) {
				if ( isset( $varnames[$i] ) ) $xml .= "<arg name=\"{$varnames[$i]}\">{$variables[$i]}</arg>";
				else $xml .= "<arg>{$variables[$i]}</arg>";
			}
			$xml .= "</template>" ;
			$a = $b ;
		}
		return true ;
		}

	function process_template_logic ( $title , $variables ) {
	
    # TODO : Process title and variables for sub-template-replacements
	
    if ( substr ( $title , 0 , 4 ) == "#if:" ) {
      $title = trim ( substr ( $title , 4 ) ) ;
      if ( $title == '' ) return array_pop ( $variables ) ; # ELSE
      return array_shift ( $variables ) ; # THEN
    }

    if ( substr ( $title , 0 , 8 ) == "#switch:" ) {
      $title = trim ( array_pop ( explode ( ':' , $title , 2 ) ) ) ;
      foreach ( $variables AS $v ) {
        $v = explode ( '=' , $v , 2 ) ;
        $key = trim ( array_shift ( $v ) ) ;
        if ( $key != $title ) continue ; # Wrong key
        return array_pop ( $v ) ; # Correct key, return value
      }
    }
    
    # BAD PARSER FUNCTION! Ignoring...
    return $title ;
	}
	
	function replace_template_variables ( &$text , &$variables ) {
		global $xmlg ;
		if ( $xmlg["useapi"] ) return false ; # API already resolved templates
		for ( $a = 0 ; $a+3 < strlen ( $text ) ; $a++ ) {
			if ( $text[$a] != '{' ) continue ;
			while ( $this->p_template_replace_single_variable ( $text , $a , $variables ) ) ;
		}
	}
	
	function p_template_replace_single_variable ( &$text , $a , &$variables ) {
		if ( substr ( $text , $a , 3 ) != '{{{' ) return false ;
		$b = $a + 3 ;

		# Name
		$start = $b ;
		$count = 0 ;
		while ( $b < strlen ( $text ) && ( $text[$b] != '|' || $count > 0 ) && ( substr ( $text , $b , 3 ) != '}}}' || $count > 0 ) ) {
			if ( $this->p_template_replace_single_variable ( $text , $b , $variables ) ) continue ;
			if ( $text[$b] == '{' ) $count++ ;
			if ( $text[$b] == '}' ) $count-- ;
			$b++ ;
		}
		if ( $b >= strlen ( $text ) ) return false ;
		$name = trim ( substr ( $text , $start , $b - $start ) ) ;
		
		# Default value
		$value = "" ;
		if ( $text[$b] == '|' ) {
			$b++ ;
			$start = $b ;
			$count = 0 ;
			while ( $b < strlen ( $text ) && ( substr ( $text , $b , 3 ) != '}}}' || $count > 0 ) ) {
				if ( $this->p_template_replace_single_variable ( $text , $b , $variables ) ) continue ;
				if ( $text[$b] == '{' ) $count++ ;
				if ( $text[$b] == '}' ) $count-- ;
				$b++ ;
			}
			if ( $b >= strlen ( $text ) ) return false ;
			$value = trim ( substr ( $text , $start , $b - $start ) ) ;#$start - $b - 1 ) ) ;
		}
		
		// Replace
		$b += 3 ; # }}}
		if ( isset ( $variables[$name] ) ) {
			$value = $variables[$name] ;
		}
		$text = substr ( $text , 0 , $a ) . $value . substr ( $text , $b ) ;
		
		return true ;
	}
		
	function p_template_variable ( &$a , &$xml )
		{
		$x = "" ;
		$b = $a ;
		if ( !$this->nextis ( $b , "{{{" ) ) return false ;
		if ( !$this->p_internal_link_text ( $b , $x , false , "}}}" ) ) return false ;
		if ( !$this->nextis ( $b , "}}}" ) ) return false ;
		$xml .= "<templatevar>{$x}</templatevar>" ;
		$a = $b ;
		return true ;
		}
		
	# Bold / italics
	function p_bold ( &$a , &$xml , $recurse = "restofline" , $end = "" )
		{
		return $this->p_intwined ( $a , $xml , "bold" , "'''" , $recurse , $end ) ;
		}
	
	function p_italics ( &$a , &$xml , $recurse = "restofline" , $end = "" )
		{
		return $this->p_intwined ( $a , $xml , "italics" , "''" , $recurse , $end ) ;
		}
	
	function p_intwined ( &$a , &$xml , $tag , $markup , $recurse , $end )
		{
		$b = $a ;
		if ( !$this->nextis ( $b , $markup ) ) return false ;
		$id = substr ( ucfirst ( $tag ) , 0 , 1 ) ;
		$bi = $this->bold_italics ;
		$open = false ;
		if ( substr ( $this->bold_italics , -1 ) == $id )
			{
			$x = "</{$tag}>" ;
			$this->bold_italics = substr ( $this->bold_italics , 0 , -1 ) ;
			}
		else
			{
			$pos = strpos ( $this->bold_italics , $id ) ;
			if ( false !== $pos ) return false ; # Can't close a tag that ain't open
			$open = true ;
			$x = "<{$tag}>" ;
			$this->bold_italics .= $id ;
			}
		
		if ( $end == "" )
			{
			$res = $this->once ( $b , $x , $recurse ) ;
			}
		else
			{
			$r = "p_{$recurse}" ;
			$res = $this->$r ( $b , $x , $end ) ;
			}
		
		$this->bold_italics = $bi ;
		if ( !$res )
			{
			return false ;
			}
		$xml .= $x ;
		$a = $b ;
		return true ;
		}	
		
	function scanplaintext ( &$a , &$xml , $goodstop , $badstop )
		{
		$b = $a ;
		$x = "" ;
		while ( $b < $this->wl )
			{
			if ( $this->w[$b] == "{" && $this->once ( $b , $x , "template" ) ) continue ;
			foreach ( $goodstop AS $s )
				if ( $this->nextis ( $b , $s , false ) ) break 2 ;
			foreach ( $badstop AS $s )
				if ( $this->nextis ( $b , $s , false ) ) return false ;
			$c = $this->w[$b] ;
			$x .= htmlspecialchars ( $c ) ;
			$b++ ;
			}
		if ( count ( $goodstop ) > 0 && $b >= $this->wl ) return false ; # Reached end; not good
		$a = $b ;
		$xml .= $x ;
		return true ;
		}
		
	# External link
	function p_external_freelink ( &$a , &$xml , $mark = true )
		{
		if ( $this->wl <= $a + 10 ) return false ; # Can't have an URL shorter than that
		if ( $this->w[$a+5] != '/' && $this->w[$a+7] != '/' ) return false ; # Shortcut for protocols 3-6 chars length
		$protocol = "" ;
		$b = $a ;
#		while ( $this->w[$b] == "{" && $this->once ( $b , $x , "template" ) ) $b = $a ;
		foreach ( $this->protocols AS $p )
			{
			if ( $this->nextis ( $b , $p . "://" ) )
				{
				$protocol = $p ;
				break ;
				}
			}
		if ( $protocol == "" ) return false ;
		$x = "{$protocol}://" ;
		while ( $b < $this->wl )
			{
			$c = $this->w[$b] ;
			if ( $c == "{" && $this->once ( $b , $x , "template" ) ) continue ;
			if ( $c == "\n" || $c == " " || $c == '|' ) break ;
			if ( !$mark && $c == "]" ) break ;
			$x .= htmlspecialchars ( $c ) ;
			$b++ ;
			}
		if ( substr ( $x , -1 ) == "." || substr ( $x , -1 ) == "," )
			{
			$x = substr ( $x , 0 , -1 ) ;
			$b-- ;
			}
		$a = $b ;
		$x = htmlspecialchars ( $x , ENT_QUOTES ) ;
		if ( $mark ) $xml .= "<link type='external' href='$x'/>" ;
		else $xml .= $x ;
		return true ;
		}

	function p_external_link ( &$a , &$xml , $mark = true )
		{
		$b = $a ;
		if ( !$this->nextis ( $b , "[" ) ) return false ;
		$url = "" ;
		$c = $b ;
		$x = "" ;
		while ( $c < $this->wl && $this->w[$c] == "{" && $this->once ( $c , $x , "template" ) ) $c = $b ;
		if ( $c >= $this->wl ) return false ;
		$x = "" ;
		if ( !$this->p_external_freelink ( $b , $url , false ) ) return false ;
		$this->skipblanks ( $b ) ;
		if ( !$this->scanplaintext ( $b , $x , array ( "]" ) , array ( "\n" ) ) ) return false ;
		$a = $b + 1 ;
		$xml .= "<link type='external' href='{$url}'>{$x}</link>" ;
		return true ;
		}
		
	# Heading
	function p_heading ( &$a , &$xml )
		{
		if ( $a >= $this->wl || $this->w[$a] != '=' ) return false ;
		$b = $a ;
		$level = 0 ;
		$h = "" ;
		$x = "" ;
		while ( $this->nextis ( $b , "=" ) )
			{
			$level++ ;
			$h .= "=" ;
			}
		$this->skipblanks ( $b ) ;
		if ( !$this->once ( $b , $x , "restofline" ) ) return false ;
		if ( $this->compensate_markup_errors ) $x = trim ( $x ) ;
		else if ( $x != trim ( $x ) ) $xml .= "<error type='heading' reason='trailing blank'/>" ;
		if ( substr ( $x , -$level ) != $h ) return false ; # No match

		$x = trim ( substr ( $x , 0 , -$level ) ) ;
		$level -= 1 ;
		$a = $b ;
		$xml .= "<heading level='" . ($level+1) . "'>{$x}</heading>" ;
		return true ;
		}
	
	# Line
	# Often used function for parsing the rest of a text line
	function p_restofline ( &$a , &$xml , $closeit = array() )
		{
		$b = $a ;
		$x = "" ;
		$override = false ;
		while ( $b < $this->wl && !$override )
			{
			$c = $this->w[$b] ;
			if ( $c == "\n" ) { $b++ ; break ; }
			foreach ( $closeit AS $z )
				if ( $this->nextis ( $b , $z , false ) ) break ;
			if ( $c == "_" && $this->once ( $b , $x , "magic_variable" ) ) continue ;
			if ( $c == "[" && $this->once ( $b , $x , "internal_link" ) ) continue ;
			if ( $c == "[" && $this->once ( $b , $x , "external_link" ) ) continue ;
			if ( $c == "{" && $this->once ( $b , $x , "template_variable" ) ) continue ;
			if ( $c == "{" && $this->once ( $b , $x , "template" ) ) continue ;
			if ( $c == "{" && $this->p_table ( $b , $x ) ) continue ;
			if ( $c == "<" && $this->once ( $b , $x , "html" ) ) continue ;
			if ( $c == "'" && $this->once ( $b , $x , "bold" ) ) { $override = true ; break ; }
			if ( $c == "'" && $this->once ( $b , $x , "italics" ) ) { $override = true ; break ; }
			if ( $b + 10 < $this->wl && 
					( $this->w[$a+5] == '/' && $this->w[$a+7] == '/' ) &&
					$this->once ( $b , $x , "external_freelink" ) ) continue ;
			
			# Just an ordinary character
			$x .= htmlspecialchars ( $c ) ;
			$b++ ;
			if ( $b >= $this->wl ) break ;
			}
		if ( !$override && $this->bold_italics != "" )
			{
			return false ;
			}
		$xml .= $x ;
		$a = $b ;
		return true ;
		}
	
	function p_line ( &$a , &$xml , $force )
		{
		if ( $a >= $this->wl ) return false ; # Already at the end of the text
		$c = $this->w[$a] ;
		if ( !$force )
			{
			if ( $c == '*' || $c == ':' || $c == '#' || $c == ';' || $c == ' ' || $c == "\n" ) return false ; # Not a suitable beginning
			if ( $this->nextis ( $a , "{|" , false ) ) return false ; # Table
			if ( count ( $this->tables ) > 0 && $this->nextis ( $a , "|" , false ) ) return false ; # Table
			if ( count ( $this->tables ) > 0 && $this->nextis ( $a , "!" , false ) ) return false ; # Table
			if ( $this->nextis ( $a , "=" , false ) ) return false ; # Heading
			if ( $this->nextis ( $a , "----" , false ) ) return false ; # <hr>
			}
		$this->bold_italics = "" ;
		return $this->once ( $a , $xml , "restofline" ) ;
		}
	
	function p_blankline ( &$a , &$xml )
		{
		if ( $this->nextis ( $a , "\n" ) ) return true ;
		return false ;
		}
	
	function p_block_lines ( &$a , &$xml , $force = false )
		{
		$x = "" ;
		$b = $a ;
		if ( !$this->p_line ( $b , $x , $force ) ) return false ;
		while ( $this->p_line ( $b , $x , false ) ) ;
		while ( $this->p_blankline ( $b , $x ) ) ; # Skip coming blank lines
		$xml .= "<paragraph>{$x}</paragraph>" ;
		$a = $b ;
		return true ;
		}
	


	# PRE block
	# Parses a line starting with ' '
	function p_preline ( &$a , &$xml )
		{
		if ( $a >= $this->wl ) return false ; # Already at the end of the text
		if ( $this->w[$a]!= ' ' ) return false ; # Not a preline

		$a++ ;
		$this->bold_italics = "" ;
		$x = "" ;
		$ret = $this->once ( $a , $x , "restofline" ) ;
		if ( $ret ) {
			$xml .= "<preline>" . $x . "</preline>" ; 
		}
		return $ret ;
		}

	# Parses a block of lines each starting with ' '
	function p_block_pre ( &$a , &$xml )
		{
		$x = "" ;
		$b = $a ;
		if ( !$this->once ( $b , $x , "preline" , true , true ) ) return false ;
		$this->once ( $b , $x , "blankline" , false , true ) ;	
		$xml .= "<preblock>{$x}</preblock>" ;
		$a = $b ;
		return true ;
		}
	
	# LIST block
	# Returns a list tag depending on the wiki markup
	function listtag ( $c , $open = true )
		{
		if ( !$open ) return "</list>" ;
		$r = "" ;
		if ( $c == '#' ) $r = "numbered" ;
		if ( $c == '*' ) $r = "bullet" ;
		if ( $c == ':' ) $r = "ident" ;
		if ( $c == ';' ) $r = "def" ;
		if ( $r != "" ) $r = " type='{$r}'" ;
		$r = "<list{$r}>" ;
		return $r ;
		}
	
	# Opens/closes list tags
	function fixlist ( $last , $cur )
		{
		$r = "" ;
		$olast = $last ;
		$ocur = $cur ;
		$ocommon = "" ;
		
		# Remove matching parts
		while ( $last != "" && $cur != "" && $last[0] == $cur[0] )
			{
			$ocommon = $cur[0] ;
			$cur = substr ( $cur , 1 ) ;
			$last = substr ( $last , 1 ) ;
			}
		
		# Close old tags
		$fixitemtag = false ;
		if ( $last != "" && $ocommon != "" ) $fixitemtag = true ;
		while ( $last != "" )
			{
			$r .= "</listitem>" . $this->listtag ( substr ( $last , -1 ) , false ) ;
			$last = substr ( $last , 0 , -1 ) ;
			}
		if ( $fixitemtag ) $r .= "</listitem><listitem>" ;
		
		# Open new tags
		while ( $cur != "" )
			{
			$r .= $this->listtag ( $cur[0] ) . "<listitem>" ;
			$cur = substr ( $cur , 1 ) ;
			}
		
		return $r ;
		}
	
	# Parses a single list line
	function p_list_line ( &$a , &$xml , &$last )
		{
		$cur = "" ;
		do {
			$lcur = $cur ;
			while ( $this->nextis ( $a , "*" ) ) $cur .= "*" ;
			while ( $this->nextis ( $a , "#" ) ) $cur .= "#" ;
			while ( $this->nextis ( $a , ":" ) ) $cur .= ":" ;
			while ( $this->nextis ( $a , ";" ) ) $cur .= ";" ;
			} while ( $cur != $lcur ) ;

		$unchanged = false ;
#		if ( substr ( $cur , 0 , strlen ( $last ) ) == $last ) $unchanged = true ;
		if ( $last == $cur ) $unchanged = true ;
		$xml .= $this->fixlist ( $last , $cur ) ;

		if ( $cur == "" ) return false ; # Not a list line
		$last = $cur ;
		$this->skipblanks ( $a ) ;
		
		if ( $unchanged ) $xml .= "</listitem><listitem>" ;
		if ( $cur == ";" ) # Definition
			{
			$b = $a ;
			while ( $b < $this->wl && $this->w[$b] != "\n" && $this->w[$b] != ':' ) $b++ ;
			if ( $b >= $this->wl || $this->w[$b] == "\n" )
				{
				$xml .= "<defkey>" ;
				$this->p_restofline ( $a , $xml ) ;
				$xml .= "</defkey>" ;
				}
			else
				{
				$xml .= "<defkey>" ;
				$this->w[$b] = "\n" ;
				$this->p_restofline ( $a , $xml ) ;
				$xml .= "</defkey>" ;
				$xml .= "<defval>" ;
				$this->p_restofline ( $a , $xml ) ;
				$xml .= "</defval>" ;
				}
			}
		else $this->p_restofline ( $a , $xml ) ;
		return true ;
		}
		
	# Checks for a list block ( those nasty things starting with '*', '#', or the like...
	function p_block_list ( &$a , &$xml )
		{
		$last = "" ;
		$found = false ;
		while ( $this->p_list_line ( $a , $xml , $last ) ) $found = true ;
		return $found ;
		}
	
	# HTML
	# This function detects a HTML tag, finds the matching close tag, 
	# parses everything in between, and returns everything as an extension.
	# Returns false otherwise.
	function p_html ( &$a , &$xml )
		{
		if ( !$this->nextis ( $a , "<" , false ) ) return false ;
		
		$b = $a ;
		$x = "" ;
		$tag = "" ;
		$closing = false ;
		$selfclosing = false ;
		
		if ( !$this->p_html_tag ( $b , $x , $tag , $closing , $selfclosing ) ) return false ;
		
		if ( isset ( $this->directhtmltags[$tag] ) )
			{
			$tag_open = "<" . $this->directhtmltags[$tag] ;
			$tag_close = "</" . $this->directhtmltags[$tag] . ">" ;
			}
		else
			{
			$tag_open = "<extension extension_name='{$tag}'" ;
			$tag_close = "</extension>" ;
			}
		
		# Is this tag self-closing?
		if ( $selfclosing )
			{
			$a = $b ;
			$xml .= $tag_open . $x . ">" . $tag_close ;
			return true ;
			}
		
		# Find the matching close tag
		# TODO : The simple open/close counter should be replaced with a
		#        stack to allow for tolerating half-broken HTML,
		#        such as unclosed <li> tags
		$begin = $b ;
		$cnt = 1 ;
		$tag2 = "" ;
		while ( $cnt > 0 && $b < $this->wl )
			{
			$x2 = "" ;
			$last = $b ;
			if ( !$this->p_html_tag ( $b , $x2 , $tag2 , $closing , $selfclosing ) )
				{
				$dummy = "";
				if ( $tag != "nowiki" && $this->w[$b] == '{' && $this->p_template ( $b , $dummy ) )
					continue ;
				$b++ ;
				continue ;
				}
			if ( $tag != $tag2 ) continue ;
			if ( $selfclosing ) continue ;
			if ( $closing ) $cnt-- ;
			else $cnt++ ;
			}

		if ( $cnt > 0 ) return false ; # Tag was never closed

		# What happens in between?
		$between = substr ( $this->w , $begin , $last - $begin ) ;
		
		if ( $tag != "nowiki" && $tag != "math" ) 
			{
			if ( $tag == 'gallery' ) {
				$this->gallery2wiki ( $between ) ;
				$tag_open = "" ;
				$tag_close = "" ;
			}
			
			# Parse the part in between the tags
			$subparser = new wiki2xml ;
			$subparser->inherit ( $this ) ;
			$between2 = $subparser->parse ( $between ) ;
			
			# Was the parsing correct?
			if ( $between2 != $this->errormessage )
				$between = $this->strip_single_paragraph ( $between2 ) ; # No <paragraph> for inline HTML tags
			else
				$between = htmlspecialchars ( $between ) ; # Incorrect markup, use safe wiki source instead
			}
		else $between = htmlspecialchars ( $between ) ; # No wiki parsing in here

		$a = $b ;
		if ( $tag_open != "" ) $xml .= $tag_open . $x . ">" ;
		$xml .= $between ;
		if ( $tag_close != "" ) $xml .= $tag_close ;
		return true ;
		}
	
	/**
	 * Converts the lines within a <gallery> to wiki tables
	 */
	function gallery2wiki ( &$text ) {
		$lines = explode ( "\n" , trim ( $text ) ) ;
		$text = "{| style='border-collapse: collapse; border: 1px solid grey;'\n" ;
		$cnt = 0 ;
		foreach ( $lines AS $line ) {
			if ( $cnt >= 4 ) {
				$cnt = 0 ;
				$text .= "|--\n" ;
			}
			$a = explode ( "|" , $line , 2 ) ;
			if ( count ( $a ) == 1 ) { # Generate caption from file name
				$b = $a[0] ;
				$b = explode ( ":" , $b , 2 ) ;
				$b = array_pop ( $b ) ;
				$b = explode ( "." , $b ) ;
				array_pop ( $b ) ;
				$a[] = implode ( "." , $b ) ;
			}
			$link = array_shift ( $a ) ;
			$caption = array_pop ( $a ) ;
			$text .= "|valign=top align=left|[[{$link}|thumb|center|]]<br/>{$caption}\n" ;
			$cnt++ ;
		}
		$text .= "|}\n" ;
	}
	
	function strip_single_paragraph ( $s )
		{
		if ( substr_count ( $s , "paragraph>" ) == 2 &&
			 substr ( $s , 0 , 11 ) == "<paragraph>" &&
			 substr ( $s , -12 ) == "</paragraph>" )
			$s = substr ( $s , 11 , -12 ) ;
		return $s ;
		}
	
	# This function checks for and parses a HTML tag
	# Only to be called from p_html, as it returns only a partial extension tag!
	function p_html_tag ( &$a , &$xml , &$tag , &$closing , &$selfclosing )
		{
		if ( $this->w[$a] != '<' ) return false ;
		$b = $a + 1 ;
		$this->skipblanks ( $b ) ;
		$tag = "" ;
		$attrs = array () ;
		if ( !$this->scanplaintext ( $b , $tag , array ( " " , ">" ) , array ( "\n" ) ) ) return false ;
		
		$this->skipblanks ( $b ) ;
		if ( $b >= $this->wl ) return false ;
		
		$tag = trim ( strtolower ( $tag ) ) ;
		$closing = false ;
		$selfclosing = false ;
		
		# Is closing tag?
		if ( substr ( $tag , 0 , 1 ) == "/" )
			{
			$tag = substr ( $tag , 1 ) ;
			$closing = true ;
			$this->skipblanks ( $b ) ;
			if ( $b >= $this->wl ) return false ;
			}
		
		if ( substr ( $tag , -1 ) == "/" )
			{
			$tag = substr ( $tag , 0 , -1 ) ;
			$selfclosing = true ;
			}

		# Parsing attributes
		$ob = $b ;
		$q = "" ;
		while ( $b < $this->wl && ( $q != "" || ( $this->w[$b] != '>' && $this->w[$b] != '/' ) ) ) {
			if ( $this->w[$b] == '"' || $this->w[$b] == "'" ) {
				if ( $q == "" ) $q = $this->w[$b] ;
				else if ( $this->w[$b] == $q ) $q = "" ;
			}
			$b++ ;
		}
		if ( $b >= $this->wl ) return false ;
		$attrs = $this->preparse_attributes ( substr ( $this->w , $ob , $b - $ob + 1 ) ) ;
		
		# Is self closing?
		if ( $tag == 'br' ) $selfclosing = true ; # Always regard <br> as <br/>
		if ( $this->w[$b] == '/' )
			{
			$b++ ;
			$this->skipblanks ( $b ) ;
			$selfclosing = true ;
			}
		
		$this->skipblanks ( $b ) ;
		if ( $b >= $this->wl ) return false ;
		if ( $this->w[$b] != '>' ) return false ;
		
		$a = $b + 1 ;
		if ( count ( $attrs ) > 0 )
			{
			$xml = " " . implode ( " " , $attrs ) ;
			}
		return true ;
		}

	# This function replaces templates and separates HTML attributes.
	# It is used for both HTML tags and wiki tables
	function preparse_attributes ( $x )
		{
		# Creating a temporary new parser to run the attribute list in
		$np = new wiki2xml ;
		$np->inherit ( $this ) ;
		$np->w = $x ;
		$np->wl = strlen ( $x ) ;

		# Replacing templates, and '<' and '>' in parameters
		$c = 0 ;
		$q = "" ;
		while ( $q != "" || ( $c < $np->wl && $np->w[$c] != '>' && $np->w[$c] != '/' ) )
			{
			$y = $np->w[$c] ;
			if ( $np->nextis ( $c , "{{" , false ) ) {
				$xx = "" ;
				if ( $np->p_template ( $c , $xx ) ) continue ;
				else $c++ ;
			} else if ( $y == "'" || $y == '"' ) {
				if ( $q == "" ) $q = $y ;
				else if ( $y == $q ) $q = "" ;
				$c++ ;
			} else if ( $q != "" && ( $y == '<' || $y == '>' ) ) {
				$y = htmlentities ( $y ) ;
				$np->w = substr ( $np->w , 0 , $c ) . $y . substr ( $np->w , $c + 1 ) ;
				$np->wl += strlen ( $y ) - 1 ;
			} else $c++ ;
			if ( $c >= $np->wl ) return array () ;
			}

		$attrs = array () ;
		$c = 0 ;
			
		# Seeking attributes
		while ( $np->w[$c] != '>' && $np->w[$c] != '/' )
			{
			$attr = "" ;
			if ( !$np->p_html_attr ( $c , $attr ) ) break ;
			if ( $attr != "" ) {
				$key = array_shift ( explode ( "=" , $attr , 2 ) ) ;
				if ( !isset ( $attrs[$key] ) && substr ( $attr , -3 , 3 ) != '=""' ) {
					$attrs[$key] = $attr ;
				}
			}
			$np->skipblanks ( $c ) ;
			if ( $c >= $np->wl ) return array () ;
			}		
		if ( substr ( $np->w , $c ) != ">" AND substr ( $np->w , $c ) != "/" ) return array() ;

		return $attrs ;
		}

		
	# This function scans a single HTML tag attribute and returns it as <attr name='key'>value</attr>
	function p_html_attr ( &$a , &$xml )
		{
		$b = $a ;
		$this->skipblanks ( $b ) ;
		if ( $b >= $this->wl ) return false ;
		$name = "" ;
		if ( !$this->scanplaintext ( $b , $name , array ( " " , "=" , ">" , "/" ) , array ( "\n" ) ) ) return false ;
		
		$this->skipblanks ( $b ) ;
		if ( $b >= $this->wl ) return false ;
		$name = trim ( strtolower ( $name ) ) ;
		
		# Trying to catch illegal names; should be replaced with regexp
		$n2 = "" ;
		for ( $q = 0 ; $q < strlen ( $name ) ; $q++ ) {
			if ( $name[$q] == '_' OR ( $name[$q] >= 'a' AND $name[$q] <= 'z' ) )
				$n2 .= $name[$q] ;
		}
		$name = trim ( $n2 ) ;
		if ( $name == 'extension_name' ) return false ; # Not allowed, because used internally
		if ( $name == '' ) return false ;
		
		# Determining value
		$value = "" ;
		if ( $this->w[$b] == "=" )
			{
			$b++ ;
			$this->skipblanks ( $b ) ;
			if ( $b >= $this->wl ) return false ;
			$q = "" ;
			$is_q = false ;
			if ( $this->w[$b] == '"' || $this->w[$b] == "'" )
				{
				$q = $this->w[$b] ;
				$b++ ;
				if ( $b >= $this->wl ) return false ;
				$is_q = true ;
				}
			while ( $b < $this->wl )
				{
				$c = $this->w[$b] ;
				if ( $c == $q )
					{
					$b++ ;
					if ( $is_q ) break ;
					return false ; # Broken attribute value
					}
				if ( $this->nextis ( $b , "\\{$q}" ) ) # Ignore escaped quotes
					{
					$value .= "\\{$q}" ;
					continue ;
					}
				if ( $c == "\n" ) return false ; # Line break before value end
				if ( !$is_q && ( $c == ' ' || $c == '>' || $c == '/' ) ) break ;
				$value .= htmlspecialchars ( $c ) ;
				$b++ ;
				}
			}
		if ( $name == "" ) return true ;
		
		$a = $b ;
		if ( $q == "'" ) $q = "'" ;
		else $q = '"' ;
		$xml = "{$name}={$q}{$value}{$q}" ;
		#$xml .= "<attr name='{$name}'>{$value}</attr>" ;
		return true ;
		}
	
	# Horizontal ruler (<hr> / ----)
	function p_hr ( &$a , &$xml )
		{
		if ( !$this->nextis ( $a , "----" ) ) return false ;
		$this->skipblanks ( $a , "-" ) ;
		$this->skipblanks ( $a ) ;
		$xml .= "<hr/>" ;
		return true ;
		}
	
	# TABLE
	# Scans the rest of the line as HTML attributes and returns the usual <attrs><attr> string
	function scanattributes ( &$a )
		{
		$x = "" ;
		while ( $a < $this->wl )
			{
			if ( $this->w[$a] == "\n" ) break ;
			$x .= $this->w[$a] ;
			$a++ ;
			}
		$x .= ">" ;
		
		$attrs = $this->preparse_attributes ( $x ) ;
		
		$ret = "" ;
		if ( count ( $attrs ) > 0 )
			{
			#$ret .= "<attrs>" ;
			$ret .= " " . implode ( " " , $attrs ) ;
			#$ret .= "</attrs>" ;
			}
		return $ret ;
		}
		
	# Finds the first of the given items; does *not* alter $a
	function scanahead ( $a , $matches )
		{
		while ( $a < $this->wl )
			{
			foreach ( $matches AS $x )
				{
				if ( $this->nextis ( $a , $x , false ) )
					{
					return $a ;
					}
				}
			$a++ ;
			}
		return -1 ; # Not found
		}
	

	# The main table parsing function
	function p_table ( &$a , &$xml )
		{
		if ( $a >= $this->wl ) return false ;
		$c = $this->w[$a] ;
		if ( $c == "{" && $this->nextis ( $a , "{|" , false ) )
			return $this->p_table_open ( $a , $xml ) ;
		
#		print "p_table for " . htmlentities ( substr ( $this->w , $a ) ) . "<br/><br/>" ; flush () ;
		
		if ( count ( $this->tables ) == 0 ) return false ; # No tables open, nothing to do
		
		# Compatability for table cell lines starting with blanks; *evil MediaWiki parser!*
		$b = $a ;
		$this->skipblanks ( $b ) ;
		if ( $b >= $this->wl ) return false ;
		$c = $this->w[$b] ;
		
		if ( $c != "|" && $c != "!" ) return false ; # No possible table markup
		
		if ( $c == "|" && $this->nextis ( $b , "|}" , false ) ) return $this->p_table_close ( $b , $xml ) ;
		
		#if ( $this->nextis ( $a , "|" , false ) || $this->nextis ( $a , "!" , false ) )
		return $this->p_table_element ( $b , $xml , true ) ;
		}
		
	function lasttable ()
		{
		return $this->tables[count($this->tables)-1] ;
		}

	# Returns the attributes for table cells
	function tryfindparams ( &$a )
		{
		$n = strspn ( $this->w , $this->allowed , $a ) ; # PHP 4.3.0 and above
#		$n = strspn ( substr ( $this->w , $a ) , $this->allowed ) ; # PHP < 4.3.0
		if ( $n == 0 ) return "" ; # None found

		$b = $a + $n ;
		if ( $b >= $this->wl ) return "" ;
		if ( $this->w[$b] != "|" && $this->w[$b] != "!" ) return "" ;
		if ( $this->nextis ( $b , "||" , false ) ) return "" ; # Reached a ||, so return blank string
		if ( $this->nextis ( $b , "!!" , false ) ) return "" ; # Reached a ||, so return blank string
		$this->w[$b] = "\n" ;
		$ret = $this->scanattributes ( $a ) ;
		$this->w[$b] = "|" ;
		$a = $b + 1 ;
		return $ret ;
		}
	
	function p_table_element ( &$a , &$xml , $newline = false )
		{
#		print "p_table_element for " . htmlentities ( substr ( $this->w , $a ) ) . "<br/><br/>" ; flush () ;
		$b = $a ;
		$this->skipblanks ( $b ) ; # Compatability for table cells starting with blanks; *evil MediaWiki parser!*
		if ( $b >= $this->wl ) return false ; # End of the game
		$x = "" ;
		if ( $newline && $this->nextis ( $b , "|-" ) ) # Table row
			{
			$this->skipblanks ( $b , "-" ) ;
			$this->skipblanks ( $b ) ;
			
			$attrs = $this->scanattributes ( $b ) ;
			if ( $this->tables[count($this->tables)-1]->is_row_open ) $x .= "</tablerow>" ;
			else $this->tables[count($this->tables)-1]->is_row_open = true ;
			$this->tables[count($this->tables)-1]->had_row = true ;
			$x .= "<tablerow{$attrs}>" ;
			$y = "" ;
			$this->p_restofcell ( $b , $y ) ;
			}
		else if ( $newline && $this->nextis ( $b , "|+" ) ) # Table caption
			{
			$this->skipblanks ( $b ) ;
			$attrs = $this->tryfindparams ( $b ) ;
			$this->skipblanks ( $b ) ;
			if ( $this->tables[count($this->tables)-1]->is_row_open ) $x .= "</tablerow>" ;
			$this->tables[count($this->tables)-1]->is_row_open = false ;

			$y = "" ;
			if ( !$this->p_restofcell ( $b , $y ) ) return false ;
			$x .= "<tablecaption{$attrs}>{$y}</tablecaption>" ;
			}
		else # TD or TH
			{
			$c = $this->w[$b] ;
			$b++ ;
			$tag = "error" ;
			if ( $c == '|' ) $tag = "tablecell" ;
			else if ( $c == '!' ) $tag = "tablehead" ;
			$attrs = $this->tryfindparams ( $b ) ;
			$this->skipblanks ( $b ) ;
			if ( !$this->p_restofcell ( $b , $x ) ) return false ;
			
			if ( substr ( $x , 0 , 1 ) == "|" ) # Crude fix to compensate for MediaWiki "tolerant" parsing
				$x = substr ( $x , 1 ) ;
			$x = "<{$tag}{$attrs}>{$x}</{$tag}>" ;
			$this->tables[count($this->tables)-1]->had_cell = true ;
			if ( !$this->tables[count($this->tables)-1]->is_row_open )
				{
				$this->tables[count($this->tables)-1]->is_row_open = true ;
				$this->tables[count($this->tables)-1]->had_row = true ;
				$x = "<tablerow>{$x}" ;
				}
			}
		
		$a = $b ;
		$xml .= $x ;
		return true ;
		}

	# Finds the substring that composes the table cell,
	# then runs a new parser on it
	function p_restofcell ( &$a , &$xml )
		{
		# Get substring for cell
		$b = $a ;
		$sameline = true ;
		$x = "" ;
		$itables = 0 ;
		while ( $b < $this->wl )
			{
			$c = $this->w[$b] ;
			if ( $c == "<" && $this->once ( $b , $x , "html" ) ) continue ; # Up front to catch pre and nowiki
			if ( $c == "\n" ) { $sameline = false ; }
			if ( $c == "\n" && $this->nextis ( $b , "\n{|" ) ) { $itables++ ; continue ; }
			if ( $c == "\n" && $itables > 0 && $this->nextis ( $b , "\n|}" ) ) { $itables-- ; continue ; }
			
			if ( ( $c == "\n" && $this->nextis ( $b , "\n|" , false ) ) OR 
				 ( $c == "\n" && $this->nextis ( $b , "\n!" , false ) ) OR
				 ( $c == "\n" && $this->nextis ( $b , "\n |" , false ) ) OR # MediaWiki parser madness compensator
				 ( $c == "\n" && $this->nextis ( $b , "\n !" , false ) ) OR # MediaWiki parser madness compensator
				 ( $c == "|" && $sameline && $this->nextis ( $b , "||" , false ) ) OR
				 ( $c == "!" && $sameline && $this->nextis ( $b , "!!" , false ) ) )
				{
				if ( $itables == 0 ) break ;
				$b += 2 ;
				}

			if ( $c == "[" && $this->once ( $b , $x , "internal_link" ) ) continue ;
			if ( $c == "{" && $this->once ( $b , $x , "template_variable" ) ) continue ;
			if ( $c == "{" && $this->once ( $b , $x , "template" ) ) continue ;
			$b++ ;
			}
		
#		if ( $itables > 0 ) return false ;
		
		# Parse cell substring
		$s = substr ( $this->w , $a , $b - $a ) ;
		$p = new wiki2xml ;
		$p->inherit ( $this ) ;
		$x = $p->parse ( $s ) ;
		if ( $x == $this->errormessage ) return false ;

		$a = $b + 1 ;
		$xml .= $this->strip_single_paragraph ( $x ) ;
		return true ;
		}
	
	function p_table_close ( &$a , &$xml )
		{
		if ( count ( $this->tables ) == 0 ) return false ;
		$b = $a ;
		if ( !$this->nextis ( $b , "|}" ) ) return false ;
		if ( !$this->tables[count($this->tables)-1]->had_row ) return false ; # Table but no row was used
		if ( !$this->tables[count($this->tables)-1]->had_cell ) return false ; # Table but no cell was used
		$x = "" ;
		if ( $this->tables[count($this->tables)-1]->is_row_open ) $x .= "</tablerow>" ;
		unset ( $this->tables[count($this->tables)-1] ) ;
		$x .= "</table>" ;
		$xml .= $x ;
		$a = $b ;
		while ( $this->nextis ( $a , "\n" ) ) ;
		return true ;
		}
		
	function p_table_open ( &$a , &$xml )
		{
		$b = $a ;
		if ( !$this->nextis ( $b , "{|" ) ) return false ;
		
		$this->is_row_open = false ;

		# Add table to stack
		$nt->is_row_open = false ;
		$nt->had_row = false ;
		$nt->had_cell = false ;
		$this->tables[count($this->tables)] = $nt ;
		
		$x = "<table" ;
		$x .= $this->scanattributes ( $b ) . ">" ;
		while ( $this->nextis ( $b , "\n" ) ) ;
		
		while ( !$this->p_table_close ( $b , $x ) )
			{
			if ( $b >= $this->wl )
				{
				unset ( $this->tables[count($this->tables)-1] ) ;
				return false ;
				}
			if ( $this->p_table_open ( $b , $x ) ) continue ;
			if ( !$this->p_table_element ( $b , $x , true ) ) # No |} and no table element
				{
				unset ( $this->tables[count($this->tables)-1] ) ;
				return false ;
				}
			}
		$a = $b ;
		$xml .= $x ;
		return true ;
		}
	
	#-----------------------------------
	# Parse the article
	function p_article ( &$a , &$xml )
		{
		$x = "" ;
		$b = $a ;
		while ( $b < $this->wl )
			{
			if ( $this->onceormore ( $b , $x , "heading" ) ) continue ;
			if ( $this->onceormore ( $b , $x , "block_lines" ) ) continue ;
			if ( $this->onceormore ( $b , $x , "block_pre" ) ) continue ;
			if ( $this->onceormore ( $b , $x , "block_list" ) ) continue ;
			if ( $this->onceormore ( $b , $x , "hr" ) ) continue ;
			if ( $this->onceormore ( $b , $x , "table" ) ) continue ;
			if ( $this->onceormore ( $b , $x , "blankline" ) ) continue ;
			if ( $this->p_block_lines ( $b , $x , true ) ) continue ;
			# The last resort! It should never come to this!
			if ( !$this->compensate_markup_errors ) $xml .= "<error type='general' reason='no matching markup'/>" ;
			$xml .= htmlspecialchars ( $this->w[$b] ) ;
			$b++ ;
			}
		$a = $b ;
		$xml .= $x ;
		
#		asort ( $this->profile ) ;
#		$p = "" ;
#		foreach ( $this->profile AS $k => $v ) $p .= "<p>{$k} : {$v}</p>" ;
#		$xml = "<debug>{$this->cnt}{$p}</debug>" . $xml ;
		return true ;
		}
	
	# The only function to be called directly from outside the class
	function parse ( &$wiki )
		{
		$this->w = rtrim ( $wiki ) ;
		
		# Fix line endings
		$cc = count_chars ( $wiki , 0 ) ;
		if ( $cc[10] > 0 && $cc[13] == 0 )
			$this->w = str_replace ( "\r" , "\n" , $this->w ) ;
		$this->w = str_replace ( "\r" , "" , $this->w ) ;
		
		# Remove HTML comments
#    $this->w = str_replace ( "\n<!--" , "<!--" , $this->w ) ;
		# Important: Do not remove leading \n, since it could be a heading delimiter
		$this->w= preg_replace('/\n<!--(.|\s)*?-->\n/', "\n<!-- --> ", $this->w);
		$this->w= preg_replace('/<!--(.|\s)*?-->/', '', $this->w);
		$this->w= preg_replace('/<!--(.|\s)*$/', '', $this->w);
		
		# Run the thing!
#		$this->tables = array () ;
		$this->wl = strlen ( $this->w ) ;
		$xml = "" ;
		$a = 0 ;
		if ( !$this->p_article ( $a , $xml ) ) return $this->errormessage ;

		# XML cleanup
		$ol = -1 ;
		while ( $ol != strlen ( $xml ) ) {
			$ol = strlen ( $xml ) ;
			$xml = str_replace ( "<preline> " , "<preline><space/>" , $xml ) ;
			$xml = str_replace ( "<space/> " , "<space/><space/>" , $xml ) ;
		}
		$ol = -1 ;
		while ( $ol != strlen ( $xml ) ) {
			$ol = strlen ( $xml ) ;
			$xml = str_replace ( "  " , " " , $xml ) ;
			}
		$ol = -1 ;
		while ( $this->use_space_tag && $ol != strlen ( $xml ) ) {
			$ol = strlen ( $xml ) ;
			$xml = str_replace ( "> " , "><space/>" , $xml ) ;
			$xml = str_replace ( " <" , "<space/><" , $xml ) ;
		}
		$xml = str_replace ( '<tablerow></tablerow>' , '' , $xml ) ;
		
		return $xml ;
		}
	
	}

?>
