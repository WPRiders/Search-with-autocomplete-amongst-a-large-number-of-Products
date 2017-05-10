jQuery(document).ready(function ($) {
    'use strict';

    $('.wpr-autocomplete').on("keydown", function (event) {
        if (event.keyCode === $.ui.keyCode.TAB &&
            $(this).autocomplete("instance").menu.active) {
            event.preventDefault();
        }
    }).autocomplete({
        source: function (request, response) {
            $.ajax({
                url: ajax_object.ajax_url,
                dataType: "json",
                data: {
                    action: 'wpr_search_product',
                    nonce: ajax_object.nonce,
                    q: request.term
                },
                success: function (data) {
                    response($.ui.autocomplete.filter(
                        response(data), extractLast(request.term))
                    );
                }
            });
        },
        minLength: 3,
        focus: function () {
            return false;
        },
        select: function (event, ui) {
            this.value = '';

            $("#wpr-active-products").append('<li><span class="wpr-product-name">' + ui.item.value + '</span><input type="hidden" value="' + ui.item.id + '" name="wpr-product-id[]" class="wpr-product-id"/> <a href="#" class="wpr-remove-product">X</a></li>');

            return false;
        }
    });

    $('#wpr-active-products').on('click', '.wpr-remove-product', function (e) {
        e.preventDefault();
        $(this).closest('li').remove();
    });
});

function split(val) {
    return val.split(/,\s*/);
}

function extractLast(term) {
    return split(term).pop();
}