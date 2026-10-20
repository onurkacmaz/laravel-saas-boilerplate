<?php

namespace Features\Compliance\GdprDownload\Application;

use Features\Compliance\Shared\Support\UserDataExporter;

class GdprDownloadHandler
{
    public function __construct(
        private readonly UserDataExporter $exporter
    ) {}

    public function __invoke(GdprDownload $command): array
    {
        return $this->exporter->export($command->user);
    }
}
