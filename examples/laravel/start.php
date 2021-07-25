<?php

/*
 * File that automatically starts up the environment, for use in integration tests
 */

echo `composer install`;
echo `./vendor/bin/sail up -d`;
