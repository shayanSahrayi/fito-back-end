<?php

namespace App\Domain\Ai;

final class FakeLanguageModel implements LanguageModel
{
    public function reply(string $intent, array $context): string
    {
        if ($intent === 'workout_program') {
            return json_encode([
                'title' => 'پیشنهاد تمرین',
                'days' => [[
                    'position' => 0,
                    'title' => 'شنبه',
                    'weekday' => 0,
                    'exercises' => [[
                        'position' => 0,
                        'exercise_id' => $context['exercise_id'],
                        'tracking_type' => 'weight_reps',
                        'prescribed_sets' => 3,
                        'reps_min' => 8,
                        'reps_max' => 10,
                    ]],
                ]],
            ], JSON_UNESCAPED_UNICODE);
        }

        if ($intent === 'meal_plan') {
            return json_encode([
                'title' => 'پیشنهاد غذا',
                'days' => [[
                    'position' => 0,
                    'title' => 'شنبه',
                    'meals' => [[
                        'position' => 0,
                        'meal_type' => 'lunch',
                        'title' => 'ناهار',
                        'items' => [[
                            'position' => 0,
                            'food_id' => $context['food_id'],
                            'quantity_g' => 100,
                        ]],
                    ]],
                ]],
            ], JSON_UNESCAPED_UNICODE);
        }

        if ($intent === 'progress_review') {
            return json_encode(['summary' => 'مرور آزمایشی'], JSON_UNESCAPED_UNICODE);
        }

        return 'پاسخ آزمایشی';
    }

    public function name(): string
    {
        return 'fake';
    }
}
