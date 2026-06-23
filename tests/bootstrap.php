<?php

/**
 * PHPUnit bootstrap: autoloader and ENV defaults for tests.
 * Do not rely on any project config files (pm-config.php) here.
 */

$autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (!is_file($autoload)) {
    throw new RuntimeException('Composer autoload not found. Run composer install.');
}
require $autoload;

// ENV defaults only if not already set (e.g. by phpunit.xml or CI)
if (getenv('DB_HOST') === false) {
    putenv('DB_HOST=localhost');
}
if (getenv('DB_NAME') === false) {
    putenv('DB_NAME=pressmind_test');
}
if (getenv('DB_USER') === false) {
    putenv('DB_USER=root');
}
if (getenv('DB_PASS') === false) {
    putenv('DB_PASS=');
}
if (getenv('MONGODB_URI') === false) {
    putenv('MONGODB_URI=mongodb://localhost:27017');
}
if (getenv('MONGODB_DB') === false) {
    putenv('MONGODB_DB=pressmind_test');
}
// Ensure OPENSEARCH_URI is available for Integration tests (Docker sets env; persist for test process).
$opensearchUri = getenv('OPENSEARCH_URI');
if ($opensearchUri !== false && $opensearchUri !== '') {
    $_SERVER['OPENSEARCH_URI'] = $opensearchUri;
    @file_put_contents(sys_get_temp_dir() . '/pm_sdk_opensearch_uri.txt', $opensearchUri);
}

// Constants required by HelperFunctions::replaceConstantsFromConfig (e.g. Storage/Filesystem tests).
// Use ImportIntegration _app path so InstallCommand and Import (ObjectTypeScaffolder) find ObjectTypeScaffolderTemplates.
$defaultAppPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR . 'ImportIntegration' . DIRECTORY_SEPARATOR . '_app';
$customClassPaths = [
    $defaultAppPath,
    __DIR__ . DIRECTORY_SEPARATOR . 'Fixtures',
];
$ensureTestDirectory = static function (string $dir): void {
    if (is_dir($dir)) {
        return;
    }
    if (!@mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException('Could not create test directory: ' . $dir);
    }
};

spl_autoload_register(function ($class) use ($customClassPaths) {
    if (str_starts_with($class, 'Custom\\')) {
        foreach ($customClassPaths as $customClassPath) {
            $file = $customClassPath . DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, $class) . '.php';
            if (is_file($file)) {
                require_once $file;
                return true;
            }
        }
    }
    if ($class === 'Pressmind\\AbstractController' && !class_exists($class, false)) {
        $stub = __DIR__ . '/Fixtures/Pressmind/AbstractController.php';
        if (is_file($stub)) {
            require_once $stub;
            return true;
        }
    }
    return false;
});
$ensureTestDirectory($defaultAppPath);
foreach (['Custom/MediaType', 'Custom/Views', 'ObjectTypeScaffolderTemplates', 'logs', 'tmp', 'docs/objecttypes', 'assets/files', 'assets/images'] as $subdir) {
    $dir = $defaultAppPath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $subdir);
    $ensureTestDirectory($dir);
}
if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname($defaultAppPath));
}
if (!defined('APPLICATION_PATH')) {
    define('APPLICATION_PATH', $defaultAppPath);
}
if (!defined('WEBSERVER_DOCUMENT_ROOT')) {
    define('WEBSERVER_DOCUMENT_ROOT', $defaultAppPath);
}
if (!defined('WEBSERVER_HTTP')) {
    define('WEBSERVER_HTTP', 'http://localhost');
}
