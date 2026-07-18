<?php

namespace App\Console\Commands;

use App\Mail\MailConfigurationTest;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class TestMailConfiguration extends Command
{
    protected $signature = 'mail:test {email}';

    protected $description = 'Send a no-excuse configuration test email';

    public function handle(): int
    {
        $email = filter_var((string) $this->argument('email'), FILTER_VALIDATE_EMAIL);
        if (! $email) {
            $this->error('Invalid recipient address.');

            return self::FAILURE;
        }
        Mail::to($email)->send(new MailConfigurationTest);
        $this->info('Test email accepted by the configured mail transport.');

        return self::SUCCESS;
    }
}
