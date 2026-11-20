<?php
namespace Concrete\Package\FewoToolkit\Controller\SinglePage\Fewobacher;

use \Concrete\Core\Page\Controller\PageController;
use Loader;

define("PATH_TO_CACHE_DIR", "application/files/cache");
define("PLUGIN_MAX_CACHE_TIME", 8*60*60);

class Plugins extends PageController
{

    /**
     * c5 method on_start
     * fill the default values
     *
     * @return null
     */
    public function on_start()
    {
        parent::on_start();
        $this->set("content", "Default plugin page. Please specify your desired plugin.");
    }

    public function on_before_render()
    {
        parent::on_before_render();
    }

    private function getData($handle, $uri, $options = array())
    {
        $fn = PATH_TO_CACHE_DIR."/".$handle;

        if (!file_exists($fn) || (filemtime($fn) + PLUGIN_MAX_CACHE_TIME < time()))
        {
            if (isset($options) && count($options) > 0)
            {
                $data = @file_get_contents($uri, false, stream_context_create($options));
                // echo $uri;
                // echo "<br>";
            } else {
                $data = @file_get_contents($uri);
                // echo $uri;
                // echo "<br>";
            }

            if ($data !== false)
            {
                file_put_contents($fn, $data);
            }
        }

        return file_get_contents($fn);
    }

    private static function showImage($data)
    {
        header("Content-type: image/jpg");
        echo $data;
        die();
    }

    public function facebook_likes($id)
    {
        if ($id != "seeherz")
        {
            // Not a valid ID!!!
            echo "Invalid id.";
            die();
        }
        $data_uri = "https://www.facebook.com/{$id}/";

        $options = array(
          'http'=>array(
            'method'=>"GET",
            'header'=>"Accept-language: en\r\n" .
                      "Cookie: foo=bar\r\n" .  // check function.stream-context-create on php.net
                      "User-Agent: Mozilla/5.0 (iPad; U; CPU OS 3_2 like Mac OS X; en-us) AppleWebKit/531.21.10 (KHTML, like Gecko) Version/4.0.4 Mobile/7B334b Safari/531.21.102011-10-16 20:23:10\r\n" // i.e. An iPad 
          )
        );

        $fb_page = $this->getData("facebook-likes-{$id}.html", $data_uri, $options);

        preg_match("/<div>([0-9]{1,}) (people like this)<\/div>/is", $fb_page, $matches);
        
        $fb_likes = $matches[1];
        
        $this->set("content", $fb_likes);
    }

    public function homeaway_com($id)
    {
        $data_uri = "https://www.fewo-direkt.de/haow/api/image/homeaway_de/trips/".$id."/reviews";

        self::showImage($this->getData("../homeaway/homeaway-{$id}.jpg", $data_uri));
    }

    public function bodenseepegel_net($location)
    {
        if ($location == "reichenau")
        {
            $data_uri = "http://www.bodenseee.net/pegel/pegel21.php";
        }

        if (isset($data_uri))
        {
            self::showImage($this->getData("bodenseepegel-{$location}.jpg", $data_uri));
        }

        echo "Invalid location.";
        die();
    }

    public function pegelstand($location)
    {
        date_default_timezone_set("CET");
        // https://www.seeschatz.com/fewobacher/plugins/pegelstand/konstanz
        if ($location == "konstanz")
        {
            $id_to_show = "00007";
        } else
        if ($location == "konstanz_rhein")
        {
            $id_to_show = "00040";
        } else
        if ($location == "radolfzell")
        {
            $id_to_show = "00215";
        } else
        if ($location == "friedrichshafen")
        {
            $id_to_show = "00353";
        } else {
            echo "Invalid location.";
            die();
        }

        $data_uri = "https://www.fewobacher.de/pegel/";
        $data_uri_water = "https://www.fewobacher.de/pegel/water2.jpg";

        if (isset($data_uri))
        {
            $data = json_decode($this->getData("pegelstand-{$location}.json", $data_uri));
            $data_image = $this->getData("pegelstand-water.jpg", $data_uri_water);

            foreach ($data->pegelstaende as $values) {
                if ($values->ID == $id_to_show)
                {
                    $Wasserstand = $values;
                    // $Wasserstand->Region = "";
                    // $Wasserstand->Datum = date("d.m.Y, H:i:s", filemtime($data));
                    $Wasserstand->Datum = date("d.m.Y, H:i", strtotime($values->Zeitpunkt));
                }
            }

            if (!isset($Wasserstand))
                die();

            $ph_imageBase64 = base64_encode($data_image);
            $ph_id = $Wasserstand->ID;
            $ph_ort = $Wasserstand->Ort.(!empty($Wasserstand->Region) ? ' '.str_replace(array("Bodensee (Untersee)", "Bodensee", "Rhein"), array("(Untersee)", "", "(Rhein)"), $Wasserstand->Region) : '');
            $ph_pegel = $Wasserstand->Wasserstand.' '.$Wasserstand->Einheit;
            $ph_zeitpunkt = $Wasserstand->Datum;

            echo <<<PEGELSTAND_WIDGET
<style type="text/css">
a.pegelstand {
    text-decoration: none;
    display: block;
    width: 202px;
    height: 129px;
    clear: left;
    float: left;
    background-image: url(data:image/jpg;base64,{$ph_imageBase64}); background-size: 202px 129px; border-radius: 10px;
}
a.pegelstand .ort {
    text-align: center; font-size: 18px; color: black; padding: 15px 0; font-style: italic; width :100%; clear: left; float: left; color: white;
    text-shadow: 0px 0px 2px rgba(0,0,0,0.75);
}
a.pegelstand .pegel {
    text-align: center; font-size: 28px; color: yellow; font-weight: bold; width :100%; clear: left; float: left;
    text-shadow: 2px 2px 2px rgba(0,0,0,0.75);
}
a.pegelstand .zeitpunkt {
    text-align: center; font-size: 12px; color: white; padding: 15px 0; font-style: italic; width: 100%; clear: left; float: left;
    text-shadow: 0px 0px 2px rgba(0,0,0,0.75);
}
</style>
<a href="https://www.hvz.baden-wuerttemberg.de/pegel.html?id={$ph_id}" target="_blank" class="pegelstand" title="Aktueller Pegel Radolfzell Untersee">
    <div class="ort">{$ph_ort}</div>
    <div class="pegel">{$ph_pegel}</div>
    <div class="zeitpunkt">{$ph_zeitpunkt} Uhr</div>
</a>
PEGELSTAND_WIDGET;
        }

        die();
    }

    public function toolbar($fewo)
    {
        if ($fewo == "sonnenseele")
        {
            $uri_data_homeaway = "";
            $uri_data_wetter   = base64_encode($this->getData("wetter-allensbach.png", "https://www.daswetter.com/wimages/fotob81f78e123d62d7c7b56cfec72a7eb1a.png"));
            $uri_data_pegel_1  = $this->getData("pegel_radolfzell.html", "https://www.ferienwohnung-hegne.de/fewobacher/plugins/pegelstand/radolfzell");
            echo <<<TOOLBAR_WIDGET
<style type="text/css">
.toolbar {
    margin: 20px 0;
    width: 100%;
    clear: left;
    float: left;
}
.toolbar img {
    padding: 0;
    margin: 0;
    box-shadow: none;
}
.toolbar .homeaway {
    clear: left;
    float: left;
    width: 202px;
}
.toolbar .wetter {
    float: left;
    width: 324px;
    text-align: center;
}
.toolbar .wetter img {
    border: none;
}
.toolbar .pegel {
    float: left;
    width: 202px;
}
</style>
<div class="toolbar">
    <div class="homeaway">
        <a href="//www.fewo-direkt.de/ferienwohnung-ferienhaus/p2100051" rel="nofollow" target="_blank"><img src="/fewobacher/plugins/homeaway_com/2100051" alt="HomeAway Property" style="border:none;" rel="nofollow"/></a>
    </div>
    <div class="wetter"><a href="https://www.daswetter.com/wetter_Allensbach-Europa-Deutschland-Baden+Wurttemberg--1-95730.html" target="_blank"><img src="data:image/png;base64,{$uri_data_wetter}" height="132" /></a></div>
    <div class="pegel">
        <div>{$uri_data_pegel_1}</div>
    </div>
</div>
TOOLBAR_WIDGET;
        } elseif ($fewo == "seeschatz")
        {
            $uri_data_homeaway = "";
            $uri_data_wetter   = base64_encode($this->getData("wetter-reichenau.png", "https://www.daswetter.com/wimages/foto7bed37d5b200aee9fc8e6209656ddd51.png"));
            $uri_data_pegel_1  = $this->getData("pegel_radolfzell.html", "https://www.seeschatz.com/fewobacher/plugins/pegelstand/radolfzell");
            echo <<<TOOLBAR_WIDGET
<style type="text/css">
.toolbar {
    margin: 20px 0;
    width: 100%;
    clear: left;
    float: left;
}
.toolbar img {
    padding: 0;
    margin: 0;
    box-shadow: none;
}
.toolbar .homeaway {
    clear: left;
    float: left;
    width: 202px;
}
.toolbar .wetter {
    float: left;
    width: 324px;
    text-align: center;
}
.toolbar .wetter img {
    border: none;
}
.toolbar .pegel {
    float: left;
    width: 202px;
}
</style>
<div class="toolbar">
    <div class="homeaway">
        <a href="//www.fewo-direkt.de/ferienwohnung-ferienhaus/p2876682" rel="nofollow" target="_blank"><img src="/fewobacher/plugins/homeaway_com/2876682" alt="HomeAway Property" style="border:none;" rel="nofollow"/></a>
    </div>
    <div class="wetter"><a href="https://www.daswetter.com/wetter_Reichenau-Europa-Deutschland-Baden+Wurttemberg--1-95676.html" target="_blank"><img src="data:image/png;base64,{$uri_data_wetter}" height="132" /></a></div>
    <div class="pegel">
        <div>{$uri_data_pegel_1}</div>
        <!--
        <div>{$uri_data_pegel_2}</div>
        <div>{$uri_data_pegel_3}</div>
        <div>{$uri_data_pegel_4}</div>
        -->
    </div>
</div>
<!--
<link rel="stylesheet" type="text/css" href="//cdn.jsdelivr.net/npm/slick-carousel@1.8.1/slick/slick.css"/>
<script type="text/javascript" src="//cdn.jsdelivr.net/npm/slick-carousel@1.8.1/slick/slick.min.js"></script>
<script type="text/javascript">
    $('.pegel').slick({autoplay: true, autoplaySpeed: 3500, speed: 750, arrows: false});
</script>
-->
TOOLBAR_WIDGET;
        } elseif ($fewo == "seeherz") {
            $data_wetter = base64_encode($this->getData("wetter-reichenau.png", "https://www.daswetter.com/wimages/foto7bc567e4df37ed7c95778c8332795a22.png"));
            // $data_pegel  = file_get_contents("https://www.ferienwohnung-reichenau.com/fewobacher/plugins/pegelstand/konstanz");
            $data_pegel  = $this->getData("pegel_konstanz.html", "https://www.ferienwohnung-reichenau.com/fewobacher/plugins/pegelstand/konstanz");
            echo <<<TOOLBAR_WIDGET
<style type="text/css">
.toolbar {
    margin: 20px 0;
    width: 100%;
    clear: left;
    float: left;
}
.toolbar img {
    padding: 0;
    margin: 0;
    box-shadow: none;
}
.toolbar .homeaway_1 {
    clear: left;
    float: left;
    width: 242px;
    padding-left: 10px;
}
.toolbar .homeaway_2 {
    float: left;
    width: 242px;
    text-align: center;
}
.toolbar .pegel {
    float: right;
    width: 212px;
    padding-left: 10px;
}
.toolbar .wetter {
    margin-top: 10px;
    clear: left;
    float: left;
    width: 100%;
    text-align: center;
}
.toolbar .wetter img {
    border: none;
}
</style>
<div class="toolbar">
    <div class="homeaway_1">
        <a href="//www.fewo-direkt.de/ferienwohnung-ferienhaus/p2430868" rel="nofollow" target="_blank"><img src="/fewobacher/plugins/homeaway_com/2430868" title="Seeherz" alt="HomeAway Property" style="border:none;" rel="nofollow"/></a>
    </div>
    <div class="homeaway_2">
        <a href="//www.fewo-direkt.de/ferienwohnung-ferienhaus/p2100157" rel="nofollow" target="_blank"><img src="/fewobacher/plugins/homeaway_com/2100157" title="Seeherzchen" alt="HomeAway Property" style="border:none;" rel="nofollow"/></a>
    </div>
    <div class="pegel">
        <div>{$data_pegel}</div>
    </div>
    <div class="wetter"><a href="https://www.daswetter.com/wetter_Reichenau-Europa-Deutschland-Baden+Wurttemberg--1-95676.html" target="_blank"><img src="data:image/png;base64,{$data_wetter}" /></a></div>
</div>
TOOLBAR_WIDGET;
        } else {
            die("Unknown fewo.");
        }

        die();
    }
}
