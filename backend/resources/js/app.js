import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.data('pmsDocuments', () => ({
    file: null,
    documentId: null,
    analysis: null,
    currentFilename: '',
    history: [],
    historyPage: 1,
    historyPrevPage: null,
    historyNextPage: null,
    historyError: '',
    isLoadingHistory: false,
    tickets: [],
    ticketsError: '',
    isLoadingTickets: false,
    exampleFormat: null,
    examplePayload: null,
    exampleError: '',
    exampleFetched: false,
    isLoadingExample: false,
    ticketError: '',
    ticketResult: null,
    isCreatingTicket: false,
    isTicketModalOpen: false,
    ticketDraft: '',
    errorMessage: '',
    isUploading: false,
    isAnalyzing: false,
    isDragging: false,
    fieldDefinitions: [
        { key: 'check_in_date', label: 'Check-in date' },
        { key: 'checkout_date', label: 'Check-out date' },
        { key: 'first_name', label: 'First name' },
        { key: 'last_name', label: 'Last name' },
        { key: 'reservation_id', label: 'Reservation ID' },
        { key: 'mobile_phone', label: 'Mobile phone' },
        { key: 'email', label: 'Email' },
        { key: 'reservation_status', label: 'Reservation status' },
    ],

    get fileName() {
        return this.file?.name || this.currentFilename || '';
    },

    get analyzeButtonLabel() {
        if (this.isUploading) {
            return 'Uploading...';
        }
        if (this.isAnalyzing) {
            return 'Analyzing...';
        }
        return 'Analyze';
    },

    get dropZoneClasses() {
        return this.isDragging
            ? 'border-primary bg-primary-soft shadow-sm border-primary/80 bg-primary-soft/70'
            : 'border-border bg-card hover:border-primary hover:bg-primary-soft hover:shadow-sm hover:border-primary/80 hover:bg-primary-soft/70';
    },

    get fieldRows() {
        if (!this.analysis) {
            return [];
        }

        return this.fieldDefinitions.map((field) => {
            if (field.key === 'reservation_status') {
                const available = this.analysis.fields.reservation_status.available;
                const values = this.analysis.fields.reservation_status.values;
                const sourceLabel = this.analysis.fields.reservation_status.source_label;
                const notes = values.length ? values.join(', ') : 'No status values documented.';
                return {
                    key: field.key,
                    label: field.label,
                    available,
                    sourceLabel,
                    notes,
                };
            }

            const available = this.analysis.fields[field.key].available;
            const sourceLabel = this.analysis.fields[field.key].source_label;
            return {
                key: field.key,
                label: field.label,
                available,
                sourceLabel,
                notes: available ? 'Documented in endpoint response.' : 'Not documented in endpoint response.',
            };
        });
    },

    get formattedExamplePayload() {
        const payload = this.examplePayload;
        const format = this.exampleFormat;
        const formatLabel = format ? String(format).toLowerCase() : '';

        if (!payload) {
            return '';
        }

        if (formatLabel === 'json') {
            return this.prettyPrintJson(payload);
        }

        if (formatLabel === 'xml') {
            return this.prettyPrintXml(payload);
        }

        return payload;
    },

    get highlightedExamplePayload() {
        const payload = this.formattedExamplePayload;
        const format = this.exampleFormat;
        const formatLabel = format ? String(format).toLowerCase() : '';

        if (!payload) {
            return '';
        }

        if (formatLabel === 'json') {
            return this.highlightJson(payload);
        }

        if (formatLabel === 'xml') {
            return this.highlightXml(payload);
        }

        return this.escapeHtml(payload);
    },

    get ticketPreviewHtml() {
        return this.renderMarkdown(this.ticketDraft || '');
    },

    initPage() {
        this.loadHistory();

        const path = window.location.pathname.replace(/\/+$/, '');
        const match = path.match(/\/pms-documents\/(\d+)$/);
        if (match) {
            const documentId = Number(match[1]);
            if (!Number.isNaN(documentId)) {
                this.loadHistoryItem(documentId);
            }
        }
    },

    updateUrl(documentId) {
        const basePath = '/pms-documents';
        const nextPath = documentId ? `${basePath}/${documentId}` : basePath;
        if (window.location.pathname !== nextPath) {
            window.history.replaceState({}, '', nextPath);
        }
    },

    handleFileInput(event) {
        const selected = event.target.files?.[0] ?? null;
        if (selected) {
            this.file = selected;
            this.documentId = null;
            this.analysis = null;
            this.currentFilename = selected.name;
            this.clearExample();
            this.clearTicket();
            this.errorMessage = '';
        }
    },

    handleDrop(event) {
        this.isDragging = false;
        const dropped = event.dataTransfer?.files?.[0] ?? null;
        if (dropped) {
            this.file = dropped;
            this.documentId = null;
            this.analysis = null;
            this.currentFilename = dropped.name;
            this.clearExample();
            this.clearTicket();
            this.errorMessage = '';
            if (this.$refs.fileInput) {
                this.$refs.fileInput.value = '';
            }
        }
    },

    async requestJson(url, options = {}) {
        const headers = {
            Accept: 'application/json',
            ...(options.headers || {}),
        };

        const response = await fetch(url, { ...options, headers });
        let data = null;

        try {
            data = await response.json();
        } catch {
            data = null;
        }

        if (!response.ok) {
            const message = data?.error || `Request failed (${response.status})`;
            throw new Error(message);
        }

        return data;
    },

    async uploadDocument() {
        if (!this.file) {
            throw new Error('Please select a PDF to upload.');
        }

        const formData = new FormData();
        formData.append('document', this.file);

        const data = await this.requestJson('/api/pms-documents', {
            method: 'POST',
            body: formData,
        });

        if (!data?.id) {
            throw new Error('Upload failed. No document ID returned.');
        }

        this.documentId = data.id;
    },

    async analyzeDocument() {
        this.errorMessage = '';
        this.clearExample();
        this.clearTicket();

        try {
            if (!this.documentId) {
                this.isUploading = true;
                await this.uploadDocument();
            }
        } catch (error) {
            this.errorMessage = this.resolveError(error);
            this.isUploading = false;
            return;
        } finally {
            this.isUploading = false;
        }

        if (!this.documentId) {
            return;
        }

        this.isAnalyzing = true;

        try {
            const data = await this.requestJson(`/api/pms-documents/${this.documentId}/analyze`, {
                method: 'POST',
            });
            this.analysis = data;
            this.currentFilename = this.file?.name || this.currentFilename;
            this.loadHistory();
            this.loadTickets();
            this.updateUrl(this.documentId);
        } catch (error) {
            this.errorMessage = this.resolveError(error);
        } finally {
            this.isAnalyzing = false;
        }
    },

    async loadHistory() {
        this.isLoadingHistory = true;
        this.historyError = '';

        try {
            const data = await this.requestJson(`/api/pms-documents?page=${this.historyPage}`);
            this.history = Array.isArray(data?.data) ? data.data : [];
            this.historyPrevPage = data?.pagination?.prev_page ?? null;
            this.historyNextPage = data?.pagination?.next_page ?? null;
        } catch (error) {
            this.historyError = this.resolveError(error);
        } finally {
            this.isLoadingHistory = false;
        }
    },

    changeHistoryPage(page) {
        if (!page || page === this.historyPage) {
            return;
        }
        this.historyPage = page;
        this.loadHistory();
    },

    async loadHistoryItem(documentId) {
        this.errorMessage = '';
        this.clearExample();
        this.clearTicket();
        this.analysis = null;
        this.tickets = [];

        try {
            const data = await this.requestJson(`/api/pms-documents/${documentId}`);
            if (!data?.analysis_result) {
                this.errorMessage = 'No analysis saved for that document.';
                return;
            }
            this.documentId = data.id;
            this.currentFilename = data.original_filename || '';
            this.analysis = data.analysis_result;
            this.loadTickets();
            this.updateUrl(this.documentId);
        } catch (error) {
            this.errorMessage = this.resolveError(error);
        }
    },

    formatHistoryMeta(entry) {
        if (!entry?.created_at) {
            return '';
        }
        const date = new Date(entry.created_at);
        if (Number.isNaN(date.getTime())) {
            return entry.created_at;
        }
        return date.toLocaleString();
    },

    async loadTickets(refresh = false) {
        if (!this.documentId) {
            this.tickets = [];
            return;
        }

        this.isLoadingTickets = true;
        this.ticketsError = '';

        try {
            const suffix = refresh ? '?refresh=1' : '';
            const data = await this.requestJson(`/api/pms-documents/${this.documentId}/tickets${suffix}`);
            this.tickets = Array.isArray(data) ? data : [];
        } catch (error) {
            this.ticketsError = this.resolveError(error);
        } finally {
            this.isLoadingTickets = false;
        }
    },

    formatTicketMeta(ticket) {
        if (!ticket?.created_at) {
            return '';
        }
        const date = new Date(ticket.created_at);
        if (Number.isNaN(date.getTime())) {
            return ticket.created_at;
        }
        return date.toLocaleString();
    },

    async fetchExample() {
        this.exampleError = '';
        this.exampleFetched = false;

        if (!this.documentId) {
            this.exampleError = 'Upload a document before requesting an example.';
            return;
        }

        if (this.analysis && this.analysis.has_get_reservations_endpoint === false) {
            this.exampleError = 'No GET reservations endpoint documented.';
            return;
        }

        this.isLoadingExample = true;
        this.exampleFormat = null;
        this.examplePayload = null;

        try {
            const data = await this.requestJson(`/api/pms-documents/${this.documentId}/example`, {
                method: 'POST',
            });
            this.exampleFormat = data?.get_reservations_example?.format ?? null;
            this.examplePayload = data?.get_reservations_example?.payload ?? null;
        } catch (error) {
            this.exampleError = this.resolveError(error);
        } finally {
            this.isLoadingExample = false;
            this.exampleFetched = true;
        }
    },

    clearExample() {
        this.exampleFormat = null;
        this.examplePayload = null;
        this.exampleError = '';
        this.isLoadingExample = false;
        this.exampleFetched = false;
    },

    async createTicket() {
        this.ticketError = '';
        this.ticketResult = null;

        if (!this.documentId) {
            this.ticketError = 'Select a document before creating a ticket.';
            return;
        }

        if (!this.analysis) {
            this.ticketError = 'Analyze a document before creating a ticket.';
            return;
        }

        if (!this.ticketDraft.trim()) {
            this.ticketError = 'Ticket body cannot be empty.';
            return;
        }

        this.isCreatingTicket = true;

        try {
            const data = await this.requestJson(`/api/pms-documents/${this.documentId}/ticket`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    type: 'spike',
                    description: this.ticketDraft,
                }),
            });
            this.ticketResult = data;
            this.isTicketModalOpen = false;
            this.loadTickets(true);
        } catch (error) {
            this.ticketError = this.resolveError(error);
        } finally {
            this.isCreatingTicket = false;
        }
    },

    clearTicket() {
        this.ticketError = '';
        this.ticketResult = null;
        this.isCreatingTicket = false;
        this.isTicketModalOpen = false;
        this.ticketDraft = '';
    },

    openTicketModal() {
        this.ticketError = '';

        if (!this.documentId || !this.analysis) {
            this.ticketError = 'Analyze a document before creating a ticket.';
            return;
        }

        if (!this.ticketDraft.trim()) {
            this.ticketDraft = this.buildSpikeDraft();
        }

        this.isTicketModalOpen = true;
    },

    closeTicketModal() {
        this.isTicketModalOpen = false;
    },

    buildSpikeDraft() {
        const fileName = this.currentFilename || 'PMS document';
        const link = this.documentId
            ? `${window.location.origin}/pms-documents/${this.documentId}/download`
            : '';

        const lines = [
            'Context:',
            `Integrate with the following PMS: ${fileName}.`,
            '',
            'Goal:',
            'Fetch campaigns and be able to send campaigns.',
            '',
            'Scope:',
            '- Review documented endpoints and required fields for integration readiness.',
            `- Validate GET reservations endpoint availability: ${this.analysis?.has_get_reservations_endpoint ? 'Yes' : 'No'}.`,
            `- Confirm webhook support: ${this.analysis?.supports_webhooks ? 'Yes' : 'No'}.`,
            '',
            'Expected Outcome:',
            '- Clear mapping of required fields and any gaps to complete the campaigns integration.',
            '',
            'Conclusion:',
            'Pending spike investigation.',
            '',
            'Next Steps:',
            'After successful integration, create the next ticket.',
            '',
            'Documentation:',
            link ? `- [${fileName}](${link})` : `- ${fileName}`,
        ];

        return lines.join('\n');
    },

    renderMarkdown(source) {
        const lines = source.split(/\r?\n/);
        const htmlParts = [];
        let inCodeBlock = false;
        let codeBuffer = [];
        let listOpen = false;

        const closeList = () => {
            if (listOpen) {
                htmlParts.push('</ul>');
                listOpen = false;
            }
        };

        const flushCode = () => {
            if (codeBuffer.length) {
                const code = this.escapeHtml(codeBuffer.join('\n'));
                htmlParts.push(`<pre class="rounded-lg border border-border bg-page p-3 text-xs text-text-muted"><code>${code}</code></pre>`);
                codeBuffer = [];
            }
        };

        lines.forEach((line) => {
            if (line.trim().startsWith('```')) {
                if (inCodeBlock) {
                    flushCode();
                    inCodeBlock = false;
                } else {
                    closeList();
                    inCodeBlock = true;
                }
                return;
            }

            if (inCodeBlock) {
                codeBuffer.push(line);
                return;
            }

            const trimmed = line.trim();
            if (trimmed === '') {
                closeList();
                htmlParts.push('<div class="h-3"></div>');
                return;
            }

            if (trimmed.startsWith('# ')) {
                closeList();
                htmlParts.push(`<h3 class="text-lg font-semibold text-text">${this.formatInline(trimmed.slice(2))}</h3>`);
                return;
            }

            if (trimmed.startsWith('## ')) {
                closeList();
                htmlParts.push(`<h4 class="text-base font-semibold text-text">${this.formatInline(trimmed.slice(3))}</h4>`);
                return;
            }

            if (trimmed.startsWith('### ')) {
                closeList();
                htmlParts.push(`<h5 class="text-sm font-semibold text-text">${this.formatInline(trimmed.slice(4))}</h5>`);
                return;
            }

            if (trimmed.startsWith('- ') || trimmed.startsWith('* ')) {
                if (!listOpen) {
                    htmlParts.push('<ul class="ml-4 list-disc space-y-1 text-sm text-text-muted">');
                    listOpen = true;
                }
                htmlParts.push(`<li>${this.formatInline(trimmed.slice(2))}</li>`);
                return;
            }

            closeList();
            htmlParts.push(`<p class="text-sm text-text-muted">${this.formatInline(trimmed)}</p>`);
        });

        if (inCodeBlock) {
            flushCode();
        }

        closeList();
        return htmlParts.join('');
    },

    formatInline(text) {
        let output = this.escapeHtml(text);

        output = output.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
        output = output.replace(/\*([^*]+)\*/g, '<em>$1</em>');
        output = output.replace(/\[([^\]]+)\]\(([^)]+)\)/g, (match, label, url) => {
            const safeUrl = url.trim();
            if (!/^(https?:\/\/|\/)/i.test(safeUrl)) {
                return `${label} (${safeUrl})`;
            }
            return `<a class="text-text underline" href="${safeUrl}" target="_blank" rel="noreferrer">${label}</a>`;
        });

        return output;
    },

    exportPdf() {
        const details = this.$refs.exampleDetails;
        const wasOpen = details?.open;

        if (details && this.examplePayload && !wasOpen) {
            details.open = true;
        }

        window.print();

        if (details && this.examplePayload && !wasOpen) {
            setTimeout(() => {
                details.open = false;
            }, 500);
        }
    },

    resolveError(error) {
        if (error?.message) {
            return error.message;
        }
        return 'An unexpected error occurred.';
    },

    prettyPrintJson(payload) {
        try {
            const parsed = JSON.parse(payload);
            return JSON.stringify(parsed, null, 2);
        } catch {
            return payload;
        }
    },

    prettyPrintXml(payload) {
        try {
            const normalized = payload.replace(/>\s*</g, '><').replace(/></g, '>\n<');
            const lines = normalized.split('\n');
            let indent = 0;
            const indentUnit = '  ';

            return lines
                .map((line) => {
                    if (line.match(/^<\/.+>/)) {
                        indent = Math.max(indent - 1, 0);
                    }

                    const padding = indentUnit.repeat(indent);

                    if (
                        line.match(/^<[^!?/][^>]*[^/]>$/) &&
                        !line.includes('</')
                    ) {
                        indent += 1;
                    }

                    return `${padding}${line}`;
                })
                .join('\n');
        } catch {
            return payload;
        }
    },

    escapeHtml(value) {
        return value
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    },

    highlightJson(payload) {
        const tokenRegex = /"(?:\\.|[^"\\])*"|-?\d+(?:\.\d+)?(?:[eE][+-]?\d+)?|\btrue\b|\bfalse\b|\bnull\b/g;
        let result = '';
        let lastIndex = 0;
        let match;

        while ((match = tokenRegex.exec(payload)) !== null) {
            result += this.escapeHtml(payload.slice(lastIndex, match.index));
            const token = match[0];
            const trailing = payload.slice(tokenRegex.lastIndex);
            let tokenClass = 'token-string';

            if (token.startsWith('"')) {
                if (/^\s*:/.test(trailing)) {
                    tokenClass = 'token-key';
                }
            } else if (token === 'true' || token === 'false') {
                tokenClass = 'token-boolean';
            } else if (token === 'null') {
                tokenClass = 'token-null';
            } else {
                tokenClass = 'token-number';
            }

            result += `<span class="${tokenClass}">${this.escapeHtml(token)}</span>`;
            lastIndex = tokenRegex.lastIndex;
        }

        result += this.escapeHtml(payload.slice(lastIndex));
        return result;
    },

    highlightXml(payload) {
        const tagRegex = /<[^>]+>/g;
        let result = '';
        let lastIndex = 0;
        let match;

        while ((match = tagRegex.exec(payload)) !== null) {
            result += this.escapeHtml(payload.slice(lastIndex, match.index));
            result += this.highlightXmlTag(match[0]);
            lastIndex = tagRegex.lastIndex;
        }

        result += this.escapeHtml(payload.slice(lastIndex));
        return result;
    },

    highlightXmlTag(tag) {
        if (tag.startsWith('<!--')) {
            return `<span class="token-comment">${this.escapeHtml(tag)}</span>`;
        }

        const isProcessing = tag.startsWith('<?');
        const isClosing = /^<\s*\//.test(tag);
        const isSelfClosing = /\/\s*>$/.test(tag);
        const isDeclarationEnd = /\?>$/.test(tag);
        const nameMatch = tag.match(/^<\s*\/?\s*([^\s>\/\?]+)/);
        const tagName = nameMatch ? nameMatch[1] : '';
        const attrRegex = /([^\s=]+)\s*=\s*(['"])(.*?)\2/g;
        const attributes = [];
        let attrMatch;

        while ((attrMatch = attrRegex.exec(tag)) !== null) {
            attributes.push({ name: attrMatch[1], value: attrMatch[3] });
        }

        let output = isProcessing ? '&lt;?' : '&lt;';

        if (isClosing) {
            output += '/';
        }

        if (tagName) {
            output += `<span class="token-tag-name">${this.escapeHtml(tagName)}</span>`;
        }

        attributes.forEach((attr) => {
            output += ` <span class="token-attr-name">${this.escapeHtml(attr.name)}</span>=<span class="token-attr-value">"${this.escapeHtml(attr.value)}"</span>`;
        });

        if (isProcessing) {
            output += isDeclarationEnd ? '?&gt;' : '&gt;';
        } else if (isSelfClosing) {
            output += ' /&gt;';
        } else {
            output += '&gt;';
        }

        return `<span class="token-tag">${output}</span>`;
    },

    reset() {
        this.file = null;
        this.documentId = null;
        this.analysis = null;
        this.currentFilename = '';
        this.tickets = [];
        this.clearExample();
        this.clearTicket();
        this.errorMessage = '';
        this.isUploading = false;
        this.isAnalyzing = false;
        this.isDragging = false;
        if (this.$refs.fileInput) {
            this.$refs.fileInput.value = '';
        }
        this.updateUrl(null);
    },
}));

Alpine.start();
