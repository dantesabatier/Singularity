<?php

namespace App;

use Sabatier\Foundation\Dictionary;

class EnvGenerator extends Generator
{
    public string $contents {
        get => new Dictionary([
            "SQL_SCHEMA_NAME" => $this->name,
            "SQL_SCHEMA_HOST" => "localhost",
            "SQL_SCHEMA_CREDENTIAL_USER" => "root",
            "SQL_SCHEMA_CREDENTIAL_PASSWORD" => ""
        ])->reduce("", fn(string &$result, string $value, string $key): string => $result .= "$key=$value\n");
    }
}
