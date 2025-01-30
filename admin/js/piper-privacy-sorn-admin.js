(function($) {
    'use strict';

    $(document).ready(function() {
        // Modal handling
        const modal = $('#data-source-modal');
        const closeBtn = $('.close');
        
        $('#create-data-source').on('click', function() {
            modal.show();
        });
        
        closeBtn.on('click', function() {
            modal.hide();
        });
        
        $(window).on('click', function(event) {
            if (event.target === modal[0]) {
                modal.hide();
            }
        });

        // Source type handling
        $('#source-type').on('change', function() {
            const type = $(this).val();
            $('.source-section').hide();
            
            switch(type) {
                case 'file':
                    $('#file-upload-section').show();
                    break;
                case 'url':
                    $('#url-section').show();
                    break;
                case 'qa':
                    $('#qa-section').show();
                    break;
            }
        });

        // Add Q&A pair handling
        let qaCount = 1;
        $('#add-qa-pair').on('click', function() {
            const newPair = `
                <div class="qa-pair">
                    <p>
                        <label>Question:</label>
                        <input type="text" name="qa_pairs[${qaCount}][question]">
                    </p>
                    <p>
                        <label>Answer:</label>
                        <textarea name="qa_pairs[${qaCount}][answer]"></textarea>
                    </p>
                    <button type="button" class="button remove-qa-pair">Remove</button>
                </div>
            `;
            $('#qa-pairs').append(newPair);
            qaCount++;
        });

        // Remove Q&A pair handling
        $(document).on('click', '.remove-qa-pair', function() {
            $(this).closest('.qa-pair').remove();
        });

        // Form submission handling
        $('#create-data-source-form').on('submit', function(e) {
            e.preventDefault();
            
            const form = $(this);
            const submitButton = form.find('input[type="submit"]');
            submitButton.prop('disabled', true);
            
            // Create FormData object to handle file uploads
            const formData = new FormData(this);
            formData.append('action', 'create_data_source');
            
            // Handle tags
            const tags = $('#source-tags').val().split(',').map(tag => tag.trim()).filter(Boolean);
            formData.set('tags', JSON.stringify(tags));
            
            // Handle Q&A pairs if present
            if ($('#source-type').val() === 'qa') {
                const qaPairs = [];
                $('.qa-pair').each(function() {
                    const question = $(this).find('input[name*="[question]"]').val();
                    const answer = $(this).find('textarea[name*="[answer]"]').val();
                    if (question && answer) {
                        qaPairs.push({ question, answer });
                    }
                });
                formData.set('qa_pairs', JSON.stringify(qaPairs));
            }

            $.ajax({
                url: piperPrivacySornAdmin.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        alert(response.data.message);
                        modal.hide();
                        form[0].reset();
                        loadDataSources(); // Refresh the list
                    } else {
                        alert(response.data.message || piperPrivacySornAdmin.i18n.error);
                    }
                },
                error: function() {
                    alert(piperPrivacySornAdmin.i18n.error);
                },
                complete: function() {
                    submitButton.prop('disabled', false);
                }
            });
        });

        // Load data sources
        function loadDataSources() {
            const container = $('#data-sources-container');
            container.html('<div class="loading"></div>');
            
            $.ajax({
                url: piperPrivacySornAdmin.ajax_url,
                type: 'POST',
                data: {
                    action: 'get_data_sources',
                    nonce: piperPrivacySornAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        displayDataSources(response.data);
                    } else {
                        container.html(`<div class="notice notice-error"><p>${response.data.message || piperPrivacySornAdmin.i18n.error}</p></div>`);
                    }
                },
                error: function() {
                    container.html(`<div class="notice notice-error"><p>${piperPrivacySornAdmin.i18n.error}</p></div>`);
                }
            });
        }

        function displayDataSources(dataSources) {
            const container = $('#data-sources-container');
            if (!dataSources || !dataSources.length) {
                container.html('<div class="notice notice-info"><p>No data sources found.</p></div>');
                return;
            }

            let html = '<div class="data-sources-list">';
            dataSources.forEach(source => {
                html += `
                    <div class="data-source-item" data-id="${source.uuid}">
                        <div class="data-source-info">
                            <h3>${escapeHtml(source.name)}</h3>
                            <p>${escapeHtml(source.description || '')}</p>
                            <div class="data-source-tags">
                                ${source.tags.map(tag => `<span class="data-source-tag">${escapeHtml(tag)}</span>`).join('')}
                            </div>
                        </div>
                        <div class="data-source-actions">
                            <button type="button" class="button view-data-source">View</button>
                            <button type="button" class="button button-link-delete delete-data-source">Delete</button>
                        </div>
                    </div>
                `;
            });
            html += '</div>';
            container.html(html);
        }

        // Helper function to escape HTML
        function escapeHtml(unsafe) {
            return unsafe
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }

        // Initial load
        if ($('#data-sources-container').length) {
            loadDataSources();
        }

        // Handle data source deletion
        $(document).on('click', '.delete-data-source', function() {
            if (!confirm(piperPrivacySornAdmin.i18n.confirm_delete)) {
                return;
            }

            const button = $(this);
            const sourceId = button.closest('.data-source-item').data('id');
            button.prop('disabled', true);

            $.ajax({
                url: piperPrivacySornAdmin.ajax_url,
                type: 'POST',
                data: {
                    action: 'delete_data_source',
                    nonce: piperPrivacySornAdmin.nonce,
                    source_id: sourceId
                },
                success: function(response) {
                    if (response.success) {
                        button.closest('.data-source-item').fadeOut(function() {
                            $(this).remove();
                            if (!$('.data-source-item').length) {
                                loadDataSources(); // Refresh if no items left
                            }
                        });
                    } else {
                        alert(response.data.message || piperPrivacySornAdmin.i18n.error);
                        button.prop('disabled', false);
                    }
                },
                error: function() {
                    alert(piperPrivacySornAdmin.i18n.error);
                    button.prop('disabled', false);
                }
            });
        });

        // Handle data source viewing
        $(document).on('click', '.view-data-source', function() {
            const sourceId = $(this).closest('.data-source-item').data('id');
            // Implement view functionality
        });
    });
})(jQuery);
