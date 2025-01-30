/**
 * Federal Register Submission History Scripts
 */
(function($) {
    'use strict';

    const FederalRegisterHistory = {
        init: function() {
            this.bindEvents();
            this.initializeDatepickers();
            this.initializeTooltips();
        },

        bindEvents: function() {
            // Filter form submission
            $('#fr-history-filters').on('submit', this.handleFilterSubmit);
            $('#fr-reset-filters').on('click', this.resetFilters);

            // Export button
            $('#fr-export-history').on('click', this.showExportModal);
            $('#fr-export-submit').on('click', this.handleExport);

            // View audit log
            $('.fr-view-audit-log').on('click', this.viewAuditLog);

            // Pagination
            $('.fr-history-pagination').on('click', 'a', this.handlePagination);
        },

        initializeDatepickers: function() {
            $('.fr-datepicker').datepicker({
                dateFormat: 'yy-mm-dd',
                maxDate: new Date()
            });
        },

        initializeTooltips: function() {
            $('.fr-history-help').tooltip({
                position: { my: 'left+10 center', at: 'right center' }
            });
        },

        handleFilterSubmit: function(e) {
            e.preventDefault();
            const $form = $(this);
            
            // Update URL with filter parameters
            const params = new URLSearchParams($form.serialize());
            window.history.pushState({}, '', `?page=fr-submission-history&${params.toString()}`);

            // Show loading state
            FederalRegisterHistory.showLoading();

            // Submit filter request
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'fr_filter_history',
                    nonce: $('#fr_history_nonce').val(),
                    filters: $form.serialize()
                },
                success: function(response) {
                    if (response.success) {
                        $('#fr-history-table-wrapper').html(response.data.html);
                        FederalRegisterHistory.updatePagination(response.data.pagination);
                    } else {
                        FederalRegisterHistory.showError(response.data);
                    }
                },
                error: function() {
                    FederalRegisterHistory.showError(wp_fr_history.error_message);
                },
                complete: function() {
                    FederalRegisterHistory.hideLoading();
                }
            });
        },

        resetFilters: function(e) {
            e.preventDefault();
            const $form = $('#fr-history-filters');
            
            // Reset form fields
            $form[0].reset();
            
            // Trigger filter submission
            $form.submit();
        },

        showExportModal: function(e) {
            e.preventDefault();
            
            // Show modal with current filter settings
            const $modal = $('#fr-export-modal');
            const currentFilters = $('#fr-history-filters').serialize();
            
            $modal.find('input[name="filters"]').val(currentFilters);
            $modal.dialog({
                title: wp_fr_history.export_title,
                modal: true,
                width: 500,
                buttons: [
                    {
                        text: wp_fr_history.export_button,
                        click: FederalRegisterHistory.handleExport
                    },
                    {
                        text: wp_fr_history.cancel_button,
                        click: function() {
                            $(this).dialog('close');
                        }
                    }
                ]
            });
        },

        handleExport: function() {
            const $modal = $('#fr-export-modal');
            const format = $modal.find('input[name="export_format"]:checked').val();
            const filters = $modal.find('input[name="filters"]').val();

            // Create form for POST submission
            const $form = $('<form>')
                .attr('method', 'post')
                .attr('action', ajaxurl)
                .css('display', 'none');

            // Add form fields
            $form.append($('<input>').attr({
                type: 'hidden',
                name: 'action',
                value: 'fr_export_history'
            }));

            $form.append($('<input>').attr({
                type: 'hidden',
                name: 'nonce',
                value: $('#fr_history_nonce').val()
            }));

            $form.append($('<input>').attr({
                type: 'hidden',
                name: 'format',
                value: format
            }));

            $form.append($('<input>').attr({
                type: 'hidden',
                name: 'filters',
                value: filters
            }));

            // Submit form
            $('body').append($form);
            $form.submit();
            $form.remove();

            // Close modal
            $modal.dialog('close');
        },

        viewAuditLog: function(e) {
            e.preventDefault();
            const submissionId = $(this).data('submission-id');

            // Show loading state
            FederalRegisterHistory.showLoading();

            // Fetch audit log
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'fr_view_audit_log',
                    nonce: $('#fr_history_nonce').val(),
                    submission_id: submissionId
                },
                success: function(response) {
                    if (response.success) {
                        const $modal = $('<div>')
                            .addClass('fr-audit-log-modal')
                            .html(response.data.html);

                        $modal.dialog({
                            title: wp_fr_history.audit_log_title,
                            modal: true,
                            width: 600,
                            maxHeight: $(window).height() * 0.8,
                            buttons: [
                                {
                                    text: wp_fr_history.close_button,
                                    click: function() {
                                        $(this).dialog('close');
                                    }
                                }
                            ],
                            close: function() {
                                $(this).remove();
                            }
                        });
                    } else {
                        FederalRegisterHistory.showError(response.data);
                    }
                },
                error: function() {
                    FederalRegisterHistory.showError(wp_fr_history.error_message);
                },
                complete: function() {
                    FederalRegisterHistory.hideLoading();
                }
            });
        },

        handlePagination: function(e) {
            e.preventDefault();
            const page = $(this).data('page');
            
            // Update page number in form
            $('#fr-history-page').val(page);
            
            // Submit filter form
            $('#fr-history-filters').submit();
        },

        updatePagination: function(paginationHtml) {
            $('.fr-history-pagination').html(paginationHtml);
        },

        showLoading: function() {
            $('#fr-history-loading').show();
        },

        hideLoading: function() {
            $('#fr-history-loading').hide();
        },

        showError: function(message) {
            const $error = $('#fr-history-error');
            $error.text(message).show();
            setTimeout(() => $error.fadeOut(), 5000);
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        FederalRegisterHistory.init();
    });

})(jQuery);
