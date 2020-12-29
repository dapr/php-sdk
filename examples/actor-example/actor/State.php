<?php

namespace Actor;

class State extends \Dapr\State\State {
    /**
     * @var int The current count
     */
    public int $count = 0;
}
