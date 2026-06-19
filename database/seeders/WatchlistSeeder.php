<?php

namespace Database\Seeders;

use App\Models\Profile;
use App\Models\ProfileSnapshot;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class WatchlistSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $chunkSize = 100;
        $totalProfiles = 1200;
        $snapshotsPerProfile = 13; // 1200 * 13 = 15,600 snapshots

        $this->command->info("Seeding {$totalProfiles} profiles and snapshot history...");

        DB::beginTransaction();
        try {
            for ($i = 0; $i < $totalProfiles; $i += $chunkSize) {
                $profilesData = [];
                for ($j = 0; $j < $chunkSize; $j++) {
                    $username = 'user_'.($i + $j + 1).'_'.rand(100, 999);
                    $profilesData[] = [
                        'username' => $username,
                        'platform' => 'instagram',
                        'followers_count' => rand(1000, 50000),
                        'following_count' => rand(100, 1000),
                        'posts_count' => rand(10, 500),
                        'bio' => "This is seeded bio for {$username}",
                        'profile_picture_url' => 'https://picsum.photos/150',
                        'status' => 'fetched',
                        'last_refreshed_at' => now()->subHours(rand(0, 72)),
                        'created_at' => now()->subDays(15),
                        'updated_at' => now()->subDays(15),
                    ];
                }

                Profile::insert($profilesData);
            }

            $profileIds = Profile::pluck('id')->toArray();

            $snapshotsData = [];
            foreach ($profileIds as $profileId) {
                $followers = rand(1000, 40000);
                $following = rand(100, 1000);
                $posts = rand(10, 500);

                for ($k = 0; $k < $snapshotsPerProfile; $k++) {
                    $followers += rand(-50, 200);
                    $snapshotsData[] = [
                        'profile_id' => $profileId,
                        'followers_count' => $followers,
                        'following_count' => $following,
                        'posts_count' => $posts,
                        'created_at' => now()->subDays(14 - $k),
                    ];

                    if (count($snapshotsData) >= 1000) {
                        ProfileSnapshot::insert($snapshotsData);
                        $snapshotsData = [];
                    }
                }
            }

            if (count($snapshotsData) > 0) {
                ProfileSnapshot::insert($snapshotsData);
            }

            DB::commit();
            $this->command->info('Seeding completed successfully!');
        } catch (\Exception $e) {
            DB::rollBack();
            $this->command->error('Seeding failed: '.$e->getMessage());
        }
    }
}
