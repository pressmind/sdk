<?php

namespace Pressmind\Backend\Auth;

use Pressmind\Registry;

/**
 * Creates an auth provider from config (backend.auth).
 * Used when no provider is passed to Application (standalone mode).
 */
class Factory
{
    /**
     * Build provider from config array.
     * Config shape: [ 'provider' => string, 'config' => array ]
     *
     * @param array $authConfig backend.auth section
     * @return ProviderInterface
     */
    public static function create(array $authConfig): ProviderInterface
    {
        $provider = $authConfig['provider'] ?? 'password';
        $config = $authConfig['config'] ?? [];

        switch ($provider) {
            case 'password':
                return new ConfigPasswordProvider($config);
            case 'basic_auth':
                return new BasicAuthProvider($config);
            case 'wordpress':
                $capability = $config['capability'] ?? 'edit_pages';
                return new WordPressProvider($capability);
            case 'callback':
                return new CallbackProvider($config);
            default:
                return new ConfigPasswordProvider($config);
        }
    }

    /**
     * Create provider from Registry config (reads backend.auth).
     *
     * @return ProviderInterface
     */
    public static function createFromRegistry(): ProviderInterface
    {
        try {
            $config = Registry::getInstance()->get('config');
        } catch (\Throwable $e) {
            return new ConfigPasswordProvider(['password' => '']);
        }
        $backend = $config['backend'] ?? null;
        if (!is_array($backend) || empty($backend['auth'])) {
            return new ConfigPasswordProvider(['password' => '']);
        }
        return self::create($backend['auth']);
    }
}
