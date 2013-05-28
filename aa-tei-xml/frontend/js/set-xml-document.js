


function WPSetAsXMLDoc(id, nonce){
	var $link = jQuery('a#wp-xml-document-' + id);

	$link.text( 'Saving...' );
	jQuery.post(ajaxurl, {
		action:"set-xml-document", post_id: post_id, xml_id: id, _ajax_nonce: nonce, cookie: encodeURIComponent(document.cookie)
		}, function(str){
			var win = window.dialogArguments || opener || parent || top;
			$link.text( 'Use as XML document' );
			
			if ( str == '0' ) {
				alert( 'Could not set as XML document. Try a different attachment.' );
			} else {
				str = JSON.parse(str);
				jQuery('a.wp-xml-document').show();
				$link.text( 'Done' );
				$link.fadeOut( 2000 );

				// update screen content...
				win.WPSetXMLDocHTML(str.html);
				if( str.postUpdate.post_content){
					win.WPSetEditorContent( str.postUpdate.post_content);
				}
				if( str.postUpdate.post_title ){
					win.WPSetTitleContent( str.postUpdate.post_title);
				}

			}
		}
	);
}

