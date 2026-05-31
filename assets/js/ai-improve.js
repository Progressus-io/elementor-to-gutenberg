( function ( window, document ) {
	'use strict';

	if ( ! window.ele2gbAiImprove ) {
		return;
	}

	const config = window.ele2gbAiImprove;
	const loader = document.getElementById( 'ele2gb-ai-loader' );
	const loaderTitle = loader
		? loader.querySelector( '.ele2gb-ai-loader-title' )
		: null;

	// ── Round 1: Improve with AI ──────────────────────────────────────────────
	const improveForm = document.getElementById( 'ele2gb-ai-improve-form' );
	const improveBtn = document.getElementById( 'ele2gb_auto_improve_submit' );

	if ( improveForm && improveBtn && loader ) {
		improveForm.addEventListener( 'submit', function () {
			improveBtn.disabled = true;
			improveBtn.value = config.processingLabel;
			if ( loaderTitle ) {
				loaderTitle.textContent =
					config.improvingLabel || loaderTitle.textContent;
			}
			loader.removeAttribute( 'hidden' );
		} );
	}

	// ── Round 2+: Refine with AI ──────────────────────────────────────────────
	const refineForm = document.getElementById( 'ele2gb-ai-refine-form' );
	const refineBtn = document.getElementById( 'ele2gb_refine_submit' );
	const focusInput = document.getElementById( 'ele2gb-focus-instruction' );

	if ( refineForm && refineBtn && loader ) {
		refineForm.addEventListener( 'submit', function () {
			refineBtn.disabled = true;
			refineBtn.value = config.processingLabel;
			if ( loaderTitle ) {
				loaderTitle.textContent =
					config.refiningLabel || loaderTitle.textContent;
			}
			loader.removeAttribute( 'hidden' );
		} );
	}

	// ── Mobile improvement: separate AI pass on mobile screenshots ───────────
	const mobileForm = document.getElementById(
		'ele2gb-ai-mobile-improve-form'
	);
	const mobileBtn = document.getElementById( 'ele2gb_mobile_improve_submit' );

	if ( mobileForm && mobileBtn && loader ) {
		mobileForm.addEventListener( 'submit', function () {
			mobileBtn.disabled = true;
			mobileBtn.value = config.processingLabel;
			if ( loaderTitle ) {
				loaderTitle.textContent =
					config.mobileLabel ||
					config.improvingLabel ||
					loaderTitle.textContent;
			}
			loader.removeAttribute( 'hidden' );
		} );
	}

	// ── Suggestion chips ──────────────────────────────────────────────────────
	const chips = document.querySelectorAll( '.ele2gb-suggestion-chip' );

	chips.forEach( function ( chip ) {
		chip.addEventListener( 'click', function () {
			if ( ! focusInput ) {
				return;
			}

			const suggestion =
				chip.getAttribute( 'data-suggestion' ) ||
				chip.textContent.trim();
			const current = focusInput.value.trim();

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
	const tabBtns = document.querySelectorAll( '.etg-ai-tab' );
	const tabPanels = document.querySelectorAll( '.etg-ai-tab-panel' );

	tabBtns.forEach( function ( btn ) {
		btn.addEventListener( 'click', function () {
			const target = btn.getAttribute( 'data-tab' );

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
	const lightbox = document.getElementById( 'etg-lightbox' );
	const lbOverlay = document.getElementById( 'etg-lightbox-overlay' );
	const lbClose = document.getElementById( 'etg-lightbox-close' );
	const lbOpenLink = document.getElementById( 'etg-lightbox-open' );
	const lbImages = document.getElementById( 'etg-lightbox-images' );

	function openLightbox( urls ) {
		if ( ! lightbox || ! lbImages ) {
			return;
		}

		lbImages.innerHTML = '';

		urls.forEach( function ( url ) {
			const img = document.createElement( 'img' );
			img.src = url;
			img.alt = '';
			lbImages.appendChild( img );
		} );

		if ( lbOpenLink ) {
			lbOpenLink.href = urls[ 0 ] || '#';
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

	document
		.querySelectorAll( '.etg-screenshot-thumb-wrap' )
		.forEach( function ( wrap ) {
			wrap.addEventListener( 'click', function ( e ) {
				if ( e.target.tagName === 'A' ) {
					return;
				}
				const raw = wrap.getAttribute( 'data-urls' ) || '[]';
				let urls = [];
				try {
					urls = JSON.parse( raw );
				} catch ( _ ) {}
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
		if (
			e.key === 'Escape' &&
			lightbox &&
			! lightbox.hasAttribute( 'hidden' )
		) {
			closeLightbox();
		}
	} );

	// ── Feedback modal ────────────────────────────────────────────────────────

	const feedbackBtn = document.getElementById( 'etg-ai-feedback-btn' );

	function openFeedbackModal() {
		const existing = document.getElementById(
			'etg-ai-improve-feedback-overlay'
		);
		if ( existing ) {
			existing.remove();
		}

		const overlay = document.createElement( 'div' );
		overlay.id = 'etg-ai-improve-feedback-overlay';
		overlay.style.cssText =
			'position:fixed;top:0;left:0;right:0;bottom:0;z-index:100000;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,0.55);padding:20px;box-sizing:border-box;';

		const modal = document.createElement( 'div' );
		modal.style.cssText =
			'background:#fff;border-radius:8px;padding:28px 32px;max-width:500px;width:100%;max-height:90vh;overflow-y:auto;box-shadow:0 4px 32px rgba(0,0,0,0.18);box-sizing:border-box;';

		const h2 = document.createElement( 'h2' );
		h2.style.cssText =
			'margin:0 0 20px;font-size:17px;font-weight:600;color:#1d2327;';
		h2.textContent = config.feedbackTitle || 'How did AI Enhancement go?';
		modal.appendChild( h2 );

		// Issue type
		const issueLbl = document.createElement( 'label' );
		issueLbl.style.cssText =
			'display:block;font-size:13px;font-weight:500;margin-bottom:6px;color:#1d2327;';
		issueLbl.textContent = config.feedbackIssue || 'Issue type';
		modal.appendChild( issueLbl );

		const issueSelect = document.createElement( 'select' );
		issueSelect.style.cssText =
			'width:100%;padding:6px 8px;border:1px solid #c3c4c7;border-radius:4px;font-size:13px;margin-bottom:16px;';
		[
			[ '', config.feedbackNoIssue || 'No issue' ],
			[ 'layout', config.feedbackLayout || 'Layout issues after AI' ],
			[ 'missing', config.feedbackMissing || 'Wrong or missing content' ],
			[ 'css', config.feedbackCss || 'CSS / styling problems' ],
			[ 'quality', config.feedbackQuality || 'AI output quality' ],
			[ 'other', config.feedbackOther || 'Other' ],
		].forEach( function ( pair ) {
			const opt = document.createElement( 'option' );
			opt.value = pair[ 0 ];
			opt.textContent = pair[ 1 ];
			issueSelect.appendChild( opt );
		} );
		modal.appendChild( issueSelect );

		// Issue detail (shown when issue selected)
		const detailWrap = document.createElement( 'div' );
		detailWrap.style.cssText = 'margin-bottom:16px;display:none;';
		const detailLbl = document.createElement( 'label' );
		detailLbl.style.cssText =
			'display:block;font-size:13px;font-weight:500;margin-bottom:6px;color:#1d2327;';
		detailLbl.textContent =
			config.feedbackIssueDetail || 'Describe the issue';
		detailWrap.appendChild( detailLbl );
		const detailTA = document.createElement( 'textarea' );
		detailTA.rows = 2;
		detailTA.maxLength = 500;
		detailTA.style.cssText =
			'width:100%;padding:6px 8px;border:1px solid #c3c4c7;border-radius:4px;font-size:13px;box-sizing:border-box;resize:vertical;';
		detailWrap.appendChild( detailTA );
		modal.appendChild( detailWrap );

		issueSelect.addEventListener( 'change', function () {
			detailWrap.style.display = issueSelect.value ? 'block' : 'none';
		} );

		// Notes
		const noteLbl = document.createElement( 'label' );
		noteLbl.style.cssText =
			'display:block;font-size:13px;font-weight:500;margin-bottom:6px;color:#1d2327;';
		noteLbl.textContent = config.feedbackNote || 'Additional notes';
		modal.appendChild( noteLbl );
		const noteTA = document.createElement( 'textarea' );
		noteTA.rows = 3;
		noteTA.maxLength = 2000;
		noteTA.style.cssText =
			'width:100%;padding:6px 8px;border:1px solid #c3c4c7;border-radius:4px;font-size:13px;box-sizing:border-box;resize:vertical;margin-bottom:16px;';
		modal.appendChild( noteTA );

		// Consent
		const consentLbl = document.createElement( 'label' );
		consentLbl.style.cssText =
			'display:flex;gap:8px;align-items:flex-start;margin-bottom:20px;cursor:pointer;';
		const consentCb = document.createElement( 'input' );
		consentCb.type = 'checkbox';
		consentCb.style.cssText = 'margin-top:3px;flex-shrink:0;';
		const consentSpan = document.createElement( 'span' );
		consentSpan.style.cssText =
			'font-size:12px;color:#50575e;line-height:1.5;';
		consentSpan.textContent =
			config.feedbackConsent ||
			'I consent to sending this anonymised AI enhancement report to the plugin developer for quality improvement.';
		consentLbl.appendChild( consentCb );
		consentLbl.appendChild( consentSpan );
		modal.appendChild( consentLbl );

		// Actions row
		const actRow = document.createElement( 'div' );
		actRow.style.cssText =
			'display:flex;gap:8px;align-items:center;flex-wrap:wrap;';
		const errSpan = document.createElement( 'span' );
		errSpan.style.cssText =
			'flex:1;font-size:12px;color:#d63638;min-width:0;';
		actRow.appendChild( errSpan );

		const cancelBtn = document.createElement( 'button' );
		cancelBtn.type = 'button';
		cancelBtn.className = 'button';
		cancelBtn.textContent = config.feedbackCancel || 'Cancel';
		cancelBtn.addEventListener( 'click', function () {
			overlay.remove();
		} );
		actRow.appendChild( cancelBtn );

		const submitBtn = document.createElement( 'button' );
		submitBtn.type = 'button';
		submitBtn.className = 'button button-primary';
		submitBtn.textContent = config.feedbackSubmit || 'Send Feedback';
		submitBtn.disabled = true;
		actRow.appendChild( submitBtn );
		modal.appendChild( actRow );

		consentCb.addEventListener( 'change', function () {
			submitBtn.disabled = ! consentCb.checked;
		} );

		submitBtn.addEventListener( 'click', function () {
			if ( ! consentCb.checked ) {
				return;
			}
			submitBtn.disabled = true;
			cancelBtn.disabled = true;
			submitBtn.textContent = config.feedbackSending || 'Sending…';
			errSpan.textContent = '';

			const fd = new FormData();
			fd.append( 'action', 'etg_submit_ai_enhancement_feedback' );
			fd.append( 'nonce', config.feedbackNonce || '' );
			fd.append( 'target_id', String( config.targetId || 0 ) );
			fd.append( 'source_id', String( config.sourceId || 0 ) );
			fd.append( 'consent_given', 'true' );
			fd.append( 'issue_type', issueSelect.value || '' );
			fd.append( 'issue_detail', detailTA.value || '' );
			fd.append( 'user_note', noteTA.value || '' );

			fetch( config.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				body: fd,
			} )
				.then( function ( r ) {
					return r.json();
				} )
				.then( function ( data ) {
					overlay.remove();
					if ( data.success ) {
						showFeedbackConfirm(
							config.feedbackSuccess ||
								'Thank you! Feedback submitted.'
						);
					} else {
						const msg =
							data.data && data.data.error
								? data.data.error
								: 'An unexpected error occurred.';
						showFeedbackConfirm( msg );
					}
				} )
				.catch( function () {
					overlay.remove();
					showFeedbackConfirm(
						config.feedbackSuccess ||
							'Thank you! Feedback submitted.'
					);
				} );
		} );

		overlay.appendChild( modal );
		document.body.appendChild( overlay );
		overlay.addEventListener( 'click', function ( e ) {
			if ( e.target === overlay ) {
				overlay.remove();
			}
		} );
	}

	function showFeedbackConfirm( message ) {
		const notice = document.createElement( 'div' );
		notice.style.cssText =
			'position:fixed;bottom:24px;right:24px;z-index:99999;padding:12px 20px;background:#00a32a;color:#fff;border-radius:6px;font-size:13px;box-shadow:0 2px 12px rgba(0,0,0,0.18);max-width:360px;';
		notice.textContent = message;
		document.body.appendChild( notice );
		setTimeout( function () {
			if ( notice.parentNode ) {
				notice.parentNode.removeChild( notice );
			}
		}, 7000 );
	}

	if ( feedbackBtn ) {
		feedbackBtn.addEventListener( 'click', openFeedbackModal );
	}
} )( window, document );
