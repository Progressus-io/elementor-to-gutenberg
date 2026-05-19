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

    // ── app ───────────────────────────────────────────────────────────────────

    function EtgAiEnhancement(config) {
        this.config  = config;
        this.strings = config.strings || {};
        this.pages   = config.pages   || [];
        this.state   = { selected: new Set(), aiImprove: null };
        this.root    = null;
    }

    EtgAiEnhancement.prototype.init = function () {
        this.root = document.getElementById('etg-ai-enhancement-app');
        if (!this.root) { return; }
        this.render();
    };

    EtgAiEnhancement.prototype.render = function () {
        if (!this.root) { return; }
        this.root.innerHTML = '';
        if (this.state.aiImprove) {
            this.root.appendChild(this.renderAiImproveStep());
        } else {
            this.root.appendChild(this.renderSelectionStep());
        }
    };

    // ── selection step ────────────────────────────────────────────────────────

    EtgAiEnhancement.prototype.renderSelectionStep = function () {
        var self = this;
        var wrap = cel('div');

        // No-API notice — hidden; revealed on button click when not configured
        if (!this.config.aiConfigured) {
            var notice = cel('div', 'notice notice-warning');
            notice.id = 'etg-no-api-notice';
            notice.style.display = 'none';
            var noticeP = cel('p');
            var noticeText = document.createTextNode((this.strings.noApiMessage || 'A Claude API key is required.') + ' ');
            var noticeLink = cel('a', '', this.strings.addApiLink || 'Add your API key in Settings');
            noticeLink.href = this.config.settingsUrl || '#';
            noticeP.appendChild(noticeText);
            noticeP.appendChild(noticeLink);
            notice.appendChild(noticeP);
            wrap.appendChild(notice);
        }

        // Action bar
        var actionBar = cel('div', 'tablenav top');
        actionBar.style.marginBottom = '8px';
        actionBar.style.marginTop = '16px';

        var bulkBtn = cel('button', 'button button-primary', this.strings.enhanceSelected || 'Enhance Selected with AI');
        bulkBtn.type = 'button';
        bulkBtn.id   = 'etg-bulk-enhance-btn';
        bulkBtn.disabled = true;
        bulkBtn.addEventListener('click', function () { self.onBulkEnhanceClick(); });
        actionBar.appendChild(bulkBtn);
        wrap.appendChild(actionBar);

        // Table
        var table = cel('table', 'wp-list-table widefat fixed striped');
        table.style.marginTop = '4px';

        var thead = document.createElement('thead');
        var hr = document.createElement('tr');

        var thCb = cel('th');
        thCb.style.width = '30px';
        var selectAll = document.createElement('input');
        selectAll.type = 'checkbox';
        selectAll.id   = 'etg-select-all';
        selectAll.title = 'Select all';
        selectAll.addEventListener('change', function (e) { self.onSelectAll(e.target.checked); });
        thCb.appendChild(selectAll);
        hr.appendChild(thCb);
        hr.appendChild(cel('th', '', this.strings.colPage    || 'Converted Page'));
        hr.appendChild(cel('th', '', this.strings.colSource  || 'Source Page'));
        var thAct = cel('th', '', this.strings.colActions || 'Actions');
        thAct.style.width = '160px';
        hr.appendChild(thAct);
        thead.appendChild(hr);
        table.appendChild(thead);

        var tbody = document.createElement('tbody');
        this.pages.forEach(function (page) {
            var tr = document.createElement('tr');

            // checkbox
            var tdCb = document.createElement('td');
            var cb = document.createElement('input');
            cb.type = 'checkbox';
            cb.value = String(page.id);
            cb.dataset.pageId = String(page.id);
            cb.addEventListener('change', function () { self.onRowCheck(); });
            tdCb.appendChild(cb);
            tr.appendChild(tdCb);

            // converted page title
            var tdTitle = document.createElement('td');
            var strong  = cel('strong');
            var titleLink = cel('a', '', page.title || String(page.id));
            titleLink.href   = self.config.editBaseUrl + String(page.id) + '&action=edit';
            titleLink.target = '_blank';
            strong.appendChild(titleLink);
            tdTitle.appendChild(strong);
            tr.appendChild(tdTitle);

            // source page
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

            // single-page enhance action
            var tdAct = document.createElement('td');
            if (self.config.aiConfigured && self.config.aiImproveBaseUrl && page.sourceId) {
                var improveUrl = self.config.aiImproveBaseUrl +
                    '&source_id=' + String(page.sourceId) +
                    '&target_id=' + String(page.id);
                var singleBtn = cel('a', 'button button-small button-primary', self.strings.enhanceSingle || 'Enhance with AI');
                singleBtn.href = improveUrl;
                tdAct.appendChild(singleBtn);
            } else {
                var noKeyBtn = cel('button', 'button button-small button-primary', self.strings.enhanceSingle || 'Enhance with AI');
                noKeyBtn.type = 'button';
                noKeyBtn.addEventListener('click', function () {
                    var notice = document.getElementById('etg-no-api-notice');
                    if (notice) {
                        notice.style.display = 'block';
                        notice.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                });
                tdAct.appendChild(noKeyBtn);
            }
            tr.appendChild(tdAct);

            tbody.appendChild(tr);
        });
        table.appendChild(tbody);
        wrap.appendChild(table);

        return wrap;
    };

    EtgAiEnhancement.prototype.onSelectAll = function (checked) {
        this.state.selected.clear();
        document.querySelectorAll('#etg-ai-enhancement-app input[type=checkbox][data-page-id]').forEach(function (cb) {
            cb.checked = checked;
        });
        if (checked) {
            var self = this;
            this.pages.forEach(function (p) { self.state.selected.add(p.id); });
        }
        this.updateBulkButton();
    };

    EtgAiEnhancement.prototype.onRowCheck = function () {
        var self = this;
        this.state.selected.clear();
        document.querySelectorAll('#etg-ai-enhancement-app input[type=checkbox][data-page-id]').forEach(function (cb) {
            if (cb.checked) { self.state.selected.add(Number(cb.value)); }
        });
        this.updateBulkButton();
    };

    EtgAiEnhancement.prototype.updateBulkButton = function () {
        var btn   = document.getElementById('etg-bulk-enhance-btn');
        if (!btn) { return; }
        var count = this.state.selected.size;
        btn.disabled = count === 0;
        btn.textContent = count > 0
            ? fmt(this.strings.enhanceSelectedCount || 'Enhance %1$d Selected with AI', count)
            : (this.strings.enhanceSelected || 'Enhance Selected with AI');
    };

    EtgAiEnhancement.prototype.onBulkEnhanceClick = function () {
        if (!this.config.aiConfigured) {
            var notice = document.getElementById('etg-no-api-notice');
            if (notice) {
                notice.style.display = 'block';
                notice.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
            return;
        }
        if (this.state.selected.size === 0) { return; }
        this.initAiImprove();
    };

    // ── ai improve flow (mirrors wizard exactly) ──────────────────────────────

    EtgAiEnhancement.prototype.initAiImprove = function () {
        var selectedIds = this.state.selected;
        var pages = this.pages
            .filter(function (p) { return selectedIds.has(p.id); })
            .map(function (p) {
                return {
                    sourceId: p.sourceId,
                    targetId: p.id,
                    title:    p.title || String(p.id),
                    type:     p.type  || 'page',
                    status:   'pending',
                    error:    '',
                };
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

        ai.currentIndex          = index;
        ai.pages[index].status  = 'processing';
        ai.pages[index].error   = '';
        this.render();

        var page = ai.pages[index];
        this.showAiOverlay(page.title, index + 1, ai.pages.length, 'analyzing');

        var formData = new FormData();
        formData.append('action',    'ele2gb_ai_improve_single');
        formData.append('nonce',     this.config.aiImproveNonce);
        formData.append('source_id', String(page.sourceId));
        formData.append('target_id', String(page.targetId));

        fetch(this.config.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: formData })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                self.hideAiOverlay();
                if (data.success) {
                    ai.pages[index].status = 'done';
                    self.render();
                    self.advanceAiImprove(index);
                } else {
                    ai.pages[index].status = 'failed';
                    ai.pages[index].error  = (data.data && data.data.message)
                        ? String(data.data.message)
                        : (self.strings.aiImproveError || 'An unexpected error occurred.');
                    self.render();
                }
            })
            .catch(function (err) {
                self.hideAiOverlay();
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

    // ── render ai improve step ────────────────────────────────────────────────

    EtgAiEnhancement.prototype.renderAiImproveStep = function () {
        var self = this;
        var ai   = this.state.aiImprove;
        var wrap = cel('div', 'ele2gb-ai-improve-step');

        // ── pre-start panel ───────────────────────────────────────────────────
        if (!ai.started) {
            var panel = cel('div', 'ele2gb-preflight-card');

            // readiness checklist
            panel.appendChild(cel('h3', '', this.strings.aiReadinessTitle || 'Pre-flight checklist'));

            var apiRow  = cel('div', 'ele2gb-preflight-row');
            var apiIcon = cel('span',
                this.config.aiConfigured ? 'ele2gb-preflight-ok' : 'ele2gb-preflight-err',
                this.config.aiConfigured ? '✓' : '✗'
            );
            var apiLbl  = cel('span', '',
                this.config.aiConfigured
                    ? (this.strings.aiReadinessApiValid   || 'API key configured')
                    : (this.strings.aiReadinessApiInvalid || 'API key not configured')
            );
            apiRow.appendChild(apiIcon);
            apiRow.appendChild(apiLbl);
            panel.appendChild(apiRow);

            var cntRow  = cel('div', 'ele2gb-preflight-row');
            var cntIcon = cel('span', 'ele2gb-preflight-ok', '✓');
            var cntLbl  = cel('span', '', fmt(this.strings.aiReadinessCredits || 'Estimated: ~%1$d API call(s)', ai.pages.length));
            cntRow.appendChild(cntIcon);
            cntRow.appendChild(cntLbl);
            panel.appendChild(cntRow);

            // warning box
            var warnBox  = cel('div', 'ele2gb-warning-box');
            var warnTitle = cel('strong', '', this.strings.aiImproveWarningTitle || 'AI credits will be used');
            var warnText  = cel('p', '', this.strings.aiImproveWarning || 'This will use AI credits once per selected item.');
            warnBox.appendChild(warnTitle);
            warnBox.appendChild(warnText);
            panel.appendChild(warnBox);

            // actions
            var actions  = cel('div', 'ele2gb-results-actions');
            var startBtn = cel('button', 'button button-primary', this.strings.aiImproveStart || 'Start AI Enhancement');
            startBtn.type     = 'button';
            startBtn.disabled = !this.config.aiConfigured;
            startBtn.addEventListener('click', function () { self.startAiImprove(); });
            actions.appendChild(startBtn);

            var backBtn = cel('button', 'button button-secondary', this.strings.back || 'Back');
            backBtn.type = 'button';
            backBtn.style.marginLeft = '8px';
            backBtn.addEventListener('click', function () { self.state.aiImprove = null; self.render(); });
            actions.appendChild(backBtn);

            panel.appendChild(actions);
            wrap.appendChild(panel);
            return wrap;
        }

        // ── progress header ───────────────────────────────────────────────────
        var done     = ai.pages.filter(function (p) { return p.status === 'done';      }).length;
        var failed   = ai.pages.filter(function (p) { return p.status === 'failed';    }).length;
        var skipped  = ai.pages.filter(function (p) { return p.status === 'skipped';   }).length;
        var pending  = ai.pages.filter(function (p) { return p.status === 'pending';   }).length;
        var total    = ai.pages.length;
        var progress = total > 0 ? Math.round(((done + failed + skipped) / total) * 100) : 0;

        var progressSection = cel('div', 'ele2gb-progress-section');

        var progressBar  = cel('div', 'ele2gb-progress-bar');
        var progressFill = cel('div', 'ele2gb-progress-fill');
        progressFill.style.width = progress + '%';
        progressBar.appendChild(progressFill);
        progressSection.appendChild(progressBar);

        var chips = cel('div', 'ele2gb-status-chips');
        if (done    > 0) { chips.appendChild(cel('span', 'ele2gb-status-chip ele2gb-status-chip--done',      fmt(this.strings.aiStatusDone    || 'Done (%1$d)',    done)));    }
        if (failed  > 0) { chips.appendChild(cel('span', 'ele2gb-status-chip ele2gb-status-chip--failed',   fmt(this.strings.aiStatusFailed  || 'Failed (%1$d)', failed)));  }
        if (skipped > 0) { chips.appendChild(cel('span', 'ele2gb-status-chip ele2gb-status-chip--skipped',  fmt(this.strings.aiStatusSkipped || 'Skipped (%1$d)',skipped))); }
        if (pending > 0) { chips.appendChild(cel('span', 'ele2gb-status-chip ele2gb-status-chip--pending',  fmt(this.strings.aiStatusPending || 'Pending (%1$d)',pending))); }
        progressSection.appendChild(chips);

        if (failed > 0 && !ai.finished) {
            var pausedNote = cel('div', 'notice notice-warning ele2gb-paused-notice');
            pausedNote.appendChild(cel('p', '', this.strings.aiImprovePaused || 'Paused — a page failed. Review the error below, then skip or retry to continue.'));
            progressSection.appendChild(pausedNote);
        }
        wrap.appendChild(progressSection);

        // ── items table ───────────────────────────────────────────────────────
        var table  = cel('table', 'wp-list-table widefat fixed striped ele2gb-ai-results-table');
        var thead2 = document.createElement('thead');
        var hr2    = document.createElement('tr');
        hr2.appendChild(cel('th', '', this.strings.colPage        || 'Page'));
        hr2.appendChild(cel('th', '', this.strings.aiImproveType  || 'Type'));
        hr2.appendChild(cel('th', '', 'Status'));
        hr2.appendChild(cel('th', '', 'Actions'));
        thead2.appendChild(hr2);
        table.appendChild(thead2);

        var tbody2 = document.createElement('tbody');
        ai.pages.forEach(function (page, i) {
            var tr = document.createElement('tr');

            tr.appendChild(cel('td', '', page.title));
            tr.appendChild(cel('td', '', page.type));

            var tdSt = document.createElement('td');
            tdSt.appendChild(self.makeAiStatusBadge(page.status, page.error));
            tr.appendChild(tdSt);

            var tdAct = document.createElement('td');
            if (page.status === 'failed') {
                var skipBtn = cel('button', 'button button-small', self.strings.skip || 'Skip');
                skipBtn.type = 'button';
                skipBtn.addEventListener('click', function () { self.skipAiImprovePage(i); });
                tdAct.appendChild(skipBtn);

                var retryBtn = cel('button', 'button button-small button-primary', self.strings.retry || 'Retry');
                retryBtn.type = 'button';
                retryBtn.style.marginLeft = '4px';
                retryBtn.addEventListener('click', function () { self.retryAiImprovePage(i); });
                tdAct.appendChild(retryBtn);
            } else if (page.status === 'processing') {
                tdAct.appendChild(self.makeRowSpinner());
            }
            tr.appendChild(tdAct);

            tbody2.appendChild(tr);
        });
        table.appendChild(tbody2);
        wrap.appendChild(table);

        // ── completion ────────────────────────────────────────────────────────
        if (ai.finished) {
            var compWrap = cel('div', 'ele2gb-ai-completion ' + (failed === 0 && skipped === 0 ? 'ele2gb-ai-completion--success' : 'ele2gb-ai-completion--partial'));
            compWrap.appendChild(cel('p', '',
                failed === 0 && skipped === 0
                    ? (this.strings.aiImproveFinishedOk  || 'All items improved successfully.')
                    : fmt(this.strings.aiImproveFinishedErr || 'Finished — %1$d done, %2$d failed, %3$d skipped.', done, failed, skipped)
            ));
            wrap.appendChild(compWrap);

            var doneActions = cel('div', 'ele2gb-results-actions');
            var backBtn2    = cel('button', 'button button-secondary', this.strings.backToList || 'Back to list');
            backBtn2.type   = 'button';
            backBtn2.addEventListener('click', function () {
                self.state.aiImprove = null;
                self.state.selected.clear();
                self.render();
            });
            doneActions.appendChild(backBtn2);
            wrap.appendChild(doneActions);
        }

        return wrap;
    };

    // ── overlay (identical to wizard) ─────────────────────────────────────────

    EtgAiEnhancement.prototype.showAiOverlay = function (title, current, total) {
        this.hideAiOverlay();

        var overlay = document.createElement('div');
        overlay.id  = 'ele2gb-bulk-ai-overlay';
        overlay.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.55);z-index:999999;display:flex;align-items:center;justify-content:center;';

        var card = document.createElement('div');
        card.style.cssText = 'background:#fff;border-radius:8px;padding:40px 48px;max-width:480px;width:90%;text-align:center;box-shadow:0 8px 40px rgba(0,0,0,0.18);';

        // spinner
        if (!document.getElementById('etg-spin-style')) {
            var style     = document.createElement('style');
            style.id      = 'etg-spin-style';
            style.textContent = '@keyframes etg-spin{from{transform:rotate(0deg)}to{transform:rotate(360deg)}}';
            document.head.appendChild(style);
        }
        var svgNS  = 'http://www.w3.org/2000/svg';
        var svgWrap = document.createElement('div');
        svgWrap.style.marginBottom = '24px';
        var svg    = document.createElementNS(svgNS, 'svg');
        svg.setAttribute('width', '48'); svg.setAttribute('height', '48');
        svg.setAttribute('viewBox', '0 0 24 24'); svg.setAttribute('fill', 'none');
        svg.style.cssText = 'animation:etg-spin 1s linear infinite;';
        var circle = document.createElementNS(svgNS, 'circle');
        circle.setAttribute('cx', '12'); circle.setAttribute('cy', '12'); circle.setAttribute('r', '10');
        circle.setAttribute('stroke', '#2271b1'); circle.setAttribute('stroke-width', '3');
        circle.setAttribute('stroke-dasharray', '40 20');
        svg.appendChild(circle);
        svgWrap.appendChild(svg);
        card.appendChild(svgWrap);

        // heading
        var h3 = document.createElement('h3');
        h3.style.cssText = 'margin:0 0 8px;font-size:18px;color:#1d2327;';
        h3.textContent = this.strings.aiLoaderTitle || 'Improving with AI…';
        card.appendChild(h3);

        // page title
        var sub = document.createElement('p');
        sub.style.cssText = 'margin:0 0 16px;color:#50575e;font-size:13px;';
        sub.textContent = title;
        card.appendChild(sub);

        // counter
        var counter = document.createElement('p');
        counter.style.cssText = 'margin:0 0 20px;color:#2271b1;font-size:13px;font-weight:600;';
        counter.textContent = current + ' / ' + total;
        card.appendChild(counter);

        // stage dots
        var stages   = [
            this.strings.aiStageAnalyzing  || 'Analyzing…',
            this.strings.aiStageGenerating || 'Generating…',
            this.strings.aiStageSaving     || 'Saving…',
        ];
        var dotsWrap = document.createElement('div');
        dotsWrap.style.cssText = 'display:flex;justify-content:center;gap:8px;margin-top:8px;';
        stages.forEach(function (label, idx) {
            var dot = document.createElement('span');
            dot.style.cssText = 'font-size:12px;padding:2px 10px;border-radius:12px;transition:background 0.3s,color 0.3s;background:' + (idx === 0 ? '#2271b1' : '#dcdcde') + ';color:' + (idx === 0 ? '#fff' : '#50575e') + ';';
            dot.textContent = label;
            dotsWrap.appendChild(dot);
        });
        card.appendChild(dotsWrap);

        var t1 = setTimeout(function () {
            var dots = dotsWrap.children;
            if (dots[0]) { dots[0].style.background = '#dcdcde'; dots[0].style.color = '#50575e'; }
            if (dots[1]) { dots[1].style.background = '#2271b1'; dots[1].style.color = '#fff'; }
        }, 6000);
        var t2 = setTimeout(function () {
            var dots = dotsWrap.children;
            if (dots[1]) { dots[1].style.background = '#dcdcde'; dots[1].style.color = '#50575e'; }
            if (dots[2]) { dots[2].style.background = '#2271b1'; dots[2].style.color = '#fff'; }
        }, 22000);

        overlay.dataset.t1 = String(t1);
        overlay.dataset.t2 = String(t2);
        overlay.appendChild(card);
        document.body.appendChild(overlay);
    };

    EtgAiEnhancement.prototype.hideAiOverlay = function () {
        var overlay = document.getElementById('ele2gb-bulk-ai-overlay');
        if (!overlay) { return; }
        if (overlay.dataset.t1) { clearTimeout(Number(overlay.dataset.t1)); }
        if (overlay.dataset.t2) { clearTimeout(Number(overlay.dataset.t2)); }
        overlay.parentNode.removeChild(overlay);
    };

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
            var errSpan = cel('span', 'ele2gb-ai-badge-error', ' — ' + error);
            badge.appendChild(errSpan);
        }
        return badge;
    };

    EtgAiEnhancement.prototype.makeRowSpinner = function () {
        var wrap   = cel('span', 'ele2gb-row-spinner');
        var svgNS  = 'http://www.w3.org/2000/svg';
        var svg    = document.createElementNS(svgNS, 'svg');
        svg.setAttribute('width', '16'); svg.setAttribute('height', '16');
        svg.setAttribute('viewBox', '0 0 24 24'); svg.setAttribute('fill', 'none');
        svg.style.cssText = 'animation:etg-spin 1s linear infinite;vertical-align:middle;';
        var circle = document.createElementNS(svgNS, 'circle');
        circle.setAttribute('cx', '12'); circle.setAttribute('cy', '12'); circle.setAttribute('r', '10');
        circle.setAttribute('stroke', '#2271b1'); circle.setAttribute('stroke-width', '3');
        circle.setAttribute('stroke-dasharray', '40 20');
        svg.appendChild(circle);
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
