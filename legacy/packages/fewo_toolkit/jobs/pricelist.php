<?php
namespace Concrete\Package\FewoToolkit\Job;

use Loader;
use \Job as AbstractJob;

use Concrete\Package\FewoToolkit\Block\Preisuebersicht\Controller as PlFwbCtrl;

class Pricelist extends AbstractJob
{
    public function getJobName()
    {
        return t("Preisliste (Fewobacher)");
    }

    /** Returns the job description.
    * @return string
    */
    public function getJobDescription()
    {
        return t("Regelmaessige Updates der Preisliste");
    }

    /** Executes the job.
    * @return string Returns a string describing the job result in case of success.
    * @throws Exception Throws an exception in case of errors.
    */
    public function run()
    {
        try {
            $db = Loader::db();

            $i = 0;

            foreach (PlFwbCtrl::getAvailableLists() as $pricelist) {
                $settings = unserialize($pricelist['settings']);
                
                $import = $settings['import'];

                if (isset($import) && isset($import['url']) && !empty($import['url'])) {
                    $url = $import['url'];

                    if (isset($import['import']['password']) && !empty($import['import']['password'])) {
                        $url .= "/".$import['import']['password'];
                    }

                    PlFwbCtrl::importById($pricelist['listID']);

                    $i++;
                }
            }

            return t("Aktualsierte Preislisten: %s", $i);
        } catch (Exception $e) {
            throw $e;
        }
    }
}
