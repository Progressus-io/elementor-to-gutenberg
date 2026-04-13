/* global ele2gbAiImprove */
( function ( window, document ) {
	'use strict';

	if ( ! window.ele2gbAiImprove ) {
		return;
	}

	var config = window.ele2gbAiImprove;
	var form   = document.getElementById( 'ele2gb-ai-improve-form' );
	var loader = document.getElementById( 'ele2gb-ai-loader' );
	var btn    = document.getElementById( 'ele2gb_auto_improve_submit' );

	if ( ! form || ! loader || ! btn ) {
		return;
	}

	form.addEventListener( 'submit', function () {
		btn.disabled = true;
		btn.value    = config.processingLabel;
		loader.removeAttribute( 'hidden' );
	} );

} )( window, document );
