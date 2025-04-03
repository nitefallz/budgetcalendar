// assets/js/app.js
$(document).ready(function() {

    /* ===== Inline Editing for Calendar Cells ===== */
    // Inline Editing for Calendar Cells (update only on Enter)
    $('[contenteditable="true"]')
        .on('focus', function() {
            $(this).addClass('editing');
            // Store original value (if not already stored)
            if (!$(this).data('original')) {
                $(this).data('original', $(this).text().trim());
            }
            // Reset flags on focus
            $(this).data('updateOnEnter', false);
            $(this).data('edited', false);
        })
        .on('keydown', function(e) {
            if (e.key === "Enter") {
                // User pressed Enter: set flag and trigger blur
                e.preventDefault();
                $(this).data('updateOnEnter', true);
                $(this).blur();
            } else {
                // If not already edited, clear cell on first key entry
                if (!$(this).data('edited')) {
                    $(this).text('');
                    $(this).data('edited', true);
                }
            }
        })
        .on('blur', function() {
            $(this).removeClass('editing');
            var $cell = $(this);
            var updateFlag = $cell.data('updateOnEnter');
            var originalText = $cell.data('original');

            // If update was not triggered by Enter, revert to original value
            if (!updateFlag) {
                $cell.text(originalText);
                $cell.data('edited', false);
                return;
            }

            // Otherwise, proceed to update
            var newText = $cell.text().trim();
            if (newText === "") {
                $cell.text(originalText);
                $cell.data('edited', false);
                return;
            }

            var action = $cell.data('action'); // "override", "deposit", or "expense"
            var date = $cell.closest('.calendar-day').data('date');

            // Since the cell was cleared on first key press, newText should contain only the arithmetic expression.
            var expr = newText.replace(/[^0-9+\-*/().\s]/g, '');
            if (!/^[0-9+\-*/().\s]+$/.test(expr)) {
                alert("Please enter a valid arithmetic expression.");
                $cell.text(originalText);
                $cell.data('edited', false);
                return;
            }

            var result;
            try {
                result = eval(expr);
            } catch (e) {
                alert("Invalid expression.");
                $cell.text(originalText);
                $cell.data('edited', false);
                return;
            }

            var numericValue = Math.round(result * 100) / 100;
            // Clear stored original data and flags
            $cell.removeData('original').data('edited', false).removeData('updateOnEnter');

            var payload = { date: date, type: action, value: numericValue };
            $.ajax({
                url: 'update-cell.php',
                type: 'POST',
                data: payload,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('Update failed: ' + response.error);
                    }
                },
                error: function(xhr, status, error) {
                    alert("AJAX error: " + error);
                }
            });
        });


    /* ===== Delete Override Handler ===== */
    $(document).on('click', '.delete-override', function(e) {
        e.stopPropagation();
        if (!confirm('Delete this override?')) return;
        var $btn = $(this);
        var date = $btn.data('date');
        var type = $btn.data('type'); // "override", "deposit", or "expense"
        $.ajax({
            url: 'delete-override.php',
            type: 'POST',
            data: { date: date, type: type },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('Delete failed: ' + response.error);
                }
            },
            error: function(xhr, status, error) {
                alert("AJAX error: " + error);
            }
        });
    });


    /* ===== Transactions Modal Handlers ===== */
    $(document).on('click', '.deposits, .expenses', function(e) {
        e.preventDefault();
        var date = $(this).closest('.calendar-day').data('date');
        $('#transDate').text(date);
        $('#transDateInput').val(date);
        // Load transactions via AJAX
        $.ajax({
            url: 'transactions.php',
            type: 'GET',
            data: { date: date },
            dataType: 'json',
            success: function(data) {
                $('#depositsList').empty();
                $('#expensesList').empty();
                data.forEach(function(item) {
                    var row = $('<div class="transaction-row"></div>');
                    var inputAmount = `<input type="number" step="0.01" class="tran-amount" value="${item.amount}" placeholder="Amount">`;
                    var inputNotes = `<input type="text" class="tran-notes" value="${item.notes || ''}" placeholder="Notes" title="${item.notes || ''}">`;
                    var deleteButton = `<button type="button" class="deleteTran">Delete</button>`;
                    row.append(inputAmount + inputNotes + deleteButton);
                    if (item.type === 'deposit') {
                        $('#depositsList').append(row);
                    } else if (item.type === 'expense') {
                        $('#expensesList').append(row);
                    }
                });
                $('#modalOverlay, #transactionsModal').show();
            },
            error: function() {
                alert('Error loading transactions.');
            }
        });
    });
    $('#closeTransModal, #modalOverlay').on('click', function() {
        $('#transactionsModal, #modalOverlay').hide();
    });
    $('#addDeposit').click(function() {
        var row = $('<div class="transaction-row"></div>');
        row.append('<input type="number" step="0.01" class="tran-amount" placeholder="Amount">');
        row.append('<input type="text" class="tran-notes" placeholder="Notes">');
        row.append('<button type="button" class="deleteTran">Delete</button>');
        $('#depositsList').append(row);
    });
    $('#addExpense').click(function() {
        var row = $('<div class="transaction-row"></div>');
        row.append('<input type="number" step="0.01" class="tran-amount" placeholder="Amount">');
        row.append('<input type="text" class="tran-notes" placeholder="Notes">');
        row.append('<button type="button" class="deleteTran">Delete</button>');
        $('#expensesList').append(row);
    });
    $(document).on('click', '.deleteTran', function() {
        $(this).closest('.transaction-row').remove();
    });
    $('#transactionsForm').submit(function(e) {
        e.preventDefault();
        var date = $('#transDateInput').val();
        var transactions = [];
        $('#depositsList .transaction-row').each(function() {
            var amount = $(this).find('.tran-amount').val();
            var notes = $(this).find('.tran-notes').val();
            if (amount) {
                transactions.push({ type: 'deposit', amount: parseFloat(amount), notes: notes });
            }
        });
        $('#expensesList .transaction-row').each(function() {
            var amount = $(this).find('.tran-amount').val();
            var notes = $(this).find('.tran-notes').val();
            if (amount) {
                transactions.push({ type: 'expense', amount: parseFloat(amount), notes: notes });
            }
        });
        var dataToSend = { date: date, transactions: transactions };
        $.ajax({
            url: 'transactions.php',
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(dataToSend),
            success: function() {
                $('#transactionsModal, #modalOverlay').hide();
                location.reload();
            },
            error: function() {
                alert('Error saving transactions.');
            }
        });
    });


    /* ===== Weekly Overrides & Entries ===== */
    $(document).on('click', '.weekly-summary', function() {
        var weekStart = $(this).data('week');
        if (!weekStart) return;
        $.ajax({
            url: 'weekly_override.php',
            type: 'GET',
            data: { week_start: weekStart },
            dataType: 'json',
            success: function(data) {
                $('#weeklyList').empty();
                data.forEach(function(item) {
                    var row = $(`
                        <div class="weekly-row mb-2 d-flex gap-2 align-items-center">
                            <input type="text" class="form-control form-control-sm w-50 label-input" value="${item.label}">
                            <input type="number" step="0.01" class="form-control form-control-sm amount-input" value="${item.amount}">
                            <button type="button" class="btn btn-sm btn-danger delete-weekly">&times;</button>
                        </div>
                    `);
                    row.data('id', item.id || null);
                    $('#weeklyList').append(row);
                });
                $('#weeklyForm').data('week-start', weekStart);
                $('#modalOverlay, #weeklyModal').show();
            },
            error: function() {
                alert('Failed to load weekly overrides.');
            }
        });
    });
    $('#addWeekly').on('click', function() {
        var row = $(`
            <div class="weekly-row mb-2 d-flex gap-2 align-items-center">
                <input type="text" class="form-control form-control-sm w-50 label-input" placeholder="Label">
                <input type="number" step="0.01" class="form-control form-control-sm amount-input" placeholder="Amount">
                <button type="button" class="btn btn-sm btn-danger delete-weekly">&times;</button>
            </div>
        `);
        row.data('id', null);
        $('#weeklyList').append(row);
    });
    $(document).on('click', '.delete-weekly', function() {
        $(this).closest('.weekly-row').remove();
    });
    $('#weeklyForm').on('submit', function(e) {
        e.preventDefault();
        var entries = [];
        $('.weekly-row').each(function() {
            var id = $(this).data('id');
            var label = $(this).find('.label-input').val();
            var amount = $(this).find('.amount-input').val();
            if (label && amount) {
                entries.push({ id: id, label: label, amount: amount });
            }
        });
        $.ajax({
            url: 'weekly_data.php',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(entries),
            success: function() {
                location.reload();
            },
            error: function() {
                alert('Error saving weekly entries.');
            }
        });
    });
    $(document).on('click', '.calendar-day.weekly-summary', function() {
        var sunday = $(this).data('date');
        $('#weeklyOverrideDate').text(sunday);
        $('#weeklyOverrideForm').data('sunday', sunday);
        $.ajax({
            url: 'weekly_override.php',
            type: 'GET',
            data: { week_start: sunday },
            success: function(data) {
                $('#weeklyOverrideList').empty();
                data.forEach(function(item) {
                    var row = $(`
                        <div class="weekly-row mb-2 d-flex gap-2 align-items-center">
                            <input type="text" class="form-control form-control-sm w-50 label-input" value="${item.label}">
                            <input type="number" step="0.01" class="form-control form-control-sm amount-input" value="${item.amount}">
                            <button type="button" class="btn btn-sm btn-danger delete-weekly">&times;</button>
                        </div>
                    `);
                    $('#weeklyOverrideList').append(row);
                });
                $('#modalOverlay, #weeklyOverrideModal').show();
            }
        });
    });
    $('#addWeeklyOverride').on('click', function() {
        var row = $(`
            <div class="weekly-row mb-2 d-flex gap-2 align-items-center"></div>
        `);
        row.append('<input type="text" class="form-control form-control-sm w-50 label-input" placeholder="Label">');
        row.append('<input type="number" step="0.01" class="form-control form-control-sm amount-input" placeholder="Amount">');
        row.append('<button type="button" class="btn btn-sm btn-danger delete-weekly">&times;</button>');
        $('#weeklyOverrideList').append(row);
    });
    $('#weeklyOverrideForm').on('submit', function(e) {
        e.preventDefault();
        var week_start = $(this).data('sunday');
        var overrides = [];
        $('#weeklyOverrideList .weekly-row').each(function() {
            var label = $(this).find('.label-input').val();
            var amount = $(this).find('.amount-input').val();
            if (label && amount) {
                overrides.push({ label: label, amount: amount });
            }
        });
        $.ajax({
            url: 'weekly_override.php',
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ week_start: week_start, overrides: overrides }),
            success: function() {
                $('#weeklyOverrideModal, #modalOverlay').hide();
                location.reload();
            },
            error: function() {
                alert('Error saving overrides.');
            }
        });
    });
    $('#closeWeeklyOverrideModal, #modalOverlay').on('click', function() {
        $('#weeklyOverrideModal, #modalOverlay').hide();
    });


    /* ===== Recalculate Cache & Other Toggles ===== */
    $(document).on('click', '#recalcCacheBtn', function () {
        if (!confirm('Clear and recalculate all month-end balances?')) return;
        $.ajax({
            url: 'recalculate.php',
            type: 'POST',
            dataType: 'json',
            success: function (res) {
                if (res.success) {
                    $('#cacheAlert').fadeIn(200);
                    setTimeout(() => {
                        $('#cacheAlert').fadeOut(200, function () {
                            location.reload();
                        });
                    }, 1200);
                } else {
                    alert('Recalculation failed: ' + (res.error || 'Unknown error'));
                }
            },
            error: function (xhr) {
                alert('AJAX error: ' + xhr.statusText);
            }
        });
    });
    $('#darkModeToggle').on('click', function() {
        $('body').toggleClass('dark-mode');
        localStorage.setItem('darkMode', $('body').hasClass('dark-mode') ? 'on' : 'off');
    });
    if (localStorage.getItem('darkMode') === 'on') {
        $('body').addClass('dark-mode');
    }
    if (localStorage.getItem('hideZeros') === 'true') {
        $('#hideZeroToggle').prop('checked', true);
        $('body').addClass('hide-zeros');
    }
    $('#hideZeroToggle').on('change', function () {
        var isChecked = $(this).is(':checked');
        localStorage.setItem('hideZeros', isChecked ? 'true' : 'false');
        document.cookie = "hide_zeros=" + (isChecked ? "true" : "false") + "; path=/";
        location.reload();
    });
});
