/**
 * Federal Register History Scripts
 */
(function($) {
    'use strict';

    const FederalRegisterHistory = {
        init: function() {
            this.bindEvents();
            this.initializeFilters();
        },

        bindEvents: function() {
            $('#fr-retry-submission').on('click', this.handleRetry);
            $('.fr-status-filter').on('change', this.handleFilterChange);
        },

        initializeFilters: function() {
            // Initialize any filter widgets (datepickers, etc.)
            if ($.fn.datepicker) {
                $('.fr-date-filter').datepicker({
                    dateFormat: 'yy-mm-dd',
                    maxDate: new Date()
                });
            }
        },

        handleRetry: function(e) {
            e.preventDefault();
            const submissionId = $(this).data('submission-id');

            if (!confirm(wp_fr_history.confirm_retry)) {
                return;
            }

            FederalRegisterHistory.showLoading();

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'fr_retry_submission',
                    nonce: wp_fr_history.retry_nonce,
                    submission_id: submissionId
                },
                success: function(response) {
                    if (response.success) {
                        window.location.reload();
                    } else {
                        FederalRegisterHistory.showError(response.data);
                    }
                },
                error: function(xhr, status, error) {
                    FederalRegisterHistory.showError(error);
                },
                complete: function() {
                    FederalRegisterHistory.hideLoading();
                }
            });
        },

        handleFilterChange: function() {
            $(this).closest('form').submit();
        },

        showLoading: function() {
            $('<div class="fr-loading">').appendTo('body')
                .append('<div class="fr-loading-spinner">')
                .append('<div class="fr-loading-text">' + wp_fr_history.loading_text + '</div>');
        },

        hideLoading: function() {
            $('.fr-loading').remove();
        },

        showError: function(message) {
            const $notice = $('<div class="notice notice-error is-dismissible">')
                .append('<p>' + message + '</p>')
                .append('<button type="button" class="notice-dismiss">')
                .insertAfter('.wp-header-end');

            // Auto dismiss after 5 seconds
            setTimeout(function() {
                $notice.fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);

            // Handle dismiss button
            $notice.find('.notice-dismiss').on('click', function() {
                $notice.fadeOut(function() {
                    $(this).remove();
                });
            });
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        FederalRegisterHistory.init();
    });

})(jQuery);
