<?php

declare(strict_types=1);

namespace App\FileWriters;

use Override;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\URL;
use function Sabatier\Foundation\read_random;

final class DotEnvFileWriter extends FileWriter
{
    #[Override]
    public string $contents {
        get {
            /** @var Dictionary<string> $dictionary */
            $dictionary = new Dictionary([
                "SQL_SCHEMA_NAME" => $this->name,
                "SQL_SCHEMA_HOST" => "127.0.0.1",
                "SQL_SCHEMA_CREDENTIAL_USER" => "root",
                "SQL_SCHEMA_CREDENTIAL_PASSWORD" => ""
            ]);
            if ($this->isGeneratedWithCORS) {
                $dictionary->merge([
                    "CORS_ALLOWED_ORIGINS" => "http://localhost",
                    "CORS_ALLOWED_METHODS" => "OPTIONS,HEAD,GET,POST,PATCH,DELETE",
                    "CORS_ALLOWED_HEADERS" => "Content-Type,Authorization,Serialization",
                    "CORS_ALLOW_CREDENTIALS" => "true"
                ]);
            }
            if ($this->isGeneratedWithJWT) {
                $dictionary->merge([
                    "JWT_PRIVATE_KEY" => base64_encode(read_random(16)),
                    "JWT_VALIDITY_TIME_INTERVAL" => "28800"
                ]);
            }
            return $dictionary->reduce("", fn(string &$result, string $value, string $key): string => $result .= "$key=$value\n");
        }
    }
    private readonly bool $isGeneratedWithCORS;
    private readonly bool $isGeneratedWithJWT;

    public function __construct(URL $url, bool $isGeneratedWithCORS, bool $isGeneratedWithJWT)
    {
        parent::__construct($url);
        $this->isGeneratedWithCORS = $isGeneratedWithCORS;
        $this->isGeneratedWithJWT = $isGeneratedWithJWT;
    }
}
