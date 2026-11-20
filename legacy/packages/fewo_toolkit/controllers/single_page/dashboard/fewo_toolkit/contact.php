<?php
namespace Concrete\Package\FewoToolkit\Controller\SinglePage\Dashboard\FewoToolkit;

use \Concrete\Core\Page\Controller\DashboardPageController;
use BlockTypeSet;
use BlockTypeList;
use BlockType;
use Loader;

class Contact extends DashboardPageController
{
    protected $error;

    /**
     * c5 method on_start
     * fill the default values
     *
     * @return null
     */
    public function on_start()
    {
        parent::on_start();
    }

    /**
     * c5 method before render
     * configure the buttons in layout
     *
     * @return null
     */
    public function on_before_render()
    {
        parent::on_before_render();

        $this->addFooterItem('<script type="text/javascript">
        $(function() {
            $("ul.ccm-attribute-sortable-set-list").sortable({
                // opacity: 0.5,
                // stop: function() {
                //     var ualist = $(this).sortable(\'serialize\');
                //     ualist += \'&categoryID='.(123).'\';
                //     $.post(\''.REL_DIR_FILES_TOOLS_REQUIRED.'/dashboard/attribute_set_order_update\', ualist, function(r) {

                //     });
                // }
            });
        });
    </script>');
    }

    public function update()
    {
        // Wird aufgerufen wenn Form gespeichert wird (bevor 'on_before_render')
        echo "<pre>";
        var_dump($_REQUEST);
        echo "</pre>";
    }

}
