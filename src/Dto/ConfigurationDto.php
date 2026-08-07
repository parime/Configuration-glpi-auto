<?php

namespace GlpiPlugin\Configurationglpiauto\Dto;

/**
 * Configuration Data Transfer Object.
 * 
 * Contains all data needed for configuration deployment.
 */
class ConfigurationDto
{
    /**
     * @var int|null
     */
    private ?int $profileId = null;

    /**
     * @var string|null
     */
    private ?string $profileType = null;

    /**
     * @var array
     */
    private array $moduleConfigurations = [];

    /**
     * @var array
     */
    private array $customConfigurations = [];

    /**
     * @var bool|null
     */
    private ?bool $dryRun = null;

    /**
     * @var bool|null
     */
    private ?bool $backupFirst = null;

    /**
     * @var string|null
     */
    private ?string $deploymentName = null;

    /**
     * @var string|null
     */
    private ?string $description = null;

    /**
     * @var array
     */
    private array $metadata = [];

    /**
     * Get profile ID.
     *
     * @return int|null
     */
    public function getProfileId(): ?int
    {
        return $this->profileId;
    }

    /**
     * Set profile ID.
     *
     * @param int $profileId
     * @return self
     */
    public function setProfileId(int $profileId): self
    {
        $this->profileId = $profileId;
        return $this;
    }

    /**
     * Get profile type.
     *
     * @return string|null
     */
    public function getProfileType(): ?string
    {
        return $this->profileType;
    }

    /**
     * Set profile type.
     *
     * @param string $profileType
     * @return self
     */
    public function setProfileType(string $profileType): self
    {
        $this->profileType = $profileType;
        return $this;
    }

    /**
     * Get module configurations.
     *
     * @return array
     */
    public function getModuleConfigurations(): array
    {
        return $this->moduleConfigurations;
    }

    /**
     * Set module configurations.
     *
     * @param array $moduleConfigurations
     * @return self
     */
    public function setModuleConfigurations(array $moduleConfigurations): self
    {
        $this->moduleConfigurations = $moduleConfigurations;
        return $this;
    }

    /**
     * Add module configuration.
     *
     * @param string $moduleKey
     * @param array $configuration
     * @return self
     */
    public function addModuleConfiguration(string $moduleKey, array $configuration): self
    {
        $this->moduleConfigurations[$moduleKey] = $configuration;
        return $this;
    }

    /**
     * Get custom configurations.
     *
     * @return array
     */
    public function getCustomConfigurations(): array
    {
        return $this->customConfigurations;
    }

    /**
     * Set custom configurations.
     *
     * @param array $customConfigurations
     * @return self
     */
    public function setCustomConfigurations(array $customConfigurations): self
    {
        $this->customConfigurations = $customConfigurations;
        return $this;
    }

    /**
     * Add custom configuration.
     *
     * @param array $configuration
     * @return self
     */
    public function addCustomConfiguration(array $configuration): self
    {
        $this->customConfigurations[] = $configuration;
        return $this;
    }

    /**
     * Check if this is a dry run.
     *
     * @return bool|null
     */
    public function isDryRun(): ?bool
    {
        return $this->dryRun;
    }

    /**
     * Set dry run flag.
     *
     * @param bool $dryRun
     * @return self
     */
    public function setDryRun(bool $dryRun): self
    {
        $this->dryRun = $dryRun;
        return $this;
    }

    /**
     * Check if we should backup first.
     *
     * @return bool|null
     */
    public function isBackupFirst(): ?bool
    {
        return $this->backupFirst;
    }

    /**
     * Set backup first flag.
     *
     * @param bool $backupFirst
     * @return self
     */
    public function setBackupFirst(bool $backupFirst): self
    {
        $this->backupFirst = $backupFirst;
        return $this;
    }

    /**
     * Get deployment name.
     *
     * @return string|null
     */
    public function getDeploymentName(): ?string
    {
        return $this->deploymentName;
    }

    /**
     * Set deployment name.
     *
     * @param string $deploymentName
     * @return self
     */
    public function setDeploymentName(string $deploymentName): self
    {
        $this->deploymentName = $deploymentName;
        return $this;
    }

    /**
     * Get description.
     *
     * @return string|null
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * Set description.
     *
     * @param string $description
     * @return self
     */
    public function setDescription(string $description): self
    {
        $this->description = $description;
        return $this;
    }

    /**
     * Get metadata.
     *
     * @return array
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * Set metadata.
     *
     * @param array $metadata
     * @return self
     */
    public function setMetadata(array $metadata): self
    {
        $this->metadata = $metadata;
        return $this;
    }

    /**
     * Add metadata.
     *
     * @param string $key
     * @param mixed $value
     * @return self
     */
    public function addMetadata(string $key, $value): self
    {
        $this->metadata[$key] = $value;
        return $this;
    }

    /**
     * Convert to array.
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'profile_id' => $this->profileId,
            'profile_type' => $this->profileType,
            'module_configurations' => $this->moduleConfigurations,
            'custom_configurations' => $this->customConfigurations,
            'dry_run' => $this->dryRun,
            'backup_first' => $this->backupFirst,
            'deployment_name' => $this->deploymentName,
            'description' => $this->description,
            'metadata' => $this->metadata,
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
        $dto = new self();

        if (isset($data['profile_id'])) {
            $dto->setProfileId($data['profile_id']);
        }

        if (isset($data['profile_type'])) {
            $dto->setProfileType($data['profile_type']);
        }

        if (isset($data['module_configurations'])) {
            $dto->setModuleConfigurations($data['module_configurations']);
        }

        if (isset($data['custom_configurations'])) {
            $dto->setCustomConfigurations($data['custom_configurations']);
        }

        if (isset($data['dry_run'])) {
            $dto->setDryRun($data['dry_run']);
        }

        if (isset($data['backup_first'])) {
            $dto->setBackupFirst($data['backup_first']);
        }

        if (isset($data['deployment_name'])) {
            $dto->setDeploymentName($data['deployment_name']);
        }

        if (isset($data['description'])) {
            $dto->setDescription($data['description']);
        }

        if (isset($data['metadata'])) {
            $dto->setMetadata($data['metadata']);
        }

        return $dto;
    }

    /**
     * Validate the DTO.
     *
     * @return bool
     */
    public function validate(): bool
    {
        // At least profile ID or type must be set
        if (!$this->profileId && !$this->profileType) {
            return false;
        }

        // If dry run is explicitly set, it must be boolean
        if ($this->dryRun !== null && !is_bool($this->dryRun)) {
            return false;
        }

        // If backup first is explicitly set, it must be boolean
        if ($this->backupFirst !== null && !is_bool($this->backupFirst)) {
            return false;
        }

        return true;
    }

    /**
     * Get validation errors.
     *
     * @return array
     */
    public function getValidationErrors(): array
    {
        $errors = [];

        if (!$this->profileId && !$this->profileType) {
            $errors[] = 'Either profile_id or profile_type must be set';
        }

        if ($this->dryRun !== null && !is_bool($this->dryRun)) {
            $errors[] = 'dry_run must be a boolean';
        }

        if ($this->backupFirst !== null && !is_bool($this->backupFirst)) {
            $errors[] = 'backup_first must be a boolean';
        }

        return $errors;
    }
}
