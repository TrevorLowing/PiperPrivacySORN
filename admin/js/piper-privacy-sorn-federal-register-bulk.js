/**
 * Federal Register Bulk Actions
 */
(function($) {
    'use strict';

    const FederalRegisterBulk = {
        init: function() {
            this.bindEvents();
        },

        bindEvents: function() {
            $('#fr-select-all').on('change', this.handleSelectAll);
            $('#fr-bulk-apply').on('click', this.handleBulkAction);
        },

        handleSelectAll: function(e) {
            $('input[name="submission_ids[]"]').prop('checked', $(this).prop('checked'));
        },

        handleBulkAction: function(e) {
            e.preventDefault();
            
            const action = $('select[name="bulk_action"]').val();
            if (!action) {
                alert(wp_fr_bulk.select_action);
                return;
            }

            const selectedIds = [];
            $('input[name="submission_ids[]"]:checked').each(function() {
                selectedIds.push($(this).val());
            });

            if (selectedIds.length === 0) {
                alert(wp_fr_bulk.select_items);
                return;
            }

            if (action === 'archive' && !confirm(wp_fr_bulk.confirm_archive)) {
                return;
            }

            FederalRegisterBulk.showLoading();

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'fr_bulk_action',
                    nonce: wp_fr_bulk.nonce,
                    bulk_action: action,
                    submission_ids: selectedIds
                },
                success: function(response) {
                    if (response.success) {
                        if (action === 'export') {
                            // Download the exported file
                            window.location.href = response.data.download_url;
                            FederalRegisterBulk.showMessage(wp_fr_bulk.export_success, 'success');
                        } else {
                            // Show results and refresh the page
                            FederalRegisterBulk.showResults(response.data.results);
                            setTimeout(function() {
                                window.location.reload();
                            }, 3000);
                        }
                    } else {
                        FederalRegisterBulk.showMessage(response.data.message, 'error');
                    }
                },
                error: function(xhr, status, error) {
                    FederalRegisterBulk.showMessage(error, 'error');
                },
                complete: function() {
                    FederalRegisterBulk.hideLoading();
                }
            });
        },

        showResults: function(results) {
            const $results = $('<div class="fr-bulk-results">');
            let successCount = 0;
            let errorCount = 0;

            Object.entries(results).forEach(([id, result]) => {
                if (result.success) {
                    successCount++;
                } else {
                    errorCount++;
                }
            });

            const summary = $('<div class="fr-bulk-summary">')
                .append(`<p>${wp_fr_bulk.results_summary
                    .replace('%s', successCount)
                    .replace('%e', errorCount)}</p>`);

            if (errorCount > 0) {
                const details = $('<div class="fr-bulk-details">');
                Object.entries(results).forEach(([id, result]) => {
                    if (!result.success) {
                        details.append(`<p>ID ${id}: ${result.message}</p>`);
                    }
                });
                $results.append(details);
            }

            $results.insertAfter('.fr-submissions-filters');
            
            if (errorCount === 0) {
                setTimeout(function() {
                    $results.fadeOut();
                }, 3000);
            }
        },

        showMessage: function(message, type) {
            const $notice = $('<div class="notice is-dismissible">')
                .addClass(type === 'error' ? 'notice-error' : 'notice-success')
                .append('<p>' + message + '</p>')
                .append('<button type="button" class="notice-dismiss">')
                .insertAfter('.wp-header-end');

            // Auto dismiss after 5 seconds if success
            if (type === 'success') {
                setTimeout(function() {
                    $notice.fadeOut(function() {
                        $(this).remove();
                    });
                }, 5000);
            }

            // Handle dismiss button
            $notice.find('.notice-dismiss').on('click', function() {
                $notice.fadeOut(function() {
                    $(this).remove();
                });
            });
        },

        showLoading: function() {
            $('<div class="fr-loading">').appendTo('body')
                .append('<div class="fr-loading-spinner">')
                .append('<div class="fr-loading-text">' + wp_fr_bulk.loading_text + '</div>');
        },

        hideLoading: function() {
            $('.fr-loading').remove();
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        FederalRegisterBulk.init();
    });

})(jQuery);
