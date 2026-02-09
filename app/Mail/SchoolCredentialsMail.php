<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SchoolCredentialsMail extends Mailable
{
    use Queueable, SerializesModels;

    public $school;
    public $username;
    public $password;
    public $changePasswordUrl;

    /**
     * Create a new message instance.
     */
    public function __construct($school, $username, $password, $changePasswordUrl)
    {
        $this->school = $school;
        $this->username = $username;
        $this->password = $password;
        $this->changePasswordUrl = $changePasswordUrl;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        return $this->subject("Your school account on {$this->school->name}")
                    ->view('emails.school_credentials')
                    ->with([
                        'school' => $this->school,
                        'username' => $this->username,
                        'password' => $this->password,
                        'changePasswordUrl' => $this->changePasswordUrl,
                    ]);
    }
}
