(function (window, document) {
    'use strict';

    if (!window.ele2gbBatchWizard) {
        return;
    }

    const data = window.ele2gbBatchWizard;
    const root = document.getElementById('ele2gb-batch-convert-root');
    if (!root) {
        return;
    }

    const STATUS_BADGES = {
        converted: { labelKey: 'statusConverted', className: 'ele2gb-status-converted' },
        not_converted: { labelKey: 'statusNotConverted', className: 'ele2gb-status-not_converted' },
        partial: { labelKey: 'statusPartial', className: 'ele2gb-status-partial' },
        error: { labelKey: 'statusError', className: 'ele2gb-status-error' },
        skipped: { labelKey: 'statusSkipped', className: 'ele2gb-status-skipped' },
    };

    const RESULT_STATUS = {
        success: { labelKey: 'statusConverted', badge: 'converted' },
        error: { labelKey: 'statusError', badge: 'error' },
        skipped: { labelKey: 'statusSkipped', badge: 'skipped' },
        partial: { labelKey: 'statusPartial', badge: 'partial' },
    };

    function formatString(template, ...values) {
        if (typeof template !== 'string') {
            return '';
        }

        return template.replace(/%([0-9]+)\$[sd]/g, function (match, index) {
            const value = values[parseInt(index, 10) - 1];
            return value !== undefined ? value : match;
        });
    }

    function formatDuration(seconds) {
        if (!seconds || seconds <= 0) {
            return '0s';
        }
        const total = Math.round(seconds);
        const mins = Math.floor(total / 60);
        const secs = total % 60;
        const parts = [];
        if (mins > 0) {
            parts.push(mins + 'm');
        }
        parts.push(secs + 's');
        return parts.join(' ');
    }

    function createElement(tag, className, text) {
        const el = document.createElement(tag);
        if (className) {
            el.className = className;
        }
        if (text) {
            el.textContent = text;
        }
        return el;
    }

    function createButton(label, className) {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = className;
        button.textContent = label;
        return button;
    }

    class WizardApp {
        constructor(rootEl, config) {
            this.root = rootEl;
            this.config = config;
            this.strings = config.strings || {};
            this.pages = Array.isArray(config.pages) ? config.pages.slice() : [];
            this.templates = config.templates || {
                headers: [],
                footers: [],
                defaults: { header: 0, footer: 0 },
                counts: { headers: 0, footers: 0 },
            };
            this.state = {
                currentStep: 'mode',
                mode: 'auto',
                modeSelection: 'auto',
                selectedPageIds: new Set(),
                disabledMeta: new Set(),
                selectedHeaderIds: new Set(),
                selectedFooterIds: new Set(),
                defaultHeaderId: 0,
                defaultFooterId: 0,
                skipConverted: true,
                conflictPolicy: 'skip',
                tablePage: 1,
                perPage: 10,
                notice: null,
                isSubmitting: false,
                job: null,
                pollTimer: null,
                lastPayload: null,
                resumed: false,
                refreshing: false,
            };

            if (config.activeJob && config.activeJob.id) {
                this.state.job = config.activeJob;
                this.state.currentStep = 'progress';
                this.state.mode = config.activeJob.mode || 'auto';
                this.state.modeSelection = this.state.mode;
                this.state.resumed = config.activeJob.status !== 'completed';
                if (this.state.resumed) {
                    this.startPolling();
                }
            }
        }

        init() {
            if (!this.state.job) {
                this.resetSelectionForMode(this.state.modeSelection);
            }
            this.render();
        }

        resetSelectionForMode(mode) {
            const defaultHeader = this.templates && this.templates.defaults ? Number(this.templates.defaults.header) || 0 : 0;
            const defaultFooter = this.templates && this.templates.defaults ? Number(this.templates.defaults.footer) || 0 : 0;

            if (mode === 'auto') {
                this.state.mode = 'auto';
                this.state.modeSelection = 'auto';
                this.state.selectedPageIds = new Set(this.pages.map((page) => page.id));
                this.state.disabledMeta = new Set();
                this.state.selectedHeaderIds = new Set();
                this.state.selectedFooterIds = new Set();
                this.state.defaultHeaderId = defaultHeader;
                this.state.defaultFooterId = defaultFooter;
                this.state.skipConverted = true;
                this.state.tablePage = 1;
            } else {
                this.state.mode = 'custom';
                this.state.modeSelection = 'custom';
                this.state.selectedPageIds = new Set();
                this.state.disabledMeta = new Set();
                this.state.selectedHeaderIds = new Set(this.getTemplatesFor('header').map((template) => Number(template.id)));
                this.state.selectedFooterIds = new Set(this.getTemplatesFor('footer').map((template) => Number(template.id)));
                this.state.defaultHeaderId = this.pickDefaultTemplate('header', this.state.selectedHeaderIds, defaultHeader);
                this.state.defaultFooterId = this.pickDefaultTemplate('footer', this.state.selectedFooterIds, defaultFooter);
                this.state.skipConverted = true;
                this.state.tablePage = 1;
            }

            this.ensureDefaultTemplate('header');
            this.ensureDefaultTemplate('footer');
        }

        getTemplatesFor(type) {
            if (type === 'header') {
                return Array.isArray(this.templates.headers) ? this.templates.headers : [];
            }
            if (type === 'footer') {
                return Array.isArray(this.templates.footers) ? this.templates.footers : [];
            }
            return [];
        }

        getTemplateById(id) {
            const targetId = Number(id);
            const header = this.getTemplatesFor('header').find((template) => Number(template.id) === targetId);
            if (header) {
                return header;
            }
            return this.getTemplatesFor('footer').find((template) => Number(template.id) === targetId) || null;
        }

        pickDefaultTemplate(type, selectedSet, fallbackId) {
            if (fallbackId && selectedSet.has(fallbackId)) {
                return fallbackId;
            }
            const iterator = selectedSet.values();
            const first = iterator.next();
            if (!first.done) {
                return first.value;
            }
            return 0;
        }

        ensureDefaultTemplate(type) {
            const key = type === 'header' ? 'defaultHeaderId' : 'defaultFooterId';
            const set = type === 'header' ? this.state.selectedHeaderIds : this.state.selectedFooterIds;
            if (!set.size) {
                this.state[key] = 0;
                return;
            }
            if (!set.has(this.state[key])) {
                this.state[key] = this.pickDefaultTemplate(type, set, 0);
            }
        }

        toggleTemplateSelection(type, id, checked) {
            const templateId = Number(id);
            const set = type === 'header' ? this.state.selectedHeaderIds : this.state.selectedFooterIds;
            if (checked) {
                set.add(templateId);
            } else {
                set.delete(templateId);
            }
            this.ensureDefaultTemplate(type);
            this.clearNotice();
            this.render();
        }

        setDefaultTemplate(type, id) {
            const key = type === 'header' ? 'defaultHeaderId' : 'defaultFooterId';
            const set = type === 'header' ? this.state.selectedHeaderIds : this.state.selectedFooterIds;
            const templateId = Number(id);
            if (!set.has(templateId)) {
                return;
            }
            if (this.state[key] !== templateId) {
                this.state[key] = templateId;
                this.clearNotice();
                this.render();
            }
        }

        getSelectedTemplateIds(type) {
            const set = type === 'header' ? this.state.selectedHeaderIds : this.state.selectedFooterIds;
            return Array.from(set.values());
        }

        getSelectedTemplates(type) {
            const set = type === 'header' ? this.state.selectedHeaderIds : this.state.selectedFooterIds;
            return this.getTemplatesFor(type).filter((template) => set.has(template.id));
        }

        hasAnySelection() {
            if (this.state.mode === 'auto') {
                return true;
            }
            return this.state.selectedPageIds.size > 0 || this.state.selectedHeaderIds.size > 0 || this.state.selectedFooterIds.size > 0;
        }

        formatResultType(type) {
            if (!type) {
                return '';
            }
            const normalized = String(type);
            return normalized.charAt(0).toUpperCase() + normalized.slice(1);
        }

        formatResultRole(role) {
            if (!role) {
                return '';
            }
            return role
                .split('_')
                .filter(Boolean)
                .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
                .join(' ');
        }

        getStepSequence() {
            if (this.state.job && this.state.currentStep === 'progress') {
                return ['progress'];
            }

            const steps = ['mode'];
            if (this.state.mode === 'custom') {
                steps.push('select');
                steps.push('templates');
            }
            if (this.shouldShowConflictStep()) {
                steps.push('conflicts');
            }
            steps.push('review');
            steps.push('progress');
            return steps;
        }

        shouldShowConflictStep() {
            const selected = this.getSelectedPages();
            if (!selected.length) {
                return false;
            }
            return selected.some((page) => page.hasConflict);
        }

        getStepTitle(step) {
            switch (step) {
                case 'mode':
                    return this.strings.modeTitle || 'Choose Mode';
                case 'select': {
                    const summary = formatString(this.strings.selectionSummary || '%1$d selected / %2$d total', this.state.selectedPageIds.size, this.pages.length);
                    return (this.strings.selectPagesTitle || 'Select Pages') + ' (' + summary + ')';
                }
                case 'templates':
                    return this.strings.headerFooterStepTitle || 'Header & Footer Templates';
                case 'conflicts':
                    return this.strings.conflictsTitle || 'Resolve Conflicts';
                case 'review':
                    return this.strings.reviewTitle || 'Review & Confirm';
                case 'progress':
                    return this.strings.progressTitle || 'Progress & Results';
                default:
                    return '';
            }
        }

        setNotice(type, message) {
            if (!message) {
                this.state.notice = null;
            } else {
                this.state.notice = { type: type || 'info', message };
            }
            this.render();
        }

        clearNotice() {
            this.state.notice = null;
        }

        goToStep(step) {
            this.state.currentStep = step;
            this.render();
        }

        goToNext() {
            const steps = this.getStepSequence();
            const index = steps.indexOf(this.state.currentStep);
            if (index > -1 && index < steps.length - 1) {
                this.state.currentStep = steps[index + 1];
                this.render();
            }
        }

        goToPrevious() {
            const steps = this.getStepSequence();
            const index = steps.indexOf(this.state.currentStep);
            if (index > 0) {
                this.state.currentStep = steps[index - 1];
                this.render();
            }
        }

        getSelectedPages() {
            const ids = this.state.selectedPageIds;
            return this.pages.filter((page) => ids.has(page.id));
        }

        isPageSelected(id) {
            return this.state.selectedPageIds.has(id);
        }

        togglePageSelection(id, checked) {
            if (checked) {
                this.state.selectedPageIds.add(id);
            } else {
                this.state.selectedPageIds.delete(id);
                this.state.disabledMeta.delete(id);
            }
            this.clearNotice();
            this.render();
        }

        toggleDisableMeta(id, disable) {
            if (disable) {
                this.state.disabledMeta.add(id);
            } else {
                this.state.disabledMeta.delete(id);
            }
            this.render();
        }

        shouldShowSkipConvertedOption() {
            if (!this.state.selectedPageIds.size) {
                return false;
            }
            if (this.state.selectedPageIds.size === this.pages.length) {
                return true;
            }
            return this.getSelectedPages().some((page) => page.conversionStatus === 'converted');
        }

        getConflictCount() {
            return this.getSelectedPages().filter((page) => page.hasConflict).length;
        }

        startPolling() {
            if (!this.state.job || !this.state.job.id) {
                return;
            }
            this.stopPolling();
            const poll = () => {
                this.request('ele2gb_poll_job', { jobId: this.state.job.id })
                    .then((response) => {
                        if (response && response.job) {
                            this.state.job = response.job;
                            this.render();
                            if (response.job.status === 'completed') {
                                this.stopPolling();
                                this.refreshPages();
                            }
                        }
                    })
                    .catch((error) => {
                        this.stopPolling();
                        const message = (error && error.message) || this.strings.retryFailed || 'Something went wrong.';
                        this.setNotice('error', message);
                    })
                    .finally(() => {
                        if (this.state.job && this.state.job.status !== 'completed') {
                            this.state.pollTimer = window.setTimeout(poll, 2000);
                        }
                    });
            };
            poll();
        }

        stopPolling() {
            if (this.state.pollTimer) {
                window.clearTimeout(this.state.pollTimer);
                this.state.pollTimer = null;
            }
        }

        cancelCurrentJob() {
            if (!this.state.job || !this.state.job.id) {
                return;
            }

            this.stopPolling();

            this.request('ele2gb_cancel_job', { jobId: this.state.job.id })
                .then((response) => {
                    // If PHP returns the cancelled job, keep it for display; otherwise clear.
                    if (response && response.job) {
                        this.state.job = response.job;
                    } else {
                        this.state.job = null;
                    }

                    this.state.isSubmitting = false;
                    this.setNotice('info', this.strings.jobCancelled || 'Conversion was cancelled.');
                    this.render();
                })
                .catch((error) => {
                    this.state.isSubmitting = false;
                    const message =
                        (error && error.message) ||
                        this.strings.retryFailed ||
                        'Unable to cancel conversion.';
                    this.setNotice('error', message);
                    this.render();
                });
        }

        request(action, payload) {
            const formData = new window.FormData();
            formData.append('action', action);
            formData.append('nonce', this.config.nonce);
            Object.keys(payload || {}).forEach((key) => {
                const value = payload[key];
                if (Array.isArray(value)) {
                    value.forEach((item) => formData.append(key + '[]', item));
                } else {
                    formData.append(key, value);
                }
            });

            return window.fetch(this.config.ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                body: formData,
            })
                .then((res) => res.json())
                .then((json) => {
                    if (!json || !json.success) {
                        const message = json && json.data && json.data.message ? json.data.message : this.strings.retryFailed || 'Request failed.';
                        throw new Error(message);
                    }
                    return json.data;
                });
        }

        startConversion() {
            const selected = Array.from(this.state.selectedPageIds);
            const selectedHeaders = this.getSelectedTemplateIds('header');
            const selectedFooters = this.getSelectedTemplateIds('footer');
            if (this.state.mode !== 'auto' && !selected.length && !selectedHeaders.length && !selectedFooters.length) {
                this.setNotice('error', this.strings.noSelectionError || 'Select at least one page or template before continuing.');
                return;
            }
            this.clearNotice();
            this.state.isSubmitting = true;
            this.render();

            const payload = {
                mode: this.state.mode,
                pages: selected,
                disabledMeta: Array.from(this.state.disabledMeta),
                skipConverted: this.state.skipConverted ? 1 : 0,
                conflictPolicy: this.state.conflictPolicy,
            };

            if (this.state.mode === 'custom') {
                payload.headerTemplates = selectedHeaders;
                payload.footerTemplates = selectedFooters;
                payload.defaultHeader = this.state.defaultHeaderId || 0;
                payload.defaultFooter = this.state.defaultFooterId || 0;
            }

            this.request('ele2gb_start_job', payload)
                .then((response) => {
                    if (response && response.job) {
                        this.state.job = response.job;
                        this.state.currentStep = 'progress';
                        this.state.lastPayload = payload;
                        this.state.isSubmitting = false;
                        this.state.resumed = false;
                        this.render();
                        if (response.job.status !== 'completed') {
                            this.startPolling();
                        }
                    }
                })
                .catch((error) => {
                    this.state.isSubmitting = false;
                    const message = (error && error.message) || this.strings.retryFailed || 'Conversion could not be started.';
                    this.setNotice('error', message);
                })
                .finally(() => {
                    this.state.isSubmitting = false;
                    this.render();
                });
        }

        retryConversionForPage(pageId, keepMeta) {
            const payload = Object.assign({}, this.state.lastPayload || {});
            payload.mode = 'custom';
            payload.pages = [pageId];
            payload.disabledMeta = keepMeta ? [] : [pageId];
            payload.skipConverted = 0;
            payload.conflictPolicy = this.state.job ? this.state.job.conflictPolicy || 'skip' : 'skip';
            delete payload.headerTemplates;
            delete payload.footerTemplates;
            delete payload.defaultHeader;
            delete payload.defaultFooter;

            this.state.mode = 'custom';
            this.state.modeSelection = 'custom';
            this.state.selectedPageIds = new Set([pageId]);
            this.state.disabledMeta = keepMeta ? new Set() : new Set([pageId]);
            this.state.selectedHeaderIds = new Set();
            this.state.selectedFooterIds = new Set();
            this.state.defaultHeaderId = 0;
            this.state.defaultFooterId = 0;
            this.state.skipConverted = false;
            this.state.currentStep = 'progress';
            this.state.lastPayload = payload;
            this.state.job = null;
            this.render();

            this.state.isSubmitting = true;
            this.request('ele2gb_start_job', payload)
                .then((response) => {
                    if (response && response.job) {
                        this.state.job = response.job;
                        this.state.isSubmitting = false;
                        this.render();
                        if (response.job.status !== 'completed') {
                            this.startPolling();
                        }
                    }
                })
                .catch((error) => {
                    this.state.isSubmitting = false;
                    const message = (error && error.message) || this.strings.retryFailed || 'Unable to retry conversion.';
                    this.setNotice('error', message);
                });
        }

        refreshPages() {
            this.state.refreshing = true;
            this.request('ele2gb_pages', {})
                .then((response) => {
                    if (response && Array.isArray(response.pages)) {
                        this.pages = response.pages;
                    }
                })
                .catch(() => {
                    // Silent fail; optional refresh.
                })
                .finally(() => {
                    this.state.refreshing = false;
                    this.render();
                });
        }

        resetWizard() {
            this.stopPolling();
            this.state = Object.assign(this.state, {
                currentStep: 'mode',
                isSubmitting: false,
                job: null,
                pollTimer: null,
                lastPayload: null,
                resumed: false,
            });
            this.resetSelectionForMode('auto');
            this.clearNotice();
            this.render();
        }

        renderHeader() {
            const header = createElement('div', 'ele2gb-wizard-header');
            const steps = this.getStepSequence();
            const index = Math.max(0, steps.indexOf(this.state.currentStep));
            const title = this.getStepTitle(this.state.currentStep);
            const stepText = formatString(this.strings.step || 'Step %1$s of %2$s — %3$s', index + 1, steps.length, title);
            header.appendChild(createElement('div', 'ele2gb-wizard-steps', stepText));

            const progress = createElement('div', 'ele2gb-progress-bar');
            const percent = steps.length ? ((index + 1) / steps.length) * 100 : 0;
            const bar = document.createElement('span');
            bar.style.width = percent + '%';
            progress.appendChild(bar);
            header.appendChild(progress);

            return header;
        }

        renderNotice() {
            if (!this.state.notice) {
                return null;
            }
            const className = 'ele2gb-alert ele2gb-alert-' + this.state.notice.type;
            return createElement('div', className, this.state.notice.message);
        }

        renderModeStep() {
            const container = createElement('div');
            container.appendChild(createElement('h2', 'ele2gb-wizard-step-title', this.strings.modeTitle || 'Choose Mode'));

            const grid = createElement('div', 'ele2gb-mode-grid');
            const modes = [
                {
                    key: 'auto',
                    title: this.strings.modeAutoTitle || 'Convert all pages automatically',
                    description: this.strings.modeAutoDesc || '',
                },
                {
                    key: 'custom',
                    title: this.strings.modeCustomTitle || 'Choose specific pages',
                    description: this.strings.modeCustomDesc || '',
                },
            ];

            modes.forEach((mode) => {
                const card = createElement('label', 'ele2gb-mode-card' + (this.state.modeSelection === mode.key ? ' is-active' : ''));
                const input = document.createElement('input');
                input.type = 'radio';
                input.name = 'ele2gb-mode';
                input.value = mode.key;
                input.checked = this.state.modeSelection === mode.key;
                input.addEventListener('change', () => {
                    this.state.modeSelection = mode.key;
                    if (mode.key === 'auto') {
                        this.state.skipConverted = true;
                    }
                    this.render();
                });
                card.appendChild(input);
                const title = createElement('h3', null, mode.title);
                card.appendChild(title);
                if (mode.description) {
                    card.appendChild(createElement('p', null, mode.description));
                }
                grid.appendChild(card);
            });

            container.appendChild(grid);

            const buttons = createElement('div', 'ele2gb-wizard-buttons');
            const continueBtn = createButton(this.strings.continue || 'Continue', 'button button-primary');
            continueBtn.addEventListener('click', () => {
                this.resetSelectionForMode(this.state.modeSelection);
                this.goToNext();
            });
            buttons.appendChild(continueBtn);
            container.appendChild(buttons);

            return container;
        }

        renderSelectStep() {
            const container = createElement('div');
            container.appendChild(createElement('h2', 'ele2gb-wizard-step-title', this.strings.selectPagesTitle || 'Select Pages'));

            if (!this.pages.length) {
                container.appendChild(createElement('p', null, this.strings.noPagesFound || 'No Elementor pages found.'));
                return container;
            }

            const tableWrapper = createElement('div', 'ele2gb-table-wrapper');
            const table = createElement('table', 'ele2gb-wizard-table widefat fixed striped');
            const thead = document.createElement('thead');
            const headRow = document.createElement('tr');

            const selectAllTh = document.createElement('th');
            const selectAllCheckbox = document.createElement('input');
            selectAllCheckbox.type = 'checkbox';
            const visiblePages = this.getVisiblePages();
            const allVisibleSelected = visiblePages.every((page) => this.state.selectedPageIds.has(page.id));
            selectAllCheckbox.checked = visiblePages.length > 0 && allVisibleSelected;
            selectAllCheckbox.addEventListener('change', () => {
                visiblePages.forEach((page) => {
                    if (selectAllCheckbox.checked) {
                        this.state.selectedPageIds.add(page.id);
                    } else {
                        this.state.selectedPageIds.delete(page.id);
                        this.state.disabledMeta.delete(page.id);
                    }
                });
                this.render();
            });
            selectAllTh.appendChild(selectAllCheckbox);
            headRow.appendChild(selectAllTh);

            const columns = [
                this.strings.tableTitle || 'Title',
                this.strings.tableStatus || 'Status',
                this.strings.tableConversionStatus || 'Conversion status',
                this.strings.tableLastConverted || 'Last converted',
                this.strings.tableActions || 'Actions',
            ];
            columns.forEach((col) => {
                const th = document.createElement('th');
                th.textContent = col;
                headRow.appendChild(th);
            });

            thead.appendChild(headRow);
            table.appendChild(thead);

            const tbody = document.createElement('tbody');
            visiblePages.forEach((page) => {
                const tr = document.createElement('tr');

                const selectTd = document.createElement('td');
                const checkbox = document.createElement('input');
                checkbox.type = 'checkbox';
                checkbox.checked = this.state.selectedPageIds.has(page.id);
                checkbox.addEventListener('change', () => {
                    this.togglePageSelection(page.id, checkbox.checked);
                });
                selectTd.appendChild(checkbox);
                tr.appendChild(selectTd);

                const titleTd = document.createElement('td');
                titleTd.textContent = page.title;
                tr.appendChild(titleTd);

                const statusTd = document.createElement('td');
                statusTd.textContent = page.status;
                tr.appendChild(statusTd);

                const conversionTd = document.createElement('td');
                const badgeInfo = STATUS_BADGES[page.conversionStatus] || STATUS_BADGES.not_converted;
                const badge = createElement('span', 'ele2gb-status-badge ' + (badgeInfo ? badgeInfo.className : ''));
                const badgeLabel = badgeInfo ? (this.strings[badgeInfo.labelKey] || badgeInfo.labelKey) : (this.strings.statusUnknown || 'Unknown');
                badge.textContent = badgeLabel;
                conversionTd.appendChild(badge);
                tr.appendChild(conversionTd);

                const lastTd = document.createElement('td');
                lastTd.textContent = page.lastConverted || '—';
                tr.appendChild(lastTd);

                const actionsTd = document.createElement('td');
                actionsTd.className = 'actions';
                const metaToggle = document.createElement('label');
                metaToggle.className = 'ele2gb-inline-toggle';
                const metaCheckbox = document.createElement('input');
                metaCheckbox.type = 'checkbox';
                metaCheckbox.checked = this.state.disabledMeta.has(page.id);
                metaCheckbox.addEventListener('change', () => {
                    this.toggleDisableMeta(page.id, metaCheckbox.checked);
                });
                metaToggle.appendChild(metaCheckbox);
                metaToggle.appendChild(createElement('span', null, this.strings.disableMeta || 'Don’t copy meta fields & featured image'));
                actionsTd.appendChild(metaToggle);
                tr.appendChild(actionsTd);

                tbody.appendChild(tr);
            });

            table.appendChild(tbody);
            tableWrapper.appendChild(table);
            container.appendChild(tableWrapper);

            const pagination = this.renderPagination();
            if (pagination) {
                container.appendChild(pagination);
            }

            if (this.shouldShowSkipConvertedOption()) {
                const skipWrapper = createElement('div', 'ele2gb-step-description');
                const skipCheckbox = document.createElement('input');
                skipCheckbox.type = 'checkbox';
                skipCheckbox.checked = this.state.skipConverted;
                skipCheckbox.id = 'ele2gb-skip-converted';
                skipCheckbox.addEventListener('change', () => {
                    this.state.skipConverted = skipCheckbox.checked;
                    this.render();
                });
                const skipLabel = document.createElement('label');
                skipLabel.htmlFor = 'ele2gb-skip-converted';
                skipLabel.textContent = this.strings.skipConverted || 'Skip pages that were already converted';
                skipWrapper.appendChild(skipCheckbox);
                skipWrapper.appendChild(createElement('span', null, ' '));
                skipWrapper.appendChild(skipLabel);
                container.appendChild(skipWrapper);
            }

            const buttons = createElement('div', 'ele2gb-wizard-buttons');
            const backBtn = createButton(this.strings.back || 'Back', 'button button-secondary');
            backBtn.addEventListener('click', () => this.goToPrevious());
            buttons.appendChild(backBtn);

            const continueBtn = createButton(this.strings.continue || 'Continue', 'button button-primary');
            continueBtn.disabled = !this.hasAnySelection();
            continueBtn.addEventListener('click', () => {
                if (!this.hasAnySelection()) {
                    this.setNotice('error', this.strings.noSelectionError || 'Select at least one page or template before continuing.');
                    return;
                }
                this.clearNotice();
                this.goToNext();
            });
            buttons.appendChild(continueBtn);
            container.appendChild(buttons);

            return container;
        }

        renderTemplatesGroup(type, label) {
            const container = createElement('div', 'ele2gb-template-group');
            container.appendChild(createElement('h3', null, label));

            const templates = this.getTemplatesFor(type);
            const selectedSet = type === 'header' ? this.state.selectedHeaderIds : this.state.selectedFooterIds;
            const defaultId = type === 'header' ? this.state.defaultHeaderId : this.state.defaultFooterId;

            if (!templates.length) {
                const noneMessage = type === 'header' ? (this.strings.noHeadersFound || 'No header templates detected.') : (this.strings.noFootersFound || 'No footer templates detected.');
                container.appendChild(createElement('p', 'ele2gb-step-description', noneMessage));
                return container;
            }

            const tableWrapper = createElement('div', 'ele2gb-table-wrapper');
            const table = createElement('table', 'ele2gb-wizard-table widefat fixed striped');
            const thead = document.createElement('thead');
            const headRow = document.createElement('tr');

            const selectTh = document.createElement('th');
            headRow.appendChild(selectTh);

            [
                this.strings.tableTitle || 'Title',
                this.strings.tableStatus || 'Status',
                this.strings.tableLastConverted || 'Last converted',
            ].forEach((heading) => {
                const th = document.createElement('th');
                th.textContent = heading;
                headRow.appendChild(th);
            });

            thead.appendChild(headRow);
            table.appendChild(thead);

            const tbody = document.createElement('tbody');
            templates.forEach((template) => {
                const id = Number(template.id);
                const tr = document.createElement('tr');

                const selectTd = document.createElement('td');
                const checkbox = document.createElement('input');
                checkbox.type = 'checkbox';
                checkbox.checked = selectedSet.has(id);
                checkbox.addEventListener('change', () => {
                    this.toggleTemplateSelection(type, id, checkbox.checked);
                });
                selectTd.appendChild(checkbox);
                tr.appendChild(selectTd);

                const titleTd = document.createElement('td');
                const titleWrapper = createElement('div', 'ele2gb-template-title', template.title);
                titleTd.appendChild(titleWrapper);
                const metaLine = createElement('div', 'ele2gb-template-meta');
                if (template.sourceLabel) {
                    const source = createElement('span', 'ele2gb-template-source', template.sourceLabel);
                    metaLine.appendChild(source);
                }
                if (template.isLikelyGlobal) {
                    const flag = createElement('span', 'ele2gb-template-flag', this.strings.likelyGlobal || 'Likely global');
                    metaLine.appendChild(flag);
                }
                if (metaLine.childNodes.length) {
                    titleTd.appendChild(metaLine);
                }
                tr.appendChild(titleTd);

                const statusTd = document.createElement('td');
                const badgeInfo = STATUS_BADGES[template.conversionStatus] || STATUS_BADGES.not_converted;
                const badgeLabel = badgeInfo ? (this.strings[badgeInfo.labelKey] || badgeInfo.labelKey) : (this.strings.statusUnknown || 'Unknown');
                const badge = createElement('span', 'ele2gb-status-badge ' + (badgeInfo ? badgeInfo.className : ''), badgeLabel);
                statusTd.appendChild(badge);
                if (template.lastResultMessage) {
                    statusTd.appendChild(createElement('div', 'ele2gb-template-message', template.lastResultMessage));
                }
                tr.appendChild(statusTd);

                const lastTd = document.createElement('td');
                lastTd.textContent = template.lastConverted || '—';
                tr.appendChild(lastTd);

                tbody.appendChild(tr);
            });

            table.appendChild(tbody);
            tableWrapper.appendChild(table);
            container.appendChild(tableWrapper);

            const defaultWrapper = createElement('div', 'ele2gb-default-selection');
            const labelText = type === 'header' ? (this.strings.defaultHeaderLabel || 'Default header after conversion') : (this.strings.defaultFooterLabel || 'Default footer after conversion');
            defaultWrapper.appendChild(createElement('p', 'ele2gb-step-description', labelText));

            const options = createElement('div', 'ele2gb-default-options');
            const selectedTemplates = templates.filter((template) => selectedSet.has(Number(template.id)));
            if (selectedTemplates.length) {
                selectedTemplates.forEach((template) => {
                    const id = Number(template.id);
                    const optionLabel = document.createElement('label');
                    const input = document.createElement('input');
                    input.type = 'radio';
                    input.name = type === 'header' ? 'ele2gb-default-header' : 'ele2gb-default-footer';
                    input.value = id;
                    input.checked = defaultId === id;
                    input.addEventListener('change', () => {
                        this.setDefaultTemplate(type, id);
                    });
                    optionLabel.appendChild(input);
                    optionLabel.appendChild(createElement('span', null, template.title));
                    options.appendChild(optionLabel);
                });
            } else {
                const message = type === 'header' ? (this.strings.noHeadersSelected || 'No headers selected for conversion.') : (this.strings.noFootersSelected || 'No footers selected for conversion.');
                options.appendChild(createElement('p', 'ele2gb-step-description', message));
            }

            defaultWrapper.appendChild(options);
            container.appendChild(defaultWrapper);

            return container;
        }

        renderTemplatesStep() {
            const container = createElement('div');
            container.appendChild(createElement('h2', 'ele2gb-wizard-step-title', this.strings.headerFooterStepTitle || 'Header & Footer Templates'));

            container.appendChild(this.renderTemplatesGroup('header', this.strings.headersLabel || 'Headers'));
            container.appendChild(this.renderTemplatesGroup('footer', this.strings.footersLabel || 'Footers'));

            const buttons = createElement('div', 'ele2gb-wizard-buttons');
            const backBtn = createButton(this.strings.back || 'Back', 'button button-secondary');
            backBtn.addEventListener('click', () => this.goToPrevious());
            buttons.appendChild(backBtn);

            const continueBtn = createButton(this.strings.continue || 'Continue', 'button button-primary');
            continueBtn.disabled = !this.hasAnySelection();
            continueBtn.addEventListener('click', () => {
                if (!this.hasAnySelection()) {
                    this.setNotice('error', this.strings.noSelectionError || 'Select at least one page or template before continuing.');
                    return;
                }
                this.clearNotice();
                this.goToNext();
            });
            buttons.appendChild(continueBtn);
            container.appendChild(buttons);

            return container;
        }

        renderPagination() {
            const totalPages = Math.max(1, Math.ceil(this.pages.length / this.state.perPage));
            if (totalPages <= 1) {
                return null;
            }
            const pagination = createElement('div', 'ele2gb-pagination');
            const prev = createButton('‹', 'button button-secondary');
            prev.disabled = this.state.tablePage <= 1;
            prev.addEventListener('click', () => {
                if (this.state.tablePage > 1) {
                    this.state.tablePage -= 1;
                    this.render();
                }
            });
            pagination.appendChild(prev);

            pagination.appendChild(createElement('span', null, this.state.tablePage + ' / ' + totalPages));

            const next = createButton('›', 'button button-secondary');
            next.disabled = this.state.tablePage >= totalPages;
            next.addEventListener('click', () => {
                if (this.state.tablePage < totalPages) {
                    this.state.tablePage += 1;
                    this.render();
                }
            });
            pagination.appendChild(next);
            return pagination;
        }

        getVisiblePages() {
            const start = (this.state.tablePage - 1) * this.state.perPage;
            return this.pages.slice(start, start + this.state.perPage);
        }

        renderConflictStep() {
            const container = createElement('div');
            container.appendChild(createElement('h2', 'ele2gb-wizard-step-title', this.strings.conflictsTitle || 'Resolve Conflicts'));
            const count = this.getConflictCount();
            const summary = formatString(this.strings.conflictDetected || '%1$d selected pages already have a converted version.', count);
            container.appendChild(createElement('p', 'ele2gb-step-description', summary));

            const options = [
                { key: 'overwrite', label: this.strings.conflictOverwrite || 'Update existing pages in place (overwrite)' },
                { key: 'skip', label: this.strings.conflictSkip || 'Skip those pages' },
                { key: 'duplicate', label: this.strings.conflictDuplicate || 'Create duplicates with “(Converted)” suffix' },
            ];

            const wrapper = createElement('div', 'ele2gb-conflict-options');
            options.forEach((option) => {
                const label = document.createElement('label');
                const input = document.createElement('input');
                input.type = 'radio';
                input.name = 'ele2gb-conflict-policy';
                input.value = option.key;
                input.checked = this.state.conflictPolicy === option.key;
                input.addEventListener('change', () => {
                    this.state.conflictPolicy = option.key;
                });
                label.appendChild(input);
                label.appendChild(createElement('span', null, option.label));
                wrapper.appendChild(label);
            });
            container.appendChild(wrapper);

            const buttons = createElement('div', 'ele2gb-wizard-buttons');
            const backBtn = createButton(this.strings.back || 'Back', 'button button-secondary');
            backBtn.addEventListener('click', () => this.goToPrevious());
            buttons.appendChild(backBtn);

            const continueBtn = createButton(this.strings.continue || 'Continue', 'button button-primary');
            continueBtn.addEventListener('click', () => this.goToNext());
            buttons.appendChild(continueBtn);
            container.appendChild(buttons);

            return container;
        }

        renderReviewStep() {
            const container = createElement('div');
            container.appendChild(createElement('h2', 'ele2gb-wizard-step-title', this.strings.reviewTitle || 'Review & Confirm'));

            const selectedCount = this.state.selectedPageIds.size;
            const convertedSelected = this.getSelectedPages().filter((page) => page.conversionStatus === 'converted').length;
            const convertCount = this.state.skipConverted ? Math.max(0, selectedCount - convertedSelected) : selectedCount;
            const skippedCount = selectedCount - convertCount;

            const summary = formatString(this.strings.reviewSummary || '%1$d pages selected — %2$d will be converted, %3$d skipped.', selectedCount, convertCount, skippedCount);
            const list = document.createElement('ul');
            list.className = 'ele2gb-review-list';
            list.appendChild(createElement('li', null, summary));

            const modeLabel = this.state.mode === 'auto' ? (this.strings.modeAutoTitle || 'Convert all pages automatically') : (this.strings.modeCustomTitle || 'Choose specific pages');
            list.appendChild(createElement('li', null, modeLabel));

            if (this.shouldShowConflictStep()) {
                let policyLabel = '';
                switch (this.state.conflictPolicy) {
                    case 'overwrite':
                        policyLabel = this.strings.conflictOverwrite || 'Update existing pages in place (overwrite)';
                        break;
                    case 'duplicate':
                        policyLabel = this.strings.conflictDuplicate || 'Create duplicates with “(Converted)” suffix';
                        break;
                    default:
                        policyLabel = this.strings.conflictSkip || 'Skip those pages';
                }
                list.appendChild(createElement('li', null, policyLabel));
            }

            if (this.state.disabledMeta.size > 0) {
                const metaNote = formatString(this.strings.metaDisabled || '%1$d pages will be converted without copying meta fields or featured image.', this.state.disabledMeta.size);
                list.appendChild(createElement('li', null, metaNote));
            }

            if (this.state.mode === 'custom') {
                const headerCount = this.state.selectedHeaderIds.size;
                const footerCount = this.state.selectedFooterIds.size;
                if (headerCount || footerCount) {
                    list.appendChild(createElement('li', null, formatString(this.strings.headerFooterSummary || '%1$d headers and %2$d footers selected for conversion.', headerCount, footerCount)));
                    const defaultHeader = headerCount ? this.getTemplateById(this.state.defaultHeaderId) : null;
                    const defaultFooter = footerCount ? this.getTemplateById(this.state.defaultFooterId) : null;
                    const headerTitle = defaultHeader ? defaultHeader.title : '—';
                    const footerTitle = defaultFooter ? defaultFooter.title : '—';
                    list.appendChild(createElement('li', null, formatString(this.strings.headerFooterDefaults || 'Default header: %1$s — Default footer: %2$s', headerTitle, footerTitle)));
                }
            }

            container.appendChild(list);
            container.appendChild(createElement('p', 'ele2gb-wizard-footer-note', this.strings.backgroundInfo || 'Conversion runs in the background. You can safely close this page.'));

            const buttons = createElement('div', 'ele2gb-wizard-buttons');
            const backBtn = createButton(this.strings.back || 'Back', 'button button-secondary');
            backBtn.addEventListener('click', () => this.goToPrevious());
            buttons.appendChild(backBtn);

            const startBtn = createButton(this.strings.startConversion || 'Start Conversion', 'button button-primary');
            startBtn.disabled = this.state.isSubmitting;
            startBtn.addEventListener('click', () => {
                if (!this.state.isSubmitting) {
                    this.startConversion();
                }
            });
            buttons.appendChild(startBtn);
            container.appendChild(buttons);

            return container;
        }

        renderProgressStep() {
            const container = createElement('div');
            container.appendChild(createElement('h2', 'ele2gb-wizard-step-title', this.strings.progressTitle || 'Progress & Results'));

            if (!this.state.job) {
                container.appendChild(createElement('p', null, this.strings.processing || 'Processing…'));
                return container;
            }

            if (this.state.resumed) {
                container.appendChild(createElement('div', 'ele2gb-alert ele2gb-alert-info', this.strings.resumeJob || 'Resuming an active conversion job.'));
            }

            const job = this.state.job;
            const progressBar = createElement('div', 'ele2gb-progress-bar ele2gb-progress-bar-large');
            const percent = job.total ? Math.min(100, Math.round((job.processed / job.total) * 100)) : 0;
            const bar = document.createElement('span');
            bar.style.width = percent + '%';
            progressBar.appendChild(bar);
            container.appendChild(progressBar);

            const summary = createElement('div', 'ele2gb-progress-summary');
            const successCount = job.counts && job.counts.success ? job.counts.success : 0;
            const skippedCount = job.counts && job.counts.skipped ? job.counts.skipped : 0;
            const errorCount = job.counts && job.counts.error ? job.counts.error : 0;
            summary.appendChild(createElement('div', null, (this.strings.converted || 'Converted') + ': ' + successCount));
            summary.appendChild(createElement('div', null, (this.strings.skipped || 'Skipped') + ': ' + skippedCount));
            summary.appendChild(createElement('div', null, (this.strings.errors || 'Errors') + ': ' + errorCount));
            summary.appendChild(createElement('div', null, (this.strings.duration || 'Duration') + ': ' + formatDuration(job.duration)));
            container.appendChild(summary);

            let message = '';
            if (job.status === 'completed') {
                message = errorCount > 0 ? formatString(this.strings.jobCompletedWithErrors || 'Conversion finished with issues in %s.', formatDuration(job.duration)) : formatString(this.strings.jobCompleted || 'Conversion completed successfully in %s.', formatDuration(job.duration));
            } else {
                message = this.strings.jobRunning || 'Conversion in progress…';
            }
            container.appendChild(createElement('p', 'ele2gb-step-description', message));

            const resultsTable = this.renderResultsTable();
            if (resultsTable) {
                container.appendChild(resultsTable);
            }

            const actions = createElement('div', 'ele2gb-results-actions');
            const viewLink = document.createElement('a');
            viewLink.className = 'button button-secondary';
            viewLink.href = 'edit.php?post_type=page';
            viewLink.textContent = this.strings.viewPages || 'View converted pages';
            actions.appendChild(viewLink);
            if (job.status !== 'completed') {
                const cancelBtn = createButton(this.strings.cancel || 'Cancel', 'button button-secondary');
                cancelBtn.addEventListener('click', () => {
                    if (this.state.isSubmitting) {
                        return;
                    }
                    this.state.isSubmitting = true;
                    this.render();
                    this.cancelCurrentJob();
                });
                actions.appendChild(cancelBtn);
            }
            if (job.status === 'completed') {
                const startNew = createButton(this.strings.startNew || 'Start new conversion', 'button button-primary');
                startNew.addEventListener('click', () => this.resetWizard());
                actions.appendChild(startNew);
            }
            container.appendChild(actions);

            return container;
        }

        renderResultsTable() {
            if (!this.state.job || !Array.isArray(this.state.job.results) || !this.state.job.results.length) {
                return null;
            }
            const wrapper = createElement('div', 'ele2gb-results-table ele2gb-table-wrapper');
            const table = createElement('table', 'ele2gb-wizard-table');
            const thead = document.createElement('thead');
            const headRow = document.createElement('tr');
            [
                this.strings.tableTitle || 'Title',
                this.strings.tableStatus || 'Status',
                this.strings.duration || 'Duration',
                this.strings.tableActions || 'Actions',
            ].forEach((heading) => {
                const th = document.createElement('th');
                th.textContent = heading;
                headRow.appendChild(th);
            });
            thead.appendChild(headRow);
            table.appendChild(thead);

            const tbody = document.createElement('tbody');
            this.state.job.results.forEach((result) => {
                const tr = document.createElement('tr');

                const titleTd = document.createElement('td');
                const titleWrapper = createElement('div', null, result.title);
                titleTd.appendChild(titleWrapper);
                const metaParts = [];
                const typeLabel = this.formatResultType(result.type);
                if (typeLabel) {
                    metaParts.push(typeLabel);
                }
                const roleLabel = this.formatResultRole(result.role);
                if (roleLabel) {
                    metaParts.push(roleLabel);
                }
                if (result.type === 'header' || result.type === 'footer') {
                    const templateInfo = this.getTemplateById(result.id);
                    if (templateInfo && templateInfo.sourceLabel) {
                        metaParts.push(templateInfo.sourceLabel);
                    }
                }
                if (metaParts.length) {
                    titleTd.appendChild(createElement('div', 'ele2gb-result-meta', metaParts.join(' · ')));
                }
                tr.appendChild(titleTd);

                const statusTd = document.createElement('td');
                statusTd.className = 'status';
                const resultConfig = RESULT_STATUS[result.status] || { badge: 'not_converted', labelKey: 'statusUnknown' };
                const badgeInfo = STATUS_BADGES[resultConfig.badge] || STATUS_BADGES.not_converted;
                const badge = createElement('span', 'ele2gb-status-badge ' + badgeInfo.className, this.strings[resultConfig.labelKey] || result.status);
                statusTd.appendChild(badge);
                if (result.message) {
                    statusTd.appendChild(createElement('div', null, result.message));
                }
                tr.appendChild(statusTd);

                const durationTd = document.createElement('td');
                durationTd.className = 'duration';
                durationTd.textContent = formatDuration(result.duration);
                tr.appendChild(durationTd);

                const actionsTd = document.createElement('td');
                actionsTd.className = 'actions';
                if (result.viewUrl) {
                    const viewLink = document.createElement('a');
                    viewLink.href = result.viewUrl;
                    viewLink.textContent = this.strings.viewConverted || 'View converted';
                    viewLink.target = '_blank';
                    viewLink.rel = 'noopener noreferrer';
                    actionsTd.appendChild(viewLink);
                }
                if (result.status === 'error' && result.type === 'page') {
                    const retryLink = document.createElement('a');
                    retryLink.href = '#';
                    retryLink.textContent = this.strings.retry || 'Retry';
                    retryLink.addEventListener('click', (event) => {
                        event.preventDefault();
                        this.retryConversionForPage(result.id, result.keepMeta);
                    });
                    actionsTd.appendChild(retryLink);
                }
                tr.appendChild(actionsTd);

                tbody.appendChild(tr);
            });
            table.appendChild(tbody);
            wrapper.appendChild(table);
            return wrapper;
        }

        render() {
            this.root.innerHTML = '';
            this.root.appendChild(this.renderHeader());
            const notice = this.renderNotice();
            if (notice) {
                this.root.appendChild(notice);
            }

            let stepContent = null;
            switch (this.state.currentStep) {
                case 'mode':
                    stepContent = this.renderModeStep();
                    break;
                case 'select':
                    stepContent = this.renderSelectStep();
                    break;
                case 'templates':
                    stepContent = this.renderTemplatesStep();
                    break;
                case 'conflicts':
                    stepContent = this.renderConflictStep();
                    break;
                case 'review':
                    stepContent = this.renderReviewStep();
                    break;
                case 'progress':
                    stepContent = this.renderProgressStep();
                    break;
                default:
                    stepContent = createElement('div', null, '');
            }

            if (stepContent) {
                this.root.appendChild(stepContent);
            }
        }
    }

    const wizard = new WizardApp(root, data);
    wizard.init();
})(window, document);