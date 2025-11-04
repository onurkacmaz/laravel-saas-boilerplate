<?php

namespace Features\Compliance\GdprExport\Application;

use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group('Compliance')]
class GdprExportController
{
    /**
     * Export user data.
     */
    public function __invoke(Request $request, GdprExportHandler $handler): JsonResponse
    {
        $data = $handler(
            command: new GdprExport(
                user: $request->user()
            )
        );

        return response()->json([
            'status' => 'success',
            'message' => 'User data exported successfully',
            'data' => $data,
        ]);
    }
}
