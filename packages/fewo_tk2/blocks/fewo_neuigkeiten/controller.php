<?php

namespace Concrete\Package\FewoTk2\Block\FewoNeuigkeiten;

use Concrete\Core\Block\BlockController;
use DateTimeImmutable;
use DateTimeZone;
use FewoTk2\Apartment\ApartmentRepository;
use FewoTk2\Block\AddDefaults;
use FewoTk2\Block\ApartmentBlock;
use FewoTk2\News\ImportanceLevels;
use FewoTk2\News\NewsLoader;

/**
 * Neuigkeiten einer Wohnung aus Fewo-Office. Eine Neuigkeit erscheint nur, solange der heutige Tag in ihren
 * Zeitraum fällt; gibt es keine, zeigt der Block nichts an.
 */
class Controller extends BlockController
{
    use AddDefaults;
    use ApartmentBlock;

    public $apartmentID = 0;
    public $title = '';
    public $showPeriod = true;
    public $importanceLevels = '';

    protected $btTable = 'btFewoNeuigkeiten';
    protected $btInterfaceWidth = 680;
    protected $btInterfaceHeight = 600;
    protected $btDefaultSet = 'fewo_tk2';
    protected $btCacheBlockRecord = true;
    // Kein Output-Cache: Was angezeigt wird, hängt vom Tag und vom Stand in Fewo-Office ab.
    protected $btCacheBlockOutput = false;

    public function getBlockTypeName()
    {
        return t('News');
    }

    public function getBlockTypeDescription()
    {
        return t('Shows the current news of a holiday apartment from Fewo-Office – each one only during its period of validity.');
    }

    public function getSearchableContent()
    {
        // Nur die Überschrift: Die Neuigkeiten selbst gelten nur eine Zeit lang und gehören nicht in den Suchindex.
        return (string) $this->title;
    }

    public function add()
    {
        $this->setAddDefaults();
        $this->edit();
    }

    public function edit()
    {
        $this->setApartmentOptions(t('** Please select'));
        $this->set('levels', ImportanceLevels::decode((string) $this->importanceLevels));
    }

    public function view()
    {
        $today = new DateTimeImmutable('today', new DateTimeZone(date_default_timezone_get()));
        $apartment = $this->getApartment();
        $levels = ImportanceLevels::decode((string) $this->importanceLevels);
        $items = [];
        $feed = null;
        if ($apartment === null) {
            $error = $this->getMissingApartmentMessage();
        } elseif ($apartment->getNewsUrl() === '') {
            $error = t('No news URL is stored for this apartment.');
        } else {
            $loaded = $this->app->make(NewsLoader::class)->load($apartment->getNewsUrl(), $apartment->getRefreshMinutes());
            $items = $loaded->current($today, $levels);
            $feed = $loaded->feed;
            $error = $loaded->error;
        }

        $this->set('items', $items);
        $this->set('levels', $levels);
        $this->set('feed', $feed);
        $this->set('error', $error);
        $this->set('canEdit', $this->canEditPage());
        $this->set('dateService', $this->app->make('date'));
    }

    public function validate($args)
    {
        $errors = $this->app->make('helper/validation/error');
        $apartment = $this->app->make(ApartmentRepository::class)->find((int) ($args['apartmentID'] ?? 0));
        if ($apartment === null) {
            $errors->add(t('Please select an apartment.'));
        } elseif ($apartment->getNewsUrl() === '') {
            $errors->add(t('No news URL is stored for the apartment "%s". Please add it in the dashboard under Fewo Toolkit 2 › Apartments.', $apartment->getName()));
        }
        $codes = array_map(ImportanceLevels::code(...), is_array($args['levelCode'] ?? null) ? $args['levelCode'] : []);
        if (in_array('', $codes, true)) {
            $errors->add(t('Each importance level needs a code (letters, digits, "-" or "_", e.g. info).'));
        }
        if (count($codes) !== count(array_unique($codes))) {
            $errors->add(t('Each code may only be assigned to one importance level.'));
        }

        return $errors;
    }

    public function save($args)
    {
        parent::save([
            'apartmentID' => (int) ($args['apartmentID'] ?? 0),
            'title' => trim((string) ($args['title'] ?? '')),
            'showPeriod' => !empty($args['showPeriod']) ? 1 : 0,
            'importanceLevels' => ImportanceLevels::encode(ImportanceLevels::fromForm($args['levelCode'] ?? [], $args['levelLabel'] ?? [], $args['levelColor'] ?? [])),
        ]);
    }
}
