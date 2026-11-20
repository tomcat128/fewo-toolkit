<?php

namespace Concrete\Package\FewoToolkit\Controller\SinglePage\Dashboard;

use Concrete\Core\Page\Controller\DashboardPageController;

class FewoToolkit extends DashboardPageController
{
    public function view()
    {
        return $this->buildRedirect('/dashboard/fewo_toolkit/pricelists');
    }
}
