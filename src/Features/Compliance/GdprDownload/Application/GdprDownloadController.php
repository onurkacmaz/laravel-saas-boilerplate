<?php

namespace Features\Compliance\GdprDownload\Application;

use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group('Compliance')]
class GdprDownloadController
{
    /**
     * Download user data.
     */
    public function __invoke(Request $request, GdprDownloadHandler $handler): JsonResponse
    {
        $data = $handler(
            command: new GdprDownload(
                user: $request->user()
            )
        );

        $filename = sprintf('user-data-%s-%s.json', $request->user()->id, now()->format('Y-m-d'));

        return response()->json($data)
            ->header('Content-Type', 'application/json')
            ->header('Content-Disposition', "attachment; filename=\"$filename\"");
    }
}
