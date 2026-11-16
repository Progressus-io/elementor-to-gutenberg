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

document.addEventListener( 'DOMContentLoaded', function () {
	document
		.querySelectorAll( '.blockshift-video-overlay' )
		.forEach( function ( wrapper ) {
			const image = wrapper.querySelector( ':scope > .wp-block-image' );
			const iframe = wrapper.querySelector( 'iframe' );
			if ( ! image || ! iframe ) {
				return;
			}

			wrapper.classList.add( 'is-ready' );
			image.addEventListener( 'click', function () {
				const src = new URL( iframe.src, window.location.href );
				src.searchParams.set( 'autoplay', '1' );
				iframe.allow = 'autoplay; fullscreen; picture-in-picture';
				iframe.src = src.toString();
				wrapper.classList.add( 'is-playing' );
			} );
		} );
} );
