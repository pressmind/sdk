<?php

namespace Pressmind\Tests\Unit\System;

use PHPUnit\Framework\TestCase;
use Pressmind\System\EnvironmentValidation;

class EnvironmentValidationTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir() . '/pm_env_validation_' . uniqid();
        mkdir($this->tempDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
        parent::tearDown();
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    // --- findIBETeamConfigFile ---

    public function testFindIBETeamConfigFileReturnsNullForNonExistentPath(): void
    {
        $result = EnvironmentValidation::findIBETeamConfigFile('/non/existent/path');
        $this->assertNull($result);
    }

    public function testFindIBETeamConfigFileReturnsNullWhenNoConfigExists(): void
    {
        $result = EnvironmentValidation::findIBETeamConfigFile($this->tempDir);
        $this->assertNull($result);
    }

    public function testFindIBETeamConfigFileIgnoresUnrelatedFiles(): void
    {
        file_put_contents($this->tempDir . '/other.php', '<?php echo "hi";');

        $result = EnvironmentValidation::findIBETeamConfigFile($this->tempDir);
        $this->assertNull($result);
    }

    public function testFindIBETeamConfigFileFindsMatchingFile(): void
    {
        $configFile = $this->tempDir . '/my-config.php';
        file_put_contents($configFile, "<?php\ndefine('IBETEAM_VERSION', '6_0');\n");

        $result = EnvironmentValidation::findIBETeamConfigFile($this->tempDir);
        $this->assertSame($configFile, $result);
    }

    public function testFindIBETeamConfigFileSkipsConfigWithoutDefine(): void
    {
        $configFile = $this->tempDir . '/app-config.php';
        file_put_contents($configFile, "<?php\n\$version = '6_0';\n");

        $result = EnvironmentValidation::findIBETeamConfigFile($this->tempDir);
        $this->assertNull($result);
    }

    // --- validateIBETeamVersion ---

    public function testValidateIBETeamVersionFailsWhenNoConfigFound(): void
    {
        $result = EnvironmentValidation::validateIBETeamVersion('/non/existent/path');

        $this->assertFalse($result['valid']);
        $this->assertNull($result['configPath']);
    }

    public function testValidateIBETeamVersionDetectsHardcodedValue(): void
    {
        $configFile = $this->tempDir . '/pm-config.php';
        file_put_contents($configFile, "<?php\ndefine('IBETEAM_VERSION', '6_0');\n");

        $result = EnvironmentValidation::validateIBETeamVersion($this->tempDir);

        $this->assertFalse($result['valid']);
        $this->assertFalse($result['usesConstantReference']);
        $this->assertStringContainsString('hardcoded', $result['message']);
    }

    public function testValidateIBETeamVersionAcceptsConstantReference(): void
    {
        $configFile = $this->tempDir . '/pm-config.php';
        file_put_contents(
            $configFile,
            "<?php\ndefine('IBETEAM_VERSION', \\Pressmind\\System\\Info::IBE_IMPORT_VERSION);\n"
        );

        $result = EnvironmentValidation::validateIBETeamVersion($this->tempDir);

        $this->assertTrue($result['valid']);
        $this->assertTrue($result['usesConstantReference']);
    }

    public function testValidateIBETeamVersionDetectsUnknownDefinition(): void
    {
        $configFile = $this->tempDir . '/pm-config.php';
        file_put_contents(
            $configFile,
            "<?php\ndefine('IBETEAM_VERSION', SOME_OTHER_CONSTANT);\n"
        );

        $result = EnvironmentValidation::validateIBETeamVersion($this->tempDir);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('unknown definition', $result['message']);
    }

    // --- patchIBETeamVersion ---

    public function testPatchIBETeamVersionReturnsFalseForMissingFile(): void
    {
        $result = EnvironmentValidation::patchIBETeamVersion('/non/existent/file.php');
        $this->assertFalse($result);
    }

    public function testPatchIBETeamVersionReplacesHardcodedValue(): void
    {
        $configFile = $this->tempDir . '/pm-config.php';
        file_put_contents($configFile, "<?php\ndefine('IBETEAM_VERSION', '6_0');\n");

        $result = EnvironmentValidation::patchIBETeamVersion($configFile);

        $this->assertTrue($result);
        $content = file_get_contents($configFile);
        $this->assertStringContainsString('Info::IBE_IMPORT_VERSION', $content);
        $this->assertStringNotContainsString("'6_0'", $content);
    }

    public function testPatchIBETeamVersionReturnsFalseWhenNoHardcodedValue(): void
    {
        $configFile = $this->tempDir . '/pm-config.php';
        file_put_contents(
            $configFile,
            "<?php\ndefine('IBETEAM_VERSION', \\Pressmind\\System\\Info::IBE_IMPORT_VERSION);\n"
        );

        $result = EnvironmentValidation::patchIBETeamVersion($configFile);
        $this->assertFalse($result);
    }

    // --- validateEnvironmentConsistency ---

    public function testProductionEnvRejectsPathContainingDevelopment(): void
    {
        $result = EnvironmentValidation::validateEnvironmentConsistency(
            'production',
            '/var/www/development/app'
        );

        $this->assertFalse($result['valid']);
        $this->assertCount(1, $result['violations']);
        $this->assertSame('APPLICATION_PATH', $result['violations'][0]['field']);
    }

    public function testDevelopmentEnvRejectsPathContainingProduction(): void
    {
        $result = EnvironmentValidation::validateEnvironmentConsistency(
            'development',
            '/var/www/production/app'
        );

        $this->assertFalse($result['valid']);
        $this->assertCount(1, $result['violations']);
    }

    public function testConsistentProductionConfigIsValid(): void
    {
        $result = EnvironmentValidation::validateEnvironmentConsistency(
            'production',
            '/var/www/live/app',
            'db_user_live',
            'mongodb://db-live:27017',
            'app_live'
        );

        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['violations']);
    }

    public function testTestingEnvSkipsConsistencyCheck(): void
    {
        $result = EnvironmentValidation::validateEnvironmentConsistency(
            'testing',
            '/var/www/development/app'
        );

        $this->assertTrue($result['valid']);
        $this->assertNull($result['forbiddenString']);
    }

    public function testMultipleViolationsAreReported(): void
    {
        $result = EnvironmentValidation::validateEnvironmentConsistency(
            'production',
            '/var/www/development/app',
            'development_user',
            null,
            'development_db'
        );

        $this->assertFalse($result['valid']);
        $this->assertCount(3, $result['violations']);
    }

    public function testConsistencyCheckIsCaseInsensitive(): void
    {
        $result = EnvironmentValidation::validateEnvironmentConsistency(
            'production',
            '/var/www/DEVELOPMENT/app'
        );

        $this->assertFalse($result['valid']);
    }

    // --- validatePHPVersion ---

    public function testValidatePHPVersionReturnsCurrentVersion(): void
    {
        $result = EnvironmentValidation::validatePHPVersion();

        $this->assertSame(PHP_VERSION, $result['currentVersion']);
        $this->assertSame('8.1.0', $result['requiredVersion']);
        // Running on PHP 8.3, so this must be valid
        $this->assertTrue($result['valid']);
    }

    // --- validatePhpCliBinary ---

    public function testValidatePhpCliBinaryAcceptsDefaultPhp(): void
    {
        $result = EnvironmentValidation::validatePhpCliBinary(null);

        // On CI/dev machines, 'php' should be in PATH
        $this->assertTrue($result['valid']);
        $this->assertFalse($result['isAbsolute']);
    }

    public function testValidatePhpCliBinaryRejectsNonExistentAbsolutePath(): void
    {
        $result = EnvironmentValidation::validatePhpCliBinary('/usr/bin/php_nonexistent_xyz');

        $this->assertFalse($result['valid']);
        $this->assertTrue($result['isAbsolute']);
    }

    public function testValidatePhpCliBinaryRejectsUnresolvableRelativeName(): void
    {
        $result = EnvironmentValidation::validatePhpCliBinary('php_binary_that_does_not_exist_xyz');

        $this->assertFalse($result['valid']);
        $this->assertFalse($result['isAbsolute']);
    }

    public function testValidatePhpCliBinaryRejectsNonExecutableAbsolutePath(): void
    {
        $nonExecutable = $this->tempDir . '/not-executable-php';
        touch($nonExecutable);
        chmod($nonExecutable, 0644);

        $result = EnvironmentValidation::validatePhpCliBinary($nonExecutable);

        $this->assertFalse($result['valid']);
        $this->assertTrue($result['isAbsolute']);
        $this->assertStringContainsString('not executable', $result['message']);
    }

    public function testValidateIBETeamVersionConstantNotFoundInFile(): void
    {
        $configFile = $this->tempDir . '/app-config.php';
        file_put_contents($configFile, "<?php\n\$other = '6_0';\n");
        // findIBETeamConfigFile only returns files that contain define('IBETEAM_VERSION', ...); this file does not
        $result = EnvironmentValidation::validateIBETeamVersion($this->tempDir);

        $this->assertFalse($result['valid']);
        $this->assertNull($result['configPath']);
        $this->assertStringContainsString('No IBE-Team config file found', $result['message']);
    }

    public function testPatchIBETeamVersionReturnsFalseWhenFileNotWritable(): void
    {
        $configFile = $this->tempDir . '/readonly.php';
        file_put_contents($configFile, "<?php\ndefine('IBETEAM_VERSION', '6_0');\n");
        chmod($configFile, 0444);

        if (is_writable($configFile)) {
            chmod($configFile, 0644);
            $result = EnvironmentValidation::patchIBETeamVersion($configFile);
            $this->assertTrue($result, 'Root can write read-only files, patch should succeed');
        } else {
            $result = EnvironmentValidation::patchIBETeamVersion($configFile);
            $this->assertFalse($result);
            chmod($configFile, 0644);
        }
    }

    public function testPatchIBETeamVersionReturnsFalseWhenContentNotReplaceable(): void
    {
        $configFile = $this->tempDir . '/no-match.php';
        file_put_contents($configFile, "<?php\necho 'no define';\n");

        $result = EnvironmentValidation::patchIBETeamVersion($configFile);

        $this->assertFalse($result);
    }
}
