WPSetXMLDocHTML = function(html){
	jQuery('.inside', '#xml-document').html(html);
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