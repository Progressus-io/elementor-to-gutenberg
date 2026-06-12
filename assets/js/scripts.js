/* global jQuery */
jQuery( document ).ready( function ( $ ) {
	const $buttons = $( '.gb-tab-title' );
	const $contents = $( '.gb-tab-content' );

	$buttons.on( 'click', function () {
		const $btn = $( this );

		$buttons.removeClass( 'active' );
		$btn.addClass( 'active' );

		$contents.each( function () {
			const $content = $( this );
			if ( $content.attr( 'id' ) === $btn.data( 'tab' ) ) {
				$content.show();
			} else {
				$content.hide();
			}
		} );
	} );

	if ( $buttons.length ) {
		$buttons.first().addClass( 'active' );
	}
} );
