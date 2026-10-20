<?php

namespace Features\Audit\GetAudits\Application;

class GetAudits
{
    public function __construct(
        public int $page = 1,
        public int $perPage = 15,
        public ?string $event = null,
        public ?string $userId = null,
        public ?string $from = null,
        public ?string $to = null,
    ) {}
}
