<?php

namespace Concrete\Package\FewoToolkit\Block\Bildergalerie;

use Concrete\Core\Block\BlockController;
use Concrete\Core\Entity\File\File as FileEntity;
use Concrete\Core\File\FileList;
use Concrete\Core\File\FolderItemList;
use Concrete\Core\File\Set\Set as FileSet;
use Concrete\Core\File\Type\Type as FileType;
use Concrete\Core\Tree\Node\Node;
use Concrete\Core\Tree\Node\Type\File as FileNode;
use Concrete\Core\Tree\Node\Type\FileFolder;

class Controller extends BlockController
{
    private const MAX_IMAGES = 300;
    private const RATIOS = ['1:1' => [1, 1], '4:3' => [4, 3], '3:2' => [3, 2], '16:9' => [16, 9]];

    public $source = 'folder';
    public $folderID = 0;
    public $fileSetID = 0;
    public $sortOrder = 'display';
    public $columnCount = 3;
    public $aspectRatio = '4:3';
    public $showCaptions = true;

    protected $btTable = 'btFewoBildergalerie';
    protected $btInterfaceWidth = 560;
    protected $btInterfaceHeight = 480;
    protected $btDefaultSet = 'fewo_toolkit';
    protected $btCacheBlockRecord = true;
    protected $btCacheBlockOutput = true;
    protected $btCacheBlockOutputOnPost = true;
    protected $btCacheBlockOutputForRegisteredUsers = false;
    // Neue Bilder im Ordner erscheinen spätestens nach einer Stunde.
    protected $btCacheBlockOutputLifetime = 3600;
    protected $btExportFileFolderColumns = ['folderID'];

    public function getBlockTypeName()
    {
        return t('Image Gallery');
    }

    public function getBlockTypeDescription()
    {
        return t('Image gallery with lightbox from a file manager folder or file set.');
    }

    public function add()
    {
        $this->edit();
    }

    public function edit()
    {
        $folders = ['' => t('** Please select')];
        foreach (Node::getNodesOfType('file_folder') as $folder) {
            $folders[$folder->getTreeNodeID()] = $folder->getTreeNodeDisplayPath();
        }
        $sets = ['' => t('** Please select')];
        foreach (FileSet::getMySets() as $set) {
            $sets[$set->getFileSetID()] = $set->getFileSetName();
        }
        $this->set('folderOptions', $folders);
        $this->set('setOptions', $sets);
        $this->set('sortOptions', [
            'display' => t('Order in the file set / file manager'),
            'title' => t('Title (A–Z)'),
            'newest' => t('Newest first'),
            'oldest' => t('Oldest first'),
        ]);
        $this->set('ratioOptions', array_combine(array_keys(self::RATIOS), array_keys(self::RATIOS)));
    }

    public function view()
    {
        $ratio = self::RATIOS[$this->aspectRatio] ?? self::RATIOS['4:3'];
        $thumbWidth = 800;
        $thumbHeight = (int) round($thumbWidth * $ratio[1] / $ratio[0]);
        $imageHelper = $this->app->make('helper/image');

        $images = [];
        foreach ($this->getFiles() as $file) {
            $version = $file->getApprovedVersion();
            if ($version === null) {
                continue;
            }
            $thumb = $imageHelper->getThumbnail($file, $thumbWidth, $thumbHeight, true);
            $large = $imageHelper->getThumbnail($file, 2000, 2000, false);
            $caption = trim((string) $version->getDescription()) ?: trim((string) $version->getTitle());
            // Dateinamen (z. B. "IMG_1234.jpg") nicht als Bildunterschrift anzeigen
            if ($caption === $version->getFileName()) {
                $caption = '';
            }
            $images[] = [
                'thumb' => $thumb->src,
                'thumbWidth' => (int) $thumb->width,
                'thumbHeight' => (int) $thumb->height,
                'large' => $large->src,
                'largeWidth' => (int) $large->width,
                'largeHeight' => (int) $large->height,
                'caption' => $caption,
                'alt' => (string) ($version->getAttribute('alt') ?: $caption),
            ];
        }

        $this->set('images', $images);
        $this->set('ratio', $ratio);
    }

    public function save($args)
    {
        parent::save([
            'source' => ($args['source'] ?? '') === 'set' ? 'set' : 'folder',
            'folderID' => (int) ($args['folderID'] ?? 0),
            'fileSetID' => (int) ($args['fileSetID'] ?? 0),
            'sortOrder' => in_array($args['sortOrder'] ?? '', ['display', 'title', 'newest', 'oldest'], true) ? $args['sortOrder'] : 'display',
            'columnCount' => max(1, min(6, (int) ($args['columnCount'] ?? 3))),
            'aspectRatio' => isset(self::RATIOS[$args['aspectRatio'] ?? '']) ? $args['aspectRatio'] : '4:3',
            'showCaptions' => !empty($args['showCaptions']) ? 1 : 0,
        ]);
    }

    public function validate($args)
    {
        $errors = $this->app->make('helper/validation/error');
        if (($args['source'] ?? '') === 'set' ? empty($args['fileSetID']) : empty($args['folderID'])) {
            $errors->add(t('Please choose where the images come from.'));
        }

        return $errors;
    }

    /**
     * @return FileEntity[]
     */
    private function getFiles(): array
    {
        $files = [];
        if ($this->source === 'set') {
            $set = FileSet::getByID((int) $this->fileSetID);
            if ($set === null) {
                return [];
            }
            $list = new FileList();
            $list->filterBySet($set);
            $list->filterByType(FileType::T_IMAGE);
            $list->sortByFileSetDisplayOrder();
            $list->setItemsPerPage(self::MAX_IMAGES);
            // Der Redakteur hat die Quelle bewusst ausgewählt – Bilder für alle Besucher anzeigen.
            $list->ignorePermissions();
            $files = $list->getResults();
        } else {
            $folder = Node::getByID((int) $this->folderID);
            if (!$folder instanceof FileFolder) {
                return [];
            }
            $list = new FolderItemList();
            $list->filterByParentFolder($folder);
            $list->filterByType(FileType::T_IMAGE);
            $list->setItemsPerPage(self::MAX_IMAGES);
            $list->setPermissionsChecker(static fn () => true);
            foreach ($list->getResults() as $node) {
                if ($node instanceof FileNode && ($file = $node->getTreeNodeFileObject()) instanceof FileEntity) {
                    $files[] = $file;
                }
            }
        }

        return $this->sort(array_slice($files, 0, self::MAX_IMAGES));
    }

    /**
     * @param FileEntity[] $files
     *
     * @return FileEntity[]
     */
    private function sort(array $files): array
    {
        $title = static fn (FileEntity $f): string => mb_strtolower((string) $f->getApprovedVersion()?->getTitle());
        $added = static fn (FileEntity $f): int => $f->getDateAdded()?->getTimestamp() ?? 0;

        switch ($this->sortOrder) {
            case 'title':
                usort($files, static fn ($a, $b) => strnatcmp($title($a), $title($b)));
                break;
            case 'newest':
                usort($files, static fn ($a, $b) => $added($b) <=> $added($a));
                break;
            case 'oldest':
                usort($files, static fn ($a, $b) => $added($a) <=> $added($b));
                break;
        }

        return $files;
    }
}
