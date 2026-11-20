/**
 * Liste auf und zuklappen
 */
$(window).ready(function() {
	$(".details a").on("click", function() {
		var list = $(this).parent().parent().find(".infos");

		var toggle = $(this).parent();

		var toggle_show = $(toggle).find("a[type='show']");
		var toggle_hide = $(toggle).find("a[type='hide']");

		var toggle_time = 400;

		// Liste ein- oder ausblenden
		if ($(this).attr("type") == "show")
		{
			if (!$(list).attr("height"))
			{
				var h = $(list).height();

				$(list).attr("height", h);
			}

			$(list).css({height:'0px'});
			$(list).show();
			$(list).animate({height:$(list).attr("height")}, toggle_time, 'swing');
			
			// Anmerkungen extra einblenden (wg. position absolute)
			$(list).find("span").css({opacity:'0.0'});
			$(list).find("span").animate({opacity:'1.0'}, toggle_time);

			$(toggle).find("a").each(function() {
				if (!$(this).attr("width")) {
					$(this).attr("width", $(this).width());
				}
			});

			toggle_show.animate({'width':'0px', 'opacity':0}, toggle_time, function() {$(this).hide()});

			toggle_hide.css({'width':'0px', 'opacity':0});
			toggle_hide.show();
			toggle_hide.animate({'width':toggle_hide.attr("width"), 'opacity':1}, toggle_time);

		} else {
			$(list).animate({height:'0px'}, toggle_time, 'swing', function() { $(this).hide()});
			
			// Anmerkungen ausblenden
			$(list).find("span").animate({opacity:'0.0'}, 100);

			// $(toggle).find("a[type='show']").show();
			// $(toggle).find("a[type='hide']").hide();
			toggle_hide.animate({'width':'0px', 'opacity':0}, toggle_time, function() {$(this).hide()});

			toggle_show.css({'width':'0px', 'opacity':0});
			toggle_show.show();
			toggle_show.animate({'width':toggle_show.attr("width"), 'opacity':1}, toggle_time);
		}
		
	});
});