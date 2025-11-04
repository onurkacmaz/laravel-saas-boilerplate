<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Console\Command\Command as CommandAlias;

class MakeFeature extends Command
{
    protected $signature = 'make:feature {name? : The name of the feature (e.g., Onur:Kacmaz)}';

    protected $description = 'Create a new feature with all necessary files and structure';

    public function handle(): int
    {
        $featureName = $this->argument('name')
            ?? $this->ask('What is the name of the feature?', 'Onur:Kacmaz');

        $parts = explode(':', $featureName);

        if (count($parts) < 2) {
            $this->error('Feature name must be in format: Module:FeatureName');

            return CommandAlias::FAILURE;
        }

        $this->createFeature($parts);

        $this->info("✓ Feature created successfully: {$featureName}");
        $this->line('  Location: src/Features/'.implode('/', $parts));

        return CommandAlias::SUCCESS;
    }

    private function createFeature(array $parts): void
    {
        $pathNamespace = implode('/', $parts);
        $classNamespace = implode('\\', $parts);
        $basePath = base_path("src/Features/{$pathNamespace}");
        $featureName = end($parts);

        $this->info('Creating feature structure...');

        $this->createRoutesFile($basePath);
        $this->createServiceProvider($basePath, $classNamespace, $featureName);
        $this->createHandler($basePath, $classNamespace, $featureName);
        $this->createController($basePath, $classNamespace, $featureName);
        $this->createRequest($basePath, $classNamespace, $featureName);
        $this->createCommand($basePath, $classNamespace, $featureName);
    }

    private function createRoutesFile(string $basePath): void
    {
        $path = "{$basePath}/Routes/api.php";
        $this->ensureDirectoryExists(dirname($path));

        $content = <<<'PHP'
<?php

use Illuminate\Support\Facades\Route;

// Define your API routes here

PHP;

        File::put($path, $content);
        $this->line('  - Routes file created');
    }

    private function createServiceProvider(string $basePath, string $namespace, string $featureName): void
    {
        $path = "{$basePath}/Providers/{$featureName}ServiceProvider.php";
        $this->ensureDirectoryExists(dirname($path));

        $content = <<<PHP
<?php

namespace Features\\{$namespace}\\Providers;

use Illuminate\Support\ServiceProvider;

class {$featureName}ServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Load routes
        \$this->loadRoutesFrom(__DIR__ . '/../Routes/api.php');
    }
}

PHP;

        File::put($path, $content);
        $this->line('  - Service Provider created');
    }

    private function createHandler(string $basePath, string $namespace, string $featureName): void
    {
        $path = "{$basePath}/Application/{$featureName}Handler.php";
        $this->ensureDirectoryExists(dirname($path));

        $content = <<<PHP
<?php

namespace Features\\{$namespace}\\Application;

class {$featureName}Handler
{
    public function __invoke({$featureName} \$command)
    {
        // Handle your feature logic here
    }
}

PHP;

        File::put($path, $content);
        $this->line('  - Handler created');
    }

    private function createController(string $basePath, string $namespace, string $featureName): void
    {
        $path = "{$basePath}/Application/{$featureName}Controller.php";
        $this->ensureDirectoryExists(dirname($path));

        $content = <<<PHP
<?php

namespace Features\\{$namespace}\\Application;

use Illuminate\Http\JsonResponse;

class {$featureName}Controller
{
    public function __invoke({$featureName}Request \$request, {$featureName}Handler \$handler): JsonResponse
    {
        \$data = \$handler(
            command: new {$featureName}(\$request->all())
        );

        return response()->json(\$data);
    }
}

PHP;

        File::put($path, $content);
        $this->line('  - Controller created');
    }

    private function createRequest(string $basePath, string $namespace, string $featureName): void
    {
        $path = "{$basePath}/Application/{$featureName}Request.php";
        $this->ensureDirectoryExists(dirname($path));

        $content = <<<PHP
<?php

namespace Features\\{$namespace}\\Application;

use Illuminate\Foundation\Http\FormRequest;

class {$featureName}Request extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Define your validation rules here
        ];
    }
}

PHP;

        File::put($path, $content);
        $this->line('  - Request created');
    }

    private function createCommand(string $basePath, string $namespace, string $featureName): void
    {
        $path = "{$basePath}/Application/{$featureName}.php";
        $this->ensureDirectoryExists(dirname($path));

        $content = <<<PHP
<?php

namespace Features\\{$namespace}\\Application;

class {$featureName}
{
    public function __construct()
    {
    }
}

PHP;

        File::put($path, $content);
        $this->line('  - Command created');
    }

    private function ensureDirectoryExists(string $directory): void
    {
        if (! File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true);
        }
    }
}
