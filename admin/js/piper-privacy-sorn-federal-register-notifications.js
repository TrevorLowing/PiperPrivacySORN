/**
 * Federal Register Notification Settings Scripts
 */
(function($) {
    'use strict';

    const FederalRegisterNotifications = {
        init: function() {
            this.bindEvents();
            this.initializeTooltips();
        },

        bindEvents: function() {
            $('#send-test-notification').on('click', this.handleTestNotification);
            $('input[name="notifications_enabled"]').on('change', this.toggleNotificationFields);
            this.toggleNotificationFields();
        },

        initializeTooltips: function() {
            $('.fr-template-help').tipTip({
                defaultPosition: 'top',
                fadeIn: 50,
                fadeOut: 50,
                delay: 200
            });
        },

        toggleNotificationFields: function() {
            const enabled = $('input[name="notifications_enabled"]').is(':checked');
            const $fields = $('.fr-notification-templates, .fr-notification-test')
                .add('input[name="notification_events[]"]')
                .add('input[name="notify_admin"]')
                .add('input[name="notify_author"]')
                .add('textarea[name="custom_recipients"]');

            if (enabled) {
                $fields.removeClass('disabled').find('input, textarea, select, button').prop('disabled', false);
            } else {
                $fields.addClass('disabled').find('input, textarea, select, button').prop('disabled', true);
            }
        },

        handleTestNotification: function(e) {
            e.preventDefault();
            const $button = $(this);
            const $spinner = $button.next('.spinner');
            const eventType = $('#test-notification-type').val();

            // Disable button and show spinner
            $button.prop('disabled', true);
            $spinner.addClass('is-active');

            // Send test notification
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'fr_test_notification',
                    nonce: wp_fr_notifications.nonce,
                    event_type: eventType
                },
                success: function(response) {
                    if (response.success) {
                        FederalRegisterNotifications.showMessage(
                            response.data.message,
                            'success'
                        );
                    } else {
                        FederalRegisterNotifications.showMessage(
                            response.data.message,
                            'error'
                        );
                    }
                },
                error: function(xhr, status, error) {
                    FederalRegisterNotifications.showMessage(
                        wp_fr_notifications.error_message,
                        'error'
                    );
                },
                complete: function() {
                    // Re-enable button and hide spinner
                    $button.prop('disabled', false);
                    $spinner.removeClass('is-active');
                }
            });
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
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        FederalRegisterNotifications.init();
    });

})(jQuery);
