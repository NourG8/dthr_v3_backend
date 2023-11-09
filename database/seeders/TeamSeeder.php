<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Team;

class TeamSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $teams = [
            [
                'name' => 'Équipe 1',
                'description' => 'Description de l\'équipe 1',
                'is_deleted' => 0,
                'status' => 'Actif',
                'department_id' => 1, // Remplacez par l'ID réel du département
            ],
            [
                'name' => 'Équipe 2',
                'description' => 'Description de l\'équipe 2',
                'is_deleted' => 0,
                'status' => 'Actif',
                'department_id' => 2, // Remplacez par l'ID réel du département
            ],
            // Ajoutez d'autres équipes si nécessaire
        ];

        Team::insert($teams);
    }
    }
