<?php

namespace Database\Seeders;

use App\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {

    $this->call(UserSeeder::class);
    $this->call(PositionSeeder::class);
    $this->call(DepartmentSeeder::class);
    $this->call(TeamSeeder::class);
    $this->call(TeamUserSeeder::class);
    $this->call(PositionUserSeeder::class);

     // Création de rôles
     $adminRole = Role::create(['name' => 'admin']);
     $userRole = Role::create(['name' => 'user']);

     // Création de permissions
     $createPostPermission = Permission::create(['name' => 'create post']);
     $editPostPermission = Permission::create(['name' => 'edit post']);

     // Attribuer des permissions aux rôles
     $adminRole->givePermissionTo($createPostPermission, $editPostPermission);
     $userRole->givePermissionTo($createPostPermission);

     // Attribuer des rôles aux utilisateurs (utilisez votre propre modèle User)
     $adminUser = User::first();
     $adminUser->assignRole('admin');

    //  $regularUser = User::where('email', 'user@example.com')->first();
    //  $regularUser->assignRole('user');
    }
}
