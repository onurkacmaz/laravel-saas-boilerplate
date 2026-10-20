<?php

namespace Features\Audit\GetEvents\Application;

class GetEvents
{
    public function __construct(
        public ?string $search = null,
    ) {}
}
