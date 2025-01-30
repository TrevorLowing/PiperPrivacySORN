/**
 * SORN Manager admin JavaScript
 */
(function($) {
    'use strict';

    // SORN Manager class
    class SornManager {
        constructor() {
            this.initModals();
            this.initForms();
            this.initSearch();
            this.loadSorns();
        }

        // Initialize modals
        initModals() {
            const self = this;
            const modals = ['#sorn-editor-modal', '#version-history-modal', '#comments-modal'];
            
            // Show modal
            $('.page-title-action').on('click', function(e) {
                e.preventDefault();
                self.showModal('#sorn-editor-modal');
            });

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

            // SORN editor form
            $('#sorn-editor-form').on('submit', function(e) {
                e.preventDefault();
                self.saveSorn($(this));
            });

            // Comment form
            $('#add-comment-form').on('submit', function(e) {
                e.preventDefault();
                self.addComment($(this));
            });
        }

        // Initialize search and filters
        initSearch() {
            const self = this;

            $('#sorn-search-form').on('submit', function(e) {
                e.preventDefault();
                self.loadSorns();
            });

            $('#filter-by-agency, #filter-by-status').on('change', function() {
                self.loadSorns();
            });
        }

        // Load SORNs
        loadSorns(page = 1) {
            const self = this;
            const container = $('#the-list');
            const searchData = {
                action: 'get_sorns',
                nonce: piperPrivacySornAdmin.nonce,
                page: page,
                search: $('#sorn-search-input').val(),
                agency: $('#filter-by-agency').val(),
                status: $('#filter-by-status').val()
            };

            container.html('<tr><td colspan="6"><div class="spinner is-active"></div></td></tr>');

            $.ajax({
                url: piperPrivacySornAdmin.ajax_url,
                type: 'POST',
                data: searchData,
                success: function(response) {
                    if (response.success) {
                        self.displaySorns(response.data);
                    } else {
                        container.html(`<tr><td colspan="6">${response.data.message || piperPrivacySornAdmin.i18n.error}</td></tr>`);
                    }
                },
                error: function() {
                    container.html(`<tr><td colspan="6">${piperPrivacySornAdmin.i18n.error}</td></tr>`);
                }
            });
        }

        // Display SORNs
        displaySorns(data) {
            const container = $('#the-list');
            let html = '';

            if (!data.results || !data.results.length) {
                container.html('<tr><td colspan="6">' + piperPrivacySornAdmin.i18n.no_sorns + '</td></tr>');
                return;
            }

            data.results.forEach(sorn => {
                html += `
                    <tr>
                        <td class="title column-title has-row-actions column-primary">
                            <strong><a href="#" class="row-title" data-id="${sorn.id}">${this.escapeHtml(sorn.title)}</a></strong>
                            <div class="row-actions">
                                <span class="edit">
                                    <a href="#" data-id="${sorn.id}">${piperPrivacySornAdmin.i18n.edit}</a> |
                                </span>
                                <span class="versions">
                                    <a href="#" data-id="${sorn.id}">${piperPrivacySornAdmin.i18n.versions}</a> |
                                </span>
                                <span class="comments">
                                    <a href="#" data-id="${sorn.id}">${piperPrivacySornAdmin.i18n.comments}</a> |
                                </span>
                                <span class="delete">
                                    <a href="#" data-id="${sorn.id}">${piperPrivacySornAdmin.i18n.delete}</a>
                                </span>
                            </div>
                        </td>
                        <td class="identifier column-identifier">${this.escapeHtml(sorn.identifier)}</td>
                        <td class="agency column-agency">${this.escapeHtml(sorn.agency)}</td>
                        <td class="status column-status">
                            <span class="status-badge status-${sorn.status}">${this.escapeHtml(sorn.status)}</span>
                        </td>
                        <td class="version column-version">${sorn.version}</td>
                        <td class="date column-date">${this.formatDate(sorn.updated_at)}</td>
                    </tr>
                `;
            });

            container.html(html);
            this.updatePagination(data.total, data.pages);
            this.initRowActions();
        }

        // Initialize row actions
        initRowActions() {
            const self = this;

            // Edit SORN
            $('.row-actions .edit a').on('click', function(e) {
                e.preventDefault();
                self.editSorn($(this).data('id'));
            });

            // View versions
            $('.row-actions .versions a').on('click', function(e) {
                e.preventDefault();
                self.viewVersions($(this).data('id'));
            });

            // View comments
            $('.row-actions .comments a').on('click', function(e) {
                e.preventDefault();
                self.viewComments($(this).data('id'));
            });

            // Delete SORN
            $('.row-actions .delete a').on('click', function(e) {
                e.preventDefault();
                if (confirm(piperPrivacySornAdmin.i18n.confirm_delete)) {
                    self.deleteSorn($(this).data('id'));
                }
            });
        }

        // Save SORN
        saveSorn($form) {
            const self = this;
            const $submit = $form.find('input[type="submit"]');
            const $spinner = $form.find('.spinner');
            
            $submit.prop('disabled', true);
            $spinner.addClass('is-active');

            $.ajax({
                url: piperPrivacySornAdmin.ajax_url,
                type: 'POST',
                data: {
                    action: 'save_sorn',
                    nonce: piperPrivacySornAdmin.nonce,
                    data: $form.serialize()
                },
                success: function(response) {
                    if (response.success) {
                        self.hideModal('#sorn-editor-modal');
                        self.loadSorns();
                    } else {
                        alert(response.data.message || piperPrivacySornAdmin.i18n.error);
                    }
                },
                error: function() {
                    alert(piperPrivacySornAdmin.i18n.error);
                },
                complete: function() {
                    $submit.prop('disabled', false);
                    $spinner.removeClass('is-active');
                }
            });
        }

        // Edit SORN
        editSorn(sornId) {
            const self = this;
            
            $.ajax({
                url: piperPrivacySornAdmin.ajax_url,
                type: 'POST',
                data: {
                    action: 'get_sorn',
                    nonce: piperPrivacySornAdmin.nonce,
                    sorn_id: sornId
                },
                success: function(response) {
                    if (response.success) {
                        self.populateForm(response.data);
                        self.showModal('#sorn-editor-modal');
                    } else {
                        alert(response.data.message || piperPrivacySornAdmin.i18n.error);
                    }
                },
                error: function() {
                    alert(piperPrivacySornAdmin.i18n.error);
                }
            });
        }

        // View versions
        viewVersions(sornId) {
            const self = this;
            const $modal = $('#version-history-modal');
            const $content = $('#version-history-content');
            
            $content.html('<div class="spinner is-active"></div>');
            self.showModal('#version-history-modal');

            $.ajax({
                url: piperPrivacySornAdmin.ajax_url,
                type: 'POST',
                data: {
                    action: 'get_versions',
                    nonce: piperPrivacySornAdmin.nonce,
                    sorn_id: sornId
                },
                success: function(response) {
                    if (response.success) {
                        self.displayVersions(response.data);
                    } else {
                        $content.html(`<p class="error">${response.data.message || piperPrivacySornAdmin.i18n.error}</p>`);
                    }
                },
                error: function() {
                    $content.html(`<p class="error">${piperPrivacySornAdmin.i18n.error}</p>`);
                }
            });
        }

        // View comments
        viewComments(sornId) {
            const self = this;
            const $modal = $('#comments-modal');
            const $content = $('#comments-content');
            
            $content.html('<div class="spinner is-active"></div>');
            $modal.find('input[name="sorn_id"]').val(sornId);
            self.showModal('#comments-modal');

            $.ajax({
                url: piperPrivacySornAdmin.ajax_url,
                type: 'POST',
                data: {
                    action: 'get_comments',
                    nonce: piperPrivacySornAdmin.nonce,
                    sorn_id: sornId
                },
                success: function(response) {
                    if (response.success) {
                        self.displayComments(response.data);
                    } else {
                        $content.html(`<p class="error">${response.data.message || piperPrivacySornAdmin.i18n.error}</p>`);
                    }
                },
                error: function() {
                    $content.html(`<p class="error">${piperPrivacySornAdmin.i18n.error}</p>`);
                }
            });
        }

        // Delete SORN
        deleteSorn(sornId) {
            const self = this;
            
            $.ajax({
                url: piperPrivacySornAdmin.ajax_url,
                type: 'POST',
                data: {
                    action: 'delete_sorn',
                    nonce: piperPrivacySornAdmin.nonce,
                    sorn_id: sornId
                },
                success: function(response) {
                    if (response.success) {
                        self.loadSorns();
                    } else {
                        alert(response.data.message || piperPrivacySornAdmin.i18n.error);
                    }
                },
                error: function() {
                    alert(piperPrivacySornAdmin.i18n.error);
                }
            });
        }

        // Add comment
        addComment($form) {
            const self = this;
            const $submit = $form.find('input[type="submit"]');
            const $spinner = $form.find('.spinner');
            
            $submit.prop('disabled', true);
            $spinner.addClass('is-active');

            $.ajax({
                url: piperPrivacySornAdmin.ajax_url,
                type: 'POST',
                data: {
                    action: 'add_comment',
                    nonce: piperPrivacySornAdmin.nonce,
                    data: $form.serialize()
                },
                success: function(response) {
                    if (response.success) {
                        self.viewComments($form.find('input[name="sorn_id"]').val());
                        $form[0].reset();
                    } else {
                        alert(response.data.message || piperPrivacySornAdmin.i18n.error);
                    }
                },
                error: function() {
                    alert(piperPrivacySornAdmin.i18n.error);
                },
                complete: function() {
                    $submit.prop('disabled', false);
                    $spinner.removeClass('is-active');
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
            return date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
        }

        updatePagination(total, pages) {
            // Implement pagination UI update
        }

        populateForm(data) {
            const $form = $('#sorn-editor-form');
            
            // Reset form
            $form[0].reset();
            
            // Set form title
            $('#sorn-editor-title').text(piperPrivacySornAdmin.i18n.edit_sorn);
            
            // Populate fields
            Object.keys(data).forEach(key => {
                const $field = $form.find(`[name="${key}"]`);
                if ($field.length) {
                    if ($field.is('textarea') && tinyMCE.get($field.attr('id'))) {
                        tinyMCE.get($field.attr('id')).setContent(data[key] || '');
                    } else {
                        $field.val(data[key]);
                    }
                }
            });

            // Show version note field for existing SORNs
            $('.version-note').show();
        }

        displayVersions(versions) {
            const $content = $('#version-history-content');
            let html = '<ul class="version-list">';

            versions.forEach(version => {
                html += `
                    <li class="version-item">
                        <div class="version-header">
                            <span class="version-number">Version ${version.version}</span>
                            <span class="version-date">${this.formatDate(version.created_at)}</span>
                        </div>
                        <p class="version-changes">${this.escapeHtml(version.changes)}</p>
                    </li>
                `;
            });

            html += '</ul>';
            $content.html(html);
        }

        displayComments(comments) {
            const $content = $('#comments-content');
            let html = '<ul class="comment-list">';

            comments.forEach(comment => {
                html += `
                    <li class="comment-item">
                        <div class="comment-header">
                            <span class="comment-author">${this.escapeHtml(comment.author)}</span>
                            <span class="comment-date">${this.formatDate(comment.created_at)}</span>
                        </div>
                        <p class="comment-text">${this.escapeHtml(comment.comment)}</p>
                    </li>
                `;
            });

            html += '</ul>';
            $content.html(html);
        }
    }

    // Initialize on document ready
    $(document).ready(function() {
        new SornManager();
    });

})(jQuery);
