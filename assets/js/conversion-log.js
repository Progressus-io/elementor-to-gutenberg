/**
 * Conversion Log admin screen.
 *
 * Adds copy-to-clipboard behaviour for the diagnostic JSONL log viewer.
 * Translated button labels are provided via wp_localize_script as
 * window.metgConversionLog.
 */
( function () {
	var data = window.metgConversionLog || {};
	var pre = document.getElementById( 'metg-jsonl-log' );
	var btn = document.getElementById( 'metg-jsonl-copy' );

	if ( ! pre || ! btn ) {
		return;
	}

	var label = btn.querySelector( 'span' );

	btn.addEventListener( 'click', function () {
		var text = pre.textContent;
		var done = function () {
			if ( label ) {
				label.textContent = data.copied || 'Copied';
			}
			setTimeout( function () {
				if ( label ) {
					label.textContent = data.copy || 'Copy';
				}
			}, 1500 );
		};

		if ( navigator.clipboard && navigator.clipboard.writeText ) {
			navigator.clipboard.writeText( text ).then( done, done );
		} else {
			done();
		}
	} );
} )();
