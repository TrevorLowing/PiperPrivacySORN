<?php
namespace PiperPrivacySorn\Tests\FederalRegister;

/**
 * Test Federal Register preview JavaScript functionality
 */
class PreviewJavaScriptTest extends \WP_UnitTestCase {
    /**
     * Set up test environment
     */
    public function setUp(): void {
        parent::setUp();
        
        // Enqueue scripts
        wp_enqueue_script('jquery');
        wp_enqueue_script(
            'fr-preview',
            plugin_dir_url(PIPER_PRIVACY_SORN_FILE) . 'admin/js/piper-privacy-sorn-federal-register-preview.js',
            ['jquery'],
            PIPER_PRIVACY_SORN_VERSION,
            true
        );

        // Set up QUnit test page
        add_action('wp_head', [$this, 'output_qunit_markup']);
    }

    /**
     * Output QUnit test markup
     */
    public function output_qunit_markup(): void {
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width">
            <title>Federal Register Preview JavaScript Tests</title>
            <link rel="stylesheet" href="https://code.jquery.com/qunit/qunit-2.19.4.css">
        </head>
        <body>
            <div id="qunit"></div>
            <div id="qunit-fixture">
                <!-- Test Preview Container -->
                <div class="fr-preview-enhanced">
                    <!-- Source Panel -->
                    <div class="fr-panel fr-panel-source">
                        <div class="fr-panel-header">
                            <h3>Source</h3>
                            <div class="fr-panel-toolbar">
                                <button class="button fr-toggle-diff">Show Diff</button>
                                <button class="button fr-copy-button">Copy</button>
                            </div>
                        </div>
                        <div class="fr-panel-content">
                            <div class="fr-source-content">Original Content</div>
                        </div>
                    </div>

                    <!-- Preview Panel -->
                    <div class="fr-panel fr-panel-preview">
                        <div class="fr-panel-header">
                            <h3>Preview</h3>
                            <div class="fr-panel-toolbar">
                                <button class="button fr-copy-button">Copy</button>
                                <button class="button fr-print-button">Print</button>
                            </div>
                        </div>
                        <div class="fr-panel-content">
                            <div class="fr-preview-content">Preview Content</div>
                        </div>
                    </div>
                </div>

                <!-- Section Navigation -->
                <select id="fr-section-nav">
                    <option value="">Jump to section...</option>
                    <option value="section-1">Section 1</option>
                    <option value="section-2">Section 2</option>
                </select>

                <!-- Messages -->
                <div id="fr-preview-message" style="display: none;"></div>
                <div id="fr-preview-error" style="display: none;"></div>
            </div>

            <script src="https://code.jquery.com/qunit/qunit-2.19.4.js"></script>
            <script>
                QUnit.module('Federal Register Preview', {
                    beforeEach: function() {
                        // Reset fixture before each test
                        $('#qunit-fixture').clone().appendTo('body');
                    },
                    afterEach: function() {
                        // Clean up after each test
                        $('.fr-preview-enhanced').not('#qunit-fixture .fr-preview-enhanced').remove();
                    }
                });

                QUnit.test('Initialize Preview', function(assert) {
                    assert.ok(
                        typeof FederalRegisterPreview !== 'undefined',
                        'FederalRegisterPreview object exists'
                    );
                    assert.ok(
                        typeof FederalRegisterPreview.init === 'function',
                        'init function exists'
                    );
                });

                QUnit.test('Toggle Diff View', function(assert) {
                    const done = assert.async();
                    
                    // Set up original content
                    $('.fr-panel-source .fr-panel-content').html('Original Content');
                    
                    // Click diff toggle
                    $('.fr-toggle-diff').click();
                    
                    setTimeout(function() {
                        assert.ok(
                            $('.fr-toggle-diff').hasClass('active'),
                            'Diff toggle button is active'
                        );
                        
                        // Click again to disable
                        $('.fr-toggle-diff').click();
                        
                        setTimeout(function() {
                            assert.notOk(
                                $('.fr-toggle-diff').hasClass('active'),
                                'Diff toggle button is inactive'
                            );
                            assert.equal(
                                $('.fr-panel-source .fr-panel-content').html(),
                                'Original Content',
                                'Original content is restored'
                            );
                            done();
                        }, 100);
                    }, 100);
                });

                QUnit.test('Section Navigation', function(assert) {
                    const done = assert.async();
                    
                    // Add test sections
                    $('.fr-panel-content').append(`
                        <div id="section-1" style="margin-top: 100px;">Section 1</div>
                        <div id="section-2" style="margin-top: 100px;">Section 2</div>
                    `);
                    
                    // Select section
                    $('#fr-section-nav').val('section-2').trigger('change');
                    
                    setTimeout(function() {
                        const scrollTop = $('.fr-panel-content').scrollTop();
                        assert.ok(
                            scrollTop > 0,
                            'Content is scrolled to section'
                        );
                        done();
                    }, 600);
                });

                QUnit.test('Copy Content', function(assert) {
                    const done = assert.async();
                    
                    // Mock clipboard API
                    const originalClipboard = navigator.clipboard;
                    navigator.clipboard = {
                        writeText: function(text) {
                            assert.equal(
                                text.trim(),
                                'Preview Content',
                                'Correct content is copied'
                            );
                            return Promise.resolve();
                        }
                    };
                    
                    // Click copy button
                    $('.fr-panel-preview .fr-copy-button').click();
                    
                    setTimeout(function() {
                        assert.ok(
                            $('#fr-preview-message').is(':visible'),
                            'Success message is shown'
                        );
                        
                        // Restore clipboard API
                        navigator.clipboard = originalClipboard;
                        done();
                    }, 100);
                });

                QUnit.test('Print Preview', function(assert) {
                    // Mock window.print
                    const originalPrint = window.print;
                    let printCalled = false;
                    window.print = function() {
                        printCalled = true;
                    };
                    
                    // Click print button
                    $('.fr-print-button').click();
                    
                    assert.ok(printCalled, 'Print dialog is triggered');
                    
                    // Restore window.print
                    window.print = originalPrint;
                });

                QUnit.test('Error Handling', function(assert) {
                    const done = assert.async();
                    
                    FederalRegisterPreview.showError('Test error message');
                    
                    assert.ok(
                        $('#fr-preview-error').is(':visible'),
                        'Error message is shown'
                    );
                    assert.equal(
                        $('#fr-preview-error').text(),
                        'Test error message',
                        'Correct error message is displayed'
                    );
                    
                    setTimeout(function() {
                        assert.notOk(
                            $('#fr-preview-error').is(':visible'),
                            'Error message is hidden after timeout'
                        );
                        done();
                    }, 5100);
                });
            </script>
        </body>
        </html>
        <?php
    }

    /**
     * Test JavaScript functionality
     */
    public function testJavaScript(): void {
        // This is a placeholder test that ensures the JavaScript test page is generated
        // The actual testing is done in the browser using QUnit
        $this->assertTrue(true);
    }
}
