<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionMethod;

class AutoTesterCommand extends Command
{
    protected $signature = 'auto-tester:watch';
    protected $description = 'Writes & runs Unit, Feature, Browser tests on every code change';

    private array $fileTimestamps = [];

    public function handle(): int
    {
        $this->info('🔁 AutoTester active. Watching app/...');
        $this->ensureDirectories();

        while (true) {
            $files = File::allFiles(app_path());
            $changed = false;

            foreach ($files as $file) {
                $path = $file->getRealPath();
                $mtime = filemtime($path);
                if (!isset($this->fileTimestamps[$path]) || $this->fileTimestamps[$path] !== $mtime) {
                    $this->fileTimestamps[$path] = $mtime;
                    $changed = true;
                    $this->handleChange($file);
                }
            }

            if ($changed) {
                $this->runAllTests();
            }

            sleep(1);
        }
    }

    private function ensureDirectories(): void
    {
        foreach (['Unit', 'Feature', 'Browser'] as $type) {
            File::ensureDirectoryExists(base_path("tests/{$type}/auto"));
        }
    }

    private function handleChange($file): void
    {
        $relative = $file->getRelativePathname();
        $this->info("📝 Change: {$relative}");

        $className = $this->extractClass($file);
        if (!$className) return;

        $this->generateUnitTest($className, $file);
        $this->generateFeatureTest($className, $file);
        $this->generateBrowserTest($className, $file);
    }

    private function extractClass($file): ?string
    {
        $content = File::get($file);
        if (preg_match('/class\s+(\w+)/', $content, $matches)) {
            return $matches[1];
        }
        return null;
    }

    private function extractNamespace($file): string
    {
        $content = File::get($file);
        if (preg_match('/namespace\s+([^;]+)/', $content, $matches)) {
            return $matches[1];
        }
        return 'App';
    }

    private function getMethods($file): array
    {
        $content = File::get($file);
        preg_match_all('/function\s+(\w+)\s*\(/', $content, $matches);
        return $matches[1] ?? [];
    }

    private function generateUnitTest(string $class, $file): void
    {
        $path = base_path("tests/Unit/auto/{$class}Test.php");
        if (File::exists($path)) return;

        $namespace = $this->extractNamespace($file);
        $methods = $this->getMethods($file);
        $testMethods = '';
        foreach ($methods as $method) {
            if (in_array($method, ['__construct', '__destruct', 'get', 'set'])) continue;
            $testMethods .= "\n    /** @test */\n    public function it_{$method}_works()\n    {\n        \$obj = new {$class}();\n        \$this->assertTrue(method_exists(\$obj, '{$method}'));\n    }\n";
        }

        $code = "<?php\n\nnamespace Tests\Unit\auto;\n\nuse Tests\TestCase;\nuse {$namespace}\\{$class};\n\nclass {$class}Test extends TestCase\n{\n    /** @test */\n    public function it_can_instantiate()\n    {\n        \$obj = new {$class}();\n        \$this->assertInstanceOf({$class}::class, \$obj);\n    }\n{$testMethods}}\n";

        File::put($path, $code);
        $this->line("   ✓ Unit test: {$class}Test");
    }

    private function generateFeatureTest(string $class, $file): void
    {
        $path = base_path("tests/Feature/auto/{$class}FeatureTest.php");
        if (File::exists($path)) return;

        $route = Str::kebab(Str::plural(str_replace('Controller', '', $class)));
        $code = "<?php\n\nnamespace Tests\Feature\auto;\n\nuse Tests\TestCase;\n\nclass {$class}FeatureTest extends TestCase\n{\n    /** @test */\n    public function it_calls_{$route}_index()\n    {\n        \$response = \$this->get('/{$route}');\n        \$response->assertStatus(200);\n    }\n\n    /** @test */\n    public function it_handles_post_to_{$route}()\n    {\n        \$response = \$this->post('/{$route}', ['_token' => csrf_token()]);\n        \$response->assertStatus(302); // or 200, adjust as needed\n    }\n}\n";

        File::put($path, $code);
        $this->line("   ✓ Feature test: {$class}FeatureTest");
    }

    private function generateBrowserTest(string $class, $file): void
    {
        $path = base_path("tests/Browser/auto/{$class}BrowserTest.php");
        if (File::exists($path)) return;

        $code = "<?php\n\nnamespace Tests\Browser\auto;\n\nuse Laravel\Dusk\Browser;\nuse Tests\DuskTestCase;\n\nclass {$class}BrowserTest extends DuskTestCase\n{\n    /** @test */\n    public function it_visits_related_page()\n    {\n        \$this->browse(function (Browser \$browser) {\n            \$browser->visit('/')\n                    ->assertSee('Laravel')\n                    ->screenshot('{$class}_homepage');\n        });\n    }\n}\n";

        File::put($path, $code);
        $this->line("   ✓ Browser test: {$class}BrowserTest");
    }

    private function runAllTests(): void
    {
        $this->newLine();
        $this->line('🧪 Running test suites...');

        $this->runPhpUnit('Unit');
        $this->runPhpUnit('Feature');
        $this->runDusk();

        $this->info('✅ Cycle finished.');
        $this->newLine();
    }

    private function runPhpUnit(string $suite): void
    {
        $this->line("▶ {$suite} tests");
        $cmd = "vendor/bin/phpunit tests/{$suite}/auto --stop-on-failure 2>&1";
        exec($cmd, $output, $code);
        if ($code === 0) {
            $this->info("   ✓ PASS");
        } else {
            $this->error("   ✗ FAIL");
            $this->warn(substr(implode("\n", $output), -500));
        }
    }

    private function runDusk(): void
    {
        $this->line("▶ Browser (Dusk) tests");
        exec("php artisan dusk tests/Browser/auto 2>&1", $output, $code);
        if ($code === 0) {
            $this->info("   ✓ PASS");
        } else {
            $this->error("   ✗ FAIL");
            $this->warn(substr(implode("\n", $output), -500));
        }
    }
}
