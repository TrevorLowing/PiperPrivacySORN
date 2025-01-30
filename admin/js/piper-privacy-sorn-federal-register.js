/**
 * Federal Register integration JavaScript
 */
(function($) {
    'use strict';

    class FederalRegisterManager {
        constructor() {
            this.initButtons();
            this.initModals();
            this.initForms();
        }

        // Initialize Federal Register buttons
        initButtons() {
            const self = this;

            // Import from Federal Register button
            $('#import-from-fr').on('click', function(e) {
                e.preventDefault();
                self.showModal('#fr-import-modal');
            });

            // Submit to Federal Register button
            $('.submit-to-fr').on('click', function(e) {
                e.preventDefault();
                const sornId = $(this).data('sorn-id');
                self.submitToFederalRegister(sornId);
            });
        }

        // Initialize modals
        initModals() {
            const self = this;
            const modals = ['#fr-import-modal', '#fr-preview-modal'];
            
            // Close modal
            $('.close').on('click', function() {
                $(this).closest('.modal').hide();
            });

            // Close on outside click
            $(window).on('click', function(e) {
                modals.forEach(modal => {
                    if (e.target === $(modal)[0]) {
                        $(modal).hide();
                    }
                });
            });
        }

        // Initialize forms
        initForms() {
            const self = this;

            // Federal Register search form
            $('#fr-search-form').on('submit', function(e) {
                e.preventDefault();
                self.searchFederalRegister($(this));
            });

            // Federal Register import form
            $('#fr-import-form').on('submit', function(e) {
                e.preventDefault();
                self.importFromFederalRegister($(this));
            });
        }

        // Search Federal Register
        searchFederalRegister($form) {
            const self = this;
            const $results = $('#fr-search-results');
            const $submit = $form.find('input[type="submit"]');
            const $spinner = $form.find('.spinner');
            
            $submit.prop('disabled', true);
            $spinner.addClass('is-active');
            $results.html('<div class="spinner is-active"></div>');

            $.ajax({
                url: piperPrivacySornAdmin.ajax_url,
                type: 'POST',
                data: {
                    action: 'search_federal_register',
                    nonce: piperPrivacySornAdmin.nonce,
                    data: $form.serialize()
                },
                success: function(response) {
                    if (response.success) {
                        self.displaySearchResults(response.data);
                    } else {
                        $results.html(`<p class="error">${response.data.message || piperPrivacySornAdmin.i18n.error}</p>`);
                    }
                },
                error: function() {
                    $results.html(`<p class="error">${piperPrivacySornAdmin.i18n.error}</p>`);
                },
                complete: function() {
                    $submit.prop('disabled', false);
                    $spinner.removeClass('is-active');
                }
            });
        }

        // Display Federal Register search results
        displaySearchResults(data) {
            const $results = $('#fr-search-results');
            let html = '<ul class="fr-results-list">';

            if (!data.results || !data.results.length) {
                $results.html(`<p>${piperPrivacySornAdmin.i18n.no_results}</p>`);
                return;
            }

            data.results.forEach(result => {
                html += `
                    <li class="fr-result-item">
                        <div class="fr-result-header">
                            <h3>${this.escapeHtml(result.title)}</h3>
                            <span class="fr-result-date">${this.formatDate(result.publication_date)}</span>
                        </div>
                        <div class="fr-result-meta">
                            <span class="fr-result-agency">${this.escapeHtml(result.agency_names.join(', '))}</span>
                            <span class="fr-result-doc-num">${this.escapeHtml(result.document_number)}</span>
                        </div>
                        <div class="fr-result-abstract">${this.escapeHtml(result.abstract)}</div>
                        <div class="fr-result-actions">
                            <button class="button preview-fr-doc" data-doc-num="${result.document_number}">
                                ${piperPrivacySornAdmin.i18n.preview}
                            </button>
                            <button class="button button-primary import-fr-doc" data-doc-num="${result.document_number}">
                                ${piperPrivacySornAdmin.i18n.import}
                            </button>
                        </div>
                    </li>
                `;
            });

            html += '</ul>';
            
            if (data.total_pages > 1) {
                html += this.createPagination(data.current_page, data.total_pages);
            }

            $results.html(html);
            this.initResultActions();
        }

        // Initialize result actions
        initResultActions() {
            const self = this;

            // Preview document
            $('.preview-fr-doc').on('click', function() {
                const docNum = $(this).data('doc-num');
                self.previewDocument(docNum);
            });

            // Import document
            $('.import-fr-doc').on('click', function() {
                const docNum = $(this).data('doc-num');
                self.importDocument(docNum);
            });

            // Pagination
            $('.fr-pagination a').on('click', function(e) {
                e.preventDefault();
                const page = $(this).data('page');
                $('#fr-search-form input[name="page"]').val(page);
                $('#fr-search-form').submit();
            });
        }

        // Preview Federal Register document
        previewDocument(docNum) {
            const self = this;
            const $modal = $('#fr-preview-modal');
            const $content = $('#fr-preview-content');
            
            $content.html('<div class="spinner is-active"></div>');
            self.showModal('#fr-preview-modal');

            $.ajax({
                url: piperPrivacySornAdmin.ajax_url,
                type: 'POST',
                data: {
                    action: 'get_federal_register_document',
                    nonce: piperPrivacySornAdmin.nonce,
                    document_number: docNum
                },
                success: function(response) {
                    if (response.success) {
                        self.displayPreview(response.data);
                    } else {
                        $content.html(`<p class="error">${response.data.message || piperPrivacySornAdmin.i18n.error}</p>`);
                    }
                },
                error: function() {
                    $content.html(`<p class="error">${piperPrivacySornAdmin.i18n.error}</p>`);
                }
            });
        }

        // Import Federal Register document
        importDocument(docNum) {
            if (!confirm(piperPrivacySornAdmin.i18n.confirm_import)) {
                return;
            }

            $.ajax({
                url: piperPrivacySornAdmin.ajax_url,
                type: 'POST',
                data: {
                    action: 'import_federal_register_document',
                    nonce: piperPrivacySornAdmin.nonce,
                    document_number: docNum
                },
                success: function(response) {
                    if (response.success) {
                        window.location.reload();
                    } else {
                        alert(response.data.message || piperPrivacySornAdmin.i18n.error);
                    }
                },
                error: function() {
                    alert(piperPrivacySornAdmin.i18n.error);
                }
            });
        }

        // Submit SORN to Federal Register
        submitToFederalRegister(sornId) {
            if (!confirm(piperPrivacySornAdmin.i18n.confirm_submit)) {
                return;
            }

            $.ajax({
                url: piperPrivacySornAdmin.ajax_url,
                type: 'POST',
                data: {
                    action: 'submit_to_federal_register',
                    nonce: piperPrivacySornAdmin.nonce,
                    sorn_id: sornId
                },
                success: function(response) {
                    if (response.success) {
                        alert(piperPrivacySornAdmin.i18n.submit_success);
                        window.location.reload();
                    } else {
                        alert(response.data.message || piperPrivacySornAdmin.i18n.error);
                    }
                },
                error: function() {
                    alert(piperPrivacySornAdmin.i18n.error);
                }
            });
        }

        // Helper functions
        showModal(modalId) {
            $(modalId).show();
        }

        hideModal(modalId) {
            $(modalId).hide();
        }

        escapeHtml(unsafe) {
            return unsafe
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }

        formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString();
        }

        createPagination(currentPage, totalPages) {
            let html = '<div class="fr-pagination">';
            
            if (currentPage > 1) {
                html += `<a href="#" data-page="${currentPage - 1}" class="prev-page">&laquo; Previous</a>`;
            }

            for (let i = 1; i <= totalPages; i++) {
                if (i === currentPage) {
                    html += `<span class="current-page">${i}</span>`;
                } else if (
                    i === 1 || 
                    i === totalPages || 
                    (i >= currentPage - 2 && i <= currentPage + 2)
                ) {
                    html += `<a href="#" data-page="${i}">${i}</a>`;
                } else if (
                    i === currentPage - 3 || 
                    i === currentPage + 3
                ) {
                    html += '<span class="ellipsis">&hellip;</span>';
                }
            }

            if (currentPage < totalPages) {
                html += `<a href="#" data-page="${currentPage + 1}" class="next-page">Next &raquo;</a>`;
            }

            html += '</div>';
            return html;
        }

        displayPreview(data) {
            const $content = $('#fr-preview-content');
            
            const html = `
                <div class="fr-preview">
                    <h2>${this.escapeHtml(data.title)}</h2>
                    <div class="fr-preview-meta">
                        <p><strong>Agency:</strong> ${this.escapeHtml(data.agency_names.join(', '))}</p>
                        <p><strong>Document Number:</strong> ${this.escapeHtml(data.document_number)}</p>
                        <p><strong>Publication Date:</strong> ${this.formatDate(data.publication_date)}</p>
                    </div>
                    <div class="fr-preview-content">
                        ${data.html_content}
                    </div>
                    <div class="fr-preview-actions">
                        <button class="button button-primary import-fr-doc" data-doc-num="${data.document_number}">
                            ${piperPrivacySornAdmin.i18n.import}
                        </button>
                    </div>
                </div>
            `;

            $content.html(html);
            this.initResultActions();
        }
    }

    // Initialize on document ready
    $(document).ready(function() {
        new FederalRegisterManager();
    });

})(jQuery);
