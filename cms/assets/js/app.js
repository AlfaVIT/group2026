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

    $(function () {
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
    });
})(jQuery);