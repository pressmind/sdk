<?php

namespace Pressmind\System;

use Pressmind\Registry;

/**
 * Diagnostic class for orphaned products.
 * 
 * Finds Media Objects that are visible (visibility=30) but have no entries
 * in pmt2core_cheapest_price_speed and therefore do not appear in search results.
 */
class TouristicOrphans
{
    /**
     * @var \Pressmind\DB\Adapter\Pdo
     */
    private $db;

    /**
     * @var array
     */
    private $config;

    public function __construct()
    {
        $this->db = Registry::getInstance()->get('db');
        $this->config = Registry::getInstance()->get('config');
    }

    /**
     * Finds all orphans for the specified object types.
     * 
     * Orphans are Media Objects that:
     * - have visibility = $visibility (default: 30 = public)
     * - have one of the specified object types
     * - have NO entries in pmt2core_cheapest_price_speed
     *
     * @param array $objectTypeIds Array of object type IDs (e.g. [1212, 1214])
     * @param int $visibility Visibility value (default: 30)
     * @return array Array of orphan objects with details
     */
    public function findOrphans(array $objectTypeIds, int $visibility = 30): array
    {
        if (empty($objectTypeIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($objectTypeIds), '?'));
        
        $sql = "
            SELECT 
                mo.id,
                mo.name,
                mo.code,
                mo.id_object_type,
                mo.visibility,
                (SELECT COUNT(*) 
                 FROM pmt2core_touristic_booking_packages bp 
                 WHERE bp.id_media_object = mo.id) as booking_packages_count,
                (SELECT COUNT(*) 
                 FROM pmt2core_touristic_dates d 
                 JOIN pmt2core_touristic_booking_packages bp ON d.id_booking_package = bp.id 
                 WHERE bp.id_media_object = mo.id) as dates_count,
                (SELECT COUNT(*) 
                 FROM pmt2core_touristic_options o 
                 WHERE o.id_media_object = mo.id 
                    OR o.id_booking_package IN (
                        SELECT id FROM pmt2core_touristic_booking_packages WHERE id_media_object = mo.id
                    )) as options_count
            FROM pmt2core_media_objects mo
            LEFT JOIN pmt2core_cheapest_price_speed cps ON cps.id_media_object = mo.id
            WHERE mo.id_object_type IN ({$placeholders})
              AND mo.visibility = ?
              AND cps.id IS NULL
            ORDER BY mo.id_object_type, mo.name
        ";

        $params = array_merge($objectTypeIds, [$visibility]);
        
        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Gets detailed information about an orphan.
     *
     * @param int $idMediaObject ID of the Media Object
     * @return array Detailed information including Booking Packages, Dates, Options
     */
    public function getOrphanDetails(int $idMediaObject): array
    {
        // Media Object base data
        $sql = "SELECT * FROM pmt2core_media_objects WHERE id = ?";
        $mediaObject = $this->db->fetchRow($sql, [$idMediaObject]);
        
        if (empty($mediaObject)) {
            return ['error' => 'Media Object not found'];
        }

        // Booking Packages
        $sql = "SELECT id, name, duration, ibe_type, product_type_ibe 
                FROM pmt2core_touristic_booking_packages 
                WHERE id_media_object = ?";
        $bookingPackages = $this->db->fetchAll($sql, [$idMediaObject]);

        // Dates per Booking Package
        $bookingPackageIds = array_column($bookingPackages, 'id');
        $dates = [];
        if (!empty($bookingPackageIds)) {
            $placeholders = implode(',', array_fill(0, count($bookingPackageIds), '?'));
            $sql = "SELECT id, id_booking_package, departure, arrival, state 
                    FROM pmt2core_touristic_dates 
                    WHERE id_booking_package IN ({$placeholders})
                    ORDER BY departure";
            $dates = $this->db->fetchAll($sql, $bookingPackageIds);
        }

        // Options (directly on Media Object or via Booking Package)
        $sql = "SELECT o.id, o.name, o.code, o.type, o.price, o.id_booking_package
                FROM pmt2core_touristic_options o
                WHERE o.id_media_object = ?";
        $directOptions = $this->db->fetchAll($sql, [$idMediaObject]);

        $bookingPackageOptions = [];
        if (!empty($bookingPackageIds)) {
            $placeholders = implode(',', array_fill(0, count($bookingPackageIds), '?'));
            $sql = "SELECT o.id, o.name, o.code, o.type, o.price, o.id_booking_package
                    FROM pmt2core_touristic_options o
                    WHERE o.id_booking_package IN ({$placeholders})";
            $bookingPackageOptions = $this->db->fetchAll($sql, $bookingPackageIds);
        }

        // Cheapest Price entries (for verification)
        $sql = "SELECT COUNT(*) as count FROM pmt2core_cheapest_price_speed WHERE id_media_object = ?";
        $cheapestPriceCount = $this->db->fetchRow($sql, [$idMediaObject]);

        // Create diagnosis
        $diagnosis = $this->diagnoseOrphan($bookingPackages, $dates, $directOptions, $bookingPackageOptions);

        return [
            'media_object' => $mediaObject,
            'booking_packages' => $bookingPackages,
            'dates' => $dates,
            'direct_options' => $directOptions,
            'booking_package_options' => $bookingPackageOptions,
            'cheapest_price_count' => $cheapestPriceCount->count ?? 0,
            'diagnosis' => $diagnosis
        ];
    }

    /**
     * Creates a diagnosis why a product has no cheapest prices.
     *
     * @param array $bookingPackages
     * @param array $dates
     * @param array $directOptions
     * @param array $bookingPackageOptions
     * @return array Diagnosis information
     */
    private function diagnoseOrphan(array $bookingPackages, array $dates, array $directOptions, array $bookingPackageOptions): array
    {
        $issues = [];
        $recommendations = [];

        if (empty($bookingPackages)) {
            $issues[] = 'No booking packages available';
            $recommendations[] = 'Create a booking package in pressmind';
        }

        if (empty($dates)) {
            $issues[] = 'No travel dates available';
            $recommendations[] = 'Create travel dates in pressmind';
        }

        $allOptions = array_merge($directOptions, $bookingPackageOptions);
        if (empty($allOptions)) {
            $issues[] = 'No options (price options) available';
            $recommendations[] = 'Create price options for the booking package';
        }

        // Check if dates are in the future
        $now = new \DateTime();
        $futureDates = array_filter($dates, function($date) use ($now) {
            $departure = new \DateTime($date->departure);
            return $departure > $now;
        });

        if (!empty($dates) && empty($futureDates)) {
            $issues[] = 'All travel dates are in the past';
            $recommendations[] = 'Create new travel dates for the future';
        }

        $status = empty($issues) ? 'unknown' : 'issues_found';
        if (empty($issues)) {
            $issues[] = 'No obvious issues found - consider checking the cheapest price generator';
        }

        return [
            'status' => $status,
            'issues' => $issues,
            'recommendations' => $recommendations,
            'summary' => [
                'booking_packages' => count($bookingPackages),
                'dates_total' => count($dates),
                'dates_future' => count($futureDates),
                'options_direct' => count($directOptions),
                'options_booking_package' => count($bookingPackageOptions)
            ]
        ];
    }

    /**
     * Gets statistics about orphans per object type.
     *
     * @param array $objectTypeIds Array of object type IDs
     * @param int $visibility Visibility value (default: 30)
     * @return array Statistics per object type
     */
    public function getStatistics(array $objectTypeIds, int $visibility = 30): array
    {
        if (empty($objectTypeIds)) {
            return [];
        }

        $stats = [];
        $totalOrphans = 0;
        $totalVisible = 0;

        foreach ($objectTypeIds as $objectTypeId) {
            // Number of visible objects
            $sql = "SELECT COUNT(*) as count 
                    FROM pmt2core_media_objects 
                    WHERE id_object_type = ? AND visibility = ?";
            $visibleCount = $this->db->fetchRow($sql, [$objectTypeId, $visibility]);

            // Number of objects with cheapest prices
            $sql = "SELECT COUNT(DISTINCT mo.id) as count 
                    FROM pmt2core_media_objects mo
                    INNER JOIN pmt2core_cheapest_price_speed cps ON cps.id_media_object = mo.id
                    WHERE mo.id_object_type = ? AND mo.visibility = ?";
            $withPricesCount = $this->db->fetchRow($sql, [$objectTypeId, $visibility]);

            // Number of orphans
            $sql = "SELECT COUNT(*) as count 
                    FROM pmt2core_media_objects mo
                    LEFT JOIN pmt2core_cheapest_price_speed cps ON cps.id_media_object = mo.id
                    WHERE mo.id_object_type = ? AND mo.visibility = ? AND cps.id IS NULL";
            $orphansCount = $this->db->fetchRow($sql, [$objectTypeId, $visibility]);

            $visible = (int)($visibleCount->count ?? 0);
            $withPrices = (int)($withPricesCount->count ?? 0);
            $orphans = (int)($orphansCount->count ?? 0);

            // Get object type name from config
            $objectTypeName = $this->getObjectTypeName($objectTypeId);

            $stats[$objectTypeId] = [
                'id_object_type' => $objectTypeId,
                'name' => $objectTypeName,
                'visible_count' => $visible,
                'with_prices_count' => $withPrices,
                'orphans_count' => $orphans,
                'percentage_orphans' => $visible > 0 ? round(($orphans / $visible) * 100, 1) : 0
            ];

            $totalOrphans += $orphans;
            $totalVisible += $visible;
        }

        return [
            'by_object_type' => $stats,
            'total' => [
                'visible_count' => $totalVisible,
                'orphans_count' => $totalOrphans,
                'percentage_orphans' => $totalVisible > 0 ? round(($totalOrphans / $totalVisible) * 100, 1) : 0
            ]
        ];
    }

    /**
     * Gets the name of an object type from the configuration.
     *
     * @param int $objectTypeId
     * @return string
     */
    private function getObjectTypeName(int $objectTypeId): string
    {
        $mediaTypes = $this->config['data']['media_types'] ?? [];
        return $mediaTypes[$objectTypeId] ?? 'Unknown (' . $objectTypeId . ')';
    }

    /**
     * Gets the primary media type IDs from the configuration.
     *
     * @return array
     */
    public function getPrimaryMediaTypeIds(): array
    {
        return $this->config['data']['primary_media_type_ids'] ?? [];
    }
}
