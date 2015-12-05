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

		add_shortcode( 'tei_download', array($this, 'display_xml_download_info') );
	}



	function display_xml_download_info($content)
	{
		global $post;

		$teiFile = $this->get_xml_file($post->ID, $asUrl = true);
		$html = '';

		if( !is_wp_error( $teiFile)){
			$html = '<div class="tei-file-download">
						<h4 style="margin: 10px 0; padding: 0;">Download</h4>
						<a class="tei-dowload-link" href="'. $teiFile . '">
							Download the TEI Encoded File</a>
						<p>(NOTE: could also parse TEI header info and output it?)</p>
					</div> <!-- .tei-file-download -->
					';	
			$content = $html . $content;	
		}

		return $content;

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
		

		// Nonce & $post_ID ok
		if ( ( isset($post_ID) && is_numeric($post_ID) ) && check_ajax_referer( "aa-parse-xml-nonce-$post_ID", '_ajax_nonce' ) ){		

			// have xslt installed?		
			if ( !class_exists( 'XSLTProcessor' ) || !class_exists( 'DOMDocument' ) ){
				echo json_encode( array(
							'success' 	=> false,
							'message'	=> 'XML and XSLT processing is not supported by your PHP installation. Please install <a href="http://www.php.net/manual/en/book.xsl.php">the PHP XSL module</a>'
						)
				);
				die();
			}
				
			$teiFile = $this->get_xml_file($post_ID);
			// array comes back if there's an error message
			// 
			if( is_wp_error( $teiFile)){
				echo json_encode(array(
					'success' => false, 
					'message' => $teiFile->get_error_message()
					)
				);
				die();
			}

			
			// Check for a user defined xsl file
			$stylesheet = $this->get_xsl_file($post_ID);
			if( is_wp_error( $stylesheet ) ){
				echo json_encode( array(
							'success' 	=> false,
							'message'	=> $stylesheet->get_error_message() 
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

	protected function get_xml_file($post_ID, $asUrl = false)
	{
		// have an xml file name to use?
		if ( !$xml_ID = get_post_meta( $post_ID, 'aa_tei_xml', true ) ){
			return new WP_Error('filenotfound', __('XML document not set.'));
		}

		$file = get_post_meta( $xml_ID, '_wp_attached_file', true);
		
		$teiFile = get_attached_file($xml_ID);
		
		// does the file exist?
		if ( !file_exists( $teiFile ) ){
			return new WP_Error('filenotfound', __('XML file not found.'));
		}
		
		if( $asUrl){
			$dir = wp_upload_dir();

			$teiFile = $dir['baseurl'] . '/' . $file;
		}

		return $teiFile;

	}

	protected function get_xsl_file( $post_ID )
	{
		$xsl_ID 	= get_post_meta( $post_ID, 'aa_tei_xsl', true);

		// If there's a document-specific XSLT set...
		if ( isset( $xsl_ID ) && is_numeric( is_int($xsl_ID ))) {
			// if it's an int, it's an attachment ID.
			//$stylesheet = get_post_meta( $xsl_ID, '_wp_attached_file', true);
			$stylesheet = get_attached_file($xsl_ID);
		 
		} else {
			$stylesheet = AATEIXML_PATH . "xsl/default.xsl";
		}

		// does an xsl file exist?
		if ( !file_exists( $stylesheet ) ){
			return new WP_Error('filenotfound', __('XSL file not found.'));
		}

		return $stylesheet;

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

	/**
	 * Set up and echo the meta box on the post page
	 * 
	 * @return Void
	 */
	function xmldoc_meta_box() {
		global $post;	
		$xml 	= get_post_meta( $post->ID, 'aa_tei_xml', true );
		$xslt 	= get_post_meta( $post->ID, 'aa_tei_xsl', true );

		echo $this->xmldoc_document_html( $xml );
		echo $this->xsldoc_document_html( $xslt );
		echo $this->xmldoc_get_parse_button_html($post->ID);
		echo $this->get_meta_box_instruction();
	}

	protected function get_meta_box_instruction()
	{
		return '<div>
					<p>
						<strong>Note</strong>: You can use the shortcode [tei_download] in the page
				 		to display an option to download the source xml file
			 		</p>
		 		</div>';
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

	/**
	 * Generate HTML to handle xml upload / choice
	 * @param  [type] $xml_ID [description]
	 * @return [type]         [description]
	 */
	protected function xmldoc_document_html( $xml_ID ) {
		global $content_width, $_wp_additional_image_sizes, $post_ID;

		$set_thumbnail_link = '<div id="xml-file-holder"><p class="hide-if-no-js"><a title="' . esc_attr( 'Set XML document' ) . '" href="' . esc_url( get_upload_iframe_src('media') ) . '" id="set-xml-document" class="thickbox">%s</a></p>';
		$content = sprintf($set_thumbnail_link, esc_html( 'Set XML document' ));

		$file = get_post_meta( $xml_ID, '_wp_attached_file', true);
		$abspath = get_attached_file($xml_ID);
		if ( $file && file_exists( $abspath ) ) {
			$content .= '<p><img src="' . admin_url('images/yes.png') . '" alt="XML document specified"/> XML document specified: <a href="' . esc_html( wp_get_attachment_url( $xml_ID ) ) . '">' . esc_html( get_the_title($xml_ID) ) . '</a></p>';
    } else {
			$content .= '<p><img src="' . admin_url('images/no.png') . '" alt="XML document NOT specified"/> XML document NOT specified: ' . esc_html( get_the_title($xml_ID) ) . '</p>';
    }
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
		$abspath = get_attached_file($xsl_ID);
		if ( $file && file_exists( $abspath ) )
			$content .= '<p><img src="' . admin_url('images/yes.png') . '" alt="XSLT document specified"/>XSLT document specified: <a href="' . esc_html( wp_get_attachment_url( $xsl_ID ) ) . '">' . esc_html( get_the_title($xsl_ID) ) . '</a></p>';

		$content .= '</div> <!-- #xsl-file-holder -->';
		return $content;

	}

	/**
	 * Generate html for metabox, both for straight
	 * display and as an ajax response
	 * 
	 * @param  Int $xFileId ID of an attached XML/XSL file
	 * @param  String $type : Type of section we're dealing with $type {xml|xsl}
	 * @return String $html
	 */
	private function aa_doc_html($xFileId, $type)
	{
		/**
		 * @todo  refactor the two functions above and extract the common 
		 *        parts to here...
		 */
	}
	

	// XML DOCUMENT MEDIA ITEM MODS
	function xmldoc_media_form_enqueue( $page ) {
		if ( 'media-upload-popup' != $page )
			return;
		$src = AATEIXML_FRONT_URL . 'frontend/js/aa-tei-document.js';
		wp_enqueue_script( 'set-xml-document', $src, array( 'jquery' ) , '1.0', true );
	}


	/**
	 * Add link to bottom of media upload lightbox to
	 * "Use XML/XSL file"
	 * 
	 * @param  Mixed $form_fields [description]
	 * @param  Post $post        
	 * @return Mixed $form_fields
	 */
	function xmldoc_media_form_fields($form_fields, $post) {

		if ( $post->post_mime_type == 'application/xml' || $post->post_mime_type == 'application/xsl') {

			$attachment_id = $post->ID;
			$calling_post_id  = $this->get_calling_post_id($post);


			if( $post->post_mime_type == 'application/xml'){
				if ( $calling_post_id ) {
					$ajax_nonce = wp_create_nonce( "set_xml_document-$calling_post_id" );
					$form_fields['buttons'] = array( 'tr' => "\t\t<tr class='submit'><td></td><td class='savesend'><a class='wp-xml-document' id='wp-xml-document-{$attachment_id}' href='#' onclick='WPSetAsXMLDoc(\"$attachment_id\", \"$ajax_nonce\");return false;'>" . esc_html( "Use as XML document" ) . "</a></td></tr>\n" );
				}
			}else{
				// xsl doc...
				if ( $calling_post_id ) {
					$ajax_nonce = wp_create_nonce( "set_xsl_document-$calling_post_id" );
					$form_fields['buttons'] = array( 'tr' => "\t\t<tr class='submit'><td></td><td class='savesend'><a class='wp-xsl-document' id='wp-xsl-document-{$attachment_id}' href='#' onclick='WPSetAsXSLDoc(\"$attachment_id\", \"$ajax_nonce\");return false;'>" . esc_html( "Use as XSL document" ) . "</a></td></tr>\n" );
				}
			}
		}
		return $form_fields;
	} 


	/**
	 * Return the id of the post calling the media
	 * upload lightbox
	 * 
	 * @param  Post $post 
	 * @return Int $calling_post_id
	 */
	private function get_calling_post_id($post)
	{

		$calling_post_id = 0;
			
		if ( isset( $_GET['post_id'] ) ){
			$calling_post_id = absint( $_GET['post_id'] );
		}
		elseif ( isset( $_POST ) && count( $_POST ) ){ // Like for async-upload where $_GET['post_id'] isn't set{
			$calling_post_id = $post->post_parent;
		}

		return $calling_post_id;
	}


	/**
	 * Ajax function to set the xml file attached to this
	 * post
	 * 
	 * @return String $json : json encoded array of ['html' => 'html content to inject into 
	 * the page']
	 */
	function xmldoc_set_xml_document() 
	{
		
		$xml_ID  = $_POST['xml_id'];
		$postUpdate = $this->xFileUpdate($xml_ID, 'aa_tei_xml');

		echo json_encode( array(
				'html' 	=> $this->xmldoc_document_html( $xml_ID )
			)
		);
		die();
		
	}

	/**
	 * Ajax function to set the xsl file attached to this
	 * post
	 * 
	 * @return String $json : json encoded array of ['html' => 'html content to inject into 
	 * the page']
	 */
	function xmldoc_set_xsl_document() 
	{
		
		$xsl_ID  = $_POST['xsl_id'];
		$postUpdate = $this->xFileUpdate($xsl_ID, 'aa_tei_xsl');

		echo json_encode( array(
				'html' 	=> $this->xsldoc_document_html( $xsl_ID )
			)
		);
		die();
		
	}

	/**
	 * Set a given file attachment ID as meta info for a 
	 * post. Called during an Ajax request => checks nonce
	 * and uses $_POST data
	 * 
	 * @param  Int $xFile_ID - ID of the attachment
	 * @param  String $meta_update_name meta_name to use
	 * @return Bool $success
	 */
	private function xFileUpdate( $xFile_ID, $meta_update_name)
	{
		$post_ID = $_POST['post_id'];

		$nonceCheck = false;
		if( $meta_update_name === 'aa_tei_xsl'){
			$nonceCheck = check_ajax_referer( "set_xsl_document-$post_ID", '_ajax_nonce');
		}elseif( $meta_update_name === 'aa_tei_xml'){
			$nonceCheck = check_ajax_referer( "set_xml_document-$post_ID", '_ajax_nonce');
		}

		if ( isset($post_ID) && $nonceCheck && isset($xFile_ID) ){
			update_post_meta( $post_ID, $meta_update_name, $xFile_ID );
			return true;
		}
		return false;
	}

}