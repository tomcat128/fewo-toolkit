<?php
    header($_SERVER['SERVER_PROTOCOL']." 404 Not Found");
    echo file_get_contents(BASE_URL.View::url("/page_not_found"));
    exit;
