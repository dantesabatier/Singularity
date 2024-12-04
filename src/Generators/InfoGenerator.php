<?php

namespace App\Generators;

use App\Delegate;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\PropertyListSerialization;
use Sabatier\Foundation\UserDefaults;
use const App\CompanyNameKey;
use const Sabatier\CoreData\SQLStoreType;
use const Sabatier\Foundation\kCFBundleDevelopmentRegionKey;
use const Sabatier\Foundation\kCFBundleDocumentTypesKey;
use const Sabatier\Foundation\kCFBundleExecutableKey;
use const Sabatier\Foundation\kCFBundleIdentifierKey;
use const Sabatier\Foundation\kCFBundleLocalizationsKey;
use const Sabatier\Foundation\kCFBundleNameKey;
use const Sabatier\Foundation\kCFBundlePackageTypeKey;
use const Sabatier\Foundation\kCFBundlePrincipalClassKey;
use const Sabatier\Foundation\kCFBundleShortVersionStringKey;
use const Sabatier\Foundation\kCFBundleTypeNameKey;
use const Sabatier\Foundation\kCFBundleVersionKey;

class InfoGenerator extends Generator
{
    public string $contents {
        get {
            $name = $this->name;
            return PropertyListSerialization::data(Dictionary::dictionaryWithArray([
                kCFBundleDevelopmentRegionKey => "English",
                kCFBundleExecutableKey => $name,
                kCFBundleIdentifierKey => sprintf("com.%s.%s", strtolower(str_replace(" ", "", (string)UserDefaults::standard()->string(CompanyNameKey))), strtolower($name)),
                kCFBundleNameKey => $name,
                kCFBundleVersionKey => "1",
                kCFBundleShortVersionStringKey => "0.1",
                kCFBundlePackageTypeKey => "APPL",
                kCFBundlePrincipalClassKey => Delegate::class,
                kCFBundleLocalizationsKey => [
                    "en"
                ],
                kCFBundleDocumentTypesKey => [
                    [
                        kCFBundleTypeNameKey => SQLStoreType
                    ]
                ]
            ]));
        }
    }
}
