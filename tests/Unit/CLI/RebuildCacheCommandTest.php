<?php

namespace Pressmind\Tests\Unit\CLI;

use Pressmind\CLI\RebuildCacheCommand;
use Pressmind\DB\Adapter\AdapterInterface;
use Pressmind\ORM\Object\MediaObject;
use Pressmind\Registry;
use Pressmind\Tests\Unit\AbstractTestCase;

class RebuildCacheCommandMediaObject extends MediaObject
{
    public static int $liveInstances = 0;
    public static int $peakInstances = 0;
    public static array $actions = [];

    public function __construct()
    {
        parent::__construct();
        self::$liveInstances++;
        self::$peakInstances = max(self::$peakInstances, self::$liveInstances);
    }

    public function __destruct()
    {
        self::$liveInstances--;
    }

    public function removeFromCache(): void
    {
        self::$actions[] = 'delete:' . $this->getId();
    }

    public function addToCache($id)
    {
        self::$actions[] = 'add:' . $id;
        return null;
    }
}

class TestableRebuildCacheCommand extends RebuildCacheCommand
{
    private iterable $ids;

    public function __construct(iterable $ids)
    {
        parent::__construct();
        $this->ids = $ids;
    }

    protected function getMediaObjectIds(): iterable
    {
        return $this->ids;
    }

    protected function createMediaObject(int $id): MediaObject
    {
        $mediaObject = new RebuildCacheCommandMediaObject();
        $mediaObject->setId($id);
        return $mediaObject;
    }
}

class InspectableRebuildCacheCommand extends RebuildCacheCommand
{
    public function mediaObjectIds(): array
    {
        return iterator_to_array($this->getMediaObjectIds(), false);
    }
}

/**
 * Unit tests for RebuildCacheCommand.
 */
class RebuildCacheCommandTest extends AbstractTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        RebuildCacheCommandMediaObject::$liveInstances = 0;
        RebuildCacheCommandMediaObject::$peakInstances = 0;
        RebuildCacheCommandMediaObject::$actions = [];
    }

    public function testProcessesMediaObjectIdsWithoutRetainingProcessedObjects(): void
    {
        $ids = (function (): iterable {
            foreach ([11, 22, 33] as $id) {
                $this->assertSame(0, RebuildCacheCommandMediaObject::$liveInstances);
                yield $id;
            }
        })();

        $command = new TestableRebuildCacheCommand($ids);

        $this->expectOutputString(
            "deleting 11 from cache\nadding 11 to cache\n11 added to cache\n"
            . "deleting 22 from cache\nadding 22 to cache\n22 added to cache\n"
            . "deleting 33 from cache\nadding 33 to cache\n33 added to cache\n"
        );

        $this->assertSame(0, $command->run(['rebuild-cache']));
        $this->assertSame(1, RebuildCacheCommandMediaObject::$peakInstances);
        $this->assertSame(0, RebuildCacheCommandMediaObject::$liveInstances);
        $this->assertSame(
            ['delete:11', 'add:11', 'delete:22', 'add:22', 'delete:33', 'add:33'],
            RebuildCacheCommandMediaObject::$actions
        );
    }

    public function testLoadsOnlyIdsFromTheConfiguredMediaObjectTable(): void
    {
        $db = $this->createMock(AdapterInterface::class);
        $db->expects($this->once())
            ->method('getTablePrefix')
            ->willReturn('customer_');
        $db->expects($this->once())
            ->method('fetchAll')
            ->with('SELECT `id` FROM `customer_pmt2core_media_objects` ORDER BY `id` ASC')
            ->willReturn([(object) ['id' => '11'], (object) ['id' => '22']]);
        Registry::getInstance()->add('db', $db);

        $command = new InspectableRebuildCacheCommand();

        $this->assertSame([11, 22], $command->mediaObjectIds());
    }
}
