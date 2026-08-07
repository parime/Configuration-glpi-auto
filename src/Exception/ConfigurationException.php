<?php

namespace GlpiPlugin\Configurationglpiauto\Exception;

use Exception;

/**
 * Configuration Exception.
 * 
 * Base exception class for Configuration GLPI Auto plugin errors.
 */
class ConfigurationException extends Exception
{
    /**
     * @var string
     */
    const PLUGIN_KEY = 'configurationglpiauto';

    /**
     * @var array
     */
    private array $context = [];

    /**
     * @var int|null
     */
    private ?int $httpStatusCode = null;

    /**
     * Constructor.
     *
     * @param string $message
     * @param int $code
     * @param Exception|null $previous
     * @param array $context
     * @param int|null $httpStatusCode
     */
    public function __construct(
        string $message = "",
        int $code = 0,
        Exception $previous = null,
        array $context = [],
        ?int $httpStatusCode = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->context = $context;
        $this->httpStatusCode = $httpStatusCode;
    }

    /**
     * Get context data.
     *
     * @return array
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Set context data.
     *
     * @param array $context
     * @return self
     */
    public function setContext(array $context): self
    {
        $this->context = $context;
        return $this;
    }

    /**
     * Add context data.
     *
     * @param string $key
     * @param mixed $value
     * @return self
     */
    public function addContext(string $key, $value): self
    {
        $this->context[$key] = $value;
        return $this;
    }

    /**
     * Get HTTP status code.
     *
     * @return int|null
     */
    public function getHttpStatusCode(): ?int
    {
        return $this->httpStatusCode;
    }

    /**
     * Set HTTP status code.
     *
     * @param int $httpStatusCode
     * @return self
     */
    public function setHttpStatusCode(int $httpStatusCode): self
    {
        $this->httpStatusCode = $httpStatusCode;
        return $this;
    }

    /**
     * Get user-friendly error message.
     *
     * @return string
     */
    public function getUserFriendlyMessage(): string
    {
        $translations = [
            'Profile not found' => __('Profile not found', self::PLUGIN_KEY),
            'Invalid profile' => __('Invalid profile', self::PLUGIN_KEY),
            'Module not found' => __('Module not found', self::PLUGIN_KEY),
            'Invalid module' => __('Invalid module', self::PLUGIN_KEY),
            'Configuration failed' => __('Configuration failed', self::PLUGIN_KEY),
            'Deployment failed' => __('Deployment failed', self::PLUGIN_KEY),
            'Backup failed' => __('Backup failed', self::PLUGIN_KEY),
            'Restore failed' => __('Restore failed', self::PLUGIN_KEY),
            'Validation failed' => __('Validation failed', self::PLUGIN_KEY),
            'Database error' => __('Database error', self::PLUGIN_KEY),
            'Permission denied' => __('Permission denied', self::PLUGIN_KEY),
            'Invalid input' => __('Invalid input', self::PLUGIN_KEY),
        ];

        $message = $this->getMessage();
        return $translations[$message] ?? $message;
    }

    /**
     * Create exception for profile not found.
     *
     * @param int|string $profileId
     * @return self
     */
    public static function profileNotFound($profileId): self
    {
        $message = is_int($profileId) ? "Profile with ID {$profileId} not found" : "Profile '{$profileId}' not found";
        return new self($message, 404, null, ['profile_id' => $profileId]);
    }

    /**
     * Create exception for invalid profile.
     *
     * @param int|string|null $profileId
     * @return self
     */
    public static function invalidProfile($profileId = null): self
    {
        $message = $profileId ? "Invalid profile: {$profileId}" : "Invalid profile";
        $context = $profileId ? ['profile_id' => $profileId] : [];
        return new self($message, 400, null, $context);
    }

    /**
     * Create exception for module not found.
     *
     * @param string $moduleKey
     * @return self
     */
    public static function moduleNotFound(string $moduleKey): self
    {
        return new self("Module '{$moduleKey}' not found", 404, null, ['module_key' => $moduleKey]);
    }

    /**
     * Create exception for invalid module.
     *
     * @param string $moduleKey
     * @return self
     */
    public static function invalidModule(string $moduleKey): self
    {
        return new self("Invalid module: {$moduleKey}", 400, null, ['module_key' => $moduleKey]);
    }

    /**
     * Create exception for validation failure.
     *
     * @param array $errors
     * @return self
     */
    public static function validationFailed(array $errors): self
    {
        $message = 'Validation failed: ' . implode(', ', $errors);
        return new self($message, 422, null, ['errors' => $errors]);
    }

    /**
     * Create exception for database error.
     *
     * @param string $error
     * @param Exception|null $previous
     * @return self
     */
    public static function databaseError(string $error, Exception $previous = null): self
    {
        return new self("Database error: {$error}", 500, $previous, ['database_error' => $error]);
    }

    /**
     * Create exception for permission denied.
     *
     * @param string $action
     * @return self
     */
    public static function permissionDenied(string $action = ''): self
    {
        $message = $action ? "Permission denied for action: {$action}" : "Permission denied";
        return new self($message, 403, null, $action ? ['action' => $action] : []);
    }

    /**
     * Create exception for configuration error.
     *
     * @param string $message
     * @param array $context
     * @return self
     */
    public static function configurationError(string $message, array $context = []): self
    {
        return new self("Configuration error: {$message}", 400, null, $context);
    }

    /**
     * Create exception for deployment error.
     *
     * @param string $message
     * @param array $context
     * @return self
     */
    public static function deploymentError(string $message, array $context = []): self
    {
        return new self("Deployment error: {$message}", 500, null, $context);
    }

    /**
     * Convert to array for logging.
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'message' => $this->getMessage(),
            'code' => $this->getCode(),
            'file' => $this->getFile(),
            'line' => $this->getLine(),
            'context' => $this->context,
            'http_status_code' => $this->httpStatusCode,
            'trace' => $this->getTraceAsString(),
        ];
    }

    /**
     * Create from array.
     *
     * @param array $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        $exception = new self(
            $data['message'] ?? '',
            $data['code'] ?? 0,
            null,
            $data['context'] ?? [],
            $data['http_status_code'] ?? null
        );

        return $exception;
    }

    /**
     * Log the exception.
     *
     * @param string $level
     * @return void
     */
    public function log(string $level = 'error'): void
    {
        $logMessage = sprintf(
            "[%s] %s in %s on line %d",
            get_class($this),
            $this->getMessage(),
            $this->getFile(),
            $this->getLine()
        );

        if (!empty($this->context)) {
            $logMessage .= ' Context: ' . json_encode($this->context);
        }

        if (!empty($this->getTraceAsString())) {
            $logMessage .= "\n" . $this->getTraceAsString();
        }

        // Use GLPI logging if available
        if (class_exists('\Glpi\Toolbox::logInFile')) {
            \Glpi\Toolbox::logInFile(
                'php-errors',
                $logMessage . "\n"
            );
        } else {
            error_log($logMessage);
        }
    }
}
