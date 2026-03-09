<?php

namespace Pressmind\Tests\Integration\REST\Controller;

use Pressmind\DB\Scaffolder\Mysql as ScaffolderMysql;
use Pressmind\ORM\Object\Geodata;
use Pressmind\ORM\Object\MediaObject;
use Pressmind\ORM\Object\ObjectdataTag;
use Pressmind\ORM\Object\Touristic\Date as TouristicDate;
use Pressmind\Tests\Integration\AbstractIntegrationTestCase;
use Pressmind\Tests\Integration\FixtureLoader;
use Pressmind\REST\Controller\Ibe;
use Pressmind\REST\Server;
use Pressmind\MVC\Request;
use Pressmind\MVC\Response;
use Pressmind\MVC\Router;
use Pressmind\Registry;

/**
 * E2E tests for the Ibe controller through the REST Server stack.
 * Validates: routing -> auth -> controller -> response encoding.
 */
class IbeE2ETest extends AbstractIntegrationTestCase
{
    private static bool $tablesVerified = false;
    private static bool $tablesExist = false;

    protected function setUp(): void
    {
        parent::setUp();

        if ($this->db === null) {
            $this->markTestSkipped('No database connection available (set DB_HOST, DB_NAME, DB_USER, DB_PASS)');
        }

        $config = Registry::getInstance()->get('config');
        $config['logging']['mode'] = 'NONE';
        $config['logging']['storage'] = [];
        $config['cache']['enabled'] = false;
        $config['cache']['types'] = [];
        $config['cache']['update_parameter'] = ['key' => 'cache_update', 'value' => '1'];
        $config['cache']['disable_parameter'] = ['key' => 'cache_disable'];
        Registry::getInstance()->add('config', $config);

        if (!self::$tablesVerified) {
            $this->ensureTables();
            self::$tablesExist = true;
            self::$tablesVerified = true;
        }
    }

    private function ensureTables(): void
    {
        $models = [
            new Geodata(),
            new MediaObject(),
            new ObjectdataTag(),
            new TouristicDate(),
        ];
        foreach ($models as $model) {
            try {
                $scaffolder = new ScaffolderMysql($model);
                $scaffolder->run(false);
            } catch (\Throwable $e) {
            }
        }
    }

    /**
     * Creates a Server with mocked Request/Router to route to a specific Ibe method.
     */
    private function createServerForIbeMethod(string $method, string $httpMethod, array $parameters): Server
    {
        $request = $this->createMock(Request::class);
        $request->method('getUri')->willReturn('ibe/' . $method);
        $request->method('getMethod')->willReturn($httpMethod);
        $request->method('getParameters')->willReturn($parameters);
        $request->method('getParameter')->willReturnCallback(function ($key) use ($parameters) {
            return $parameters[$key] ?? null;
        });
        $request->method('getHeader')->willReturn('');
        $request->method('getParsedBasicAuth')->willReturn(null);

        $router = new Router();
        $route = new Router\Route(
            'ibe/' . $method,
            $httpMethod,
            '\\Pressmind\\REST\\Controller',
            'Ibe',
            $method
        );
        $router->addRoute($route);

        return new Server(null, $request, new Response(), $router);
    }

    // ---------------------------------------------------------------
    // Full-stack: pressmind_ib3_v2_test
    // ---------------------------------------------------------------

    public function testE2ETestEndpointReturnsSuccessResponse(): void
    {
        $server = $this->createServerForIbeMethod(
            'pressmind_ib3_v2_test',
            'POST',
            ['data' => ['key' => 'value']]
        );
        $response = $server->run();

        $this->assertSame(200, $response->getCode());
        $body = $response->getBody();
        $this->assertIsArray($body);
        $this->assertTrue($body['success']);
        $this->assertSame('Test erfolgreich', $body['msg']);
    }

    public function testE2ETestEndpointResponseIsJsonSerializable(): void
    {
        $server = $this->createServerForIbeMethod(
            'pressmind_ib3_v2_test',
            'POST',
            ['foo' => 'bar']
        );
        $response = $server->run();
        $body = $response->getBody();
        $json = json_encode($body);
        $this->assertNotFalse($json);
        $decoded = json_decode($json, true);
        $this->assertTrue($decoded['success']);
    }

    // ---------------------------------------------------------------
    // Full-stack: pressmind_ib3_v2_get_geodata_status
    // ---------------------------------------------------------------

    public function testE2EGeodataStatusReturnsValidResponse(): void
    {
        $server = $this->createServerForIbeMethod(
            'pressmind_ib3_v2_get_geodata_status',
            'GET',
            []
        );
        $response = $server->run();

        $this->assertSame(200, $response->getCode());
        $body = $response->getBody();
        $this->assertIsArray($body);
        $this->assertTrue($body['success']);
        $this->assertArrayHasKey('data', $body);
        $this->assertArrayHasKey('geodata_count', $body['data']);
        $this->assertArrayHasKey('has_geodata', $body['data']);
        $this->assertIsInt($body['data']['geodata_count']);
        $this->assertIsBool($body['data']['has_geodata']);
    }

    // ---------------------------------------------------------------
    // Full-stack: getCheapestPrice error handling
    // ---------------------------------------------------------------

    public function testE2ECheapestPriceMissingParamReturnsError(): void
    {
        $server = $this->createServerForIbeMethod(
            'getCheapestPrice',
            'POST',
            ['data' => []]
        );
        $response = $server->run();

        $this->assertSame(200, $response->getCode());
        $body = $response->getBody();
        $this->assertFalse($body['success']);
        $this->assertStringContainsString('parameters are missing', $body['msg']);
    }

    // ---------------------------------------------------------------
    // Full-stack: getRequestableOffer error handling
    // ---------------------------------------------------------------

    public function testE2ERequestableOfferMissingParamReturnsError(): void
    {
        $server = $this->createServerForIbeMethod(
            'getRequestableOffer',
            'POST',
            ['data' => []]
        );
        $response = $server->run();

        $this->assertSame(200, $response->getCode());
        $body = $response->getBody();
        $this->assertFalse($body['success']);
        $this->assertStringContainsString('parameters are missing', $body['msg']);
        $this->assertArrayHasKey('params', $body);
    }

    // ---------------------------------------------------------------
    // Full-stack: pressmind_ib3_v2_get_touristic_object validation
    // ---------------------------------------------------------------

    public function testE2ETouristicObjectMissingParamsReturnsError(): void
    {
        $server = $this->createServerForIbeMethod(
            'pressmind_ib3_v2_get_touristic_object',
            'POST',
            ['data' => ['params' => []]]
        );
        $response = $server->run();

        $this->assertSame(200, $response->getCode());
        $body = $response->getBody();
        $this->assertFalse($body['success']);
        $this->assertStringContainsString('parameters are missing', $body['msg']);
    }

    public function testE2ETouristicObjectDateNotFoundReturnsError(): void
    {
        $server = $this->createServerForIbeMethod(
            'pressmind_ib3_v2_get_touristic_object',
            'POST',
            ['data' => ['params' => ['imo' => 1, 'idbp' => 'bp_nonexistent', 'idd' => 'date_nonexistent']]]
        );
        $response = $server->run();

        $this->assertSame(200, $response->getCode());
        $body = $response->getBody();
        $this->assertIsArray($body);
        $this->assertFalse($body['success']);
        $this->assertArrayHasKey('code', $body);
        $this->assertSame('not_found', $body['code']);
        $this->assertStringContainsString('date not found', $body['msg']);
    }

    // ---------------------------------------------------------------
    // Full-stack: pressmind_ib3_v2_get_starting_point_options
    // ---------------------------------------------------------------

    public function testE2EStartingPointOptionsReturnsResponse(): void
    {
        $server = $this->createServerForIbeMethod(
            'pressmind_ib3_v2_get_starting_point_options',
            'POST',
            ['data' => ['id_starting_point' => 'sp_1', 'limit' => 10]]
        );
        $response = $server->run();

        $this->assertSame(200, $response->getCode());
        $body = $response->getBody();
        $this->assertIsArray($body);
        $this->assertTrue($body['success']);
        $this->assertArrayHasKey('data', $body);
        $this->assertArrayHasKey('total', $body['data']);
        $this->assertArrayHasKey('starting_point_options', $body['data']);
    }

    // ---------------------------------------------------------------
    // Full-stack: pressmind_ib3_v2_get_starting_point_option_by_id
    // ---------------------------------------------------------------

    public function testE2EStartingPointOptionByIdReturnsResponse(): void
    {
        $server = $this->createServerForIbeMethod(
            'pressmind_ib3_v2_get_starting_point_option_by_id',
            'POST',
            ['data' => ['id_starting_point_option' => 'opt_nonexistent_999']]
        );
        $response = $server->run();

        $this->assertSame(200, $response->getCode());
        $body = $response->getBody();
        $this->assertIsArray($body);
        $this->assertTrue($body['success']);
        $this->assertArrayHasKey('data', $body);
        $this->assertArrayHasKey('starting_point_option', $body['data']);
    }

    // ---------------------------------------------------------------
    // Full-stack: pressmind_ib3_v2_find_pickup_service
    // ---------------------------------------------------------------

    public function testE2EPickupServiceReturnsResponse(): void
    {
        $server = $this->createServerForIbeMethod(
            'pressmind_ib3_v2_find_pickup_service',
            'POST',
            ['data' => ['id_starting_point' => 'sp_1']]
        );
        $response = $server->run();

        $this->assertSame(200, $response->getCode());
        $body = $response->getBody();
        $this->assertIsArray($body);
        $this->assertTrue($body['success']);
        $this->assertArrayHasKey('data', $body);
        $this->assertArrayHasKey('total', $body['data']);
        $this->assertArrayHasKey('starting_point_options', $body['data']);
    }

    // ---------------------------------------------------------------
    // Full-stack: Response headers
    // ---------------------------------------------------------------

    public function testE2EResponseHasCorrectContentType(): void
    {
        $server = $this->createServerForIbeMethod(
            'pressmind_ib3_v2_test',
            'POST',
            []
        );
        $response = $server->run();

        $this->assertSame('application/json', $response->getContentType());
    }

    public function testE2EResponseHasCorsHeaders(): void
    {
        $server = $this->createServerForIbeMethod(
            'pressmind_ib3_v2_test',
            'POST',
            []
        );
        $response = $server->run();

        $headers = $response->getHeaders();
        $this->assertArrayHasKey('Access-Control-Allow-Origin', $headers);
        $this->assertSame('*', $headers['Access-Control-Allow-Origin']);
    }

    // ---------------------------------------------------------------
    // Full-stack: 404 for non-existent route
    // ---------------------------------------------------------------

    public function testE2ENonExistentRouteReturns404(): void
    {
        $request = $this->createMock(Request::class);
        $request->method('getUri')->willReturn('nonexistent/route');
        $request->method('getMethod')->willReturn('GET');
        $request->method('getParameters')->willReturn([]);
        $request->method('getParameter')->willReturn(null);
        $request->method('getHeader')->willReturn('');
        $request->method('getParsedBasicAuth')->willReturn(null);

        $router = new Router();
        $server = new Server(null, $request, new Response(), $router);
        $response = $server->run();

        $this->assertSame(404, $response->getCode());
    }
}
