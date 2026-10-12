<?php

namespace App\Domain\Ai;

use App\Models\PlatformAiProvider;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;

final class PlatformProvider
{
    /**
     * @return array<string, mixed>
     */
    public function show(): array
    {
        $row = $this->current();

        if ($row === null) {
            return [
                'configured' => false,
                'provider' => null,
                'model_name' => null,
                'base_url' => null,
                'key_hint' => null,
            ];
        }

        return $this->present($row);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function save(User $admin, array $data): array
    {
        $provider = (string) ($data['provider'] ?? '');

        if (! in_array($provider, ['openai', 'anthropic'], true)) {
            throw new AiException('invalid_provider', 'ارائه‌دهنده مدل معتبر نیست.');
        }

        $model = trim((string) ($data['model_name'] ?? ''));

        if ($model === '' || mb_strlen($model) > 120) {
            throw new AiException('invalid_model', 'نام مدل معتبر نیست.');
        }

        $base = trim((string) ($data['base_url'] ?? ''));
        $base = $base === '' ? null : $base;

        if ($base !== null && mb_strlen($base) > 255) {
            throw new AiException('invalid_provider', 'آدرس پایه معتبر نیست.');
        }

        $current = $this->current();
        $secret = trim((string) ($data['api_key'] ?? ''));

        if ($secret === '') {
            if ($current === null) {
                throw new AiException('api_key_required', 'کلید API لازم است.');
            }

            $encrypted = $current->api_key_encrypted;
        } else {
            if (mb_strlen($secret) > 500) {
                throw new AiException('invalid_provider', 'کلید API معتبر نیست.');
            }

            $encrypted = Crypt::encryptString($secret);
        }

        if ($current === null) {
            $current = PlatformAiProvider::query()->create([
                'provider' => $provider,
                'model_name' => $model,
                'base_url' => $base,
                'api_key_encrypted' => $encrypted,
                'updated_by_user_id' => $admin->id,
            ]);
        } else {
            $current->fill([
                'provider' => $provider,
                'model_name' => $model,
                'base_url' => $base,
                'api_key_encrypted' => $encrypted,
                'updated_by_user_id' => $admin->id,
            ])->save();
        }

        return $this->present($current);
    }

    public function secret(): ?string
    {
        $row = $this->current();

        if ($row === null) {
            return null;
        }

        return Crypt::decryptString($row->api_key_encrypted);
    }

    public function current(): ?PlatformAiProvider
    {
        return PlatformAiProvider::query()->orderBy('id')->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function present(PlatformAiProvider $row): array
    {
        $secret = Crypt::decryptString($row->api_key_encrypted);

        return [
            'configured' => true,
            'provider' => $row->provider,
            'model_name' => $row->model_name,
            'base_url' => $row->base_url,
            'key_hint' => substr($secret, -4),
        ];
    }
}
