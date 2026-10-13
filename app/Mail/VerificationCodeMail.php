<?php


namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class VerificationCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $code)
    {
    }

    public function build()
    {
        return $this->subject('کد تأیید Todolist')
            ->view('emails.verification-code')
            ->text('emails.verification-code-text')
            ->with(['code' => $this->code]);
    }
}
