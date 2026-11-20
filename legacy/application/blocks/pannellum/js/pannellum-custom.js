function resizeContainer(id, ratio)
{
	$("#" + id).height(Math.ceil($("#"+id).width() * ratio));
}

// Hot spot creation function
function hotspotFontAwesome(hotSpotDiv, args)
{
	hotSpotDiv.classList.add('custom-tooltip');
	// Create span-element
	var span = document.createElement('span');
	span.classList.add("fa");
	span.classList.add(args["fa-icon"]);

	// Add span to hotspot-div
	hotSpotDiv.appendChild(span);
	if (args["fa-size"] != "undefined")
	{
		span.style.fontSize = args["fa-size"];
	}
	// Center icon
	/*span.style.fontSize = "20px";*/
	span.style.marginLeft = (-(span.scrollWidth - hotSpotDiv.offsetWidth) / 2 - 4) + 'px';
	span.style.marginTop = (-(span.scrollHeight - hotSpotDiv.offsetHeight) / 2 - 4) + 'px';
}