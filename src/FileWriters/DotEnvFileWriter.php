<?php

namespace App\FileWriters;

use Random\RandomException;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\URL;

final class DotEnvFileWriter extends FileWriter
{
    public string $contents {
        /**
         * @throws RandomException
         */
        get {
            $dictionary = new Dictionary([
                "SQL_SCHEMA_NAME" => $this->name,
                "SQL_SCHEMA_HOST" => "127.0.0.1",
                "SQL_SCHEMA_CREDENTIAL_USER" => "root",
                "SQL_SCHEMA_CREDENTIAL_PASSWORD" => ""
            ]);
            if ($this->isGeneratedWithCORS) {
                $dictionary->merge(new Dictionary([
                    "CORS_ALLOWED_ORIGINS" => "http://localhost",
                    "CORS_ALLOWED_METHODS" => "OPTIONS,HEAD,GET,POST,PATCH,DELETE",
                    "CORS_ALLOWED_HEADERS" => "Content-Type,Authorization,Serialization",
                    "CORS_ALLOW_CREDENTIALS" => "true"
                ]));
            }
            if ($this->isGeneratedWithJWT) {
                $dictionary->merge(new Dictionary([
                    "JWT_PRIVATE_KEY" => bin2hex(random_bytes(32)),
                    "JWT_VALIDITY_TIME_INTERVAL" => "28800"
                ]));
            }
            return $dictionary->reduce("", fn(string &$result, string $value, string $key): string => $result .= "$key=$value\n");
        }
    }
    private bool $isGeneratedWithCORS;
    private bool $isGeneratedWithJWT;

    public function __construct(URL $url, bool $isGeneratedWithCORS, bool $isGeneratedWithJWT)
    {
        parent::__construct($url);
        $this->isGeneratedWithCORS = $isGeneratedWithCORS;
        $this->isGeneratedWithJWT = $isGeneratedWithJWT;
    }
}
