<?php

namespace Tests\Feature\Api\V1;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;

class ModelsFilterCountReproTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;
    protected $namespace   = 'App';

    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function migrateDatabase()
    {
        $runner = service('migrations');
        $runner->setGroup('tests');

        $runner->setNamespace('CodeIgniter\Settings');
        $runner->latest('tests');

        $runner->setNamespace('CodeIgniter\Shield');
        $runner->latest('tests');

        try {
            $runner->setNamespace('StarDust');
            $runner->latest('tests');
        } catch (\Throwable $e) {
            // StarDust migration issues should fail the test
            throw $e;
        }

        $runner->setNamespace('App');
        $runner->latest('tests');
    }

    private function createTestUser()
    {
        $users = model(UserModel::class);
        $user = new User([
            'username' => 'testuser',
            'email'    => 'test@example.com',
            'password' => 'password123',
        ]);
        $users->save($user);
        $user = $users->findById($users->getInsertID());
        return $user;
    }

    private function getHeaders($user)
    {
        return [
            'Authorization' => 'Bearer ' . $user->generateAccessToken('test-token')->raw_token
        ];
    }

    public function testIndexPaginationCountsWithFilter()
    {
        $user = $this->createTestUser();
        $manager = service('modelsManager');
        $userId = $user->id;

        // 1. Create 3 models
        $manager->create(['name' => 'Apple', 'fields' => json_encode([])], $userId);
        $manager->create(['name' => 'Banana', 'fields' => json_encode([])], $userId);
        $manager->create(['name' => 'Cherry', 'fields' => json_encode([])], $userId);

        // 2. Request with Filter for 'Apple'
        $result = $this->withHeaders($this->getHeaders($user))
            ->get('api/v1/models?q=Apple');

        // 3. Assertions
        $result->assertStatus(200);

        // This assertion is expected to FAIL currently (it will show total_items: 3)
        $result->assertJSONFragment([
            'meta' => [
                'code' => 200,
            ],
            'pagination' => [
                'total_items' => 1,
                'total_pages' => 1
            ]
        ]);
    }
}
