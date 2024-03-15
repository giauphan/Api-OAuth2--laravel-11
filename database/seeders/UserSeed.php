<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserSeed extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user = User::create([
            'name' => 'test',
            'email' => 'test@test.example',
            'password' => Hash::make('12345678'),
        ]);
        $role = Role::updateOrCreate(['name' => 'Admin']);
        $user->assignRole($role);
    }
}
