/* global ele2gbAiImprove */
( function ( window, document ) {
	'use strict';

	if ( ! window.ele2gbAiImprove ) {
		return;
	}

	var config     = window.ele2gbAiImprove;
	var loader     = document.getElementById( 'ele2gb-ai-loader' );
	var loaderTitle = loader ? loader.querySelector( '.ele2gb-ai-loader-title' ) : null;

	// ── Round 1: Improve with AI ──────────────────────────────────────────────
	var improveForm = document.getElementById( 'ele2gb-ai-improve-form' );
	var improveBtn  = document.getElementById( 'ele2gb_auto_improve_submit' );

	if ( improveForm && improveBtn && loader ) {
		improveForm.addEventListener( 'submit', function () {
			improveBtn.disabled = true;
			improveBtn.value    = config.processingLabel;
			if ( loaderTitle ) {
				loaderTitle.textContent = config.improvingLabel || loaderTitle.textContent;
			}
			loader.removeAttribute( 'hidden' );
		} );
	}

	// ── Round 2+: Refine with AI ──────────────────────────────────────────────
	var refineForm  = document.getElementById( 'ele2gb-ai-refine-form' );
	var refineBtn   = document.getElementById( 'ele2gb_refine_submit' );
	var focusInput  = document.getElementById( 'ele2gb-focus-instruction' );

	if ( refineForm && refineBtn && loader ) {
		refineForm.addEventListener( 'submit', function () {
			refineBtn.disabled = true;
			refineBtn.value    = config.processingLabel;
			if ( loaderTitle ) {
				loaderTitle.textContent = config.refiningLabel || loaderTitle.textContent;
			}
			loader.removeAttribute( 'hidden' );
		} );
	}

	// ── Mobile improvement: separate AI pass on mobile screenshots ───────────
	var mobileForm = document.getElementById( 'ele2gb-ai-mobile-improve-form' );
	var mobileBtn  = document.getElementById( 'ele2gb_mobile_improve_submit' );

	if ( mobileForm && mobileBtn && loader ) {
		mobileForm.addEventListener( 'submit', function () {
			mobileBtn.disabled = true;
			mobileBtn.value    = config.processingLabel;
			if ( loaderTitle ) {
				loaderTitle.textContent = config.mobileLabel || config.improvingLabel || loaderTitle.textContent;
			}
			loader.removeAttribute( 'hidden' );
		} );
	}

	// ── Suggestion chips ──────────────────────────────────────────────────────
	var chips = document.querySelectorAll( '.ele2gb-suggestion-chip' );

	chips.forEach( function ( chip ) {
		chip.addEventListener( 'click', function () {
			if ( ! focusInput ) {
				return;
			}

			var suggestion = chip.getAttribute( 'data-suggestion' ) || chip.textContent.trim();
			var current    = focusInput.value.trim();

			if ( '' === current ) {
				focusInput.value = suggestion;
			} else {
				focusInput.value = current + '. ' + suggestion;
			}

			focusInput.focus();
			chip.classList.add( 'ele2gb-suggestion-chip--active' );
		} );
	} );

	// ── Screenshot tabs ────────────────────────────────────────────────────────
	var tabBtns   = document.querySelectorAll( '.etg-ai-tab' );
	var tabPanels = document.querySelectorAll( '.etg-ai-tab-panel' );

	tabBtns.forEach( function ( btn ) {
		btn.addEventListener( 'click', function () {
			var target = btn.getAttribute( 'data-tab' );

			tabBtns.forEach( function ( b ) {
				b.classList.toggle( 'etg-ai-tab--active', b === btn );
				b.setAttribute( 'aria-selected', b === btn ? 'true' : 'false' );
			} );

			tabPanels.forEach( function ( panel ) {
				if ( panel.getAttribute( 'data-panel' ) === target ) {
					panel.removeAttribute( 'hidden' );
				} else {
					panel.setAttribute( 'hidden', '' );
				}
			} );
		} );
	} );

	// ── Lightbox ───────────────────────────────────────────────────────────────
	var lightbox      = document.getElementById( 'etg-lightbox' );
	var lbOverlay     = document.getElementById( 'etg-lightbox-overlay' );
	var lbClose       = document.getElementById( 'etg-lightbox-close' );
	var lbOpenLink    = document.getElementById( 'etg-lightbox-open' );
	var lbImages      = document.getElementById( 'etg-lightbox-images' );

	function openLightbox( urls ) {
		if ( ! lightbox || ! lbImages ) {
			return;
		}

		lbImages.innerHTML = '';

		urls.forEach( function ( url ) {
			var img = document.createElement( 'img' );
			img.src = url;
			img.alt = '';
			lbImages.appendChild( img );
		} );

		if ( lbOpenLink ) {
			lbOpenLink.href = urls[0] || '#';
		}

		lightbox.removeAttribute( 'hidden' );
		document.body.style.overflow = 'hidden';

		if ( lbClose ) {
			lbClose.focus();
		}
	}

	function closeLightbox() {
		if ( ! lightbox ) {
			return;
		}
		lightbox.setAttribute( 'hidden', '' );
		document.body.style.overflow = '';
		lbImages.innerHTML = '';
	}

	document.querySelectorAll( '.etg-screenshot-thumb-wrap' ).forEach( function ( wrap ) {
		wrap.addEventListener( 'click', function ( e ) {
			if ( e.target.tagName === 'A' ) {
				return;
			}
			var raw  = wrap.getAttribute( 'data-urls' ) || '[]';
			var urls = [];
			try { urls = JSON.parse( raw ); } catch ( _ ) {}
			if ( urls.length ) {
				openLightbox( urls );
			}
		} );
	} );

	if ( lbOverlay ) {
		lbOverlay.addEventListener( 'click', closeLightbox );
	}

	if ( lbClose ) {
		lbClose.addEventListener( 'click', closeLightbox );
	}

	document.addEventListener( 'keydown', function ( e ) {
		if ( e.key === 'Escape' && lightbox && ! lightbox.hasAttribute( 'hidden' ) ) {
			closeLightbox();
		}
	} );

} )( window, document );
