<div class="row">
    <div class="col-md-12 ccm-dashboard-section-menu">
        <h2><?php echo t("fwbDashboardOverview");?></h2>
        <ul class="list-unstyled">
            <li><a href="<?php echo URL::to("/dashboard/fewo_toolkit/pricelist");?>">Preislisten</a></li>
            <li><a href="<?php echo URL::to("/dashboard/fewo_toolkit");?>"  style="text-decoration: line-through;">Kontaktformular</a></li>
        </ul>
    </div>
    <div class="col-md-12 ccm-dashboard-section-menu">
        <h2>ToDo's</h2>
        <ul class="list-unstyled">
            <li style="color: gray;">Blöcke &amp; Backend-Tools
                <ul>
                    <li>Infrastrukturkarten / Standorte (mit Leaflet JS) [+BACKEND]</li>
                    <li style="text-decoration: line-through;">Block only: Preisrechner (Auswahl einer aus dem Backend erstellten Preisliste)</li>
                    <li>Kontaktformular (<span style="text-decoration: line-through;">mit Backend? -> vermutlich besser</span>)</li>
                    <!-- <li>SPÄTER: Belegungsplan (selbst konfigurierbar)</li> -->
                </ul>
            </li>
            <li style="color: gray;">Cronjobs
                <ul>
                    <li style="text-decoration: line-through;">Belegungsplan</li>
                    <li style="text-decoration: line-through;">Preisliste (falls im Import-Modus)</li>
                </ul>
            </li>
        </ul>
    </div>
</div>
