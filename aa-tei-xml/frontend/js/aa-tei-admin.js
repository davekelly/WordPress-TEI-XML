// update interface links after upload 
// lightbox gets clicked
WPSetDocHTML = function(html, typeLocation){
	switch(typeLocation){
		case 'xml':
			jQuery('.inside #xml-file-holder').html(html);
			break;
		case 'xsl':
			jQuery('.inside #xsl-file-holder').html(html);
			break;
		default:
	}
};


// update the editor pane content
WPSetEditorContent = function( html ){
	if( tinyMCE.activeEditor ){
		tinyMCE.get('content').setContent( html );
	}else{
		jQuery('#content').html(html);	
	}
}

// Update title box content
WPSetTitleContent = function( text ){
	jQuery('#title-prompt-text').addClass('screen-reader-text');
	jQuery('#title').val(text);
}

/**
 * Handle button click to manage xml->xsl transform
 * 
 * @param {int} id  post_id
 * @param {[string]} nonce 
 */
function WPParseXmlDoc(id, nonce)
{
	jQuery.post(ajaxurl, {
		action:"parse-xml-document", post_id: id, _ajax_nonce: nonce, cookie: encodeURIComponent(document.cookie)
		}, function(response){
			var win = window.dialogArguments || opener || parent || top;
			response = JSON.parse(response);

			if ( response.success == false ) {
				alert( response.message );
			} else {
				// update screen content...
				if( typeof response.post_content !== "undefined"){
					win.WPSetEditorContent( response.post_content);
				}
				if( typeof response.post_title !== "undefined"){
					win.WPSetTitleContent( response.post_title);
				}

			}
		}
	);
}

