<?php

namespace Concrete\Package\FewoToolkit\Block\Belegungsplan;

use Concrete\Core\Block\BlockController;
use Concrete\Core\Http\Client\Client;
use Concrete\Core\Logging\Channels;
use Concrete\Core\Logging\LoggerFactory;
use Concrete\Core\Page\Page;
use Concrete\Core\Permission\Checker;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
use FewoToolkit\Calendar\FeedLoader;
use FewoToolkit\Calendar\IcsParser;
use FewoToolkit\Calendar\InvalidCalendarException;
use FewoToolkit\Calendar\MonthGrid;
use FewoToolkit\Calendar\Occupancy;

class Controller extends BlockController
{
    public const REFRESH_OPTIONS = [15, 30, 60, 120, 360, 720, 1440];

    public $title = '';
    public $icsUrl = '';
    public $refreshMinutes = 60;
    public $monthsVisible = 4;
    public $monthsTotal = 12;
    public $onRequestKeywords = 'Auf Anfrage';
    public $showLegend = true;

    protected $btTable = 'btFewoBelegungsplan';
    protected $btInterfaceWidth = 600;
    protected $btInterfaceHeight = 560;
    protected $btDefaultSet = 'fewo_toolkit';
    protected $btCacheBlockRecord = true;
    // Kein Output-Cache: Die Belegung ändert sich unabhängig vom Block.
    protected $btCacheBlockOutput = false;

    public function getBlockTypeName()
    {
        return t('Occupancy Calendar');
    }

    public function getBlockTypeDescription()
    {
        return t('Shows free and booked days from an iCalendar (ICS) feed, e.g. from Airbnb, Booking.com or Fewo-direkt.');
    }

    public function getSearchableContent()
    {
        return (string) $this->title;
    }

    public function add()
    {
        $this->prepareForm();
    }

    public function edit()
    {
        $this->prepareForm();
        if ($url = FeedLoader::normalizeUrl((string) $this->icsUrl)) {
            $this->set('feed', $this->getFeedLoader()->getFeed($url));
        }
    }

    public function view()
    {
        $timezone = new DateTimeZone(date_default_timezone_get());
        $today = new DateTimeImmutable('today', $timezone);
        $monthsTotal = max(1, (int) $this->monthsTotal);
        $error = null;
        $occupancy = new Occupancy();
        $feed = null;

        $url = FeedLoader::normalizeUrl((string) $this->icsUrl);
        if ($url === null) {
            $error = t('No valid calendar URL has been configured.');
        } else {
            $feed = $this->getFeedLoader()->load($url, (int) $this->refreshMinutes);
            if ($feed->getBody() === null) {
                $error = t('The calendar could not be loaded yet.');
            } else {
                try {
                    $parser = new IcsParser($timezone, $this->getOnRequestKeywords());
                    $occupancy = new Occupancy($parser->parse(
                        $feed->getBody(),
                        $today->modify('first day of this month')->modify('-1 day'),
                        $today->modify('first day of this month')->modify('+' . ($monthsTotal + 1) . ' months')
                    ));
                } catch (InvalidCalendarException $e) {
                    $error = t('The calendar data is invalid.');
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
        $this->set('canEdit', $this->canEditBlock());
        $this->set('dateService', $this->app->make('date'));
    }

    public function validate($args)
    {
        $errors = $this->app->make('helper/validation/error');
        if (FeedLoader::normalizeUrl((string) ($args['icsUrl'] ?? '')) === null) {
            $errors->add(t('Please enter a valid calendar URL (https://… or webcal://…).'));
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
        $data = [
            'title' => trim((string) ($args['title'] ?? '')),
            'icsUrl' => FeedLoader::normalizeUrl((string) ($args['icsUrl'] ?? '')) ?? '',
            'refreshMinutes' => in_array((int) ($args['refreshMinutes'] ?? 0), self::REFRESH_OPTIONS, true) ? (int) $args['refreshMinutes'] : 60,
            'monthsTotal' => $total,
            'monthsVisible' => max(1, min($total, (int) ($args['monthsVisible'] ?? 4))),
            'onRequestKeywords' => trim((string) ($args['onRequestKeywords'] ?? '')),
            'showLegend' => !empty($args['showLegend']) ? 1 : 0,
        ];
        parent::save($data);

        // Beim Speichern den Kalender direkt (neu) laden, damit Fehler sofort sichtbar sind.
        if ($data['icsUrl'] !== '') {
            $this->getFeedLoader()->load($data['icsUrl'], $data['refreshMinutes'], !empty($args['forceRefresh']));
        }
    }

    /**
     * @return string[]
     */
    public function getOnRequestKeywords(): array
    {
        return array_values(array_filter(array_map('trim', explode(',', (string) $this->onRequestKeywords))));
    }

    private function prepareForm(): void
    {
        $refreshOptions = [];
        foreach (self::REFRESH_OPTIONS as $minutes) {
            $refreshOptions[$minutes] = $minutes < 60
                ? t2('%s minute', '%s minutes', $minutes)
                : t2('%s hour', '%s hours', intdiv($minutes, 60));
        }
        $this->set('refreshOptions', $refreshOptions);
        $this->set('feed', null);
    }

    private function getFeedLoader(): FeedLoader
    {
        return new FeedLoader(
            $this->app->make(EntityManagerInterface::class),
            $this->app->make(Client::class),
            $this->app->make(LoggerFactory::class)->createLogger(Channels::CHANNEL_APPLICATION)
        );
    }

    private function canEditBlock(): bool
    {
        $page = Page::getCurrentPage();

        return $page instanceof Page && !$page->isError() && (new Checker($page))->canEditPageContents();
    }
}
