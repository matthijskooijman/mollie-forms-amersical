jQuery(document).ready(function($) {

    $("#rfmp_fields tbody, #rfmp_priceoptions tbody").sortable({
        handle: ".sort",
        cursor: "move",
        axis:   "y"
    });

    $("#rfmp_fields td .delete, #rfmp_priceoptions td .delete").live('click', function() {
        $(this).closest('tr').remove();
    });

    $("#rfmp_add_field").on('click', function() {
        $("#rfmp_fields tbody").prepend($("#rfmp_template_field").html());
    });

    $("#rfmp_add_priceoption").on('click', function() {
        $("#rfmp_priceoptions tbody").append($("#rfmp_template_priceoption").html());
    });

    $(".rfmp_frequency").live('change', function() {
        if ($(this).val() != 'once')
        {
            $(this).prev("input").show();
            $(this).closest('td').next('td').find("input").show();
        }
        else
        {
            $(this).prev("input").hide();
            $(this).closest('td').next('td').find("input").hide();
        }
    });

    $(".rfmp_pricetype").live('change', function() {
        var input = $(this).next("input");
        if ($(this).val() != 'open')
            input.attr('placeholder', input.data('ph-fixed'));
        else
            input.attr('placeholder', input.data('ph-open'));
    });

    $("[name=rfmp_after_payment]").live('change', function() {
        if ($(this).val() == 'redirect')
        {
            $('.rfmp_after_payment_redirect').show();
            $('.rfmp_after_payment_message').hide();
        }
        else
        {
            $('.rfmp_after_payment_redirect').hide();
            $('.rfmp_after_payment_message').show();
        }
    });

    $(".rfmp_type").live('change', function() {
        if ($(this).val() == 'dropdown' || $(this).val() == 'radio')
            $(this).closest('td').next('td').next('td').find(".rfmp_value").show();
        else
            $(this).closest('td').next('td').next('td').find(".rfmp_value").val('').hide();
    });

    $('#rfmp_tabs').tabs();

});