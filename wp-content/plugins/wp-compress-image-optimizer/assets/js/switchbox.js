jQuery(document).ready(function ($) {

    /**
     * Switch Box
     * @since 3.3.0
     */
    $('a', '.wp-ic-select-box').on('click', function (e) {
        e.preventDefault();

        var link = $(this);
        var li = $(this).parent();
        var ul = $(li).parent();
        var div = $(ul).parent();
        var input = $('input[type="hidden"]', div);
        var value = $(link).data('value');

        if ($(div).hasClass('disabled')) {
            return false;
        }

        $(input).attr('value', value);
        $('li', ul).removeClass('current');
        $(link).parent().addClass('current');
    });


});