<?php

namespace Pressmind\Import;

use Pressmind\ORM\Object\MediaObject;
use Pressmind\Registry;
use Pressmind\Search\OpenSearch\Indexer as OpenSearchIndexer;
use Throwable;

class MediaObjectDepublisher
{
    public const VISIBILITY_NOBODY = 10;

    /**
     * @var callable
     */
    private $mediaObjectFactory;

    /**
     * @var callable
     */
    private $openSearchDelete;

    public function __construct(?callable $mediaObjectFactory = null, ?callable $openSearchDelete = null)
    {
        $this->mediaObjectFactory = $mediaObjectFactory ?? static fn (int $id) => new MediaObject($id);
        $this->openSearchDelete = $openSearchDelete ?? static function (int $id): void {
            $indexer = new OpenSearchIndexer();
            $indexer->deleteMediaObject($id);
        };
    }

    /**
     * @param int|string|array<int|string> $ids
     */
    public function depublish($ids): MediaObjectDepublishResult
    {
        $result = new MediaObjectDepublishResult();

        foreach ($this->normalizeIds($ids) as $id) {
            $this->depublishOne($id, $result);
        }

        return $result;
    }

    private function depublishOne(int $id, MediaObjectDepublishResult $result): void
    {
        try {
            $mediaObject = ($this->mediaObjectFactory)($id);
            if (!is_object($mediaObject)) {
                throw new \RuntimeException('Media object factory did not return an object');
            }
        } catch (Throwable $e) {
            $result->addError($id, 'load', $e->getMessage());
            return;
        }

        $this->runStep($id, 'mysql', $result, function () use ($mediaObject): void {
            $mediaObject->visibility = self::VISIBILITY_NOBODY;
            $mediaObject->update();
        });

        $config = $this->getConfig();

        if (!empty($config['cache']['enabled'])) {
            $this->runStep($id, 'cache', $result, function () use ($mediaObject): void {
                $mediaObject->removeFromCache();
            });
        }

        $this->runStep($id, 'fulltext', $result, function () use ($id): void {
            Registry::getInstance()
                ->get('db')
                ->delete('pmt2core_fulltext_search', ['id_media_object = ?', $id]);
        });

        if (!empty($config['data']['search_mongodb']['enabled'])) {
            $this->runStep($id, 'mongodb_search', $result, function () use ($mediaObject): void {
                $mediaObject->createMongoDBIndex();
            });
            $this->runStep($id, 'mongodb_calendar', $result, function () use ($mediaObject): void {
                $mediaObject->deleteMongoDBCalendar();
            });
        }

        if (!empty($config['data']['search_opensearch']['enabled'])) {
            $this->runStep($id, 'opensearch', $result, function () use ($id): void {
                ($this->openSearchDelete)($id);
            });
        }
    }

    private function runStep(int $id, string $target, MediaObjectDepublishResult $result, callable $callback): void
    {
        try {
            $callback();
            $result->addSuccess($id, $target);
        } catch (Throwable $e) {
            $result->addError($id, $target, $e->getMessage());
        }
    }

    /**
     * @param int|string|array<int|string> $ids
     * @return int[]
     */
    private function normalizeIds($ids): array
    {
        if (!is_array($ids)) {
            $ids = is_string($ids) ? explode(',', $ids) : [$ids];
        }

        $normalized = [];
        foreach ($ids as $id) {
            $id = (int)trim((string)$id);
            if ($id > 0) {
                $normalized[] = $id;
            }
        }

        return array_values(array_unique($normalized));
    }

    private function getConfig(): array
    {
        $config = Registry::getInstance()->get('config');
        return is_array($config) ? $config : [];
    }
}
