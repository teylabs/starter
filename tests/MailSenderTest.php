<?php

use Dotenv\Dotenv;
use PHPUnit\Framework\TestCase;

require dirname(__DIR__).'/vendor/autoload.php';

/** The template's .env.example names the mail sender after the app, so a renamed app does not send as "Example". */
final class MailSenderTest extends TestCase
{
    public function test_the_mail_sender_follows_the_app_name(): void
    {
        // The example's own values, with the app renamed, in a fresh process: the environment the app boots in. (This process has
        // already loaded the developer's own .env, which would win over anything set here.)
        $contents = (string) file_get_contents(dirname(__DIR__).'/.env.example');
        $this->assertMatchesRegularExpression('/^MAIL_FROM_NAME="\$\{APP_NAME\}"$/m', $contents);
        $renamed = Dotenv::parse(preg_replace('/^APP_NAME=.*$/m', 'APP_NAME="Renamed App"', $contents)."\n");
        $this->assertArrayHasKey('MAIL_FROM_NAME', $renamed);
        $this->assertArrayHasKey('KIT_BRAND_MAIL_FROM_ADDRESS', $renamed);
        $env = ['APP_NAME' => $renamed['APP_NAME'], 'MAIL_FROM_NAME' => $renamed['MAIL_FROM_NAME'], 'KIT_BRAND_MAIL_FROM_ADDRESS' => $renamed['KIT_BRAND_MAIL_FROM_ADDRESS']];
        $script = '$app = require "'.__DIR__.'/application.php"; echo json_encode([config("app.name"), config("mail.from.name"), config("mail.from.address")]);';
        $process = proc_open([PHP_BINARY, '-r', $script], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__), [...getenv(), ...$env]);
        $out = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);
        $this->assertSame(['Renamed App', 'Renamed App', 'updates@example.com'], json_decode(substr($out, (int) strrpos($out, '[')), true), $out);
    }
}
