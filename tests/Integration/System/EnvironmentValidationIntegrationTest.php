<?php

namespace Pressmind\Tests\Integration\System;

use Pressmind\System\EnvironmentValidation;
use Pressmind\Tests\Integration\AbstractIntegrationTestCase;

/**
 * Integration tests for EnvironmentValidation.
 * Uses temporary directories and config files to test IBETeam version validation,
 * environment consistency, PHP version and CLI binary validation.
 */
class EnvironmentValidationIntegrationTest extends AbstractIntegrationTestCase
{
    private ?string $tempDir = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir() . '/pm_env_validation_test_' . getmypid();
        if (!is_dir($this->tempDir)) {
            mkdir($this->tempDir, 0755, true);
        }
    }

    protected function tearDown(): void
    {
        if ($this->tempDir !== null && is_dir($this->tempDir)) {
            $files = glob($this->tempDir . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            rmdir($this->tempDir);
        }
        parent::tearDown();
    }

    // --- findIBETeamConfigFile ---

    public function testFindIBETeamConfigFileReturnsNullForNonExistentDirectory(): void
    {
        $result = EnvironmentValidation::findIBETeamConfigFile('/tmp/nonexistent_dir_' . uniqid());
        $this->assertNull($result);
    }

    public function testFindIBETeamConfigFileReturnsNullWhenNoConfigFiles(): void
    {
        $result = EnvironmentValidation::findIBETeamConfigFile($this->tempDir);
        $this->assertNull($result);
    }

    public function testFindIBETeamConfigFileReturnsNullWhenConfigWithoutDefine(): void
    {
        file_put_contents($this->tempDir . '/config.php', '<?php $foo = "bar";');

        $result = EnvironmentValidation::findIBETeamConfigFile($this->tempDir);
        $this->assertNull($result);
    }

    public function testFindIBETeamConfigFileFindsCorrectFile(): void
    {
        $configPath = $this->tempDir . '/config.php';
        file_put_contents($configPath, "<?php\ndefine('IBETEAM_VERSION', '6_0');");

        $result = EnvironmentValidation::findIBETeamConfigFile($this->tempDir);
        $this->assertSame($configPath, $result);
    }

    // --- validateIBETeamVersion ---

    public function testValidateIBETeamVersionNoConfigFile(): void
    {
        $result = EnvironmentValidation::validateIBETeamVersion($this->tempDir);

        $this->assertTrue($result['valid']);
        $this->assertNull($result['configPath']);
        $this->assertFalse($result['usesConstantReference']);
        $this->assertStringContainsString('check skipped', $result['message']);
    }

    public function testValidateIBETeamVersionHardcodedValue(): void
    {
        file_put_contents(
            $this->tempDir . '/config.php',
            "<?php\ndefine('IBETEAM_VERSION', '6_0');"
        );

        $result = EnvironmentValidation::validateIBETeamVersion($this->tempDir);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('hardcoded', $result['message']);
        $this->assertFalse($result['usesConstantReference']);
    }

    public function testValidateIBETeamVersionCorrectConstantReference(): void
    {
        file_put_contents(
            $this->tempDir . '/config.php',
            "<?php\ndefine('IBETEAM_VERSION', \\Pressmind\\System\\Info::IBE_IMPORT_VERSION);"
        );

        $result = EnvironmentValidation::validateIBETeamVersion($this->tempDir);

        $this->assertTrue($result['valid']);
        $this->assertTrue($result['usesConstantReference']);
    }

    public function testValidateIBETeamVersionShortConstantReference(): void
    {
        file_put_contents(
            $this->tempDir . '/config.php',
            "<?php\nuse Pressmind\\System\\Info;\ndefine('IBETEAM_VERSION', Info::IBE_IMPORT_VERSION);"
        );

        $result = EnvironmentValidation::validateIBETeamVersion($this->tempDir);

        $this->assertTrue($result['valid']);
        $this->assertTrue($result['usesConstantReference']);
    }

    public function testValidateIBETeamVersionUnknownDefinition(): void
    {
        file_put_contents(
            $this->tempDir . '/config.php',
            "<?php\ndefine('IBETEAM_VERSION', SOME_OTHER_CONSTANT);"
        );

        $result = EnvironmentValidation::validateIBETeamVersion($this->tempDir);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('unknown definition', $result['message']);
    }

    public function testValidateIBETeamVersionConstantNotFoundInFile(): void
    {
        file_put_contents(
            $this->tempDir . '/config.php',
            "<?php\ndefine('IBETEAM_VERSION');"
        );

        $result = EnvironmentValidation::validateIBETeamVersion($this->tempDir);
        $this->assertFalse($result['valid']);
    }

    // --- patchIBETeamVersion ---

    public function testPatchIBETeamVersionSuccess(): void
    {
        $configPath = $this->tempDir . '/config.php';
        file_put_contents($configPath, "<?php\ndefine('IBETEAM_VERSION', '6_0');");

        $result = EnvironmentValidation::patchIBETeamVersion($configPath);

        $this->assertTrue($result);
        $content = file_get_contents($configPath);
        $this->assertStringContainsString('Info::IBE_IMPORT_VERSION', $content);
        $this->assertStringNotContainsString("'6_0'", $content);
    }

    public function testPatchIBETeamVersionDoubleQuotes(): void
    {
        $configPath = $this->tempDir . '/config.php';
        file_put_contents($configPath, '<?php' . "\n" . 'define("IBETEAM_VERSION", "5_9");');

        $result = EnvironmentValidation::patchIBETeamVersion($configPath);

        $this->assertTrue($result);
        $content = file_get_contents($configPath);
        $this->assertStringContainsString('Info::IBE_IMPORT_VERSION', $content);
    }

    public function testPatchIBETeamVersionNonExistentFile(): void
    {
        $result = EnvironmentValidation::patchIBETeamVersion('/tmp/nonexistent_' . uniqid() . '.php');
        $this->assertFalse($result);
    }

    public function testPatchIBETeamVersionNoMatch(): void
    {
        $configPath = $this->tempDir . '/config.php';
        file_put_contents($configPath, "<?php\n// no IBETEAM_VERSION here");

        $result = EnvironmentValidation::patchIBETeamVersion($configPath);
        $this->assertFalse($result);
    }

    // --- validateEnvironmentConsistency ---

    public function testConsistencyProductionValid(): void
    {
        $result = EnvironmentValidation::validateEnvironmentConsistency(
            'production',
            '/var/www/production/app',
            'db_production_user',
            'mongodb://prod-server:27017',
            'prod_db'
        );

        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['violations']);
    }

    public function testConsistencyProductionWithDevPath(): void
    {
        $result = EnvironmentValidation::validateEnvironmentConsistency(
            'production',
            '/var/www/development/app',
            'prod_user'
        );

        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['violations']);
        $this->assertSame('APPLICATION_PATH', $result['violations'][0]['field']);
    }

    public function testConsistencyProductionWithDevDbUser(): void
    {
        $result = EnvironmentValidation::validateEnvironmentConsistency(
            'production',
            '/var/www/prod/app',
            'development_user'
        );

        $this->assertFalse($result['valid']);
        $this->assertSame('DB Username', $result['violations'][0]['field']);
    }

    public function testConsistencyProductionWithDevMongo(): void
    {
        $result = EnvironmentValidation::validateEnvironmentConsistency(
            'production',
            '/var/www/prod/app',
            'prod_user',
            'mongodb://development-server:27017',
            'development_db'
        );

        $this->assertFalse($result['valid']);
        $this->assertCount(2, $result['violations']);
    }

    public function testConsistencyDevelopmentWithProdPath(): void
    {
        $result = EnvironmentValidation::validateEnvironmentConsistency(
            'development',
            '/var/www/production/app'
        );

        $this->assertFalse($result['valid']);
        $this->assertSame('production', $result['forbiddenString']);
    }

    public function testConsistencyDevelopmentValid(): void
    {
        $result = EnvironmentValidation::validateEnvironmentConsistency(
            'development',
            '/var/www/development/app',
            'dev_user'
        );

        $this->assertTrue($result['valid']);
    }

    public function testConsistencyTestingSkipsCheck(): void
    {
        $result = EnvironmentValidation::validateEnvironmentConsistency(
            'testing',
            '/var/www/production_and_development/app'
        );

        $this->assertTrue($result['valid']);
        $this->assertNull($result['forbiddenString']);
    }

    public function testConsistencyNullValuesAreSkipped(): void
    {
        $result = EnvironmentValidation::validateEnvironmentConsistency(
            'production',
            '/var/www/prod/app',
            null,
            null,
            null
        );

        $this->assertTrue($result['valid']);
    }

    public function testConsistencyCaseInsensitive(): void
    {
        $result = EnvironmentValidation::validateEnvironmentConsistency(
            'production',
            '/var/www/prod/app',
            'DEVELOPMENT_admin'
        );

        $this->assertFalse($result['valid']);
    }

    public function testConsistencyMultipleViolations(): void
    {
        $result = EnvironmentValidation::validateEnvironmentConsistency(
            'production',
            '/var/www/development/app',
            'development_user',
            'mongodb://development:27017',
            'development_db'
        );

        $this->assertFalse($result['valid']);
        $this->assertCount(4, $result['violations']);
        $this->assertStringContainsString('4 configuration value(s)', $result['message']);
    }

    // --- validatePHPVersion ---

    public function testValidatePHPVersion(): void
    {
        $result = EnvironmentValidation::validatePHPVersion();

        // PHP 8.1+ is required; the test environment should have it
        $this->assertTrue($result['valid']);
        $this->assertSame(PHP_VERSION, $result['currentVersion']);
        $this->assertSame('8.1.0', $result['requiredVersion']);
    }

    // --- validatePhpCliBinary ---

    public function testValidatePhpCliBinaryWithDefaultPhp(): void
    {
        $result = EnvironmentValidation::validatePhpCliBinary(null);

        $this->assertTrue($result['valid']);
        $this->assertFalse($result['isAbsolute']);
        $this->assertNotNull($result['resolvedPath']);
    }

    public function testValidatePhpCliBinaryWithExplicitPhp(): void
    {
        $result = EnvironmentValidation::validatePhpCliBinary('php');

        $this->assertTrue($result['valid']);
    }

    public function testValidatePhpCliBinaryWithAbsolutePathNotFound(): void
    {
        $result = EnvironmentValidation::validatePhpCliBinary('/usr/nonexistent/path/php');

        $this->assertFalse($result['valid']);
        $this->assertTrue($result['isAbsolute']);
        $this->assertStringContainsString('not found', $result['message']);
    }

    public function testValidatePhpCliBinaryWithRelativeNotInPath(): void
    {
        $result = EnvironmentValidation::validatePhpCliBinary('totally_nonexistent_binary_xyz');

        $this->assertFalse($result['valid']);
        $this->assertFalse($result['isAbsolute']);
        $this->assertStringContainsString('not found in system PATH', $result['message']);
    }

    public function testValidatePhpCliBinaryWithActualPhpPath(): void
    {
        $phpPath = PHP_BINARY;
        if (empty($phpPath) || !file_exists($phpPath)) {
            $this->markTestSkipped('PHP_BINARY not available');
        }

        $result = EnvironmentValidation::validatePhpCliBinary($phpPath);

        $this->assertTrue($result['valid']);
        $this->assertTrue($result['isAbsolute']);
        $this->assertSame($phpPath, $result['resolvedPath']);
    }

    public function testValidatePhpCliBinaryNotExecutable(): void
    {
        $fakeBinary = $this->tempDir . '/fake_php';
        file_put_contents($fakeBinary, 'not a real binary');
        chmod($fakeBinary, 0644);

        $result = EnvironmentValidation::validatePhpCliBinary($fakeBinary);

        $this->assertFalse($result['valid']);
        $this->assertTrue($result['isAbsolute']);
    }
}
