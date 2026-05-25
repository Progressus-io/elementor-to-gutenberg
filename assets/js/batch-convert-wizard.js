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
        converted: {labelKey: 'statusConverted', className: 'ele2gb-status-converted'},
        not_converted: {labelKey: 'statusNotConverted', className: 'ele2gb-status-not_converted'},
        partial: {labelKey: 'statusPartial', className: 'ele2gb-status-partial'},
        error: {labelKey: 'statusError', className: 'ele2gb-status-error'},
        skipped: {labelKey: 'statusSkipped', className: 'ele2gb-status-skipped'},
    };

    const RESULT_STATUS = {
        success: {labelKey: 'statusConverted', badge: 'converted'},
        error: {labelKey: 'statusError', badge: 'error'},
        skipped: {labelKey: 'statusSkipped', badge: 'skipped'},
        partial: {labelKey: 'statusPartial', badge: 'partial'},
    };

    function formatString(template, ...values) {
        if (typeof template !== 'string') {
            return '';
        }

        let nextIndex = 0;
        return template.replace(/%(?:([0-9]+)\$)?([sd])/g, function (match, index) {
            const valueIndex = index ? parseInt(index, 10) - 1 : nextIndex++;
            const value = values[valueIndex];
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
            this.postTypes = Array.isArray(config.postTypes) ? config.postTypes.slice() : [];
            this.themes = config.themes || {currentTheme: null, installedBlockThemes: [], suggestedCoreThemes: []};
            this.templates = config.templates || {
                headers: [],
                footers: [],
                defaults: {header: 0, footer: 0},
                counts: {headers: 0, footers: 0},
            };
            this.state = {
                currentStep: 'mode',
                mode: 'auto',
                modeSelection: 'auto',
                selectedPageIds: new Set(),
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
                selectedThemeSlug: this.getCurrentThemeSlug(),
                changeTheme: false,
                copyCustomCss: true,
                aiImprove: null,
                filterStatus: 'all',
                searchQuery: '',
                activeTab: this.postTypes.length ? this.postTypes[0].slug : '',
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
                this.state.selectedHeaderIds = new Set(this.getTemplatesFor('header').map((template) => Number(template.id)));
                this.state.selectedFooterIds = new Set(this.getTemplatesFor('footer').map((template) => Number(template.id)));
                this.state.defaultHeaderId = this.pickDefaultTemplate('header', this.state.selectedHeaderIds, defaultHeader);
                this.state.defaultFooterId = this.pickDefaultTemplate('footer', this.state.selectedFooterIds, defaultFooter);
                this.state.skipConverted = false;
                this.state.conflictPolicy = 'overwrite';
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

        resetThemeSelection() {
            this.state.selectedThemeSlug = this.getCurrentThemeSlug();
            this.state.changeTheme = false;
            this.state.copyCustomCss = true;
        }

        getCurrentThemeSlug() {
            return (this.themes.currentTheme && this.themes.currentTheme.slug) || '';
        }

        getCurrentThemeName() {
            return (this.themes.currentTheme && this.themes.currentTheme.name) || '';
        }

        isCurrentThemeBlock() {
            return !!(this.themes.currentTheme && this.themes.currentTheme.isBlockTheme);
        }

        willChangeTheme() {
            const targetSlug = this.state.selectedThemeSlug || this.getCurrentThemeSlug();
            return !!(targetSlug && targetSlug !== this.getCurrentThemeSlug());
        }

        shouldCopyCss() {
            if (!this.willChangeTheme()) {
                return false;
            }

            if (this.state.mode === 'auto') {
                return true;
            }

            return !!this.state.copyCustomCss;
        }

        getSelectedTheme() {
            const slug = this.state.selectedThemeSlug || this.getCurrentThemeSlug();
            const installed = Array.isArray(this.themes.installedBlockThemes) ? this.themes.installedBlockThemes : [];
            const suggested = Array.isArray(this.themes.suggestedCoreThemes) ? this.themes.suggestedCoreThemes : [];

            return installed.find((theme) => theme.slug === slug) || suggested.find((theme) => theme.slug === slug) || null;
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
            if (this.state.currentStep === 'ai_improve') {
                return ['progress', 'ai_improve'];
            }
            if (this.state.job && this.state.currentStep === 'progress') {
                return ['progress'];
            }

            const steps = ['mode', 'theme'];
            if (this.state.mode === 'custom') {
                steps.push('templates');
                steps.push('select');
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
                case 'theme':
                    return this.strings.themeStepTitle || 'Theme compatibility';
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
                case 'ai_improve':
                    return this.strings.aiImproveTitle || 'AI Improvement';
                default:
                    return '';
            }
        }

        setNotice(type, message) {
            if (!message) {
                this.state.notice = null;
            } else {
                this.state.notice = {type: type || 'info', message};
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
            }
            this.clearNotice();
            this.render();
        }

        shouldShowSkipConvertedOption() {
            if (this.state.mode === 'custom') {
                return false;
            }
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
                this.request('ele2gb_poll_job', {jobId: this.state.job.id})
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

            this.request('ele2gb_cancel_job', {jobId: this.state.job.id})
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

        getThemePayload() {
            const willChange = this.willChangeTheme();
            const payload = {
                changeTheme: willChange ? 1 : 0,
            };

            if (willChange) {
                payload.newTheme = this.state.selectedThemeSlug || this.getCurrentThemeSlug();
                payload.copyCustomCss = this.shouldCopyCss() ? 1 : 0;
            }

            return payload;
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
                skipConverted: this.state.skipConverted ? 1 : 0,
                conflictPolicy: this.state.conflictPolicy,
            };

            const themePayload = this.getThemePayload();
            Object.assign(payload, themePayload);

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

        retryConversionForPage(pageId) {
            const payload = Object.assign({}, this.state.lastPayload || {});
            payload.mode = 'custom';
            payload.pages = [pageId];
            payload.skipConverted = 0;
            payload.conflictPolicy = this.state.job ? this.state.job.conflictPolicy || 'skip' : 'skip';
            delete payload.disabledMeta;
            delete payload.headerTemplates;
            delete payload.footerTemplates;
            delete payload.defaultHeader;
            delete payload.defaultFooter;

            this.state.mode = 'custom';
            this.state.modeSelection = 'custom';
            this.state.selectedPageIds = new Set([pageId]);
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
                    if (response && response.preflight) {
                        this.config.preflight = response.preflight;
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
                aiImprove: null,
            });
            this.resetSelectionForMode('auto');
            this.resetThemeSelection();
            this.clearNotice();
            this.render();
        }

        getStepShortLabel(step) {
            const map = {
                mode:       this.strings.stepLabelMode       || 'Mode',
                theme:      this.strings.stepLabelTheme      || 'Theme',
                select:     this.strings.stepLabelSelect     || 'Pages',
                templates:  this.strings.stepLabelTemplates  || 'Templates',
                conflicts:  this.strings.stepLabelConflicts  || 'Conflicts',
                review:     this.strings.stepLabelReview     || 'Review',
                progress:   this.strings.stepLabelProgress   || 'Convert',
                ai_improve: this.strings.stepLabelAiImprove  || 'AI Improve',
            };
            return map[step] || step;
        }

        renderHeader() {
            const header = createElement('div', 'ele2gb-wizard-header');
            const steps = this.getStepSequence();
            const currentIndex = Math.max(0, steps.indexOf(this.state.currentStep));

            const stepper = createElement('div', 'ele2gb-stepper');
            const svgNS = 'http://www.w3.org/2000/svg';

            steps.forEach((step, i) => {
                let stateClass = '';
                if (i < currentIndex) {
                    stateClass = ' is-completed';
                } else if (i === currentIndex) {
                    stateClass = ' is-current';
                }

                const item = createElement('div', 'ele2gb-stepper-item' + stateClass);

                const circle = createElement('div', 'ele2gb-stepper-circle');
                if (i < currentIndex) {
                    const checkSvg = document.createElementNS(svgNS, 'svg');
                    checkSvg.setAttribute('class', 'ele2gb-stepper-check');
                    checkSvg.setAttribute('viewBox', '0 0 20 20');
                    checkSvg.setAttribute('fill', 'currentColor');
                    checkSvg.setAttribute('aria-hidden', 'true');
                    const path = document.createElementNS(svgNS, 'path');
                    path.setAttribute('d', 'M7.5 13.5 4 10l1.4-1.4 2.1 2.1 5.1-5.1L14 7z');
                    checkSvg.appendChild(path);
                    circle.appendChild(checkSvg);
                } else {
                    circle.textContent = String(i + 1);
                }

                item.appendChild(circle);
                item.appendChild(createElement('span', 'ele2gb-stepper-label', this.getStepShortLabel(step)));
                stepper.appendChild(item);
            });

            header.appendChild(stepper);
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

            const preflight = this.config.preflight || {};
            const svgNS = 'http://www.w3.org/2000/svg';

            const makeIcon = function (pathD) {
                const wrap = createElement('div', 'ele2gb-mode-card-icon');
                const svg = document.createElementNS(svgNS, 'svg');
                svg.setAttribute('viewBox', '0 0 24 24');
                svg.setAttribute('fill', 'none');
                svg.setAttribute('stroke', 'currentColor');
                svg.setAttribute('stroke-width', '2');
                svg.setAttribute('stroke-linecap', 'round');
                svg.setAttribute('stroke-linejoin', 'round');
                svg.setAttribute('aria-hidden', 'true');
                const path = document.createElementNS(svgNS, 'path');
                path.setAttribute('d', pathD);
                svg.appendChild(path);
                wrap.appendChild(svg);
                return wrap;
            };

            modes.forEach((mode) => {
                const card = createElement('label', 'ele2gb-mode-card' + (this.state.modeSelection === mode.key ? ' is-active' : ''));
                const input = document.createElement('input');
                input.type = 'radio';
                input.name = 'ele2gb-mode';
                input.value = mode.key;
                input.checked = this.state.modeSelection === mode.key;
                input.className = 'screen-reader-text';
                input.addEventListener('change', () => {
                    this.state.modeSelection = mode.key;
                    if (mode.key === 'auto') {
                        this.state.skipConverted = true;
                    }
                    this.render();
                });
                card.appendChild(input);

                // Icon
                const iconPath = mode.key === 'auto'
                    ? 'M13 2L3 14h7l-1 8 10-12h-7l1-8z'
                    : 'M12 20h9M16.5 3.5a2.12 2.12 0 113 3L7 19l-4 1 1-4 12.5-12.5z';
                card.appendChild(makeIcon(iconPath));

                const subtextKey = mode.key === 'auto' ? 'modeAutoSubtext' : 'modeCustomSubtext';
                const subtext = this.strings[subtextKey];
                if (subtext) {
                    card.appendChild(createElement('p', 'ele2gb-mode-subtext', subtext));
                }

                const title = createElement('h3', null, mode.title);
                card.appendChild(title);
                if (mode.description) {
                    card.appendChild(createElement('p', null, mode.description));
                }

                if (preflight.eligibleCount !== undefined) {
                    const contextLine = mode.key === 'auto'
                        ? formatString(
                            '%1$d eligible pages · %2$d headers · %3$d footers',
                            preflight.eligibleCount,
                            preflight.headersCount || 0,
                            preflight.footersCount || 0
                        )
                        : formatString(
                            '%1$d total pages available to select',
                            (preflight.eligibleCount || 0) + (preflight.convertedCount || 0)
                        );
                    card.appendChild(createElement('small', 'ele2gb-mode-context-line', contextLine));
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

        getJobWarnings() {
            if (!this.state.job || !Array.isArray(this.state.job.warnings)) {
                return [];
            }
            return this.state.job.warnings;
        }

        getThemeWarnings() {
            return this.getJobWarnings().filter((warning) => {
                return warning && typeof warning.code === 'string' && warning.code.indexOf('theme_') === 0;
            });
        }

        renderThemeWarnings() {
            const warnings = this.getThemeWarnings();
            if (!warnings.length) {
                return null;
            }

            const wrapper = createElement('div', 'ele2gb-theme-warning-list');
            warnings.forEach((warning) => {
                const message = warning.message || this.strings.themeWarningInline || 'Theme step failed — conversion continued using current theme. Update WordPress to use this theme.';
                const details = warning.details ? ' ' + warning.details : '';
                wrapper.appendChild(createElement('div', 'ele2gb-alert ele2gb-alert-warning', message + details));
            });
            return wrapper;
        }

        selectTheme(slug) {
            this.state.selectedThemeSlug = slug;
            this.state.changeTheme = slug !== this.getCurrentThemeSlug();
            if (this.state.mode === 'auto' && this.state.changeTheme) {
                this.state.copyCustomCss = true;
            }
            this.clearNotice();
            this.render();
        }

        renderThemeCard(theme) {
            const isSelected = (this.state.selectedThemeSlug || this.getCurrentThemeSlug()) === theme.slug;
            const isActive = !!theme.isActive || theme.slug === this.getCurrentThemeSlug();
            const isInstalled = theme.isInstalled !== false;
            var cardClass = 'ele2gb-theme-browser-card' + (isSelected ? ' is-selected' : '') + (isActive ? ' ele2gb-theme-card--current' : '');
            const card = createElement('article', cardClass);

            const preview = createElement('div', 'ele2gb-theme-card-preview');
            if (theme.screenshot) {
                const image = document.createElement('img');
                image.src = theme.screenshot;
                image.alt = theme.name || theme.slug;
                preview.appendChild(image);
            } else {
                preview.appendChild(createElement('div', 'ele2gb-theme-card-no-preview', theme.name || theme.slug));
            }

            const actions = createElement('div', 'ele2gb-theme-card-actions');
            const buttonLabel = isActive
                ? (this.strings.themeActionActive || 'Active')
                : (isInstalled ? (this.strings.themeActionUseTheme || 'Use this theme') : (this.strings.themeActionInstall || 'Install'));
            const buttonClass = 'button button-primary' + (isActive ? ' disabled' : '');
            const actionButton = createButton(buttonLabel, buttonClass);
            actionButton.disabled = isActive;
            actionButton.addEventListener('click', (event) => {
                event.preventDefault();
                if (!isActive) {
                    this.selectTheme(theme.slug);
                }
            });
            actions.appendChild(actionButton);
            preview.appendChild(actions);
            card.appendChild(preview);

            const body = createElement('div', 'ele2gb-theme-card-body');
            const titleRow = createElement('div', 'ele2gb-theme-card-title-row');
            titleRow.appendChild(createElement('h3', 'ele2gb-theme-card-title', theme.name || theme.slug));

            const statusText = isActive
                ? (this.strings.themeActiveLabel || 'Active')
                : (isInstalled ? (this.strings.themeStatusInstalled || 'Installed') : (this.strings.themeStatusNotInstalled || 'Not installed'));
            titleRow.appendChild(createElement('span', 'ele2gb-theme-status-pill', statusText));
            body.appendChild(titleRow);

            const labels = createElement('div', 'ele2gb-theme-card-labels');
            labels.appendChild(createElement('span', 'ele2gb-theme-chip', this.strings.themeBlockLabel || 'Block theme'));
            if (isSelected && !isActive) {
                labels.appendChild(createElement('span', 'ele2gb-theme-chip ele2gb-theme-chip-selected', this.strings.themeSelected || 'Selected'));
            }
            body.appendChild(labels);

            const selector = document.createElement('input');
            selector.type = 'radio';
            selector.name = 'ele2gb-theme-choice';
            selector.value = theme.slug;
            selector.checked = isSelected;
            selector.className = 'screen-reader-text';
            selector.addEventListener('change', () => this.selectTheme(theme.slug));
            body.appendChild(selector);

            card.addEventListener('click', (event) => {
                if (event.target.tagName && event.target.tagName.toLowerCase() === 'button') {
                    return;
                }
                this.selectTheme(theme.slug);
            });

            card.appendChild(body);

            return card;
        }

        renderThemeStep() {
            const container = createElement('div');
            container.appendChild(createElement('h2', 'ele2gb-wizard-step-title', this.strings.themeStepTitle || 'Theme compatibility'));
            if (this.strings.themeStepDesc) {
                container.appendChild(createElement('p', 'ele2gb-step-description', this.strings.themeStepDesc));
            }

            const warningList = this.renderThemeWarnings();
            if (warningList) {
                container.appendChild(warningList);
            }

            if (this.strings.themeCompatibilityNote) {
                container.appendChild(createElement('p', 'ele2gb-step-description', this.strings.themeCompatibilityNote));
            }

            const browser = createElement('div', 'ele2gb-theme-browser-grid');
            const currentTheme = {
                slug: this.getCurrentThemeSlug(),
                name: this.getCurrentThemeName(),
                isActive: true,
                isInstalled: true,
                screenshot: this.themes.currentTheme && this.themes.currentTheme.screenshot ? this.themes.currentTheme.screenshot : '',
            };
            browser.appendChild(this.renderThemeCard(currentTheme));

            const installed = Array.isArray(this.themes.installedBlockThemes) ? this.themes.installedBlockThemes : [];
            const suggested = Array.isArray(this.themes.suggestedCoreThemes) ? this.themes.suggestedCoreThemes : [];

            installed
                .filter((theme) => theme.slug !== currentTheme.slug)
                .forEach((theme) => {
                    browser.appendChild(this.renderThemeCard(theme));
                });

            suggested.forEach((theme) => {
                browser.appendChild(this.renderThemeCard(theme));
            });

            container.appendChild(browser);

            if (this.willChangeTheme() && this.strings.themeChangeWarning) {
                container.appendChild(createElement('div', 'ele2gb-alert ele2gb-alert-warning ele2gb-theme-change-warning', this.strings.themeChangeWarning));
            }

            const selectedTheme = this.getSelectedTheme();
            const selectedName = (selectedTheme && selectedTheme.name) || this.getCurrentThemeName();
            const selectedSummary = this.willChangeTheme()
                ? formatString(this.strings.themeSelectedSummary || 'Selected: %s', selectedName)
                : formatString(this.strings.themeUsingCurrentSummary || 'Using current theme: %s', this.getCurrentThemeName());
            container.appendChild(createElement('p', 'ele2gb-theme-selected-summary', selectedSummary));

            const cssPanel = createElement('div', 'ele2gb-theme-options-panel');
            if (this.willChangeTheme() && this.state.mode === 'custom') {
                const cssWrapper = document.createElement('label');
                cssWrapper.className = 'ele2gb-inline-toggle';
                const checkbox = document.createElement('input');
                checkbox.type = 'checkbox';
                checkbox.checked = this.state.copyCustomCss;
                checkbox.addEventListener('change', () => {
                    this.state.copyCustomCss = checkbox.checked;
                    this.render();
                });
                cssWrapper.appendChild(checkbox);
                cssWrapper.appendChild(createElement('span', null, this.strings.copyAdditionalCss || 'Copy Additional CSS from the current theme'));
                cssPanel.appendChild(cssWrapper);
            } else if (this.willChangeTheme() && this.state.mode === 'auto') {
                cssPanel.appendChild(createElement('p', 'ele2gb-step-description', this.strings.copyAdditionalCss || 'Copy Additional CSS from the current theme'));
            }
            if (cssPanel.childNodes.length) {
                container.appendChild(cssPanel);
            }

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

        renderSelectStep() {
            const container = createElement('div');
            container.appendChild(createElement('h2', 'ele2gb-wizard-step-title', this.strings.selectPagesTitle || 'Select Pages'));

            if (!this.pages.length) {
                container.appendChild(createElement('p', null, this.strings.noPagesFound || 'No Elementor pages found.'));
                return container;
            }

            const toolbar = createElement('div', 'ele2gb-select-toolbar');

            const searchInput = document.createElement('input');
            searchInput.type = 'text';
            searchInput.className = 'regular-text';
            searchInput.placeholder = this.strings.searchPlaceholder || 'Search by title\u2026';
            searchInput.value = this.state.searchQuery || '';
            searchInput.addEventListener('input', () => {
                this.state.searchQuery = searchInput.value;
                this.state.tablePage = 1;
                this.render();
            });
            toolbar.appendChild(searchInput);

            const filterSelect = document.createElement('select');
            const filterOptions = [
                {value: 'all',           label: this.strings.filterAll         || 'All'},
                {value: 'eligible',      label: this.strings.filterEligible    || 'Eligible'},
                {value: 'not_converted', label: this.strings.filterUnconverted || 'Unconverted'},
                {value: 'converted',     label: this.strings.filterConverted   || 'Converted'},
                {value: 'failed',        label: this.strings.filterFailed      || 'Failed'},
            ];
            filterOptions.forEach(function (opt) {
                const option = document.createElement('option');
                option.value = opt.value;
                option.textContent = opt.label;
                filterSelect.appendChild(option);
            });
            filterSelect.value = this.state.filterStatus || 'all';
            filterSelect.addEventListener('change', () => {
                this.state.filterStatus = filterSelect.value;
                this.state.tablePage = 1;
                this.render();
            });
            toolbar.appendChild(filterSelect);

            const bulkActions = createElement('div', 'ele2gb-select-bulk-actions');
            const selectedCount = this.state.selectedPageIds.size;
            if (selectedCount > 0) {
                bulkActions.appendChild(createElement('span', 'ele2gb-selection-chip',
                    formatString(this.strings.selectionChip || '%1$d selected', selectedCount)));
            }
            const selectEligibleLink = document.createElement('a');
            selectEligibleLink.href = '#';
            selectEligibleLink.textContent = this.strings.selectAllEligible || 'Select all eligible';
            selectEligibleLink.addEventListener('click', (event) => {
                event.preventDefault();
                this.pages.forEach((page) => {
                    if (page.conversionStatus !== 'converted') {
                        this.state.selectedPageIds.add(page.id);
                    }
                });
                this.render();
            });
            bulkActions.appendChild(selectEligibleLink);

            const clearLink = document.createElement('a');
            clearLink.href = '#';
            clearLink.textContent = this.strings.clearSelection || 'Clear selection';
            clearLink.addEventListener('click', (event) => {
                event.preventDefault();
                this.state.selectedPageIds = new Set();
                this.render();
            });
            bulkActions.appendChild(clearLink);
            toolbar.appendChild(bulkActions);
            container.appendChild(toolbar);

            if (this.postTypes.length > 1) {
                const masterRow = createElement('div', 'ele2gb-master-select-row');
                const masterLabel = document.createElement('label');
                masterLabel.className = 'ele2gb-master-select-label';
                const masterCheckbox = document.createElement('input');
                masterCheckbox.type = 'checkbox';
                masterCheckbox.className = 'ele2gb-master-select-checkbox';
                const allPageIds = this.pages.map((p) => p.id);
                masterCheckbox.checked = allPageIds.length > 0 && allPageIds.every((id) => this.state.selectedPageIds.has(id));
                masterCheckbox.addEventListener('change', () => {
                    if (masterCheckbox.checked) {
                        this.pages.forEach((p) => { this.state.selectedPageIds.add(p.id); });
                    } else {
                        this.state.selectedPageIds = new Set();
                    }
                    this.render();
                });
                masterLabel.appendChild(masterCheckbox);
                masterLabel.appendChild(createElement('span', null, this.strings.selectAllAcrossTypes || 'Select all across all types'));
                masterRow.appendChild(masterLabel);
                container.appendChild(masterRow);

                const tabStrip = createElement('div', 'ele2gb-tab-strip');
                const tabCountTpl = this.strings.tabCountLabel || '%1$s (%2$d)';
                this.postTypes.forEach((pt) => {
                    const tab = document.createElement('button');
                    tab.type = 'button';
                    tab.className = 'ele2gb-tab' + (this.state.activeTab === pt.slug ? ' is-active' : '');
                    tab.textContent = formatString(tabCountTpl, pt.label, pt.count);
                    tab.addEventListener('click', () => {
                        if (this.state.activeTab === pt.slug) {
                            return;
                        }
                        this.state.activeTab = pt.slug;
                        this.state.tablePage = 1;
                        this.render();
                    });
                    tabStrip.appendChild(tab);
                });
                container.appendChild(tabStrip);
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
                badge.textContent = this.getPageStatusLabel(page.conversionStatus);
                conversionTd.appendChild(badge);
                tr.appendChild(conversionTd);

                const lastTd = document.createElement('td');
                lastTd.textContent = page.lastConverted || '—';
                tr.appendChild(lastTd);

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
                const skipWrapper = createElement('div', 'ele2gb-skip-converted-wrapper');
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
            if (label) {
                container.appendChild(createElement('h3', null, label));
            }

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
                const metaParts = [];
                if (template.postType) {
                    metaParts.push(template.postType);
                }
                if (template.sourceLabel) {
                    metaParts.push(template.sourceLabel);
                }
                if (template.id) {
                    metaParts.push('ID ' + template.id);
                }
                if (template.lastConverted) {
                    metaParts.push('Last updated ' + template.lastConverted);
                }
                if (metaParts.length) {
                    titleTd.appendChild(createElement('div', 'ele2gb-template-meta', metaParts.join(' · ')));
                }
                if (template.isLikelyGlobal) {
                    titleTd.appendChild(createElement('span', 'ele2gb-template-flag', this.strings.likelyGlobal || 'Likely global'));
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

            const headerSection = createElement('div', 'ele2gb-template-section ele2gb-template-section--header');
            headerSection.appendChild(createElement('p', 'ele2gb-template-section-heading', this.strings.headersLabel || 'Headers'));
            headerSection.appendChild(this.renderTemplatesGroup('header', ''));
            container.appendChild(headerSection);

            container.appendChild(createElement('hr', 'ele2gb-template-section-divider'));

            const footerSection = createElement('div', 'ele2gb-template-section ele2gb-template-section--footer');
            footerSection.appendChild(createElement('p', 'ele2gb-template-section-heading', this.strings.footersLabel || 'Footers'));
            footerSection.appendChild(this.renderTemplatesGroup('footer', ''));
            container.appendChild(footerSection);

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
            const totalPages = Math.max(1, Math.ceil(this.getFilteredPages().length / this.state.perPage));
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

        getFilteredPages() {
            let pages = this.pages.slice();
            const activeTab = this.state.activeTab || '';
            if (activeTab && this.postTypes.length > 1) {
                pages = pages.filter(function (page) {
                    return (page.postType || '') === activeTab;
                });
            }
            const q = (this.state.searchQuery || '').trim().toLowerCase();
            if (q) {
                pages = pages.filter(function (page) {
                    return page.title.toLowerCase().indexOf(q) !== -1;
                });
            }
            const f = this.state.filterStatus || 'all';
            if (f === 'eligible') {
                pages = pages.filter(function (page) { return page.conversionStatus !== 'converted'; });
            } else if (f === 'converted') {
                pages = pages.filter(function (page) { return page.conversionStatus === 'converted'; });
            } else if (f === 'failed') {
                pages = pages.filter(function (page) { return page.conversionStatus === 'error'; });
            } else if (f === 'not_converted') {
                pages = pages.filter(function (page) { return page.conversionStatus === 'not_converted'; });
            }
            return pages;
        }

        getVisiblePages() {
            const start = (this.state.tablePage - 1) * this.state.perPage;
            return this.getFilteredPages().slice(start, start + this.state.perPage);
        }

        getPageStatusLabel(conversionStatus) {
            if (conversionStatus === 'converted') {
                return this.strings.statusAlreadyConverted || 'Already converted';
            }
            if (conversionStatus === 'error') {
                return this.strings.statusFailedLastRun || 'Failed last run';
            }
            if (conversionStatus === 'not_converted') {
                return this.strings.statusReady || 'Ready';
            }
            const badgeInfo = STATUS_BADGES[conversionStatus];
            return badgeInfo ? (this.strings[badgeInfo.labelKey] || conversionStatus) : conversionStatus;
        }

        normalizeResultMessage(message) {
            if (!message) { return ''; }
            if (message.indexOf('conversion produced no Gutenberg content') !== -1) {
                return this.strings.errorNoOutput || 'No Gutenberg output was generated. The source may contain unsupported widgets or empty content.';
            }
            return message;
        }

        renderConflictStep() {
            const container = createElement('div');
            container.appendChild(createElement('h2', 'ele2gb-wizard-step-title', this.strings.conflictsTitle || 'Resolve Conflicts'));
            const count = this.getConflictCount();
            const summary = formatString(this.strings.conflictDetected || '%1$d selected pages already have a converted version.', count);
            container.appendChild(createElement('p', 'ele2gb-step-description', summary));

            const options = [
                {
                    key: 'overwrite',
                    label: this.strings.conflictOverwrite || 'Update existing pages in place (overwrite)'
                },
                {
                    key: 'duplicate',
                    label: this.strings.conflictDuplicate || 'Create duplicates with “(Converted)” suffix'
                },
            ];

            if (this.state.mode !== 'custom') {
                options.splice(1, 0, {key: 'skip', label: this.strings.conflictSkip || 'Skip those pages'});
            } else if (this.state.conflictPolicy === 'skip') {
                this.state.conflictPolicy = 'overwrite';
            }

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

        buildReviewStatTile(value, label) {
            const tile = createElement('div', 'ele2gb-review-stat');
            tile.appendChild(createElement('div', 'ele2gb-review-stat-value', String(value)));
            tile.appendChild(createElement('div', 'ele2gb-review-stat-label', label));
            return tile;
        }

        buildReviewSection(title, editStep, bodyBuilder) {
            const section = createElement('div', 'ele2gb-review-section');
            const header = createElement('div', 'ele2gb-review-section-header');
            header.appendChild(createElement('h3', 'ele2gb-review-section-title', title));
            if (editStep) {
                const edit = document.createElement('a');
                edit.href = '#';
                edit.className = 'ele2gb-review-section-edit';
                edit.textContent = this.strings.editSection || 'Edit';
                edit.addEventListener('click', (e) => {
                    e.preventDefault();
                    this.goToStep(editStep);
                });
                header.appendChild(edit);
            }
            section.appendChild(header);
            const body = createElement('div', 'ele2gb-review-section-body');
            bodyBuilder(body);
            section.appendChild(body);
            return section;
        }

        renderReviewStep() {
            const container = createElement('div');
            container.appendChild(createElement('h2', 'ele2gb-wizard-step-title', this.strings.reviewTitle || 'Review & Confirm'));
            container.appendChild(createElement('p', 'ele2gb-step-description',
                this.strings.reviewDesc || 'Double-check the plan below before starting. You can edit any section from here.'));

            const reviewWarnings = this.renderThemeWarnings();
            if (reviewWarnings) {
                container.appendChild(reviewWarnings);
            }

            const selectedCount = this.state.selectedPageIds.size;
            const convertedSelected = this.getSelectedPages().filter((page) => page.conversionStatus === 'converted').length;
            const convertCount = this.state.skipConverted ? Math.max(0, selectedCount - convertedSelected) : selectedCount;
            const skippedCount = selectedCount - convertCount;
            const headerCount = this.state.selectedHeaderIds.size;
            const footerCount = this.state.selectedFooterIds.size;

            const dashboard = createElement('div', 'ele2gb-review-dashboard');

            // Stat tiles row
            const stats = createElement('div', 'ele2gb-review-stats');
            stats.appendChild(this.buildReviewStatTile(convertCount, this.strings.reviewStatPages || 'Pages to convert'));
            if (this.state.mode === 'custom') {
                stats.appendChild(this.buildReviewStatTile(headerCount, this.strings.reviewStatHeaders || 'Headers'));
                stats.appendChild(this.buildReviewStatTile(footerCount, this.strings.reviewStatFooters || 'Footers'));
            }
            if (skippedCount > 0) {
                stats.appendChild(this.buildReviewStatTile(skippedCount, this.strings.reviewStatSkipped || 'To skip'));
            }
            dashboard.appendChild(stats);

            // Scope section
            dashboard.appendChild(this.buildReviewSection(
                this.strings.reviewSectionScope || 'Scope',
                'mode',
                (body) => {
                    const ul = document.createElement('ul');
                    const modeLabel = this.state.mode === 'auto'
                        ? (this.strings.modeAutoTitle || 'Convert all pages automatically')
                        : (this.strings.modeCustomTitle || 'Choose specific pages');
                    ul.appendChild(createElement('li', null, modeLabel));
                    ul.appendChild(createElement('li', null, formatString('%1$d pages selected, %2$d will convert, %3$d skipped', selectedCount, convertCount, skippedCount)));
                    body.appendChild(ul);
                }
            ));

            // Theme section
            dashboard.appendChild(this.buildReviewSection(
                this.strings.reviewSectionTheme || 'Theme',
                'theme',
                (body) => {
                    const selectedTheme = this.getSelectedTheme();
                    const ul = document.createElement('ul');
                    if (this.willChangeTheme()) {
                        const themeName = (selectedTheme && selectedTheme.name) || (this.state.selectedThemeSlug || '');
                        ul.appendChild(createElement('li', null, 'Switching to: ' + themeName));
                        if (this.shouldCopyCss()) {
                            ul.appendChild(createElement('li', null, this.strings.copyAdditionalCss || 'Copy Additional CSS from the current theme'));
                        }
                    } else if (this.getCurrentThemeName()) {
                        ul.appendChild(createElement('li', null, (this.strings.themeKeepCurrent || 'Keep current theme') + ': ' + this.getCurrentThemeName()));
                    }
                    body.appendChild(ul);
                }
            ));

            // Templates section (custom mode)
            if (this.state.mode === 'custom' && (headerCount || footerCount)) {
                dashboard.appendChild(this.buildReviewSection(
                    this.strings.reviewSectionTemplates || 'Templates',
                    'templates',
                    (body) => {
                        const ul = document.createElement('ul');
                        ul.appendChild(createElement('li', null, formatString(this.strings.headerFooterSummary || '%1$d headers and %2$d footers selected for conversion.', headerCount, footerCount)));
                        const defaultHeader = headerCount ? this.getTemplateById(this.state.defaultHeaderId) : null;
                        const defaultFooter = footerCount ? this.getTemplateById(this.state.defaultFooterId) : null;
                        const headerTitle = defaultHeader ? defaultHeader.title : '—';
                        const footerTitle = defaultFooter ? defaultFooter.title : '—';
                        ul.appendChild(createElement('li', null, formatString(this.strings.headerFooterDefaults || 'Default header: %1$s — Default footer: %2$s', headerTitle, footerTitle)));
                        body.appendChild(ul);
                    }
                ));
            }

            // Conflicts section
            if (this.shouldShowConflictStep()) {
                dashboard.appendChild(this.buildReviewSection(
                    this.strings.reviewSectionConflicts || 'Conflicts',
                    'conflicts',
                    (body) => {
                        let policyLabel = '';
                        switch (this.state.conflictPolicy) {
                            case 'overwrite':
                                policyLabel = this.strings.conflictOverwrite || 'Update existing pages in place (overwrite)';
                                break;
                            case 'duplicate':
                                policyLabel = this.strings.conflictDuplicate || 'Create duplicates with \u201C(Converted)\u201D suffix';
                                break;
                            default:
                                policyLabel = this.strings.conflictSkip || 'Skip those pages';
                        }
                        const ul = document.createElement('ul');
                        ul.appendChild(createElement('li', null, policyLabel));
                        body.appendChild(ul);
                    }
                ));
            }

            container.appendChild(dashboard);

            // Safety note
            const safety = createElement('div', 'ele2gb-safety-note');
            safety.appendChild(createElement('span', 'ele2gb-safety-note-icon', '\u{1F6E1}'));
            safety.appendChild(createElement('span', null, this.strings.safetyNote || 'Recommended to run on a staging environment if your site is live. Conversion runs in the background — you can safely close this page.'));
            container.appendChild(safety);

            const buttons = createElement('div', 'ele2gb-wizard-buttons');
            const backBtn = createButton(this.strings.back || 'Back', 'button button-secondary');
            backBtn.addEventListener('click', () => this.goToPrevious());
            const buttonsLeft = createElement('div', 'ele2gb-wizard-buttons-left');
            buttonsLeft.appendChild(backBtn);
            buttons.appendChild(buttonsLeft);

            const buttonsRight = createElement('div', 'ele2gb-wizard-buttons-right');
            const startBtn = createButton(this.strings.startConversion || 'Start Conversion', 'button button-primary button-large');
            startBtn.disabled = this.state.isSubmitting;
            startBtn.addEventListener('click', () => {
                if (!this.state.isSubmitting) {
                    this.startConversion();
                }
            });
            buttonsRight.appendChild(startBtn);
            buttons.appendChild(buttonsRight);
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

            const progressWarnings = this.renderThemeWarnings();
            if (progressWarnings) {
                container.appendChild(progressWarnings);
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

            const makeTile = function (value, label, modifier) {
                const tile = createElement('div', 'ele2gb-stat-tile' + (modifier ? ' ele2gb-stat-tile--' + modifier : ''));
                tile.appendChild(createElement('div', 'ele2gb-stat-tile-value', String(value)));
                tile.appendChild(createElement('div', 'ele2gb-stat-tile-label', label));
                return tile;
            };

            summary.appendChild(makeTile(successCount, this.strings.converted || 'Converted', successCount > 0 ? 'success' : 'muted'));
            summary.appendChild(makeTile(skippedCount, this.strings.skipped || 'Skipped', 'muted'));
            summary.appendChild(makeTile(errorCount, this.strings.errors || 'Errors', errorCount > 0 ? 'error' : 'muted'));
            summary.appendChild(makeTile(formatDuration(job.duration), this.strings.duration || 'Duration', 'muted'));
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
                const aiPages = this.getAiImprovePages();
                if (aiPages.length > 0 && this.config.aiImproveNonce && this.config.aiConfigured) {
                    const count = aiPages.length;
                    const improveAllBtn = this.buildActionPill({
                        variant: 'ai-primary',
                        label: formatString(this.strings.improveSuccessful || 'Improve %1$d items with AI', count),
                        iconPath: [
                            'M12 2v4', 'M12 18v4', 'M4.93 4.93l2.83 2.83', 'M16.24 16.24l2.83 2.83',
                            'M2 12h4', 'M18 12h4', 'M4.93 19.07l2.83-2.83', 'M16.24 7.76l2.83-2.83'
                        ],
                        onClick: () => this.initAiImprove()
                    });
                    actions.appendChild(improveAllBtn);
                }
                actions.appendChild(this.buildActionPill({
                    variant: 'view-secondary',
                    href: 'edit.php?post_type=page',
                    label: this.strings.viewPages || 'View converted pages',
                    iconPath: [
                        'M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z',
                        'M14 2v6h6',
                        'M16 13H8', 'M16 17H8', 'M10 9H8'
                    ]
                }));
                actions.appendChild(this.buildActionPill({
                    variant: 'neutral',
                    label: this.strings.startNew || 'Start new conversion',
                    iconPath: ['M12 5v14', 'M5 12h14'],
                    onClick: () => this.resetWizard()
                }));
            }
            container.appendChild(actions);

            return container;
        }

        buildActionPill(opts) {
            const svgNS = 'http://www.w3.org/2000/svg';
            const el = opts.href
                ? document.createElement('a')
                : document.createElement('button');
            el.className = 'ele2gb-action-pill ele2gb-action-pill--' + (opts.variant || 'default');
            if (opts.href) {
                el.href = opts.href;
                if (opts.external) {
                    el.target = '_blank';
                    el.rel = 'noopener noreferrer';
                }
            } else {
                el.type = 'button';
            }
            if (opts.title) { el.title = opts.title; }
            if (opts.onClick) {
                el.addEventListener('click', opts.onClick);
            }
            if (opts.iconPath) {
                const svg = document.createElementNS(svgNS, 'svg');
                svg.setAttribute('class', 'ele2gb-action-pill-icon');
                svg.setAttribute('viewBox', '0 0 24 24');
                svg.setAttribute('fill', 'none');
                svg.setAttribute('stroke', 'currentColor');
                svg.setAttribute('stroke-width', '2');
                svg.setAttribute('stroke-linecap', 'round');
                svg.setAttribute('stroke-linejoin', 'round');
                svg.setAttribute('aria-hidden', 'true');
                if (Array.isArray(opts.iconPath)) {
                    opts.iconPath.forEach(function (d) {
                        const p = document.createElementNS(svgNS, 'path');
                        p.setAttribute('d', d);
                        svg.appendChild(p);
                    });
                } else {
                    const p = document.createElementNS(svgNS, 'path');
                    p.setAttribute('d', opts.iconPath);
                    svg.appendChild(p);
                }
                el.appendChild(svg);
            }
            if (opts.label) {
                el.appendChild(createElement('span', 'ele2gb-action-pill-label', opts.label));
            }
            return el;
        }

        buildResultsTable(results) {
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
            results.forEach((result) => {
                const tr = document.createElement('tr');

                const titleTd = document.createElement('td');
                const titleWrapper = createElement('div', null, result.title);
                titleTd.appendChild(titleWrapper);
                const metaParts = [];
                const typeLabel = (result.type === 'page' && result.postTypeLabel)
                    ? String(result.postTypeLabel)
                    : this.formatResultType(result.type);
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
                const resultConfig = RESULT_STATUS[result.status] || {
                    badge: 'not_converted',
                    labelKey: 'statusUnknown'
                };
                const badgeInfo = STATUS_BADGES[resultConfig.badge] || STATUS_BADGES.not_converted;
                const badge = createElement('span', 'ele2gb-status-badge ' + badgeInfo.className, this.strings[resultConfig.labelKey] || result.status);
                statusTd.appendChild(badge);
                const displayMessage = this.normalizeResultMessage(result.message);
                if (displayMessage) {
                    statusTd.appendChild(createElement('div', null, displayMessage));
                }
                tr.appendChild(statusTd);

                const durationTd = document.createElement('td');
                durationTd.className = 'duration';
                durationTd.textContent = formatDuration(result.duration);
                tr.appendChild(durationTd);

                const actionsTd = document.createElement('td');
                actionsTd.className = 'actions';
                const actionGroup = createElement('div', 'ele2gb-action-group');

                if (result.viewUrl) {
                    actionGroup.appendChild(this.buildActionPill({
                        variant: 'view',
                        href: result.viewUrl,
                        external: true,
                        label: this.strings.viewConverted || 'View',
                        title: this.strings.viewConvertedTooltip || 'View converted page',
                        iconPath: [
                            'M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z',
                            'M12 15a3 3 0 100-6 3 3 0 000 6z'
                        ]
                    }));
                }
                if (
                    (result.type === 'page' || result.type === 'header' || result.type === 'footer') &&
                    result.status === 'success' &&
                    Number(result.convertedPostId || 0) > 0 &&
                    this.config.aiImproveBaseUrl &&
                    this.config.aiConfigured
                ) {
                    const improveUrl = new URL(this.config.aiImproveBaseUrl, window.location.origin);
                    improveUrl.searchParams.set('target_id', String(result.convertedPostId));
                    improveUrl.searchParams.set('source_id', String(result.id));
                    actionGroup.appendChild(this.buildActionPill({
                        variant: 'ai',
                        href: improveUrl.toString(),
                        label: this.strings.improveWithAi || 'Improve with AI',
                        title: this.strings.improveWithAiTooltip || 'Improve this page with AI',
                        iconPath: [
                            'M12 2v4', 'M12 18v4', 'M4.93 4.93l2.83 2.83', 'M16.24 16.24l2.83 2.83',
                            'M2 12h4', 'M18 12h4', 'M4.93 19.07l2.83-2.83', 'M16.24 7.76l2.83-2.83'
                        ]
                    }));
                }
                if (result.status === 'error' && result.type === 'page') {
                    actionGroup.appendChild(this.buildActionPill({
                        variant: 'retry',
                        label: this.strings.retry || 'Retry',
                        title: this.strings.retryTooltip || 'Retry this conversion',
                        iconPath: [
                            'M1 4v6h6',
                            'M3.51 15a9 9 0 102.13-9.36L1 10'
                        ],
                        onClick: (event) => {
                            event.preventDefault();
                            this.retryConversionForPage(result.id);
                        }
                    }));
                }
                actionsTd.appendChild(actionGroup);
                tr.appendChild(actionsTd);

                tbody.appendChild(tr);
            });
            table.appendChild(tbody);
            wrapper.appendChild(table);
            return wrapper;
        }

        renderResultsTable() {
            if (!this.state.job || !Array.isArray(this.state.job.results) || !this.state.job.results.length) {
                return null;
            }
            const results = this.state.job.results;
            const errors    = results.filter(function (r) { return r.status === 'error' || r.status === 'partial'; });
            const successes = results.filter(function (r) { return r.status === 'success' || r.status === 'skipped'; });
            const container = createElement('div', 'ele2gb-results-sections');
            if (errors.length) {
                container.appendChild(createElement('h3', 'ele2gb-results-section-title ele2gb-results-section-title--error', this.strings.resultsNeedsAttention || 'Needs attention'));
                container.appendChild(this.buildResultsTable(errors));
            }
            if (successes.length) {
                container.appendChild(createElement('h3', 'ele2gb-results-section-title ele2gb-results-section-title--success', this.strings.resultsCompleted || 'Completed successfully'));
                container.appendChild(this.buildResultsTable(successes));
            }
            return container;
        }

        // ── AI Improve step ──────────────────────────────────────────────────

        getAiImprovePages() {
            if (!this.state.job || !Array.isArray(this.state.job.results)) {
                return [];
            }
            return this.state.job.results.filter(
                (r) => r.status === 'success' && Number(r.convertedPostId || 0) > 0
            );
        }

        initAiImprove() {
            const pages = this.getAiImprovePages().map((r) => ({
                sourceId: Number(r.id),
                targetId: Number(r.convertedPostId),
                title: r.title || '',
                type: r.type || 'page',
                status: 'pending',
                error: '',
            }));
            this.state.aiImprove = {pages, currentIndex: 0, started: false, finished: false};
            this.state.currentStep = 'ai_improve';
            this.render();
        }

        startAiImprove() {
            const ai = this.state.aiImprove;
            if (!ai || ai.started) {
                return;
            }
            ai.started = true;
            this.processAiImprovePage(0);
        }

        processAiImprovePage(index) {
            const ai = this.state.aiImprove;
            if (!ai || index >= ai.pages.length) {
                if (ai) {
                    ai.finished = true;
                }
                this.render();
                return;
            }

            ai.currentIndex = index;
            ai.pages[index].status = 'processing';
            ai.pages[index].error = '';
            this.render();

            const page = ai.pages[index];
            this.showAiOverlay(page.title, index + 1, ai.pages.length, 'analyzing');

            const formData = new FormData();
            formData.append('action', 'ele2gb_ai_improve_single');
            formData.append('nonce', this.config.aiImproveNonce);
            formData.append('source_id', String(page.sourceId));
            formData.append('target_id', String(page.targetId));

            fetch(this.config.ajaxUrl, {method: 'POST', credentials: 'same-origin', body: formData})
                .then((response) => response.json())
                .then((data) => {
                    this.hideAiOverlay();
                    if (data.success) {
                        ai.pages[index].status = 'done';
                        this.render();
                        this.advanceAiImprove(index);
                    } else {
                        ai.pages[index].status = 'failed';
                        ai.pages[index].error = (data.data && data.data.message)
                            ? String(data.data.message)
                            : (this.strings.aiImproveError || 'An unexpected error occurred.');
                        this.render();
                    }
                })
                .catch((err) => {
                    this.hideAiOverlay();
                    ai.pages[index].status = 'failed';
                    ai.pages[index].error = err.message || (this.strings.aiImproveError || 'An unexpected error occurred.');
                    this.render();
                });
        }

        showAiOverlay(pageTitle, current, total, stage) {
            this.hideAiOverlay();

            // Overlay — inline styles guarantee visibility regardless of CSS load order
            const overlay = document.createElement('div');
            overlay.id = 'ele2gb-bulk-ai-overlay';
            overlay.style.cssText = [
                'position:fixed',
                'top:0', 'left:0', 'right:0', 'bottom:0',
                'z-index:100000',
                'display:flex',
                'align-items:center',
                'justify-content:center',
                'background:rgba(0,0,0,0.5)',
            ].join(';');

            // Card
            const card = document.createElement('div');
            card.style.cssText = [
                'display:flex',
                'flex-direction:column',
                'align-items:center',
                'gap:16px',
                'padding:40px 48px',
                'background:#fff',
                'border-radius:12px',
                'box-shadow:0 20px 60px rgba(0,0,0,0.25),0 4px 16px rgba(0,0,0,0.1)',
                'text-align:center',
                'min-width:300px',
                'max-width:420px',
            ].join(';');

            // Spinner SVG — uses CSS class for animation only
            const svgNS = 'http://www.w3.org/2000/svg';
            const svg = document.createElementNS(svgNS, 'svg');
            svg.setAttribute('class', 'ele2gb-bulk-ai-overlay-spinner');
            svg.setAttribute('viewBox', '0 0 44 44');
            svg.setAttribute('aria-hidden', 'true');
            svg.style.cssText = 'width:64px;height:64px;flex-shrink:0;';

            const track = document.createElementNS(svgNS, 'circle');
            track.setAttribute('class', 'track');
            track.setAttribute('cx', '22'); track.setAttribute('cy', '22');
            track.setAttribute('r', '20');  track.setAttribute('fill', 'none');
            track.setAttribute('stroke', '#2271b1'); track.setAttribute('stroke-width', '3');
            track.style.opacity = '0.12';

            const arc = document.createElementNS(svgNS, 'circle');
            arc.setAttribute('class', 'arc');
            arc.setAttribute('cx', '22'); arc.setAttribute('cy', '22');
            arc.setAttribute('r', '20');  arc.setAttribute('fill', 'none');
            arc.setAttribute('stroke', '#2271b1'); arc.setAttribute('stroke-width', '3');

            svg.appendChild(track);
            svg.appendChild(arc);
            card.appendChild(svg);

            // Title
            const title = document.createElement('strong');
            title.textContent = this.strings.aiLoaderTitle || 'Improving with AI\u2026';
            title.style.cssText = 'display:block;font-size:16px;font-weight:700;color:#1d2327;';
            card.appendChild(title);

            // Stage dots
            const stageStrip = document.createElement('div');
            stageStrip.style.cssText = 'display:flex;align-items:center;gap:8px;margin-top:6px;';
            const stageKeys = ['analyzing', 'generating', 'saving'];
            const stageLabels = {
                analyzing:  this.strings.aiStageAnalyzing  || 'Analyzing\u2026',
                generating: this.strings.aiStageGenerating || 'Generating\u2026',
                saving:     this.strings.aiStageSaving     || 'Saving\u2026',
            };
            const dots = {};
            stageKeys.forEach(function (k, i) {
                if (i > 0) {
                    const sep = document.createElement('span');
                    sep.style.cssText = 'width:18px;height:1px;background:#dcdcde;';
                    stageStrip.appendChild(sep);
                }
                const dot = document.createElement('span');
                dot.style.cssText = 'width:8px;height:8px;border-radius:50%;background:#dcdcde;transition:background 200ms ease;';
                dots[k] = dot;
                stageStrip.appendChild(dot);
            });
            card.appendChild(stageStrip);

            // Description message with stage cycling
            const msg = document.createElement('span');
            const currentStage = stage || 'analyzing';
            msg.textContent = stageLabels[currentStage];
            msg.style.cssText = 'display:block;margin-top:2px;font-size:13px;color:#2271b1;font-weight:600;line-height:1.5;';
            card.appendChild(msg);
            dots[currentStage].style.background = '#2271b1';

            const timerIds = [];
            timerIds.push(window.setTimeout(function () {
                msg.textContent = stageLabels.generating;
                dots.generating.style.background = '#2271b1';
            }, 6000));
            timerIds.push(window.setTimeout(function () {
                msg.textContent = stageLabels.saving;
                dots.saving.style.background = '#2271b1';
            }, 22000));
            overlay.dataset.timerIds = JSON.stringify(timerIds);

            // Separator + page name + counter
            const sep = document.createElement('div');
            sep.style.cssText = 'width:100%;height:1px;background:#f0f0f1;margin-top:4px;';
            card.appendChild(sep);

            const sub = document.createElement('span');
            sub.textContent = pageTitle;
            sub.style.cssText = 'display:block;font-size:13px;color:#50575e;max-width:320px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;';
            card.appendChild(sub);

            const counter = document.createElement('span');
            counter.textContent = current + ' / ' + total;
            counter.style.cssText = 'display:inline-block;padding:3px 12px;border-radius:999px;background:#f0f6fc;border:1px solid #bcd7f0;color:#135e96;font-size:12px;font-weight:600;';
            card.appendChild(counter);

            overlay.appendChild(card);
            document.body.appendChild(overlay);
        }

        hideAiOverlay() {
            const existing = document.getElementById('ele2gb-bulk-ai-overlay');
            if (existing) {
                try {
                    const ids = JSON.parse(existing.dataset.timerIds || '[]');
                    ids.forEach(function (id) { window.clearTimeout(id); });
                } catch (e) {}
                existing.remove();
            }
        }

        advanceAiImprove(index) {
            const ai = this.state.aiImprove;
            const next = index + 1;
            if (!ai || next >= ai.pages.length) {
                if (ai) {
                    ai.finished = true;
                    this.render();
                }
                return;
            }
            this.processAiImprovePage(next);
        }

        skipAiImprovePage(index) {
            const ai = this.state.aiImprove;
            if (!ai) {
                return;
            }
            ai.pages[index].status = 'skipped';
            this.render();
            this.advanceAiImprove(index);
        }

        retryAiImprovePage(index) {
            this.processAiImprovePage(index);
        }

        renderAiImproveStep() {
            const ai = this.state.aiImprove;
            const container = createElement('div', 'ele2gb-ai-improve-step');

            container.appendChild(createElement('h2', 'ele2gb-wizard-step-title',
                this.strings.aiImproveTitle || 'AI Improvement'));

            // ── Pre-start panel ──────────────────────────────────────────────
            if (!ai || !ai.started) {
                const apiConfigured = !!this.config.aiConfigured;
                const itemCount = ai ? ai.pages.length : 0;
                const allReady = apiConfigured && itemCount > 0;

                const panel = createElement('div', 'ele2gb-ai-readiness-panel');
                const panelHeader = createElement('div', 'ele2gb-ai-readiness-header');
                panelHeader.appendChild(createElement('h3', 'ele2gb-ai-readiness-title',
                    this.strings.aiReadinessTitle || 'Pre-flight checklist'));
                if (allReady) {
                    panelHeader.appendChild(createElement('span', 'ele2gb-ai-readiness-all-ready',
                        this.strings.aiReadinessAllReady || '\u2713 Ready to start'));
                }
                panel.appendChild(panelHeader);

                const makeRow = function (ok, label) {
                    const row = createElement('div', 'ele2gb-ai-readiness-row');
                    const icon = createElement('div', 'ele2gb-ai-readiness-icon ele2gb-ai-readiness-icon--' + (ok ? 'ok' : 'error'), ok ? '\u2713' : '\u00D7');
                    row.appendChild(icon);
                    row.appendChild(createElement('span', 'ele2gb-ai-readiness-status' + (ok ? '' : ' is-invalid'), label));
                    return row;
                };
                const makeInfoRow = function (label) {
                    const row = createElement('div', 'ele2gb-ai-readiness-row');
                    const icon = createElement('div', 'ele2gb-ai-readiness-icon ele2gb-ai-readiness-icon--info', 'i');
                    row.appendChild(icon);
                    row.appendChild(createElement('span', null, label));
                    return row;
                };

                panel.appendChild(makeRow(apiConfigured,
                    apiConfigured
                        ? (this.strings.aiReadinessApiValid || 'API key configured')
                        : (this.strings.aiReadinessApiInvalid || 'API key not configured')
                ));
                panel.appendChild(makeRow(itemCount > 0, itemCount + ' item' + (itemCount !== 1 ? 's' : '') + ' ready for improvement'));
                panel.appendChild(makeInfoRow(formatString(this.strings.aiReadinessCredits || 'Estimated: ~%1$d API call(s), ~1–2 minutes per item', itemCount)));

                container.appendChild(panel);

                if (!apiConfigured) {
                    const apiAlert = createElement('div', 'ele2gb-alert ele2gb-alert-error');
                    apiAlert.appendChild(document.createTextNode(this.strings.aiReadinessApiMissing || 'AI features require a valid API key. '));
                    const settingsLink = document.createElement('a');
                    settingsLink.href = 'admin.php?page=gutenberg-settings';
                    settingsLink.textContent = this.strings.goToSettings || 'Go to Settings \u2192';
                    apiAlert.appendChild(settingsLink);
                    container.appendChild(apiAlert);
                }

                const warning = createElement('div', 'ele2gb-ai-warning-notice');
                const icon = createElement('span', 'ele2gb-ai-warning-icon', '\u26A0');
                const text = createElement('div', 'ele2gb-ai-warning-text');
                text.appendChild(createElement('strong', null,
                    this.strings.aiImproveWarningTitle || 'AI credits will be used'));
                text.appendChild(createElement('p', null,
                    this.strings.aiImproveWarning || 'This will use AI credits once per selected item. Make sure your API key has sufficient credits before starting.'));
                warning.appendChild(icon);
                warning.appendChild(text);
                container.appendChild(warning);
            }

            if (!ai || !ai.pages.length) {
                container.appendChild(createElement('p', 'ele2gb-step-description',
                    this.strings.aiImproveNone || 'No successfully converted items found in this session.'));
                return container;
            }

            // ── Progress header (shown once running) ─────────────────────────
            if (ai.started) {
                const done    = ai.pages.filter((p) => p.status === 'done').length;
                const failed  = ai.pages.filter((p) => p.status === 'failed').length;
                const skipped = ai.pages.filter((p) => p.status === 'skipped').length;
                const settled = done + skipped; // failed rows are paused, not settled
                const total   = ai.pages.length;
                const pct     = Math.round((settled / total) * 100);
                const waiting = failed > 0 && !ai.finished;

                const progressHeader = createElement('div', 'ele2gb-ai-progress-header');

                // Bar + label row
                const barRow = createElement('div', 'ele2gb-ai-bar-row');
                const bar    = createElement('div', 'ele2gb-progress-bar ele2gb-progress-bar-large');
                const fill   = document.createElement('span');
                fill.style.width = pct + '%';
                bar.appendChild(fill);
                barRow.appendChild(bar);
                const label = createElement('span', 'ele2gb-ai-bar-label',
                    settled + ' / ' + total);
                barRow.appendChild(label);
                progressHeader.appendChild(barRow);

                // Stat chips
                const chips = createElement('div', 'ele2gb-ai-chips');
                const chipData = [
                    {count: done,    label: this.strings.aiStatusDone    || 'Done',    cls: 'chip-done'},
                    {count: failed,  label: this.strings.aiStatusFailed  || 'Failed',  cls: 'chip-failed'},
                    {count: skipped, label: this.strings.aiStatusSkipped || 'Skipped', cls: 'chip-skipped'},
                    {count: total - settled - failed, label: this.strings.aiStatusPending || 'Pending', cls: 'chip-pending'},
                ];
                chipData.forEach(({count, label: chipLabel, cls}) => {
                    const chip = createElement('span', 'ele2gb-ai-chip ' + cls);
                    chip.appendChild(createElement('strong', null, String(count)));
                    chip.appendChild(document.createTextNode(' ' + chipLabel));
                    chips.appendChild(chip);
                });
                progressHeader.appendChild(chips);

                // Paused notice when waiting on a failed row
                if (waiting) {
                    progressHeader.appendChild(createElement('p', 'ele2gb-ai-paused-notice',
                        this.strings.aiImprovePaused || 'Paused — review the failed item below, then choose Skip or Retry to continue.'));
                }

                container.appendChild(progressHeader);
            }

            // ── Items table ──────────────────────────────────────────────────
            const tableWrapper = createElement('div', 'ele2gb-results-table ele2gb-table-wrapper');
            const table = createElement('table', 'ele2gb-wizard-table ele2gb-ai-table');
            const thead = document.createElement('thead');
            const headRow = document.createElement('tr');
            [
                this.strings.tableTitle   || 'Title',
                this.strings.aiImproveType || 'Type',
                this.strings.tableStatus  || 'Status',
                this.strings.tableActions || 'Actions',
            ].forEach((heading) => {
                const th = document.createElement('th');
                th.textContent = heading;
                headRow.appendChild(th);
            });
            thead.appendChild(headRow);
            table.appendChild(thead);

            const tbody = document.createElement('tbody');
            ai.pages.forEach((page, i) => {
                const tr = document.createElement('tr');
                tr.className = 'ele2gb-ai-row ele2gb-ai-row--' + page.status;

                // Title — strike-through when skipped
                const titleTd = document.createElement('td');
                titleTd.className = 'ele2gb-ai-title-cell';
                titleTd.textContent = page.title;
                tr.appendChild(titleTd);

                // Type
                const typeTd = document.createElement('td');
                typeTd.className = 'ele2gb-ai-type-cell';
                typeTd.textContent = page.type
                    ? page.type.charAt(0).toUpperCase() + page.type.slice(1)
                    : '';
                tr.appendChild(typeTd);

                // Status
                const statusTd = document.createElement('td');
                statusTd.className = 'status ele2gb-ai-status-cell';

                if (page.status === 'pending') {
                    statusTd.appendChild(this.makeAiStatusBadge('pending',
                        this.strings.aiStatusPending || 'Pending'));

                } else if (page.status === 'processing') {
                    const wrapper = createElement('span', 'ele2gb-ai-processing');
                    wrapper.appendChild(this.makeRowSpinner());
                    wrapper.appendChild(createElement('span', null,
                        this.strings.aiStatusProcessing || 'Processing…'));
                    statusTd.appendChild(wrapper);

                } else if (page.status === 'done') {
                    statusTd.appendChild(this.makeAiStatusBadge('done',
                        this.strings.aiStatusDone || 'Done'));

                } else if (page.status === 'failed') {
                    statusTd.appendChild(this.makeAiStatusBadge('failed',
                        this.strings.aiStatusFailed || 'Failed'));
                    if (page.error) {
                        statusTd.appendChild(createElement('p', 'ele2gb-ai-error-msg', page.error));
                    }

                } else if (page.status === 'skipped') {
                    statusTd.appendChild(this.makeAiStatusBadge('skipped',
                        this.strings.aiStatusSkipped || 'Skipped'));
                }
                tr.appendChild(statusTd);

                // Actions — Skip / Retry only on failed rows
                const actionsTd = document.createElement('td');
                actionsTd.className = 'actions ele2gb-ai-actions-cell';
                if (page.status === 'failed') {
                    const skipBtn = createButton(this.strings.skip || 'Skip', 'button button-secondary button-small');
                    skipBtn.addEventListener('click', () => this.skipAiImprovePage(i));
                    actionsTd.appendChild(skipBtn);
                    const retryBtn = createButton(this.strings.retry || 'Retry', 'button button-primary button-small');
                    retryBtn.addEventListener('click', () => this.retryAiImprovePage(i));
                    actionsTd.appendChild(retryBtn);
                }
                tr.appendChild(actionsTd);

                tbody.appendChild(tr);
            });
            table.appendChild(tbody);
            tableWrapper.appendChild(table);
            container.appendChild(tableWrapper);

            // ── Completion summary ───────────────────────────────────────────
            if (ai.finished) {
                const done    = ai.pages.filter((p) => p.status === 'done').length;
                const failed  = ai.pages.filter((p) => p.status === 'failed').length;
                const skipped = ai.pages.filter((p) => p.status === 'skipped').length;
                const allGood = failed === 0;
                const summary = createElement('div',
                    'ele2gb-ai-completion ' + (allGood ? 'ele2gb-ai-completion--success' : 'ele2gb-ai-completion--partial'));
                summary.appendChild(createElement('strong', null,
                    allGood
                        ? (this.strings.aiImproveFinishedOk  || 'All items improved successfully.')
                        : (this.strings.aiImproveFinishedErr || 'Finished with some failures.')));
                const detail = createElement('span', 'ele2gb-ai-completion-detail',
                    ' ' + done + ' done · ' + failed + ' failed · ' + skipped + ' skipped');
                summary.appendChild(detail);
                container.appendChild(summary);
            }

            // ── Bottom actions ───────────────────────────────────────────────
            const actions = createElement('div', 'ele2gb-results-actions');
            if (!ai.started) {
                const startBtn = createButton(
                    this.strings.aiImproveStart || 'Start AI Improvement',
                    'button button-primary'
                );
                startBtn.disabled = !this.config.aiConfigured;
                startBtn.addEventListener('click', () => this.startAiImprove());
                actions.appendChild(startBtn);
                const backBtn = createButton(this.strings.back || 'Back', 'button button-secondary');
                backBtn.addEventListener('click', () => this.goToStep('progress'));
                actions.appendChild(backBtn);
            } else if (ai.finished) {
                const newConvBtn = createButton(
                    this.strings.startNew || 'Start new conversion',
                    'button button-primary'
                );
                newConvBtn.addEventListener('click', () => this.resetWizard());
                actions.appendChild(newConvBtn);
            }
            container.appendChild(actions);

            return container;
        }

        makeAiStatusBadge(status, label) {
            const map = {
                pending:    'ele2gb-status-not_converted',
                processing: 'ele2gb-status-not_converted',
                done:       'ele2gb-status-converted',
                failed:     'ele2gb-status-error',
                skipped:    'ele2gb-status-skipped',
            };
            return createElement('span',
                'ele2gb-status-badge ' + (map[status] || ''), label);
        }

        makeRowSpinner() {
            const svgNS = 'http://www.w3.org/2000/svg';
            const svg   = document.createElementNS(svgNS, 'svg');
            svg.setAttribute('class', 'ele2gb-row-spinner');
            svg.setAttribute('viewBox', '0 0 24 24');
            svg.setAttribute('aria-hidden', 'true');

            // Faint background track
            const track = document.createElementNS(svgNS, 'circle');
            track.setAttribute('class', 'track');
            track.setAttribute('cx', '12');
            track.setAttribute('cy', '12');
            track.setAttribute('r', '9');
            track.setAttribute('fill', 'none');
            track.setAttribute('stroke', 'currentColor');
            track.setAttribute('stroke-width', '2.5');
            svg.appendChild(track);

            // Animated arc
            const arc = document.createElementNS(svgNS, 'circle');
            arc.setAttribute('class', 'arc');
            arc.setAttribute('cx', '12');
            arc.setAttribute('cy', '12');
            arc.setAttribute('r', '9');
            arc.setAttribute('fill', 'none');
            arc.setAttribute('stroke', 'currentColor');
            arc.setAttribute('stroke-width', '2.5');
            svg.appendChild(arc);

            return svg;
        }

        // ── End AI Improve step ──────────────────────────────────────────────

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
                case 'theme':
                    stepContent = this.renderThemeStep();
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
                case 'ai_improve':
                    stepContent = this.renderAiImproveStep();
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
