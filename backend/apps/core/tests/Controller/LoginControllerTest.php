<?php
declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpKernel\KernelInterface;
use Thomann\Core\Kernel;

final class LoginControllerTest extends WebTestCase {
    public function testStatusReturnsLoggedInRoot (): void {
        $client = static::createClient();

        $client->request('GET', '/api/login/status');
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type', 'application/json');

        $data = self::decode($client);
        self::assertArrayHasKey('loggedIn', $data);
        self::assertTrue($data['loggedIn']);

        self::assertArrayHasKey('user', $data);
        self::assertIsArray($data['user']);
        self::assertSame('root', $data['user']['username'] ?? null);

        // roles should be present with admin + superadmin
        self::assertArrayHasKey('roles', $data['user']);
        self::assertContains('admin', $data['user']['roles']);
        self::assertContains('superadmin', $data['user']['roles']);
    }

    public function testLoginSuccessForRoot (): void {
        $client = static::createClient();

        $client->request(
            'POST',
            '/api/login',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['username' => 'root', 'password' => 'x'], JSON_THROW_ON_ERROR)
        );

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type', 'application/json');

        $data = self::decode($client);

        self::assertTrue($data['loggedIn'] ?? null);
        self::assertSame('root', $data['user']['username'] ?? null);
        self::assertContains('admin', $data['user']['roles'] ?? []);
        self::assertContains('superadmin', $data['user']['roles'] ?? []);
    }

    public function testLoginFailsForNonRoot (): void {
        $client = static::createClient();

        $client->request(
            'POST',
            '/api/login',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['username' => 'alice', 'password' => 'x'], JSON_THROW_ON_ERROR)
        );

        self::assertResponseIsSuccessful(); // controller always returns 200
        $data = self::decode($client);

        self::assertFalse($data['loggedIn'] ?? true);

        // user may be null or missing (depending on your serializer config/groups)
        // assert the important part: no roles are present
        self::assertTrue(
            !isset($data['user']) ||
            $data['user'] === null ||
            !array_key_exists('roles', (array) $data['user'])
        );
    }

    /**
     * Optional: if you add Symfony Validator constraints to LoginRequest
     * (e.g., #[Assert\NotBlank] on username/password), MapRequestPayload will
     * trigger a 422 (or 400) for invalid payloads. Enable this test then.
     */
    public function testLoginValidationErrorOnMissingUsername (): void {
        $client = static::createClient();

        $client->request(
            'POST',
            '/api/login',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['password' => 'x'], JSON_THROW_ON_ERROR)
        );

        // Accept either 422 or 400 depending on your config;
        // flip to assertResponseStatusCodeSame(422) once you lock it in.
        self::assertTrue(in_array($client->getResponse()->getStatusCode(), [400, 422], true));
    }

    private static function decode ($client): array {
        $json = $client->getResponse()->getContent();
        return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    }

    protected static function getKernelClass (): string {
        return Kernel::class;
    }

    // Force the 'test' env explicitly
    protected static function createKernel (array $options = []): KernelInterface {
        return new Kernel('test', true);
    }
}
