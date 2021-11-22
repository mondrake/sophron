<?php

namespace Drupal\sophron_guesser;

use Drupal\Core\File\FileSystemInterface;
use Drupal\sophron\MimeMapManagerInterface;
use Symfony\Component\Mime\MimeTypeGuesserInterface;

/**
 * Makes possible to guess the MIME type of a file using its extension.
 */
class SophronMimeTypeGuesser implements MimeTypeGuesserInterface {

  /**
   * The MIME map manager service.
   *
   * @var \Drupal\sophron\MimeMapManagerInterface
   */
  protected $mimeMapManager;

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * Constructs a SophronMimeTypeGuesser object.
   *
   * @param \Drupal\sophron\MimeMapManagerInterface $mime_map_manager
   *   The MIME map manager service.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system service.
   */
  public function __construct(MimeMapManagerInterface $mime_map_manager, FileSystemInterface $file_system) {
    $this->mimeMapManager = $mime_map_manager;
    $this->fileSystem = $file_system;
  }

  /**
   * {@inheritdoc}
   */
  public function guessMimeType(string $path) : ?string {
    $extension = '';
    $file_parts = explode('.', $this->fileSystem->basename($path));

    // Remove the first part: a full filename should not match an extension.
    array_shift($file_parts);

    // Iterate over the file parts, trying to find a match.
    // For 'my.awesome.image.jpeg', we try: 'jpeg', then 'image.jpeg', then
    // 'awesome.image.jpeg'.
    while ($additional_part = array_pop($file_parts)) {
      $extension = strtolower($additional_part . ($extension ? '.' . $extension : ''));
      if ($mime_map_extension = $this->mimeMapManager->getExtension($extension)) {
        return $mime_map_extension->getDefaultType(FALSE);
      }
    }

    return 'application/octet-stream';
  }

  /**
   * {@inheritdoc}
   */
  public function isGuesserSupported(): bool {
    return TRUE;
  }

  /**
   * Sets the mimetypes/extension mapping to use when guessing mimetype.
   *
   * This method is implemented to ensure that when this class is set to
   * override \Drupal\Core\File\MimeType\ExtensionMimeTypeGuesser in the service
   * definition, any call to this method does not fatal. Actually, for Sophron
   * this is a no-op.
   *
   * @param array|null $mapping
   *   Not relevant.
   */
  public function setMapping(array $mapping = NULL) {
    // Do nothing.
  }

}
