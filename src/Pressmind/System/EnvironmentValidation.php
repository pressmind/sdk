<?php

namespace Pressmind\System;

/**
 * Validation methods for SDK installation.
 * Checks environment and configuration for compatibility.
 */
class EnvironmentValidation
{
    /**
     * Correct reference to IBE_IMPORT_VERSION constant.
     */
    private const CORRECT_VERSION_REFERENCE = '\\Pressmind\\System\\Info::IBE_IMPORT_VERSION';

    /**
     * Alternative valid references (with use statement or shorter notation).
     */
    private const VALID_VERSION_PATTERNS = [
        'Info::IBE_IMPORT_VERSION',
        '\\Pressmind\\System\\Info::IBE_IMPORT_VERSION',
        '\Pressmind\System\Info::IBE_IMPORT_VERSION',
    ];

    /**
     * Searches for a config file in a Custom directory that defines IBETEAM_VERSION.
     *
     * @param string $customPath Path to Custom directory
     * @return string|null Path to config file or null if not found
     */
    public static function findIBETeamConfigFile(string $customPath): ?string
    {
        if (!is_dir($customPath)) {
            return null;
        }

        $files = glob($customPath . '/*config*.php');
        
        if (empty($files)) {
            return null;
        }

        foreach ($files as $file) {
            $content = file_get_contents($file);
            if ($content !== false && preg_match('/define\s*\(\s*[\'"]IBETEAM_VERSION[\'"]\s*,/', $content)) {
                return $file;
            }
        }

        return null;
    }

    /**
     * Validates if IBETEAM_VERSION is correctly defined with Info::IBE_IMPORT_VERSION.
     * 
     * WRONG: define('IBETEAM_VERSION', '6_0');
     * CORRECT: define('IBETEAM_VERSION', Info::IBE_IMPORT_VERSION);
     *
     * @param string $customPath Path to Custom directory
     * @return array ValidationResult with valid, message, configPath, currentDefinition
     */
    public static function validateIBETeamVersion(string $customPath): array
    {
        $configPath = self::findIBETeamConfigFile($customPath);
        
        if ($configPath === null) {
            return [
                'valid' => false,
                'message' => 'No IBE-Team config file found in: ' . $customPath,
                'configPath' => null,
                'currentDefinition' => null,
                'usesConstantReference' => false
            ];
        }

        $content = file_get_contents($configPath);
        
        // Check if IBETEAM_VERSION is defined with a string literal (WRONG)
        if (preg_match('/define\s*\(\s*[\'"]IBETEAM_VERSION[\'"]\s*,\s*[\'"]([^\'"]+)[\'"]\s*\)/', $content, $matches)) {
            return [
                'valid' => false,
                'message' => sprintf(
                    'IBETEAM_VERSION uses hardcoded value "%s" instead of Info::IBE_IMPORT_VERSION',
                    $matches[1]
                ),
                'configPath' => $configPath,
                'currentDefinition' => $matches[0],
                'usesConstantReference' => false
            ];
        }

        // Check if IBETEAM_VERSION is defined with Info::IBE_IMPORT_VERSION (CORRECT)
        if (preg_match('/define\s*\(\s*[\'"]IBETEAM_VERSION[\'"]\s*,\s*([^)]+)\)/', $content, $matches)) {
            $definition = trim($matches[1]);
            
            // Check if it's one of the valid references
            foreach (self::VALID_VERSION_PATTERNS as $validPattern) {
                if (str_contains($definition, 'Info::IBE_IMPORT_VERSION')) {
                    return [
                        'valid' => true,
                        'message' => 'IBETEAM_VERSION correctly uses Info::IBE_IMPORT_VERSION',
                        'configPath' => $configPath,
                        'currentDefinition' => $matches[0],
                        'usesConstantReference' => true
                    ];
                }
            }

            // Other definition (neither string nor Info reference)
            return [
                'valid' => false,
                'message' => sprintf(
                    'IBETEAM_VERSION has unknown definition: %s',
                    $definition
                ),
                'configPath' => $configPath,
                'currentDefinition' => $matches[0],
                'usesConstantReference' => false
            ];
        }

        return [
            'valid' => false,
            'message' => 'IBETEAM_VERSION constant not found in: ' . $configPath,
            'configPath' => $configPath,
            'currentDefinition' => null,
            'usesConstantReference' => false
        ];
    }

    /**
     * Patches IBETEAM_VERSION in config file to use Info::IBE_IMPORT_VERSION.
     * 
     * Replaces: define('IBETEAM_VERSION', '6_0');
     * With:     define('IBETEAM_VERSION', \Pressmind\System\Info::IBE_IMPORT_VERSION);
     *
     * @param string $configFilePath Path to config file
     * @return bool True on success, false on error
     */
    public static function patchIBETeamVersion(string $configFilePath): bool
    {
        if (!file_exists($configFilePath) || !is_writable($configFilePath)) {
            return false;
        }

        $content = file_get_contents($configFilePath);
        
        if ($content === false) {
            return false;
        }

        // Replace hardcoded string value with constant reference
        $pattern = '/define\s*\(\s*([\'"])IBETEAM_VERSION\1\s*,\s*[\'"][^\'"]+[\'"]\s*\)/';
        $replacement = "define('IBETEAM_VERSION', " . self::CORRECT_VERSION_REFERENCE . ")";

        $newContent = preg_replace($pattern, $replacement, $content, 1, $count);

        if ($count === 0 || $newContent === null) {
            return false;
        }

        return file_put_contents($configFilePath, $newContent) !== false;
    }

    /**
     * Checks if environment configuration is consistent.
     * 
     * If ENV=production: No values may contain "development"
     * If ENV=development: No values may contain "production"
     *
     * @param string $env Current environment (development, production, testing)
     * @param string $applicationPath The APPLICATION_PATH
     * @param string|null $dbUsername Database username
     * @param string|null $mongoConnectionString MongoDB connection string
     * @param string|null $mongoDbName MongoDB database name
     * @return array ValidationResult with valid, message, violations
     */
    public static function validateEnvironmentConsistency(
        string $env,
        string $applicationPath,
        ?string $dbUsername = null,
        ?string $mongoConnectionString = null,
        ?string $mongoDbName = null
    ): array {
        $violations = [];
        $forbiddenString = null;

        // Determine which string is forbidden
        if ($env === 'production') {
            $forbiddenString = 'development';
        } elseif ($env === 'development') {
            $forbiddenString = 'production';
        }

        // No check needed (e.g. testing environment)
        if ($forbiddenString === null) {
            return [
                'valid' => true,
                'message' => sprintf('Environment "%s" does not require consistency check.', $env),
                'env' => $env,
                'forbiddenString' => null,
                'violations' => []
            ];
        }

        // Check APPLICATION_PATH
        if (stripos($applicationPath, $forbiddenString) !== false) {
            $violations[] = [
                'field' => 'APPLICATION_PATH',
                'value' => $applicationPath,
                'reason' => sprintf('contains "%s"', $forbiddenString)
            ];
        }

        // Check DB Username
        if ($dbUsername !== null && stripos($dbUsername, $forbiddenString) !== false) {
            $violations[] = [
                'field' => 'DB Username',
                'value' => $dbUsername,
                'reason' => sprintf('contains "%s"', $forbiddenString)
            ];
        }

        // Check MongoDB Connection String
        if ($mongoConnectionString !== null && stripos($mongoConnectionString, $forbiddenString) !== false) {
            $violations[] = [
                'field' => 'MongoDB Connection String',
                'value' => $mongoConnectionString,
                'reason' => sprintf('contains "%s"', $forbiddenString)
            ];
        }

        // Check MongoDB Database Name
        if ($mongoDbName !== null && stripos($mongoDbName, $forbiddenString) !== false) {
            $violations[] = [
                'field' => 'MongoDB Database',
                'value' => $mongoDbName,
                'reason' => sprintf('contains "%s"', $forbiddenString)
            ];
        }

        if (empty($violations)) {
            return [
                'valid' => true,
                'message' => sprintf('Environment configuration for "%s" is consistent.', $env),
                'env' => $env,
                'forbiddenString' => $forbiddenString,
                'violations' => []
            ];
        }

        return [
            'valid' => false,
            'message' => sprintf(
                'CRITICAL: Environment is "%s", but %d configuration value(s) contain "%s"!',
                $env,
                count($violations),
                $forbiddenString
            ),
            'env' => $env,
            'forbiddenString' => $forbiddenString,
            'violations' => $violations
        ];
    }

    /**
     * Checks if PHP version is at least 8.1.
     *
     * @return array ValidationResult with valid, message, currentVersion, requiredVersion
     */
    public static function validatePHPVersion(): array
    {
        $requiredVersion = '8.1.0';
        $currentVersion = PHP_VERSION;

        if (version_compare($currentVersion, $requiredVersion, '>=')) {
            return [
                'valid' => true,
                'message' => sprintf('PHP version %s is compatible.', $currentVersion),
                'currentVersion' => $currentVersion,
                'requiredVersion' => $requiredVersion
            ];
        }

        return [
            'valid' => false,
            'message' => sprintf(
                'PHP version not compatible! Current: %s, Required minimum: %s',
                $currentVersion,
                $requiredVersion
            ),
            'currentVersion' => $currentVersion,
            'requiredVersion' => $requiredVersion
        ];
    }

    /**
     * Validates the PHP CLI binary path from configuration.
     * 
     * Checks if the configured php_cli_binary path is valid and executable.
     * Supports both absolute paths (e.g., /usr/bin/php) and relative paths (e.g., php).
     *
     * @param string|null $configuredPath The configured php_cli_binary path
     * @return array ValidationResult with valid, message, configuredPath, resolvedPath, isAbsolute
     */
    public static function validatePhpCliBinary(?string $configuredPath): array
    {
        // Use default 'php' if not configured
        $pathToCheck = !empty($configuredPath) ? $configuredPath : 'php';
        $isAbsolute = self::isAbsolutePath($pathToCheck);
        
        if ($isAbsolute) {
            // Absolute path: check if file exists and is executable
            if (!file_exists($pathToCheck)) {
                return [
                    'valid' => false,
                    'message' => sprintf(
                        'PHP CLI binary not found at configured path "%s". ' .
                        'Common paths are /usr/bin/php, /usr/local/bin/php, or /opt/homebrew/bin/php. ' .
                        'Check your pm-config.php setting for server.php_cli_binary.',
                        $pathToCheck
                    ),
                    'configuredPath' => $configuredPath,
                    'resolvedPath' => null,
                    'isAbsolute' => true
                ];
            }
            
            if (!is_executable($pathToCheck)) {
                return [
                    'valid' => false,
                    'message' => sprintf(
                        'PHP CLI binary at "%s" exists but is not executable. ' .
                        'Check file permissions.',
                        $pathToCheck
                    ),
                    'configuredPath' => $configuredPath,
                    'resolvedPath' => $pathToCheck,
                    'isAbsolute' => true
                ];
            }
            
            // Verify it's actually PHP
            $verifyResult = self::verifyPhpBinary($pathToCheck);
            if (!$verifyResult['valid']) {
                return [
                    'valid' => false,
                    'message' => $verifyResult['message'],
                    'configuredPath' => $configuredPath,
                    'resolvedPath' => $pathToCheck,
                    'isAbsolute' => true
                ];
            }
            
            return [
                'valid' => true,
                'message' => sprintf('PHP CLI binary at "%s" is valid.', $pathToCheck),
                'configuredPath' => $configuredPath,
                'resolvedPath' => $pathToCheck,
                'isAbsolute' => true
            ];
        }
        
        // Relative path: try to resolve via PATH
        $resolvedPath = self::resolveExecutableInPath($pathToCheck);
        
        if ($resolvedPath === null) {
            return [
                'valid' => false,
                'message' => sprintf(
                    'PHP CLI binary "%s" not found in system PATH. ' .
                    'Either add PHP to your PATH or configure an absolute path ' .
                    '(e.g., /usr/bin/php) in pm-config.php setting server.php_cli_binary.',
                    $pathToCheck
                ),
                'configuredPath' => $configuredPath,
                'resolvedPath' => null,
                'isAbsolute' => false
            ];
        }
        
        // Verify it's actually PHP
        $verifyResult = self::verifyPhpBinary($resolvedPath);
        if (!$verifyResult['valid']) {
            return [
                'valid' => false,
                'message' => $verifyResult['message'],
                'configuredPath' => $configuredPath,
                'resolvedPath' => $resolvedPath,
                'isAbsolute' => false
            ];
        }
        
        return [
            'valid' => true,
            'message' => sprintf(
                'PHP CLI binary "%s" resolved to "%s" and is valid.',
                $pathToCheck,
                $resolvedPath
            ),
            'configuredPath' => $configuredPath,
            'resolvedPath' => $resolvedPath,
            'isAbsolute' => false
        ];
    }

    /**
     * Checks if a path is absolute.
     *
     * @param string $path The path to check
     * @return bool True if absolute, false if relative
     */
    private static function isAbsolutePath(string $path): bool
    {
        // Unix absolute path
        if (str_starts_with($path, '/')) {
            return true;
        }
        // Windows absolute path (e.g., C:\php\php.exe)
        if (preg_match('/^[A-Za-z]:[\\\\\/]/', $path)) {
            return true;
        }
        return false;
    }

    /**
     * Resolves an executable name to its full path using the system PATH.
     *
     * @param string $binary The binary name to resolve (e.g., 'php')
     * @return string|null The full path or null if not found
     */
    private static function resolveExecutableInPath(string $binary): ?string
    {
        // Use 'which' on Unix/Mac, 'where' on Windows
        $command = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' 
            ? 'where ' . escapeshellarg($binary) . ' 2>NUL'
            : 'which ' . escapeshellarg($binary) . ' 2>/dev/null';
        
        $output = [];
        $returnCode = 0;
        exec($command, $output, $returnCode);
        
        if ($returnCode === 0 && !empty($output[0])) {
            return trim($output[0]);
        }
        
        return null;
    }

    /**
     * Verifies that a binary is actually PHP by running it with -v flag.
     *
     * @param string $binaryPath Full path to the binary
     * @return array Result with valid and message keys
     */
    private static function verifyPhpBinary(string $binaryPath): array
    {
        $output = [];
        $returnCode = 0;
        $command = escapeshellarg($binaryPath) . ' -v 2>&1';
        exec($command, $output, $returnCode);
        
        if ($returnCode !== 0) {
            return [
                'valid' => false,
                'message' => sprintf(
                    'Binary at "%s" failed to execute. Return code: %d',
                    $binaryPath,
                    $returnCode
                )
            ];
        }
        
        $outputString = implode("\n", $output);
        if (stripos($outputString, 'PHP') === false) {
            return [
                'valid' => false,
                'message' => sprintf(
                    'Binary at "%s" does not appear to be PHP. Output: %s',
                    $binaryPath,
                    substr($outputString, 0, 100)
                )
            ];
        }
        
        return [
            'valid' => true,
            'message' => 'Binary verified as PHP.'
        ];
    }
}
