<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Position;

class PositionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $positions = [
            [
                'job_name' => 'Développeur Web',
                'status' => 'active',
                'description' => 'Description du développeur web',
                'title' => 'Mr',
                'is_deleted' => 0,
            ],
            [
                'job_name' => 'Designer UX/UI',
                'status' => 'active',
                'description' => 'Description du designer UX/UI',
                'title' => 'Mme',
                'is_deleted' => 0,
            ],
            [
                'job_name' => 'Chef de Projet',
                'status' => 'Inactive',
                'description' => 'Description du chef de projet',
                'title' => 'M.',
                'is_deleted' => 0,
            ],
            [
                'job_name' => 'Manager',
                'status' => 'active',
                'description' => 'Description du manager',
                'title' => 'M.',
                'is_deleted' => 0,
            ],
            [
                'job_name' => 'Ressources Humaines',
                'status' => 'active',
                'description' => 'Description des ressources humaines',
                'title' => 'Mme',
                'is_deleted' => 0,
            ],
            [
                'job_name' => 'Gérant',
                'status' => 'Inactive',
                'description' => 'Description du gérant',
                'title' => 'M.',
                'is_deleted' => 0,
            ],
            [
                'job_name' => 'Administrateur',
                'status' => 'active',
                'description' => 'Description de l\'administrateur',
                'title' => 'M.',
                'is_deleted' => 0,
            ],
        ];

        Position::insert($positions);
    }
}
