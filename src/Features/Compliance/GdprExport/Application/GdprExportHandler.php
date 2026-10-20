<?php

namespace Features\Compliance\GdprExport\Application;

use Features\Compliance\Shared\Support\UserDataExporter;

class GdprExportHandler
{
    public function __construct(
        private readonly UserDataExporter $exporter
    ) {}

    public function __invoke(GdprExport $command): array
    {
        return $this->exporter->export($command->user);
    }
}
