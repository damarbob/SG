<?php

namespace Tests\Feature\Api\V1;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Shield\Models\UserModel;
use StarDust\Services\EntriesManager;

class EntriesTest extends CIUnitTestCase
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

    private function createTestUser()
    {
        $data = [
            'username' => 'testuser_' . uniqid(),
            'email'    => 'test_' . uniqid() . '@example.com',
            'password' => 'StrongPassword123!',
            'active'   => 1,
        ];

        $user = new \CodeIgniter\Shield\Entities\User($data);
        $user->password_hash = service('passwords')->hash('StrongPassword123!');
        $users = model(UserModel::class);
        $users->save($user);

        return $users->findById($users->getInsertID());
    }

    private function createSuperAdmin()
    {
        $user = $this->createTestUser();
        $user->addGroup('superadmin');
        return $user;
    }

    private function getHeaders($user)
    {
        $token = $user->generateAccessToken('test-token');
        return [
            'Authorization' => 'Bearer ' . $token->raw_token,
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
        ];
    }

    private function mockManager()
    {
        $mock = $this->getMockBuilder(EntriesManager::class)
            ->disableOriginalConstructor()
            ->getMock();

        \CodeIgniter\Config\Services::injectMock('entriesManager', $mock);
        return $mock;
    }

    // ---------------------------------------------------------------
    // Index
    // ---------------------------------------------------------------

    public function testIndexPagination()
    {
        $user = $this->createTestUser();
        $mock = $this->mockManager();

        $mock->expects($this->any())->method('count')->willReturn(50);
        $mock->expects($this->any())->method('paginate')->willReturn([['id' => 1]]);

        // Default
        $result = $this->withHeaders($this->getHeaders($user))->get('api/v1/entries');
        $result->assertStatus(200);
        $json = json_decode($result->response()->getBody(), true);
        $this->assertEquals(20, $json['pagination']['per_page']);

        // Custom limit
        $result = $this->withHeaders($this->getHeaders($user))->get('api/v1/entries?limit=5');
        $json = json_decode($result->response()->getBody(), true);
        $this->assertEquals(5, $json['pagination']['per_page']);

        // Hard cap
        $result = $this->withHeaders($this->getHeaders($user))->get('api/v1/entries?limit=150');
        $json = json_decode($result->response()->getBody(), true);
        $this->assertEquals(100, $json['pagination']['per_page']);
    }

    public function testIndexFiltering()
    {
        $user = $this->createTestUser();
        $mock = $this->mockManager();

        $mock->expects($this->any())->method('count')->willReturn(0);
        $mock->expects($this->exactly(2))
            ->method('paginate')
            ->willReturnCallback(function ($page, $perPage, $criteria) {
                if (! $criteria instanceof \StarDust\Data\EntrySearchCriteria) {
                    throw new \Exception('Expected EntrySearchCriteria');
                }
                return [];
            });

        // Filter by model_id
        $this->withHeaders($this->getHeaders($user))->get('api/v1/entries?filter[model_id]=5');

        // Filter by date range
        $this->withHeaders($this->getHeaders($user))->get('api/v1/entries?filter[created_at][gt]=2024-01-01');
    }

    public function testIndexReturnsRequestId()
    {
        $user = $this->createTestUser();
        $mock = $this->mockManager();
        $mock->method('paginate')->willReturn([]);
        $mock->method('count')->willReturn(0);

        $result = $this->withHeaders($this->getHeaders($user))
            ->get('api/v1/entries?request_id=req_123');

        $result->assertStatus(200);
        $json = json_decode($result->response()->getBody(), true);
        $this->assertArrayHasKey('request_id', $json);
        $this->assertEquals('req_123', $json['request_id']);
    }

    // ---------------------------------------------------------------
    // Show
    // ---------------------------------------------------------------

    public function testShowFound()
    {
        $user = $this->createTestUser();
        $mock = $this->mockManager();

        $mock->expects($this->once())
            ->method('find')
            ->with(42)
            ->willReturn(['id' => 42, 'name' => 'Test Entry']);

        $result = $this->withHeaders($this->getHeaders($user))->get('api/v1/entries/42');
        $result->assertStatus(200);
        $result->assertJSONFragment(['id' => 42]);
    }

    public function testShowNotFound()
    {
        $user = $this->createTestUser();
        $mock = $this->mockManager();

        $mock->expects($this->once())
            ->method('find')
            ->with(999)
            ->willReturn(false);

        $result = $this->withHeaders($this->getHeaders($user))->get('api/v1/entries/999');
        $result->assertStatus(404);
    }

    // ---------------------------------------------------------------
    // Create
    // ---------------------------------------------------------------

    public function testCreateAsSuperadmin()
    {
        $admin = $this->createSuperAdmin();
        $mock  = $this->mockManager();

        $mock->expects($this->once())->method('create')->willReturn(77);

        $data = [
            'model_id' => 1,
            'name'     => 'New Entry',
            'fields'   => json_encode(['title' => 'Hello']),
        ];

        $result = $this->withHeaders($this->getHeaders($admin))
            ->withBody(json_encode($data))
            ->post('api/v1/entries');

        $result->assertStatus(201);
        $result->assertJSONFragment(['id' => 77]);
    }

    public function testCreateValidationFails()
    {
        $admin = $this->createSuperAdmin();
        $mock  = $this->mockManager();

        $mock->expects($this->never())->method('create');

        // Missing model_id and fields
        $data = ['name' => 'AB']; // Too short and missing required fields

        $result = $this->withHeaders($this->getHeaders($admin))
            ->withBody(json_encode($data))
            ->post('api/v1/entries');

        $result->assertStatus(400);
    }

    public function testCreateAsUserForbidden()
    {
        $user = $this->createTestUser();
        $mock = $this->mockManager();

        $mock->expects($this->never())->method('create');

        $data = [
            'model_id' => 1,
            'name'     => 'New Entry',
            'fields'   => json_encode(['title' => 'Hello']),
        ];

        $result = $this->withHeaders($this->getHeaders($user))
            ->withBody(json_encode($data))
            ->post('api/v1/entries');

        $result->assertStatus(403);
        $result->assertJSONFragment(['error' => 'Access denied']);
    }

    // ---------------------------------------------------------------
    // Update
    // ---------------------------------------------------------------

    public function testUpdateAsSuperadmin()
    {
        $admin = $this->createSuperAdmin();
        $mock  = $this->mockManager();

        $mock->expects($this->once())
            ->method('find')
            ->with(42)
            ->willReturn(['id' => 42, 'name' => 'Old Name']);

        $mock->expects($this->once())->method('update');

        $result = $this->withHeaders($this->getHeaders($admin))
            ->withBody(json_encode(['name' => 'Updated Name']))
            ->put('api/v1/entries/42');

        $result->assertStatus(200);
    }

    public function testUpdateNotFound()
    {
        $admin = $this->createSuperAdmin();
        $mock  = $this->mockManager();

        $mock->expects($this->once())
            ->method('find')
            ->with(999)
            ->willReturn(false);

        $mock->expects($this->never())->method('update');

        $result = $this->withHeaders($this->getHeaders($admin))
            ->withBody(json_encode(['name' => 'Updated']))
            ->put('api/v1/entries/999');

        $result->assertStatus(404);
    }

    public function testUpdateAsUserForbidden()
    {
        $user = $this->createTestUser();
        $mock = $this->mockManager();

        $mock->expects($this->never())->method('update');

        $result = $this->withHeaders($this->getHeaders($user))
            ->withBody(json_encode(['name' => 'Updated']))
            ->put('api/v1/entries/42');

        $result->assertStatus(403);
        $result->assertJSONFragment(['error' => 'Access denied']);
    }

    // ---------------------------------------------------------------
    // Delete
    // ---------------------------------------------------------------

    public function testDeleteAsSuperadmin()
    {
        $admin = $this->createSuperAdmin();
        $mock  = $this->mockManager();

        $mock->expects($this->once())->method('deleteEntries');

        $result = $this->withHeaders($this->getHeaders($admin))->delete('api/v1/entries/42');

        $result->assertStatus(200);
    }

    public function testDeleteAsUserForbidden()
    {
        $user = $this->createTestUser();
        $mock = $this->mockManager();

        $mock->expects($this->never())->method('deleteEntries');

        $result = $this->withHeaders($this->getHeaders($user))->delete('api/v1/entries/42');

        $result->assertStatus(403);
        $result->assertJSONFragment(['error' => 'Access denied']);
    }
}
