<?php

namespace App\Domains\Auth\Rules;

use App\Domains\Auth\Models\User;
use App\Domains\Auth\Services\UserService;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Facades\Hash;

/**
 * Class UnusedPassword.
 */
class UnusedPassword implements Rule
{
    /**
     * @var
     */
    protected $user;

    /**
     * Create a new rule instance.
     *
     * UnusedPassword constructor.
     *
     * @param $user
     */
    public function __construct($user)
    {
        $this->user = $user;
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value): bool
    {
        // Option is off
        if (! config('base.access.user.password_history')) {
            return true;
        }

        if (! $this->user instanceof User) {
            if (is_numeric($this->user)) {
                $this->user = resolve(UserService::class)->getById($this->user);
            } else {
                $this->user = resolve(UserService::class)->getByColumn($this->user, 'email');
            }
        }

        if (! $this->user || null === $this->user) {
            return false;
        }

        $histories = $this->user
            ->passwordHistories()
            ->take(config('base.access.user.password_history'))
            ->orderBy('id', 'desc')
            ->get();

        foreach ($histories as $history) {
            if (Hash::check($value, $history->password)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message(): string
    {
        return __('Bạn không thể đặt mật khẩu mà bạn đã sử dụng trước đó trong vòng :num lần gần đây nhất.', [
            'num' => config('base.access.user.password_history'),
        ]);
    }
}
