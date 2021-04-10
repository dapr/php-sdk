<?php

namespace App\Models;

use Dapr\consistency\StrongLastWrite;
use Dapr\State\Attributes\StateStore;

#[StateStore('statestore', StrongLastWrite::class)]
class ExampleState
{
    public int $page_views = 0;
    public string|null $last_name_seen = null;
}
