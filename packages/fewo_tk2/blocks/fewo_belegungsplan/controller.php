<?php

namespace Concrete\Package\FewoTk2\Block\FewoBelegungsplan;

use Concrete\Core\Block\BlockController;
use Concrete\Core\Logging\Channels;
use Concrete\Core\Logging\LoggerFactory;
use DateTimeImmutable;
use DateTimeZone;
use FewoTk2\Apartment\ApartmentRepository;
use FewoTk2\Block\AddDefaults;
use FewoTk2\Block\ApartmentBlock;
use FewoTk2\Calendar\BookingReader;
use FewoTk2\Calendar\MonthGrid;
use FewoTk2\Calendar\Occupancy;
use FewoTk2\Calendar\OccupancyLoader;
use FewoTk2\Feed\FeedLoader;
use FewoTk2\Feed\InvalidFeedException;

class Controller extends BlockController
{
    use AddDefaults;
    use ApartmentBlock;

    public $apartmentID = 0;
    public $title = '';
    public $monthsVisible = 4;
    public $monthsTotal = 12;
    public $onRequestKeywords = '';
    public $showLegend = true;

    protected $btTable = 'btFewoBelegungsplan';
    protected $btInterfaceWidth = 600;
    protected $btInterfaceHeight = 520;
    protected $btDefaultSet = 'fewo_tk2';
    protected $btCacheBlockRecord = true;
    // Kein Output-Cache: Die Belegung ändert sich unabhängig vom Block.
    protected $btCacheBlockOutput = false;

    public function getBlockTypeName()
    {
        return t('Occupancy Calendar');
    }

    public function getBlockTypeDescription()
    {
        return t('Shows free and booked days. The occupancy is loaded from Fewo-Office.');
    }

    public function getSearchableContent()
    {
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
    }

    public function view()
    {
        $today = new DateTimeImmutable('today', new DateTimeZone(date_default_timezone_get()));
        $monthsTotal = max(1, (int) $this->monthsTotal);
        $error = null;
        $occupancy = new Occupancy();
        $feed = null;

        $apartment = $this->getApartment();
        $url = $apartment !== null ? FeedLoader::normalizeUrl($apartment->getOccupancyUrl()) : null;
        if ($apartment === null) {
            $error = $this->getMissingApartmentMessage();
        } elseif ($url === null) {
            $error = t('No valid occupancy URL has been configured.');
        } else {
            $feed = $this->app->make(OccupancyLoader::class)->load($url, $apartment->getRefreshMinutes());
            if ($feed->getBody() === null) {
                $error = t('The occupancy could not be loaded yet.');
            } else {
                try {
                    $firstOfMonth = $today->modify('first day of this month');
                    $occupancy = new Occupancy((new BookingReader($today->getTimezone(), $this->getOnRequestKeywords()))->read(
                        $feed->getBody(),
                        $firstOfMonth->modify('-1 day'),
                        $firstOfMonth->modify('+' . ($monthsTotal + 1) . ' months')
                    ));
                } catch (InvalidFeedException $e) {
                    $error = t('The occupancy data is invalid.') . ' ' . $e->getMessage();
                    $this->app->make(LoggerFactory::class)->createLogger(Channels::CHANNEL_APPLICATION)
                        ->warning('Fewo Toolkit: ' . $e->getMessage(), ['url' => $url]);
                }
            }
        }

        $this->set('months', MonthGrid::build($occupancy, $today, $monthsTotal));
        $this->set('monthsVisible', max(1, min((int) $this->monthsVisible, $monthsTotal)));
        $this->set('hasRequestStatus', $this->getOnRequestKeywords() !== []);
        $this->set('feed', $feed);
        $this->set('error', $error);
        $this->set('canEdit', $this->canEditPage());
        $this->set('dateService', $this->app->make('date'));
    }

    public function validate($args)
    {
        $errors = $this->app->make('helper/validation/error');
        if ($this->app->make(ApartmentRepository::class)->find((int) ($args['apartmentID'] ?? 0)) === null) {
            $errors->add(t('Please select an apartment.'));
        }
        $total = (int) ($args['monthsTotal'] ?? 0);
        if ($total < 1 || $total > 36) {
            $errors->add(t('The number of months must be between 1 and 36.'));
        }

        return $errors;
    }

    public function save($args)
    {
        $total = max(1, min(36, (int) ($args['monthsTotal'] ?? 12)));
        parent::save([
            'apartmentID' => (int) ($args['apartmentID'] ?? 0),
            'title' => trim((string) ($args['title'] ?? '')),
            'monthsTotal' => $total,
            'monthsVisible' => max(1, min($total, (int) ($args['monthsVisible'] ?? 4))),
            'onRequestKeywords' => trim((string) ($args['onRequestKeywords'] ?? '')),
            'showLegend' => !empty($args['showLegend']) ? 1 : 0,
        ]);
    }

    /**
     * @return string[]
     */
    public function getOnRequestKeywords(): array
    {
        return array_values(array_filter(array_map('trim', explode(',', (string) $this->onRequestKeywords))));
    }
}
