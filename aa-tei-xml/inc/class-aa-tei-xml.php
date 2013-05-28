<?php

class AATEIXML{

	public function __construct()
	{
		$this->init();
	}


	function init()
	{
		// Setup XML document edit screen:
		// hook into dbx_post_advanced instead of add_meta_boxes so we get in earlier
		add_action( 'dbx_post_advanced', array($this, 'xmldoc_register_meta_box') );

		// Modify Media upload screen for XML documents
		add_action( 'admin_enqueue_scripts', array($this, 'xmldoc_media_form_enqueue'), 10, 1 );
		add_filter( 'attachment_fields_to_edit', array($this, 'xmldoc_media_form_fields'), 10, 2 );
		add_action( 'wp_ajax_set-xml-document', array($this, 'xmldoc_set_xml_document') );
	}



	function xmldoc_content_update()
	{

	}


	/**
	 * Parse XML doc and apply XSL
	 *
	 * @param  [type] $content [description]
	 * @return [type]          [description]
	 */
	function xmldoc_parse( $post_ID = null) {
		global $post;

		if( is_object($post)){
			$post_ID = $post->ID;
		}

		if ( !class_exists( 'XSLTProcessor' ) || !class_exists( 'DOMDocument' ) )
			return 'XML and XSLT processing is not supported by your PHP installation. Please install <a href="http://www.php.net/manual/en/book.xsl.php">the PHP XSL module</a>.';

		if ( !$xml_ID = get_post_meta( $post_ID, 'aa_tei_xml', true ) )
			 	return 'XML document not set.';

		
		$teiFile = get_option('upload_path') .'/'. get_post_meta( $xml_ID, '_wp_attached_file', true);
		//var_dump($teiFile);
		//die();
		if ( !file_exists( $teiFile ) ){
			return 'XML document not found.';	
			die('XML document not found.');
		}

		/*
		$xslt_ID = get_post_meta( $post_ID, 'aa_tei_xslt', true);

		// If there's a document-specific XSLT set...
		if ( isset( $xslt_ID ) ) {
			if ( is_int( $xslt_ID ) ) {
				// if it's an int, it's an attachment ID.
				$xslt = get_post_meta( $xslt_ID, '_wp_attached_file', true);
				$xslt = get_option('upload_path') . '/' . $xslt;
			} else {
				// else in current theme dir
				$xslt = AATEIXML_PATH . '/' . $xslt;
			}
		} else {
			$stylesheet = AATEIXML_PATH . "xsl/default.xsl";
		}
		if ( !file_exists( $xslt ) )
			return $xslt . 'XSLT stylesheet not found.';	
		*/

		// $stylesheet = AATEIXML_PATH . "xsl/default.xsl";
		$stylesheet = AATEIXML_PATH . "xsl/francesca.xsl";

		$xp = new XsltProcessor();
		// create a DOM document and load the XSL stylesheet
		$xsl = new DomDocument;

		// import the XSL styelsheet into the XSLT process
		$xsl->load($stylesheet);
		$xp->importStylesheet($xsl);
		
		// set query parameter to pass into stylesheet (for default.xsl)
		$displayType 	= 'entire';
		$section 		= 'body';
		$xp->setParameter('', 'display', $displayType);
		$xp->setParameter('', 'section', $section);
		
		// create a DOM document and load the XML data
		$xml_doc = new DomDocument;
		$xml_doc->load($teiFile);

		// xPath to extract the document title
		$xpath = new DOMXPath($xml_doc);
		$titleQueries = '//*[local-name() = "teiHeader"]/*[local-name() = "fileDesc"]/*[local-name() = "titleStmt"]/*[local-name() = "title"]';
		$nodes = $xpath->query($titleQueries);
		$newTitle = null;
		foreach ($nodes as $node){					
			//see if that text is already set and don't put in any blank or null fields
			$newTitle = preg_replace('/\s\s+/', ' ', trim($node->nodeValue));
		}

		
		
		try { 
			// transform to html and update wordpress body content
			// and title
			if ($doc = $xp->transformToXML($xml_doc)) {			
				
				$postUpdate = array(
					'ID' 			=> $post_ID,
					'post_content'	=> $doc
				);
				if( $newTitle ){
					$postUpdate['post_title'] = $newTitle;
				}

				wp_update_post( $postUpdate );
				
				return $postUpdate;
			}
		} catch (Exception $e){
			return $e->getMessage();
		} 

		// var_dump($html);
		// die();
	}

	// XML DOCS EDIT SCREEN

	// Add XML document meta box
	function xmldoc_register_meta_box() {
		global $post_type;
		if ( post_type_supports( $post_type, 'xmldoc' ) ) {
			add_meta_box( 'xml-document', 'XML Document', array($this, 'xmldoc_meta_box'), $post_type, 'normal', 'core' );
			add_thickbox();
			wp_enqueue_script('media-upload');
			$src = AATEIXML_FRONT_URL . 'frontend/js/admin.js';
			wp_enqueue_script( 'xml-document-admin', $src, array( 'jquery' ) , '1.0', true );
		}
	}

	function xmldoc_meta_box() {
		global $post;	
		$xml = get_post_meta( $post->ID, 'aa_tei_xml', true );
	//	$xslt = get_post_meta( $post->ID, '_xslt', true );
		echo $this->xmldoc_document_html( $xml );

		// echo $this->xmldoc_parse();
	}

	protected function xmldoc_document_html( $xml_ID ) {
		global $content_width, $_wp_additional_image_sizes, $post_ID;

		$set_thumbnail_link = '<p class="hide-if-no-js"><a title="' . esc_attr( 'Set XML document' ) . '" href="' . esc_url( get_upload_iframe_src('media') ) . '" id="set-xml-document" class="thickbox">%s</a></p>';
		$content = sprintf($set_thumbnail_link, esc_html( 'Set XML document' ));

		$file = get_post_meta( $xml_ID, '_wp_attached_file', true);
		$abspath = get_option('upload_path') . '/' . $file;
		if ( $file && file_exists( $abspath ) )
			$content .= '<p><img src="' . admin_url('images/yes.png') . '" alt="XML document specified"/> XML document specified: <a href="' . esc_html( wp_get_attachment_url( $xml_ID ) ) . '">' . esc_html( get_the_title($xml_ID) ) . '</a></p>';

		return $content;
	}

	// XML DOCUMENT MEDIA ITEM MODS
	function xmldoc_media_form_enqueue( $page ) {
		if ( 'media-upload-popup' != $page )
			return;
		$src = AATEIXML_FRONT_URL . 'frontend/js/set-xml-document.js';
		wp_enqueue_script( 'set-xml-document', $src, array( 'jquery' ) , '1.0', true );
	}

	function xmldoc_media_form_fields($form_fields, $post) {
		if ( $post->post_mime_type == 'application/xml' ) {
			$attachment_id = $post->ID;
			$calling_post_id = 0;
			$hit = 0;
			if ( isset( $_GET['post_id'] ) ){
				$calling_post_id = absint( $_GET['post_id'] );
				$hit = 1;
			}
			elseif ( isset( $_POST ) && count( $_POST ) ){ // Like for async-upload where $_GET['post_id'] isn't set{
				$calling_post_id = $post->post_parent;
				$hit = 2;
			}
			if ( $calling_post_id ) {
				$ajax_nonce = wp_create_nonce( "set_xml_document-$calling_post_id" );
				$form_fields['buttons'] = array( 'tr' => "\t\t<tr class='submit'><td></td><td class='savesend'><a class='wp-xml-document' id='wp-xml-document-{$attachment_id}' href='#' onclick='WPSetAsXMLDoc(\"$attachment_id\", \"$ajax_nonce\", \"$hit\");return false;'>" . esc_html( "Use as XML document" ) . "</a></td></tr>\n" );
			}
		}
		return $form_fields;
	}

	function xmldoc_set_xml_document() {
		global $post_ID;
		$post_ID = $_POST['post_id'];
		$xml_ID  = $_POST['xml_id'];
		$postUpdate = null;

		if ( isset($post_ID) && check_ajax_referer( "set_xml_document-$post_ID", '_ajax_nonce' ) && isset($xml_ID) ){
		
			update_post_meta( $post_ID, 'aa_tei_xml', $xml_ID );
			$postUpdate = $this->xmldoc_parse($post_ID);

		}
		echo json_encode( array(
						'html' 			=> $this->xmldoc_document_html( $xml_ID ),
						'postUpdate' 	=> $postUpdate
						)
		);
		die();
		
	}

}