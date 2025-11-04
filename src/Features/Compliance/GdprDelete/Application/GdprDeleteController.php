<?php

namespace Features\Compliance\GdprDelete\Application;

use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

#[Group('Compliance')]
class GdprDeleteController
{
    /**
     * Delete user data.
     */
    public function __invoke(Request $request, GdprDeleteHandler $handler): Response
    {
        $handler(
            command: new GdprDelete(
                user: $request->user()
            )
        );

        return response()->noContent();
    }
}
