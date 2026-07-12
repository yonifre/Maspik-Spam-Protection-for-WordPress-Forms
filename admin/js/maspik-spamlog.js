jQuery(document).ready(function ($) {

    // ─── State ───────────────────────────────────────────────────────────────
    var state = {
        page:     1,
        perPage:  200,
        sortCol:  'id',
        sortDir:  'DESC',
        filters:  { type: '', ip: '', country: '', source: '', dateFrom: '', dateTo: '' },
        total:    0,
        totalPages: 1
    };

    // Bootstrap from PHP-rendered initial values.
    if (typeof maspikAdmin !== 'undefined' && maspikAdmin.initialState) {
        var init = maspikAdmin.initialState;
        state.sortCol    = init.sortCol    || 'id';
        state.sortDir    = init.sortDir    || 'DESC';
        state.perPage    = init.perPage    || 200;
        state.total      = init.total      || 0;
        state.totalPages = init.totalPages || 1;
    }

    var i18n = (typeof maspikAdmin !== 'undefined' && maspikAdmin.i18n) ? maspikAdmin.i18n : {};

    // ─── Toast notification ───────────────────────────────────────────────────
    if (!$('#maspik-toast-wrap').length) {
        $('body').append('<div id="maspik-toast-wrap" class="maspik-toast-wrap"></div>');
    }
    function maspikToast(message, type) {
        type = type || 'success';
        var icons = { success: 'yes-alt', error: 'dismiss', warning: 'warning', info: 'info' };
        var $t = $('<div class="maspik-toast maspik-toast--' + type + '">' +
            '<span class="dashicons dashicons-' + (icons[type] || 'info') + '"></span>' +
            '<span>' + message + '</span></div>');
        $('#maspik-toast-wrap').append($t);
        setTimeout(function () { $t.addClass('maspik-toast--visible'); }, 10);
        setTimeout(function () {
            $t.removeClass('maspik-toast--visible');
            setTimeout(function () { $t.remove(); }, 300);
        }, 4000);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────
    function sprintfI18n(tpl, a, b, c) {
        return tpl.replace('%1$d', a).replace('%2$d', b).replace('%3$d', c);
    }

    function debounce(fn, delay) {
        var timer;
        return function () {
            var args = arguments, ctx = this;
            clearTimeout(timer);
            timer = setTimeout(function () { fn.apply(ctx, args); }, delay);
        };
    }

    function applySpamValueMarkup() {
        $('#maspik-log-tbody .spam-value-text').each(function () {
            var text = $(this).html();
            text = text.replace(/\*!(.*?)!\*/g, '<u>$1</u>');
            text = text.replace(/\*(.*?)\*/g, '<u>$1</u>');
            $(this).html(text);
        });
    }

    function updatePaginationUI() {
        var $prev  = $('#maspik-prev-page, #maspik-first-page');
        var $next  = $('#maspik-next-page, #maspik-last-page');
        var $input = $('#maspik-page-input');

        $prev.prop('disabled', state.page <= 1);
        $next.prop('disabled', state.page >= state.totalPages || state.totalPages <= 1);
        $input.val(state.page).attr('max', Math.max(1, state.totalPages));
        $('#maspik-total-pages').text(Math.max(1, state.totalPages));

        var end = Math.min(state.page * state.perPage, state.total);
        var start = state.total === 0 ? 0 : (state.page - 1) * state.perPage + 1;
        if (state.perPage === -1) { start = state.total > 0 ? 1 : 0; end = state.total; }

        var tpl = i18n.showing || 'Showing %1$d–%2$d of %3$d entries';
        $('#maspik-pagination-count').text(sprintfI18n(tpl, start, end, state.total));
    }

    // ─── AJAX load ───────────────────────────────────────────────────────────
    function loadPage(resetPage) {
        if (resetPage) { state.page = 1; }

        var $loading = $('#maspik-log-loading');
        var $wrap    = $('#maspik-log-table-wrap');

        $loading.show();
        $wrap.css('opacity', '0.4');

        $.ajax({
            url:  maspikAdmin.ajaxurl,
            type: 'POST',
            data: {
                action:          'maspik_get_spam_log',
                nonce:           maspikAdmin.spamlogNonce,
                page:            state.page,
                per_page:        state.perPage,
                sort_col:        state.sortCol,
                sort_dir:        state.sortDir,
                filter_type:     state.filters.type,
                filter_ip:       state.filters.ip,
                filter_country:  state.filters.country,
                filter_source:   state.filters.source,
                filter_date_from: state.filters.dateFrom,
                filter_date_to:  state.filters.dateTo
            },
            success: function (response) {
                if (response && response.success) {
                    var d = response.data;
                    state.total      = d.total;
                    state.totalPages = d.total_pages;
                    state.page       = d.page;

                    $('#maspik-log-tbody').html(d.rows);
                    applySpamValueMarkup();
                    updatePaginationUI();
                } else {
                    console.error('Maspik log error', response);
                }
            },
            error: function (xhr, status, error) {
                console.error('Maspik AJAX error', status, error);
            },
            complete: function () {
                $loading.hide();
                $wrap.css('opacity', '1');
            }
        });
    }

    // ─── Filters ─────────────────────────────────────────────────────────────
    var debouncedLoad = debounce(function () { loadPage(true); }, 400);

    function markHasValue($el) {
        $el.toggleClass('has-value', $el.val() !== '');
    }

    $('#maspik-filter-type').on('change', function () {
        state.filters.type = $(this).val();
        markHasValue($(this));
        loadPage(true);
    });
    $('#maspik-filter-ip').on('input', function () {
        state.filters.ip = $(this).val();
        markHasValue($(this));
        debouncedLoad();
    });
    $('#maspik-filter-country').on('input', function () {
        state.filters.country = $(this).val();
        markHasValue($(this));
        debouncedLoad();
    });
    $('#maspik-filter-source').on('input', function () {
        state.filters.source = $(this).val();
        markHasValue($(this));
        debouncedLoad();
    });
    $('#maspik-filter-date-from').on('change', function () {
        state.filters.dateFrom = $(this).val();
        markHasValue($(this));
        loadPage(true);
    });
    $('#maspik-filter-date-to').on('change', function () {
        state.filters.dateTo = $(this).val();
        markHasValue($(this));
        loadPage(true);
    });

    $('#maspik-clear-filters').on('click', function () {
        state.filters = { type: '', ip: '', country: '', source: '', dateFrom: '', dateTo: '' };
        $('#maspik-filter-type').val('');
        $('#maspik-filter-ip, #maspik-filter-country, #maspik-filter-source').val('');
        $('#maspik-filter-date-from, #maspik-filter-date-to').val('');
        $('.maspik-filter-input').removeClass('has-value');
        loadPage(true);
    });

    // ─── Per-page selector ───────────────────────────────────────────────────
    $('#maspik-per-page').on('change', function () {
        state.perPage = parseInt($(this).val(), 10);
        loadPage(true);
    });

    // ─── Sortable column headers ─────────────────────────────────────────────
    $(document).on('click', '.maspik-log-table th.sortable', function () {
        var col = $(this).data('col');
        if (state.sortCol === col) {
            state.sortDir = (state.sortDir === 'DESC') ? 'ASC' : 'DESC';
        } else {
            state.sortCol = col;
            state.sortDir = 'DESC';
        }

        // Update header UI.
        $('.maspik-log-table th.sortable').removeClass('sort-active sort-asc sort-desc');
        $('.maspik-log-table th.sortable .sort-indicator').text('');
        $(this).addClass('sort-active ' + (state.sortDir === 'ASC' ? 'sort-asc' : 'sort-desc'));
        $(this).find('.sort-indicator').text(state.sortDir === 'ASC' ? '▲' : '▼');

        loadPage(true);
    });

    // ─── Pagination ───────────────────────────────────────────────────────────
    $('#maspik-prev-page').on('click', function () {
        if (state.page > 1) { state.page--; loadPage(false); }
    });
    $('#maspik-next-page').on('click', function () {
        if (state.page < state.totalPages) { state.page++; loadPage(false); }
    });
    $('#maspik-first-page').on('click', function () {
        if (state.page !== 1) { state.page = 1; loadPage(false); }
    });
    $('#maspik-last-page').on('click', function () {
        if (state.page !== state.totalPages) { state.page = state.totalPages; loadPage(false); }
    });
    $('#maspik-page-input').on('change', function () {
        var p = parseInt($(this).val(), 10);
        if (!isNaN(p) && p >= 1 && p <= state.totalPages) {
            state.page = p;
            loadPage(false);
        } else {
            $(this).val(state.page);
        }
    });

    // ─── Expand All ───────────────────────────────────────────────────────────
    var allExpanded = false;
    $('#expand-all').on('click', function () {
        allExpanded = !allExpanded;
        var labelShow = i18n.showDetails || 'Show Details';
        var labelHide = i18n.hideDetails || 'Hide Details';
        var expandLabel = i18n.expandAll  || 'Expand All';
        var collapseLabel = i18n.collapseAll || 'Collapse All';

        $('#maspik-log-tbody .details-toggle-btn').each(function () {
            var $btn   = $(this);
            var $panel = $btn.closest('.value-content-container').find('.details-panel');
            if (allExpanded) {
                $btn.addClass('active').attr('aria-expanded', 'true');
                $panel.addClass('active');
                $btn.find('.details-text').text(labelHide);
            } else {
                $btn.removeClass('active').attr('aria-expanded', 'false');
                $panel.removeClass('active');
                $btn.find('.details-text').text(labelShow);
            }
        });
        $(this).text(allExpanded ? collapseLabel : expandLabel);
    });

    // ─── Details toggle (delegated — works for dynamically loaded rows) ───────
    $(document).on('click', '.details-toggle-btn', function (e) {
        e.preventDefault();
        var $btn    = $(this);
        var $panel  = $btn.closest('.value-content-container').find('.details-panel');
        var isOpen  = $btn.hasClass('active');
        var labelShow = i18n.showDetails || 'Show Details';
        var labelHide = i18n.hideDetails || 'Hide Details';

        $btn.toggleClass('active').attr('aria-expanded', isOpen ? 'false' : 'true');
        $panel.toggleClass('active');
        $btn.find('.details-text').text(isOpen ? labelShow : labelHide);
    });

    // ─── Delete row (delegated) ───────────────────────────────────────────────
    var modal         = $('#confirmation-modal');
    var rowIdToDelete = null;

    $(document).on('click', '.spam-delete-button', function () {
        rowIdToDelete = $(this).data('row-id');
        modal.show();
    });

    function closeDeleteModal() {
        modal.hide();
        rowIdToDelete = null;
    }

    $('#confirm-delete').on('click', function () {
        if (!rowIdToDelete) { return; }

        $.ajax({
            url:  maspikAdmin.ajaxurl,
            type: 'POST',
            data: { action: 'maspik_delete_row', row_id: rowIdToDelete, nonce: maspikAdmin.nonce },
            beforeSend: function () {
                $('tr[class*="row-entries"]').filter(function () {
                    return $(this).find('.spam-delete-button').data('row-id') == rowIdToDelete;
                }).css('opacity', '0.5');
            },
            success: function (response) {
                if (response.success) {
                    $('tr[class*="row-entries"]').filter(function () {
                        return $(this).find('.spam-delete-button').data('row-id') == rowIdToDelete;
                    }).fadeOut(400, function () {
                        $(this).remove();
                        state.total = Math.max(0, state.total - 1);
                        updatePaginationUI();
                        if ($('.row-entries').length === 0) {
                            $('.log-warp').html("<div class='spam-empty-log'><h4>Empty log</h4></div>");
                        }
                    });
                } else {
                    maspikToast(response.data.message || (i18n.deleteFailed || 'Failed to delete row.'), 'error');
                    $('tr[class*="row-entries"]').filter(function () {
                        return $(this).find('.spam-delete-button').data('row-id') == rowIdToDelete;
                    }).css('opacity', '1');
                }
            },
            error: function (xhr, status, error) {
                console.error('Delete error:', status, error, xhr.responseText);
                maspikToast(i18n.serverError || 'Server error. Please try again.', 'error');
                $('tr[class*="row-entries"]').css('opacity', '1');
            },
            complete: closeDeleteModal
        });
    });

    $('#cancel-delete').on('click', closeDeleteModal);
    $(document).on('click', '#confirmation-modal .close-button', closeDeleteModal);
    $(window).on('click', function (e) {
        if ($(e.target).is(modal)) { closeDeleteModal(); }
    });

    // ─── Filter / Not-Spam modal ──────────────────────────────────────────────
    var fmodal          = $('#filter-delete-modal');
    var fpModal         = $('#false-positive-modal');
    var fpRowId         = null;
    var fpSpamValue     = null;
    var fpSpamType      = null;

    function closeFModal() { fmodal.hide(); }
    function closeFpModal() {
        fpModal.hide();
        fpModal.find('.fp-modal-header, .fp-modal-body, .fp-modal-actions').show();
        $('#fp-modal-feedback').hide();
    }

    function showFpFeedback(msg) {
        fpModal.find('.fp-modal-header, .fp-modal-body, .fp-modal-actions').hide();
        $('#fp-modal-feedback-text').text(msg);
        $('#fp-modal-feedback').show();
    }

    function markRowNotSpam(rowId) {
        $('[data-row-id="' + rowId + '"]').closest('tr').addClass('not-a-spam');
    }

    function sendNotSpamRequest(sendReport) {
        $.ajax({
            url:  maspikAdmin.ajaxurl,
            type: 'POST',
            data: {
                action:      'maspik_not_spam',
                nonce:       maspikAdmin.nonce,
                row_id:      fpRowId,
                spam_value:  fpSpamValue,
                spam_type:   fpSpamType || '',
                send_report: sendReport ? 1 : 0
            },
            success: function (response) {
                if (response && response.success) { markRowNotSpam(fpRowId); }
            }
        });
    }

    // Not Spam button — delegated so it works after AJAX loads new rows.
    $(document).on('click', '.not-spam-action', function (e) {
        e.preventDefault();
        e.stopPropagation();
        e.stopImmediatePropagation();

        fpRowId     = $(this).data('row-id');
        fpSpamValue = $(this).data('spam-value');
        fpSpamType  = $(this).data('spam-type') || $(this).attr('data-spam-type') || '';

        fpModal.show();
    });

    $('#fp-send-report').on('click', function () {
        showFpFeedback(i18n.reportSent || 'Report sent. Thank you!');
        sendNotSpamRequest(true);
        setTimeout(closeFpModal, 1500);
    });
    $('#fp-skip-report').on('click', function () {
        showFpFeedback(i18n.notSpamDone || 'Marked as not spam.');
        sendNotSpamRequest(false);
        setTimeout(closeFpModal, 1500);
    });
    $('#fp-cancel').on('click', closeFpModal);
    $(document).on('click', '#false-positive-modal .close-button', closeFpModal);
    $(window).on('click', function (e) {
        if ($(e.target).is(fpModal)) { closeFpModal(); }
    });

    // Filter-delete modal (for the "Not Spam" button on non-not-spam rows).
    $(document).on('click', '.row-entries:not(.not-a-spam) .filter-delete-button', function () {
        if ($(this).hasClass('not-spam-action')) { return; }

        var rowId     = $(this).data('row-id');
        var spamValue = $(this).data('spam-value');
        var spamType  = $(this).data('spam-type');

        if (spamType === 'Phone Format Field') {
            $('#filter-type').html("The phone number doesn't match any of the whitelisted formats. Would you like to remove all the existing whitelisted phone number formats?");
        } else if (spamValue == '1') {
            $('#filter-type').html('Do you want to disable the <pre>' + spamType + '</pre> option?');
        } else {
            $('#filter-type').html('Do you want to remove <pre>' + spamValue + '</pre> filter for <pre>' + spamType + '</pre>?');
        }

        rowIdToDelete = rowId;
        fmodal.show();
    });

    $('#confirm-del-filter').on('click', function () {
        closeFModal();
        $.ajax({
            url:  maspikAdmin.ajaxurl,
            type: 'POST',
            data: { action: 'delete_filter', row_id: rowIdToDelete, nonce: maspikAdmin.nonce },
            success: function (response) {
                if (response.success) {
                    maspikToast(i18n.filterDeleted || 'Filter deleted successfully!', 'success');
                    setTimeout(function () { location.reload(); }, 1200);
                } else {
                    maspikToast(i18n.filterDeleteFailed || 'This filter cannot be deleted automatically. It is either already deleted or comes from the Maspik API Dashboard — try deleting it manually.', 'warning');
                }
            },
            error: function () { maspikToast(i18n.serverError || 'Server error. Please try again.', 'error'); }
        });
    });

    $('#cancel-del-filter').on('click', closeFModal);
    $(document).on('click', '#filter-delete-modal .close-button', closeFModal);
    $(window).on('click', function (e) {
        if ($(e.target).is(fmodal)) { closeFModal(); }
    });

    // ─── Reset Log modal ──────────────────────────────────────────────────────
    var resetLogModal = $('#reset-log-modal');

    function openResetLogModal()  { resetLogModal.show(); }
    function closeResetLogModal() { resetLogModal.hide(); }

    $('#reset-log-btn').on('click', openResetLogModal);
    $('#cancel-reset-log').on('click', closeResetLogModal);
    $(document).on('click', '#reset-log-modal .close-button', closeResetLogModal);
    $(window).on('click', function (e) {
        if ($(e.target).is(resetLogModal)) { closeResetLogModal(); }
    });

    $('#confirm-reset-log').on('click', function () {
        closeResetLogModal();
        $('#reset-log-form').submit();
    });

    // ─── Initial markup pass ─────────────────────────────────────────────────
    applySpamValueMarkup();
    updatePaginationUI();

});
