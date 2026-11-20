<?php
    echo '<?xml version="1.0" encoding="UTF-8"?>';
    include __DIR__."/elements/xml_header.php";
?>

<?php
if (isset($status)) {
    echo "<status>";
    echo "<type>".$status['type']."</type>";
    echo "<message>".$status['message']."</message>";
    echo "</status>";
}
?>

<?php
$is_indexed = function (&$array) {
    if (is_array($array) && count($array) > 0 && isset($array[0])) {
        foreach ($array as $key => $value) {
            if (!is_int($key)) {
                return false;
            }
        }
        return true;
    }
    return false;
};

$print_xml_data = function ($xml, $parent = null) use (&$print_xml_data, $is_indexed) {
    foreach ($xml as $key => $value) {
        if (is_array($value) && $is_indexed($value)) {
            $list = $key;
        } else {
            unset($list);
        }

        // Nur wenn String
        if (!is_int($key) && !isset($list)) {
            echo "<".$key.">";
        } elseif (isset($parent))
            echo "<".$parent.">";

        // Wenn Array, dann gehe Rekursiv weiter, ansonsten Wert ausgeben
        if (is_array($value)) {
            $print_xml_data($value, $list);
        } else {
            echo $value;
        }

        // Nur wenn String
        if (!is_int($key) && !isset($list)) {
            echo "</".$key.">";
        } elseif (isset($parent)) {
            echo "</".$parent.">";
        }
    }
};

if (isset($data)) {
    $print_xml_data($data);
}
?>

<?php
    include __DIR__."/elements/xml_footer.php";
