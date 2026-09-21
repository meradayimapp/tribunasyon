<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPasswordNotification extends ResetPassword
{
    public function toMail($notifiable): MailMessage
    {
        $url = url(route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));
        $minutes = (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60);

        return (new MailMessage)
            ->subject('Şifreni sıfırla')
            ->greeting('Merhaba!')
            ->line('Tribünasyon hesabınız için bir şifre sıfırlama isteği aldık.')
            ->action('Şifremi sıfırla', $url)
            ->line("Bu bağlantı {$minutes} dakika boyunca geçerlidir.")
            ->line('Bu istek size ait değilse bu e-postayı yok sayabilirsiniz.')
            ->salutation('Tribünasyon');
    }
}
