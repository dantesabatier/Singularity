<?php

declare(strict_types=1);

namespace App\Bundles;

use Exception;
use Sabatier\CoreData\ManagedObjectModelBundle;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\FileManager;
use Sabatier\Foundation\PropertyListSerialization;
use Sabatier\Foundation\URL;
use const Sabatier\CoreData\ManagedObjectModelBundleFileExtension;
use const Sabatier\CoreData\ManagedObjectModelCurrentVersionNameKey;
use const Sabatier\CoreData\ManagedObjectModelFileExtension;
use const Sabatier\CoreData\ManagedObjectModelVersionHashesKey;
use const Sabatier\CoreData\MappingModelFileExtension;

/**
 * Where a project keeps the model it emits.
 *
 * A project starts out with a single model file, which is what every project has today and what a
 * project that never declared a version keeps. Declaring a version turns that into a package holding
 * one file per version, so the two models a migration needs can exist at once.
 *
 * The layout on disk is derived here and nowhere else: the writers, the loader and the rename all
 * ask this class where the model goes, so a project that moved to a package cannot be written to the
 * path it used to occupy.
 */
final class ModelBundle
{
    /** @var URL The location of the package holding one model file per version, whether or not it exists. */
    public URL $packageURL {
        get => $this->resourcesURL->appendingPathComponent($this->name)->appendingPathExtension(ManagedObjectModelBundleFileExtension);
    }
    /** @var URL The location of the lone model file, whether or not it exists. This is the layout a project without versions keeps. */
    public URL $modelFileURL {
        get => $this->resourcesURL->appendingPathComponent($this->name)->appendingPathExtension(ManagedObjectModelFileExtension);
    }
    /** @var bool Whether the project keeps its versions in a package. */
    public bool $isVersioned {
        get => FileManager::default()->fileExists($this->packageURL->path);
    }
    /** @var URL The location of the model a consumer loads: the package when there is one, the lone file otherwise. */
    public URL $url {
        get => $this->isVersioned ? $this->packageURL : $this->modelFileURL;
    }
    /** @var URL The location the work in progress is written to. Inside the package it is the current version; without one it is the lone file. */
    public URL $currentVersionURL {
        get => $this->isVersioned ? $this->urlForVersionNamed($this->currentVersionName) : $this->modelFileURL;
    }
    /** @var string The name of the version the work in progress belongs to. A project without a package has one unnamed version, which is the bundle's own name. */
    public string $currentVersionName {
        get {
            if (!$this->isVersioned) {
                return $this->name;
            }
            /** @var string|null $name */
            $name = $this->versionInfo->valueForKey(ManagedObjectModelCurrentVersionNameKey);
            return $name ?? $this->name;
        }
    }
    /** @var Dictionary<mixed> The package's version information, empty when the project keeps no package or the package carries none. */
    public Dictionary $versionInfo {
        get {
            if (!$this->isVersioned) {
                return new Dictionary();
            }
            /** @var Dictionary<mixed>|null $versionInfo */
            $versionInfo = PropertyListSerialization::propertyListWithURL($this->versionInfoURL);
            return $versionInfo ?? new Dictionary();
        }
    }
    /** @var URL The location of the package's version information. */
    public URL $versionInfoURL {
        get => new ManagedObjectModelBundle($this->packageURL)->versionInfoURL;
    }
    /** @var Dictionary<string> The version checksum of every version the package records, keyed by version name. */
    public Dictionary $versionChecksums {
        get {
            /** @var Dictionary<string>|null $checksums */
            $checksums = $this->versionInfo->valueForKey(ManagedObjectModelVersionHashesKey);
            return $checksums ?? new Dictionary();
        }
    }
    private URL $resourcesURL {
        get => $this->resourcesURL ??= $this->bundleURL->appendingPathComponent("Resources");
    }

    /**
     * @param URL $bundleURL The generated bundle the model belongs to.
     * @param string $name The bundle's name, which the model is named after.
     */
    public function __construct(private readonly URL $bundleURL, private readonly string $name)
    {
    }

    /**
     * Returns the location of the named version inside the package, whether or not it exists.
     * @param string $name The name of a version.
     */
    public function urlForVersionNamed(string $name): URL
    {
        return $this->packageURL->appendingPathComponent($name)->appendingPathExtension(ManagedObjectModelFileExtension);
    }

    /**
     * Returns the location of the named mapping model, which sits beside the package rather than inside it.
     *
     * A mapping model is discovered by enumerating a bundle's resources, and that enumeration never
     * descends into a package: one written inside the model package would be invisible. The name is
     * incidental — a map is located by the entity version hashes it carries.
     * @param string $name The name of a mapping model.
     */
    public function urlForMappingModelNamed(string $name): URL
    {
        return $this->resourcesURL->appendingPathComponent($name)->appendingPathExtension(MappingModelFileExtension);
    }

    /**
     * Writes the package's version information, naming the version the work in progress belongs to.
     * @param string $currentVersionName The name of the version to load.
     * @param Dictionary<string> $versionChecksums The version checksum of every version, keyed by version name.
     * @throws Exception
     */
    public function writeVersionInfo(string $currentVersionName, Dictionary $versionChecksums): void
    {
        /** @var Dictionary<mixed> $versionInfo */
        $versionInfo = new Dictionary([
            ManagedObjectModelCurrentVersionNameKey => $currentVersionName,
            ManagedObjectModelVersionHashesKey => $versionChecksums,
        ]);
        PropertyListSerialization::writePropertyList($versionInfo, $this->versionInfoURL);
    }
}
