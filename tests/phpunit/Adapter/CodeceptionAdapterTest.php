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

namespace Infection\Tests\TestFramework\Codeception\Adapter;

use function array_merge;
use function file_get_contents;
use Generator;
use function implode;
use Infection\TestFramework\Codeception\CodeceptionAdapter;
use Infection\TestFramework\Codeception\CommandLineBuilder;
use Infection\TestFramework\Codeception\Coverage\JUnitTestCaseSorter;
use Infection\TestFramework\Codeception\VersionParser;
use Infection\Tests\TestFramework\Codeception\FileSystem\FileSystemTestCase;
use function Infection\Tests\TestFramework\Codeception\normalizePath as p;
use function realpath;
use function sprintf;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @group integration Requires some I/O operations
 */
final class CodeceptionAdapterTest extends FileSystemTestCase
{
    private const MUTATION_HASH = 'a1b2c3';
    private const ORIGINAL_FILE_PATH = '/original/file/path';
    private const MUTATED_FILE_PATH = '/mutated/file/path';

    private const DEFAULT_CONFIG = [
        'paths' => [
            'tests' => 'tests',
            'output' => 'tests/_output',
            'data' => 'tests/_data',
            'support' => 'tests/_support',
            'envs' => 'tests/_envs',
        ],
        'actor_suffix' => 'Tester',
        'extensions' => [
            'enabled' => ['Codeception\Extension\RunFailed'],
        ],
    ];

    private string $pathToProject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pathToProject = p((string) realpath(__DIR__ . '/Fixtures/Files/codeception'));
    }

    public function test_it_has_a_name(): void
    {
        $adapter = $this->createAdapter();
        $this->assertSame('codeception', $adapter->getName());
    }

    /**
     * @dataProvider passProvider
     */
    public function test_it_determines_whether_tests_pass_or_not(string $output, bool $expectedResult): void
    {
        $adapter = $this->createAdapter();
        $result = $adapter->testsPass($output);

        $this->assertSame($expectedResult, $result);
    }

    /**
     * @dataProvider memoryReportProvider
     */
    public function test_it_determines_used_memory_amount(string $output, float $expectedResult): void
    {
        $adapter = $this->createAdapter();
        $result = $adapter->getMemoryUsed($output);

        $this->assertSame($expectedResult, $result);
    }

    /**
     * @return Generator<int, array{0: string, 1: float}>
     */
    public static function memoryReportProvider(): Generator
    {
        yield ['Memory: 8.00MB', 8.0];

        yield ['Memory: 68.00MB', 68.0];

        yield ['Memory: 68.00 MB', 68.0];

        yield ['Time: 2.51 seconds', -1.0];
    }

    /**
     * @return Generator<int, array{0: string, 1: bool}>
     */
    public static function passProvider(): Generator
    {
        yield ['OK, but incomplete, skipped, or risky tests!', true];

        yield ['OK (5 tests, 3 assertions)', true];

        yield ['FAILURES!', false];

        yield ['ERRORS!', false];

        yield ['unhandled string', false];
    }

    public function test_it_sets_coverage_phpunit_dir(): void
    {
        $adapter = $this->createAdapter();
        $commandLine = $adapter->getInitialTestRunCommandLine('', [], true);

        $this->assertContains('--coverage-phpunit', $commandLine);
        $this->assertContains(CodeceptionAdapter::COVERAGE_DIR, $commandLine);
    }

    public function test_it_sets_junit_xml_path(): void
    {
        $adapter = $this->createAdapter();
        $commandLine = $adapter->getInitialTestRunCommandLine('', [], true);

        $this->assertContains('--xml', $commandLine);
        $this->assertContains('path/to/junit', $commandLine);
    }

    public function test_it_sets_the_output_dir_to_tmp_dir(): void
    {
        $adapter = $this->createAdapter();
        $commandLine = $adapter->getInitialTestRunCommandLine('', [], true);

        $this->assertContains(sprintf('paths: output: %s', $this->tmp), $commandLine);
    }

    public function test_it_enables_coverage_if_not_skipped(): void
    {
        $adapter = $this->createAdapter();
        $commandLine = $adapter->getInitialTestRunCommandLine('', [], false);

        $this->assertContains('coverage: enabled: true', $commandLine);
    }

    public function test_it_disables_coverage_if_skipped(): void
    {
        $adapter = $this->createAdapter();
        $commandLine = $adapter->getInitialTestRunCommandLine('', [], true);

        $this->assertContains('coverage: enabled: false', $commandLine);
        $this->assertContains('coverage: include: []', $commandLine);
    }

    public function test_it_populates_include_coverage_key_from_src_folders_if_not_set(): void
    {
        $adapter = $this->createAdapter();
        $commandLine = $adapter->getInitialTestRunCommandLine('', [], false);

        $this->assertContains('coverage: include: [projectSrc/dir/*.php]', $commandLine);
    }

    public function test_it_runs_tests_with_a_random_order(): void
    {
        $adapter = $this->createAdapter();
        $commandLine = $adapter->getInitialTestRunCommandLine('', [], false);

        $this->assertContains('settings: shuffle: true', $commandLine);
    }

    public function test_it_disables_coverage_for_mutant_command_line(): void
    {
        $adapter = $this->createAdapter();
        $commandLine = $adapter->getMutantCommandLine(
            [],
            self::MUTATED_FILE_PATH,
            self::MUTATION_HASH,
            self::ORIGINAL_FILE_PATH,
            ''
        );

        $this->assertContains('coverage: enabled: false', $commandLine);
    }

    public function test_it_adds_extra_options_for_mutant_command_line(): void
    {
        $adapter = $this->createAdapter();
        $commandLine = $adapter->getMutantCommandLine(
            [],
            self::MUTATED_FILE_PATH,
            self::MUTATION_HASH,
            self::ORIGINAL_FILE_PATH,
            '--filter=xyz'
        );

        $this->assertContains('--filter=xyz', $commandLine);
    }

    public function test_it_sets_infection_group(): void
    {
        $adapter = $this->createAdapter();
        $commandLine = $adapter->getMutantCommandLine(
            [],
            self::MUTATED_FILE_PATH,
            self::MUTATION_HASH,
            self::ORIGINAL_FILE_PATH,
            '--filter=xyz'
        );

        $this->assertContains('--group', $commandLine);
        $this->assertContains('infection', $commandLine);
    }

    public function test_it_sets_bootstrap_file(): void
    {
        $adapter = $this->createAdapter();
        $commandLine = $adapter->getMutantCommandLine(
            [],
            self::MUTATED_FILE_PATH,
            self::MUTATION_HASH,
            self::ORIGINAL_FILE_PATH,
            '--filter=xyz'
        );

        $this->assertContains('--bootstrap', $commandLine);
    }

    public function test_it_creates_interceptor_file(): void
    {
        $adapter = $this->createAdapter();

        $adapter->getMutantCommandLine(
            [],
            self::MUTATED_FILE_PATH,
            self::MUTATION_HASH,
            self::ORIGINAL_FILE_PATH,
            ''
        );

        $expectedConfigPath = $this->tmp . '/interceptor.codeception.a1b2c3.php';

        $this->assertFileExists($expectedConfigPath);
    }

    public function test_it_does_not_add_original_bootstrap_to_the_created_config_file_if_not_exists(): void
    {
        $adapter = $this->createAdapter();

        $adapter->getMutantCommandLine(
            [],
            self::MUTATED_FILE_PATH,
            self::MUTATION_HASH,
            self::ORIGINAL_FILE_PATH,
            ''
        );

        $this->assertStringNotContainsString(
            'bootstrap',
            (string) file_get_contents($this->tmp . '/interceptor.codeception.a1b2c3.php')
        );
    }

    public function test_adds_original_bootstrap_to_the_created_config_file_with_absolute_path(): void
    {
        $config = array_merge(
            self::DEFAULT_CONFIG,
            [
                'bootstrap' => '/original/bootstrap.php',
            ]
        );

        $adapter = $this->createAdapter($config);

        $adapter->getMutantCommandLine(
            [],
            self::MUTATED_FILE_PATH,
            self::MUTATION_HASH,
            self::ORIGINAL_FILE_PATH,
            ''
        );

        $this->assertStringContainsString(
            "require_once '/original/bootstrap.php';",
            (string) file_get_contents($this->tmp . '/interceptor.codeception.a1b2c3.php')
        );
    }

    public function test_adds_original_bootstrap_to_the_created_config_file_with_relative_path(): void
    {
        $config = array_merge(
            self::DEFAULT_CONFIG,
            [
                'bootstrap' => 'original/bootstrap.php',
            ]
        );

        $adapter = $this->createAdapter($config);

        $adapter->getMutantCommandLine(
            [],
            self::MUTATED_FILE_PATH,
            self::MUTATION_HASH,
            self::ORIGINAL_FILE_PATH,
            ''
        );

        $this->assertStringContainsString(
            "tests/original/bootstrap.php';",
            (string) file_get_contents($this->tmp . '/interceptor.codeception.a1b2c3.php')
        );
    }

    public function test_it_has_junit_report(): void
    {
        $adapter = $this->createAdapter();

        $this->assertTrue($adapter->hasJUnitReport(), 'Codeception Framework must have JUnit report');
    }

    public function test_codeception_name(): void
    {
        $this->assertSame('codeception', $this->createAdapter()->getName());
    }

    public function test_prepare_arguments_and_options_contains_run_first(): void
    {
        $adapter = $this->createAdapter();

        $commandLine = $adapter->getMutantCommandLine(
            [],
            self::MUTATED_FILE_PATH,
            self::MUTATION_HASH,
            self::ORIGINAL_FILE_PATH,
            '--skip blah'
        );

        $this->assertStringContainsString(
            'path/to/codeception run --skip blah',
            implode(' ', $commandLine)
        );
    }

    /**
     * @param array<string, mixed>|null $config
     */
    private function createAdapter(?array $config = null): CodeceptionAdapter
    {
        return new CodeceptionAdapter(
            '/path/to/codeception',
            new CommandLineBuilder(),
            new VersionParser(),
            new JUnitTestCaseSorter(),
            new Filesystem(),
            'path/to/junit',
            $this->tmp,
            $this->pathToProject,
            $config ?? self::DEFAULT_CONFIG,
            ['projectSrc/dir']
        );
    }
}
