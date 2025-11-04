<?php

namespace Features\Compliance\GdprDownload\Application;

use App\Models\User;

class GdprDownload
{
    public function __construct(public User $user) {}
}
