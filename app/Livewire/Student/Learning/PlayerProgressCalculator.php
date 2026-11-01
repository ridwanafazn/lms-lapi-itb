<?php

namespace App\Livewire\Student\Learning;

use App\Services\QuizService;
use Illuminate\Support\Facades\DB;

class PlayerProgressCalculator
{
    public static function progressForUserAndCourse($user, $course): array
    {
        if (! $user || ! $course) {
            return [
                'total_lessons' => 0,
                'completed_ids' => [],
                'overall_progress' => 0,
                'section_progress' => [],
            ];
        }

        $allLessons = $course->sections->flatMap->lessons;
        $allLessonIds = $allLessons->pluck('id');
        $totalLessons = $allLessonIds->count();

        $completedIds = DB::table('lesson_completions')
            ->where('user_id', $user->id)
            ->whereIn('lesson_id', $allLessonIds)
            ->pluck('lesson_id')
            ->toArray();

        $quizService = app(QuizService::class);
        $completedIds = array_values(array_filter($completedIds, function ($id) use ($allLessons, $user, $quizService) {
            $lesson = $allLessons->firstWhere('id', $id);

            return ! $lesson->quiz_id || ($lesson->quiz && $quizService->hasPassed($user, $lesson->quiz, null));
        }));

        $totalCompleted = count($completedIds);
        $overallProgress = $totalLessons > 0 ? round(($totalCompleted / $totalLessons) * 100) : 0;

        $sectionProgress = [];
        foreach ($course->sections as $section) {
            $sLessons = $section->lessons->pluck('id')->toArray();
            $sCount = count($sLessons);
            $sCompleted = count(array_intersect($sLessons, $completedIds));
            $sectionProgress[$section->id] = $sCount > 0 ? round(($sCompleted / $sCount) * 100) : 0;
        }

        return [
            'total_lessons' => $totalLessons,
            'completed_ids' => $completedIds,
            'overall_progress' => $overallProgress,
            'section_progress' => $sectionProgress,
        ];
    }
}
