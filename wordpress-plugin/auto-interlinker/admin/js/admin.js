/**
 * Auto Interlinker Admin JavaScript
 *
 * @package AutoInterlinker
 */

/* global autoInterlinker, jQuery */

(function ($) {
    'use strict';

    var AI = autoInterlinker;

    // =========================================================
    // Utility helpers
    // =========================================================

    /**
     * Set status message on an element.
     *
     * @param {jQuery} $el     Status element.
     * @param {string} message Message text.
     * @param {string} type    'success' | 'error' | ''
     */
    function setStatus($el, message, type) {
        $el.text(message)
            .removeClass('success error')
            .addClass(type || '');
    }

    /**
     * Generic AJAX helper.
     *
     * @param {string}   action   WP AJAX action.
     * @param {Object}   data     Extra POST data.
     * @param {Function} success  Success callback (response.data).
     * @param {Function} error    Error callback (message string).
     */
    function ajaxRequest(action, data, success, error) {
        $.post(
            AI.ajaxUrl,
            $.extend({ action: action, nonce: AI.nonce }, data),
            function (response) {
                if (response.success) {
                    success(response.data);
                } else {
                    var msg = (response.data && response.data.message) ? response.data.message : AI.strings.error;
                    if (error) error(msg);
                }
            }
        ).fail(function () {
            if (error) error(AI.strings.error);
        });
    }

    // =========================================================
    // Reprocess All Posts
    // =========================================================

    $('#ai-reprocess-all').on('click', function () {
        if (!confirm(AI.strings.confirmReprocess)) {
            return;
        }

        var $btn    = $(this);
        var $status = $('#ai-reprocess-status');

        $btn.prop('disabled', true).text(AI.strings.processing);
        setStatus($status, AI.strings.processing, '');

        ajaxRequest(
            'ai_reprocess_all',
            {},
            function (data) {
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-update"></span> Reprocess All Posts');
                setStatus($status, data.message, 'success');
            },
            function (msg) {
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-update"></span> Reprocess All Posts');
                setStatus($status, msg, 'error');
            }
        );
    });

    // =========================================================
    // Strip All Links
    // =========================================================

    $('#ai-strip-all-links').on('click', function () {
        if (!confirm(AI.strings.confirmStrip)) {
            return;
        }

        var $btn    = $(this);
        var $status = $('#ai-strip-status');

        $btn.prop('disabled', true).text(AI.strings.processing);
        setStatus($status, AI.strings.processing, '');

        ajaxRequest(
            'ai_strip_all_links',
            {},
            function (data) {
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-trash"></span> Remove All Interlinks');
                setStatus($status, data.message, 'success');
            },
            function (msg) {
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-trash"></span> Remove All Interlinks');
                setStatus($status, msg, 'error');
            }
        );
    });

    // =========================================================
    // Process Single Post
    // =========================================================

    $('#ai-process-single').on('click', function () {
        var postId  = $('#ai-single-post-id').val();
        var $status = $('#ai-single-status');

        if (!postId || parseInt(postId, 10) < 1) {
            setStatus($status, 'Please enter a valid post ID.', 'error');
            return;
        }

        var $btn = $(this);
        $btn.prop('disabled', true).text(AI.strings.processing);
        setStatus($status, AI.strings.processing, '');

        ajaxRequest(
            'ai_process_single',
            { post_id: postId },
            function (data) {
                $btn.prop('disabled', false).text('Process Post');
                setStatus($status, data.message, 'success');
            },
            function (msg) {
                $btn.prop('disabled', false).text('Process Post');
                setStatus($status, msg, 'error');
            }
        );
    });

    // =========================================================
    // Add Custom Keyword
    // =========================================================

    $('#ai-add-keyword').on('click', function () {
        var postId  = $('#ai-kw-post-id').val();
        var keyword = $('#ai-kw-keyword').val().trim();
        var $status = $('#ai-kw-status');

        if (!postId || parseInt(postId, 10) < 1) {
            setStatus($status, 'Please enter a valid post ID.', 'error');
            return;
        }

        if (!keyword) {
            setStatus($status, 'Please enter a keyword.', 'error');
            return;
        }

        var $btn = $(this);
        $btn.prop('disabled', true).text(AI.strings.processing);
        setStatus($status, AI.strings.processing, '');

        ajaxRequest(
            'ai_add_keyword',
            { post_id: postId, keyword: keyword },
            function (data) {
                $btn.prop('disabled', false).text('Add Keyword');
                setStatus($status, data.message, 'success');
                $('#ai-kw-keyword').val('');
                // Reload page to show new keyword.
                setTimeout(function () { location.reload(); }, 1000);
            },
            function (msg) {
                $btn.prop('disabled', false).text('Add Keyword');
                setStatus($status, msg, 'error');
            }
        );
    });

    // =========================================================
    // Delete Keyword
    // =========================================================

    $(document).on('click', '.ai-delete-keyword', function () {
        var $btn = $(this);
        var id   = $btn.data('id');
        var $row = $('#ai-kw-row-' + id);

        if (!confirm('Delete this keyword?')) {
            return;
        }

        $btn.prop('disabled', true).text('Deleting...');

        ajaxRequest(
            'ai_delete_keyword',
            { id: id },
            function () {
                $row.fadeOut(300, function () { $row.remove(); });
            },
            function (msg) {
                $btn.prop('disabled', false).text('Delete');
                alert(msg);
            }
        );
    });

    // =========================================================
    // Clear Logs
    // =========================================================

    $('#ai-clear-logs').on('click', function () {
        if (!confirm(AI.strings.confirmClear)) {
            return;
        }

        var $btn = $(this);
        $btn.prop('disabled', true).text(AI.strings.processing);

        ajaxRequest(
            'ai_clear_logs',
            {},
            function (data) {
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-trash"></span> Clear All Logs');
                // Remove table rows.
                $('.ai-table tbody tr').fadeOut(300, function () { $(this).remove(); });
                // Show empty message.
                setTimeout(function () {
                    if ($('.ai-table tbody tr').length === 0) {
                        $('.ai-table').replaceWith('<p class="ai-empty">' + data.message + '</p>');
                    }
                }, 400);
            },
            function (msg) {
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-trash"></span> Clear All Logs');
                alert(msg);
            }
        );
    });

    // =========================================================
    // Accordion
    // =========================================================

    $(document).on('click', '.ai-accordion-header', function () {
        var $item = $(this).closest('.ai-accordion-item');
        var $body = $item.find('.ai-accordion-body');

        $item.toggleClass('open');
        $body.slideToggle(200);
    });

    // =========================================================
    // Allow Enter key in single post ID field
    // =========================================================

    $('#ai-single-post-id').on('keypress', function (e) {
        if (e.which === 13) {
            $('#ai-process-single').trigger('click');
        }
    });

    $('#ai-kw-keyword').on('keypress', function (e) {
        if (e.which === 13) {
            $('#ai-add-keyword').trigger('click');
        }
    });

}(jQuery));
