<?php

use Dapr\Actors\Internal\Caches\CacheInterface;
use Dapr\Actors\Internal\Caches\FileCache;
use Dapr\Actors\Internal\Caches\KeyNotFound;
use Dapr\Actors\Internal\Caches\MemoryCache;

require_once __DIR__.'/DaprTests.php';

/**
 * Class ActorStateTest
 */
class ActorCacheTest extends DaprTests {
    public function testNoCache() {
        $cache = new MemoryCache('','','');
        $cache->set_key('test', 'test');
        $this->assertSame('test', $cache->get_key('test'));
        $this->expectException(KeyNotFound::class);
        $cache->get_key('failure');
    }

    public function assertKeyNotExist(CacheInterface $cache, string $key): void {
        try {
            $cache->get_key($key);
            $this->assertTrue(false);
        } catch(KeyNotFound) {
            $this->assertTrue(true);
        }
    }

    public function testReadWriteCacheOnSameInstance() {
        $cache = new FileCache('test', '123', 'state-test');
        $this->assertKeyNotExist($cache, 'test');

        $cache->set_key('test', 'test');
        $this->assertSame('test', $cache->get_key('test'));
        $cache->evict('test');
        $this->assertKeyNotExist($cache, 'test');
        $cache->set_key('test', 'test');
        $cache->reset();
        $this->assertKeyNotExist($cache, 'test');
    }
}
