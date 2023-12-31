<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Department;

class DepartmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $departments = [
            [
                'department_name' => 'Département des Ressources Humaines',
                'description' => 'Description du département RH',
                'status' => 'active',
                'department_chief' => 1, // Remplacez par l'ID du chef de département
                
            ],
            [
                'department_name' => 'Département de Développement',
                'description' => 'Description du département de développement',
                'status' => 'active',
                'department_chief' => 2, // Remplacez par l'ID du chef de département
                
            ]
        ];

        Department::insert($departments);
    }
}
