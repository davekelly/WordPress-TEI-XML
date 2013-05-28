WPSetXMLDocHTML = function(html){
	jQuery('.inside', '#xml-document').html(html);
};


WPSetEditorContent = function( html ){
	jQuery('#content').html(html);
}


WPSetTitleContent = function( text ){
	jQuery('#title-prompt-text').addClass('screen-reader-text');
	jQuery('#title').val(text);
}