<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;
use CodeIgniter\CLI\CLI;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;
use Faker\Factory;

class RealModelsSeeder extends Seeder
{
    private const SEEDER_USERNAME = 'seeder_bot';
    private const SEEDER_EMAIL    = 'seeder@example.com';

    public function run()
    {
        $faker = Factory::create();
        $manager = service('modelsManager');

        // 1. Ensure Seeder User Exists
        $user = $this->getSeederUser();

        // 2. Generate Realistic Models
        $totalModels = 12;
        if (is_cli()) {
            CLI::write("Seeding {$totalModels} models...", 'yellow');
        }

        // Predictable Names for Search Testing
        $predictableNames = [
            'Project Alpha',
            'Project Beta',
            'Gamma Ray',
            'Delta Force',
            'Omega Protocol'
        ];

        for ($i = 0; $i < $totalModels; $i++) {
            // Use predictable name for first few, random for rest
            if (isset($predictableNames[$i])) {
                $name = $predictableNames[$i];
            } else {
                $name = ucwords($faker->words(3, true));
            }

            // Generate realistic fields JSON
            $fields = $this->generateRealisticFields($faker);

            $data = [
                'name'   => $name,
                'fields' => json_encode($fields)
            ];

            try {
                // Determine user ID
                $userId = $user->id;

                $modelId = $manager->create($data, $userId);

                // Determine Date Strategy
                $isCreatedOneYearAgo = ($i === 0); // First model: 1 year ago
                $isCreatedNow = ($i === $totalModels - 1); // Last model: Now

                // For others: random past date
                // For 50% of models, updated_at > created_at
                $hasSeparateUpdate = ($i % 2 === 0);

                $this->backdateModel($modelId, $faker, $isCreatedOneYearAgo, $isCreatedNow, $hasSeparateUpdate);

                if (is_cli()) {
                    CLI::print('.');
                }
            } catch (\Exception $e) {
                if (is_cli()) {
                    CLI::error("Failed to seed model: " . $e->getMessage());
                }
            }
        }

        if (is_cli()) {
            CLI::newLine();
            CLI::write("Done!", 'green');
        }
    }

    private function getSeederUser()
    {
        $users = model(UserModel::class);
        $user = $users->findByCredentials(['email' => self::SEEDER_EMAIL]);

        if ($user) {
            return $user;
        }

        $user = new User([
            'username' => self::SEEDER_USERNAME,
            'email'    => self::SEEDER_EMAIL,
            'password' => 'SeederPassword123!',
            'active'   => 1,
        ]);

        $users->save($user);
        return $users->findById($users->getInsertID());
    }

    private function generateRealisticFields($faker): array
    {
        $fields = [];
        $numFields = $faker->numberBetween(2, 8);

        for ($j = 0; $j < $numFields; $j++) {
            $type = $faker->randomElement(['text', 'textarea', 'number', 'boolean', 'date']);
            $fields[] = [
                'id'    => $faker->uuid,
                'type'  => $type,
                'label' => ucwords($faker->words(2, true)),
                'required' => $faker->boolean(30),
            ];
        }

        return $fields;
    }

    private function backdateModel(int $modelId, $faker, bool $isOneYearAgo = false, bool $isNow = false, bool $separateUpdate = false)
    {
        $db = \Config\Database::connect();
        $modelsModel = model('StarDust\Models\ModelsModel');

        if ($isNow) {
            // Leave as IS (Now)
            return;
        }

        if ($isOneYearAgo) {
            // Exactly 1 year ago
            $createdAt = date('Y-m-d H:i:s', strtotime('-1 year'));
        } else {
            // Random between 1 year ago and 1 month ago (to allow space for updates)
            $createdAt = $faker->dateTimeBetween('-1 year', '-1 month')->format('Y-m-d H:i:s');
        }

        // Determine UpdatedAt
        if ($separateUpdate) {
            // Updated sometime after creation, but before now
            // We use simple logic: created + random days
            $baseTimestamp = strtotime($createdAt);
            $daysToAdd = $faker->numberBetween(1, 25);
            $updatedAt = date('Y-m-d H:i:s', strtotime("+$daysToAdd days", $baseTimestamp));

            // Ensure we don't go into future (unlikely given -1 month cap, but safe check)
            if (strtotime($updatedAt) > time()) {
                $updatedAt = date('Y-m-d H:i:s');
            }
        } else {
            $updatedAt = $createdAt;
        }

        // Update Models Table (created_at)
        $db->table($modelsModel->table)->where('id', $modelId)->update([
            'created_at' => $createdAt,
            // We don't touch updated_at of the parent model usually, or maybe we do?
            // CodeIgniter Models auto-update 'updated_at' on save.
            // But here we are forcing history.
            'updated_at' => $updatedAt
        ]);

        // Update Model Data (current version)
        // In StarDust, model_data.created_at IS the version timestamp.
        // If we want to simulate "Updated Later", the current version should be created at $updatedAt.
        $db->table('model_data')->where('model_id', $modelId)->update([
            'created_at' => $updatedAt
        ]);
    }
}
