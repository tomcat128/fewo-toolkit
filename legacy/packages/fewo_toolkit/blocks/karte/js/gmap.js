/* Hier alles zur Darstellung der Karte */
var gmap = null;
var gmap_defaults = null;
var gmap_markers = new Array();

function mapCenterSonnenseele()
{
    gmap.setCenter(new google.maps.LatLng(47.710252,9.102491));
    gmap.setZoom(14);
}

function mapCenterDefault()
{
    gmap.fitBounds(gmap_defaults.bounds);
}

function mapInitialize(map_container, markers) {
    // Karte + Optionen initialisieren
    var mapOptions = {
        zoom: 12,
        center: new google.maps.LatLng(47.710238,9.102471),
        mapTypeId: google.maps.MapTypeId.ROADMAP, // ROADMAP | SATELLITE | HYBRID | TERRAIN
        mapTypeControl: true,
        mapTypeControlOptions: {
            style: google.maps.MapTypeControlStyle.DROPDOWN_MENU
        },
        zoomControl: true,
        zoomControlOptions: {
            style: google.maps.ZoomControlStyle.NORMAL
        },
        disableDefaultUI: false
    };
    
    gmap = new google.maps.Map(document.getElementById(map_container), mapOptions);
    
    var bounds = new google.maps.LatLngBounds(null, null);
    
    var infowindow = new google.maps.InfoWindow();
    /*
    */
    
    for (i = 0; i < markers.length; i++)
    {
        data = markers[i];
        
        position = new google.maps.LatLng(data.position[0], data.position[1]);
        
        bounds.extend(position);
        
        contentHTML = data.info;
        
        // Neues Marker-Element
        var marker = new google.maps.Marker({
            position: position,
            map: gmap,
            // title: hotel.name, // ACHTUNG: HTML-Entities werden nicht umgewandelt
            html: contentHTML,
            // visible: (data.list == "all" || data.list == null ? true : false),
            visible: false,
            icon: {url: gmap_icons[data.type].icon, anchor: (gmap_icons[data.type].anchor != null ? gmap_icons[data.type].anchor : null)},
            shadow: (gmap_icons[data.type].shadow != null ? {'anchor':gmap_icons[data.type].shadow.anchor,'url':gmap_icons[data.type].shadow.icon} : null)
        });
        
        google.maps.event.addListener(marker, "click", function() {
            infowindow.setContent(this.html);
            infowindow.open(gmap, this);
        });
        
        gmap_markers.push({id: i, marker: marker, info: infowindow});
        
        
        //gmap_markers[0].marker.setVisible(false);
    }
    
    if (markers.length > 1)
    {
        gmap.fitBounds(bounds);
        
        gmap_defaults = {"bounds": bounds};
    }
}

function ShowMarkers(markers, list, types, fit_to_bounds)
{
    // alert(list+"\n"+type);
    
    var bounds = new google.maps.LatLngBounds(null, null);
    
    for (i = 0; i < gmap_markers.length; i++)
    {
        var point = markers[gmap_markers[i].id];
        
        if (point.list == list && (point.fewo == true || types == null || types['all'] == true || types[point.type] == true) )
        {
            gmap_markers[i].marker.setVisible(true);
            
            bounds.extend(new google.maps.LatLng(point.position[0], point.position[1]));
        } else {
            gmap_markers[i].marker.setVisible(false);
        }
    }
    
    if (fit_to_bounds == true)
    {
        gmap.fitBounds(bounds);
        
        gmap_defaults = {"bounds": bounds};
    }
}