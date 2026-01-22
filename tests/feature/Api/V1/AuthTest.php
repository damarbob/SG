<?php

namespace Tests\Feature\Api\V1;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Shield\Models\UserModel;

class AuthTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = true;
    protected $refresh     = true;
    protected $namespace   = 'CodeIgniter\Shield';

    protected function migrateDatabase()
    {
        $runner = service('migrations');
        $runner->setGroup('tests');

        $runner->setNamespace('CodeIgniter\Settings');
        $runner->latest('tests');

        $runner->setNamespace('CodeIgniter\Shield');
        $runner->latest('tests');

        $runner->setNamespace('App');
        $runner->latest('tests');
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Mock Throttler to bypass 429 Too Many Requests
        $throttler = $this->getMockBuilder(\CodeIgniter\Throttle\Throttler::class)
            ->disableOriginalConstructor()
            ->getMock();

        $throttler->method('check')->willReturn(true);

        \CodeIgniter\Config\Services::injectMock('throttler', $throttler);
    }

    public function testRegisterSuccess()
    {
        $data = [
            'username'         => 'testuser',
            'email'            => 'test@example.com',
            'password'         => 'StrongPassword123!',
            'password_confirm' => 'StrongPassword123!',
        ];

        $result = $this->withBody(json_encode($data))
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post('api/v1/auth/register');

        $result->assertStatus(201);

        // Assert nested structure
        $result->assertJSONFragment([
            'user' => [
                'username' => 'testuser',
                'email'    => 'test@example.com'
            ]
        ]);

        // Ensure user is in DB
        $this->seeInDatabase('users', ['username' => 'testuser', 'active' => 1]);
    }

    public function testRegisterValidationFail()
    {
        $data = [
            'username'         => 'testuser',
            'email'            => 'not-an-email',
            'password'         => '123', // Too short
            'password_confirm' => '123',
        ];

        $result = $this->withBody(json_encode($data))
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post('api/v1/auth/register');

        $result->assertStatus(400);
    }

    public function testRegisterDuplicateEmail()
    {
        $data = [
            'username'         => 'user1',
            'email'            => 'test@example.com',
            'password'         => 'StrongPassword123!',
            'password_confirm' => 'StrongPassword123!',
        ];

        $this->withBody(json_encode($data))
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post('api/v1/auth/register');

        $data2 = [
            'username'         => 'user2',
            'email'            => 'test@example.com',
            'password'         => 'StrongPassword123!',
            'password_confirm' => 'StrongPassword123!',
        ];

        $result = $this->withBody(json_encode($data2))
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post('api/v1/auth/register');

        $result->assertStatus(400);
    }

    public function testRegisterDisabled()
    {
        $config = config('Auth');
        $original = $config->allowRegistration;
        $config->allowRegistration = false;

        $data = [
            'username'         => 'testuser',
            'email'            => 'test@example.com',
            'password'         => 'StrongPassword123!',
            'password_confirm' => 'StrongPassword123!',
        ];

        $result = $this->withBody(json_encode($data))
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post('api/v1/auth/register');

        $config->allowRegistration = $original;

        $result->assertStatus(403);
    }

    public function testLoginSuccess()
    {
        $data = [
            'username' => 'loginuser',
            'email'    => 'login@example.com',
            'password' => 'StrongPassword123!',
            'active'   => 1,
        ];

        $user = new \CodeIgniter\Shield\Entities\User($data);
        $user->password_hash = service('passwords')->hash('StrongPassword123!');

        $users = model(UserModel::class);
        if (! $users->save($user)) {
            $this->fail('User creation failed: ' . json_encode($users->errors()));
        }

        $loginData = [
            'email'    => 'login@example.com',
            'password' => 'StrongPassword123!',
        ];

        $result = $this->withBody(json_encode($loginData))
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post('api/v1/auth/login');

        $result->assertStatus(200);

        $result->assertJSONFragment([
            'user' => [
                'email' => 'login@example.com'
            ]
        ]);

        $json = json_decode($result->response()->getBody());
        $this->assertObjectHasProperty('access_token', $json);
    }

    public function testLoginFailInvalidCredentials()
    {
        $data = [
            'username' => 'wrongpass',
            'email'    => 'wrongpass@example.com',
            'password' => 'StrongPassword123!',
            'active'   => 1,
        ];

        $user = new \CodeIgniter\Shield\Entities\User($data);
        $user->password_hash = service('passwords')->hash('StrongPassword123!');
        $users = model(UserModel::class);
        $users->save($user);

        $loginData = [
            'email'    => 'wrongpass@example.com',
            'password' => 'WrongPassword',
        ];

        $result = $this->withBody(json_encode($loginData))
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post('api/v1/auth/login');

        $result->assertStatus(401);
    }

    public function testLoginValidationFail()
    {
        $loginData = [
            'email'    => 'not-an-email',
        ];

        $result = $this->withBody(json_encode($loginData))
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post('api/v1/auth/login');

        $result->assertStatus(400);
    }

    public function testLoginFailUserNotFound()
    {
        $loginData = [
            'email'    => 'unknown@example.com',
            'password' => 'StrongPassword123!',
        ];

        $result = $this->withBody(json_encode($loginData))
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post('api/v1/auth/login');

        $result->assertStatus(401);
    }

    public function testLoginFailUserInactive()
    {
        $data = [
            'username' => 'inactive',
            'email'    => 'inactive@example.com',
            'password' => 'StrongPassword123!',
            'active'   => 0,
        ];

        $user = new \CodeIgniter\Shield\Entities\User($data);
        $user->password_hash = service('passwords')->hash('StrongPassword123!');
        $users = model(UserModel::class);
        $users->save($user);

        // Ensure clearly inactive
        $user = $users->findById($users->getInsertID());
        $user->deactivate();
        $users->save($user); // Ensure change is persisted

        $loginData = [
            'email'    => 'inactive@example.com',
            'password' => 'StrongPassword123!',
        ];

        $result = $this->withBody(json_encode($loginData))
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post('api/v1/auth/login');

        $result->assertStatus(401);
    }

    public function testLoginFailUserBanned()
    {
        $data = [
            'username' => 'banned',
            'email'    => 'banned@example.com',
            'password' => 'StrongPassword123!',
            'active'   => 1,
        ];

        $user = new \CodeIgniter\Shield\Entities\User($data);
        $user->password_hash = service('passwords')->hash('StrongPassword123!');
        $users = model(UserModel::class);
        $users->save($user);

        // Ban the user
        $user = $users->findById($users->getInsertID());
        $users->addToDefaultGroup($user);
        $user->ban('Banned for testing');

        $loginData = [
            'email'    => 'banned@example.com',
            'password' => 'StrongPassword123!',
        ];

        $result = $this->withBody(json_encode($loginData))
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post('api/v1/auth/login');

        $result->assertStatus(401);
    }

    public function testMe()
    {
        $user = $this->createTestUser('me_user', 'me@example.com');
        $token = $user->generateAccessToken('test-token');

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token->raw_token])
            ->get('api/v1/auth/me');

        $result->assertStatus(200);
        $result->assertJSONFragment(['email' => 'me@example.com']);
    }

    public function testLogout()
    {
        $user = $this->createTestUser('logout_user', 'logout@example.com');
        $token = $user->generateAccessToken('test-token');

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token->raw_token])
            ->post('api/v1/auth/logout');

        $result->assertStatus(204);

        // Assert token is gone
        $this->assertEmpty($user->accessTokens());
    }

    private function createTestUser($username, $email)
    {
        $data = [
            'username' => $username,
            'email'    => $email,
            'password' => 'StrongPassword123!',
            'active'   => 1,
        ];

        $user = new \CodeIgniter\Shield\Entities\User($data);
        $user->password_hash = service('passwords')->hash('StrongPassword123!');

        $users = model(UserModel::class);
        $users->save($user);

        return $users->findById($users->getInsertID());
    }

    public function testMagicLinkSuccess()
    {
        $user = $this->createTestUser('magic_user', 'magic@example.com');

        $data = [
            'email' => 'magic@example.com',
        ];

        // Ensure allowMagicLinkLogins is true
        $original = setting('Auth.allowMagicLinkLogins');
        config('Auth')->allowMagicLinkLogins = true;

        // Mock StarGate config
        $originalCallback = config('StarGate')->magicLinkCallbackUrl;
        config('StarGate')->magicLinkCallbackUrl = 'https://example.com/verify';

        $result = $this->withBody(json_encode($data))
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post('api/v1/auth/magic-link');

        config('Auth')->allowMagicLinkLogins = $original;
        config('StarGate')->magicLinkCallbackUrl = $originalCallback;

        $result->assertStatus(200);

        // Verify Identity created
        $this->seeInDatabase('auth_identities', [
            'user_id' => $user->id,
            'type'    => 'magic-link',
        ]);
    }

    public function testMagicLinkMissingConfig()
    {
        $user = $this->createTestUser('noconfig_user', 'noconfig@example.com');

        $data = ['email' => 'noconfig@example.com'];

        $original = setting('Auth.allowMagicLinkLogins');
        config('Auth')->allowMagicLinkLogins = true;

        // Mock Missing Config
        $originalCallback = config('StarGate')->magicLinkCallbackUrl;
        config('StarGate')->magicLinkCallbackUrl = '';

        $result = $this->withBody(json_encode($data))
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post('api/v1/auth/magic-link');

        config('Auth')->allowMagicLinkLogins = $original;
        config('StarGate')->magicLinkCallbackUrl = $originalCallback;

        $result->assertStatus(500);
    }

    public function testVerifyMagicLinkSuccess()
    {
        $user = $this->createTestUser('verify_user', 'verify@example.com');

        // Manually create token
        $token = 'test-magic-token';
        $identities = model(\CodeIgniter\Shield\Models\UserIdentityModel::class);
        $identities->insert([
            'user_id' => $user->id,
            'type'    => 'magic-link',
            'secret'  => $token,
            'expires' => \CodeIgniter\I18n\Time::now()->addHours(1),
        ]);

        $original = setting('Auth.allowMagicLinkLogins');
        config('Auth')->allowMagicLinkLogins = true;

        $data = ['token' => $token];

        $result = $this->withBody(json_encode($data))
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post('api/v1/auth/magic-link/verify');

        config('Auth')->allowMagicLinkLogins = $original;

        $result->assertStatus(200);
        $json = json_decode($result->response()->getBody());
        $this->assertObjectHasProperty('access_token', $json);

        // Verify token consumed
        $this->dontSeeInDatabase('auth_identities', ['secret' => $token]);
    }

    public function testMagicLinkUserNotFound()
    {
        $data = ['email' => 'unknown@example.com'];

        $original = setting('Auth.allowMagicLinkLogins');
        setting('Auth.allowMagicLinkLogins', true);

        // Mock StarGate config
        $originalCallback = config('StarGate')->magicLinkCallbackUrl;
        config('StarGate')->magicLinkCallbackUrl = 'https://example.com/verify';

        $result = $this->withBody(json_encode($data))
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post('api/v1/auth/magic-link');

        setting('Auth.allowMagicLinkLogins', $original);
        config('StarGate')->magicLinkCallbackUrl = $originalCallback;

        // Should be 200 OK (Silent Success)
        $result->assertStatus(200);
        $result->assertJSON(['message' => lang('Auth.checkYourEmail')]);
    }

    public function testVerifyMagicLinkInvalidToken()
    {
        $original = setting('Auth.allowMagicLinkLogins');
        config('Auth')->allowMagicLinkLogins = true;

        $data = ['token' => 'invalid-token'];

        $result = $this->withBody(json_encode($data))
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post('api/v1/auth/magic-link/verify');

        config('Auth')->allowMagicLinkLogins = $original;

        $result->assertStatus(401);
    }

    public function testVerifyMagicLinkExpiredToken()
    {
        $user = $this->createTestUser('expired_user', 'expired@example.com');

        // Manually create expired token
        $token = 'expired-token';
        $identities = model(\CodeIgniter\Shield\Models\UserIdentityModel::class);
        $identities->insert([
            'user_id' => $user->id,
            'type'    => 'magic-link',
            'secret'  => $token,
            'expires' => \CodeIgniter\I18n\Time::now()->subHours(1),
        ]);

        $original = setting('Auth.allowMagicLinkLogins');
        config('Auth')->allowMagicLinkLogins = true;

        $data = ['token' => $token];

        $result = $this->withBody(json_encode($data))
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post('api/v1/auth/magic-link/verify');

        config('Auth')->allowMagicLinkLogins = $original;

        $result->assertStatus(401);
    }

    public function testVerifyMagicLinkUserInactive()
    {
        $user = $this->createTestUser('inactive_magic', 'inactive_magic@example.com');

        $users = model(UserModel::class);
        $user->deactivate();
        $users->save($user);

        // Manually create token
        $token = 'inactive-token';
        $identities = model(\CodeIgniter\Shield\Models\UserIdentityModel::class);
        $identities->insert([
            'user_id' => $user->id,
            'type'    => 'magic-link',
            'secret'  => $token,
            'expires' => \CodeIgniter\I18n\Time::now()->addHours(1),
        ]);

        $original = setting('Auth.allowMagicLinkLogins');
        setting('Auth.allowMagicLinkLogins', true);

        $data = ['token' => $token];

        $result = $this->withBody(json_encode($data))
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post('api/v1/auth/magic-link/verify');

        setting('Auth.allowMagicLinkLogins', $original);

        $result->assertStatus(401);
        $result->assertJSONFragment(['messages' => ['error' => lang('StarGate.notActivated')]]);
    }

    public function testVerifyMagicLinkUserBanned()
    {
        $user = $this->createTestUser('banned_magic', 'banned_magic@example.com');

        $users = model(UserModel::class);
        $users->addToDefaultGroup($user);
        $user->ban('Testing ban');

        // Manually create token
        $token = 'banned-token';
        $identities = model(\CodeIgniter\Shield\Models\UserIdentityModel::class);
        $identities->insert([
            'user_id' => $user->id,
            'type'    => 'magic-link',
            'secret'  => $token,
            'expires' => \CodeIgniter\I18n\Time::now()->addHours(1),
        ]);

        $original = setting('Auth.allowMagicLinkLogins');
        setting('Auth.allowMagicLinkLogins', true);

        $data = ['token' => $token];

        $result = $this->withBody(json_encode($data))
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post('api/v1/auth/magic-link/verify');

        setting('Auth.allowMagicLinkLogins', $original);

        $result->assertStatus(401);
        $result->assertJSONFragment(['messages' => ['error' => lang('StarGate.userBanned')]]);
    }
}
