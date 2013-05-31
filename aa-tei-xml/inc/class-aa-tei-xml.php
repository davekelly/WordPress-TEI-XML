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

		add_action( 'wp_ajax_set-xsl-document', array($this, 'xmldoc_set_xsl_document') );

		add_action( 'wp_ajax_parse-xml-document', array($this, 'xmldoc_parse') );
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
	function xmldoc_parse() {
		global $post;

		$post_ID = $_POST['post_id'];
		

		if ( ( isset($post_ID) && is_numeric($post_ID) ) && check_ajax_referer( "aa-parse-xml-nonce-$post_ID", '_ajax_nonce' ) ){		

		

			if ( !class_exists( 'XSLTProcessor' ) || !class_exists( 'DOMDocument' ) ){
				echo json_encode( array(
							'success' 	=> false,
							'message'	=> 'XML and XSLT processing is not supported by your PHP installation. Please install <a href="http://www.php.net/manual/en/book.xsl.php">the PHP XSL module</a>'
						)
				);
				die();
			}
				
			if ( !$xml_ID = get_post_meta( $post_ID, 'aa_tei_xml', true ) ){
				echo json_encode( array(
							'success' 	=> false,
							'message'	=> 'XML document not set.'
						)
				);
				die();
			}

			
			$teiFile = get_option('upload_path') .'/'. get_post_meta( $xml_ID, '_wp_attached_file', true);
			//var_dump($teiFile);
			//die();
			if ( !file_exists( $teiFile ) ){
				
				echo json_encode( array(
							'success' 	=> false,
							'message'	=> 'XML file not found.'
						)
				);
				die();
			}

			
			// Check for a user defined xsl file
			$xslt_ID = get_post_meta( $post_ID, 'aa_tei_xsl', true);
			
			// If there's a document-specific XSLT set...
			if ( isset( $xslt_ID ) && is_numeric( is_int($xslt_ID ))) {
				// if it's an int, it's an attachment ID.
				$stylesheet = get_post_meta( $xslt_ID, '_wp_attached_file', true);
				$stylesheet = get_option('upload_path') . '/' . $xslt;
			 
			} else {
				$stylesheet = AATEIXML_PATH . "xsl/default.xsl";
			}


			if ( !file_exists( $stylesheet ) ){
				echo json_encode( array(
							'success' 	=> false,
							'message'	=> 'XSLT stylesheet not found.' . $stylesheet 
						)
				);
				die();
			}
				
			

			$xp = new XsltProcessor();
			// create a DOM document and load the XSL stylesheet
			$xsl = new DomDocument;

			// import the XSL styelsheet into the XSLT process
			$xsl->load($stylesheet);
			$xp->importStylesheet($xsl);
			
			//set query parameter to pass into stylesheet
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
					
					
					echo json_encode( $postUpdate);
					die();
					
				}
			} catch (Exception $e){
				
				echo json_encode( array(
							'success' 	=> false,
							'message'	=> $e->getMessage()
						)
				);
				die();
			} 

		}else{   // ajax nonce check fail
			echo json_encode( array(
						'success' 	=> false,
						'message'	=> 'Access not allowed.'
					)
			);
			die();
		}

		// var_dump($html);
		// die();
	}

	// XML DOCS EDIT SCREEN

	// Add XML document meta box
	function xmldoc_register_meta_box() {
		global $post_type;
		// if ( post_type_supports( $post_type, 'xmldoc' ) ) {
			add_meta_box( 'xml-document', 'TEI Document Upload', array($this, 'xmldoc_meta_box'), $post_type, 'normal', 'core' );
			add_thickbox();
			wp_enqueue_script('media-upload');
			$src = AATEIXML_FRONT_URL . 'frontend/js/aa-tei-admin.js';
			wp_enqueue_script( 'xml-document-admin', $src, array( 'jquery' ) , '1.0', true );
		// }
	}

	function xmldoc_meta_box() {
		global $post;	
		$xml = get_post_meta( $post->ID, 'aa_tei_xml', true );
		$xslt = get_post_meta( $post->ID, 'aa_tei_xsl', true );

		echo $this->xmldoc_document_html( $xml );
		echo $this->xsldoc_document_html( $xslt );
		echo $this->xmldoc_get_parse_button_html($post->ID);
		// echo $this->xmldoc_parse();
	}

	protected function xmldoc_get_parse_button_html($post_ID)
	{
		$ajax_nonce = wp_create_nonce( "aa-parse-xml-nonce-$post_ID" );
		return '<div class="hide-if-no-js">
					<a href="#" class="button button-primary button-large" id="aa-parse-xml-doc" onclick=\'WPParseXmlDoc("'. $post_ID .'", "' . $ajax_nonce . '");return false;\'>
						Add XML Document Content to Editor
					</a>
				</div>';
	}

	protected function xmldoc_document_html( $xml_ID ) {
		global $content_width, $_wp_additional_image_sizes, $post_ID;

		$set_thumbnail_link = '<div id="xml-file-holder"><p class="hide-if-no-js"><a title="' . esc_attr( 'Set XML document' ) . '" href="' . esc_url( get_upload_iframe_src('media') ) . '" id="set-xml-document" class="thickbox">%s</a></p>';
		$content = sprintf($set_thumbnail_link, esc_html( 'Set XML document' ));

		$file = get_post_meta( $xml_ID, '_wp_attached_file', true);
		$abspath = get_option('upload_path') . '/' . $file;
		if ( $file && file_exists( $abspath ) )
			$content .= '<p><img src="' . admin_url('images/yes.png') . '" alt="XML document specified"/> XML document specified: <a href="' . esc_html( wp_get_attachment_url( $xml_ID ) ) . '">' . esc_html( get_the_title($xml_ID) ) . '</a></p>';

		$content .= '</div> <!-- #xml-file-holder -->';
		return $content;
	}

	/**
	 * Generate HTML to handle xsl upload / choice
	 * @param  [type] $xsl_ID [description]
	 * @return [type]         [description]
	 */
	protected function xsldoc_document_html( $xsl_ID )
	{
		global $content_width, $_wp_additional_image_sizes, $post_ID;

		$set_thumbnail_link = '<div id="xsl-file-holder"><p class="hide-if-no-js"><a title="' . esc_attr( 'Set XSLT document' ) . '" href="' . esc_url( get_upload_iframe_src('media')) . '" id="set-xsl-document" class="thickbox">%s</a></p>';
		$content = sprintf($set_thumbnail_link, esc_html( 'Set XSL document' ));
		$content .= '<span>[Optional: Default xsl file will be used if none is submitted]</span>';

		$file = get_post_meta( $xsl_ID, '_wp_attached_file', true);
		$abspath = get_option('upload_path') . '/' . $file;
		if ( $file && file_exists( $abspath ) )
			$content .= '<p><img src="' . admin_url('images/yes.png') . '" alt="XSLT document specified"/>XSLT document specified: <a href="' . esc_html( wp_get_attachment_url( $xsl_ID ) ) . '">' . esc_html( get_the_title($xsl_ID) ) . '</a></p>';

		$content .= '</div> <!-- #xsl-file-holder -->';
		return $content;

	}

	// XML DOCUMENT MEDIA ITEM MODS
	function xmldoc_media_form_enqueue( $page ) {
		if ( 'media-upload-popup' != $page )
			return;
		$src = AATEIXML_FRONT_URL . 'frontend/js/aa-tei-document.js';
		wp_enqueue_script( 'set-xml-document', $src, array( 'jquery' ) , '1.0', true );
	}

	function xmldoc_media_form_fields($form_fields, $post) {
		if ( $post->post_mime_type == 'application/xml' ) {
			$attachment_id = $post->ID;
			$calling_post_id = 0;
			

			if ( isset( $_GET['post_id'] ) ){
				$calling_post_id = absint( $_GET['post_id'] );
			}
			elseif ( isset( $_POST ) && count( $_POST ) ){ // Like for async-upload where $_GET['post_id'] isn't set{
				$calling_post_id = $post->post_parent;
			}
			if ( $calling_post_id ) {

				$ajax_nonce = wp_create_nonce( "set_xml_document-$calling_post_id" );
				$form_fields['buttons'] = array( 'tr' => "\t\t<tr class='submit'><td></td><td class='savesend'><a class='wp-xml-document' id='wp-xml-document-{$attachment_id}' href='#' onclick='WPSetAsXMLDoc(\"$attachment_id\", \"$ajax_nonce\");return false;'>" . esc_html( "Use as XML document" ) . "</a></td></tr>\n" );
			}
		}elseif( $post->post_mime_type = 'appication/xsl'){
			$attachment_id = $post->ID;
			$calling_post_id = 0;
			

			if ( isset( $_GET['post_id'] ) ){
				$calling_post_id = absint( $_GET['post_id'] );
			}
			elseif ( isset( $_POST ) && count( $_POST ) ){ // Like for async-upload where $_GET['post_id'] isn't set{
				$calling_post_id = $post->post_parent;
			}
			if ( $calling_post_id ) {
				
				$ajax_nonce = wp_create_nonce( "set_xsl_document-$calling_post_id" );
				$form_fields['buttons'] = array( 'tr' => "\t\t<tr class='submit'><td></td><td class='savesend'><a class='wp-xsl-document' id='wp-xsl-document-{$attachment_id}' href='#' onclick='WPSetAsXSLDoc(\"$attachment_id\", \"$ajax_nonce\");return false;'>" . esc_html( "Use as XSL document" ) . "</a></td></tr>\n" );
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
		}
		echo json_encode( array(
							'html' 			=> $this->xmldoc_document_html( $xml_ID )
						)
		);
		die();
		
	}

	function xmldoc_set_xsl_document() {
		global $post_ID;
		$post_ID = $_POST['post_id'];
		$xsl_ID  = $_POST['xsl_id'];
		$postUpdate = null;

		if ( isset($post_ID) && check_ajax_referer( "set_xsl_document-$post_ID", '_ajax_nonce' ) && isset($xsl_ID) ){
			update_post_meta( $post_ID, 'aa_tei_xsl', $xsl_ID );
		}
		echo json_encode( array(
							'html' 			=> $this->xsldoc_document_html( $xsl_ID )
						)
		);
		die();
		
	}

}