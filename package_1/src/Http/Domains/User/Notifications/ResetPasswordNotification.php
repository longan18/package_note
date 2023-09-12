<?php

namespace App\Domains\Auth\Notifications\Frontend;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;

/**
 * Class ResetPasswordNotification.
 */
class ResetPasswordNotification extends Notification
{
    /**
     * The password reset token.
     *
     * @var string
     */
    public $token;

    /**
     * Create a notification instance.
     *
     * @param string $token
     * @return void
     */
    public function __construct($token)
    {
        $this->token = $token;
    }

    /**
     * Get the notification's channels.
     *
     * @param mixed $notifiable
     * @return array|string
     */
    public function via($notifiable)
    {
        return ['mail'];
    }

    /**
     * Build the mail representation of the notification.
     *
     * @param mixed $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject(__('Đặt lại mật khẩu'))
            ->line(__('Bạn nhận được email này vì chúng tôi đã nhận được yêu cầu đặt lại mật khẩu cho tài khoản của bạn.'))
            ->action(__('Đặt lại mật khẩu'), $this->verificationUrl($notifiable))
            ->line(__('Liên kết đặt lại mật khẩu này sẽ hết hạn sau :count phút.', ['count' => config('auth.passwords.' . config('auth.defaults.passwords') . '.expire')]))
            ->line(__('Nếu bạn không yêu cầu đặt lại mật khẩu thì không cần thực hiện thêm hành động nào.'));
    }

    /**
     * Get the verification URL for the given notifiable.
     *
     * @param mixed $notifiable
     * @return string
     */
    protected function verificationUrl($notifiable)
    {
        return URL::temporarySignedRoute(
            'frontend.auth.password.reset',
            Carbon::now()->addMinutes(config('auth.passwords.' . config('auth.defaults.passwords') . '.expire')),
            [
                'token' => $this->token,
                'email' => $notifiable->getEmailForPasswordReset()
            ]
        );
    }
}
