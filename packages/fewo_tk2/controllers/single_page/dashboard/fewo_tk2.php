<?php

namespace Concrete\Package\FewoTk2\Controller\SinglePage\Dashboard;

use Concrete\Core\Page\Controller\DashboardPageController;

class FewoTk2 extends DashboardPageController
{
    public function view()
    {
        return $this->buildRedirect('/dashboard/fewo_tk2/inquiries');
    }
}
