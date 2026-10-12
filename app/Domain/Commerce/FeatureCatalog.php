<?php

namespace App\Domain\Commerce;

final class FeatureCatalog
{
    /**
     * @var list<string>
     */
    public const KEYS = [
        'workout_programs',
        'nutrition_programs',
        'body_analysis',
        'ai_coach_assistant',
        'ai_athlete_access',
        'progress_reports',
    ];

    /**
     * @var list<string>
     */
    public const AI = [
        'ai_coach_assistant',
        'ai_athlete_access',
    ];

    /**
     * @param  list<string>  $keys
     */
    public static function assertValid(array $keys): void
    {
        foreach ($keys as $key) {
            if (! in_array($key, self::KEYS, true)) {
                throw new CommerceException('invalid_feature', 'قابلیت پلن شناخته‌شده نیست.');
            }
        }
    }

    /**
     * @param  list<string>  $keys
     */
    public static function assertQuota(?int $quota, array $keys): void
    {
        if (array_intersect($keys, self::AI) !== [] && ($quota === null || $quota < 1)) {
            throw new CommerceException('ai_quota_required', 'برای قابلیت هوش مصنوعی سهمیه لازم است.');
        }
    }
}
