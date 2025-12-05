jQuery(document).ready(function($) {
    var $buttons  = $(".gb-tab-title");
    var $contents = $(".gb-tab-content");

    $buttons.on("click", function() {
        var $btn = $(this);

        $buttons.removeClass("active");
        $btn.addClass("active");

        $contents.each(function() {
            var $content = $(this);
            if ($content.attr("id") === $btn.data("tab")) {
                $content.show();
            } else {
                $content.hide();
            }
        });
    });

    if ($buttons.length) {
        $buttons.first().addClass("active");
    }
});
