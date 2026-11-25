jQuery(function ($) {
    $('.mbws-color').wpColorPicker();

    // "Show more details" / "Xem chi tiết" rows
    $(document).on('click', '.mbws-toggle', function () {
        var row = $(this).closest('tr').next('.mbws-details');
        row.prop('hidden', !row.prop('hidden'));
    });
    $(document).on('change', '.mbws-check-all', function () {
        $(this).closest('table').find('tbody input[type=checkbox]').prop('checked', this.checked);
    });

    // Contact button rows
    $(document).on('click', '.mbws-row-add', function () {
        var body = $('.mbws-repeater tbody'), i = Date.now();
        var row = $($('#mbws-row-template').html().replace(/__i__/g, i));
        body.append(row);
        row.find('.mbws-color').wpColorPicker();
    });
    $(document).on('click', '.mbws-row-delete', function () { $(this).closest('tr').remove(); });

    // Logo / favicon pickers
    $(document).on('click', '.mbws-image-choose', function () {
        var box = $(this).closest('.mbws-image');
        var frame = wp.media({ title: 'Choose image', multiple: false, library: { type: 'image' } });
        frame.on('select', function () {
            var a = frame.state().get('selection').first().toJSON();
            box.find('input').val(a.id);
            box.find('img').attr('src', (a.sizes && a.sizes.thumbnail ? a.sizes.thumbnail.url : a.url)).show();
        });
        frame.open();
    });
    $(document).on('click', '.mbws-image-remove', function () {
        var box = $(this).closest('.mbws-image');
        box.find('input').val('');
        box.find('img').hide();
    });
});
