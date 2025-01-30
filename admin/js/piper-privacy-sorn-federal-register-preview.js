/**
 * Federal Register Preview Scripts
 */
(function($) {
    'use strict';

    // Preview functionality
    const FederalRegisterPreview = {
        init: function() {
            this.bindEvents();
            this.initializeTooltips();
            this.initializeSplitView();
        },

        bindEvents: function() {
            $('#fr-preview-button').on('click', this.generatePreview);
            $('#fr-validate-button').on('click', this.validateSorn);
            $('#fr-refresh-preview').on('click', this.refreshPreview);
            $('#fr-section-nav').on('change', this.navigateToSection);
            $('.fr-copy-button').on('click', this.copyContent);
            $('.fr-print-button').on('click', this.printPreview);
            $('.fr-toggle-diff').on('click', this.toggleDiffView);
        },

        initializeTooltips: function() {
            $('.fr-preview-help').tooltip({
                position: { my: 'left+10 center', at: 'right center' }
            });
        },

        initializeSplitView: function() {
            // Initialize split view if container exists
            const $container = $('.fr-preview-enhanced');
            if ($container.length) {
                // Store initial content for diff comparison
                this.originalContent = $('.fr-panel-source .fr-panel-content').html();
            }
        },

        generatePreview: function(e) {
            e.preventDefault();
            const sornId = $('#post_ID').val();

            FederalRegisterPreview.showLoading();

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'fr_preview_sorn',
                    nonce: $('#fr_preview_nonce').val(),
                    sorn_id: sornId,
                    format: 'enhanced'
                },
                success: function(response) {
                    if (response.success) {
                        FederalRegisterPreview.displayEnhancedPreview(response.data);
                    } else {
                        FederalRegisterPreview.showError(response.data);
                    }
                },
                error: function(xhr, status, error) {
                    FederalRegisterPreview.showError(error);
                },
                complete: function() {
                    FederalRegisterPreview.hideLoading();
                }
            });
        },

        displayEnhancedPreview: function(data) {
            // Update source panel
            $('.fr-panel-source .fr-panel-content').html(data.source_html);
            
            // Update preview panel
            $('.fr-panel-preview .fr-panel-content').html(data.preview_html);

            // Update section navigation
            this.updateSectionNav(data.sections);

            // Initialize diff view if enabled
            if ($('.fr-toggle-diff').hasClass('active')) {
                this.showDiffView();
            }

            // Update validation status
            if (data.validation) {
                this.displayValidation(data.validation);
            }

            // Store content for future diff comparison
            this.originalContent = data.source_html;
        },

        updateSectionNav: function(sections) {
            const $select = $('#fr-section-nav');
            $select.empty();
            
            $select.append($('<option>', {
                value: '',
                text: 'Jump to section...'
            }));

            sections.forEach(section => {
                $select.append($('<option>', {
                    value: section.id,
                    text: section.title
                }));
            });
        },

        navigateToSection: function() {
            const sectionId = $(this).val();
            if (!sectionId) return;

            const $section = $('#' + sectionId);
            if ($section.length) {
                $('.fr-panel-content').animate({
                    scrollTop: $section.position().top - 20
                }, 500);
            }
        },

        showDiffView: function() {
            const currentContent = $('.fr-panel-source .fr-panel-content').html();
            const diff = JsDiff.diffChars(this.originalContent, currentContent);
            
            let html = '';
            diff.forEach(part => {
                const className = part.added ? 'fr-diff-add' :
                                part.removed ? 'fr-diff-remove' :
                                '';
                
                if (className) {
                    html += `<span class="${className}">${part.value}</span>`;
                } else {
                    html += part.value;
                }
            });

            $('.fr-panel-source .fr-panel-content').html(html);
        },

        toggleDiffView: function(e) {
            e.preventDefault();
            const $button = $(this);
            
            if ($button.hasClass('active')) {
                // Restore original view
                $('.fr-panel-source .fr-panel-content').html(FederalRegisterPreview.originalContent);
                $button.removeClass('active');
            } else {
                // Show diff view
                FederalRegisterPreview.showDiffView();
                $button.addClass('active');
            }
        },

        copyContent: function(e) {
            e.preventDefault();
            const $panel = $(this).closest('.fr-panel');
            const text = $panel.find('.fr-panel-content').text();

            navigator.clipboard.writeText(text).then(() => {
                FederalRegisterPreview.showMessage('Content copied to clipboard');
            }).catch(() => {
                FederalRegisterPreview.showError('Failed to copy content');
            });
        },

        printPreview: function(e) {
            e.preventDefault();
            window.print();
        },

        validateSorn: function(e) {
            e.preventDefault();
            const sornId = $('#post_ID').val();

            FederalRegisterPreview.showLoading();

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'fr_validate_sorn',
                    nonce: $('#fr_validate_nonce').val(),
                    sorn_id: sornId
                },
                success: function(response) {
                    if (response.success) {
                        FederalRegisterPreview.displayValidation(response.data);
                    } else {
                        FederalRegisterPreview.showError(response.data);
                    }
                },
                error: function(xhr, status, error) {
                    FederalRegisterPreview.showError(error);
                },
                complete: function() {
                    FederalRegisterPreview.hideLoading();
                }
            });
        },

        displayValidation: function(validation) {
            const $validation = $('#fr-preview-validation');
            let html = '';

            if (validation.errors.length) {
                html += this.formatValidationSection('Errors', validation.errors, 'error');
            }
            if (validation.warnings.length) {
                html += this.formatValidationSection('Warnings', validation.warnings, 'warning');
            }
            if (validation.suggestions.length) {
                html += this.formatValidationSection('Suggestions', validation.suggestions, 'info');
            }

            $validation.html(html);
            this.highlightValidationIssues(validation);
        },

        formatValidationSection: function(title, items, type) {
            if (!items.length) return '';

            return `
                <div class="fr-preview-${type}s">
                    <h4>${title}</h4>
                    <ul>
                        ${items.map(item => `<li>${item}</li>`).join('')}
                    </ul>
                </div>
            `;
        },

        highlightValidationIssues: function(validation) {
            // Remove existing highlights
            $('.fr-panel-content').find('.fr-highlight').removeClass('fr-highlight-error fr-highlight-warning');

            // Add new highlights based on validation results
            if (validation.errors) {
                validation.errors.forEach(error => {
                    this.highlightContent(error, 'error');
                });
            }
            if (validation.warnings) {
                validation.warnings.forEach(warning => {
                    this.highlightContent(warning, 'warning');
                });
            }
        },

        highlightContent: function(text, type) {
            $('.fr-panel-content').each(function() {
                const $content = $(this);
                const html = $content.html();
                const regex = new RegExp(text.replace(/[-[\]{}()*+?.,\\^$|#\s]/g, '\\$&'), 'gi');
                
                $content.html(html.replace(regex, match => 
                    `<span class="fr-highlight fr-highlight-${type}">${match}</span>`
                ));
            });
        },

        showLoading: function() {
            $('.fr-panel-loading').show();
        },

        hideLoading: function() {
            $('.fr-panel-loading').hide();
        },

        showError: function(message) {
            const $error = $('#fr-preview-error');
            $error.text(message).show();
            setTimeout(() => $error.fadeOut(), 5000);
        },

        showMessage: function(message) {
            const $message = $('#fr-preview-message');
            $message.text(message).show();
            setTimeout(() => $message.fadeOut(), 3000);
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        FederalRegisterPreview.init();
    });

})(jQuery);
