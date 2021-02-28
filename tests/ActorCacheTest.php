<?php

use Dapr\Actors\Internal\Caches\CacheInterface;
use Dapr\Actors\Internal\Caches\FileCache;
use Dapr\Actors\Internal\Caches\KeyNotFound;
use Dapr\Actors\Internal\Caches\NoCache;

require_once __DIR__.'/DaprTests.php';

/**
 * Class ActorStateTest
 */
class ActorCacheTest extends DaprTests {
    private string $cache_name;

    public function setUp(): void
    {
        parent::setUp();
        $this->cache_name = uniqid('unit_test');
    }

    public function testNoCache() {
        $cache = new NoCache($this->cache_name);
        $cache->set_key('test', 'test');
        $this->expectException(KeyNotFound::class);
        $cache->get_key('test');
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
        $cache = new FileCache($this->cache_name);
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
