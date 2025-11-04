<?php

namespace Features\Subscription\Cancel\Application;

use App\Models\Organization;

class Cancel
{
    public function __construct(
        public Organization $organization,
        public bool $immediately = false
    ) {}
}
