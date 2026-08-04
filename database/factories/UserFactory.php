<?php

namespace Database\Factories;

use App\Enums\UserStatus;
use App\Models\User;
use App\Models\UserProfile;
use App\Models\UserSetting;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'username' => fake()->unique()->userName(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'terms_accepted_at' => now(),
            'profile_setup_completed_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'status' => UserStatus::Active,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (User $user): void {
            UserProfile::query()->firstOrCreate(
                ['user_id' => $user->id],
                [
                    'display_name' => $user->username,
                    'locale' => 'fr',
                    'theme' => 'system',
                ],
            );

            UserSetting::query()->firstOrCreate(
                ['user_id' => $user->id],
                [
                    'notifications_enabled' => true,
                    'notify_messages' => true,
                    'notify_devices' => true,
                    'notify_security' => true,
                    'hide_message_previews' => false,
                    'silent_mode' => false,
                    'send_read_receipts' => true,
                    'show_last_seen' => true,
                    'auto_download_media' => false,
                ],
            );

            if (! $user->hasAnyRole(['user', 'admin'])) {
                $user->assignRole('user');
            }
        });
    }

    public function admin(): static
    {
        return $this->afterCreating(function (User $user): void {
            $user->syncRoles(['admin']);
        })->state(fn (array $attributes) => [
            'status' => UserStatus::Active,
            'email_verified_at' => now(),
        ]);
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
            'status' => UserStatus::Pending,
        ]);
    }

    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => UserStatus::Suspended,
        ]);
    }
}
