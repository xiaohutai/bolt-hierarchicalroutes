jQuery(document).ready(function($) {

    $('.expand-all').on('click', function(e){
        e.preventDefault();
        $('.hierarchicalroutes-tree details').attr('open', true);
    });

    $('.collapse-all').on('click', function(e){
        e.preventDefault();
        $('.hierarchicalroutes-tree details').attr('open', false);
    });

});
