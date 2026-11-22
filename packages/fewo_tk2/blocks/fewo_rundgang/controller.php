<?php

namespace Concrete\Package\FewoTk2\Block\FewoRundgang;

use Concrete\Core\Block\BlockController;
use Concrete\Core\Entity\File\Version;
use Concrete\Core\File\File;
use Concrete\Core\Package\PackageService;
use Concrete\Core\Page\Page;
use Concrete\Core\Permission\Checker;
use FewoTk2\Block\AddDefaults;
use FewoTk2\Support\ImageMemory;
use FewoTk2\Tour\TourConfig;
use Throwable;

/**
 * 360°-Rundgang mit Pannellum – Nachfolger des Blocks "Pannellum 360° Viewer" der alten Website.
 *
 * Die Räume werden im Block-Dialog aus der Dateiverwaltung gewählt und bekommen einen Namen. Im
 * Szenen-Editor legt man je Raum die Startposition fest und setzt Wegweiser zu anderen Räumen und
 * Hinweise auf besondere Stellen. Wer mehr braucht, kann stattdessen eigene Pannellum-Einstellungen
 * eintragen; die Bilder der Räume stehen darin als Platzhalter {name}.
 */
class Controller extends BlockController
{
    use AddDefaults;

    /** Seitenverhältnisse der Ansicht; "manual" überlässt die Höhe dem Theme (CSS-Klasse). */
    public const ASPECT_RATIOS = ['16:9', '3:2', '4:3', '2:1', '1:1', self::ASPECT_MANUAL];
    public const ASPECT_MANUAL = 'manual';

    /** Breiter als so viele Pixel: Telefone und Tablets bekommen eine verkleinerte Fassung. */
    private const MOBILE_MAX_WIDTH = 4096;

    public $title = '';
    public $tour = '';
    public $previewFID = 0;
    public $autoLoad = true;
    public $compass = false;
    public $author = '';
    public $useCustomCode = false;
    public $viewerCode = '';
    public $aspectRatio = '3:2';
    public $cssClass = '';

    protected $btTable = 'btFewoRundgang';
    protected $btInterfaceWidth = 980;
    protected $btInterfaceHeight = 760;
    protected $btDefaultSet = 'fewo_tk2';
    protected $btCacheBlockRecord = true;
    protected $btCacheBlockOutput = true;
    protected $btCacheBlockOutputOnPost = true;
    // Angemeldete Redakteure sehen bei fehlerhaften Einstellungen den Grund: nicht aus dem Cache
    protected $btCacheBlockOutputForRegisteredUsers = false;

    public function getBlockTypeName()
    {
        return t('360° Tour');
    }

    public function getBlockTypeDescription()
    {
        return t('360° panoramas and virtual tours with several rooms (Pannellum viewer).');
    }

    public function getSearchableContent()
    {
        $tour = $this->getTour();

        return trim($this->title . ' ' . implode(' ', array_column($tour['scenes'], 'title')));
    }

    public function registerViewAssets($outputContent = '')
    {
        $this->requireAsset('fewo_tk2/pannellum');
    }

    public function add()
    {
        $this->setAddDefaults();
        $this->edit();
    }

    public function edit()
    {
        $tour = $this->getTour();
        $files = [];
        foreach ($tour['scenes'] as $scene) {
            $version = $this->getFileVersion((int) $scene['fID']);
            if ($version !== null) {
                $files[(int) $scene['fID']] = ['url' => $this->localUrl((string) $version->getURL()), 'thumbnail' => $this->getThumbnailUrl($version)];
            }
        }
        $package = $this->app->make(PackageService::class)->getByHandle('fewo_tk2');

        $this->set('editorData', ['tour' => $tour, 'files' => $files]);
        $this->set('previewFile', $this->previewFID ? File::getByID((int) $this->previewFID) : null);
        $this->set('pannellumPath', $package->getRelativePath() . '/assets/pannellum');
        $this->set('aspectOptions', [
            '16:9' => '16:9',
            '3:2' => '3:2',
            '4:3' => '4:3',
            '2:1' => '2:1',
            '1:1' => '1:1',
            self::ASPECT_MANUAL => t('Manual (height from the CSS class of the theme)'),
        ]);
    }

    public function view()
    {
        $page = Page::getCurrentPage();
        $ratio = in_array($this->aspectRatio, self::ASPECT_RATIOS, true) ? (string) $this->aspectRatio : '3:2';

        // Bilder der Räume: Name => Adresse, für Mobilgeräte bei sehr großen Bildern eine verkleinerte Fassung
        $tour = $this->getTour();
        $images = [];
        $mobileImages = [];
        foreach ($tour['scenes'] as $scene) {
            $version = $this->getFileVersion((int) $scene['fID']);
            if ($version === null) {
                continue;
            }
            $images[$scene['name']] = $this->localUrl((string) $version->getURL());
            $mobile = $this->getMobileUrl($version);
            if ($mobile !== null) {
                $mobileImages[$scene['name']] = $mobile;
            }
        }
        $preview = $this->previewFID ? $this->getFileVersion((int) $this->previewFID) : null;
        if ($preview !== null) {
            $images[TourConfig::PREVIEW_PLACEHOLDER] = $this->localUrl((string) $preview->getURL());
        }

        if ($this->useCustomCode) {
            $code = trim((string) $this->viewerCode);
        } else {
            $config = TourConfig::toPannellum($tour, array_keys($images), [
                'autoLoad' => (bool) $this->autoLoad,
                'compass' => (bool) $this->compass,
                'author' => (string) $this->author,
                'preview' => $preview !== null,
            ]);
            $code = $config === null ? '' : (string) json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $this->set('viewerCode', $code);
        $this->set('imageMap', $images);
        $this->set('mobileImageMap', $mobileImages);
        $this->set('ratio', $ratio === self::ASPECT_MANUAL ? null : str_replace(':', ' / ', $ratio));
        $this->set('cssClass', self::sanitizeCssClass((string) $this->cssClass));
        $this->set('canEdit', $page instanceof Page && !$page->isError() && (new Checker($page))->canEditPageContents());
    }

    public function validate($args)
    {
        $errors = $this->app->make('helper/validation/error');
        $tour = TourConfig::sanitize(json_decode((string) ($args['tour'] ?? ''), true));
        if (!empty($args['useCustomCode'])) {
            if (trim((string) ($args['viewerCode'] ?? '')) === '') {
                $errors->add(t('Please enter the Pannellum settings.'));
            }
        } elseif (array_filter(array_column($tour['scenes'], 'fID')) === []) {
            $errors->add(t('Please add at least one room with an image.'));
        }

        return $errors;
    }

    public function save($args)
    {
        $tour = TourConfig::sanitize(json_decode((string) ($args['tour'] ?? ''), true));

        parent::save([
            'title' => trim((string) ($args['title'] ?? '')),
            'tour' => json_encode($tour, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'previewFID' => max(0, (int) ($args['previewFID'] ?? 0)),
            'autoLoad' => !empty($args['autoLoad']) ? 1 : 0,
            'compass' => !empty($args['compass']) ? 1 : 0,
            'author' => mb_substr(trim((string) ($args['author'] ?? '')), 0, 255),
            'useCustomCode' => !empty($args['useCustomCode']) ? 1 : 0,
            // Zeilenenden aus dem Formular (CRLF) vereinheitlichen
            'viewerCode' => trim(str_replace(["\r\n", "\r"], "\n", (string) ($args['viewerCode'] ?? ''))),
            'aspectRatio' => in_array($args['aspectRatio'] ?? '', self::ASPECT_RATIOS, true) ? $args['aspectRatio'] : '3:2',
            'cssClass' => self::sanitizeCssClass((string) ($args['cssClass'] ?? '')),
        ]);
    }

    /**
     * @return array{first: string, scenes: array<int, array<string, mixed>>}
     */
    public function getTour(): array
    {
        return TourConfig::sanitize(json_decode((string) $this->tour, true));
    }

    private function getFileVersion(int $fID): ?Version
    {
        $file = $fID > 0 ? File::getByID($fID) : null;
        $version = $file !== null ? $file->getApprovedVersion() : null;

        return $version instanceof Version ? $version : null;
    }

    /**
     * Verkleinerte Fassung für Telefone und Tablets, deren Grafikchip sehr große Bilder nicht darstellen kann.
     */
    private function getMobileUrl(Version $version): ?string
    {
        $width = (int) $version->getAttribute('width');
        $height = (int) $version->getAttribute('height');
        if ($width <= self::MOBILE_MAX_WIDTH || $height < 1) {
            return null;
        }
        if (!ImageMemory::canResize($width, $height, (int) $version->getFullSize())) {
            // zu wenig Arbeitsspeicher für dieses Bild: es bleibt beim Original (ein Abbruch wäre nicht abzufangen)
            return null;
        }
        try {
            $thumbnail = $this->app->make('helper/image')->getThumbnail(
                $version->getFile(),
                self::MOBILE_MAX_WIDTH,
                (int) round(self::MOBILE_MAX_WIDTH * $height / $width)
            );

            return isset($thumbnail->src) ? $this->localUrl((string) $thumbnail->src) : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function getThumbnailUrl(Version $version): string
    {
        if (!ImageMemory::canResize((int) $version->getAttribute('width'), (int) $version->getAttribute('height'), (int) $version->getFullSize())) {
            return $this->localUrl((string) $version->getURL());
        }
        try {
            $thumbnail = $this->app->make('helper/image')->getThumbnail($version->getFile(), 240, 120, true);

            return $this->localUrl(isset($thumbnail->src) ? (string) $thumbnail->src : (string) $version->getURL());
        } catch (Throwable) {
            return $this->localUrl((string) $version->getURL());
        }
    }

    /**
     * Adressen auf der eigenen Website ohne Protokoll und Rechnernamen ausgeben: Der Viewer darf Bilder nur
     * von der Adresse laden, unter der die Seite aufgerufen wurde – auch wenn die Ausgabe des Blocks aus dem
     * Cache kommt und die Website unter mehreren Namen erreichbar ist.
     */
    private function localUrl(string $url): string
    {
        $prefix = $this->request->getSchemeAndHttpHost();

        return str_starts_with($url, $prefix . '/') ? substr($url, strlen($prefix)) : $url;
    }

    private static function sanitizeCssClass(string $value): string
    {
        return mb_substr(trim(preg_replace('/[^A-Za-z0-9_ -]+/', '', $value) ?? ''), 0, 64);
    }
}
