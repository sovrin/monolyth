#!/usr/bin/env php
<?php

/**
 * Application Deployment Manager
 * This tool helps deploy applications from the backend by:
 * - Checking dependencies
 * - Including required packages
 * - Creating deployment packages
 * - Verifying installation requirements
 */

class DeploymentManager {

    private string $backendPath;

    private string $appsPath;

    private string $packagesPath;

    private array $config;

    public function __construct(string $backendPath = __DIR__ . '/..') {
        $this->backendPath = realpath($backendPath);
        $this->appsPath = $this->backendPath . '/apps';
        $this->packagesPath = $this->backendPath . '/packages';
        $this->config = [
            'temp_dir' => $this->backendPath . '/temp/deploy',
            'exclude_dirs' => ['.git', '.idea', 'node_modules', 'vendor', 'temp'],
            'exclude_files' => ['.DS_Store', 'composer.lock', '.env', '.env.local'],
        ];
    }

    public function deploy(string $appName, array $options = []): bool {
        $this->log("Starting deployment of app: {$appName}");

        try {
            // Validate app exists
            $appPath = $this->getAppPath($appName);
            if (!$appPath) {
                throw new Exception("App '{$appName}' not found");
            }

            // Check dependencies
            $dependencies = $this->checkDependencies($appPath);
            $this->log("Found " . count($dependencies['packages']) . " package dependencies");

            // Create deployment package
            $deploymentPath = $this->createDeploymentPackage($appName, $appPath, $dependencies, $options);

            $this->log("Deployment package created at: {$deploymentPath}");

            return true;
        } catch (Exception $e) {
            $this->error("Deployment failed: " . $e->getMessage());

            return false;
        }
    }

    private function getAppPath(string $appName): ?string {
        $appPath = $this->appsPath . '/' . $appName;

        return is_dir($appPath) ? $appPath : null;
    }

    private function checkDependencies(string $appPath): array {
        $composerFile = $appPath . '/composer.json';
        if (!file_exists($composerFile)) {
            throw new Exception("No composer.json found in app");
        }

        $composer = json_decode(file_get_contents($composerFile), true);
        if (!$composer) {
            throw new Exception("Invalid composer.json");
        }

        $dependencies = [
            'php' => $composer['require']['php'] ?? '^8.4',
            'packages' => [],
            'external' => [],
        ];

        // Check for local packages
        foreach ($composer['require'] ?? [] as $package => $version) {
            if (strpos($package, 'nc/') === 0) {
                $packageName = str_replace('nc/', '', $package);
                $packagePath = $this->packagesPath . '/' . $packageName;

                if (is_dir($packagePath)) {
                    $dependencies['packages'][$packageName] = [
                        'version' => $version,
                        'path' => $packagePath,
                        'dependencies' => $this->getPackageDependencies($packagePath),
                    ];
                } else {
                    $dependencies['external'][$package] = $version;
                }
            } elseif ($package !== 'php') {
                $dependencies['external'][$package] = $version;
            }
        }

        // Resolve package dependencies recursively
        $dependencies['packages'] = $this->resolvePackageDependencies($dependencies['packages']);

        $this->log("PHP version requirement: " . $dependencies['php']);
        $this->log("Local packages: " . implode(', ', array_keys($dependencies['packages'])));
        $this->log("External packages: " . implode(', ', array_keys($dependencies['external'])));

        return $dependencies;
    }

    private function getPackageDependencies(string $packagePath): array {
        $composerFile = $packagePath . '/composer.json';
        if (!file_exists($composerFile)) {
            return [];
        }

        $composer = json_decode(file_get_contents($composerFile), true);

        return $composer['require'] ?? [];
    }

    private function resolvePackageDependencies(array $packages): array {
        $resolved = $packages;

        // Simple dependency resolution - could be made more sophisticated
        foreach ($packages as $packageName => $packageInfo) {
            foreach ($packageInfo['dependencies'] as $depName => $depVersion) {
                if (strpos($depName, 'nc/') === 0) {
                    $depPackageName = str_replace('nc/', '', $depName);
                    if (!isset($resolved[$depPackageName])) {
                        $depPath = $this->packagesPath . '/' . $depPackageName;
                        if (is_dir($depPath)) {
                            $resolved[$depPackageName] = [
                                'version' => $depVersion,
                                'path' => $depPath,
                                'dependencies' => $this->getPackageDependencies($depPath),
                            ];
                        }
                    }
                }
            }
        }

        return $resolved;
    }

    private function createDeploymentPackage(string $appName, string $appPath, array $dependencies, array $options): string {
        $timestamp = date('Y-m-d_H-i-s');
        $deploymentName = "{$appName}_{$timestamp}";
        $deploymentPath = $this->config['temp_dir'] . '/' . $deploymentName;

        // Create deployment directory
        if (!is_dir($deploymentPath)) {
            mkdir($deploymentPath, 0755, true);
        }

        // Copy app files
        $this->log("Copying app files...");
        $this->copyDirectory($appPath, $deploymentPath . '/app', true);

        // Fix composer.json repositories path for deployment
        $this->fixComposerRepositoryPath($deploymentPath . '/app/composer.json');

        // Copy required packages
        $packagesDir = $deploymentPath . '/packages';
        mkdir($packagesDir, 0755, true);

        foreach ($dependencies['packages'] as $packageName => $packageInfo) {
            $this->log("Including package: {$packageName}");
            $this->copyDirectory(
                $packageInfo['path'],
                $packagesDir . '/' . $packageName,
                true
            );
        }

        // Create deployment manifest
        $this->createDeploymentManifest($deploymentPath, $appName, $dependencies, $options);

        // Create deployment script
        $this->createDeploymentScript($deploymentPath, $appName, $dependencies);

        // Create archive if requested
        if ($options['archive'] ?? true) {
            $archivePath = $this->createArchive($deploymentPath, $deploymentName);
            $this->log("Archive created: {$archivePath}");

            // Clean up temp directory if archive created successfully
            if (file_exists($archivePath)) {
                $this->removeDirectory($deploymentPath);

                return $archivePath;
            }
        }

        return $deploymentPath;
    }

    private function fixComposerRepositoryPath(string $composerJsonPath): void {
        if (!file_exists($composerJsonPath)) {
            return;
        }

        $composer = json_decode(file_get_contents($composerJsonPath), true);
        if (!$composer) {
            return;
        }

        // Update repository paths from ../../packages/* to ../packages/*
        if (isset($composer['repositories'])) {
            foreach ($composer['repositories'] as &$repo) {
                if (isset($repo['url']) && $repo['url'] === '../../packages/*') {
                    $repo['url'] = '../packages/*';
                    $this->log("Updated repository path to ../packages/*");
                }
            }
        }

        // Write back the updated composer.json
        file_put_contents(
            $composerJsonPath,
            json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }

    private function copyDirectory(string $source, string $destination, bool $excludeVendor = false): void {
        if (!is_dir($destination)) {
            mkdir($destination, 0755, true);
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $relativePath = substr($item->getPathname(), strlen($source) + 1);

            // Skip excluded directories and files
            if ($this->shouldExclude($relativePath, $excludeVendor)) {
                continue;
            }

            $destPath = $destination . '/' . $relativePath;

            if ($item->isDir()) {
                if (!is_dir($destPath)) {
                    mkdir($destPath, 0755, true);
                }
            } else {
                copy($item->getPathname(), $destPath);
            }
        }
    }

    private function shouldExclude(string $path, bool $excludeVendor): bool {
        $pathParts = explode('/', $path);
        $firstDir = $pathParts[0] ?? '';
        $filename = basename($path);

        // Exclude specific directories
        if (in_array($firstDir, $this->config['exclude_dirs'])) {
            return true;
        }

        // Exclude vendor if requested
        if ($excludeVendor && $firstDir === 'vendor') {
            return true;
        }

        // Exclude specific files
        if (in_array($filename, $this->config['exclude_files'])) {
            return true;
        }

        return false;
    }

    private function createDeploymentManifest(string $deploymentPath, string $appName, array $dependencies, array $options): void {
        $manifest = [
            'app' => [
                'name' => $appName,
                'version' => '1.0.0',
                'created_at' => date('c'),
            ],
            'requirements' => [
                'php' => $dependencies['php'],
            ],
            'packages' => array_keys($dependencies['packages']),
            'external_dependencies' => $dependencies['external'],
            'options' => $options,
        ];

        file_put_contents(
            $deploymentPath . '/deployment-manifest.json',
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }

    private function createDeploymentScript(string $deploymentPath, string $appName, array $dependencies): void {
        $script = <<<'SCRIPT'
#!/bin/bash

set -e

echo "Deploying {APP_NAME}..."

# Check PHP version
php_version=$(php -r "echo PHP_VERSION;")
echo "Current PHP version: $php_version"

# Install external dependencies
if [ -f "app/composer.json" ]; then
    cd app
    composer install --no-dev --optimize-autoloader
    cd ..
fi

echo "Deployment completed successfully!"
echo "You can now run the application from the 'app' directory"

SCRIPT;

        $script = str_replace('{APP_NAME}', $appName, $script);

        $scriptPath = $deploymentPath . '/deploy.sh';
        file_put_contents($scriptPath, $script);
        chmod($scriptPath, 0755);
    }

    private function createArchive(string $deploymentPath, string $deploymentName): string {
        $archivePath = dirname($deploymentPath) . '/' . $deploymentName . '.tar.gz';

        $command = sprintf(
            'cd %s && tar -czf %s %s',
            escapeshellarg(dirname($deploymentPath)),
            escapeshellarg($archivePath),
            escapeshellarg($deploymentName)
        );

        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            throw new Exception("Failed to create archive");
        }

        return $archivePath;
    }

    private function removeDirectory(string $dir): void {
        if (!is_dir($dir)) {
            return;
        }

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $fileinfo) {
            if ($fileinfo->isDir()) {
                rmdir($fileinfo->getRealPath());
            } else {
                unlink($fileinfo->getRealPath());
            }
        }

        rmdir($dir);
    }

    public function listApps(): array {
        $apps = [];

        if (!is_dir($this->appsPath)) {
            return $apps;
        }

        foreach (scandir($this->appsPath) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $appPath = $this->appsPath . '/' . $item;
            if (is_dir($appPath) && file_exists($appPath . '/composer.json')) {
                $composer = json_decode(file_get_contents($appPath . '/composer.json'), true);
                $apps[$item] = [
                    'name' => $composer['name'] ?? $item,
                    'description' => $composer['description'] ?? '',
                    'path' => $appPath,
                ];
            }
        }

        return $apps;
    }

    public function listPackages(): array {
        $packages = [];

        if (!is_dir($this->packagesPath)) {
            return $packages;
        }

        foreach (scandir($this->packagesPath) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $packagePath = $this->packagesPath . '/' . $item;
            if (is_dir($packagePath) && file_exists($packagePath . '/composer.json')) {
                $composer = json_decode(file_get_contents($packagePath . '/composer.json'), true);
                $packages[$item] = [
                    'name' => $composer['name'] ?? "nc/{$item}",
                    'description' => $composer['description'] ?? '',
                    'path' => $packagePath,
                ];
            }
        }

        return $packages;
    }

    private function log(string $message): void {
        echo "[" . date('H:i:s') . "] {$message}\n";
    }

    private function error(string $message): void {
        echo "[" . date('H:i:s') . "] ERROR: {$message}\n";
    }
}

// CLI Interface
function showUsage(): void {
    echo "Application Deployment Tool\n";
    echo "Usage: php deploy.php <command> [options]\n\n";
    echo "Commands:\n";
    echo "  deploy <app-name>     Deploy an application\n";
    echo "  list-apps            List all available applications\n";
    echo "  list-packages        List all available packages\n";
    echo "  help                 Show this help message\n\n";
    echo "Options:\n";
    echo "  --no-archive         Don't create archive, keep as directory\n";
    echo "  --output-dir <dir>   Specify output directory\n";
    echo "\nExamples:\n";
    echo "  php deploy.php deploy app2\n";
    echo "  php deploy.php deploy app2 --no-archive\n";
    echo "  php deploy.php list-apps\n";
}

// Main execution
if ($argc < 2) {
    showUsage();
    exit(1);
}

$command = $argv[1];
$deployer = new DeploymentManager();

switch ($command) {
    case 'deploy':
        if ($argc < 3) {
            echo "Error: App name required\n";
            showUsage();
            exit(1);
        }

        $appName = $argv[2];
        $options = [];

        // Parse options
        for ($i = 3; $i < $argc; $i++) {
            switch ($argv[$i]) {
                case '--no-archive':
                    $options['archive'] = false;
                    break;
                case '--output-dir':
                    if ($i + 1 < $argc) {
                        $options['output_dir'] = $argv[++$i];
                    }
                    break;
            }
        }

        $success = $deployer->deploy($appName, $options);
        exit($success ? 0 : 1);

    case 'list-apps':
        $apps = $deployer->listApps();
        echo "Available applications:\n";
        foreach ($apps as $key => $app) {
            echo "  {$key}: {$app['name']}\n";
            if ($app['description']) {
                echo "    {$app['description']}\n";
            }
        }
        break;

    case 'list-packages':
        $packages = $deployer->listPackages();
        echo "Available packages:\n";
        foreach ($packages as $key => $package) {
            echo "  {$key}: {$package['name']}\n";
            if ($package['description']) {
                echo "    {$package['description']}\n";
            }
        }
        break;

    case 'help':
        showUsage();
        break;

    default:
        echo "Unknown command: {$command}\n";
        showUsage();
        exit(1);
}
