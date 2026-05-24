(function () {
    'use strict';

    // ── helpers ───────────────────────────────────────────────────────────────

    function cel(tag, cls, txt) {
        var el = document.createElement(tag);
        if (cls) { el.className = cls; }
        if (txt !== undefined && txt !== null) { el.textContent = String(txt); }
        return el;
    }

    function fmt(str) {
        var args = Array.prototype.slice.call(arguments, 1);
        return str.replace(/%(\d+)\$[sd]/g, function (_, i) {
            var v = args[parseInt(i, 10) - 1];
            return v !== undefined ? String(v) : '';
        });
    }

    var CAMERA_SVG = '<svg width="14" height="14" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M2 7a2 2 0 012-2h1l1.5-2h7L15 5h1a2 2 0 012 2v8a2 2 0 01-2 2H4a2 2 0 01-2-2V7z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/><circle cx="10" cy="11" r="2.5" stroke="currentColor" stroke-width="1.5"/></svg>';
    var AI_SVG    = '<svg width="15" height="15" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M10 2l1.8 5.4H17l-4.4 3.2 1.7 5.1L10 13l-4.3 2.7 1.7-5.1L3 7.4h5.2z" fill="currentColor"/></svg>';

    function shotStatusClass(status) {
        var map = { success: 'generated', failed: 'failed', pending: 'pending' };
        return map[status] || 'none';
    }

    function shotStatusLabel(status, strings) {
        var map = {
            success:       strings.shotGenerated || 'Screenshots ready',
            failed:        strings.shotFailed    || 'Screenshot failed',
            pending:       strings.shotPending   || 'Pending',
            not_generated: strings.shotNone      || 'No screenshots',
        };
        return map[status] || (strings.shotNone || 'No screenshots');
    }

    function makeActionPill(href, label, mod, targetBlank) {
        var a = cel('a', 'ele2gb-action-pill ele2gb-action-pill--' + mod);
        a.href = href;
        if (targetBlank) { a.target = '_blank'; a.rel = 'noopener'; }
        a.appendChild(cel('span', 'ele2gb-action-pill-label', label));
        return a;
    }

    function makeIconBtn(iconHtml, label, mod) {
        var btn = cel('button', 'ele2gb-action-pill ele2gb-action-pill--' + mod);
        btn.type = 'button';
        if (iconHtml) {
            var icon = document.createElement('span');
            icon.className = 'ele2gb-action-pill-icon';
            icon.innerHTML = iconHtml;
            btn.appendChild(icon);
        }
        if (label) { btn.appendChild(cel('span', 'ele2gb-action-pill-label', label)); }
        return btn;
    }

    // ── app ───────────────────────────────────────────────────────────────────

    function EtgAiEnhancement(config) {
        this.config  = config;
        this.strings = config.strings || {};
        this.pages   = config.pages   || [];
        this.state   = {
            selected:  new Set(),
            rowShots:  {},
            aiImprove: null,
            bulkShot:  null,
        };
        this.root = null;
    }

    EtgAiEnhancement.prototype.init = function () {
        this.root = document.getElementById('etg-ai-enhancement-app');
        if (!this.root) { return; }
        this.render();
    };

    EtgAiEnhancement.prototype.render = function () {
        if (!this.root) { return; }
        this.root.innerHTML = '';
        if (this.state.bulkShot) {
            this.root.appendChild(this.renderBulkShotStep());
        } else if (this.state.aiImprove) {
            this.root.appendChild(this.renderAiImproveStep());
        } else {
            this.root.appendChild(this.renderSelectionStep());
        }
    };

    // ── stats bar ─────────────────────────────────────────────────────────────

    EtgAiEnhancement.prototype.renderStatsBar = function () {
        var pages    = this.pages;
        var total    = pages.length;
        var withShot = pages.filter(function (p) { return p.screenshotStatus === 'success'; }).length;
        var enhanced = pages.filter(function (p) { return !!p.lastImproved; }).length;
        var s        = this.strings;

        var bar = cel('div', 'ele2gb-enhancement-stats');

        function tile(value, label, mod) {
            var t = cel('div', 'ele2gb-enhancement-stat' + (mod ? ' ele2gb-enhancement-stat--' + mod : ''));
            t.appendChild(cel('span', 'ele2gb-enhancement-stat-value', String(value)));
            t.appendChild(cel('span', 'ele2gb-enhancement-stat-label', label));
            return t;
        }

        bar.appendChild(tile(total,    s.statTotalPages   || 'Converted Pages',  ''));
        bar.appendChild(tile(withShot, s.statScreenshots  || 'Screenshots Ready', withShot > 0 && withShot === total ? 'success' : ''));
        bar.appendChild(tile(enhanced, s.statAiEnhanced   || 'AI-Enhanced',       enhanced > 0 && enhanced === total ? 'success' : ''));

        return bar;
    };

    // ── selection step ────────────────────────────────────────────────────────

    EtgAiEnhancement.prototype.renderSelectionStep = function () {
        var self = this;
        var wrap = cel('div');

        // Hidden no-API banner
        if (!this.config.aiConfigured) {
            var notice = cel('div', 'ele2gb-alert ele2gb-alert-warning');
            notice.id = 'etg-no-api-notice';
            notice.style.display = 'none';
            var noticeText = document.createTextNode((this.strings.noApiMessage || 'A Claude API key is required.') + ' ');
            var noticeLink = cel('a', '', this.strings.addApiLink || 'Add your API key in Settings');
            noticeLink.href = this.config.settingsUrl || '#';
            notice.appendChild(noticeText);
            notice.appendChild(noticeLink);
            wrap.appendChild(notice);
        }

        // Stats bar
        wrap.appendChild(this.renderStatsBar());

        // Toolbar
        var toolbar = cel('div', 'ele2gb-select-toolbar');

        var selectAllWrap = cel('label', 'ele2gb-master-select-label');
        var selectAllCb   = document.createElement('input');
        selectAllCb.type      = 'checkbox';
        selectAllCb.id        = 'etg-select-all';
        selectAllCb.className = 'ele2gb-master-select-checkbox';
        selectAllCb.addEventListener('change', function (e) { self.onSelectAll(e.target.checked); });
        selectAllWrap.appendChild(selectAllCb);
        selectAllWrap.appendChild(document.createTextNode(' Select all'));
        toolbar.appendChild(selectAllWrap);

        var toolbarRight = cel('div', 'ele2gb-toolbar-right');

        // Bulk Screenshots button
        var bulkShotBtn = makeIconBtn(CAMERA_SVG, this.strings.genScreenshotsBulk || 'Bulk Screenshots', 'screenshot-primary');
        bulkShotBtn.id       = 'etg-bulk-shot-btn';
        bulkShotBtn.disabled = true;
        bulkShotBtn.addEventListener('click', function () { self.onBulkShotClick(); });
        toolbarRight.appendChild(bulkShotBtn);

        // Bulk AI Enhance button
        var bulkAiBtn = makeIconBtn(AI_SVG, this.strings.enhanceSelected || 'Bulk Enhance with AI', 'ai-primary');
        bulkAiBtn.id       = 'etg-bulk-enhance-btn';
        bulkAiBtn.disabled = true;
        bulkAiBtn.addEventListener('click', function () { self.onBulkEnhanceClick(); });
        toolbarRight.appendChild(bulkAiBtn);

        toolbar.appendChild(toolbarRight);
        wrap.appendChild(toolbar);

        // Table
        var tableWrap = cel('div', 'ele2gb-table-wrapper');
        var table     = cel('table', 'ele2gb-wizard-table');

        var thead = document.createElement('thead');
        var hr    = document.createElement('tr');

        var thCb = cel('th');
        thCb.style.width = '36px';
        hr.appendChild(thCb);

        hr.appendChild(cel('th', '', this.strings.colPage || 'Converted Page'));

        var thSrc = cel('th', '', this.strings.colSource || 'Source Page');
        thSrc.style.width = '180px';
        hr.appendChild(thSrc);

        var thShot = cel('th', '', 'Screenshots');
        thShot.style.width = '200px';
        hr.appendChild(thShot);

        var thAct = cel('th', '', this.strings.colActions || 'Actions');
        thAct.style.width = '280px';
        hr.appendChild(thAct);

        thead.appendChild(hr);
        table.appendChild(thead);

        var tbody = document.createElement('tbody');
        this.pages.forEach(function (page) {
            var tr = document.createElement('tr');

            // Checkbox
            var tdCb = document.createElement('td');
            var cb   = document.createElement('input');
            cb.type = 'checkbox';
            cb.value = String(page.id);
            cb.dataset.pageId = String(page.id);
            cb.addEventListener('change', function () { self.onRowCheck(); });
            tdCb.appendChild(cb);
            tr.appendChild(tdCb);

            // Converted page title
            var tdTitle  = document.createElement('td');
            var strong   = cel('strong');
            var titleLink = cel('a', '', page.title || String(page.id));
            titleLink.href   = self.config.editBaseUrl + String(page.id) + '&action=edit';
            titleLink.target = '_blank';
            strong.appendChild(titleLink);
            tdTitle.appendChild(strong);
            if (page.lastImproved) {
                tdTitle.appendChild(cel('div', 'ele2gb-result-meta ele2gb-result-meta--enhanced', '✓ AI-enhanced'));
            }
            tr.appendChild(tdTitle);

            // Source page
            var tdSource = document.createElement('td');
            if (page.sourceId) {
                var srcLink = cel('a', '', page.sourceTitle || String(page.sourceId));
                srcLink.href   = self.config.editBaseUrl + String(page.sourceId) + '&action=edit';
                srcLink.target = '_blank';
                tdSource.appendChild(srcLink);
            } else {
                tdSource.textContent = '—';
            }
            tr.appendChild(tdSource);

            // Screenshot cell (in-place updatable)
            var tdShot = document.createElement('td');
            tdShot.className = 'ele2gb-shot-cell';
            tdShot.dataset.shotPageId = String(page.id);
            self.fillShotCell(tdShot, page);
            tr.appendChild(tdShot);

            // Actions
            var tdAct    = document.createElement('td');
            var actGroup = cel('div', 'ele2gb-action-group');

            if (page.sourcePreviewUrl) {
                actGroup.appendChild(makeActionPill(page.sourcePreviewUrl, 'Source ↗', 'view', true));
            }
            if (page.previewUrl) {
                actGroup.appendChild(makeActionPill(page.previewUrl, 'Preview ↗', 'view', true));
            }

            // Screenshot generate / regenerate button
            (function (p) {
                var rState     = self.state.rowShots[p.id] || {};
                var rStatus    = rState.status !== undefined ? rState.status : p.screenshotStatus;
                var rGen       = !!rState.generating;
                var isRegen    = rStatus === 'success';
                var shotBtn    = makeIconBtn(CAMERA_SVG, isRegen ? (self.strings.shotRegenerate || 'Regenerate') : (self.strings.genScreenshots || 'Generate'), 'screenshot');
                shotBtn.dataset.shotBtnPageId = String(p.id);
                shotBtn.disabled = rGen || !p.sourceId;
                if (!p.sourceId) { shotBtn.title = 'Source page ID missing'; }
                shotBtn.addEventListener('click', function () { self.generateScreenshotSingle(p.id, p.sourceId); });
                actGroup.appendChild(shotBtn);
            }(page));

            if (self.config.aiConfigured && self.config.aiImproveBaseUrl && page.sourceId) {
                var improveUrl = self.config.aiImproveBaseUrl +
                    '&source_id=' + String(page.sourceId) +
                    '&target_id=' + String(page.id);
                actGroup.appendChild(makeActionPill(improveUrl, 'Enhance', 'ai', false));
            } else {
                var noKeyBtn = makeIconBtn('', 'Enhance', 'ai');
                noKeyBtn.addEventListener('click', function () {
                    var n = document.getElementById('etg-no-api-notice');
                    if (n) { n.style.display = 'block'; n.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
                });
                actGroup.appendChild(noKeyBtn);
            }

            tdAct.appendChild(actGroup);
            tr.appendChild(tdAct);
            tbody.appendChild(tr);
        });

        table.appendChild(tbody);
        tableWrap.appendChild(table);
        wrap.appendChild(tableWrap);

        return wrap;
    };

    // ── screenshot cell fill / refresh ────────────────────────────────────────

    EtgAiEnhancement.prototype.fillShotCell = function (td, page) {
        var self       = this;
        var rowState   = this.state.rowShots[page.id] || {};
        var generating = !!rowState.generating;
        var status     = rowState.status !== undefined ? rowState.status : page.screenshotStatus;
        var thumb      = rowState.thumb  !== undefined ? rowState.thumb  : page.screenshotThumb;
        var errorMsg   = rowState.error  || '';

        td.innerHTML = '';
        var inner = cel('div', 'ele2gb-shot-cell-inner');

        if (generating) {
            var genWrap = cel('div', 'ele2gb-shot-generating');
            genWrap.appendChild(this.makeRowSpinner());
            genWrap.appendChild(cel('span', 'ele2gb-shot-generating-label', this.strings.shotGenerating || 'Generating…'));
            inner.appendChild(genWrap);
        } else {
            if (thumb) {
                var thumbWrap = cel('div', 'ele2gb-row-thumb-wrap');
                var img = document.createElement('img');
                img.src = thumb;
                img.alt = '';
                img.className = 'ele2gb-row-thumb';
                thumbWrap.appendChild(img);
                inner.appendChild(thumbWrap);
            }

            var shotClass = shotStatusClass(status);
            inner.appendChild(cel('span', 'ele2gb-shot-status ele2gb-shot-status--' + shotClass,
                shotStatusLabel(status, this.strings)));

            if (errorMsg && status === 'failed') {
                inner.appendChild(cel('div', 'ele2gb-shot-error-msg', errorMsg));
            }
        }

        td.appendChild(inner);
    };

    EtgAiEnhancement.prototype.updateRowShotCell = function (pageId) {
        // Refresh the status/thumb cell
        var td = document.querySelector('.ele2gb-shot-cell[data-shot-page-id="' + String(pageId) + '"]');
        if (td) {
            var page = null;
            for (var i = 0; i < this.pages.length; i++) {
                if (this.pages[i].id === pageId) { page = this.pages[i]; break; }
            }
            if (page) { this.fillShotCell(td, page); }
        }

        // Refresh the Generate / Regenerate button in the Actions column
        var btn = document.querySelector('[data-shot-btn-page-id="' + String(pageId) + '"]');
        if (btn) {
            var rs         = this.state.rowShots[pageId] || {};
            var generating = !!rs.generating;
            var status     = rs.status !== undefined ? rs.status : '';
            var lbl        = btn.querySelector('.ele2gb-action-pill-label');
            if (lbl) {
                lbl.textContent = status === 'success'
                    ? (this.strings.shotRegenerate || 'Regenerate')
                    : (this.strings.genScreenshots || 'Generate');
            }
            btn.disabled = generating;
        }
    };

    // ── single screenshot AJAX ────────────────────────────────────────────────

    EtgAiEnhancement.prototype.generateScreenshotSingle = function (pageId, sourceId) {
        var self = this;
        this.state.rowShots[pageId] = { generating: true };
        this.updateRowShotCell(pageId);

        var fd = new FormData();
        fd.append('action',    'ele2gb_generate_screenshots_single');
        fd.append('nonce',     this.config.screenshotNonce || '');
        fd.append('source_id', String(sourceId));
        fd.append('target_id', String(pageId));

        fetch(this.config.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.success) {
                    self.state.rowShots[pageId] = { status: data.data.status, thumb: data.data.thumb };
                } else {
                    self.state.rowShots[pageId] = {
                        status: 'failed',
                        error:  (data.data && data.data.message) ? data.data.message : 'Screenshot generation failed.',
                    };
                }
                self.updateRowShotCell(pageId);
            })
            .catch(function (err) {
                self.state.rowShots[pageId] = { status: 'failed', error: err.message || 'Screenshot generation failed.' };
                self.updateRowShotCell(pageId);
            });
    };

    // ── select all / row check ────────────────────────────────────────────────

    EtgAiEnhancement.prototype.onSelectAll = function (checked) {
        this.state.selected.clear();
        document.querySelectorAll('#etg-ai-enhancement-app input[type=checkbox][data-page-id]').forEach(function (cb) {
            cb.checked = checked;
        });
        if (checked) {
            var self = this;
            this.pages.forEach(function (p) { self.state.selected.add(p.id); });
        }
        this.updateBulkButtons();
    };

    EtgAiEnhancement.prototype.onRowCheck = function () {
        var self = this;
        this.state.selected.clear();
        document.querySelectorAll('#etg-ai-enhancement-app input[type=checkbox][data-page-id]').forEach(function (cb) {
            if (cb.checked) { self.state.selected.add(Number(cb.value)); }
        });
        this.updateBulkButtons();
    };

    EtgAiEnhancement.prototype.updateBulkButtons = function () {
        var count = this.state.selected.size;

        var aiBtn = document.getElementById('etg-bulk-enhance-btn');
        if (aiBtn) {
            aiBtn.disabled = count === 0;
            var aiLbl = aiBtn.querySelector('.ele2gb-action-pill-label');
            if (aiLbl) {
                aiLbl.textContent = count > 0
                    ? fmt(this.strings.enhanceSelectedCount || 'Enhance %1$d Pages with AI', count)
                    : (this.strings.enhanceSelected || 'Bulk Enhance with AI');
            }
        }

        var shotBtn = document.getElementById('etg-bulk-shot-btn');
        if (shotBtn) {
            shotBtn.disabled = count === 0;
            var shotLbl = shotBtn.querySelector('.ele2gb-action-pill-label');
            if (shotLbl) {
                shotLbl.textContent = count > 0
                    ? fmt(this.strings.genScreenshotsCount || 'Screenshot %1$d Pages', count)
                    : (this.strings.genScreenshotsBulk || 'Bulk Screenshots');
            }
        }
    };

    // ── bulk screenshot flow ──────────────────────────────────────────────────

    EtgAiEnhancement.prototype.onBulkShotClick = function () {
        if (this.state.selected.size === 0) { return; }
        this.initBulkShot();
    };

    EtgAiEnhancement.prototype.initBulkShot = function () {
        var selectedIds = this.state.selected;
        var pages = this.pages
            .filter(function (p) { return selectedIds.has(p.id); })
            .map(function (p) {
                return { sourceId: p.sourceId, targetId: p.id, title: p.title || String(p.id), status: 'pending', error: '' };
            });
        this.state.bulkShot = { pages: pages, currentIndex: 0, started: false, finished: false };
        this.render();
    };

    EtgAiEnhancement.prototype.startBulkShot = function () {
        var bs = this.state.bulkShot;
        if (!bs || bs.started) { return; }
        bs.started = true;
        this.processBulkShotPage(0);
    };

    EtgAiEnhancement.prototype.processBulkShotPage = function (index) {
        var self = this;
        var bs   = this.state.bulkShot;
        if (!bs || index >= bs.pages.length) {
            if (bs) { bs.finished = true; }
            this.render();
            return;
        }

        bs.currentIndex        = index;
        bs.pages[index].status = 'processing';
        bs.pages[index].error  = '';
        this.render();

        var page = bs.pages[index];
        this.showOverlay(
            this.strings.bulkShotTitle || 'Generating Screenshots…',
            page.title, index + 1, bs.pages.length, null
        );

        var fd = new FormData();
        fd.append('action',    'ele2gb_generate_screenshots_single');
        fd.append('nonce',     this.config.screenshotNonce || '');
        fd.append('source_id', String(page.sourceId));
        fd.append('target_id', String(page.targetId));

        fetch(this.config.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                self.hideOverlay();
                if (data.success) {
                    bs.pages[index].status = 'done';
                    self.state.rowShots[page.targetId] = { status: data.data.status, thumb: data.data.thumb };
                    self.render();
                    self.advanceBulkShot(index);
                } else {
                    bs.pages[index].status = 'failed';
                    bs.pages[index].error  = (data.data && data.data.message) ? String(data.data.message) : 'Screenshot generation failed.';
                    self.render();
                }
            })
            .catch(function (err) {
                self.hideOverlay();
                bs.pages[index].status = 'failed';
                bs.pages[index].error  = err.message || 'Screenshot generation failed.';
                self.render();
            });
    };

    EtgAiEnhancement.prototype.advanceBulkShot = function (index) {
        var bs   = this.state.bulkShot;
        var next = index + 1;
        if (!bs || next >= bs.pages.length) {
            if (bs) { bs.finished = true; this.render(); }
            return;
        }
        this.processBulkShotPage(next);
    };

    EtgAiEnhancement.prototype.skipBulkShotPage = function (index) {
        var bs = this.state.bulkShot;
        if (!bs) { return; }
        bs.pages[index].status = 'skipped';
        this.render();
        this.advanceBulkShot(index);
    };

    EtgAiEnhancement.prototype.retryBulkShotPage = function (index) {
        this.processBulkShotPage(index);
    };

    // ── render bulk screenshot step ───────────────────────────────────────────

    EtgAiEnhancement.prototype.renderBulkShotStep = function () {
        var self = this;
        var bs   = this.state.bulkShot;
        var wrap = cel('div', 'ele2gb-ai-improve-step');

        if (!bs.started) {
            var header     = cel('div', 'ele2gb-ai-readiness-panel');
            var headerRow  = cel('div', 'ele2gb-ai-readiness-header');
            headerRow.appendChild(cel('h3', 'ele2gb-ai-readiness-title', 'Screenshot Generation'));
            header.appendChild(headerRow);

            var cntRow  = cel('div', 'ele2gb-ai-readiness-row');
            var cntIcon = cel('div', 'ele2gb-ai-readiness-icon ele2gb-ai-readiness-icon--info', String(bs.pages.length));
            var cntLbl  = cel('span', 'ele2gb-ai-readiness-status', bs.pages.length + ' page(s) selected for screenshot generation');
            cntRow.appendChild(cntIcon);
            cntRow.appendChild(cntLbl);
            header.appendChild(cntRow);
            wrap.appendChild(header);

            var pagesSection = cel('div', 'ele2gb-preflight-pages-section');
            pagesSection.appendChild(cel('p', 'ele2gb-preflight-section-label', 'Pages to screenshot (' + bs.pages.length + ')'));
            var pagesList = cel('div', 'ele2gb-preflight-pages');

            bs.pages.forEach(function (page) {
                var fullData  = self.config.pages ? self.config.pages.filter(function (p) { return p.id === page.targetId; })[0] : null;
                var rowState  = self.state.rowShots[page.targetId] || {};
                var thumb     = rowState.thumb !== undefined ? rowState.thumb : (fullData ? fullData.screenshotThumb : '');
                var card      = cel('div', 'ele2gb-preflight-page-card');
                var thumbWrap = cel('div', 'ele2gb-preflight-thumb');

                if (thumb) {
                    var img = document.createElement('img');
                    img.src = thumb; img.alt = ''; img.className = 'ele2gb-preflight-thumb-img';
                    thumbWrap.appendChild(img);
                } else {
                    thumbWrap.classList.add('ele2gb-preflight-thumb--empty');
                    thumbWrap.appendChild(cel('span', 'ele2gb-preflight-thumb-icon', ''));
                }
                card.appendChild(thumbWrap);

                var body = cel('div', 'ele2gb-preflight-page-body');
                var titleRow = cel('div', 'ele2gb-preflight-page-title');
                var tLink = cel('a', '', page.title || String(page.targetId));
                tLink.href = self.config.editBaseUrl + String(page.targetId) + '&action=edit';
                tLink.target = '_blank';
                titleRow.appendChild(tLink);
                body.appendChild(titleRow);

                if (fullData && fullData.sourceTitle) {
                    body.appendChild(cel('div', 'ele2gb-preflight-page-source', 'Source: ' + fullData.sourceTitle));
                }
                card.appendChild(body);
                pagesList.appendChild(card);
            });

            pagesSection.appendChild(pagesList);
            wrap.appendChild(pagesSection);

            var warnBox  = cel('div', 'ele2gb-ai-warning-notice');
            var warnIcon = cel('div', 'ele2gb-ai-warning-icon ele2gb-ai-warning-icon--shot', '');
            var warnText = cel('div', 'ele2gb-ai-warning-text');
            warnText.appendChild(cel('strong', '', 'Screenshots will be captured'));
            warnText.appendChild(cel('p', '', 'This calls the screenshot service for each page — both Elementor original and Gutenberg version. Allow 30–60 seconds per page.'));
            warnBox.appendChild(warnIcon);
            warnBox.appendChild(warnText);
            wrap.appendChild(warnBox);

            var actions = cel('div', 'ele2gb-results-actions');
            var backBtn = makeIconBtn('', '← ' + (this.strings.back || 'Back'), 'view-secondary');
            backBtn.addEventListener('click', function () { self.state.bulkShot = null; self.render(); });
            actions.appendChild(backBtn);

            var startBtn = makeIconBtn(CAMERA_SVG, 'Start Screenshot Generation', 'screenshot-primary');
            startBtn.addEventListener('click', function () { self.startBulkShot(); });
            actions.appendChild(startBtn);

            wrap.appendChild(actions);
            return wrap;
        }

        // Progress view
        var done    = bs.pages.filter(function (p) { return p.status === 'done';    }).length;
        var failed  = bs.pages.filter(function (p) { return p.status === 'failed';  }).length;
        var skipped = bs.pages.filter(function (p) { return p.status === 'skipped'; }).length;
        var pending = bs.pages.filter(function (p) { return p.status === 'pending'; }).length;
        var total   = bs.pages.length;
        var pct     = total > 0 ? Math.round(((done + failed + skipped) / total) * 100) : 0;

        wrap.appendChild(this.renderProgressSection(done, failed, skipped, pending, pct, bs.finished));

        var table  = cel('table', 'ele2gb-wizard-table ele2gb-ai-results-table');
        var thead2 = document.createElement('thead');
        var hr2    = document.createElement('tr');
        hr2.appendChild(cel('th', '', 'Page'));
        hr2.appendChild(cel('th', '', 'Status'));
        hr2.appendChild(cel('th', '', 'Actions'));
        thead2.appendChild(hr2);
        table.appendChild(thead2);

        var tbody2 = document.createElement('tbody');
        bs.pages.forEach(function (page, i) {
            var tr = document.createElement('tr');
            tr.className = 'ele2gb-ai-row--' + page.status;
            tr.appendChild(cel('td', 'ele2gb-ai-title-cell', page.title));
            var tdSt = document.createElement('td');
            tdSt.appendChild(self.makeAiStatusBadge(page.status, page.error));
            tr.appendChild(tdSt);
            tr.appendChild(self.makeProgressRowActions(page.status, i, 'shot'));
            tbody2.appendChild(tr);
        });
        table.appendChild(tbody2);
        wrap.appendChild(table);

        if (bs.finished) {
            var msg  = failed === 0 && skipped === 0 ? 'All screenshots generated successfully.' : 'Finished — ' + done + ' done, ' + failed + ' failed, ' + skipped + ' skipped.';
            var comp = cel('div', 'ele2gb-ai-completion ' + (failed === 0 && skipped === 0 ? 'ele2gb-ai-completion--success' : 'ele2gb-ai-completion--partial'));
            comp.appendChild(cel('p', '', msg));
            wrap.appendChild(comp);

            var doneAct = cel('div', 'ele2gb-results-actions');
            var backBtn2 = makeIconBtn('', '← ' + (this.strings.backToList || 'Back to list'), 'view-secondary');
            backBtn2.addEventListener('click', function () {
                self.state.bulkShot = null;
                self.state.selected.clear();
                self.render();
            });
            doneAct.appendChild(backBtn2);
            wrap.appendChild(doneAct);
        }

        return wrap;
    };

    // ── bulk AI improve flow ──────────────────────────────────────────────────

    EtgAiEnhancement.prototype.onBulkEnhanceClick = function () {
        if (!this.config.aiConfigured) {
            var notice = document.getElementById('etg-no-api-notice');
            if (notice) { notice.style.display = 'block'; notice.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
            return;
        }
        if (this.state.selected.size === 0) { return; }
        this.initAiImprove();
    };

    EtgAiEnhancement.prototype.initAiImprove = function () {
        var selectedIds = this.state.selected;
        var pages = this.pages
            .filter(function (p) { return selectedIds.has(p.id); })
            .map(function (p) {
                return { sourceId: p.sourceId, targetId: p.id, title: p.title || String(p.id), type: p.type || 'page', status: 'pending', error: '' };
            });
        this.state.aiImprove = { pages: pages, currentIndex: 0, started: false, finished: false };
        this.render();
    };

    EtgAiEnhancement.prototype.startAiImprove = function () {
        var ai = this.state.aiImprove;
        if (!ai || ai.started) { return; }
        ai.started = true;
        this.processAiImprovePage(0);
    };

    EtgAiEnhancement.prototype.processAiImprovePage = function (index) {
        var self = this;
        var ai   = this.state.aiImprove;
        if (!ai || index >= ai.pages.length) {
            if (ai) { ai.finished = true; }
            this.render();
            return;
        }

        ai.currentIndex        = index;
        ai.pages[index].status = 'processing';
        ai.pages[index].error  = '';
        this.render();

        var page = ai.pages[index];
        this.showOverlay(
            this.strings.aiLoaderTitle || 'Improving with AI…',
            page.title, index + 1, ai.pages.length,
            [
                this.strings.aiStageAnalyzing  || 'Analyzing…',
                this.strings.aiStageGenerating || 'Generating…',
                this.strings.aiStageSaving     || 'Saving…',
            ]
        );

        var fd = new FormData();
        fd.append('action',    'ele2gb_ai_improve_single');
        fd.append('nonce',     this.config.aiImproveNonce);
        fd.append('source_id', String(page.sourceId));
        fd.append('target_id', String(page.targetId));

        fetch(this.config.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                self.hideOverlay();
                if (data.success) {
                    ai.pages[index].status = 'done';
                    self.render();
                    self.advanceAiImprove(index);
                } else {
                    ai.pages[index].status = 'failed';
                    ai.pages[index].error  = (data.data && data.data.message) ? String(data.data.message) : (self.strings.aiImproveError || 'An unexpected error occurred.');
                    self.render();
                }
            })
            .catch(function (err) {
                self.hideOverlay();
                ai.pages[index].status = 'failed';
                ai.pages[index].error  = err.message || (self.strings.aiImproveError || 'An unexpected error occurred.');
                self.render();
            });
    };

    EtgAiEnhancement.prototype.advanceAiImprove = function (index) {
        var ai   = this.state.aiImprove;
        var next = index + 1;
        if (!ai || next >= ai.pages.length) {
            if (ai) { ai.finished = true; this.render(); }
            return;
        }
        this.processAiImprovePage(next);
    };

    EtgAiEnhancement.prototype.skipAiImprovePage = function (index) {
        var ai = this.state.aiImprove;
        if (!ai) { return; }
        ai.pages[index].status = 'skipped';
        this.render();
        this.advanceAiImprove(index);
    };

    EtgAiEnhancement.prototype.retryAiImprovePage = function (index) {
        this.processAiImprovePage(index);
    };

    // ── render AI improve step ────────────────────────────────────────────────

    EtgAiEnhancement.prototype.renderAiImproveStep = function () {
        var self      = this;
        var ai        = this.state.aiImprove;
        var wrap      = cel('div', 'ele2gb-ai-improve-step');
        var cfg       = this.config;
        var str       = this.strings;

        if (!ai.started) {
            var readinessPanel = cel('div', 'ele2gb-ai-readiness-panel');
            var rHeader = cel('div', 'ele2gb-ai-readiness-header');
            rHeader.appendChild(cel('h3', 'ele2gb-ai-readiness-title', str.aiReadinessTitle || 'Pre-flight Checklist'));
            if (cfg.aiConfigured) { rHeader.appendChild(cel('span', 'ele2gb-ai-readiness-all-ready', '✓ Ready')); }
            readinessPanel.appendChild(rHeader);

            var apiRow  = cel('div', 'ele2gb-ai-readiness-row');
            var apiIcon = cel('div', 'ele2gb-ai-readiness-icon ' + (cfg.aiConfigured ? 'ele2gb-ai-readiness-icon--ok' : 'ele2gb-ai-readiness-icon--error'), cfg.aiConfigured ? '✓' : '✗');
            var apiLbl  = cel('span', 'ele2gb-ai-readiness-status' + (cfg.aiConfigured ? '' : ' is-invalid'), cfg.aiConfigured ? (str.aiReadinessApiValid || 'API key configured') : (str.aiReadinessApiInvalid || 'API key not configured'));
            apiRow.appendChild(apiIcon); apiRow.appendChild(apiLbl);
            readinessPanel.appendChild(apiRow);

            var cntRow  = cel('div', 'ele2gb-ai-readiness-row');
            var cntIcon = cel('div', 'ele2gb-ai-readiness-icon ele2gb-ai-readiness-icon--info', String(ai.pages.length));
            var cntLbl  = cel('span', 'ele2gb-ai-readiness-status', fmt(str.aiReadinessCredits || 'Estimated: ~%1$d API call(s), ~1–2 minutes per item', ai.pages.length));
            cntRow.appendChild(cntIcon); cntRow.appendChild(cntLbl);
            readinessPanel.appendChild(cntRow);
            wrap.appendChild(readinessPanel);

            var pagesSection = cel('div', 'ele2gb-preflight-pages-section');
            pagesSection.appendChild(cel('p', 'ele2gb-preflight-section-label', 'Pages to enhance (' + ai.pages.length + ')'));
            var pagesList = cel('div', 'ele2gb-preflight-pages');

            ai.pages.forEach(function (page) {
                var fullData  = cfg.pages ? cfg.pages.filter(function (p) { return p.id === page.targetId; })[0] : null;
                var rowState  = self.state.rowShots[page.targetId] || {};
                var thumb     = rowState.thumb !== undefined ? rowState.thumb : (fullData ? fullData.screenshotThumb : '');
                var card      = cel('div', 'ele2gb-preflight-page-card');
                var thumbWrap = cel('div', 'ele2gb-preflight-thumb');

                if (thumb) {
                    var img = document.createElement('img');
                    img.src = thumb; img.alt = ''; img.className = 'ele2gb-preflight-thumb-img';
                    thumbWrap.appendChild(img);
                } else {
                    thumbWrap.classList.add('ele2gb-preflight-thumb--empty');
                    thumbWrap.appendChild(cel('span', '', '🖼'));
                }
                card.appendChild(thumbWrap);

                var body     = cel('div', 'ele2gb-preflight-page-body');
                var titleRow = cel('div', 'ele2gb-preflight-page-title');
                var tLink    = cel('a', '', page.title || String(page.targetId));
                tLink.href = cfg.editBaseUrl + String(page.targetId) + '&action=edit';
                tLink.target = '_blank';
                titleRow.appendChild(tLink);
                if (fullData && fullData.lastImproved) { titleRow.appendChild(cel('span', 'ele2gb-preflight-improved-badge', '✓ Enhanced')); }
                body.appendChild(titleRow);

                if (fullData && fullData.sourceTitle) {
                    var srcRow = cel('div', 'ele2gb-preflight-page-source');
                    srcRow.textContent = 'Source: ';
                    if (fullData.sourcePreviewUrl) {
                        var srcLink = cel('a', '', fullData.sourceTitle);
                        srcLink.href = cfg.editBaseUrl + String(page.sourceId) + '&action=edit';
                        srcLink.target = '_blank';
                        srcRow.appendChild(srcLink);
                    } else {
                        srcRow.textContent += fullData.sourceTitle;
                    }
                    body.appendChild(srcRow);
                }

                var metaRow = cel('div', 'ele2gb-preflight-page-meta');
                if (fullData) {
                    var sc = shotStatusClass(fullData.screenshotStatus);
                    metaRow.appendChild(cel('span', 'ele2gb-shot-status ele2gb-shot-status--' + sc, shotStatusLabel(fullData.screenshotStatus, str)));
                }

                var linksWrap = cel('div', 'ele2gb-preflight-page-links');
                if (fullData && fullData.sourcePreviewUrl) { linksWrap.appendChild(makeActionPill(fullData.sourcePreviewUrl, 'View Source ↗', 'view', true)); }
                if (fullData && fullData.previewUrl)       { linksWrap.appendChild(makeActionPill(fullData.previewUrl, 'Preview ↗', 'view', true)); }
                if (cfg.aiImproveBaseUrl && page.sourceId) {
                    var aiUrl = cfg.aiImproveBaseUrl + '&source_id=' + String(page.sourceId) + '&target_id=' + String(page.targetId);
                    linksWrap.appendChild(makeActionPill(aiUrl, 'AI Page →', 'ai', false));
                }

                body.appendChild(metaRow);
                body.appendChild(linksWrap);
                card.appendChild(body);
                pagesList.appendChild(card);
            });

            pagesSection.appendChild(pagesList);
            wrap.appendChild(pagesSection);

            var warnBox  = cel('div', 'ele2gb-ai-warning-notice');
            var warnIcon = cel('div', 'ele2gb-ai-warning-icon', '⚠');
            var warnText = cel('div', 'ele2gb-ai-warning-text');
            warnText.appendChild(cel('strong', '', str.aiImproveWarningTitle || 'AI credits will be used'));
            warnText.appendChild(cel('p', '', str.aiImproveWarning || 'This will use AI credits once per selected item. Make sure your API key has sufficient credits before starting.'));
            warnBox.appendChild(warnIcon); warnBox.appendChild(warnText);
            wrap.appendChild(warnBox);

            var actions = cel('div', 'ele2gb-results-actions');
            var backBtn = makeIconBtn('', '← ' + (str.back || 'Back'), 'view-secondary');
            backBtn.addEventListener('click', function () { self.state.aiImprove = null; self.render(); });
            actions.appendChild(backBtn);

            var startBtn = makeIconBtn(AI_SVG, str.aiImproveStart || 'Start AI Enhancement', 'ai-primary');
            startBtn.disabled = !cfg.aiConfigured;
            startBtn.addEventListener('click', function () { self.startAiImprove(); });
            actions.appendChild(startBtn);

            wrap.appendChild(actions);
            return wrap;
        }

        // Progress view
        var done    = ai.pages.filter(function (p) { return p.status === 'done';    }).length;
        var failed  = ai.pages.filter(function (p) { return p.status === 'failed';  }).length;
        var skipped = ai.pages.filter(function (p) { return p.status === 'skipped'; }).length;
        var pending = ai.pages.filter(function (p) { return p.status === 'pending'; }).length;
        var total   = ai.pages.length;
        var pct     = total > 0 ? Math.round(((done + failed + skipped) / total) * 100) : 0;

        wrap.appendChild(this.renderProgressSection(done, failed, skipped, pending, pct, ai.finished));

        var table  = cel('table', 'ele2gb-wizard-table ele2gb-ai-results-table');
        var thead2 = document.createElement('thead');
        var hr2    = document.createElement('tr');
        hr2.appendChild(cel('th', '', this.strings.colPage || 'Page'));
        hr2.appendChild(cel('th', '', this.strings.aiImproveType || 'Type'));
        hr2.appendChild(cel('th', '', 'Status'));
        hr2.appendChild(cel('th', '', 'Actions'));
        thead2.appendChild(hr2);
        table.appendChild(thead2);

        var tbody2 = document.createElement('tbody');
        ai.pages.forEach(function (page, i) {
            var tr = document.createElement('tr');
            tr.className = 'ele2gb-ai-row--' + page.status;
            tr.appendChild(cel('td', 'ele2gb-ai-title-cell', page.title));
            tr.appendChild(cel('td', '', page.type));
            var tdSt = document.createElement('td');
            tdSt.appendChild(self.makeAiStatusBadge(page.status, page.error));
            tr.appendChild(tdSt);
            tr.appendChild(self.makeProgressRowActions(page.status, i, 'ai'));
            tbody2.appendChild(tr);
        });
        table.appendChild(tbody2);
        wrap.appendChild(table);

        if (ai.finished) {
            var msg  = failed === 0 && skipped === 0 ? (str.aiImproveFinishedOk || 'All items improved successfully.') : fmt(str.aiImproveFinishedErr || 'Finished — %1$d done, %2$d failed, %3$d skipped.', done, failed, skipped);
            var comp = cel('div', 'ele2gb-ai-completion ' + (failed === 0 && skipped === 0 ? 'ele2gb-ai-completion--success' : 'ele2gb-ai-completion--partial'));
            comp.appendChild(cel('p', '', msg));
            wrap.appendChild(comp);

            var doneAct  = cel('div', 'ele2gb-results-actions');
            var backBtn2 = makeIconBtn('', this.strings.backToList || 'Back to list', 'view-secondary');
            backBtn2.addEventListener('click', function () {
                self.state.aiImprove = null;
                self.state.selected.clear();
                self.render();
            });
            doneAct.appendChild(backBtn2);
            wrap.appendChild(doneAct);
        }

        return wrap;
    };

    // ── shared progress section renderer ──────────────────────────────────────

    EtgAiEnhancement.prototype.renderProgressSection = function (done, failed, skipped, pending, pct, finished) {
        var section = cel('div', 'ele2gb-progress-section');
        var bar     = cel('div', 'ele2gb-progress-bar');
        var fill    = cel('div', 'ele2gb-progress-fill');
        fill.style.width = pct + '%';
        bar.appendChild(fill);
        section.appendChild(bar);

        var chips = cel('div', 'ele2gb-status-chips');
        if (done    > 0) { chips.appendChild(cel('span', 'ele2gb-status-chip ele2gb-status-chip--done',    fmt(this.strings.aiStatusDone    || 'Done (%1$d)',    done)));    }
        if (failed  > 0) { chips.appendChild(cel('span', 'ele2gb-status-chip ele2gb-status-chip--failed',  fmt(this.strings.aiStatusFailed  || 'Failed (%1$d)', failed)));  }
        if (skipped > 0) { chips.appendChild(cel('span', 'ele2gb-status-chip ele2gb-status-chip--skipped', fmt(this.strings.aiStatusSkipped || 'Skipped (%1$d)',skipped))); }
        if (pending > 0) { chips.appendChild(cel('span', 'ele2gb-status-chip ele2gb-status-chip--pending', fmt(this.strings.aiStatusPending || 'Pending (%1$d)',pending))); }
        section.appendChild(chips);

        if (failed > 0 && !finished) {
            var note = cel('div', 'ele2gb-paused-notice');
            note.appendChild(cel('p', '', this.strings.aiImprovePaused || 'Paused — a page failed. Review the error below, then skip or retry to continue.'));
            section.appendChild(note);
        }
        return section;
    };

    // ── shared progress row actions ───────────────────────────────────────────

    EtgAiEnhancement.prototype.makeProgressRowActions = function (status, index, mode) {
        var self  = this;
        var tdAct = document.createElement('td');

        if (status === 'failed') {
            var skipBtn = makeIconBtn('', this.strings.skip || 'Skip', 'view');
            skipBtn.addEventListener('click', function () {
                if (mode === 'shot') { self.skipBulkShotPage(index); }
                else                { self.skipAiImprovePage(index); }
            });
            tdAct.appendChild(skipBtn);

            var retryBtn = makeIconBtn('', this.strings.retry || 'Retry', 'retry');
            retryBtn.style.marginLeft = '6px';
            retryBtn.addEventListener('click', function () {
                if (mode === 'shot') { self.retryBulkShotPage(index); }
                else                 { self.retryAiImprovePage(index); }
            });
            tdAct.appendChild(retryBtn);
        } else if (status === 'processing') {
            tdAct.appendChild(this.makeRowSpinner());
        }

        return tdAct;
    };

    // ── generic overlay ───────────────────────────────────────────────────────

    EtgAiEnhancement.prototype.showOverlay = function (title, subtitle, current, total, stages) {
        this.hideOverlay();

        if (!document.getElementById('etg-spin-style')) {
            var style = document.createElement('style');
            style.id  = 'etg-spin-style';
            style.textContent = '@keyframes etg-spin{from{transform:rotate(0deg)}to{transform:rotate(360deg)}}';
            document.head.appendChild(style);
        }

        var accentColor = stages ? '#2271b1' : '#0ea5e9';
        var overlay     = document.createElement('div');
        overlay.id      = 'ele2gb-bulk-ai-overlay';
        overlay.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.55);z-index:999999;display:flex;align-items:center;justify-content:center;';

        var card = document.createElement('div');
        card.style.cssText = 'background:#fff;border-radius:10px;padding:40px 48px;max-width:480px;width:90%;text-align:center;box-shadow:0 8px 40px rgba(0,0,0,0.18);';

        var svgNS   = 'http://www.w3.org/2000/svg';
        var spinWrap = document.createElement('div');
        spinWrap.style.marginBottom = '24px';
        var svg = document.createElementNS(svgNS, 'svg');
        svg.setAttribute('width', '48'); svg.setAttribute('height', '48');
        svg.setAttribute('viewBox', '0 0 24 24'); svg.setAttribute('fill', 'none');
        svg.style.cssText = 'animation:etg-spin 1s linear infinite;';
        var circle = document.createElementNS(svgNS, 'circle');
        circle.setAttribute('cx', '12'); circle.setAttribute('cy', '12'); circle.setAttribute('r', '10');
        circle.setAttribute('stroke', accentColor); circle.setAttribute('stroke-width', '3'); circle.setAttribute('stroke-dasharray', '40 20');
        svg.appendChild(circle);
        spinWrap.appendChild(svg);
        card.appendChild(spinWrap);

        var h3 = document.createElement('h3');
        h3.style.cssText = 'margin:0 0 8px;font-size:18px;color:#1d2327;font-weight:700;';
        h3.textContent = title;
        card.appendChild(h3);

        var sub = document.createElement('p');
        sub.style.cssText = 'margin:0 0 12px;color:#50575e;font-size:13px;';
        sub.textContent = subtitle;
        card.appendChild(sub);

        var counter = document.createElement('p');
        counter.style.cssText = 'margin:0 0 20px;color:' + accentColor + ';font-size:13px;font-weight:600;';
        counter.textContent = current + ' / ' + total;
        card.appendChild(counter);

        if (stages && stages.length) {
            var dotsWrap = document.createElement('div');
            dotsWrap.style.cssText = 'display:flex;justify-content:center;gap:8px;';
            stages.forEach(function (label, idx) {
                var dot = document.createElement('span');
                dot.style.cssText = 'font-size:12px;padding:2px 10px;border-radius:12px;transition:background 0.3s,color 0.3s;background:' + (idx === 0 ? accentColor : '#dcdcde') + ';color:' + (idx === 0 ? '#fff' : '#50575e') + ';';
                dot.textContent = label;
                dotsWrap.appendChild(dot);
            });
            card.appendChild(dotsWrap);
            var t1 = setTimeout(function () {
                var d = dotsWrap.children;
                if (d[0]) { d[0].style.background = '#dcdcde'; d[0].style.color = '#50575e'; }
                if (d[1]) { d[1].style.background = accentColor; d[1].style.color = '#fff'; }
            }, 6000);
            var t2 = setTimeout(function () {
                var d = dotsWrap.children;
                if (d[1]) { d[1].style.background = '#dcdcde'; d[1].style.color = '#50575e'; }
                if (d[2]) { d[2].style.background = accentColor; d[2].style.color = '#fff'; }
            }, 22000);
            overlay.dataset.t1 = String(t1);
            overlay.dataset.t2 = String(t2);
        }

        overlay.appendChild(card);
        document.body.appendChild(overlay);
    };

    EtgAiEnhancement.prototype.hideOverlay = function () {
        var overlay = document.getElementById('ele2gb-bulk-ai-overlay');
        if (!overlay) { return; }
        if (overlay.dataset.t1) { clearTimeout(Number(overlay.dataset.t1)); }
        if (overlay.dataset.t2) { clearTimeout(Number(overlay.dataset.t2)); }
        overlay.parentNode.removeChild(overlay);
    };

    // Backward-compat aliases
    EtgAiEnhancement.prototype.showAiOverlay = function (title, current, total) {
        this.showOverlay(this.strings.aiLoaderTitle || 'Improving with AI…', title, current, total,
            [this.strings.aiStageAnalyzing || 'Analyzing…', this.strings.aiStageGenerating || 'Generating…', this.strings.aiStageSaving || 'Saving…']);
    };
    EtgAiEnhancement.prototype.hideAiOverlay = function () { this.hideOverlay(); };

    // ── badge / spinner helpers ───────────────────────────────────────────────

    EtgAiEnhancement.prototype.makeAiStatusBadge = function (status, error) {
        var labels = {
            pending:    this.strings.aiStatusPending    || 'Pending',
            processing: this.strings.aiStatusProcessing || 'Processing…',
            done:       this.strings.aiStatusDone       || 'Done',
            failed:     this.strings.aiStatusFailed     || 'Failed',
            skipped:    this.strings.aiStatusSkipped    || 'Skipped',
        };
        var badge = cel('span', 'ele2gb-ai-badge ele2gb-ai-badge--' + status, labels[status] || status);
        if (status === 'failed' && error) {
            badge.appendChild(cel('span', 'ele2gb-ai-badge-error', ' — ' + error));
        }
        return badge;
    };

    EtgAiEnhancement.prototype.makeRowSpinner = function () {
        var wrap  = cel('span', 'ele2gb-row-spinner');
        var svgNS = 'http://www.w3.org/2000/svg';
        var svg   = document.createElementNS(svgNS, 'svg');
        svg.setAttribute('width', '16'); svg.setAttribute('height', '16');
        svg.setAttribute('viewBox', '0 0 24 24'); svg.setAttribute('fill', 'none');
        svg.style.cssText = 'animation:etg-spin 1s linear infinite;vertical-align:middle;';
        var c = document.createElementNS(svgNS, 'circle');
        c.setAttribute('cx', '12'); c.setAttribute('cy', '12'); c.setAttribute('r', '10');
        c.setAttribute('stroke', '#2271b1'); c.setAttribute('stroke-width', '3'); c.setAttribute('stroke-dasharray', '40 20');
        svg.appendChild(c);
        wrap.appendChild(svg);
        return wrap;
    };

    // ── boot ──────────────────────────────────────────────────────────────────

    document.addEventListener('DOMContentLoaded', function () {
        if (typeof window.etgAiEnhancement === 'undefined') { return; }
        var app = new EtgAiEnhancement(window.etgAiEnhancement);
        app.init();
    });

}());
