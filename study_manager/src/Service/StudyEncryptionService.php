<?php

namespace Drupal\study_manager\Service;

use Drupal\Core\PrivateKey;
use Drupal\Core\Site\Settings;

/**
 * Generates and applies per-study encryption keys for private study files.
 *
 * Each study gets its own random AES-256 key ("study key"). The study key
 * is never stored in the database in the clear: it is wrapped (encrypted)
 * with a site-wide master key derived from Drupal's private key service and
 * hash salt before being persisted on the Study entity. File contents are
 * encrypted with the unwrapped study key using AES-256-GCM, with a short
 * marker prefix so already-encrypted files can be detected and skipped on
 * subsequent saves.
 */
class StudyEncryptionService {

  /**
   * Prefix written before the IV/tag/ciphertext of an encrypted file.
   */
  const FILE_MARKER = 'TRAKENC1';

  /**
   * The cipher used for both key wrapping and file encryption.
   */
  const CIPHER = 'aes-256-gcm';

  /**
   * The GCM tag length, in bytes.
   */
  const TAG_LENGTH = 16;

  /**
   * The GCM IV length, in bytes.
   */
  const IV_LENGTH = 12;

  public function __construct(protected PrivateKey $privateKey) {}

  /**
   * Derives the site-wide master key used to wrap/unwrap study keys.
   */
  protected function getMasterKey(): string {
    return hash('sha256', $this->privateKey->get() . Settings::getHashSalt(), TRUE);
  }

  /**
   * Generates a new random 256-bit study key.
   */
  public function generateStudyKey(): string {
    return random_bytes(32);
  }

  /**
   * Encrypts a raw study key for storage on the Study entity.
   */
  public function wrapKey(string $rawKey): string {
    $iv = random_bytes(self::IV_LENGTH);
    $tag = '';
    $ciphertext = openssl_encrypt($rawKey, self::CIPHER, $this->getMasterKey(), OPENSSL_RAW_DATA, $iv, $tag);
    if ($ciphertext === FALSE) {
      throw new \RuntimeException('Failed to wrap study encryption key.');
    }
    return base64_encode($iv . $tag . $ciphertext);
  }

  /**
   * Decrypts a stored, wrapped study key back to its raw form.
   */
  public function unwrapKey(string $wrapped): string {
    $raw = base64_decode($wrapped, TRUE);
    if ($raw === FALSE || strlen($raw) <= self::IV_LENGTH + self::TAG_LENGTH) {
      throw new \RuntimeException('Malformed wrapped study encryption key.');
    }
    $iv = substr($raw, 0, self::IV_LENGTH);
    $tag = substr($raw, self::IV_LENGTH, self::TAG_LENGTH);
    $ciphertext = substr($raw, self::IV_LENGTH + self::TAG_LENGTH);
    $key = openssl_decrypt($ciphertext, self::CIPHER, $this->getMasterKey(), OPENSSL_RAW_DATA, $iv, $tag);
    if ($key === FALSE) {
      throw new \RuntimeException('Failed to unwrap study encryption key.');
    }
    return $key;
  }

  /**
   * Returns TRUE if $blob already carries the encrypted-file marker.
   */
  public function isEncrypted(string $blob): bool {
    return strncmp($blob, self::FILE_MARKER, strlen(self::FILE_MARKER)) === 0;
  }

  /**
   * Encrypts file contents with the given (raw) study key.
   */
  public function encryptFileContents(string $rawKey, string $plaintext): string {
    $iv = random_bytes(self::IV_LENGTH);
    $tag = '';
    $ciphertext = openssl_encrypt($plaintext, self::CIPHER, $rawKey, OPENSSL_RAW_DATA, $iv, $tag);
    if ($ciphertext === FALSE) {
      throw new \RuntimeException('Failed to encrypt file contents.');
    }
    return self::FILE_MARKER . $iv . $tag . $ciphertext;
  }

  /**
   * Decrypts file contents that were encrypted with encryptFileContents().
   */
  public function decryptFileContents(string $rawKey, string $blob): string {
    if (!$this->isEncrypted($blob)) {
      throw new \RuntimeException('File contents are not in the expected encrypted format.');
    }
    $offset = strlen(self::FILE_MARKER);
    $iv = substr($blob, $offset, self::IV_LENGTH);
    $tag = substr($blob, $offset + self::IV_LENGTH, self::TAG_LENGTH);
    $ciphertext = substr($blob, $offset + self::IV_LENGTH + self::TAG_LENGTH);
    $plaintext = openssl_decrypt($ciphertext, self::CIPHER, $rawKey, OPENSSL_RAW_DATA, $iv, $tag);
    if ($plaintext === FALSE) {
      throw new \RuntimeException('Failed to decrypt file contents.');
    }
    return $plaintext;
  }

}
