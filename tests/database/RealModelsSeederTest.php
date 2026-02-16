<?php

namespace Tests\Database;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use App\Database\Seeds\RealModelsSeeder;
use App\Database\Seeds\RealModelsPurger;
use StarDust\Models\ModelsModel;
use StarDust\Models\ModelDataModel;

class RealModelsSeederTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    // We want to run migrations to ensure schema is fresh
    protected $migrate = true;
    protected $migrateOnce = false;
    protected $refresh = true;

    // We don't want to run the seeder automatically in setup, we want to test it explicitly
    // so we leave $seed empty or rely on manual seeding in methods
    protected $seed = '';

    // Run migrations for all namespaces (App and StarDust)
    protected $namespace = null;

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function testSeederAndPurgerCycle()
    {
        // Use string service/model loading if verifying class existence is tricky, but class imports are cleaner
        // Assuming StarDust is autoloaded
        $modelsModel = model(ModelsModel::class);
        $modelDataModel = model(ModelDataModel::class);

        // 1. Initial State
        $initialCount = $modelsModel->countAllResults();
        $this->assertSame(0, $initialCount, 'Database should be empty initially');

        // 2. Run Seeder
        $this->seed(RealModelsSeeder::class);

        // 3. Verify Seeding
        $seededCount = $modelsModel->countAllResults();
        $this->assertSame(12, $seededCount, 'Seeder should create exactly 12 models');

        // Verify Data Integrity of a random model
        // fetch one
        $randomModel = $modelsModel->orderBy('id', 'ASC')->first(); // First or random is fine
        $this->assertNotEmpty($randomModel, 'Should be able to fetch a model');

        // Check current_model_data_id linkage
        $this->assertNotEmpty($randomModel['current_model_data_id']);
        $modelData = $modelDataModel->find($randomModel['current_model_data_id']);
        $this->assertIsArray($modelData);
        $this->assertSame($randomModel['id'], $modelData['model_id']);

        // Check JSON fields
        $fields = json_decode($modelData['fields'], true);
        $this->assertIsArray($fields, 'Fields should be valid JSON array');
        $this->assertNotEmpty($fields);
        $this->assertArrayHasKey('id', $fields[0], 'Field definition needs id');
        $this->assertArrayHasKey('type', $fields[0], 'Field definition needs type');

        // 4. Create "Safe" Data (Bystander)
        // This simulates another user's data that should NOT be touched
        $manager = service('modelsManager');
        $bystanderId = 999;
        $safeModelId = $manager->create([
            'name' => 'Safe Model',
            'fields' => json_encode([['id' => 'f1', 'type' => 'text']])
        ], $bystanderId);

        $this->seeInDatabase($modelsModel->table, ['id' => $safeModelId]);

        // 5. Run Purger
        $this->seed(RealModelsPurger::class);

        // 6. Verify Purge
        // Purger now uses purgeModels($ids), which hard deletes.
        // So count should be 1 (The Safe Model).
        $finalCount = $modelsModel->withDeleted()->countAllResults();
        $this->assertSame(1, $finalCount, 'Purger should remove only seeder models');

        $this->seeInDatabase($modelsModel->table, ['id' => $safeModelId]);
        $this->dontSeeInDatabase($modelsModel->table, ['id' => $randomModel['id']]);

        // Cleanup Safe Model
        $manager->purgeModels([$safeModelId]);
    }
}
