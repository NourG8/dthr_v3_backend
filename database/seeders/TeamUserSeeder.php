<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\TeamUser;

class TeamUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $userTeams = [
            [
                'is_leader' => 1,
                'is_deleted' => 0,
                'integration_date' => '2022-11-08', // Remplacez par la date d'intégration réelle
                'user_id' => 1, // Remplacez par l'ID réel de l'utilisateur
                'team_id' => 1, // Remplacez par l'ID réel de l'équipe
            ],
            [
                'is_leader' => 0,
                'is_deleted' => 0,
                'integration_date' => '2022-11-08', // Remplacez par la date d'intégration réelle
                'user_id' => 2, // Remplacez par l'ID réel de l'utilisateur
                'team_id' => 2, // Remplacez par l'ID réel de l'équipe
            ]
        ];

        TeamUser::insert($userTeams);
    }
}
