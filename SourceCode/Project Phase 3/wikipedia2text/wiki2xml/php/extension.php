<?php
/*
To enable this extension, put all files in this directory into a "wiki2xml"
subdirectory of your MediaWiki extensions directory.
Also, add
	require_once ( "extensions/wiki2xml/extension.php" ) ;
to your LocalSettings.php
The extension will then be accessed as [[Special:Wiki2XML]].
*/

if( !defined( 'MEDIAWIKI' ) ) die();

# Integrating into the MediaWiki environment

$wgExtensionCredits['Wiki2XML'][] = array(
        'name' => 'Wiki2XML',
        'description' => 'An extension to convert wiki markup into XML.',
        'author' => 'Magnus Manske'
);

$wgExtensionFunctions[] = 'wfWiki2XMLExtension';

# for Special::Version:
$wgExtensionCredits['parserhook'][] = array(
        'name' => 'wiki2xml extension',
        'author' => 'Magnus Manske et al.',
        'url' => 'http://en.wikipedia.org/wiki/User:Magnus_Manske',
        'version' => 'v0.02',
);


#_____________________________________________________________________________

/**
 * The special page
 */
function wfWiki2XMLExtension() { # Checked for HTML and MySQL insertion attacks
	global $IP, $wgMessageCache;
#	wfTasksAddCache();

	// FIXME : i18n
	$wgMessageCache->addMessage( 'wiki2xml', 'Wiki2XML' );

	require_once $IP.'/includes/SpecialPage.php';

	class SpecialWiki2XML extends SpecialPage {
	
		/**
		* Constructor
		*/
		function SpecialWiki2XML() { # Checked for HTML and MySQL insertion attacks
			SpecialPage::SpecialPage( 'Wiki2XML' );
			$this->includable( true );
		}
		
		/**
		* Special page main function
		*/
		function execute( $par = null ) { # Checked for HTML and MySQL insertion attacks
			global $wgOut, $wgRequest, $wgUser, $wgTitle, $IP;
			$fname = 'Special::Tasks:execute';
			global $xmlg , $html_named_entities_mapping_mine, $content_provider;
			include_once ( "default.php" ) ; 
			$xmlg['sourcedir'] = $IP.'/extensions/wiki2xml' ;
			include_once ( "w2x.php" ) ;
			
			$this->setHeaders();
			$wgOut->addHtml( $out );
		}
		
	} # end of class

	SpecialPage::addPage( new SpecialWiki2XML );
}


?>
