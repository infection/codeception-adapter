<?php
/**
 * This code is licensed under the BSD 3-Clause License.
 *
 * Copyright (c) 2017, Maks Rafalko
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * * Redistributions of source code must retain the above copyright notice, this
 *   list of conditions and the following disclaimer.
 *
 * * Redistributions in binary form must reproduce the above copyright notice,
 *   this list of conditions and the following disclaimer in the documentation
 *   and/or other materials provided with the distribution.
 *
 * * Neither the name of the copyright holder nor the names of its
 *   contributors may be used to endorse or promote products derived from
 *   this software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
 * AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE
 * FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL
 * DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR
 * SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
 * CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY,
 * OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */

declare(strict_types=1);

namespace Infection\TestFramework\Codeception;

use function array_filter;
use function array_key_exists;
use function array_map;
use function array_merge;
use function assert;
use function explode;
use function implode;
use Infection\AbstractTestFramework\Coverage\TestLocation;
use Infection\AbstractTestFramework\MemoryUsageAware;
use Infection\AbstractTestFramework\TestFrameworkAdapter;
use Infection\StreamWrapper\IncludeInterceptor;
use Infection\TestFramework\Codeception\Coverage\JUnitTestCaseSorter;
use InvalidArgumentException;
use function is_string;
use const LOCK_EX;
use Phar;
use function preg_match;
use ReflectionClass;
use function Safe\file_put_contents;
use function sprintf;
use function str_replace;
use function str_starts_with;
use function strlen;
use function strstr;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;
use function trim;
use function version_compare;

final class CodeceptionAdapter implements MemoryUsageAware, TestFrameworkAdapter
{
    public const COVERAGE_DIR = 'codeception-coverage-xml';

    public const NAME = 'codeception';

    /**
     * Minimum version of Codeception that supports the --disable-coverage-php flag.
     * This flag was introduced in Codeception 5.2.0.
     */
    private const MIN_VERSION_DISABLE_COVERAGE_PHP = '5.2.0';

    private const DEFAULT_ARGS_AND_OPTIONS = [
        '--no-colors',
        '--fail-fast',
    ];

    private ?string $cachedVersion = null;

    public function __construct(
        private string $testFrameworkExecutable,
        private CommandLineBuilder $commandLineBuilder,
        private VersionParser $versionParser,
        private JUnitTestCaseSorter $jUnitTestCaseSorter,
        private Filesystem $filesystem,
        private string $jUnitFilePath,
        private string $tmpDir,
        private string $projectDir,
        /**
         * @var array<string, mixed>
         */
        private array $originalConfigContentParsed,
        /**
         * @var array<string>
         */
        private array $srcDirs,
    ) {
    }

    public function hasJUnitReport(): bool
    {
        return true;
    }

    public function testsPass(string $output): bool
    {
        if (preg_match('/failures!/i', $output) > 0) {
            return false;
        }

        if (preg_match('/errors!/i', $output) > 0) {
            return false;
        }

        // OK (XX tests, YY assertions)
        $isOk = preg_match('/OK\s\(/', $output) > 0;

        // "OK, but incomplete, skipped, or risky tests!"
        $isOkWithInfo = preg_match('/OK\s?,/', $output) > 0;

        // "Warnings!" - e.g. when deprecated functions are used, but tests pass
        $isWarning = preg_match('/warnings!/i', $output) > 0;

        return $isOk || $isOkWithInfo || $isWarning;
    }

    public function getMemoryUsed(string $output): float
    {
        if (preg_match('/Memory: (\d+(?:\.\d+))\s*MB/', $output, $match) > 0) {
            return (float) $match[1];
        }

        return -1;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getInitialTestRunCommandLine(string $extraOptions, array $phpExtraArgs, bool $skipCoverage): array
    {
        $argumentsAndOptions = $this->prepareArgumentsAndOptions($extraOptions);

        return $this->commandLineBuilder->build(
            $this->testFrameworkExecutable,
            $phpExtraArgs,
            array_merge(
                $argumentsAndOptions,
                [
                    '--coverage-phpunit',
                    self::COVERAGE_DIR,
                    // JUnit report
                    '--xml',
                    $this->jUnitFilePath,
                    '-o',
                    "paths: output: {$this->tmpDir}",
                    '-o',
                    sprintf('coverage: enabled: %s', Stringifier::stringifyBoolean(!$skipCoverage)),
                    '-o',
                    sprintf('coverage: include: %s', $this->getCoverageIncludeFiles($skipCoverage)),
                    '-o',
                    'settings: shuffle: true',
                ],
                $this->getDisableCoveragePhpOptions($skipCoverage),
            ),
        );
    }

    /**
     * @param TestLocation[] $coverageTests
     *
     * @return string[]
     */
    public function getMutantCommandLine(
        array $coverageTests,
        string $mutatedFilePath,
        string $mutationHash,
        string $mutationOriginalFilePath,
        string $extraOptions,
    ): array {
        $argumentsAndOptions = $this->prepareArgumentsAndOptions($extraOptions);

        $commandLine = $this->commandLineBuilder->build($this->testFrameworkExecutable, [], $argumentsAndOptions);

        $output = sprintf('%s/%s', $this->tmpDir, $mutationHash);

        $interceptorFilePath = sprintf(
            '%s/interceptor.codeception.%s.php',
            $this->tmpDir,
            $mutationHash,
        );

        file_put_contents($interceptorFilePath, $this->createCustomBootstrapWithInterceptor($mutationOriginalFilePath, $mutatedFilePath), LOCK_EX);

        $uniqueTestFilePaths = implode(',', $this->jUnitTestCaseSorter->getUniqueSortedFileNames($coverageTests));

        return array_merge(
            $commandLine,
            [
                '--group',
                'infection',
                '--bootstrap',
                $interceptorFilePath,
                '-o',
                "paths: output: {$output}",
                '-o',
                'coverage: enabled: false',
                '-o',
                "bootstrap: {$interceptorFilePath}",
                '-o',
                "groups: infection: [$uniqueTestFilePaths]",
            ],
        );
    }

    public function getVersion(): string
    {
        if ($this->cachedVersion !== null) {
            return $this->cachedVersion;
        }

        $testFrameworkVersionExecutable = $this->commandLineBuilder->build(
            $this->testFrameworkExecutable,
            [],
            ['--version'],
        );

        $process = new Process($testFrameworkVersionExecutable);
        $process->mustRun();

        try {
            $version = $this->versionParser->parse($process->getOutput());
        } catch (InvalidArgumentException $e) {
            $version = 'unknown';
        }

        $this->cachedVersion = $version;

        return $this->cachedVersion;
    }

    public function getInitialTestsFailRecommendations(string $commandLine): string
    {
        return sprintf('Check the executed command to identify the problem: %s', $commandLine);
    }

    /**
     * Returns the --disable-coverage-php option if coverage is enabled and the Codeception
     * version supports it (>= 5.2.0).
     *
     * @return string[]
     */
    private function getDisableCoveragePhpOptions(bool $skipCoverage): array
    {
        if ($skipCoverage) {
            return [];
        }

        $version = $this->getVersion();

        if ($version === 'unknown') {
            return [];
        }

        if (version_compare($version, self::MIN_VERSION_DISABLE_COVERAGE_PHP, '<')) {
            return [];
        }

        return ['--disable-coverage-php'];
    }

    private function getInterceptorFileContent(string $interceptorPath, string $originalFilePath, string $mutatedFilePath): string
    {
        $infectionPhar = '';

        if (str_starts_with(__FILE__, 'phar:')) {
            $infectionPhar = sprintf(
                '\Phar::loadPhar("%s", "%s");',
                str_replace('phar://', '', Phar::running(true)),
                'infection.phar',
            );
        }

        $namespacePrefix = $this->getInterceptorNamespacePrefix();

        return <<<CONTENT
            {$infectionPhar}
            require_once '{$interceptorPath}';

            use {$namespacePrefix}Infection\StreamWrapper\IncludeInterceptor;

            IncludeInterceptor::intercept('{$originalFilePath}', '{$mutatedFilePath}');
            IncludeInterceptor::enable();
            CONTENT;
    }

    private function createCustomBootstrapWithInterceptor(string $originalFilePath, string $mutatedFilePath): string
    {
        $originalBootstrap = $this->getOriginalBootstrapFilePath();
        $bootstrapPlaceholder = $originalBootstrap !== null && strlen($originalBootstrap) > 0 ? "require_once '{$originalBootstrap}';" : '';

        $class = new ReflectionClass(IncludeInterceptor::class);
        $interceptorPath = $class->getFileName();

        $customBootstrap = <<<AUTOLOAD
            <?php

            %s
            %s

            AUTOLOAD;

        return sprintf(
            $customBootstrap,
            $bootstrapPlaceholder,
            $this->getInterceptorFileContent((string) $interceptorPath, $originalFilePath, $mutatedFilePath),
        );
    }

    private function getOriginalBootstrapFilePath(): ?string
    {
        if (!array_key_exists('bootstrap', $this->originalConfigContentParsed)) {
            return null;
        }

        if ($this->filesystem->isAbsolutePath($this->originalConfigContentParsed['bootstrap'])) {
            return $this->originalConfigContentParsed['bootstrap'];
        }

        return sprintf(
            '%s/%s/%s',
            $this->projectDir,
            $this->originalConfigContentParsed['paths']['tests'] ?? 'tests',
            $this->originalConfigContentParsed['bootstrap'],
        );
    }

    private function getInterceptorNamespacePrefix(): string
    {
        $prefix = strstr(__NAMESPACE__, 'Infection', true);
        assert(is_string($prefix));

        return $prefix;
    }

    /**
     * @return string[]
     */
    private function prepareArgumentsAndOptions(string $extraOptions): array
    {
        return array_filter(array_merge(
            ['run'],
            explode(' ', $extraOptions),
            self::DEFAULT_ARGS_AND_OPTIONS,
        ));
    }

    private function getCoverageIncludeFiles(bool $skipCoverage): string
    {
        // if coverage should be skipped, this anyway will be ignored, return early
        if ($skipCoverage) {
            return Stringifier::stringifyArray([]);
        }

        $coverage = array_merge($this->originalConfigContentParsed['coverage'] ?? [], ['enabled' => true]);

        $includedFiles = array_key_exists('include', $coverage)
            ? $coverage['include']
            : array_map(
                static function (string $dir): string {
                    return trim($dir, '/') . '/*.php';
                },
                $this->srcDirs,
            );

        return Stringifier::stringifyArray($includedFiles);
    }
}
