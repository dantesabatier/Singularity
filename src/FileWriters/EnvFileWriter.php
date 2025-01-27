<?php

namespace App\FileWriters;

use Sabatier\Foundation\Dictionary;

class EnvFileWriter extends FileWriter
{
    public string $contents {
        get => new Dictionary([
            "SQL_SCHEMA_NAME" => $this->name,
            "SQL_SCHEMA_HOST" => "127.0.0.1",
            "SQL_SCHEMA_CREDENTIAL_USER" => "root",
            "SQL_SCHEMA_CREDENTIAL_PASSWORD" => ""
        ])->reduce("", fn(string &$result, string $value, string $key): string => $result .= "$key=$value\n");
    }
}
