<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\PositionUser;

class PositionUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $userPositions = [
            [
                'start_date' => '2022-01-01',
                'end_date' => '2023-12-08',
                'position_id' => 1, // Remplacez par l'ID réel de l'utilisateur
                'user_id' => 1, // Remplacez par l'ID réel de l'équipe
            ],
            [
                'start_date' => '2000-02-07',
                'end_date' => '2022-07-08',
                'position_id' => 2, // Remplacez par l'ID réel de l'utilisateur
                'user_id' => 2, // Remplacez par l'ID réel de l'équipe
            ],
        ];

        PositionUser::insert($userPositions);
    }
}
