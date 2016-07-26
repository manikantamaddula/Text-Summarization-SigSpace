<?php

/**
 * You can create your own local.php file, similar to this one, to configure
 * your local installation.
 * If you do not create a local.php file, the scripts will run with
 * default settings.
 */

$xmlg["site_base_url"] = "127.0.0.1/phase3" ;
$xmlg["use_special_export"] = 1 ;

# Directory for temporary files:
$xmlg["temp_dir"] = "C:/windows/temp" ;

$xmlg["docbook"] = array (
	"command_pdf" => "C:/docbook/bat/docbook_pdf.bat %1" ,
	"command_html" => "C:/docbook/bat/docbook_html.bat %1" ,
	"temp_dir" => "C:/docbook/repository" ,
	"out_dir" => "C:/docbook/output" ,
	"dtd" => "file:/c:/docbook/dtd/docbookx.dtd"
) ;

/* To allow parameters passed as URL parameter ($_GET), set
$xmlg['allow_get'] = true ;

Parameters:
doit=1
text=lines_of_text_or_titles
whatsthis=wikitext/articlelist
site=en.wikipedia.org/w
output_format=xml/text/xhtml/docbook_xml/odt_xml/odt

Optional:
use_templates=all/none/these/notthese
templates=lines_of_templates
document_title=
add_gfdl=1
keep_categories=1
keep_interlanguage=1
*/



# To use the toolserver text access, set
# $xmlg["use_toolserver_url"] = true ;
# $xmlg["use_special_export"] = false ;

# On Windows, set
# $xmlg['is_windows'] = true ;

### Uncomment the following to use Special:Export and (potentially) automatic
### authors list; a little slower, though:
#$xmlg["use_special_export"] = 1 ;


### Uncomment and localize the following to offer ODT export

# Path to the zip/unzip programs; can be omitted if in path:
#$xmlg["zip_odt_path"] = "E:\\Program Files\\7-Zip" ;

# Command to zip directory $1 to file $2;
# NOTE THE '*' AFTER '$2' FOR WINDOWS ONLY!
#$xmlg["zip_odt"] = '7z.exe  a -r -tzip $1 $2*' ;

# Command to unzip file $1 to directory $2:
#$xmlg["unzip_odt"] = '7z.exe x $1 -o$2' ;


# If you want to do text-file browsing, run "xmldump2files.php" once
# (see settings there), then set this:
# $base_text_dir = "C:/dewiki-20060327-pages-articles" ;

?>
