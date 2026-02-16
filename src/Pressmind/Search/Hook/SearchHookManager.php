<?php

namespace Pressmind\Search\Hook;

use Pressmind\Registry;

/**
 * Manager for search hooks
 * 
 * Handles registration and execution of search hooks in priority order.
 * Hooks can be registered:
 * - Programmatically via register()
 * - Via SDK config under data.search_hooks (similar to import hooks pattern)
 * 
 * Config format in pm-config.php:
 * 'data' => [
 *     'search_hooks' => [
 *         [
 *             'class' => '\\Travelshop\\Infx\\InfxSearchProvider',
 *             'config' => [ ... provider-specific config ... ]
 *         ]
 *     ]
 * ]
 */
class SearchHookManager
{
    /**
     * @var SearchHookInterface[]
     */
    private static array $hooks = [];
    
    /**
     * @var bool Whether hooks are sorted by priority
     */
    private static bool $sorted = false;
    
    /**
     * @var bool Whether hooks have been initialized from config
     */
    private static bool $initialized = false;
    
    /**
     * Initialize hooks from SDK config
     * 
     * Reads data.search_hooks from config and instantiates hook classes.
     * Called automatically on first hook execution.
     * 
     * @return void
     */
    public static function initFromConfig(): void
    {
        if (self::$initialized) {
            return;
        }
        
        self::$initialized = true;
        $isDebug = !empty($_GET['debug']) || (defined('PM_SDK_DEBUG') && PM_SDK_DEBUG);
        
        try {
            $registry = Registry::getInstance();
            $config = $registry->get('config');
            
            if (empty($config['data']['search_hooks'])) {
                if ($isDebug) {
                    echo '<pre style="background:#fce4ec;padding:10px;margin:10px 0;border-left:4px solid #e91e63;">SearchHookManager: No search_hooks configured in data.search_hooks</pre>';
                }
                return;
            }
            
            if ($isDebug) {
                echo '<pre style="background:#f3e5f5;padding:10px;margin:10px 0;border-left:4px solid #9c27b0;">SearchHookManager: Loading ' . count($config['data']['search_hooks']) . ' hook(s) from config</pre>';
            }
            
            foreach ($config['data']['search_hooks'] as $hookConfig) {
                if (empty($hookConfig['class'])) {
                    continue;
                }
                
                $className = $hookConfig['class'];
                $providerConfig = $hookConfig['config'] ?? [];
                
                if (!class_exists($className)) {
                    $msg = "SearchHookManager: Class not found: $className";
                    error_log($msg);
                    if ($isDebug) {
                        echo '<pre style="background:#ffebee;padding:10px;margin:10px 0;border-left:4px solid #f44336;">' . $msg . '</pre>';
                    }
                    continue;
                }
                
                $hook = new $className($providerConfig);
                
                if (!$hook instanceof SearchHookInterface) {
                    $msg = "SearchHookManager: Class does not implement SearchHookInterface: $className";
                    error_log($msg);
                    if ($isDebug) {
                        echo '<pre style="background:#ffebee;padding:10px;margin:10px 0;border-left:4px solid #f44336;">' . $msg . '</pre>';
                    }
                    continue;
                }
                
                self::register($hook);
                
                if ($isDebug) {
                    echo '<pre style="background:#e8f5e9;padding:10px;margin:10px 0;border-left:4px solid #4caf50;">SearchHookManager: Registered hook ' . $className . '</pre>';
                }
            }
        } catch (\Exception $e) {
            $msg = "SearchHookManager: Error initializing from config: " . $e->getMessage();
            error_log($msg);
            if ($isDebug) {
                echo '<pre style="background:#ffebee;padding:10px;margin:10px 0;border-left:4px solid #f44336;">' . $msg . '</pre>';
            }
        }
    }
    
    /**
     * Register a search hook
     * 
     * @param SearchHookInterface $hook
     * @return void
     */
    public static function register(SearchHookInterface $hook): void
    {
        self::$hooks[] = $hook;
        self::$sorted = false;
    }
    
    /**
     * Unregister all hooks (useful for testing)
     * 
     * @return void
     */
    public static function clear(): void
    {
        self::$hooks = [];
        self::$sorted = false;
        self::$initialized = false;
    }
    
    /**
     * Get all registered hooks
     * 
     * @return SearchHookInterface[]
     */
    public static function getHooks(): array
    {
        self::initFromConfig();
        return self::$hooks;
    }
    
    /**
     * Sort hooks by priority (lower = earlier)
     * 
     * @return void
     */
    private static function sortHooks(): void
    {
        if (!self::$sorted) {
            usort(self::$hooks, function (SearchHookInterface $a, SearchHookInterface $b) {
                return $a->getPriority() <=> $b->getPriority();
            });
            self::$sorted = true;
        }
    }
    
    /**
     * Execute pre-search hooks
     * 
     * Calls all active hooks in priority order and returns the first non-null result
     * 
     * @param array $conditions Current search conditions
     * @param array $context Search context
     * @return SearchHookResult|null
     */
    public static function executePreSearch(array $conditions, array $context): ?SearchHookResult
    {
        if (!empty($context['skip_search_hooks'])) {
            return null;
        }
        self::initFromConfig();
        self::sortHooks();
        
        $isDebug = !empty($_GET['debug']) || (defined('PM_SDK_DEBUG') && PM_SDK_DEBUG);
        
        if ($isDebug) {
            $hookInfo = [];
            foreach (self::$hooks as $hook) {
                $hookInfo[] = [
                    'class' => get_class($hook),
                    'priority' => $hook->getPriority(),
                    'active' => $hook->isActive($context),
                ];
            }
            echo '<pre style="background:#fff3e0;padding:10px;margin:10px 0;border-left:4px solid #ff9800;">SearchHookManager: ' . count(self::$hooks) . ' hook(s) registered' . "\n" . json_encode($hookInfo, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . '</pre>';
        }
        
        foreach (self::$hooks as $hook) {
            if (!$hook->isActive($context)) {
                continue;
            }
            
            $result = $hook->preSearch($conditions, $context);
            
            if ($result !== null) {
                // Store the hook reference for post-search
                $context['_active_hook'] = $hook;
                return $result;
            }
        }
        
        return null;
    }
    
    /**
     * Execute post-search hooks
     * 
     * Calls all active hooks in priority order to modify the result
     * 
     * @param object $result The search result
     * @param array $context Search context
     * @return object Modified result
     */
    public static function executePostSearch(object $result, array $context): object
    {
        if (!empty($context['skip_search_hooks'])) {
            return $result;
        }
        self::initFromConfig();
        self::sortHooks();
        
        foreach (self::$hooks as $hook) {
            if (!$hook->isActive($context)) {
                continue;
            }
            
            $result = $hook->postSearch($result, $context);
        }
        
        return $result;
    }
    
    /**
     * Check if any hooks are registered and active for the given context
     * 
     * @param array $context Search context
     * @return bool
     */
    public static function hasActiveHooks(array $context): bool
    {
        foreach (self::$hooks as $hook) {
            if ($hook->isActive($context)) {
                return true;
            }
        }
        return false;
    }
}
