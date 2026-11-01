<?php

namespace Database\Seeders;

use App\Enums\CourseStatus;
use App\Models\Assignment;
use App\Models\Batch;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Instructor;
use App\Models\Lesson;
use App\Models\Material;
use App\Models\Program;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Quiz;
use App\Models\Section;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Data demo untuk portofolio. Aman dijalankan berulang karena memakai
 * updateOrCreate dan firstOrCreate pada kunci alami seperti email dan judul.
 *
 * Jalankan perintah php artisan db:seed --class=PortfolioDemoSeeder
 */
class PortfolioDemoSeeder extends Seeder
{
    private const PASSWORD = 'password';

    public function run(): void
    {
        $this->call([RoleSeeder::class, MembershipPlanSeeder::class]);

        $admin = $this->makeUser('Demo Admin LAPI', 'demo.admin@lapi.test', 'admin', 'Manajer Akademik');
        $instructor = $this->makeUser('Demo Instruktur LAPI', 'demo.instruktur@lapi.test', 'instructor', 'Praktisi Data');
        $student = $this->makeUser('Demo Peserta LAPI', 'demo.peserta@lapi.test', 'student', 'Analis di Instansi Pemerintah');

        $program = Program::updateOrCreate(
            ['enrollment_code' => 'DEMO-DA-01'],
            [
                'title' => 'Data Analytics untuk Pengambilan Keputusan',
                'short_description' => 'Program demo untuk memperlihatkan alur belajar dari pendaftaran sampai sertifikat.',
                'description' => 'Peserta mempelajari dasar analisis data, menyusun visualisasi, lalu menyusun rekomendasi kebijakan berbasis data.',
                'price' => 1500000,
                'type' => 'cohort',
                'min_membership_level' => 'standard',
                'is_active' => true,
                'status' => 'published',
                'rating_avg' => 4.8,
                'what_is_included' => ['Materi daring', 'Kuis terstruktur', 'Tugas akhir', 'Sertifikat'],
            ]
        );

        $course = Course::updateOrCreate(
            ['course_code' => 'DEMO-CRS-DA'],
            [
                'title' => 'Dasar Analisis Data untuk Kebijakan',
                'description' => 'Kelas demo berisi materi, kuis, dan tugas agar seluruh alur belajar dapat dicoba.',
                'level' => 'beginner',
                'language' => 'id',
                'delivery_mode' => 'cohort_based',
                'status' => CourseStatus::PUBLISHED,
                'published_at' => now(),
                'is_sequential' => true,
                'instructor_id' => $instructor->id,
                'topics' => ['Analisis Data', 'Visualisasi'],
                'tools_used' => ['Spreadsheet', 'SQL'],
                'target_audience' => ['Analis', 'Perencana'],
                'prerequisites' => ['Mampu memakai spreadsheet'],
                'learning_outcomes' => ['Membaca data', 'Menyusun rekomendasi'],
                'curriculum_summary' => 'Dua bab, satu kuis, satu tugas.',
            ]
        );

        $program->courses()->syncWithoutDetaching([$course->id => ['order_index' => 1]]);

        $this->attachInstructor($program, $instructor, 'Pengampu Program');
        $this->attachInstructor($course, $instructor, 'Instruktur Utama');

        [$materialLesson, $quizLesson, $assignmentLesson] = $this->makeCurriculum($course);

        $batch = Batch::firstOrCreate(
            ['program_id' => $program->id, 'batch_name' => 'Angkatan Demo 1'],
            [
                'course_id' => $course->id,
                'price' => 1500000,
                'min_membership_level' => 'standard',
                'quota' => 30,
                'location' => 'Daring',
                'status' => 'open',
                'registration_deadline' => ['date' => now()->addDays(14)->toDateString()],
                'start_date' => ['date' => now()->addDays(21)->toDateString()],
                'end_date' => ['date' => now()->addDays(60)->toDateString()],
            ]
        );

        $this->attachInstructor($batch, $instructor, 'Instruktur Kelas');

        Enrollment::firstOrCreate(
            [
                'user_id' => $student->id,
                'enrollable_type' => Program::class,
                'enrollable_id' => $program->id,
            ],
            [
                'enrolled_at' => now(),
                'status' => 'active',
                'access_expires_at' => now()->addYear(),
                'progress_percentage' => 0,
            ]
        );

        $this->command?->info('Demo siap dipakai.');
        $this->command?->table(
            ['Peran', 'Email', 'Kata sandi'],
            [
                ['Admin', $admin->email, self::PASSWORD],
                ['Instruktur', $instructor->email, self::PASSWORD],
                ['Peserta', $student->email, self::PASSWORD],
            ]
        );
        $this->command?->line('Program demo berisi '.$materialLesson->sort_order.' materi, 1 kuis, dan 1 tugas.');
        $this->command?->line('Batch demo bernama '.$batch->batch_name.' dengan kuota '.$batch->quota.'.');
        $this->command?->line('Kuis demo memiliki soal pilihan ganda dan soal esai pada lesson urutan '.$quizLesson->sort_order.'.');
        $this->command?->line('Tugas demo ada pada lesson urutan '.$assignmentLesson->sort_order.'.');
    }

    private function makeUser(string $name, string $email, string $role, string $agency): User
    {
        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make(self::PASSWORD),
                'email_verified_at' => now(),
                'is_active' => true,
            ]
        );

        $user->syncRoles([$role]);

        UserProfile::updateOrCreate(
            ['user_id' => $user->id],
            [
                'full_name' => $name,
                'agency_name' => $agency,
                'phone_number' => '081200000000',
                'position' => $agency,
            ]
        );

        return $user;
    }

    private function attachInstructor(Program|Course|Batch $target, User $instructor, string $roleLabel): void
    {
        Instructor::updateOrCreate(
            [
                'user_id' => $instructor->id,
                'instructor_type' => $target::class,
                'instructor_id' => $target->id,
            ],
            [
                'is_primary' => true,
                'role_label' => $roleLabel,
            ]
        );
    }

    /**
     * @return array{0: Lesson, 1: Lesson, 2: Lesson}
     */
    private function makeCurriculum(Course $course): array
    {
        $sectionOne = Section::firstOrCreate(
            ['course_id' => $course->id, 'title' => 'Bab 1 Fondasi Data'],
            ['description' => 'Mengenal jenis data dan cara membacanya.', 'order_index' => 1]
        );

        $sectionTwo = Section::firstOrCreate(
            ['course_id' => $course->id, 'title' => 'Bab 2 Membaca Temuan'],
            ['description' => 'Menyusun kesimpulan dari data yang sudah diolah.', 'order_index' => 2]
        );

        $material = Material::firstOrCreate(
            ['course_id' => $course->id, 'title' => 'Mengenal Jenis Data'],
            [
                'type' => 'article',
                'body_text' => 'Data kuantitatif berbentuk angka sedangkan data kualitatif berbentuk uraian. Keduanya dipakai untuk menjawab pertanyaan kebijakan yang berbeda.',
                'status' => 'published',
            ]
        );

        $videoMaterial = Material::firstOrCreate(
            ['course_id' => $course->id, 'title' => 'Menyusun Kesimpulan'],
            [
                'type' => 'video',
                'content_url' => 'https://example.com/video-demo',
                'status' => 'published',
            ]
        );

        $materialLesson = Lesson::firstOrCreate(
            ['section_id' => $sectionOne->id, 'material_id' => $material->id],
            ['sort_order' => 1, 'item_type' => 'material', 'is_required' => true]
        );

        Lesson::firstOrCreate(
            ['section_id' => $sectionTwo->id, 'material_id' => $videoMaterial->id],
            ['sort_order' => 2, 'item_type' => 'material', 'is_required' => true]
        );

        $quiz = Quiz::firstOrCreate(
            ['course_id' => $course->id, 'title' => 'Kuis Pemahaman Data'],
            [
                'duration_minutes' => 15,
                'passing_grade' => 70,
                'is_randomized' => false,
                'max_attempts' => 3,
                'status' => 'active',
            ]
        );

        $choice = Question::firstOrCreate(
            ['quiz_id' => $quiz->id, 'question_text' => 'Data yang berbentuk angka disebut data apa'],
            ['question_type' => 'multiple_choice', 'score_weight' => 1]
        );

        QuestionOption::firstOrCreate(
            ['question_id' => $choice->id, 'option_text' => 'Kuantitatif'],
            ['is_correct' => true, 'order_index' => 1]
        );

        QuestionOption::firstOrCreate(
            ['question_id' => $choice->id, 'option_text' => 'Kualitatif'],
            ['is_correct' => false, 'order_index' => 2]
        );

        Question::firstOrCreate(
            ['quiz_id' => $quiz->id, 'question_text' => 'Jelaskan satu contoh penggunaan data untuk mengambil keputusan'],
            ['question_type' => 'essay', 'score_weight' => 2]
        );

        $quizLesson = Lesson::firstOrCreate(
            ['section_id' => $sectionTwo->id, 'quiz_id' => $quiz->id],
            ['sort_order' => 3, 'item_type' => 'quiz', 'is_required' => true]
        );

        $assignment = Assignment::firstOrCreate(
            ['course_id' => $course->id, 'title' => 'Tugas Akhir Analisis Sederhana'],
            [
                'description' => 'Unggah ringkasan analisis satu halaman beserta rekomendasi.',
                'deadline_days' => 7,
            ]
        );

        $assignmentLesson = Lesson::firstOrCreate(
            ['section_id' => $sectionTwo->id, 'assignment_id' => $assignment->id],
            ['sort_order' => 4, 'item_type' => 'assignment', 'is_required' => true]
        );

        return [$materialLesson, $quizLesson, $assignmentLesson];
    }
}
