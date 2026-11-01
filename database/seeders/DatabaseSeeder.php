<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // [WAJIB] Jalankan RoleSeeder dulu agar tabel 'roles' terisi.
        // Jika ini dihapus, script di bawah akan error saat assignRole.
        $this->call(RoleSeeder::class);

        $passwordDefault = Hash::make('password'); // Password seragam

        // ---------------------------------------------------------
        // 1. BUAT ADMIN (admin@lms.com)
        // ---------------------------------------------------------
        $admin = User::create([
            'name' => 'Super Admin',
            'email' => 'admin@lms.com',
            'password' => $passwordDefault,
            'email_verified_at' => now(),
        ]);
        $admin->assignRole('admin');

        UserProfile::create([
            'user_id' => $admin->id,
            'full_name' => 'Administrator Sistem',
            'agency_name' => 'LMS Admin'
        ]);

        // ---------------------------------------------------------
        // 2. BUAT INSTRUKTUR (pemateri@lms.com)
        // ---------------------------------------------------------
        $instructor = User::create([
            'name' => 'Pemateri Utama',
            'email' => 'pemateri@lms.com',
            'password' => $passwordDefault,
            'email_verified_at' => now(),
        ]);
        $instructor->assignRole('instructor');

        UserProfile::create([
            'user_id' => $instructor->id,
            'full_name' => 'Pemateri Utama',
            'agency_name' => 'Expert Instructor'
        ]);

        // ---------------------------------------------------------
        // 3. BUAT SISWA SESUAI LIST (ridwan, giras, rey, dll)
        // ---------------------------------------------------------
        $studentsList = [
            'ridwan',
            'giras',
            'rey',
            'jihad',
            'yosep',
            'reza',
            'kinan',
            'alika',
            'kamila'
        ];

        foreach ($studentsList as $name) {
            // Buat User
            $student = User::create([
                'name' => ucfirst($name), // Huruf pertama kapital
                'email' => $name . '@lms.com',
                'password' => $passwordDefault,
                'email_verified_at' => now(),
            ]);

            // Assign Role Student
            $student->assignRole('student');

            // Buat Profile Dummy
            UserProfile::create([
                'user_id' => $student->id,
                'full_name' => ucfirst($name),
                'agency_name' => 'Peserta Bootcamp'
            ]);
        }
    }
}
