<?php

declare(strict_types=1);

namespace App\FileWriters;

use Exception;
use Override;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\FileAttributeKey;
use Sabatier\Foundation\FileManager;
use Sabatier\Foundation\URL;

final class HtaccessFileWriter extends FileWriter
{
    #[Override]
    public string $contents {
        get {
            $content = "# $this->name — Apache configuration\n";
            $content .= "#\n";
            $content .= "# The application is served from the document root: the rewrite below sends\n";
            $content .= "# every request that is not index.php through it, and the leading slash\n";
            $content .= "# anchors that at the host root.\n";
            $content .= "#\n";
            $content .= "# Requires mod_rewrite. On a vhost, also set `Options -MultiViews`: with\n";
            $content .= "# MultiViews on, Apache resolves /Info to Info.plist before index.php ever runs.\n";
            $content .= "\n";
            $content .= "php_flag display_startup_errors off\n";
            $content .= "php_flag display_errors off\n";
            $content .= "php_value docref_root 0\n";
            $content .= "php_value docref_ext 0\n";
            $content .= "php_flag log_errors on\n";
            $content .= "php_value error_reporting -1\n";
            $content .= "php_value upload_max_filesize 2M\n";
            $content .= "php_value error_log " . $this->logFileURL->path . "\n";
            $content .= "\n";
            $content .= "# Apache strips the Authorization header before PHP sees it; this puts it back,\n";
            $content .= "# which is what Bearer and Basic authentication depend on.\n";
            $content .= "SetEnvIf Authorization \"(.*)\" HTTP_AUTHORIZATION=\$1\n";
            $content .= "\n";
            $content .= "Options +FollowSymLinks\n";
            $content .= "RewriteEngine on\n";
            $content .= "RewriteCond %{REQUEST_URI} !index.php\n";
            return $content . "RewriteRule ^(.*)\$ /index.php?url=\$1 [L]\n";
        }
    }
    private URL $logDirectoryURL {
        get => $this->url->deletingLastPathComponent()->appendingPathComponent("Library")->appendingPathComponent("Logs");
    }
    private URL $logFileURL {
        get => $this->logDirectoryURL->appendingPathComponent("errors")->appendingPathExtension("log");
    }

    /**
     * @throws Exception
     */
    #[Override]
    public function save(): void
    {
        $fileManager = FileManager::default();
        $logDirectoryURL = $this->logDirectoryURL;
        if (!$fileManager->fileExists($logDirectoryURL->path)) {
            $fileManager->createDirectory($logDirectoryURL, true, new Dictionary([FileAttributeKey::posixPermissions => 0777]));
        }
        parent::save();
    }
}
