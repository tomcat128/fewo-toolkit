<!DOCTYPE html>
<html>
<head>
    <?php Loader::element("header_required"); ?>
    <title>Fewobacher</title>
    <meta charset="utf-8"/>
    <link rel="shortcut icon" href="/packages/fewo_themes/themes/fewobacher/<?php echo WEBSITE_ID;?>.ico" type="image/x-icon"/>
    <link rel="apple-touch-icon" href="/packages/fewo_themes/themes/fewobacher/images/safari/apple-touch-icon-<?php echo WEBSITE_ID;?>.png"/>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@400;700&display=swap" rel="stylesheet"> 
    <link href="https://fonts.googleapis.com/css2?family=Courgette&display=swap" rel="stylesheet"> 
    <link href="https://fonts.googleapis.com/css2?family=Lora&display=swap" rel="stylesheet"> 
    <link href="https://fonts.googleapis.com/css2?family=Crimson+Text&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Crimson+Text&family=Tinos&display=swap" rel="stylesheet"> 
    <link href="https://fonts.googleapis.com/css2?family=Roboto+Condensed&display=swap" rel="stylesheet"> 
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="/packages/fewo_themes/assets/scripts/jquery-ui.1_13_1.min.js"></script>
    <link href="/packages/fewo_themes/assets/styles/jquery-ui.1_13_1.min.css" rel="stylesheet" type="text/css" media="screen"/>
    <link href="<?=$this->getThemePath()?>/styles/basic.css" rel="stylesheet">
    <link href="<?=$this->getThemePath()?>/styles/custom.css" rel="stylesheet">
    <link href="<?=$this->getThemePath()?>/styles/mobile.css" rel="stylesheet">
    <link href="<?=$this->getThemePath()?>/styles/fewobacher.css" rel="stylesheet">
    <?php
    /*
    <link href="<?=$this->getThemePath()?>/styles/all-in-one.min.css" rel="stylesheet">
    <link href="<?=$this->getThemePath()?>/styles/basic.css" rel="stylesheet">
    <link href="<?=$this->getThemePath()?>/styles/custom.css" rel="stylesheet">
    <link href="<?=$this->getThemePath()?>/styles/mobile.css" rel="stylesheet">
    <link href="<?=$this->getThemePath()?>/styles/fewobacher.css" rel="stylesheet">
    */
    ?>
    <?php
        $user_is_logged_in = false;
        $u = new User();
        if($u->isLoggedIn()) {$user_is_logged_in = true;}

        if ($user_is_logged_in)
        {
            ?>
<style type="text/css">   
/* Adjustments when user logged in as administrator */
.logged_in .header .content {
    margin-top: 48px;
}
body.logged_in {
    margin-top: 105px;
}
</style>
            <?php
        }
    ?>
    <?php
        if (!$user_is_logged_in) {
            // Code if User is not in EditMode
            ?>
<!--     <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.14.0/css/all.min.css" integrity="sha512-1PKOgIY59xJ8Co8+NE6FZ+LOAZKjy+KY8iq0G4B3CyeY6wYHN3yt9PW0XpSriVlkMXe40PTKnXrLnZ9+fkDaog==" crossorigin="anonymous" /> -->
    <link href="<?=$this->getThemePath()?>/fontawesome5/css/all.min.css" rel="stylesheet">
            <?php
            if (!empty($matomo_tracking_code))
            {
                echo $matomo_tracking_code;
            }

            // Icons Font Awesome
            $fa_home = "fad fa-home";
            $fa_phone = "fas fa-phone-rotary";
            $fa_whatsapp = "fab fa-whatsapp";
            $fa_telegram = "fab fa-telegram";
            $fa_security = "fas fa-shield-check";
            $fa_imprint = "fad fa-info-square";
            $fa_close = "fa fa-times-circle";
        } else {
            ?>

            <?php

            // Icons Font Awesome
            $fa_home = "fa fa-home";
            $fa_phone = "fa fa-phone";
            $fa_whatsapp = "";
            $fa_telegram = "";
            $fa_security = "fa fa-shield";
            $fa_imprint = "fa fa-file-text-o";
            $fa_close = "fa fa-close";
        } 
    ?>
</head>
<body id="<?php echo strtolower($_ENV['APP_ID']);?>" class="fewobacher <?php if($user_is_logged_in) { echo " logged_in";}?>">
    <div class="header">
        <div class="content">
            <div class="mobile mobile-menu">
                <ul class="home">
                    <li><a href="#menu"><i class="fa fa-bars"></i> <?php echo $type;?> <?php echo $fewo;?></a></li>
                </ul>
                <nav id="menu">
                    <a href="#close" class="m-menu m-nav-bg">
                        <i class="<?php echo $fa_close;?> close"></i>
                    </a>
                    <div class="m-menu m-nav">
                        <ul class="nav">
                            <li class="m-title"><span><em><?php echo $type;?> <?php echo $fewo;?></em></span></li>
                            <li><a href="/"><i class="<?php echo $fa_home;?>"></i> Startseite</a></li>
                            <li class="quick-funcs">
                                <a href="tel:+491601221223" class="phone"><i class="fas fa-phone-rotary"></i> <em>Telefon</em></a>
                                <a href="/kontakt" class="email"><i class="fa fa-envelope"></i> <em>E-Mail</em></a>
                                <!-- <a href="https://t.me/DanielaFehrenbacher" class="whatsapp" target="_blank"><i class="<?php echo $fa_telegram;?>"></i> <em>Telegram</em></a> -->
                                <div class="clearfix"></div>
                            </li>
                        </ul>
                        <?php
                        (new GlobalArea('WWW Navigation'))->display($c);
                        ?>
                        <ul class="nav">
                            <li><a href="/lage-anfahrt">Lage &amp; Anfahrt</a></li>
                            <li><a href="/datenschutz"><i class="<?php echo $fa_security;?>"></i> Datenschutz</a></li>
                            <li><a href="/impressum"><i class="<?php echo $fa_imprint;?>"></i> Impressum</a></li>
                        </ul>
                    </div>
                </nav>
            </div>
            <ul class="home desktop">
                <li class="home"><a href="/"><i class="<?php echo $fa_home;?>"></i> <span class="mobile"><?php echo $type;?></span> <?php echo $fewo;?></a></li>
            </ul>
            <div class="desktop">
		<div class="navigation">
	            <?php
	                (new GlobalArea('WWW Navigation'))->display($c);
        	    ?>
		</div>
            </div>
        </div>
        <div class="image">
            <div class="title-in-background"><?php echo $type;?> <?php echo $fewo;?></div>
        </div>
    </div>
    <div class="body">
        <div class="content">
            <?php
			/*
            if (strtolower($_ENV['APP_ID']) == "seeherz")
            {
            echo "Bitte beachten Sie: Diese Website befindet sich noch im Aufbau und ist daher noch unvollständig.<br/><br/>Sie können in der Zwischenzeit noch alte Seite besuchen: <a href=\"https://www.ferienwohnung-reichenau.com/\">https://www.ferienwohnung-reichenau.com/</a><br/><br/>";
            }
			*/
            ?>
            <?php
                $a = new Area("Main");
                $a->display($c);
            ?>
            <div class="desktop">
                <br />
                <center><a href="#top" class="link-top" style="color: #808080; font-size: 50px; margin-bottom: 40px;"><i class="fa fa-arrow-circle-up"></i></a></center>
            </div>
        </div>
    </div>
    <div class="footer">
        <div class="content">
            <div class="row imprint">
                <?php
                    (new GlobalArea('Footer Imprint'))->display($c);
                ?>
            </div>
            <div class="row links-local">
                <?php
                    (new GlobalArea('Footer Links Local'))->display($c);
                ?>
            </div>
            <div class="row links-global">
                <?php
                    (new GlobalArea('Footer Links Global'))->display($c);
                ?>
            </div>
        </div>
        <hr />
    </div>
    <a class="mobile top-page" href="#top">
        <i class="fa fa-chevron-up"></i>
    </a>
    <?php Loader::element("footer_required"); ?>
    <style type="text/css">
    .cookie-hint {
        display: block;
        position: fixed;
        bottom: 0;
        width: 100%;
        margin: 0 auto;
        text-align: center;
        background: rgba(64, 64, 64, 0.8);
        color: white;
        padding: 10px 0;
    }
    .cookie-hint a {
        color: white;
    }
    </style>
    <?php
    define("COOKIE_NAME", "FEWO_COOKIE_" . WEBSITE_ID);
    define("COOKIE_VALUE", "dsbld_ckinfo");

    // Cookie set by JavaScript and read by PHP
    if (!(isset($_COOKIE[COOKIE_NAME]) && $_COOKIE[COOKIE_NAME] == COOKIE_VALUE))
    {
    ?>
    <div class="cookie-hint">
        <p>
            <strong>Hinweis zur Verwendung von Cookies</strong>
            <br /><br />
            Diese Website verwendet Cookies. Wenn Sie diese Website weiterhin nutzen, stimmen Sie der Verwendung von Cookies zu. 
            <br /><br />
            <a href="./datenschutz#cookies" class="more">Weitere Informationen anzeigen</a> | <a href="#" class="disable">Diese Meldung nicht mehr anzeigen</a>
        </p>
    </div>
    <script type="text/javascript">
        function setCookie(cname, cvalue, exdays) {
            var d = new Date();
            d.setTime(d.getTime() + (exdays*24*60*60*1000));
            var expires = "expires="+ d.toUTCString();
            document.cookie = cname + "=" + cvalue + ";" + expires + ";path=/";
        }

        $(".cookie-hint a.disable").on("click", function(e) {
            e.preventDefault();
            $(".cookie-hint").hide();
            setCookie("<?php echo COOKIE_NAME;?>", "dsbld_ckinfo", 365);
        });

        $(document).ready(function() {
            $(".cookie-hint").css({"opacity": 0}).animate({"opacity": 1}, 250);
        });
    </script>
    <?php
    }
    ?>
</body>
</html>
