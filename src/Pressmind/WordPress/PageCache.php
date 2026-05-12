<?php

namespace Pressmind\WordPress;

/**
 * WordPress full-page cache backed by Redis (drop-in via advanced-cache.php).
 *
 * This class is WordPress-specific integration code, not part of the core PIM SDK.
 * Requires wp-config constants: PM_REDIS_HOST, PM_REDIS_PORT, PM_REDIS_TTL,
 * PM_REDIS_BACKGROUND_KEY_REFRESH, PM_REDIS_DEBUG, PM_REDIS_GZIP, PM_REDIS_KEY_PREFIX,
 * PM_REDIS_ACTIVATE. Optional: PM_REDIS_BLACKLIST_URLS (array of URI path substrings to never cache).
 */
class PageCache
{
    private static $redis;

    private static $request_hash = '';

    private static $current_url = '';

    private static $site_url = '';

    private static $page_url = '';

    /**
     * Query parameters that do not change rendered output (tracking, ads).
     *
     * @see https://github.com/mpchadwick/tracking-query-params-registry/blob/master/_data/params.csv
     */
    private static $blacklist_params = [
        'ScCid',
        '_branch_match_id',
        '_bta_c',
        '_bta_tid',
        '_ga',
        '_gl',
        '_ke',
        '_kx',
        'awc',
        'belboon',
        'campid',
        'cleverPushNotificationId',
        'dclid',
        'dm_i',
        'ef_id',
        'epik',
        'fbcid',
        'fbcmlid',
        'fbclid',
        'fbcmid',
        'fbid',
        'fbmid',
        'gad_source',
        'gbraid',
        'gclid',
        'gclsrc',
        'gdfms',
        'gdftrk',
        'gdffi',
        'gdpr_consent',
        'hsa_acc',
        'hsa_ad',
        'hsa_cam',
        'hsa_grp',
        'hsa_kw',
        'hsa_mt',
        'hsa_net',
        'hsa_src',
        'hsa_tgt',
        'hsa_ver',
        'iclid',
        'matomo_campaign',
        'matomo_cid',
        'matomo_content',
        'matomo_group',
        'matomo_keyword',
        'matomo_medium',
        'matomo_placement',
        'matomo_source',
        'mc_cid',
        'mc_eid',
        'mkwid',
        'mlmsg',
        'mlnc',
        'mlnch',
        'mlnm',
        'mlnt',
        'mltest',
        'msclkid',
        'mtm_campaign',
        'mtm_cid',
        'mtm_content',
        'mtm_group',
        'mtm_keyword',
        'mtm_medium',
        'mtm_placement',
        'mtm_source',
        'pcrid',
        'piwik_campaign',
        'piwik_keyword',
        'piwik_kwd',
        'pk_campaign',
        'pk_keyword',
        'pk_kwd',
        'redirect_log_mongo_id',
        'redirect_mongo_id',
        's_kwcid',
        'sb_referer_host',
        'trk_contact',
        'trk_msg',
        'trk_module',
        'trk_sid',
        'utm_',
        'wbraid',
    ];

    private static $clean_get = [];

    public static function init()
    {
        self::$redis = new \Redis();
        self::$redis->connect(PM_REDIS_HOST, PM_REDIS_PORT);
    }

    public static function cache_init()
    {
        self::init();
        self::cleanup_get();
        self::parse_request_uri();
        if (self::do_not_cache() === true) {
            header('X-PM-Cache-Status: do not cache');
            return;
        }
        if (self::$redis === false) {
            header('X-PM-Cache-Status: no server');
            return;
        }
        self::$request_hash = self::create_request_hash();

        if (PM_REDIS_DEBUG === true) {
            header('X-PM-Cache-Key: ' . self::$request_hash);
        }

        $is_refresh = isset($_GET['cache-refresh']);

        $cache = self::$redis->get(self::$request_hash);
        $cache = !empty($cache) ? unserialize($cache, ['allowed_classes' => false]) : null;

        if (is_array($cache) && empty($cache) === false && !$is_refresh) {

            if (PM_REDIS_DEBUG === true) {
                header('X-PM-Cache-Time: ' . $cache['updated']);
            }

            $expired = ($cache['updated'] + PM_REDIS_BACKGROUND_KEY_REFRESH) < time();

            if ($expired) {
                header('X-PM-Cache-Expired: true');
                $url = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] . (empty($_GET) ? '?' : '&') . 'cache-refresh=1';
                self::prime($url);
            }

            if ($cache['gzip'] === true) {
                if (PM_REDIS_DEBUG === true) {
                    header('X-PM-Cache-Gzip: true');
                }
                $cache['output'] = gzuncompress($cache['output']);
            }

            header('X-PM-Cache-Status: hit');

            if (PM_REDIS_DEBUG === true) {
                header('X-PM-Cache-Expires: ' . (PM_REDIS_BACKGROUND_KEY_REFRESH + $cache['updated'] - time()));
            }

            if (!empty($cache['status'])) {
                http_response_code($cache['status']);
            }

            if (is_array($cache['headers']) && !empty($cache['headers'])) {
                foreach ($cache['headers'] as $header) {
                    header($header);
                }
            }
            echo $cache['output'];
            exit;
        }
        header('X-PM-Cache-Status: ' . ($is_refresh ? 'refresh' : 'miss'));
        ob_start([__CLASS__, 'output_buffer']);
    }

    public static function cleanup_get()
    {
        self::$clean_get = $_GET;
        unset(self::$clean_get['cache-refresh']);
        ksort(self::$clean_get);
        foreach (self::$blacklist_params as $p) {
            foreach ($_GET as $k => $v) {
                if ($k == $p || strpos($k, $p) !== false) {
                    unset(self::$clean_get[$k]);
                }
            }
        }
    }

    /**
     * @return string
     */
    public static function create_request_hash()
    {
        unset($_SERVER['HTTP_IF_NONE_MATCH']);
        unset($_SERVER['HTTP_IF_MODIFIED_SINCE']);
        $request_hash = [
            'request' => self::$current_url,
            'host' => !empty($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '',
            'https' => !empty($_SERVER['HTTPS']) ? $_SERVER['HTTPS'] : '',
            'method' => $_SERVER['REQUEST_METHOD'],
            'get' => self::$clean_get,
        ];
        if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
            $request_hash['http-auth'] = $_SERVER['HTTP_AUTHORIZATION'];
        }
        return self::get_key_path_from_url(self::$page_url) . ':' . md5(serialize($request_hash));
    }

    /**
     * @param string $url
     * @return string
     */
    public static function get_key_path_from_url($url)
    {
        $path = str_replace(['https://', 'http://', '/'], ['', '', ':'], trim($url, '/'));
        return PM_REDIS_KEY_PREFIX . ':' . $path;
    }

    /**
     * @return int
     */
    public static function ttl()
    {
        if (is_array(PM_REDIS_TTL)) {
            foreach (PM_REDIS_TTL as $route => $ttl) {
                if (strpos(self::$current_url, $route) === 0) {
                    return $ttl;
                }
            }
            return 0;
        }
        return PM_REDIS_TTL;
    }

    /**
     * @return array<int, string>
     */
    private static function get_blacklist_urls()
    {
        if (defined('PM_REDIS_BLACKLIST_URLS') && is_array(PM_REDIS_BLACKLIST_URLS)) {
            return PM_REDIS_BLACKLIST_URLS;
        }
        return [];
    }

    public static function do_not_cache()
    {
        $doing_ajax = defined('DOING_AJAX') && DOING_AJAX;
        $xmlrpc_request = defined('XMLRPC_REQUEST') && XMLRPC_REQUEST;
        $rest_request = defined('REST_REQUEST') && REST_REQUEST;
        $no_cache_c = defined('DONOTCACHE') && DONOTCACHE;
        $no_cache_b = defined('DONOTCACHEPAGE') && DONOTCACHEPAGE;
        $robots_request = strpos($_SERVER['REQUEST_URI'], 'robots.txt') != false;
        $wp_admin = strpos($_SERVER['REQUEST_URI'], 'wp-admin') != false;
        $is_post = (strtolower($_SERVER['REQUEST_METHOD']) == 'post') ? true : false;
        $no_cache_g = isset($_GET['no-cache']);
        $no_cache_g_ = isset($_GET['no_cache']);
        $no_cache_p = isset($_GET['preview']);
        $is_logged_in = ((function_exists('is_user_logged_in') && is_user_logged_in()));
        $no_ttl = (self::ttl() < 1) ? true : false;
        $no_bgcrf = (PM_REDIS_BACKGROUND_KEY_REFRESH < 1) ? true : false;
        $wp_login_cookie = false;
        foreach ($_COOKIE as $key => $cookie) {
            if (strpos($key, 'wordpress_logged_in') !== false) {
                $wp_login_cookie = true;
            }
        }
        $ib3_session_cookie = isset($_COOKIE['_ib3_sid']);

        $blacklisted_url = false;
        foreach (self::get_blacklist_urls() as $path_fragment) {
            if ($path_fragment !== '' && strpos($_SERVER['REQUEST_URI'], $path_fragment) !== false) {
                $blacklisted_url = true;
                break;
            }
        }

        $result = ($no_bgcrf || $is_post || $no_ttl || $doing_ajax || $xmlrpc_request || $rest_request || $robots_request || $wp_admin ||
            $no_cache_c || $no_cache_b || $no_cache_g || $is_logged_in || $wp_login_cookie || $ib3_session_cookie || $no_cache_g_ || $no_cache_p || $blacklisted_url);
        return $result;
    }

    /**
     * Take a request uri and remove ignored request keys.
     */
    private static function parse_request_uri()
    {
        $query = http_build_query(self::$clean_get);
        $parsed = parse_url($_SERVER['REQUEST_URI']);
        self::$site_url = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
        self::$page_url = self::$site_url;
        self::$page_url .= !empty($parsed['path']) ? $parsed['path'] : '';
        self::$current_url = self::$page_url;
        if (!empty($query)) {
            self::$current_url .= '?' . $query;
        }
        return true;
    }

    /**
     * Runs when the output buffer stops.
     *
     * @param string $output
     * @return string
     */
    public static function output_buffer($output)
    {
        if (self::$redis === false) {
            return $output;
        }
        if (self::do_not_cache() === true) {
            self::$redis->del(self::$request_hash);
            return $output;
        }
        $log = [];
        $cache = true;
        $data = [
            'url' => self::$current_url,
            'output' => $output,
            'headers' => [],
            'status' => http_response_code(),
            'gzip' => false,
        ];
        if ($data['status'] != 200) {
            $log[] = 'error: response code is: ' . $data['status'] . "\r\n";
            $cache = false;
        }
        if (PM_REDIS_GZIP && function_exists('gzcompress')) {
            $data['output'] = gzcompress($data['output']);
            $data['gzip'] = true;
        }
        foreach (headers_list() as $header) {
            list($key, $value) = explode(':', $header, 2);
            $value = trim($value);
            if (strtolower($key) == 'set-cookie') {
                continue;
            }
            if (strpos(strtolower($key), strtolower('X-PM-Cache')) !== false) {
                continue;
            }
            $data['headers'][] = $header;
        }
        $data['updated'] = time();
        if ($cache === true) {
            self::$redis->set(self::$request_hash, serialize($data), self::ttl());
            if (PM_REDIS_DEBUG === true) {
                $str = '<!-- pm-cache: request_hash: ' . self::$request_hash . ' -->';
                $output = str_replace('</html>', "\r\n" . $str . "\r\n</html>", $output);
            }
        } else {
            if (PM_REDIS_DEBUG === true) {
                $str = '<!-- pm-cache: error can not cache -->';
                $str .= implode("\r\n", $log);
                $output = str_replace('</html>', "\r\n" . $str . "\r\n</html>", $output);
            }
            self::$redis->del(self::$request_hash);
        }
        return $output;
    }

    /**
     * @param string $pattern
     * @return array<int, string>
     */
    public static function get_by_pattern($pattern)
    {
        if (self::$redis === null) {
            self::init();
        }
        if (self::$redis === false) {
            return [];
        }
        $iterator = null;
        $keys = [];
        self::$redis->setOption(\Redis::OPT_SCAN, \Redis::SCAN_RETRY);
        // Do not use truthiness on the scan result: phpredis may return an empty
        // array for a chunk while the cursor is not finished (see phpredis scan examples).
        do {
            $scanned_keys = self::$redis->scan($iterator, $pattern);
            if ($scanned_keys === false) {
                break;
            }
            foreach ($scanned_keys as $str_key) {
                $keys[] = $str_key;
            }
        } while ($iterator != 0);
        return $keys;
    }

    /**
     * @param string $redis_pattern
     * @param int|null $level
     * @return int
     */
    public static function del_by_pattern($redis_pattern, $level = null)
    {
        $keys = self::get_by_pattern($redis_pattern);
        $c = 0;
        foreach ($keys as $key) {
            $path = explode(':', $key);
            $depth = count($path);
            if ($level == null || $depth == $level) {
                self::$redis->del($key);
                $c++;
            }
        }
        return $c;
    }

    /** Max concurrent curl requests when priming by id_media_object */
    const PRIME_CONCURRENCY_LIMIT = 10;

    /**
     * Invalidate and re-prime cache for all media objects that actually changed during import.
     * Resolves affected primary objects transitively via ObjectLink chains.
     *
     * @param \Pressmind\Import $importer
     * @param bool $silent
     * @return array{deleted: int, primed: int}
     */
    public static function invalidate_changed(\Pressmind\Import $importer, bool $silent = true): array
    {
        $changedIds = $importer->getChangedIds();
        if (empty($changedIds)) {
            return ['deleted' => 0, 'primed' => 0];
        }

        $config = \Pressmind\Registry::getInstance()->get('config');
        $primaryTypeIds = $config['data']['primary_media_type_ids'] ?? [];

        $changedPrimaryIds = [];
        if (!empty($primaryTypeIds)) {
            $db = \Pressmind\Registry::getInstance()->get('db');
            if (method_exists($db, 'ensureConnected')) {
                $db->ensureConnected();
            }
            $placeholders = implode(',', array_map('intval', $changedIds));
            $rows = $db->fetchAll(
                'SELECT id FROM pmt2core_media_objects WHERE id IN (' . $placeholders . ') AND id_object_type IN (' . implode(',', array_map('intval', $primaryTypeIds)) . ')'
            );
            foreach ($rows as $row) {
                $changedPrimaryIds[] = (int) $row->id;
            }
        }

        $affectedPrimaryIds = $importer->getAffectedPrimaryIds();
        $primingIds = array_values(array_unique(array_merge($changedPrimaryIds, $affectedPrimaryIds)));

        if (empty($primingIds)) {
            return ['deleted' => 0, 'primed' => 0];
        }

        if (!$silent) {
            echo "Cache invalidation: " . count($changedIds) . " changed, " . count($primingIds) . " primary to prime\n";
        }

        $deleted = self::del_by_id_media_object($primingIds);
        $primed = self::prime_by_id_media_object($primingIds, true, $silent);

        return ['deleted' => $deleted, 'primed' => $primed];
    }

    /**
     * Load full URLs and id_object_type for given media object IDs from pmt2core_routes.
     * Uses a single SQL query instead of instantiating MediaObject per ID.
     *
     * @param int[] $ids
     * @param bool $primaryOnly
     * @return \stdClass[]
     */
    private static function get_urls_by_media_object_ids($ids, $primaryOnly = false)
    {
        if (empty($ids)) {
            return [];
        }
        $ids = array_map('intval', $ids);
        $registry = \Pressmind\Registry::getInstance();
        $db = $registry->get('db');
        if (method_exists($db, 'ensureConnected')) {
            $db->ensureConnected();
        }
        $placeholders = implode(',', $ids);
        $config = $registry->get('config');
        $defaultLanguage = $config['data']['languages']['default'] ?? 'de';
        $siteUrl = defined('SITE_URL') ? SITE_URL : '';
        $sql = "SELECT r.id_media_object, r.route, r.id_object_type FROM pmt2core_routes r WHERE r.id_media_object IN ($placeholders) AND r.language = ?";
        $rows = $db->fetchAll($sql, [$defaultLanguage]);
        $primaryIds = $primaryOnly && !empty($config['data']['primary_media_type_ids'])
            ? $config['data']['primary_media_type_ids']
            : null;
        $result = [];
        foreach ($rows as $row) {
            if ($primaryIds !== null && !in_array((int) $row->id_object_type, $primaryIds, true)) {
                continue;
            }
            $url = $siteUrl . $row->route;
            $result[] = (object) [
                'id_media_object' => (int) $row->id_media_object,
                'url' => $url,
                'id_object_type' => (int) $row->id_object_type,
            ];
        }
        return $result;
    }

    /**
     * @param array<int|string>|string $ids
     * @param bool $prime
     * @return int
     */
    public static function del_by_id_media_object($ids, $prime = false)
    {
        if (is_string($ids)) {
            $ids = [$ids];
        }
        if (is_null(self::$redis)) {
            self::init();
        }
        $items = self::get_urls_by_media_object_ids($ids, false);
        $primaryIds = $prime ? (\Pressmind\Registry::getInstance()->get('config')['data']['primary_media_type_ids'] ?? []) : [];
        $c = 0;
        foreach ($items as $item) {
            $key = self::get_key_path_from_url($item->url) . ':*';
            $c += self::del_by_pattern($key);
            if ($prime && in_array($item->id_object_type, $primaryIds, true)) {
                self::prime($item->url);
            }
        }
        return $c;
    }

    /**
     * @param array<int|string>|string $ids
     * @param bool $background
     * @param bool $silent
     * @param int $delay_ms
     * @return int
     */
    public static function prime_by_id_media_object($ids, $background = true, $silent = true, $delay_ms = 200)
    {
        if (is_string($ids)) {
            $ids = [$ids];
        }
        if (is_null(self::$redis)) {
            self::init();
        }
        $items = self::get_urls_by_media_object_ids($ids, true);
        $urls = [];
        foreach ($items as $item) {
            if (!$silent) {
                echo "Priming: " . $item->url . "\n";
            }
            $urls[] = $item->url;
        }
        return self::prime_urls($urls, self::PRIME_CONCURRENCY_LIMIT);
    }

    /**
     * Single-URL prime via exec (used for inline background refresh of one page).
     *
     * @param string $url
     * @param bool $background
     */
    public static function prime($url, $background = true)
    {
        $safe_url = escapeshellarg($url);
        if ($background) {
            $cmd = 'nohup curl ' . $safe_url . ' </dev/null >/dev/null 2>&1 &';
        } else {
            $cmd = 'curl -s ' . $safe_url . ' > /dev/null';
        }
        exec($cmd, $output);
    }

    /**
     * Batch-prime multiple URLs using curl_multi with a sliding-window concurrency limit.
     *
     * @param string[] $urls
     * @param int $concurrency Max parallel requests
     * @param int $timeout Timeout per request in seconds
     * @return int Number of successfully primed URLs
     */
    private static function prime_urls(array $urls, int $concurrency = 10, int $timeout = 10): int
    {
        if (empty($urls)) {
            return 0;
        }
        $mh = curl_multi_init();
        $active = [];
        $queue = $urls;
        $done = 0;

        $addHandle = function () use ($mh, &$queue, &$active, $timeout) {
            if (empty($queue)) {
                return;
            }
            $url = array_shift($queue);
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_NOBODY, false);
            curl_multi_add_handle($mh, $ch);
            $active[(int) $ch] = $ch;
        };

        while (count($active) < $concurrency && !empty($queue)) {
            $addHandle();
        }

        do {
            curl_multi_exec($mh, $running);
            while ($info = curl_multi_info_read($mh)) {
                $ch = $info['handle'];
                curl_multi_remove_handle($mh, $ch);
                unset($active[(int) $ch]);
                curl_close($ch);
                $done++;
                if (count($active) < $concurrency && !empty($queue)) {
                    $addHandle();
                }
            }
            if ($running > 0) {
                curl_multi_select($mh, 0.1);
            }
        } while ($running > 0 || !empty($active));

        curl_multi_close($mh);
        return $done;
    }
}

if (!class_exists('RedisPageCache', false)) {
    class_alias(PageCache::class, 'RedisPageCache');
}
