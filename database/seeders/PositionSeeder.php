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
                
            ],
            [
                'job_name' => 'Designer UX/UI',
                'status' => 'active',
                'description' => 'Description du designer UX/UI',
                'title' => 'Mme',
                
            ],
            [
                'job_name' => 'Chef de Projet',
                'status' => 'inactive',
                'description' => 'Description du chef de projet',
                'title' => 'M.',
                
            ],
            [
                'job_name' => 'Manager',
                'status' => 'active',
                'description' => 'Description du manager',
                'title' => 'M.',
                
            ],
            [
                'job_name' => 'Ressources Humaines',
                'status' => 'active',
                'description' => 'Description des ressources humaines',
                'title' => 'Mme',
                
            ],
            [
                'job_name' => 'Gérant',
                'status' => 'inactive',
                'description' => 'Description du gérant',
                'title' => 'M.',
                
            ],
            [
                'job_name' => 'Administrateur',
                'status' => 'active',
                'description' => 'Description de l\'administrateur',
                'title' => 'M.',
                
            ],
        ];

        Position::insert($positions);
    }
}
