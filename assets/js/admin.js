(function ($) {
    'use strict';

    $(function () {
        const { nonce, formId, exportNonce, statuses, statusColors } = wsmData;

        // ─── SETTINGS PAGE LOGIC ─────────────────────────────────────────────

        // Toggle duplicate fields container visibility
        $('.wsm-track-dups-checkbox').on('change', function () {
            const container = $(this).closest('tr').find('.wsm-duplicate-fields-container');
            if (container.length) container.toggle(this.checked);
        });

        // Settings form save
        $('#wsm-settings-form').on('submit', function (e) {
            e.preventDefault();
            const checked = $('.wsm-form-checkbox:checked').map((_, el) => el.value).get();
            const order = $('.wsm-sort-item').map((_, el) => el.dataset.id).get();
            const trackDups = $('.wsm-track-dups-checkbox:checked').map((_, el) => el.value).get();

            const data = new FormData();
            data.append('action', 'wsm_save_settings');
            data.append('nonce', nonce);
            checked.forEach(id => data.append('form_ids[]', id));
            order.forEach(id => data.append('form_order[]', id));
            trackDups.forEach(id => data.append('track_dup_forms[]', id));

            const dupFieldsMap = {};
            $('.wsm-duplicate-fields-input').each(function () {
                const fidMatch = this.name.match(/\[(\d+)\]/);
                if (fidMatch) dupFieldsMap[fidMatch[1]] = this.value;
            });

            $('.wsm-duplicate-fields-options').each(function () {
                const fid = this.dataset.fid;
                const checkedBoxes = $(this).find('.wsm-dup-check:checked').map((_, el) => el.value).get();
                dupFieldsMap[fid] = checkedBoxes.join(', ');
            });

            for (const [fid, val] of Object.entries(dupFieldsMap)) {
                data.append(`duplicate_fields[${fid}]`, val);
            }

            const globalData = new FormData($('#wsm-global-settings')[0]);
            for (const [key, val] of globalData.entries()) {
                data.append(key, val);
            }

            fetch(ajaxurl, { method: 'POST', body: data })
                .then(r => r.json())
                .then(r => {
                    const $msg = $('#wsm-settings-msg');
                    $msg.text(r.success ? '✅ Saved!' : '❌ Error saving.');
                    $msg.css('color', r.success ? 'green' : 'red');
                    setTimeout(() => $msg.text(''), 3000);
                    if (r.success) rebuildSortList(checked);
                });
        });

        // Legacy CSV mapping logic
        $(document).on('change', '.wsm-legacy-file', function (e) {
            const file = e.target.files[0];
            if (!file) return;

            // Simple frontend validation
            if (!file.name.toLowerCase().endsWith('.csv')) {
                alert('Please select a valid CSV file.');
                this.value = '';
                return;
            }

            const $container = $(this).closest('.wsm-legacy-container');
            const $mapping = $container.find('.wsm-legacy-mapping');
            const $uidSel = $container.find('.wsm-csv-uid-col');
            const $valSel = $container.find('.wsm-csv-match-col');

            const data = new FormData();
            data.append('action', 'wsm_get_csv_headers');
            data.append('nonce', nonce);
            data.append('file', file);

            fetch(ajaxurl, { method: 'POST', body: data })
                .then(r => r.json())
                .then(r => {
                    if (r.success) {
                        $uidSel.empty();
                        $valSel.empty();
                        r.data.forEach((h, i) => {
                            $uidSel.append(`<option value="${i}">${h}</option>`);
                            $valSel.append(`<option value="${i}">${h}</option>`);
                        });
                        $mapping.slideDown();
                    } else {
                        alert(r.data || 'Error reading CSV headers.');
                    }
                });
        });

        $(document).on('click', '.wsm-start-import', function () {
            const btn = this;
            const $container = $(this).closest('.wsm-legacy-container');
            const fid = this.dataset.fid;
            const file = $container.find('.wsm-legacy-file')[0].files[0];
            const uidCol = $container.find('.wsm-csv-uid-col').val();
            const valCol = $container.find('.wsm-csv-match-col').val();
            const matchField = $container.find('.wsm-form-field-key').val();

            if (!file) return;

            btn.disabled = true;
            btn.textContent = 'Importing...';

            const data = new FormData();
            data.append('action', 'wsm_import_legacy');
            data.append('nonce', nonce);
            data.append('form_id', fid);
            data.append('uid_col', uidCol);
            data.append('val_col', valCol);
            data.append('match_field', matchField);
            data.append('file', file);

            fetch(ajaxurl, { method: 'POST', body: data })
                .then(r => r.json())
                .then(r => {
                    if (r.success) {
                        alert(`✅ Successfully imported ${r.data.count} records.`);
                        location.reload();
                    } else {
                        alert(r.data || 'Import failed.');
                    }
                })
                .finally(() => {
                    btn.disabled = false;
                    btn.textContent = 'Start Import';
                });
        });

        $(document).on('click', '.wsm-clear-legacy', function () {
            if (!confirm('Are you sure you want to clear all legacy data for this form?')) return;
            const fid = this.dataset.fid;
            const data = new FormData();
            data.append('action', 'wsm_clear_legacy');
            data.append('nonce', nonce);
            data.append('form_id', fid);

            fetch(ajaxurl, { method: 'POST', body: data })
                .then(r => r.json())
                .then(r => {
                    if (r.success) location.reload();
                });
        });

        function rebuildSortList(checkedIds) {
            const $list = $('#wsm-sortable-forms');
            if (!$list.length) return;

            $list.find('.wsm-sort-item').each(function () {
                if (!checkedIds.includes(this.dataset.id)) $(this).remove();
            });

            const existing = $list.find('.wsm-sort-item').map((_, el) => el.dataset.id).get();
            checkedIds.forEach(id => {
                if (existing.includes(id)) return;
                const row = $(`.wsm-form-checkbox[value="${id}"]`).closest('tr');
                const name = row.length ? row.find('td:eq(1)').text().trim() : `Form #${id}`;
                const $li = $('<li class="wsm-sort-item">')
                    .attr('data-id', id)
                    .html(`<span class="wsm-drag-handle" title="Drag to reorder">⠿</span><span class="wsm-sort-label">${name}</span><span class="wsm-sort-id" style="color:#999;font-size:12px;"> #${id}</span>`);
                $list.append($li);
                attachDragEvents($li[0]);
            });
        }

        // Drag-and-drop reorder
        let dragSrc = null;

        function saveOrder() {
            const order = $('.wsm-sort-item').map((_, el) => el.dataset.id).get();
            const data = new FormData();
            data.append('action', 'wsm_save_order');
            data.append('nonce', nonce);
            order.forEach(id => data.append('order[]', id));
            fetch(ajaxurl, { method: 'POST', body: data })
                .then(r => r.json())
                .then(r => {
                    const $st = $('#wsm-order-status');
                    if (!$st.length) return;
                    $st.text(r.success ? '✅ Order saved!' : '❌ Save failed');
                    $st.css('color', r.success ? '#00a32a' : '#d63638');
                    setTimeout(() => $st.text(''), 2500);
                });
        }

        function attachDragEvents(item) {
            item.setAttribute('draggable', 'true');
            item.addEventListener('dragstart', function (e) {
                dragSrc = this;
                this.classList.add('wsm-dragging');
                e.dataTransfer.effectAllowed = 'move';
            });
            item.addEventListener('dragend', function () {
                this.classList.remove('wsm-dragging');
                $('.wsm-sort-item').removeClass('wsm-drag-over');
                saveOrder();
            });
            item.addEventListener('dragover', function (e) {
                e.preventDefault();
                e.dataTransfer.dropEffect = 'move';
                $('.wsm-sort-item').removeClass('wsm-drag-over');
                this.classList.add('wsm-drag-over');
            });
            item.addEventListener('drop', function (e) {
                e.preventDefault();
                if (dragSrc && dragSrc !== this) {
                    const list = this.parentNode;
                    const items = [...list.children];
                    const srcIdx = items.indexOf(dragSrc);
                    const destIdx = items.indexOf(this);
                    if (srcIdx < destIdx) {
                        list.insertBefore(dragSrc, this.nextSibling);
                    } else {
                        list.insertBefore(dragSrc, this);
                    }
                }
                this.classList.remove('wsm-drag-over');
            });
        }

        $('.wsm-sort-item').each(function () { attachDragEvents(this); });


        // ─── DASHBOARD LOGIC ─────────────────────────────────────────────────

        function updateWSMBadges(counts) {
            if (!counts) return;
            if (counts.form_new_count !== undefined) {
                const tab = document.querySelector(`.wsm-tab[data-tab-fid="${formId}"]`);
                if (tab) {
                    let badge = tab.querySelector('.wsm-new-badge');
                    if (counts.form_new_count > 0) {
                        if (!badge) {
                            badge = document.createElement('span');
                            badge.className = 'wsm-new-badge';
                            tab.appendChild(badge);
                        }
                        badge.title = `${counts.form_new_count} new submission(s)`;
                        badge.textContent = counts.form_new_count > 99 ? '99+' : counts.form_new_count;
                    } else if (badge) {
                        badge.remove();
                    }
                }
            }
            if (counts.total_new_count !== undefined) {
                const menuItem = document.querySelector('#toplevel_page_wsm-dashboard .wp-menu-name');
                if (menuItem) {
                    let badge = menuItem.querySelector('.update-plugins');
                    if (counts.total_new_count > 0) {
                        if (!badge) {
                            badge = document.createElement('span');
                            menuItem.appendChild(badge);
                        }
                        badge.className = `update-plugins count-${counts.total_new_count}`;
                        badge.innerHTML = `<span class="plugin-count">${counts.total_new_count}</span>`;
                    } else if (badge) {
                        badge.remove();
                    }
                }
            }
        }

        function paintSelect(sel) {
            const colors = statusColors || {};
            const apply = () => {
                const c = colors[sel.value] || '#555';
                sel.style.borderColor = c;
                sel.style.color = c;
            };
            apply();
        }

        // Initialize existing selects
        document.querySelectorAll('.wsm-status-select').forEach(paintSelect);

        // Delegated listener for status select changes
        $(document).on('change', '.wsm-status-select', function () {
            paintSelect(this);
            const row = this.closest('.wsm-row');
            if (this.value !== 'New') {
                row.classList.remove('wsm-row-new');
                const dot = row.querySelector('.wsm-row-new-dot');
                if (dot) dot.remove();
            } else {
                row.classList.add('wsm-row-new');
                if (!row.querySelector('.wsm-row-new-dot')) {
                    const idCol = row.querySelector('[data-label="ID"]');
                    const dot = document.createElement('span');
                    dot.className = 'wsm-row-new-dot';
                    dot.title = 'New submission';
                    dot.textContent = '●';
                    idCol.appendChild(dot);
                }
            }
        });

        function saveRow(row, btn, msg) {
            btn.disabled = true;
            btn.textContent = 'Saving…';
            const body = new FormData();
            body.append('action', 'wsm_save');
            body.append('nonce', nonce);
            body.append('entry_id', row.dataset.entry);
            body.append('form_id', formId);
            body.append('status', row.querySelector('.wsm-status-select').value);
            body.append('notes', row.querySelector('.wsm-notes').value);

            return fetch(ajaxurl, { method: 'POST', body })
                .then(r => r.json())
                .then(r => {
                    msg.textContent = r.success ? '✅ Saved' : '❌ Error';
                    msg.style.color = r.success ? '#00a32a' : '#d63638';
                    if (r.success && r.data) updateWSMBadges(r.data);
                })
                .catch(() => {
                    msg.textContent = '❌ Failed';
                    msg.style.color = '#d63638';
                })
                .finally(() => {
                    btn.disabled = false;
                    btn.textContent = 'Save';
                    setTimeout(() => msg.textContent = '', 4000);
                });
        }

        // Delegated Save button
        $(document).on('click', '.wsm-save-btn', function () {
            const row = this.closest('.wsm-row');
            saveRow(row, this, row.querySelector('.wsm-msg'));
        });

        const $bulkPanel = $('#wsm-bulk-controls');

        function getChecked() {
            return $('.wsm-row-check:checked');
        }

        function updateBulkPanel() {
            const any = getChecked().length > 0;
            $bulkPanel.toggle(any);
            if (any) $bulkPanel.css('display', 'flex');
        }

        function syncRowHighlight(cb) {
            $(cb).closest('.wsm-row').toggleClass('wsm-selected', cb.checked);
        }

        // Delegated row checkbox
        $(document).on('change', '.wsm-row-check', function () {
            syncRowHighlight(this);
            updateBulkPanel();
        });

        // Delegated Select All
        $(document).on('change', '#wsm-head-check, #wsm-check-all', function () {
            const checked = this.checked;
            $('.wsm-row-check').each(function () {
                this.checked = checked;
                syncRowHighlight(this);
            });
            $('#wsm-head-check').prop('checked', checked);
            $('#wsm-check-all').prop('checked', checked);
            updateBulkPanel();
        });

        // ─── AJAX SEARCH LOGIC ───────────────────────────────────────────────

        let searchTimer;
        const $searchInput = $('#wsm-search-input');
        const $spinner = $('#wsm-search-spinner');
        const $tableBody = $('#wsm-entries-body');
        const $paginationWrap = $('#wsm-pagination-container');

        $searchInput.on('input', function () {
            clearTimeout(searchTimer);
            $spinner.addClass('is-active');

            searchTimer = setTimeout(() => {
                const query = $(this).val();
                const statusFilter = new URLSearchParams(window.location.search).get('status_filter') || '';

                const body = new FormData();
                body.append('action', 'wsm_search_entries');
                body.append('nonce', nonce);
                body.append('form_id', formId);
                body.append('search', query);
                body.append('status_filter', statusFilter);

                fetch(ajaxurl, { method: 'POST', body })
                    .then(r => r.json())
                    .then(r => {
                        if (r.success) {
                            $tableBody.html(r.data.rows);
                            $paginationWrap.html(r.data.pagination);
                            $('.wsm-total').text(`${r.data.total} total entries`);

                            // Colorize new selects
                            document.querySelectorAll('#wsm-entries-body .wsm-status-select').forEach(paintSelect);

                            // Reset checkboxes
                            $('#wsm-head-check, #wsm-check-all').prop('checked', false);
                            updateBulkPanel();

                            // Update export URL if active (simple approach)
                            const $exportBtn = $('#wsm-export-all-btn');
                            if ($exportBtn.length) {
                                let url = new URL($exportBtn.attr('href'));
                                url.searchParams.set('search', query);
                                $exportBtn.attr('href', url.toString());
                            }
                        }
                    })
                    .finally(() => {
                        $spinner.removeClass('is-active');
                    });
            }, 400);
        });

        // Prevent form submission on Enter if we want purely AJAX
        $('#wsm-search-form').on('submit', function (e) {
            e.preventDefault();
        });

        // ─── BULK ACTIONS ────────────────────────────────────────────────────

        $('#wsm-save-all-btn').on('click', function () {
            const btn = this;
            const $msg = $('#wsm-saveall-msg');
            const $rows = $('.wsm-row');

            btn.disabled = true;
            btn.textContent = 'Saving…';
            const payload = $rows.map((_, row) => ({
                entry_id: row.dataset.entry,
                status: $(row).find('.wsm-status-select').val(),
                notes: $(row).find('.wsm-notes').val(),
            })).get();

            const body = new FormData();
            body.append('action', 'wsm_bulk_save');
            body.append('nonce', nonce);
            body.append('form_id', formId);
            payload.forEach((r, i) => {
                body.append(`rows[${i}][entry_id]`, r.entry_id);
                body.append(`rows[${i}][status]`, r.status);
                body.append(`rows[${i}][notes]`, r.notes);
            });

            fetch(ajaxurl, { method: 'POST', body })
                .then(r => r.json())
                .then(r => {
                    $msg.text(r.success ? `✅ Saved ${r.data?.saved ?? $rows.length} rows` : '❌ Error');
                    $msg.css('color', r.success ? '#00a32a' : '#d63638');
                    if (r.success && r.data) updateWSMBadges(r.data);
                })
                .catch(() => {
                    $msg.text('❌ Failed');
                    $msg.css('color', '#d63638');
                })
                .finally(() => {
                    btn.disabled = false;
                    btn.textContent = '💾 Save All';
                    setTimeout(() => $msg.text(''), 5000);
                });
        });

        $('#wsm-bulk-status-btn').on('click', function () {
            const status = $('#wsm-bulk-status-select').val();
            const $msg = $('#wsm-bulk-msg');
            const $checked = getChecked();
            const ids = $checked.map((_, cb) => cb.value).get();

            if (!status) {
                $msg.text('Pick a status first').css('color', '#d63638');
                return;
            }
            if (!ids.length) return;

            this.disabled = true;
            const body = new FormData();
            body.append('action', 'wsm_bulk_status');
            body.append('nonce', nonce);
            body.append('form_id', formId);
            body.append('status', status);
            ids.forEach(id => body.append('entry_ids[]', id));

            fetch(ajaxurl, { method: 'POST', body })
                .then(r => r.json())
                .then(r => {
                    if (r.success) {
                        ids.forEach(id => {
                            const $row = $(`.wsm-row[data-entry="${id}"]`);
                            if (!$row.length) return;
                            const sel = $row.find('.wsm-status-select')[0];
                            sel.value = status;
                            paintSelect(sel);
                            if (status !== 'New') {
                                $row.removeClass('wsm-row-new');
                                $row.find('.wsm-row-new-dot').remove();
                            }
                        });
                        $msg.text(`✅ Updated ${ids.length} rows`).css('color', '#00a32a');
                        if (r.data) updateWSMBadges(r.data);
                    } else {
                        $msg.text('❌ Error').css('color', '#d63638');
                    }
                })
                .catch(() => {
                    $msg.text('❌ Failed').css('color', '#d63638');
                })
                .finally(() => {
                    this.disabled = false;
                    setTimeout(() => $msg.text(''), 5000);
                });
        });

    });
})(jQuery);
