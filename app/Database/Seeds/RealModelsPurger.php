<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;
use CodeIgniter\CLI\CLI;
use CodeIgniter\Shield\Models\UserModel;
use StarDust\Services\ModelsManager;

class RealModelsPurger extends Seeder
{
    private const SEEDER_EMAIL = 'seeder@example.com';

    public function run()
    {
        /** @var ModelsManager $manager */
        $manager = service('modelsManager');

        // 1. Identify Seeder User
        $users = model(UserModel::class);
        $user  = $users->findByCredentials(['email' => self::SEEDER_EMAIL]);

        if (! $user) {
            if (is_cli()) {
                CLI::error("Seeder user not found. Nothing to purge.");
            }

            return;
        }

        if (is_cli()) {
            CLI::write("Purging data for user: " . $user->username, 'yellow');
        }

        // 2. Find and Delete Models in Chunks
        $modelsModel = model('StarDust\Models\ModelsModel');
        $chunkSize   = 100;
        $totalPurged = 0;

        do {
            // Fetch a chunk of IDs owned by the seeder user
            $ids = $modelsModel->builder()
                ->select('id')
                ->where('creator_id', $user->id)
                ->limit($chunkSize)
                ->get()
                ->getResultArray();

            $ids = array_column($ids, 'id');

            if (empty($ids)) {
                break;
            }

            $count = count($ids);
            if (is_cli()) {
                CLI::write("Processing chunk of {$count} models...", 'yellow');
            }

            try {
                // Soft Delete
                $manager->deleteModels($ids, $user->id);

                // Permanent Purge
                $purgedCount = $manager->purgeModels($ids);
                $totalPurged += $purgedCount;

                if (is_cli()) {
                    CLI::print('.');
                }
            } catch (\Exception $e) {
                if (is_cli()) {
                    CLI::error("Failed to purge chunk: " . $e->getMessage());
                }
            }

            // Clean up memory
            unset($ids);
        } while ($count >= $chunkSize); // Continue if we fetched a full chunk

        if (is_cli()) {
            CLI::newLine();
            CLI::write("Purge complete! Total purged: {$totalPurged}", 'green');
        }
    }
}
