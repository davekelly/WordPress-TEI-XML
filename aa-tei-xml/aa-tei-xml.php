<?php
/*
	Plugin Name: TEI-XML
	Plugin URI: https://github.com/davekelly/WordPress-TEI-XML
	Description: Wordpress Plugin for display of TEI XML documents
	Author: Dave Kelly (@davkell)
	Version: 0.1-alpha
	Author URI: https://github.com/davekelly/
	Text Domain: aa-tei-xml
	Domain Path: /lang
 */

/*
 * [Credits]
 *
 *  
 *  Code for this plug-in was developed for the [Thomas Moore Archive](http://www.thomasmoore.ie) project at 
 *  the [Moore Institute](http://nuigalway.ie/mooreinstitute), [NUI Galway](http://nuigalway.ie).
 *  
 *  Parts of this plugin are based on work done by:
 *  	- TEIDisplay Plugin (http://omeka.org/codex/Plugins/TeiDisplay) by ScholarsLab.org at University of Virginia Library
 *  	- XML-Documents Plugin, by mitcho (Michael Yoshitaka Erlewine) - http://wordpress.org/plugins/xml-documents/
 * 		- TEICHI.org Drupal Module:	http://www.teichi.org/
 * 				Current maintainer: Christof Schöch (christof.s) - http://drupal.org/user/1152238
 *     			Coders: Roman Kominek, with Dmitrij Funkner, Mohammed Abuhaish.
 *        		Project supervisors: Christof Schöch, Lutz Wegner and Sebastian Pape, University of Kassel, Germany.	
 */

/*

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License, version 2, as 
published by the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
http://www.gnu.org/copyleft/gpl.html 

 */

// Make sure we don't expose any info if called directly
if ( !function_exists( 'add_action' ) ) {
	echo "Wha'sup. Not much happening here. Sorry ;)";
	exit;
}

define( 'AATEIXML', '0.1' );

$pluginurl = plugin_dir_url(__FILE__);
if ( preg_match( '/^https/', $pluginurl ) && !preg_match( '/^https/', get_bloginfo('url') ) )
	$pluginurl = preg_replace( '/^https/', 'http', $pluginurl );
define( 'AATEIXML_FRONT_URL', $pluginurl );

define( 'AATEIXML_URL', plugin_dir_url(__FILE__) );
define( 'AATEIXML_PATH', plugin_dir_path(__FILE__) );
define( 'AATEIXML_BASENAME', plugin_basename( __FILE__ ) );


// Support uploading of XML
add_filter( 'upload_mimes', 'aa_add_xml_mime' );
add_filter( 'wp_mime_type_icon', 'aa_xml_mime_type_icon', 10, 3 );

function aa_add_xml_mime($mimes) {
  $mimes['xml'] = 'application/xml';
  $mimes['tei'] = 'application/tei+xml'; // http://www.iana.org/assignments/media-types/application/tei+xml
  $mimes['xsl'] = 'application/xslt+xml';     // http://www.iana.org/assignments/media-types/media-types.xhtml, http://www.w3.org/TR/2007/REC-xslt20-20070123/#media-type-registration
  $mimes['xslt'] = 'application/xslt+xml';
  return $mimes;
}
function aa_xml_mime_type_icon($icon, $mime, $post_id) {
	if ( $mime == 'application/xml' || $mime == 'text/xml'  || $mime == 'application/tei+xml' )
		return wp_mime_type_icon('document');
	return $icon;
}


// Include 
require AATEIXML_PATH . 'inc/class-aa-tei-xml.php';

if(is_admin()){    
    // admin side stuff...
    require AATEIXML_PATH.'admin/class-aa-tei-xml-admin.php';
    // 
    // Need to include xsl files...
    // 
}else{
    // require AATEIXML_PATH. 'frontend/aa-aa-tei-xml-form.php';
}

if( ! isset($aaTeiXml ) ){
	$aaTeiXml = new AATEIXML();
}

/**
 * Enqueue front-end scripts / style
 * @return [type] [description]
 */
function aa_plugin_enqueue_scripts(){
    wp_enqueue_style('aa-aa-tei-xml', AATEIXML_FRONT_URL . 'frontend/css/tei_display_public.css');
    wp_enqueue_script('jquery');
    wp_enqueue_script('aa-aa-tei-xml', AATEIXML_FRONT_URL . 'frontend/js/tei_display_toggle_toc.js', array('jquery'), AATEIXML, true );
}
add_action('wp_enqueue_scripts', 'aa_plugin_enqueue_scripts');



// Get rid of everything on de-activation / deletion
// register_deactivation_hook( __FILE__, array( 'AATEIXML_Plugin_Admin', 'on_deactivate' ) );
// register_uninstall_hook( __FILE__, array( 'AATEIXML_Plugin_Admin', 'on_uninstall' ) );
