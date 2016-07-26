<!--
Converts Wikipedia articles in wiki format into an XML format. It might
segfault or go into an "infinite" loop sometimes.

Evan Jones <evanj@mit.edu>
April, 2008
Released under a BSD licence.
http://evanjones.ca/software/wikipedia2text.html
-->

<?php
error_reporting(E_ALL);
require_once("mediawiki_converter.php");

if (count($argv) != 3) {
    echo "wiki2xml_command [input wikitext] [output wiki XML]\n";
    exit(1);
}

$filename = $argv[1];
$wikitext = file_get_contents($filename);
if (strlen($wikitext) == 0) {
    echo "Bad input file\n";
    exit(1);
}

$filename_parts = explode("/", $filename);
$title = $filename_parts[count($filename_parts)-1];
$title = str_replace(".txt", "", $title);
$title = urldecode($title);

// Configures options for converting to XML
$xmlg = array();
$xmlg["usetemplates"] = "none";
$xmlg["resolvetemplates"] = "none";
$xmlg["templates"] = array();
$xmlg['add_gfdl'] = false;
$xmlg['keep_interlanguage'] = false;
$xmlg['keep_categories'] = false;
$xmlg['text_hide_images'] = true;
$xmlg['text_hide_tables'] = true;
$xmlg["useapi"] = false;
$xmlg["xml_articles_header"] = "<articles>";

// No idea what it does, but it makes it work
$content_provider = new ContentProviderHTTP;

$converter = new MediaWikiConverter;
$xml = $converter->article2xml($title, $wikitext , $xmlg);

// To convert to plain text:
//~ require_once("xml2tree.php");
//~ require_once("xml2txt.php");
//~ $x2t = new xml2php ;
//~ $tree = $x2t->scanString($xml);
//~ $text = trim($tree->parse($tree));

file_put_contents($argv[2], $xml);
?>
