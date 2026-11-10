(function ($) {
    'use strict';

    function materialRow(item) {
        item = item || {};
        var type = item.type || 'link';
        var $row = $(
            '<div class="materials-row border rounded-3 p-2 mb-2 bg-body-tertiary">' +
            '  <div class="row g-2 align-items-center">' +
            '    <div class="col-6 col-md-3">' +
            '      <select class="form-select form-select-sm mat-type" name="mat_type[]">' +
            '        <option value="link">Ссылка</option>' +
            '        <option value="text">Текст</option>' +
            '      </select>' +
            '    </div>' +
            '    <div class="col-6 col-md-4">' +
            '      <input class="form-control form-control-sm mat-title" type="text" name="mat_title[]" placeholder="Название (необязательно)">' +
            '    </div>' +
            '    <div class="col-10 col-md-4">' +
            '      <input class="form-control form-control-sm mat-link" type="url" name="mat_url[]" placeholder="https://…">' +
            '      <textarea class="form-control form-control-sm mat-text d-none" name="mat_text[]" rows="2" placeholder="Текст материала"></textarea>' +
            '    </div>' +
            '    <div class="col-2 col-md-1 text-end">' +
            '      <button type="button" class="btn btn-sm btn-outline-danger mat-remove" title="Удалить">&times;</button>' +
            '    </div>' +
            '  </div>' +
            '</div>'
        );
        $row.find('.mat-type').val(type);
        $row.find('.mat-title').val(item.title || '');
        if (type === 'text') {
            $row.find('.mat-link').addClass('d-none').val('');
            $row.find('.mat-text').removeClass('d-none').val(item.text || '');
        } else {
            $row.find('.mat-url-fix');
            $row.find('.mat-link').val(item.url || '');
        }
        return $row;
    }

    function formatPhone(raw) {
        var d = (raw || '').replace(/\D+/g, '');
        if (!d) {
            return '';
        }
        var s = '+' + d.charAt(0);
        if (d.length > 1) {
            s += ' (' + d.slice(1, 4);
        }
        if (d.length > 4) {
            s += ') ' + d.slice(4, 7);
        }
        if (d.length > 7) {
            s += '-' + d.slice(7, 9);
        }
        if (d.length > 9) {
            s += '-' + d.slice(9, 11);
        }
        return s;
    }

    function maskDigits(el, max) {
        var v = (el.value || '').replace(/\D+/g, '');
        if (max && v.length > max) {
            v = v.slice(0, max);
        }
        if (el.value !== v) {
            el.value = v;
        }
    }

    var EYE_ICON = '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' +
        '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>';
    var EYE_OFF_ICON = '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' +
        '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/>' +
        '<path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/>' +
        '<path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"/>' +
        '<line x1="1" y1="1" x2="23" y2="23"/></svg>';

    function showToast(message, type) {
        type = type || 'success';
        var $wrap = $('#cms-toasts');
        if (!$wrap.length) {
            $wrap = $('<div id="cms-toasts" aria-live="polite"></div>').appendTo('body');
        }
        var $t = $('<div class="cms-toast alert alert-' + type + ' mb-0" role="alert">' + message + '</div>');
        $wrap.append($t);
        setTimeout(function () { $t.addClass('show'); }, 10);
        setTimeout(function () {
            $t.removeClass('show');
            setTimeout(function () { $t.remove(); }, 300);
        }, 4000);
    }

    $(function () {
        $(document).on('submit', 'form[method="post"][action]:not([data-no-ajax]):not([onsubmit])', function (e) {
            var $form = $(this);
            var $btn = $form.find('[type="submit"]');
            var wasDisabled = $btn.prop('disabled');
            e.preventDefault();
            if ($btn.length) {
                $btn.prop('disabled', true);
            }
            $.ajax({
                url: $form.attr('action'),
                method: 'POST',
                data: new FormData(this),
                processData: false,
                contentType: false,
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                dataType: 'json'
            }).done(function (data) {
                if (data && data.ok) {
                    if (data.navigate && data.redirect) {
                        window.location.href = data.redirect;
                        return;
                    }
                    if (data.toast && data.toast.message) {
                        showToast(data.toast.message, data.toast.type || 'success');
                    } else {
                        showToast('Сохранено', 'success');
                    }
                } else if (data && data.error) {
                    showToast(data.error, 'danger');
                    if (data.reload) {
                        window.location.reload();
                        return;
                    }
                } else {
                    showToast('Что-то пошло не так', 'danger');
                }
                if ($btn.length) {
                    $btn.prop('disabled', wasDisabled);
                }
                var updateSel = $form.attr('data-ajax-update');
                if (updateSel && !(data.navigate && data.redirect)) {
                    $.get(window.location.href).done(function (html) {
                        var $parsed = $('<div>').append(html);
                        $(updateSel).each(function () {
                            var id = this.id;
                            if (!id) {
                                return;
                            }
                            var $fresh = $parsed.find('[id="' + id.replace(/"/g, '\\"') + '"]').first();
                            if ($fresh.length) {
                                $(this).replaceWith($fresh);
                            }
                        });
                    });
                }
            }).fail(function () {
                if ($btn.length) {
                    $btn.prop('disabled', wasDisabled);
                }
                showToast('Ошибка сети. Попробуйте ещё раз.', 'danger');
            });
        });
    });

    $(function () {
        var $atForm = $('#attendance form');
        if ($atForm.length) {
            var $reason = $atForm.find('#attendance_reason');
            var updateReasonRequired = function () {
                var v = $atForm.find('input[name="status"]:checked').val();
                $reason.prop('required', v === 'absent');
                $reason.attr('placeholder', v === 'absent' ? 'Обязательно укажите причину' : '');
            };
            $atForm.on('change', 'input[name="status"]', updateReasonRequired);
            updateReasonRequired();
        }

        $(document).on('input', '[data-bday-mask]', function () {
            maskDigits(this, parseInt($(this).attr('data-bday-mask'), 10) || 0);
        });

        $(document).on('change', 'input[name="birthday_no_year"]', function () {
            var $year = $('#' + this.id.replace(/_no_year$/, '_year'));
            if (!$year.length) {
                $year = $('[name="birthday_year"]').first();
            }
            $year.toggleClass('d-none', this.checked);
            if (this.checked) {
                $year.val('');
            }
        });

        var $helpNav = $('#help-nav');
        if ($helpNav.length) {
            var $helpSections = $('.help-section');
            var showHelpSection = function (name) {
                $helpSections.addClass('d-none');
                $('#help-' + name).removeClass('d-none');
                $helpNav.find('.active').removeClass('active').addClass('px-0').removeClass('px-1');
                var $link = $helpNav.find('[data-help="' + name + '"]');
                $link.addClass('active').addClass('px-1').removeClass('px-0');
            };
            $helpNav.on('click', 'a', function (e) {
                e.preventDefault();
                var name = $(this).data('help');
                showHelpSection(name);
                history.replaceState(null, '', '#help-' + name);
            });
            var initial = (location.hash || '').replace(/^#help-/, '');
            if (initial && $helpNav.find('[data-help="' + initial + '"]').length) {
                showHelpSection(initial);
            }
        }

        $(document).on('click', '[data-bd-month]', function () {
            var n = $(this).data('bd-month');
            $('[data-bd-month]').removeClass('active');
            $(this).addClass('active');
            $('.bd-panel').addClass('d-none');
            $('.bd-panel[data-bd-panel="' + n + '"]').removeClass('d-none');
        });

        $('#contactFilter [data-cf]').on('click', function () {
            var term = $(this).data('cf');
            $('#contactFilter [data-cf]').removeClass('active');
            $(this).addClass('active');
            $('.cms-contact-row').each(function () {
                $(this).toggle(term === '' || $(this).data('term') === term);
            });
        });

        $('input[type="password"]').each(function () {
            var $input = $(this);
            if ($input.closest('.cms-pwd').length) {
                return;
            }
            $input.wrap('<div class="cms-pwd"></div>');
            $input.after('<button type="button" class="cms-pwd-btn" tabindex="-1" aria-label="Показать пароль">' + EYE_ICON + '</button>');
        });

        $(document).on('click', '.cms-pwd-btn', function () {
            var $wrap = $(this).closest('.cms-pwd');
            var $input = $wrap.find('input');
            var show = $input.attr('type') === 'password';
            $input.attr('type', show ? 'text' : 'password');
            $(this).attr('aria-label', show ? 'Скрыть пароль' : 'Показать пароль');
            $(this).html(show ? EYE_OFF_ICON : EYE_ICON);
            $input.trigger('focus');
        });

        var $materials = $('#materialsRows');
        if ($materials.length) {
            if (!$materials.children('.materials-row').length) {
                $materials.append(materialRow());
            }
            $materials.on('click', '.mat-remove', function () {
                var $rows = $materials.children('.materials-row');
                if ($rows.length > 1) {
                    $(this).closest('.materials-row').remove();
                } else {
                    $(this).closest('.materials-row').find('input, textarea').val('');
                }
            });
            $materials.on('change', '.mat-type', function () {
                var $row = $(this).closest('.materials-row');
                var isLink = $(this).val() === 'link';
                $row.find('.mat-link').toggleClass('d-none', !isLink);
                $row.find('.mat-text').toggleClass('d-none', isLink);
            });
            $('#matAdd').on('click', function () {
                $materials.append(materialRow());
            });
        }

        $('#geoGenerate').on('click', function () {
            var parts = [];
            ['region', 'district', 'locality', 'street'].forEach(function (id) {
                var v = $('#' + id).val().trim();
                if (v) {
                    parts.push(v);
                }
            });
            var house = $('#house').val().trim() + $('#house_letter').val().trim();
            if (house) {
                parts.push(house);
            }
            if (!parts.length) {
                return;
            }
            $('#geo_link').val('https://yandex.ru/maps/?text=' + encodeURIComponent(parts.join(', ')));
        });

        $('#photos').on('change', function () {
            var files = this.files;
            if (files && files.length > 10) {
                alert('Можно загрузить не более 10 фотографий за раз');
                this.value = '';
            }
        });

        var $userEdit = $('form[data-user-edit]');
        if ($userEdit.length) {
            var updateUserEditRequired = function () {
                var active = $userEdit.find('#is_active').is(':checked');
                $userEdit.find('#login').prop('required', active);
            };
            $userEdit.on('change', '#role', function () {
                var isGuest = $(this).val() === 'Гость';
                $userEdit.find('#is_active').prop('checked', !isGuest);
                updateUserEditRequired();
            });
            $userEdit.on('change', '#is_active', updateUserEditRequired);
            updateUserEditRequired();
        }

        $(document).on('input', '[data-phone-mask]', function (e) {
            var el = this;
            var ev = e.originalEvent || {};
            var deletion = typeof ev.inputType === 'string' && ev.inputType.indexOf('delete') !== -1;
            var digits = el.value.replace(/\D+/g, '');
            if (deletion) {
                digits = digits.slice(0, -1);
            }
            el.value = formatPhone(digits);
        });

        // Подсказки по похожести для полей «Статья» (справочник казны).        var suggestTimers = {};
        $(document).on('input', 'input[data-article-suggest]', function () {
            var $input = $(this);
            var inputId = this.id;
            if (!inputId) {
                return;
            }
            var $box = $('.article-suggest[data-for="' + inputId + '"]');
            var url = $input.attr('data-suggest-url');
            if (!$box.length || !url) {
                return;
            }
            var kindSource = $input.attr('data-suggest-kind-source');
            var kind = (kindSource && $('#' + kindSource).length)
                ? $('#' + kindSource).val()
                : ($input.attr('data-article-suggest') || 'income');
            var q = this.value;

            clearTimeout(suggestTimers[inputId]);
            suggestTimers[inputId] = setTimeout(function () {
                if (q.trim().length < 2) {
                    $box.empty();
                    return;
                }
                $.getJSON(url, { kind: kind, q: q }).done(function (data) {
                    $box.empty();
                    if (!data || !data.items) {
                        return;
                    }
                    data.items.forEach(function (item) {
                        $('<button type="button" class="btn btn-sm btn-outline-secondary me-1 mb-1"></button>')
                            .text(item.name + ' · ' + item.score + '%')
                            .on('click', function () {
                                $input.val(item.name);
                                $box.empty();
                                $input.trigger('focus');
                            })
                            .appendTo($box);
                    });
                });
            }, 250);
        });

        // Просмотр фотографий места поверх страницы (затемнение + снимок по центру)
        function closeLightbox() {
            $('#cms-lightbox').removeClass('show');
            $('body').removeClass('cms-lightbox-open');
        }

        $(document).on('click', 'a[data-lightbox]', function (e) {
            e.preventDefault();
            var src = $(this).attr('data-lightbox') || $(this).attr('href');
            if (!src) {
                return;
            }
            var $box = $('#cms-lightbox');
            if (!$box.length) {
                $box = $('<div id="cms-lightbox" class="cms-lightbox" role="dialog" aria-modal="true" aria-label="Просмотр фотографии">' +
                    '<button type="button" class="cms-lightbox-close" aria-label="Закрыть">&times;</button>' +
                    '<img class="cms-lightbox-img" alt=""></div>').appendTo('body');
            }
            $box.find('img').attr('src', src).attr('alt', $(this).attr('title') || '');
            $box.addClass('show');
            $('body').addClass('cms-lightbox-open');
            $box.find('.cms-lightbox-close').trigger('focus');
        });

        $(document).on('click', '#cms-lightbox', function (e) {
            if (e.target === this || $(e.target).hasClass('cms-lightbox-close')) {
                closeLightbox();
            }
        });

        $(document).on('keydown', function (e) {
            if (e.key === 'Escape' || e.keyCode === 27) {
                closeLightbox();
            }
        });

        // Запись звука для конспекта встречи (MediaRecorder)
        var noteRec = null;
        var noteRecChunks = [];
        var noteRecTimer = null;
        var noteRecSeconds = 0;

        function noteRecStatus(text) {
            $('#noteRecStatus').text(text || '');
        }

        $(document).on('click', '#noteRecStart', function () {
            if (!navigator.mediaDevices || typeof window.MediaRecorder === 'undefined') {
                alert('Запись звука не поддерживается этим браузером. Прикрепите аудиофайл.');
                return;
            }
            navigator.mediaDevices.getUserMedia({ audio: true }).then(function (stream) {
                noteRecChunks = [];
                var mime = '';
                if (window.MediaRecorder.isTypeSupported && MediaRecorder.isTypeSupported('audio/webm;codecs=opus')) {
                    mime = 'audio/webm;codecs=opus';
                }
                try {
                    noteRec = mime ? new MediaRecorder(stream, { mimeType: mime }) : new MediaRecorder(stream);
                } catch (err) {
                    noteRec = new MediaRecorder(stream);
                }
                noteRec.ondataavailable = function (e) {
                    if (e.data && e.data.size) {
                        noteRecChunks.push(e.data);
                    }
                };
                noteRec.onstop = function () {
                    stream.getTracks().forEach(function (t) { t.stop(); });
                    clearInterval(noteRecTimer);
                    var type = noteRec.mimeType || 'audio/webm';
                    var blob = new Blob(noteRecChunks, { type: type });
                    var ext = type.indexOf('ogg') >= 0 ? 'ogg' : 'webm';
                    try {
                        var dt = new DataTransfer();
                        dt.items.add(new File([blob], 'zapis-' + Date.now() + '.' + ext, { type: type }));
                        document.getElementById('note_audio').files = dt.files;
                        noteRecStatus('Запись готова: ' + Math.round(blob.size / 1024) + ' КБ');
                    } catch (err) {
                        noteRecStatus('Запись готова, но браузер не даёт прикрепить её к форме');
                    }
                    $('#noteRecStop').prop('disabled', true);
                    $('#noteRecStart').prop('disabled', false);
                };
                noteRec.start();
                noteRecSeconds = 0;
                $('#noteRecStart').prop('disabled', true);
                $('#noteRecStop').prop('disabled', false);
                noteRecStatus('Идёт запись… 0:00');
                noteRecTimer = setInterval(function () {
                    noteRecSeconds++;
                    var mm = Math.floor(noteRecSeconds / 60);
                    var ss = ('0' + (noteRecSeconds % 60)).slice(-2);
                    noteRecStatus('Идёт запись… ' + mm + ':' + ss);
                }, 1000);
            }).catch(function () {
                alert('Не удалось получить доступ к микрофону');
            });
        });

        $(document).on('click', '#noteRecStop', function () {
            clearInterval(noteRecTimer);
            if (noteRec && noteRec.state !== 'inactive') {
                noteRec.stop();
            }
        });

        // Редактор конспекта: панель форматирования + синхронизация в скрытое поле
        function syncNoteBody() {
            var editor = document.getElementById('noteEditor');
            var field = document.getElementById('noteBody');
            if (editor && field) {
                field.value = editor.innerHTML;
            }
        }

        $(document).on('click', '[data-note-cmd]', function () {
            var cmd = $(this).attr('data-note-cmd');
            var arg = $(this).attr('data-note-arg') || null;
            var editor = document.getElementById('noteEditor');
            if (!editor) {
                return false;
            }
            editor.focus();
            if (cmd === 'link') {
                var url = window.prompt('Адрес ссылки', 'https://');
                if (!url) {
                    return false;
                }
                url = url.trim();
                if (!/^(https?:\/\/|mailto:)/i.test(url)) {
                    alert('Ссылка должна начинаться с http:// или https://');
                    return false;
                }
                document.execCommand('createLink', false, url);
            } else if (cmd === 'formatBlock') {
                // H3 и цитата работают как переключатели, как и списки:
                // повторное нажатие возвращает обычный абзац
                var current = '';
                try {
                    current = String(document.queryCommandValue('formatBlock') || '');
                } catch (e) {
                    current = '';
                }
                current = current.replace(/[<>]/g, '').toLowerCase();
                var target = (current !== '' && current === String(arg).toLowerCase()) ? 'p' : arg;
                document.execCommand('formatBlock', false, '<' + target + '>');
            } else {
                document.execCommand(cmd, false, null);
            }
            syncNoteBody();
            return false;
        });

        $(document).on('submit', 'form[action*="note_save"]', function () {
            syncNoteBody();
        });

        $(document).on('input', '#noteEditor', syncNoteBody);
    });

    // Отметка присутствия за другого участника (Старший/Помощник):
    // при выборе участника подставляем его текущий статус и причину.
    $(document).on('change', '#att_user', function () {
        var $form = $(this).closest('form');
        var $opt = $(this).find('option:selected');
        var status = $opt.attr('data-status') || 'present';
        var reason = $opt.attr('data-reason') || '';
        $form.find('input[name="status"]').prop('checked', false);
        $form.find('#attendance_reason').val(reason);
        $form.find('input[name="status"][value="' + status + '"]').prop('checked', true).trigger('change');
    });
})(jQuery);