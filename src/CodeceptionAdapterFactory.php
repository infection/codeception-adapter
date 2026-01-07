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

use Infection\AbstractTestFramework\TestFrameworkAdapter;
use Infection\AbstractTestFramework\TestFrameworkAdapterFactory;
use Infection\TestFramework\Codeception\Coverage\JUnitTestCaseSorter;
use function Safe\file_get_contents;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

final class CodeceptionAdapterFactory implements TestFrameworkAdapterFactory
{
    private const COVERAGE_DIR = 'coverage-xml';

    /**
     * @param string[] $sourceDirectories
     */
    public static function create(
        string $testFrameworkExecutable,
        string $tmpDir,
        string $testFrameworkConfigPath,
        ?string $testFrameworkConfigDir,
        string $jUnitFilePath,
        string $projectDir,
        array $sourceDirectories,
        bool $skipCoverage,
    ): TestFrameworkAdapter {
        return new CodeceptionAdapter(
            $testFrameworkExecutable,
            new CommandLineBuilder(),
            new VersionParser(),
            new JUnitTestCaseSorter(),
            new Filesystem(),
            $jUnitFilePath,
            $tmpDir,
            $projectDir,
            // For Codeception the coverage path is relative to the output
            // directory configured.
            self::COVERAGE_DIR,
            self::parseYaml($testFrameworkConfigPath),
            $sourceDirectories,
        );
    }

    public static function getAdapterName(): string
    {
        return CodeceptionAdapter::NAME;
    }

    public static function getExecutableName(): string
    {
        return 'codecept';
    }

    /**
     * @return array<string, mixed>
     */
    private static function parseYaml(string $codeceptionConfigPath): array
    {
        $codeceptionConfigContent = file_get_contents($codeceptionConfigPath);

        try {
            $codeceptionConfigContentParsed = Yaml::parse($codeceptionConfigContent);
        } catch (ParseException $e) {
            throw CodeceptionConfigParseException::fromPath($codeceptionConfigPath, $e);
        }

        return $codeceptionConfigContentParsed;
    }
}
