<?php

namespace Features\Audit\GetEvents\Application;

class GetEvents
{
    public function __construct(
        public int $page = 1,
        public int $perPage = 10,
        public ?string $search = null,
    ) {}
}
