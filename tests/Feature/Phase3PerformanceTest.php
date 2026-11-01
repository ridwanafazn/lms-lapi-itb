<?php

namespace Tests\Feature;

use App\Livewire\Student\Learning\Player;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Material;
use App\Models\Section;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Phase 3.1 — Performance guardrails for the learning player.
 */
class Phase3PerformanceTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $user = User::create([
            'name' => 'Admin Perf',
            'email' => 'admin-perf-'.uniqid().'@example.test',
            'password' => 'password',
        ]);

        $user->assignRole('admin');

        return $user;
    }

    /**
     * Course with four sections, each with a material lesson.
     *
     * @return array{0: Course, 1: array<int, Lesson>}
     */
    private function courseWithMaterials(int $sections = 4): array
    {
        $course = Course::create(['title' => 'Kursus Performa']);
        $lessons = [];

        foreach (range(1, $sections) as $index) {
            $section = Section::create([
                'course_id' => $course->id,
                'title' => 'Bab '.$index,
                'order_index' => $index,
            ]);

            $material = Material::create([
                'course_id' => $course->id,
                'title' => 'Materi '.$index,
                'content' => 'Isi materi '.$index,
                'type' => 'article',
                'status' => 'published',
            ]);

            $lessons[] = Lesson::create([
                'section_id' => $section->id,
                'item_type' => 'material',
                'material_id' => $material->id,
                'sort_order' => 1,
                'is_required' => true,
            ]);
        }

        return [$course, $lessons];
    }

    private function countQueries(callable $callback): int
    {
        $count = 0;
        DB::listen(function () use (&$count) {
            $count++;
        });

        $callback();

        return $count;
    }

    public function test_switching_lessons_does_not_reload_the_whole_course(): void
    {
        $this->actingAs($this->admin());
        [$course, $lessons] = $this->courseWithMaterials();

        $component = Livewire::withQueryParams(['lesson_id' => $lessons[0]->id])
            ->test(Player::class, ['course' => $course]);

        $queries = $this->countQueries(function () use ($component, $lessons) {
            $component->call('loadLesson', $lessons[2]->id);
        });

        // Guardrail: switching lessons must not re-query every section/lesson of the course.
        // Relations are batched per request (sections, lessons, materials), not per model.
        $this->assertLessThanOrEqual(
            10,
            $queries,
            "loadLesson() issued {$queries} queries; expected <= 10."
        );

        $component->assertSet('currentLesson.id', $lessons[2]->id);
    }

    public function test_loading_a_lesson_does_not_lazy_load_its_section_per_render(): void
    {
        $this->actingAs($this->admin());
        [$course, $lessons] = $this->courseWithMaterials();

        $component = Livewire::withQueryParams(['lesson_id' => $lessons[0]->id])
            ->test(Player::class, ['course' => $course]);

        $queries = $this->countQueries(function () use ($component) {
            $component->call('loadLesson', $component->get('currentLesson')->id);
        });

        $this->assertLessThanOrEqual(10, $queries, "Reloading the same lesson used {$queries} queries.");
    }

    public function test_query_count_stays_flat_as_more_sections_are_added(): void
    {
        $this->actingAs($this->admin());

        $measure = function (int $sections): int {
            [$course, $lessons] = $this->courseWithMaterials($sections);

            $component = Livewire::withQueryParams(['lesson_id' => $lessons[0]->id])
                ->test(Player::class, ['course' => $course]);

            return $this->countQueries(fn () => $component->call('loadLesson', $lessons[$sections - 1]->id));
        };

        $small = $measure(3);
        $large = $measure(12);

        $this->assertSame(
            $small,
            $large,
            "Query count grew with section count ({$small} -> {$large}); N+1 is back."
        );
    }

    public function test_reopening_the_same_lesson_is_cheaper_than_switching(): void
    {
        $this->actingAs($this->admin());
        [$course, $lessons] = $this->courseWithMaterials();

        $component = Livewire::withQueryParams(['lesson_id' => $lessons[1]->id])
            ->test(Player::class, ['course' => $course]);

        $switch = $this->countQueries(fn () => $component->call('loadLesson', $lessons[2]->id));
        $reopen = $this->countQueries(fn () => $component->call('loadLesson', $lessons[2]->id));

        $this->assertLessThan(
            $switch,
            $reopen,
            "Reopening the active lesson ({$reopen} queries) should be cheaper than switching ({$switch} queries)."
        );
    }

    public function test_program_lesson_count_reuses_preloaded_counts(): void
    {
        $program = \App\Models\Program::create(['title' => 'Program Perf', 'is_active' => true]);

        foreach (range(1, 4) as $index) {
            $course = Course::create(['title' => 'Course '.$index]);
            $program->courses()->attach($course->id, ['order_index' => $index]);

            Material::create([
                'course_id' => $course->id,
                'title' => 'M '.$index,
                'content' => 'C',
                'type' => 'article',
                'status' => 'published',
            ]);
        }

        $program->load(['courses' => fn ($query) => $query->withCount(['materials', 'quizzes', 'assignments'])]);
        $this->assertTrue($program->courses->every(fn ($course) => isset($course->materials_count)));

        $queries = $this->countQueries(function () use ($program) {
            $this->assertSame(4, $program->lessons_count);
        });

        $this->assertSame(0, $queries, "lessons_count issued {$queries} queries despite preloaded counts.");
    }

    public function test_program_lesson_count_falls_back_to_three_aggregate_queries(): void
    {
        $program = \App\Models\Program::create(['title' => 'Program Fallback', 'is_active' => true]);

        foreach (range(1, 4) as $index) {
            $course = Course::create(['title' => 'Course '.$index]);
            $program->courses()->attach($course->id, ['order_index' => $index]);
        }

        $queries = $this->countQueries(function () use ($program) {
            $this->assertSame(0, $program->lessons_count);
        });

        // One relation load + three aggregate counts, independent of course count.
        $this->assertLessThanOrEqual(
            4,
            $queries,
            "Fallback lessons_count issued {$queries} queries; it must be flat, not per course."
        );
    }

    public function test_progress_calculation_uses_a_single_completion_query(): void
    {
        $this->actingAs($this->admin());
        [$course] = $this->courseWithMaterials(5);

        $component = Livewire::test(Player::class, ['course' => $course]);

        $queries = $this->countQueries(function () use ($component) {
            $component->call('loadUserProgress');
        });

        // One completion query plus the quiz-pass lookups; must stay flat as sections grow.
        $this->assertLessThanOrEqual(
            8,
            $queries,
            "loadUserProgress() issued {$queries} queries without any quiz lessons."
        );
    }
}
