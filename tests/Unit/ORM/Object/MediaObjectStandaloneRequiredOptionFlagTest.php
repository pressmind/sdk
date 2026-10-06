<?php

namespace Pressmind\Tests\Unit\ORM\Object;

use Pressmind\Tests\Unit\AbstractTestCase;

/**
 * Guards the contract of data.touristic.include_standalone_required_option_in_cheapest_price.
 *
 * The aggregation inside insertCheapestPrice() cannot be reached with a mocked database,
 * it leaves early when a media object has no booking packages. These assertions therefore
 * cover the two properties that make the setting safe for existing installations: the
 * default ships as enabled, and only the standalone branch is guarded while the
 * required_group branch keeps running unconditionally.
 */
class MediaObjectStandaloneRequiredOptionFlagTest extends AbstractTestCase
{
    private const SETTING = 'include_standalone_required_option_in_cheapest_price';

    private function sdkRoot(): string
    {
        return dirname(__DIR__, 4);
    }

    public function testDefaultConfigShipsTheSettingEnabled(): void
    {
        $config = json_decode(file_get_contents($this->sdkRoot() . '/config.default.json'), true);
        $this->assertIsArray($config, 'config.default.json is not valid JSON');

        // The default config is keyed by environment and only the development block is
        // populated, it serves as the template for new installations.
        $touristic = $config['development']['data']['touristic'] ?? null;
        $this->assertIsArray($touristic, 'development.data.touristic is missing');
        $this->assertArrayHasKey(
            self::SETTING,
            $touristic,
            'The setting is missing from config.default.json'
        );
        $this->assertTrue(
            $touristic[self::SETTING],
            'The default has to keep the existing behaviour'
        );
    }

    public function testAbsentSettingResolvesToEnabled(): void
    {
        $source = file_get_contents($this->sdkRoot() . '/src/Pressmind/ORM/Object/MediaObject.php');
        $this->assertMatchesRegularExpression(
            '/!isset\(Registry::getInstance\(\)->get\(\'config\'\)\[\'data\'\]\[\'touristic\'\]\[\''
                . self::SETTING . '\'\]\) \? true :/',
            $source,
            'An installation without the setting has to behave as before'
        );
    }

    public function testOnlyTheStandaloneBranchIsGuarded(): void
    {
        $source = file_get_contents($this->sdkRoot() . '/src/Pressmind/ORM/Object/MediaObject.php');

        $guarded = '/if \(\$' . self::SETTING . ' !== false\) \{\s*'
            . 'foreach \(\$option_list as \$option\) \{\s*'
            . 'if \(!empty\(\$option->required\) && empty\(\$option->required_group\)/';
        $this->assertMatchesRegularExpression(
            $guarded,
            $source,
            'Required options without a required_group are no longer behind the setting'
        );

        // Exactly one of the group members always has to be booked, so that branch is
        // part of the cheapest price regardless of the setting.
        $this->assertStringContainsString(
            'if (!empty($option->required_group) && !empty($option->required)) {',
            $source,
            'The required_group branch has to stay unconditional'
        );
        $this->assertDoesNotMatchRegularExpression(
            '/if \(\$' . self::SETTING . ' !== false\) \{\s*'
                . 'foreach \(\$option_list as \$option\) \{\s*'
                . '\$key = \$option->type/',
            $source,
            'The required_group branch must not be put behind the setting'
        );
    }
}
