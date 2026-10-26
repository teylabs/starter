<?php

namespace Tests;

require_once __DIR__.'/TestCase.php';

/** The base of the storage tests: the application booted with a synthetic object-storage bucket in the environment. */
abstract class StorageCase extends TestCase
{
    public const BUCKET = ['AWS_ACCESS_KEY_ID' => 'synthetic-key', 'AWS_SECRET_ACCESS_KEY' => 'synthetic-secret', 'AWS_DEFAULT_REGION' => 'auto', 'AWS_BUCKET' => 'synthetic-bucket', 'AWS_ENDPOINT' => 'https://synthetic.r2.example.test', 'AWS_URL' => 'https://synthetic.r2.example.test/synthetic-bucket', 'AWS_USE_PATH_STYLE_ENDPOINT' => 'true'];

    public function createApplication()
    {
        foreach (self::BUCKET as $key => $value) {
            putenv($key.'='.$value);
            $_ENV[$key] = $_SERVER[$key] = $value;
        }

        return require __DIR__.'/application.php';
    }
}
