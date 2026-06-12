( function () {
	'use strict';

	// ── helpers ───────────────────────────────────────────────────────────────

	function cel( tag, cls, txt ) {
		const el = document.createElement( tag );
		if ( cls ) {
			el.className = cls;
		}
		if ( txt !== undefined && txt !== null ) {
			el.textContent = String( txt );
		}
		return el;
	}

	function fmt( str ) {
		const args = Array.prototype.slice.call( arguments, 1 );
		return str.replace( /%(\d+)\$[sd]/g, function ( _, i ) {
			const v = args[ parseInt( i, 10 ) - 1 ];
			return v !== undefined ? String( v ) : '';
		} );
	}

	const AI_SVG =
		'<svg width="15" height="15" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M10 2l1.8 5.4H17l-4.4 3.2 1.7 5.1L10 13l-4.3 2.7 1.7-5.1L3 7.4h5.2z" fill="currentColor"/></svg>';
	const FEEDBACK_SVG =
		'<svg width="13" height="13" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M18 13a2 2 0 01-2 2H6l-4 4V4a2 2 0 012-2h12a2 2 0 012 2z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/></svg>';

	function makeActionPill( href, label, mod, targetBlank ) {
		const a = cel( 'a', 'ele2gb-action-pill ele2gb-action-pill--' + mod );
		a.href = href;
		if ( targetBlank ) {
			a.target = '_blank';
			a.rel = 'noopener';
		}
		a.appendChild( cel( 'span', 'ele2gb-action-pill-label', label ) );
		return a;
	}

	function makeIconBtn( iconHtml, label, mod ) {
		const btn = cel(
			'button',
			'ele2gb-action-pill ele2gb-action-pill--' + mod
		);
		btn.type = 'button';
		if ( iconHtml ) {
			const icon = document.createElement( 'span' );
			icon.className = 'ele2gb-action-pill-icon';
			icon.innerHTML = iconHtml;
			btn.appendChild( icon );
		}
		if ( label ) {
			btn.appendChild( cel( 'span', 'ele2gb-action-pill-label', label ) );
		}
		return btn;
	}

	function typeLabel( type ) {
		if ( ! type || type === 'page' ) {
			return '';
		}
		return type.charAt( 0 ).toUpperCase() + type.slice( 1 );
	}

	// ── app ───────────────────────────────────────────────────────────────────

	function EtgAiEnhancement( config ) {
		this.config = config;
		this.strings = config.strings || {};
		this.pages = config.pages || [];
		this.state = {
			selected: new Set(),
			aiImprove: null,
		};
		this.root = null;
	}

	EtgAiEnhancement.prototype.init = function () {
		this.root = document.getElementById( 'etg-ai-enhancement-app' );
		if ( ! this.root ) {
			return;
		}
		this.render();
	};

	EtgAiEnhancement.prototype.render = function () {
		if ( ! this.root ) {
			return;
		}
		this.root.innerHTML = '';
		if ( this.state.aiImprove ) {
			this.root.appendChild( this.renderAiImproveStep() );
		} else {
			this.root.appendChild( this.renderSelectionStep() );
		}
	};

	// ── stats bar ─────────────────────────────────────────────────────────────

	EtgAiEnhancement.prototype.renderStatsBar = function () {
		const pages = this.pages;
		const total = pages.length;
		const enhanced = pages.filter( function ( p ) {
			return !! p.lastImproved;
		} ).length;
		const s = this.strings;

		const bar = cel( 'div', 'ele2gb-enhancement-stats' );

		function tile( value, label, mod ) {
			const t = cel(
				'div',
				'ele2gb-enhancement-stat' +
					( mod ? ' ele2gb-enhancement-stat--' + mod : '' )
			);
			t.appendChild(
				cel( 'span', 'ele2gb-enhancement-stat-value', String( value ) )
			);
			t.appendChild(
				cel( 'span', 'ele2gb-enhancement-stat-label', label )
			);
			return t;
		}

		bar.appendChild(
			tile( total, s.statTotalPages || 'Converted Items', '' )
		);
		bar.appendChild(
			tile(
				enhanced,
				s.statAiEnhanced || 'AI-Enhanced',
				enhanced > 0 && enhanced === total ? 'success' : ''
			)
		);

		return bar;
	};

	// ── selection step ────────────────────────────────────────────────────────

	EtgAiEnhancement.prototype.renderSelectionStep = function () {
		const self = this;
		const wrap = cel( 'div' );

		// Hidden no-API banner
		if ( ! this.config.aiConfigured ) {
			const notice = cel( 'div', 'ele2gb-alert ele2gb-alert-warning' );
			notice.id = 'etg-no-api-notice';
			notice.style.display = 'none';
			const noticeText = document.createTextNode(
				( this.strings.noApiMessage ||
					'A Claude API key is required.' ) + ' '
			);
			const noticeLink = cel(
				'a',
				'',
				this.strings.addApiLink || 'Add your API key in Settings'
			);
			noticeLink.href = this.config.settingsUrl || '#';
			notice.appendChild( noticeText );
			notice.appendChild( noticeLink );
			wrap.appendChild( notice );
		}

		// Stats bar
		wrap.appendChild( this.renderStatsBar() );

		// Toolbar
		const toolbar = cel( 'div', 'ele2gb-select-toolbar' );

		const selectAllWrap = cel( 'label', 'ele2gb-master-select-label' );
		const selectAllCb = document.createElement( 'input' );
		selectAllCb.type = 'checkbox';
		selectAllCb.id = 'etg-select-all';
		selectAllCb.className = 'ele2gb-master-select-checkbox';
		selectAllCb.addEventListener( 'change', function ( e ) {
			self.onSelectAll( e.target.checked );
		} );
		selectAllWrap.appendChild( selectAllCb );
		selectAllWrap.appendChild( document.createTextNode( ' Select all' ) );
		toolbar.appendChild( selectAllWrap );

		const toolbarRight = cel( 'div', 'ele2gb-toolbar-right' );

		const bulkAiBtn = makeIconBtn(
			AI_SVG,
			this.strings.enhanceSelected || 'Bulk Enhance with AI',
			'ai-primary'
		);
		bulkAiBtn.id = 'etg-bulk-enhance-btn';
		bulkAiBtn.disabled = true;
		bulkAiBtn.addEventListener( 'click', function () {
			self.onBulkEnhanceClick();
		} );
		toolbarRight.appendChild( bulkAiBtn );

		toolbar.appendChild( toolbarRight );
		wrap.appendChild( toolbar );

		// Table
		const tableWrap = cel( 'div', 'ele2gb-table-wrapper' );
		const table = cel( 'table', 'ele2gb-wizard-table' );

		const thead = document.createElement( 'thead' );
		const hr = document.createElement( 'tr' );

		const thCb = cel( 'th' );
		thCb.style.width = '36px';
		hr.appendChild( thCb );

		hr.appendChild(
			cel( 'th', '', this.strings.colPage || 'Converted Page' )
		);

		const thSrc = cel( 'th', '', this.strings.colSource || 'Source Page' );
		thSrc.style.width = '180px';
		hr.appendChild( thSrc );

		const thAct = cel( 'th', '', this.strings.colActions || 'Actions' );
		thAct.style.width = '160px';
		hr.appendChild( thAct );

		thead.appendChild( hr );
		table.appendChild( thead );

		const tbody = document.createElement( 'tbody' );
		this.pages.forEach( function ( page ) {
			const tr = document.createElement( 'tr' );

			// Checkbox
			const tdCb = document.createElement( 'td' );
			const cb = document.createElement( 'input' );
			cb.type = 'checkbox';
			cb.value = String( page.id );
			cb.dataset.pageId = String( page.id );
			cb.addEventListener( 'change', function () {
				self.onRowCheck();
			} );
			tdCb.appendChild( cb );
			tr.appendChild( tdCb );

			// Converted page title + type badge
			const tdTitle = document.createElement( 'td' );
			const strong = cel( 'strong' );
			const titleLink = cel( 'a', '', page.title || String( page.id ) );
			titleLink.href =
				self.config.editBaseUrl + String( page.id ) + '&action=edit';
			titleLink.target = '_blank';
			strong.appendChild( titleLink );
			tdTitle.appendChild( strong );

			const tl = typeLabel( page.type );
			if ( tl ) {
				const typeBadge = cel( 'span', 'ele2gb-result-meta' );
				typeBadge.style.marginLeft = '6px';
				typeBadge.textContent = tl;
				tdTitle.appendChild( typeBadge );
			}
			if ( page.lastImproved ) {
				tdTitle.appendChild(
					cel(
						'div',
						'ele2gb-result-meta ele2gb-result-meta--enhanced',
						'✓ AI-enhanced'
					)
				);
			}
			tr.appendChild( tdTitle );

			// Source page
			const tdSource = document.createElement( 'td' );
			if ( page.sourceId ) {
				const srcLink = cel(
					'a',
					'',
					page.sourceTitle || String( page.sourceId )
				);
				srcLink.href =
					self.config.editBaseUrl +
					String( page.sourceId ) +
					'&action=edit';
				srcLink.target = '_blank';
				tdSource.appendChild( srcLink );
			} else {
				tdSource.textContent = '—';
			}
			tr.appendChild( tdSource );

			// Actions: Enhance with AI only
			const tdAct = document.createElement( 'td' );
			const actGroup = cel( 'div', 'ele2gb-action-group' );

			if (
				self.config.aiConfigured &&
				self.config.aiImproveBaseUrl &&
				page.sourceId
			) {
				const improveUrl =
					self.config.aiImproveBaseUrl +
					'&source_id=' +
					String( page.sourceId ) +
					'&target_id=' +
					String( page.id );
				actGroup.appendChild(
					makeActionPill(
						improveUrl,
						self.strings.enhanceSingle || 'Enhance with AI',
						'ai',
						false
					)
				);
			} else {
				const noKeyBtn = makeIconBtn(
					'',
					self.strings.enhanceSingle || 'Enhance with AI',
					'ai'
				);
				noKeyBtn.addEventListener( 'click', function () {
					const n = document.getElementById( 'etg-no-api-notice' );
					if ( n ) {
						n.style.display = 'block';
						n.scrollIntoView( {
							behavior: 'smooth',
							block: 'center',
						} );
					}
				} );
				actGroup.appendChild( noKeyBtn );
			}

			tdAct.appendChild( actGroup );
			tr.appendChild( tdAct );
			tbody.appendChild( tr );
		} );

		table.appendChild( tbody );
		tableWrap.appendChild( table );
		wrap.appendChild( tableWrap );

		return wrap;
	};

	// ── select all / row check ────────────────────────────────────────────────

	EtgAiEnhancement.prototype.onSelectAll = function ( checked ) {
		this.state.selected.clear();
		document
			.querySelectorAll(
				'#etg-ai-enhancement-app input[type=checkbox][data-page-id]'
			)
			.forEach( function ( cb ) {
				cb.checked = checked;
			} );
		if ( checked ) {
			const self = this;
			this.pages.forEach( function ( p ) {
				self.state.selected.add( p.id );
			} );
		}
		this.updateBulkButtons();
	};

	EtgAiEnhancement.prototype.onRowCheck = function () {
		const self = this;
		this.state.selected.clear();
		document
			.querySelectorAll(
				'#etg-ai-enhancement-app input[type=checkbox][data-page-id]'
			)
			.forEach( function ( cb ) {
				if ( cb.checked ) {
					self.state.selected.add( Number( cb.value ) );
				}
			} );
		this.updateBulkButtons();
	};

	EtgAiEnhancement.prototype.updateBulkButtons = function () {
		const count = this.state.selected.size;

		const aiBtn = document.getElementById( 'etg-bulk-enhance-btn' );
		if ( aiBtn ) {
			aiBtn.disabled = count === 0;
			const aiLbl = aiBtn.querySelector( '.ele2gb-action-pill-label' );
			if ( aiLbl ) {
				aiLbl.textContent =
					count > 0
						? fmt(
								this.strings.enhanceSelectedCount ||
									'Enhance %1$d items with AI',
								count
						  )
						: this.strings.enhanceSelected ||
						  'Bulk Enhance with AI';
			}
		}
	};

	// ── bulk AI improve flow ──────────────────────────────────────────────────

	EtgAiEnhancement.prototype.onBulkEnhanceClick = function () {
		if ( ! this.config.aiConfigured ) {
			const notice = document.getElementById( 'etg-no-api-notice' );
			if ( notice ) {
				notice.style.display = 'block';
				notice.scrollIntoView( {
					behavior: 'smooth',
					block: 'center',
				} );
			}
			return;
		}
		if ( this.state.selected.size === 0 ) {
			return;
		}
		this.initAiImprove();
	};

	EtgAiEnhancement.prototype.initAiImprove = function () {
		const selectedIds = this.state.selected;
		const pages = this.pages
			.filter( function ( p ) {
				return selectedIds.has( p.id );
			} )
			.map( function ( p ) {
				return {
					sourceId: p.sourceId,
					targetId: p.id,
					title: p.title || String( p.id ),
					type: p.type || 'page',
					status: 'pending',
					error: '',
				};
			} );
		this.state.aiImprove = {
			pages,
			currentIndex: 0,
			started: false,
			finished: false,
		};
		this.render();
	};

	EtgAiEnhancement.prototype.startAiImprove = function () {
		const ai = this.state.aiImprove;
		if ( ! ai || ai.started ) {
			return;
		}
		ai.started = true;
		this.processAiImprovePage( 0 );
	};

	EtgAiEnhancement.prototype.processAiImprovePage = function ( index ) {
		const self = this;
		const ai = this.state.aiImprove;
		if ( ! ai || index >= ai.pages.length ) {
			if ( ai ) {
				ai.finished = true;
			}
			this.render();
			return;
		}

		ai.currentIndex = index;
		ai.pages[ index ].status = 'processing';
		ai.pages[ index ].error = '';
		this.render();

		const page = ai.pages[ index ];
		this.showOverlay(
			this.strings.aiLoaderTitle || 'Improving with AI…',
			page.title,
			index + 1,
			ai.pages.length,
			[
				this.strings.aiStageAnalyzing || 'Analyzing…',
				this.strings.aiStageGenerating || 'Generating…',
				this.strings.aiStageSaving || 'Saving…',
			]
		);

		const fd = new FormData();
		fd.append( 'action', 'ele2gb_ai_improve_single' );
		fd.append( 'nonce', this.config.aiImproveNonce );
		fd.append( 'source_id', String( page.sourceId ) );
		fd.append( 'target_id', String( page.targetId ) );

		fetch( this.config.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: fd,
		} )
			.then( function ( r ) {
				return r.json();
			} )
			.then( function ( data ) {
				self.hideOverlay();
				if ( data.success ) {
					ai.pages[ index ].status = 'done';
					self.render();
					self.advanceAiImprove( index );
				} else {
					ai.pages[ index ].status = 'failed';
					ai.pages[ index ].error =
						data.data && data.data.message
							? String( data.data.message )
							: self.strings.aiImproveError ||
							  'An unexpected error occurred.';
					self.render();
				}
			} )
			.catch( function ( err ) {
				self.hideOverlay();
				ai.pages[ index ].status = 'failed';
				ai.pages[ index ].error =
					err.message ||
					self.strings.aiImproveError ||
					'An unexpected error occurred.';
				self.render();
			} );
	};

	EtgAiEnhancement.prototype.advanceAiImprove = function ( index ) {
		const ai = this.state.aiImprove;
		const next = index + 1;
		if ( ! ai || next >= ai.pages.length ) {
			if ( ai ) {
				ai.finished = true;
				this.render();
			}
			return;
		}
		this.processAiImprovePage( next );
	};

	EtgAiEnhancement.prototype.skipAiImprovePage = function ( index ) {
		const ai = this.state.aiImprove;
		if ( ! ai ) {
			return;
		}
		ai.pages[ index ].status = 'skipped';
		this.render();
		this.advanceAiImprove( index );
	};

	EtgAiEnhancement.prototype.retryAiImprovePage = function ( index ) {
		this.processAiImprovePage( index );
	};

	// ── render AI improve step ────────────────────────────────────────────────

	EtgAiEnhancement.prototype.renderAiImproveStep = function () {
		const self = this;
		const ai = this.state.aiImprove;
		const wrap = cel( 'div', 'ele2gb-ai-improve-step' );
		const cfg = this.config;
		const str = this.strings;

		if ( ! ai.started ) {
			const readinessPanel = cel( 'div', 'ele2gb-ai-readiness-panel' );
			const rHeader = cel( 'div', 'ele2gb-ai-readiness-header' );
			rHeader.appendChild(
				cel(
					'h3',
					'ele2gb-ai-readiness-title',
					str.aiReadinessTitle || 'Pre-flight Checklist'
				)
			);
			if ( cfg.aiConfigured ) {
				rHeader.appendChild(
					cel( 'span', 'ele2gb-ai-readiness-all-ready', '✓ Ready' )
				);
			}
			readinessPanel.appendChild( rHeader );

			const apiRow = cel( 'div', 'ele2gb-ai-readiness-row' );
			const apiIcon = cel(
				'div',
				'ele2gb-ai-readiness-icon ' +
					( cfg.aiConfigured
						? 'ele2gb-ai-readiness-icon--ok'
						: 'ele2gb-ai-readiness-icon--error' ),
				cfg.aiConfigured ? '✓' : '✗'
			);
			const apiLbl = cel(
				'span',
				'ele2gb-ai-readiness-status' +
					( cfg.aiConfigured ? '' : ' is-invalid' ),
				cfg.aiConfigured
					? str.aiReadinessApiValid || 'API key configured'
					: str.aiReadinessApiInvalid || 'API key not configured'
			);
			apiRow.appendChild( apiIcon );
			apiRow.appendChild( apiLbl );
			readinessPanel.appendChild( apiRow );

			const cntRow = cel( 'div', 'ele2gb-ai-readiness-row' );
			const cntIcon = cel(
				'div',
				'ele2gb-ai-readiness-icon ele2gb-ai-readiness-icon--info',
				String( ai.pages.length )
			);
			const cntLbl = cel(
				'span',
				'ele2gb-ai-readiness-status',
				fmt(
					str.aiReadinessCredits ||
						'Estimated: ~%1$d API call(s), ~1–2 minutes per item',
					ai.pages.length
				)
			);
			cntRow.appendChild( cntIcon );
			cntRow.appendChild( cntLbl );
			readinessPanel.appendChild( cntRow );
			wrap.appendChild( readinessPanel );

			const pagesSection = cel( 'div', 'ele2gb-preflight-pages-section' );
			pagesSection.appendChild(
				cel(
					'p',
					'ele2gb-preflight-section-label',
					'Items to enhance (' + ai.pages.length + ')'
				)
			);
			const pagesList = cel( 'div', 'ele2gb-preflight-pages' );

			ai.pages.forEach( function ( page ) {
				const fullData = cfg.pages
					? cfg.pages.filter( function ( p ) {
							return p.id === page.targetId;
					  } )[ 0 ]
					: null;
				const card = cel( 'div', 'ele2gb-preflight-page-card' );
				const body = cel( 'div', 'ele2gb-preflight-page-body' );

				const titleRow = cel( 'div', 'ele2gb-preflight-page-title' );
				const tLink = cel(
					'a',
					'',
					page.title || String( page.targetId )
				);
				tLink.href =
					cfg.editBaseUrl + String( page.targetId ) + '&action=edit';
				tLink.target = '_blank';
				titleRow.appendChild( tLink );
				if ( fullData && fullData.lastImproved ) {
					titleRow.appendChild(
						cel(
							'span',
							'ele2gb-preflight-improved-badge',
							'✓ Enhanced'
						)
					);
				}
				body.appendChild( titleRow );

				const tl = typeLabel( page.type );
				if ( tl ) {
					body.appendChild(
						cel( 'div', 'ele2gb-preflight-page-source', tl )
					);
				}

				if ( fullData && fullData.sourceTitle ) {
					body.appendChild(
						cel(
							'div',
							'ele2gb-preflight-page-source',
							'Source: ' + fullData.sourceTitle
						)
					);
				}

				card.appendChild( body );
				pagesList.appendChild( card );
			} );

			pagesSection.appendChild( pagesList );
			wrap.appendChild( pagesSection );

			const warnBox = cel( 'div', 'ele2gb-ai-warning-notice' );
			const warnIcon = cel( 'div', 'ele2gb-ai-warning-icon', '⚠' );
			const warnText = cel( 'div', 'ele2gb-ai-warning-text' );
			warnText.appendChild(
				cel(
					'strong',
					'',
					str.aiImproveWarningTitle || 'AI credits will be used'
				)
			);
			warnText.appendChild(
				cel(
					'p',
					'',
					str.aiImproveWarning ||
						'This will use AI credits once per selected item. Make sure your API key has sufficient credits before starting.'
				)
			);
			warnBox.appendChild( warnIcon );
			warnBox.appendChild( warnText );
			wrap.appendChild( warnBox );

			const actions = cel( 'div', 'ele2gb-results-actions' );
			const backBtn = makeIconBtn(
				'',
				'← ' + ( str.back || 'Back' ),
				'view-secondary'
			);
			backBtn.addEventListener( 'click', function () {
				self.state.aiImprove = null;
				self.render();
			} );
			actions.appendChild( backBtn );

			const startBtn = makeIconBtn(
				AI_SVG,
				str.aiImproveStart || 'Start AI Enhancement',
				'ai-primary'
			);
			startBtn.disabled = ! cfg.aiConfigured;
			startBtn.addEventListener( 'click', function () {
				self.startAiImprove();
			} );
			actions.appendChild( startBtn );

			wrap.appendChild( actions );
			return wrap;
		}

		// Progress view
		const done = ai.pages.filter( function ( p ) {
			return p.status === 'done';
		} ).length;
		const failed = ai.pages.filter( function ( p ) {
			return p.status === 'failed';
		} ).length;
		const skipped = ai.pages.filter( function ( p ) {
			return p.status === 'skipped';
		} ).length;
		const pending = ai.pages.filter( function ( p ) {
			return p.status === 'pending';
		} ).length;
		const total = ai.pages.length;
		const pct =
			total > 0
				? Math.round( ( ( done + failed + skipped ) / total ) * 100 )
				: 0;

		wrap.appendChild(
			this.renderProgressSection(
				done,
				failed,
				skipped,
				pending,
				pct,
				ai.finished
			)
		);

		const table = cel(
			'table',
			'ele2gb-wizard-table ele2gb-ai-results-table'
		);
		const thead2 = document.createElement( 'thead' );
		const hr2 = document.createElement( 'tr' );
		hr2.appendChild( cel( 'th', '', str.colPage || 'Item' ) );
		hr2.appendChild( cel( 'th', '', str.aiImproveType || 'Type' ) );
		hr2.appendChild( cel( 'th', '', 'Status' ) );
		hr2.appendChild( cel( 'th', '', 'Actions' ) );
		thead2.appendChild( hr2 );
		table.appendChild( thead2 );

		const tbody2 = document.createElement( 'tbody' );
		ai.pages.forEach( function ( page, i ) {
			const tr = document.createElement( 'tr' );
			tr.className = 'ele2gb-ai-row--' + page.status;
			tr.appendChild( cel( 'td', 'ele2gb-ai-title-cell', page.title ) );
			tr.appendChild(
				cel( 'td', '', typeLabel( page.type ) || page.type )
			);
			const tdSt = document.createElement( 'td' );
			tdSt.appendChild(
				self.makeAiStatusBadge( page.status, page.error )
			);
			tr.appendChild( tdSt );
			tr.appendChild( self.makeProgressRowActions( page.status, i ) );
			tbody2.appendChild( tr );
		} );
		table.appendChild( tbody2 );
		wrap.appendChild( table );

		if ( ai.finished ) {
			const msg =
				failed === 0 && skipped === 0
					? str.aiImproveFinishedOk ||
					  'All items improved successfully.'
					: fmt(
							str.aiImproveFinishedErr ||
								'Finished — %1$d done, %2$d failed, %3$d skipped.',
							done,
							failed,
							skipped
					  );
			const comp = cel(
				'div',
				'ele2gb-ai-completion ' +
					( failed === 0 && skipped === 0
						? 'ele2gb-ai-completion--success'
						: 'ele2gb-ai-completion--partial' )
			);
			comp.appendChild( cel( 'p', '', msg ) );
			wrap.appendChild( comp );

			const doneAct = cel( 'div', 'ele2gb-results-actions' );
			const backBtn2 = makeIconBtn(
				'',
				str.backToList || 'Back to list',
				'view-secondary'
			);
			backBtn2.addEventListener( 'click', function () {
				self.state.aiImprove = null;
				self.state.selected.clear();
				self.render();
			} );
			doneAct.appendChild( backBtn2 );

			if ( cfg.feedbackEnabled && cfg.feedbackNonce && done > 0 ) {
				const fbRunBtn = makeIconBtn(
					FEEDBACK_SVG,
					str.feedbackBtn || 'Send Feedback',
					'feedback'
				);
				fbRunBtn.addEventListener( 'click', function () {
					const firstDone = ai.pages.filter( function ( p ) {
						return p.status === 'done';
					} )[ 0 ];
					if ( firstDone ) {
						self.openAiFeedbackModal(
							firstDone.targetId,
							firstDone.sourceId,
							firstDone.title
						);
					}
				} );
				doneAct.appendChild( fbRunBtn );
			}

			wrap.appendChild( doneAct );
		}

		return wrap;
	};

	// ── shared progress section renderer ──────────────────────────────────────

	EtgAiEnhancement.prototype.renderProgressSection = function (
		done,
		failed,
		skipped,
		pending,
		pct,
		finished
	) {
		const section = cel( 'div', 'ele2gb-progress-section' );
		const bar = cel( 'div', 'ele2gb-progress-bar' );
		const fill = cel( 'div', 'ele2gb-progress-fill' );
		fill.style.width = pct + '%';
		bar.appendChild( fill );
		section.appendChild( bar );

		const chips = cel( 'div', 'ele2gb-status-chips' );
		if ( done > 0 ) {
			chips.appendChild(
				cel(
					'span',
					'ele2gb-status-chip ele2gb-status-chip--done',
					fmt( this.strings.aiStatusDone || 'Done (%1$d)', done )
				)
			);
		}
		if ( failed > 0 ) {
			chips.appendChild(
				cel(
					'span',
					'ele2gb-status-chip ele2gb-status-chip--failed',
					fmt(
						this.strings.aiStatusFailed || 'Failed (%1$d)',
						failed
					)
				)
			);
		}
		if ( skipped > 0 ) {
			chips.appendChild(
				cel(
					'span',
					'ele2gb-status-chip ele2gb-status-chip--skipped',
					fmt(
						this.strings.aiStatusSkipped || 'Skipped (%1$d)',
						skipped
					)
				)
			);
		}
		if ( pending > 0 ) {
			chips.appendChild(
				cel(
					'span',
					'ele2gb-status-chip ele2gb-status-chip--pending',
					fmt(
						this.strings.aiStatusPending || 'Pending (%1$d)',
						pending
					)
				)
			);
		}
		section.appendChild( chips );

		if ( failed > 0 && ! finished ) {
			const note = cel( 'div', 'ele2gb-paused-notice' );
			note.appendChild(
				cel(
					'p',
					'',
					this.strings.aiImprovePaused ||
						'Paused — an item failed. Review the error below, then skip or retry to continue.'
				)
			);
			section.appendChild( note );
		}
		return section;
	};

	// ── shared progress row actions ───────────────────────────────────────────

	EtgAiEnhancement.prototype.makeProgressRowActions = function (
		status,
		index
	) {
		const self = this;
		const tdAct = document.createElement( 'td' );

		if ( status === 'failed' ) {
			const skipBtn = makeIconBtn(
				'',
				this.strings.skip || 'Skip',
				'view'
			);
			skipBtn.addEventListener( 'click', function () {
				self.skipAiImprovePage( index );
			} );
			tdAct.appendChild( skipBtn );

			const retryBtn = makeIconBtn(
				'',
				this.strings.retry || 'Retry',
				'retry'
			);
			retryBtn.style.marginLeft = '6px';
			retryBtn.addEventListener( 'click', function () {
				self.retryAiImprovePage( index );
			} );
			tdAct.appendChild( retryBtn );
		} else if ( status === 'processing' ) {
			tdAct.appendChild( this.makeRowSpinner() );
		}

		return tdAct;
	};

	// ── generic overlay ───────────────────────────────────────────────────────

	EtgAiEnhancement.prototype.showOverlay = function (
		title,
		subtitle,
		current,
		total,
		stages
	) {
		this.hideOverlay();

		if ( ! document.getElementById( 'etg-spin-style' ) ) {
			const style = document.createElement( 'style' );
			style.id = 'etg-spin-style';
			style.textContent =
				'@keyframes etg-spin{from{transform:rotate(0deg)}to{transform:rotate(360deg)}}';
			document.head.appendChild( style );
		}

		const accentColor = stages ? '#2271b1' : '#0ea5e9';
		const overlay = document.createElement( 'div' );
		overlay.id = 'ele2gb-bulk-ai-overlay';
		overlay.style.cssText =
			'position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.55);z-index:999999;display:flex;align-items:center;justify-content:center;';

		const card = document.createElement( 'div' );
		card.style.cssText =
			'background:#fff;border-radius:10px;padding:40px 48px;max-width:480px;width:90%;text-align:center;box-shadow:0 8px 40px rgba(0,0,0,0.18);';

		const svgNS = 'http://www.w3.org/2000/svg';
		const spinWrap = document.createElement( 'div' );
		spinWrap.style.marginBottom = '24px';
		const svg = document.createElementNS( svgNS, 'svg' );
		svg.setAttribute( 'width', '48' );
		svg.setAttribute( 'height', '48' );
		svg.setAttribute( 'viewBox', '0 0 24 24' );
		svg.setAttribute( 'fill', 'none' );
		svg.style.cssText = 'animation:etg-spin 1s linear infinite;';
		const circle = document.createElementNS( svgNS, 'circle' );
		circle.setAttribute( 'cx', '12' );
		circle.setAttribute( 'cy', '12' );
		circle.setAttribute( 'r', '10' );
		circle.setAttribute( 'stroke', accentColor );
		circle.setAttribute( 'stroke-width', '3' );
		circle.setAttribute( 'stroke-dasharray', '40 20' );
		svg.appendChild( circle );
		spinWrap.appendChild( svg );
		card.appendChild( spinWrap );

		const h3 = document.createElement( 'h3' );
		h3.style.cssText =
			'margin:0 0 8px;font-size:18px;color:#1d2327;font-weight:700;';
		h3.textContent = title;
		card.appendChild( h3 );

		const sub = document.createElement( 'p' );
		sub.style.cssText = 'margin:0 0 12px;color:#50575e;font-size:13px;';
		sub.textContent = subtitle;
		card.appendChild( sub );

		const counter = document.createElement( 'p' );
		counter.style.cssText =
			'margin:0 0 20px;color:' +
			accentColor +
			';font-size:13px;font-weight:600;';
		counter.textContent = current + ' / ' + total;
		card.appendChild( counter );

		if ( stages && stages.length ) {
			const dotsWrap = document.createElement( 'div' );
			dotsWrap.style.cssText =
				'display:flex;justify-content:center;gap:8px;';
			stages.forEach( function ( label, idx ) {
				const dot = document.createElement( 'span' );
				dot.style.cssText =
					'font-size:12px;padding:2px 10px;border-radius:12px;transition:background 0.3s,color 0.3s;background:' +
					( idx === 0 ? accentColor : '#dcdcde' ) +
					';color:' +
					( idx === 0 ? '#fff' : '#50575e' ) +
					';';
				dot.textContent = label;
				dotsWrap.appendChild( dot );
			} );
			card.appendChild( dotsWrap );
			const t1 = setTimeout( function () {
				const d = dotsWrap.children;
				if ( d[ 0 ] ) {
					d[ 0 ].style.background = '#dcdcde';
					d[ 0 ].style.color = '#50575e';
				}
				if ( d[ 1 ] ) {
					d[ 1 ].style.background = accentColor;
					d[ 1 ].style.color = '#fff';
				}
			}, 6000 );
			const t2 = setTimeout( function () {
				const d = dotsWrap.children;
				if ( d[ 1 ] ) {
					d[ 1 ].style.background = '#dcdcde';
					d[ 1 ].style.color = '#50575e';
				}
				if ( d[ 2 ] ) {
					d[ 2 ].style.background = accentColor;
					d[ 2 ].style.color = '#fff';
				}
			}, 22000 );
			overlay.dataset.t1 = String( t1 );
			overlay.dataset.t2 = String( t2 );
		}

		overlay.appendChild( card );
		document.body.appendChild( overlay );
	};

	EtgAiEnhancement.prototype.hideOverlay = function () {
		const overlay = document.getElementById( 'ele2gb-bulk-ai-overlay' );
		if ( ! overlay ) {
			return;
		}
		if ( overlay.dataset.t1 ) {
			clearTimeout( Number( overlay.dataset.t1 ) );
		}
		if ( overlay.dataset.t2 ) {
			clearTimeout( Number( overlay.dataset.t2 ) );
		}
		overlay.parentNode.removeChild( overlay );
	};

	// ── badge / spinner helpers ───────────────────────────────────────────────

	EtgAiEnhancement.prototype.makeAiStatusBadge = function ( status, error ) {
		const labels = {
			pending: this.strings.aiStatusPending || 'Pending',
			processing: this.strings.aiStatusProcessing || 'Processing…',
			done: this.strings.aiStatusDone || 'Done',
			failed: this.strings.aiStatusFailed || 'Failed',
			skipped: this.strings.aiStatusSkipped || 'Skipped',
		};
		const badge = cel(
			'span',
			'ele2gb-ai-badge ele2gb-ai-badge--' + status,
			labels[ status ] || status
		);
		if ( status === 'failed' && error ) {
			badge.appendChild(
				cel( 'span', 'ele2gb-ai-badge-error', ' — ' + error )
			);
		}
		return badge;
	};

	EtgAiEnhancement.prototype.makeRowSpinner = function () {
		const wrap = cel( 'span', 'ele2gb-row-spinner' );
		const svgNS = 'http://www.w3.org/2000/svg';
		const svg = document.createElementNS( svgNS, 'svg' );
		svg.setAttribute( 'width', '16' );
		svg.setAttribute( 'height', '16' );
		svg.setAttribute( 'viewBox', '0 0 24 24' );
		svg.setAttribute( 'fill', 'none' );
		svg.style.cssText =
			'animation:etg-spin 1s linear infinite;vertical-align:middle;';
		const c = document.createElementNS( svgNS, 'circle' );
		c.setAttribute( 'cx', '12' );
		c.setAttribute( 'cy', '12' );
		c.setAttribute( 'r', '10' );
		c.setAttribute( 'stroke', '#2271b1' );
		c.setAttribute( 'stroke-width', '3' );
		c.setAttribute( 'stroke-dasharray', '40 20' );
		svg.appendChild( c );
		wrap.appendChild( svg );
		return wrap;
	};

	// ── AI Enhancement feedback modal ─────────────────────────────────────────

	EtgAiEnhancement.prototype.openAiFeedbackModal = function (
		targetId,
		sourceId,
		title
	) {
		this.closeAiFeedbackModal();
		if ( ! this.config.feedbackNonce ) {
			return;
		}
		const self = this;
		const str = this.strings;

		const overlay = document.createElement( 'div' );
		overlay.id = 'etg-ae-feedback-overlay';
		overlay.style.cssText =
			'position:fixed;top:0;left:0;right:0;bottom:0;z-index:100000;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,0.55);padding:20px;box-sizing:border-box;';

		const modal = document.createElement( 'div' );
		modal.style.cssText =
			'background:#fff;border-radius:8px;padding:28px 32px;max-width:500px;width:100%;max-height:90vh;overflow-y:auto;box-shadow:0 4px 32px rgba(0,0,0,0.18);box-sizing:border-box;';

		const h2 = document.createElement( 'h2' );
		h2.style.cssText =
			'margin:0 0 4px;font-size:17px;font-weight:600;color:#1d2327;';
		h2.textContent = str.feedbackModalTitle || 'How did AI Enhancement go?';
		modal.appendChild( h2 );

		if ( title ) {
			const sub = cel( 'p', null, title );
			sub.style.cssText = 'margin:0 0 20px;font-size:12px;color:#787c82;';
			modal.appendChild( sub );
		} else {
			modal.style.marginBottom = '16px';
		}

		const issueWrap = cel( 'div', null );
		issueWrap.style.cssText = 'margin-bottom:16px;';
		const issueLbl = cel(
			'label',
			null,
			str.feedbackIssueLabel || 'Issue type'
		);
		issueLbl.style.cssText =
			'display:block;font-size:13px;font-weight:500;margin-bottom:6px;color:#1d2327;';
		issueWrap.appendChild( issueLbl );
		const issueSelect = document.createElement( 'select' );
		issueSelect.style.cssText =
			'width:100%;padding:6px 8px;border:1px solid #c3c4c7;border-radius:4px;font-size:13px;';
		[
			[ '', str.feedbackNoIssue || 'No issue' ],
			[ 'layout', str.feedbackIssueLayout || 'Layout issues after AI' ],
			[
				'missing',
				str.feedbackIssueMissing || 'Wrong or missing content',
			],
			[ 'css', str.feedbackIssueCss || 'CSS / styling problems' ],
			[ 'quality', str.feedbackIssueQuality || 'AI output quality' ],
			[ 'other', str.feedbackIssueOther || 'Other' ],
		].forEach( function ( pair ) {
			const opt = document.createElement( 'option' );
			opt.value = pair[ 0 ];
			opt.textContent = pair[ 1 ];
			issueSelect.appendChild( opt );
		} );
		issueWrap.appendChild( issueSelect );
		modal.appendChild( issueWrap );

		const detailWrap = cel( 'div', null );
		detailWrap.style.cssText = 'margin-bottom:16px;display:none;';
		const detailLbl = cel(
			'label',
			null,
			str.feedbackIssueDetailLabel || 'Describe the issue'
		);
		detailLbl.style.cssText =
			'display:block;font-size:13px;font-weight:500;margin-bottom:6px;color:#1d2327;';
		detailWrap.appendChild( detailLbl );
		const detailInput = document.createElement( 'textarea' );
		detailInput.rows = 2;
		detailInput.maxLength = 500;
		detailInput.style.cssText =
			'width:100%;padding:6px 8px;border:1px solid #c3c4c7;border-radius:4px;font-size:13px;box-sizing:border-box;resize:vertical;';
		detailWrap.appendChild( detailInput );
		modal.appendChild( detailWrap );

		issueSelect.addEventListener( 'change', function () {
			detailWrap.style.display = issueSelect.value ? 'block' : 'none';
		} );

		const noteLbl = cel(
			'label',
			null,
			str.feedbackNoteLabel || 'Additional notes'
		);
		noteLbl.style.cssText =
			'display:block;font-size:13px;font-weight:500;margin-bottom:6px;color:#1d2327;';
		modal.appendChild( noteLbl );
		const noteTA = document.createElement( 'textarea' );
		noteTA.rows = 3;
		noteTA.maxLength = 2000;
		noteTA.style.cssText =
			'width:100%;padding:6px 8px;border:1px solid #c3c4c7;border-radius:4px;font-size:13px;box-sizing:border-box;resize:vertical;margin-bottom:16px;';
		modal.appendChild( noteTA );

		const consentLbl = document.createElement( 'label' );
		consentLbl.style.cssText =
			'display:flex;gap:8px;align-items:flex-start;margin-bottom:20px;cursor:pointer;';
		const consentCb = document.createElement( 'input' );
		consentCb.type = 'checkbox';
		consentCb.checked = false;
		consentCb.style.cssText = 'margin-top:3px;flex-shrink:0;';
		const consentSpan = cel( 'span', null );
		consentSpan.style.cssText =
			'font-size:12px;color:#50575e;line-height:1.5;';
		consentSpan.textContent =
			str.feedbackConsentLabel ||
			'I consent to sending this anonymised AI enhancement report to the plugin developer for quality improvement. No passwords, API keys, or user data are included.';
		consentLbl.appendChild( consentCb );
		consentLbl.appendChild( consentSpan );
		modal.appendChild( consentLbl );

		const actRow = cel( 'div', null );
		actRow.style.cssText =
			'display:flex;gap:8px;align-items:center;flex-wrap:wrap;';
		const errSpan = cel( 'span', null );
		errSpan.style.cssText =
			'flex:1;font-size:12px;color:#d63638;min-width:0;';
		actRow.appendChild( errSpan );
		const cancelBtn = document.createElement( 'button' );
		cancelBtn.type = 'button';
		cancelBtn.className = 'button';
		cancelBtn.textContent = str.feedbackCancel || 'Cancel';
		cancelBtn.addEventListener( 'click', function () {
			self.closeAiFeedbackModal();
		} );
		actRow.appendChild( cancelBtn );
		const submitBtn = document.createElement( 'button' );
		submitBtn.type = 'button';
		submitBtn.className = 'button button-primary';
		submitBtn.textContent = str.feedbackSubmit || 'Send Feedback';
		submitBtn.disabled = true;
		actRow.appendChild( submitBtn );
		modal.appendChild( actRow );

		consentCb.addEventListener( 'change', function () {
			submitBtn.disabled = ! consentCb.checked;
		} );

		overlay.appendChild( modal );
		document.body.appendChild( overlay );
		this._feedbackOverlay = overlay;

		overlay.addEventListener( 'click', function ( e ) {
			if ( e.target === overlay ) {
				self.closeAiFeedbackModal();
			}
		} );

		submitBtn.addEventListener( 'click', function () {
			if ( ! consentCb.checked ) {
				return;
			}
			submitBtn.disabled = true;
			cancelBtn.disabled = true;
			submitBtn.textContent = str.feedbackSending || 'Sending…';
			errSpan.textContent = '';

			const fd = new FormData();
			fd.append( 'action', 'etg_submit_ai_enhancement_feedback' );
			fd.append( 'nonce', self.config.feedbackNonce );
			fd.append( 'target_id', String( targetId ) );
			fd.append( 'source_id', String( sourceId || 0 ) );
			fd.append( 'consent_given', 'true' );
			fd.append( 'issue_type', issueSelect.value || '' );
			fd.append( 'issue_detail', detailInput.value || '' );
			fd.append( 'user_note', noteTA.value || '' );

			fetch( self.config.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				body: fd,
			} )
				.then( function ( r ) {
					return r.json();
				} )
				.then( function ( data ) {
					if ( data.success ) {
						const fbId =
							data.data && data.data.feedback_id
								? String( data.data.feedback_id )
								: '';
						self.closeAiFeedbackModal();
						self.showAiFeedbackConfirm(
							fmt(
								str.feedbackSuccess ||
									'Thank you! Feedback submitted (ID: %1$s).',
								fbId
							)
						);
					} else {
						const msg =
							data.data && data.data.error
								? String( data.data.error )
								: str.feedbackError ||
								  'An unexpected error occurred.';
						errSpan.textContent = msg;
						submitBtn.disabled = false;
						cancelBtn.disabled = false;
						submitBtn.textContent =
							str.feedbackSubmit || 'Send Feedback';
					}
				} )
				.catch( function ( err ) {
					errSpan.textContent =
						err.message ||
						str.feedbackError ||
						'An unexpected error occurred.';
					submitBtn.disabled = false;
					cancelBtn.disabled = false;
					submitBtn.textContent =
						str.feedbackSubmit || 'Send Feedback';
				} );
		} );
	};

	EtgAiEnhancement.prototype.closeAiFeedbackModal = function () {
		if ( this._feedbackOverlay ) {
			this._feedbackOverlay.remove();
			this._feedbackOverlay = null;
		}
	};

	EtgAiEnhancement.prototype.showAiFeedbackConfirm = function ( message ) {
		const notice = cel( 'div', 'ele2gb-alert ele2gb-alert-success' );
		notice.style.cssText =
			'position:fixed;bottom:24px;right:24px;z-index:99999;padding:12px 20px;background:#00a32a;color:#fff;border-radius:6px;font-size:13px;box-shadow:0 2px 12px rgba(0,0,0,0.18);max-width:360px;';
		notice.textContent = message;
		document.body.appendChild( notice );
		setTimeout( function () {
			if ( notice.parentNode ) {
				notice.parentNode.removeChild( notice );
			}
		}, 8000 );
	};

	// ── boot ──────────────────────────────────────────────────────────────────

	document.addEventListener( 'DOMContentLoaded', function () {
		if ( typeof window.etgAiEnhancement === 'undefined' ) {
			return;
		}
		const app = new EtgAiEnhancement( window.etgAiEnhancement );
		app.init();
	} );
} )();
