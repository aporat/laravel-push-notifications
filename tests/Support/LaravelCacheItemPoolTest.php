<?php

declare(strict_types=1);

namespace Aporat\PushNotifications\Laravel\Tests\Support;

use Aporat\PushNotifications\Laravel\Support\Psr6\LaravelCacheItem;
use Aporat\PushNotifications\Laravel\Support\Psr6\LaravelCacheItemPool;
use Aporat\PushNotifications\Laravel\Tests\TestCase;
use Illuminate\Support\Facades\Cache;
use Psr\Cache\CacheItemInterface;

final class LaravelCacheItemPoolTest extends TestCase
{
    private function pool(): LaravelCacheItemPool
    {
        return new LaravelCacheItemPool(Cache::store('array'), 'push:');
    }

    public function test_miss_then_hit(): void
    {
        $pool = $this->pool();

        $item = $pool->getItem('token');
        self::assertFalse($item->isHit());
        self::assertNull($item->get());

        $item->set(['token' => 'abc'])->expiresAfter(60);
        self::assertTrue($pool->save($item));

        self::assertTrue($pool->hasItem('token'));
        self::assertSame(['token' => 'abc'], $pool->getItem('token')->get());
        self::assertSame(['token' => 'abc'], Cache::store('array')->get('push:token'));
    }

    public function test_forever_and_delete(): void
    {
        $pool = $this->pool();
        $pool->save($pool->getItem('k')->set('v'));

        self::assertTrue($pool->hasItem('k'));
        self::assertTrue($pool->deleteItem('k'));
        self::assertFalse($pool->hasItem('k'));
        self::assertTrue($pool->deleteItems(['k', 'missing']));
    }

    public function test_expired_item_is_not_stored(): void
    {
        $pool = $this->pool();
        $pool->save($pool->getItem('k')->set('v')->expiresAfter(-1));

        self::assertFalse($pool->hasItem('k'));
    }

    public function test_deferred_items_commit_together(): void
    {
        $pool = $this->pool();

        self::assertTrue($pool->saveDeferred($pool->getItem('a')->set(1)));
        self::assertTrue($pool->saveDeferred($pool->getItem('b')->set(2)));
        self::assertTrue($pool->getItem('a')->isHit());
        self::assertNull(Cache::store('array')->get('push:a'));

        self::assertTrue($pool->commit());
        self::assertSame(1, Cache::store('array')->get('push:a'));
        self::assertSame(2, Cache::store('array')->get('push:b'));
    }

    public function test_get_items_and_clear(): void
    {
        $pool = $this->pool();
        $pool->save($pool->getItem('a')->set(1));

        $items = iterator_to_array($pool->getItems(['a', 'b']));
        self::assertTrue($items['a']->isHit());
        self::assertFalse($items['b']->isHit());
        self::assertFalse($pool->clear());
    }

    public function test_foreign_items_are_refused(): void
    {
        $foreign = $this->createStub(CacheItemInterface::class);

        self::assertFalse($this->pool()->save($foreign));
        self::assertFalse($this->pool()->saveDeferred($foreign));
    }

    public function test_item_ttl(): void
    {
        self::assertNull((new LaravelCacheItem('k'))->ttl());
        self::assertGreaterThan(50, (new LaravelCacheItem('k'))->expiresAfter(60)->ttl());
        self::assertLessThanOrEqual(0, (new LaravelCacheItem('k'))->expiresAt(new \DateTimeImmutable('-1 minute'))->ttl());
    }
}
