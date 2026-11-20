$(document).ready(function() {
    // Build Mobile Navigation
    $("#mobile-navigation").html("");

    $("#desktop-navigation ul:first.nav > li").each(function() {
        if ($(this).attr("hide_mobile") != "1") {
            var new_el = $(this).clone();
                
            $("#mobile-navigation").append(new_el);

            if ($(this).find("ul").length != 0) {
                // Clone first
                var top_first = $(new_el).find("a:first").clone();
                // The set attribute
                $(new_el).find("a:first").attr("is_parent", "1");

                var alt_title = $(top_first).attr("alt_title");
                if (typeof alt_title == "string") {
                    $(top_first).html(alt_title);
                }
                
                $(new_el).find("ul").prepend("<li>");
                $(new_el).find("ul li:first").append(top_first);
                
                $(new_el).find("a:first").append('<div class="nav-icon"><i class="fa fa-chevron-up mobile-menu-arrow" aria-hidden="true"></i></div>');
            }
        }
    });

    $("#desktop-navigation .links-external ul.nav > li").each(function() {
        // Show external links
        if (!$(".mobile-external-box").is(":visible")) {
            $(".mobile-external-box").show();
        }

        var new_el = $(this).clone();
                
        $("#mobile-external-navigation").append(new_el);
    });
    
    // Accordion
    $("#mobile-navigation a[is_parent=1]").on("click", function(e) {
        // Don't follow Links
        e.preventDefault();

        // Check if menu already open
        var open_menu = true;
        if ($(this).parent().hasClass("active")) {
            open_menu = false;
        }
        
        // Close all
        $("#mobile-navigation li").removeClass("active");
        $("#mobile-navigation li ul").slideUp();
        $("#mobile-navigation li i.mobile-menu-arrow").removeClass("fa-rotate-180");

        if (open_menu) {
            $(this).parent().find("i.mobile-menu-arrow").addClass("fa-rotate-180");
            $(this).parent().find("ul").slideDown();
            $(this).parent().addClass("active");
        }
    });
    // Accordion End

    $(".nav-hamburger, .mobile-menu-close").on("click", function() {
        $(".mobile-menu").toggleClass("menu-open");
        
        if ($(".mobile-menu").hasClass("menu-open")) {
            $(".body-overlay").show();
            $("body").addClass("no-scroll");
        } else {
            $(".body-overlay").hide();
            $("body").removeClass("no-scroll");
        }
    });

    $(".body-overlay").on("click", function() {
        $(".mobile-menu").removeClass("menu-open");
        $(".mobile-language-select").removeClass("open");
        $("body").removeClass("no-scroll");
        $(this).hide();
    });

    $(".mobile .nav-language img").on("click", function() {
        $(".body-overlay").show();
        $("body").addClass("no-scroll");
        $(".mobile-language-select").addClass("open");
    });

    $(".mobile-language-select .window-close").on("click", function() {
        $(".body-overlay").hide();
        $("body").removeClass("no-scroll");
        $(".mobile-language-select").removeClass("open");
    });

    $(".mobile-language-switch").on("change", function() {
        window.location.href = $(this).val();
    });

});