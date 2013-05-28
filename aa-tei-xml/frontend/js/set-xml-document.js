


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
				win.WPSetXMLDocHTML(str.html);
				jQuery('#title').val( str.postUpdate.post_title);
				if( jQuery('#content').length !== 1){
					console.log('dont have a #content box');
				}else{
					console.log('hav a content box');
					// jQuery('#content').text( str.postUpdate.post_content);	
				}
				// jQuery('#content').html( str.postUpdate.post_content);
				win.WPSetEditorContent( str.postUpdate.post_content);
				win.WPSetTitleContent( str.postUpdate.post_title);

			}
		}
	);
}

