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

	const CHECK_SVG =
		'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"></polyline></svg>';

	// SVG sentinels kept so existing call sites (makeIconBtn) keep their meaning;
	// they now map to design-system icon names rendered by pgsIcons.
	const AI_SVG = 'sparkles';
	const FEEDBACK_SVG = 'message-square';

	function icon( name ) {
		const span = cel( 'span', 'pgs-btn__icon' );
		span.innerHTML = '<i data-icon="' + name + '"></i>';
		return span;
	}

	const MOD_VARIANT = {
		'ai-primary': 'primary',
		ai: 'secondary',
		'view-secondary': 'secondary',
		view: 'ghost',
		retry: 'secondary',
		feedback: 'ghost',
	};

	function modSize( mod ) {
		return mod === 'view' || mod === 'retry' || mod === 'feedback'
			? 'sm'
			: 'md';
	}

	// Enhance-single deep link rendered as a secondary pgs button.
	function makeActionPill( href, label, mod, targetBlank ) {
		const variant = MOD_VARIANT[ mod ] || 'secondary';
		const a = cel(
			'a',
			'pgs-btn pgs-btn--' + variant + ' pgs-btn--' + modSize( mod )
		);
		a.href = href;
		if ( targetBlank ) {
			a.target = '_blank';
			a.rel = 'noopener';
		}
		a.appendChild( icon( 'sparkles' ) );
		a.appendChild( cel( 'span', 'pgs-btn__label', label ) );
		return a;
	}

	function makeIconBtn( iconHtml, label, mod ) {
		const variant = MOD_VARIANT[ mod ] || 'secondary';
		const btn = cel(
			'button',
			'pgs-btn pgs-btn--' + variant + ' pgs-btn--' + modSize( mod )
		);
		btn.type = 'button';
		if ( iconHtml === AI_SVG ) {
			btn.appendChild( icon( 'sparkles' ) );
		} else if ( iconHtml === FEEDBACK_SVG ) {
			btn.appendChild( icon( 'message-square' ) );
		}
		if ( label ) {
			btn.appendChild( cel( 'span', 'pgs-btn__label', label ) );
		}
		return btn;
	}

	// pgs-check control: returns { label, input }.
	function pgsCheck() {
		const label = cel( 'label', 'pgs-check' );
		const input = document.createElement( 'input' );
		input.type = 'checkbox';
		input.className = 'pgs-check__input';
		const box = cel( 'span', 'pgs-check__box' );
		box.setAttribute( 'aria-hidden', 'true' );
		box.innerHTML = CHECK_SVG;
		label.appendChild( input );
		label.appendChild( box );
		return { label: label, input: input };
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
		const col = cel( 'div', 'pgs-col' );
		if ( this.state.aiImprove ) {
			col.appendChild( this.renderAiImproveStep() );
		} else {
			col.appendChild( this.renderSelectionStep() );
		}
		this.root.appendChild( col );
		if ( window.pgsIcons ) {
			window.pgsIcons.create( this.root );
		}
	};

	EtgAiEnhancement.prototype.pageTitle = function ( title, sub ) {
		const pt = cel( 'div', 'pgs-pagetitle' );
		const box = cel( 'div' );
		box.appendChild( cel( 'h1', '', title ) );
		if ( sub ) {
			box.appendChild( cel( 'p', '', sub ) );
		}
		pt.appendChild( box );
		return pt;
	};

	// ── stats bar ─────────────────────────────────────────────────────────────

	EtgAiEnhancement.prototype.renderStatsBar = function () {
		const pages = this.pages;
		const total = pages.length;
		const enhanced = pages.filter( function ( p ) {
			return !! p.lastImproved;
		} ).length;
		const s = this.strings;

		const grid = cel( 'div', 'pgs-grid2-13' );

		function stat( value, label, iconName, mods ) {
			const t = cel( 'div', 'pgs-stat' + ( mods ? ' ' + mods : '' ) );
			const top = cel( 'div', 'pgs-stat__top' );
			top.appendChild(
				cel( 'span', 'pgs-stat__value', String( value ) )
			);
			if ( iconName ) {
				const ic = cel( 'span', 'pgs-stat__icon' );
				ic.innerHTML = '<i data-icon="' + iconName + '"></i>';
				top.appendChild( ic );
			}
			t.appendChild( top );
			t.appendChild( cel( 'span', 'pgs-stat__label', label ) );
			return t;
		}

		grid.appendChild(
			stat( total, s.statTotalPages || 'Converted items', 'blocks', '' )
		);
		grid.appendChild(
			stat(
				enhanced,
				s.statAiEnhanced || 'AI-enhanced',
				'sparkles',
				'pgs-stat--tinted pgs-stat--brand'
			)
		);

		return grid;
	};

	// ── selection step ────────────────────────────────────────────────────────

	EtgAiEnhancement.prototype.renderSelectionStep = function () {
		const self = this;
		const wrap = cel( 'div', 'pgs-stack' );
		wrap.style.gap = 'var(--gap-section)';

		wrap.appendChild(
			this.pageTitle(
				this.strings.pageTitle || 'AI Enhancement',
				this.strings.pageSubtitle ||
					'Refine converted pages until they visually match the original.'
			)
		);

		// Hidden no-API banner
		if ( ! this.config.aiConfigured ) {
			const notice = cel( 'div', 'pgs-banner pgs-banner--warning' );
			notice.id = 'etg-no-api-notice';
			notice.style.display = 'none';
			notice.setAttribute( 'role', 'status' );
			const ic = cel( 'span', 'pgs-banner__icon' );
			ic.innerHTML = '<i data-icon="alert-triangle"></i>';
			notice.appendChild( ic );
			const body = cel( 'div', 'pgs-banner__body' );
			const text = cel( 'span', 'pgs-banner__text' );
			text.appendChild(
				document.createTextNode(
					( this.strings.noApiMessage ||
						'A Claude API key is required.' ) + ' '
				)
			);
			const noticeLink = cel(
				'a',
				'',
				this.strings.addApiLink || 'Add your API key in Settings'
			);
			noticeLink.href = this.config.settingsUrl || '#';
			text.appendChild( noticeLink );
			body.appendChild( text );
			notice.appendChild( body );
			wrap.appendChild( notice );
		}

		// Stats
		wrap.appendChild( this.renderStatsBar() );

		// Queue card
		const card = cel( 'div', 'pgs-card' );

		const header = cel( 'div', 'pgs-card__header' );
		const headLeft = cel( 'div' );
		headLeft.appendChild(
			cel(
				'div',
				'pgs-card__eyebrow',
				fmt(
					this.strings.queueEyebrow || 'Queue (%1$d)',
					this.pages.length
				)
			)
		);
		headLeft.appendChild(
			cel(
				'div',
				'pgs-card__title',
				this.strings.queueTitle || 'Items to enhance'
			)
		);
		header.appendChild( headLeft );

		const headActions = cel( 'div', 'pgs-card__actions' );
		const bulkAiBtn = makeIconBtn(
			AI_SVG,
			this.strings.enhanceSelected || 'Bulk Enhance with AI',
			'view-secondary'
		);
		bulkAiBtn.className = 'pgs-btn pgs-btn--subtle pgs-btn--sm';
		bulkAiBtn.id = 'etg-bulk-enhance-btn';
		bulkAiBtn.disabled = true;
		bulkAiBtn.addEventListener( 'click', function () {
			self.onBulkEnhanceClick();
		} );
		headActions.appendChild( bulkAiBtn );
		header.appendChild( headActions );
		card.appendChild( header );

		// Table
		const table = cel( 'table', 'pgs-table' );

		const thead = document.createElement( 'thead' );
		const hr = document.createElement( 'tr' );

		const thCb = cel( 'th' );
		thCb.style.width = '38px';
		const masterCheck = pgsCheck();
		masterCheck.input.id = 'etg-select-all';
		masterCheck.input.addEventListener( 'change', function ( e ) {
			self.onSelectAll( e.target.checked );
		} );
		thCb.appendChild( masterCheck.label );
		hr.appendChild( thCb );

		hr.appendChild(
			cel( 'th', '', this.strings.colPage || 'Converted page' )
		);
		hr.appendChild(
			cel( 'th', '', this.strings.colSource || 'Source page' )
		);
		const thAct = cel( 'th', '', this.strings.colActions || 'Actions' );
		thAct.style.textAlign = 'right';
		hr.appendChild( thAct );

		thead.appendChild( hr );
		table.appendChild( thead );

		const tbody = document.createElement( 'tbody' );
		this.pages.forEach( function ( page ) {
			const tr = document.createElement( 'tr' );

			// Checkbox
			const tdCb = document.createElement( 'td' );
			const rowCheck = pgsCheck();
			rowCheck.input.value = String( page.id );
			rowCheck.input.dataset.pageId = String( page.id );
			rowCheck.input.addEventListener( 'change', function () {
				self.onRowCheck();
			} );
			tdCb.appendChild( rowCheck.label );
			tr.appendChild( tdCb );

			// Converted page title + type + enhanced flag
			const tdTitle = document.createElement( 'td' );
			const titleWrap = cel( 'div', 'pgs-table__linkstrong' );
			const titleLink = cel( 'a', '', page.title || String( page.id ) );
			titleLink.href =
				self.config.editBaseUrl + String( page.id ) + '&action=edit';
			titleLink.target = '_blank';
			titleWrap.appendChild( titleLink );
			const tl = typeLabel( page.type );
			if ( tl ) {
				titleWrap.appendChild(
					cel( 'span', 'pgs-table__sub', ' ' + tl )
				);
			}
			tdTitle.appendChild( titleWrap );
			if ( page.lastImproved ) {
				const enh = cel( 'div', 'pgs-aienh' );
				enh.innerHTML = '<i data-icon="check"></i>';
				enh.appendChild(
					document.createTextNode( ' ' + ( self.strings.aiEnhancedFlag || 'AI-enhanced' ) )
				);
				tdTitle.appendChild( enh );
			}
			tr.appendChild( tdTitle );

			// Source page
			const tdSource = document.createElement( 'td' );
			if ( page.sourceId ) {
				const srcLink = cel(
					'a',
					'pgs-table__link',
					page.sourceTitle || String( page.sourceId )
				);
				srcLink.href =
					self.config.editBaseUrl +
					String( page.sourceId ) +
					'&action=edit';
				srcLink.target = '_blank';
				tdSource.appendChild( srcLink );
			} else {
				tdSource.className = 'pgs-table__muted';
				tdSource.textContent = '—';
			}
			tr.appendChild( tdSource );

			// Actions: Enhance with AI
			const tdAct = document.createElement( 'td' );
			tdAct.style.textAlign = 'right';
			const actGroup = cel( 'div', 'pgs-rowactions' );

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
				const pill = makeActionPill(
					improveUrl,
					page.lastImproved
						? self.strings.reEnhance || 'Re-enhance'
						: self.strings.enhanceSingle || 'Enhance with AI',
					'ai',
					false
				);
				if ( page.lastImproved ) {
					pill.className = 'pgs-btn pgs-btn--ghost pgs-btn--sm';
				}
				actGroup.appendChild( pill );
			} else {
				const noKeyBtn = makeIconBtn(
					AI_SVG,
					self.strings.enhanceSingle || 'Enhance with AI',
					'ai'
				);
				noKeyBtn.addEventListener( 'click', function () {
					const n = document.getElementById( 'etg-no-api-notice' );
					if ( n ) {
						n.style.display = 'flex';
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
		card.appendChild( table );
		wrap.appendChild( card );

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
			const aiLbl = aiBtn.querySelector( '.pgs-btn__label' );
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
				notice.style.display = 'flex';
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
		const wrap = cel( 'div', 'pgs-stack' );
		wrap.style.gap = 'var(--gap-section)';
		const cfg = this.config;
		const str = this.strings;

		wrap.appendChild(
			this.pageTitle(
				str.bulkTitle || 'Bulk AI Enhancement',
				str.bulkSubtitle ||
					'Each selected item is enhanced one at a time.'
			)
		);

		if ( ! ai.started ) {
			// Pre-flight checklist card
			const card = cel( 'div', 'pgs-card' );
			const pre = cel( 'div', 'pgs-preflight' );

			const head = cel( 'div', 'pgs-preflight__head' );
			head.appendChild(
				cel(
					'span',
					'pgs-eyebrow',
					str.aiReadinessTitle || 'Pre-flight checklist'
				)
			);
			if ( cfg.aiConfigured ) {
				const pill = cel( 'span', 'pgs-pill pgs-pill--success' );
				const pIc = cel( 'span', 'pgs-pill__icon' );
				pIc.innerHTML = '<i data-icon="check"></i>';
				pill.appendChild( pIc );
				pill.appendChild( document.createTextNode( str.ready || 'Ready' ) );
				head.appendChild( pill );
			}
			pre.appendChild( head );

			const apiRow = cel( 'div', 'pgs-preflight__item' );
			const apiMark = cel(
				'span',
				cfg.aiConfigured
					? 'pgs-preflight__ok'
					: 'pgs-preflight__num'
			);
			apiMark.innerHTML = cfg.aiConfigured
				? '<i data-icon="check"></i>'
				: '<i data-icon="x"></i>';
			if ( ! cfg.aiConfigured ) {
				apiMark.style.background = 'var(--error-solid)';
			}
			apiRow.appendChild( apiMark );
			apiRow.appendChild(
				document.createTextNode(
					cfg.aiConfigured
						? str.aiReadinessApiValid || 'API key configured'
						: str.aiReadinessApiInvalid || 'API key not configured'
				)
			);
			pre.appendChild( apiRow );

			const cntRow = cel( 'div', 'pgs-preflight__item' );
			const cntNum = cel(
				'span',
				'pgs-preflight__num',
				String( ai.pages.length )
			);
			cntRow.appendChild( cntNum );
			cntRow.appendChild(
				document.createTextNode(
					fmt(
						str.aiReadinessCredits ||
							'Estimated: ~%1$d API call(s), ~1–2 minutes per item',
						ai.pages.length
					)
				)
			);
			pre.appendChild( cntRow );

			card.appendChild( pre );
			wrap.appendChild( card );

			// Items-to-enhance card (table)
			const listCard = cel( 'div', 'pgs-card' );
			const listHead = cel( 'div', 'pgs-card__header' );
			const lh = cel( 'div' );
			lh.appendChild(
				cel(
					'div',
					'pgs-card__eyebrow',
					fmt( str.queueEyebrow || 'Queue (%1$d)', ai.pages.length )
				)
			);
			lh.appendChild(
				cel( 'div', 'pgs-card__title', str.queueTitle || 'Items to enhance' )
			);
			listHead.appendChild( lh );
			listCard.appendChild( listHead );

			const t = cel( 'table', 'pgs-table' );
			const th = document.createElement( 'thead' );
			const thr = document.createElement( 'tr' );
			thr.appendChild( cel( 'th', '', str.colPage || 'Item' ) );
			thr.appendChild( cel( 'th', '', str.aiImproveType || 'Type' ) );
			thr.appendChild( cel( 'th', '', str.colSource || 'Source page' ) );
			th.appendChild( thr );
			t.appendChild( th );
			const tb = document.createElement( 'tbody' );
			ai.pages.forEach( function ( page ) {
				const fullData = cfg.pages
					? cfg.pages.filter( function ( p ) {
							return p.id === page.targetId;
					  } )[ 0 ]
					: null;
				const tr = document.createElement( 'tr' );
				const tdT = document.createElement( 'td' );
				const tw = cel( 'div', 'pgs-table__linkstrong' );
				const tLink = cel( 'a', '', page.title || String( page.targetId ) );
				tLink.href =
					cfg.editBaseUrl + String( page.targetId ) + '&action=edit';
				tLink.target = '_blank';
				tw.appendChild( tLink );
				tdT.appendChild( tw );
				if ( fullData && fullData.lastImproved ) {
					const enh = cel( 'div', 'pgs-aienh' );
					enh.innerHTML = '<i data-icon="check"></i>';
					enh.appendChild(
						document.createTextNode( ' ' + ( str.aiEnhancedFlag || 'AI-enhanced' ) )
					);
					tdT.appendChild( enh );
				}
				tr.appendChild( tdT );
				tr.appendChild(
					cel( 'td', 'pgs-table__muted', typeLabel( page.type ) || '—' )
				);
				tr.appendChild(
					cel(
						'td',
						'pgs-table__muted',
						fullData && fullData.sourceTitle
							? fullData.sourceTitle
							: '—'
					)
				);
				tb.appendChild( tr );
			} );
			t.appendChild( tb );
			listCard.appendChild( t );
			wrap.appendChild( listCard );

			// Warning banner
			const warnBox = cel( 'div', 'pgs-banner pgs-banner--warning' );
			warnBox.setAttribute( 'role', 'status' );
			const warnIcon = cel( 'span', 'pgs-banner__icon' );
			warnIcon.innerHTML = '<i data-icon="alert-triangle"></i>';
			warnBox.appendChild( warnIcon );
			const warnBody = cel( 'div', 'pgs-banner__body' );
			warnBody.appendChild(
				cel(
					'span',
					'pgs-banner__title',
					str.aiImproveWarningTitle || 'AI credits will be used'
				)
			);
			warnBody.appendChild(
				cel(
					'span',
					'pgs-banner__text',
					str.aiImproveWarning ||
						'This will use AI credits once per selected item. Make sure your API key has sufficient credits before starting.'
				)
			);
			warnBox.appendChild( warnBody );
			wrap.appendChild( warnBox );

			// Actions
			const actions = cel( 'div', 'pgs-wizard__nav pgs-wizard__nav--end' );
			actions.style.borderTop = 'none';
			actions.style.paddingTop = '0';
			actions.style.marginTop = '0';
			actions.style.justifyContent = 'space-between';
			const backBtn = makeIconBtn(
				'',
				str.back || 'Back',
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

		const card = cel( 'div', 'pgs-card pgs-card--flat' );
		const table = cel( 'table', 'pgs-table' );
		const thead2 = document.createElement( 'thead' );
		const hr2 = document.createElement( 'tr' );
		hr2.appendChild( cel( 'th', '', str.colPage || 'Item' ) );
		hr2.appendChild( cel( 'th', '', str.aiImproveType || 'Type' ) );
		hr2.appendChild( cel( 'th', '', str.statusLabel || 'Status' ) );
		const thA = cel( 'th', '', str.colActions || 'Actions' );
		thA.style.textAlign = 'right';
		hr2.appendChild( thA );
		thead2.appendChild( hr2 );
		table.appendChild( thead2 );

		const tbody2 = document.createElement( 'tbody' );
		ai.pages.forEach( function ( page, i ) {
			const tr = document.createElement( 'tr' );
			tr.appendChild( cel( 'td', 'pgs-table__strong', page.title ) );
			tr.appendChild(
				cel( 'td', 'pgs-table__muted', typeLabel( page.type ) || page.type )
			);
			const tdSt = document.createElement( 'td' );
			tdSt.appendChild( self.makeAiStatusBadge( page.status, page.error ) );
			tr.appendChild( tdSt );
			tr.appendChild( self.makeProgressRowActions( page.status, i ) );
			tbody2.appendChild( tr );
		} );
		table.appendChild( tbody2 );
		card.appendChild( table );
		wrap.appendChild( card );

		if ( ai.finished ) {
			const isOk = failed === 0 && skipped === 0;
			const msg = isOk
				? str.aiImproveFinishedOk || 'All items improved successfully.'
				: fmt(
						str.aiImproveFinishedErr ||
							'Finished — %1$d done, %2$d failed, %3$d skipped.',
						done,
						failed,
						skipped
				  );
			const comp = cel(
				'div',
				'pgs-banner ' +
					( isOk ? 'pgs-banner--success' : 'pgs-banner--warning' )
			);
			comp.setAttribute( 'role', 'status' );
			const cIc = cel( 'span', 'pgs-banner__icon' );
			cIc.innerHTML =
				'<i data-icon="' +
				( isOk ? 'check-circle-2' : 'alert-triangle' ) +
				'"></i>';
			comp.appendChild( cIc );
			const cBody = cel( 'div', 'pgs-banner__body' );
			cBody.appendChild( cel( 'span', 'pgs-banner__text', msg ) );
			comp.appendChild( cBody );
			wrap.appendChild( comp );

			const doneAct = cel(
				'div',
				'pgs-wizard__nav pgs-wizard__nav--end'
			);
			doneAct.style.borderTop = 'none';
			doneAct.style.paddingTop = '0';
			doneAct.style.marginTop = '0';
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
		const section = cel( 'div', 'pgs-stack' );

		const progress = cel(
			'div',
			'pgs-progress pgs-progress--lg' +
				( finished ? '' : ' pgs-progress--running' )
		);
		const phead = cel( 'div', 'pgs-progress__head' );
		phead.appendChild(
			cel(
				'span',
				'pgs-progress__value',
				pct >= 100 ? this.strings.complete || 'Complete' : pct + '%'
			)
		);
		progress.appendChild( phead );
		const track = cel( 'div', 'pgs-progress__track' );
		track.setAttribute( 'role', 'progressbar' );
		const fill = cel( 'div', 'pgs-progress__fill pgs-progress__fill--migrate' );
		fill.style.width = pct + '%';
		track.appendChild( fill );
		progress.appendChild( track );
		section.appendChild( progress );

		const chips = cel( 'div', 'pgs-stack' );
		chips.style.flexDirection = 'row';
		chips.style.flexWrap = 'wrap';
		chips.style.gap = 'var(--space-2)';
		function chip( label, variant ) {
			const c = cel( 'span', 'pgs-pill pgs-pill--' + variant );
			const d = cel( 'span', 'pgs-pill__dot' );
			d.setAttribute( 'aria-hidden', 'true' );
			c.appendChild( d );
			c.appendChild( document.createTextNode( label ) );
			return c;
		}
		if ( done > 0 ) {
			chips.appendChild(
				chip(
					fmt( this.strings.aiStatusDone || 'Done (%1$d)', done ),
					'success'
				)
			);
		}
		if ( failed > 0 ) {
			chips.appendChild(
				chip(
					fmt( this.strings.aiStatusFailed || 'Failed (%1$d)', failed ),
					'error'
				)
			);
		}
		if ( skipped > 0 ) {
			chips.appendChild(
				chip(
					fmt(
						this.strings.aiStatusSkipped || 'Skipped (%1$d)',
						skipped
					),
					'neutral'
				)
			);
		}
		if ( pending > 0 ) {
			chips.appendChild(
				chip(
					fmt(
						this.strings.aiStatusPending || 'Pending (%1$d)',
						pending
					),
					'info'
				)
			);
		}
		section.appendChild( chips );

		if ( failed > 0 && ! finished ) {
			const note = cel( 'div', 'pgs-banner pgs-banner--warning' );
			note.setAttribute( 'role', 'status' );
			const nIc = cel( 'span', 'pgs-banner__icon' );
			nIc.innerHTML = '<i data-icon="alert-triangle"></i>';
			note.appendChild( nIc );
			const nBody = cel( 'div', 'pgs-banner__body' );
			nBody.appendChild(
				cel(
					'span',
					'pgs-banner__text',
					this.strings.aiImprovePaused ||
						'Paused — an item failed. Review the error below, then skip or retry to continue.'
				)
			);
			note.appendChild( nBody );
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
		tdAct.style.textAlign = 'right';
		const group = cel( 'div', 'pgs-rowactions' );

		if ( status === 'failed' ) {
			const skipBtn = makeIconBtn( '', this.strings.skip || 'Skip', 'view' );
			skipBtn.addEventListener( 'click', function () {
				self.skipAiImprovePage( index );
			} );
			group.appendChild( skipBtn );

			const retryBtn = makeIconBtn(
				'',
				this.strings.retry || 'Retry',
				'retry'
			);
			retryBtn.addEventListener( 'click', function () {
				self.retryAiImprovePage( index );
			} );
			group.appendChild( retryBtn );
		} else if ( status === 'processing' ) {
			group.appendChild( this.makeRowSpinner() );
		}

		tdAct.appendChild( group );
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

		const accentColor = '#4f44dd';
		const overlay = document.createElement( 'div' );
		overlay.id = 'ele2gb-bulk-ai-overlay';
		overlay.style.cssText =
			'position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(12,16,24,0.55);z-index:999999;display:flex;align-items:center;justify-content:center;font-family:"Hanken Grotesk",system-ui,sans-serif;';

		const card = document.createElement( 'div' );
		card.style.cssText =
			'background:#fff;border-radius:12px;padding:40px 48px;max-width:480px;width:90%;text-align:center;box-shadow:0 8px 40px rgba(12,16,24,0.18);';

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
			'margin:0 0 8px;font-size:18px;color:#151b26;font-weight:700;font-family:"Schibsted Grotesk",sans-serif;';
		h3.textContent = title;
		card.appendChild( h3 );

		const sub = document.createElement( 'p' );
		sub.style.cssText = 'margin:0 0 12px;color:#36404f;font-size:13px;';
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
					'font-size:12px;padding:2px 10px;border-radius:999px;transition:background 0.3s,color 0.3s;background:' +
					( idx === 0 ? accentColor : '#e1e7f0' ) +
					';color:' +
					( idx === 0 ? '#fff' : '#36404f' ) +
					';';
				dot.textContent = label;
				dotsWrap.appendChild( dot );
			} );
			card.appendChild( dotsWrap );
			const t1 = setTimeout( function () {
				const d = dotsWrap.children;
				if ( d[ 0 ] ) {
					d[ 0 ].style.background = '#e1e7f0';
					d[ 0 ].style.color = '#36404f';
				}
				if ( d[ 1 ] ) {
					d[ 1 ].style.background = accentColor;
					d[ 1 ].style.color = '#fff';
				}
			}, 6000 );
			const t2 = setTimeout( function () {
				const d = dotsWrap.children;
				if ( d[ 1 ] ) {
					d[ 1 ].style.background = '#e1e7f0';
					d[ 1 ].style.color = '#36404f';
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
		const variants = {
			pending: 'pgs-pill--neutral',
			processing: 'pgs-pill--info pgs-pill--pending',
			done: 'pgs-pill--success',
			failed: 'pgs-pill--error',
			skipped: 'pgs-pill--neutral',
		};
		const badge = cel(
			'span',
			'pgs-pill ' + ( variants[ status ] || 'pgs-pill--neutral' )
		);
		const dot = cel( 'span', 'pgs-pill__dot' );
		dot.setAttribute( 'aria-hidden', 'true' );
		badge.appendChild( dot );
		badge.appendChild(
			document.createTextNode( labels[ status ] || status )
		);
		if ( status === 'failed' && error ) {
			// Render the pill plus an error note in one wrapper for the cell.
			const wrap = cel( 'div', 'pgs-stack' );
			wrap.style.gap = 'var(--space-1)';
			wrap.appendChild( badge );
			const errSpan = cel( 'div', 'pgs-table__meta', error );
			errSpan.style.color = 'var(--error-fg)';
			wrap.appendChild( errSpan );
			return wrap;
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
		c.setAttribute( 'stroke', '#4f44dd' );
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
			'position:fixed;top:0;left:0;right:0;bottom:0;z-index:100000;display:flex;align-items:center;justify-content:center;background:rgba(12,16,24,0.55);padding:20px;box-sizing:border-box;font-family:"Hanken Grotesk",system-ui,sans-serif;';

		const modal = document.createElement( 'div' );
		modal.style.cssText =
			'background:#fff;border-radius:12px;padding:28px 32px;max-width:500px;width:100%;max-height:90vh;overflow-y:auto;box-shadow:0 4px 32px rgba(12,16,24,0.18);box-sizing:border-box;';

		const h2 = document.createElement( 'h2' );
		h2.style.cssText =
			'margin:0 0 4px;font-size:17px;font-weight:600;color:#151b26;font-family:"Schibsted Grotesk",sans-serif;';
		h2.textContent = str.feedbackModalTitle || 'How did AI Enhancement go?';
		modal.appendChild( h2 );

		if ( title ) {
			const sub = cel( 'p', null, title );
			sub.style.cssText = 'margin:0 0 20px;font-size:12px;color:#677489;';
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
			'display:block;font-size:13px;font-weight:500;margin-bottom:6px;color:#151b26;';
		issueWrap.appendChild( issueLbl );
		const issueSelect = document.createElement( 'select' );
		issueSelect.style.cssText =
			'width:100%;padding:7px 10px;border:1px solid #cdd6e4;border-radius:6px;font-size:13px;';
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
			'display:block;font-size:13px;font-weight:500;margin-bottom:6px;color:#151b26;';
		detailWrap.appendChild( detailLbl );
		const detailInput = document.createElement( 'textarea' );
		detailInput.rows = 2;
		detailInput.maxLength = 500;
		detailInput.style.cssText =
			'width:100%;padding:7px 10px;border:1px solid #cdd6e4;border-radius:6px;font-size:13px;box-sizing:border-box;resize:vertical;';
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
			'display:block;font-size:13px;font-weight:500;margin-bottom:6px;color:#151b26;';
		modal.appendChild( noteLbl );
		const noteTA = document.createElement( 'textarea' );
		noteTA.rows = 3;
		noteTA.maxLength = 2000;
		noteTA.style.cssText =
			'width:100%;padding:7px 10px;border:1px solid #cdd6e4;border-radius:6px;font-size:13px;box-sizing:border-box;resize:vertical;margin-bottom:16px;';
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
			'font-size:12px;color:#36404f;line-height:1.5;';
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
			'flex:1;font-size:12px;color:#e0463d;min-width:0;';
		actRow.appendChild( errSpan );
		const cancelBtn = document.createElement( 'button' );
		cancelBtn.type = 'button';
		cancelBtn.className = 'pgs-btn pgs-btn--secondary pgs-btn--sm';
		cancelBtn.textContent = str.feedbackCancel || 'Cancel';
		cancelBtn.addEventListener( 'click', function () {
			self.closeAiFeedbackModal();
		} );
		actRow.appendChild( cancelBtn );
		const submitBtn = document.createElement( 'button' );
		submitBtn.type = 'button';
		submitBtn.className = 'pgs-btn pgs-btn--primary pgs-btn--sm';
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
		if ( window.pgsIcons ) {
			window.pgsIcons.create( modal );
		}

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
		const notice = cel( 'div', null );
		notice.style.cssText =
			'position:fixed;bottom:24px;right:24px;z-index:99999;padding:12px 20px;background:#12a16d;color:#fff;border-radius:8px;font-size:13px;box-shadow:0 2px 12px rgba(12,16,24,0.18);max-width:360px;font-family:"Hanken Grotesk",system-ui,sans-serif;';
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
