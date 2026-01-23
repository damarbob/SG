<?php

namespace Tests\Feature\Api;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Shield\Models\UserModel;
use CodeIgniter\Shield\Authentication\Authenticators\AccessTokens;

class AccessTokenTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = true;
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

        // Mock Throttler
        $throttler = $this->getMockBuilder(\CodeIgniter\Throttle\Throttler::class)
            ->disableOriginalConstructor()
            ->getMock();
        $throttler->method('check')->willReturn(true);
        \CodeIgniter\Config\Services::injectMock('throttler', $throttler);
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

    public function testListTokens()
    {
        $user = $this->createTestUser('token_user', 'token@example.com');
        $token = $user->generateAccessToken('list-token');

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token->raw_token])
            ->get('api/v1/access-token/list');

        $result->assertStatus(200);
        $json = json_decode($result->response()->getBody(), true);
        $this->assertArrayHasKey('tokens', $json);
        $this->assertNotEmpty($json['tokens'], 'Tokens array is empty');
        $this->assertEquals('list-token', $json['tokens'][0]['name']);
    }

    public function testGenerateToken()
    {
        $user = $this->createTestUser('gen_user', 'gen@example.com');
        $token = $user->generateAccessToken('login-token');

        $data = ['name' => 'new-token'];

        $result = $this->withBody(json_encode($data))
            ->withHeaders([
                'Authorization' => 'Bearer ' . $token->raw_token,
                'Content-Type'  => 'application/json'
            ])
            ->post('api/v1/access-token/generate');

        $result->assertStatus(201);
        $json = json_decode($result->response()->getBody());
        $this->assertObjectHasProperty('token', $json);

        // Verify in DB
        $this->seeInDatabase('auth_identities', [
            'user_id' => $user->id,
            'secret'  => hash('sha256', $json->token),
            'type'    => AccessTokens::ID_TYPE_ACCESS_TOKEN
        ]);
    }

    public function testDeleteToken()
    {
        $user = $this->createTestUser('del_user', 'del@example.com');
        $token1 = $user->generateAccessToken('keep-token');
        $token2 = $user->generateAccessToken('delete-token');

        // Verify it exists first (by checking checking logic indirectly or just assumption)
        // Check DB for token2
        // We need the ID of token2. 
        // AccessToken is not returned with ID on generateAccessToken usually, but we can query it.
        $identityModel = model(\CodeIgniter\Shield\Models\UserIdentityModel::class);
        $identity = $identityModel->where('user_id', $user->id)
            ->like('name', 'delete-token')
            ->first();

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token1->raw_token])
            ->delete('api/v1/access-token/' . $identity->id);

        $result->assertStatus(204);

        $this->dontSeeInDatabase('auth_identities', ['id' => $identity->id]);
    }

    public function testRevokeToken()
    {
        $user = $this->createTestUser('rev_user', 'rev@example.com');
        $token1 = $user->generateAccessToken('auth-token');
        $token2 = $user->generateAccessToken('revoke-me');

        $data = ['token' => $token2->raw_token];

        $result = $this->withBody(json_encode($data))
            ->withHeaders([
                'Authorization' => 'Bearer ' . $token1->raw_token,
                'Content-Type'  => 'application/json'
            ])
            ->post('api/v1/access-token/revoke-token');

        $result->assertStatus(204);

        // Check DB
        $this->dontSeeInDatabase('auth_identities', [
            'user_id' => $user->id,
            'secret' => hash('sha256', $token2->raw_token)
        ]);
    }

    public function testRevokeAll()
    {
        $user = $this->createTestUser('revall_user', 'revall@example.com');
        $token1 = $user->generateAccessToken('token-1');
        $token2 = $user->generateAccessToken('token-2');

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token1->raw_token])
            ->post('api/v1/access-token/revoke-all');

        $result->assertStatus(204);

        $this->assertEmpty($user->accessTokens());
    }
}
