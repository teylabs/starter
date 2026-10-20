<?php

use Dotenv\Dotenv;
use Illuminate\Foundation\Testing\TestCase;

require dirname(__DIR__).'/vendor/autoload.php';

/** The template's .env.example names the mail sender after the app, so a renamed app does not send as "Example". */
final class MailSenderTest extends TestCase
{
    protected function setUp(): void
    {
        // The example's own values, with the app renamed, as the environment the app boots in.
        $example = Dotenv::parse((string) file_get_contents(dirname(__DIR__).'/.env.example').PHP_EOL);
        $this->assertArrayHasKey('MAIL_FROM_NAME', $example);
        $this->assertArrayHasKey('MAIL_FROM_ADDRESS', $example);
        $renamed = Dotenv::parse(preg_replace('/^APP_NAME=.*$/m', 'APP_NAME="Renamed App"', (string) file_get_contents(dirname(__DIR__).'/.env.example')));
        foreach (['APP_NAME', 'MAIL_FROM_NAME', 'MAIL_FROM_ADDRESS'] as $key) {
            putenv($key.'='.$renamed[$key]);
            $_ENV[$key] = $_SERVER[$key] = $renamed[$key];
        }
        parent::setUp();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        foreach (['APP_NAME', 'MAIL_FROM_NAME', 'MAIL_FROM_ADDRESS'] as $key) {
            putenv($key);
            unset($_ENV[$key], $_SERVER[$key]);
        }
    }

    public function createApplication()
    {
        return require __DIR__.'/application.php';
    }

    public function test_the_mail_sender_follows_the_app_name(): void
    {
        $this->assertSame('Renamed App', config('app.name'));
        $this->assertSame('Renamed App', config('mail.from.name'));
        $this->assertNotSame('Example', config('mail.from.name'));
        $this->assertSame('hello@example.com', config('mail.from.address'));
    }
}
