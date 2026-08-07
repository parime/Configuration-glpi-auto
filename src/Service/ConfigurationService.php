<?php

namespace GlpiPlugin\Configurationglpiauto\Service;

use GlpiPlugin\Configurationglpiauto\Entity\ConfigurationProfile;
use GlpiPlugin\Configurationglpiauto\Entity\Module;
use GlpiPlugin\Configurationglpiauto\Repository\ConfigurationRepository;
use GlpiPlugin\Configurationglpiauto\Dto\ConfigurationDto;
use GlpiPlugin\Configurationglpiauto\Exception\ConfigurationException;

/**
 * Configuration service.
 * 
 * Handles all configuration-related business logic.
 */
class ConfigurationService
{
    /**
     * @var ConfigurationRepository
     */
    private ConfigurationRepository $configurationRepository;

    /**
     * @var array
     */
    private array $deployedConfigurations = [];

    /**
     * @var array
     */
    private array $deploymentLogs = [];

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->configurationRepository = new ConfigurationRepository();
    }

    /**
     * Get the plugin key.
     *
     * @return string
     */
    public static function getPluginKey(): string
    {
        return 'configurationglpiauto';
    }

    /**
     * Initialize default configurations.
     *
     * @return void
     */
    public function initializeDefaultConfigurations(): void
    {
        // Initialize default profiles
        $this->initializeDefaultProfiles();
        
        // Initialize default modules
        $this->initializeDefaultModules();
        
        // Initialize default settings
        $this->initializeDefaultSettings();
    }

    /**
     * Initialize default profiles.
     *
     * @return void
     */
    private function initializeDefaultProfiles(): void
    {
        $defaultProfiles = [
            [
                'name' => 'Installation minimale',
                'description' => 'Configuration minimale pour une installation basique',
                'type' => 'minimal',
                'is_active' => 1,
                'sort_order' => 1,
            ],
            [
                'name' => 'PME',
                'description' => 'Configuration adaptée aux PME (Petites et Moyennes Entreprises)',
                'type' => 'sme',
                'is_active' => 1,
                'sort_order' => 2,
            ],
            [
                'name' => 'ETI',
                'description' => 'Configuration adaptée aux ETI (Entreprises de Taille Intermédiaire)',
                'type' => 'eti',
                'is_active' => 1,
                'sort_order' => 3,
            ],
            [
                'name' => 'Grande entreprise',
                'description' => 'Configuration complète pour les grandes entreprises',
                'type' => 'enterprise',
                'is_active' => 1,
                'sort_order' => 4,
            ],
            [
                'name' => 'MSP',
                'description' => 'Configuration pour les MSP (Managed Service Providers)',
                'type' => 'msp',
                'is_active' => 1,
                'sort_order' => 5,
            ],
            [
                'name' => 'ISO 27001',
                'description' => 'Configuration conforme aux normes ISO 27001',
                'type' => 'iso27001',
                'is_active' => 1,
                'sort_order' => 6,
            ],
            [
                'name' => 'ITIL',
                'description' => 'Configuration basée sur les bonnes pratiques ITIL',
                'type' => 'itil',
                'is_active' => 1,
                'sort_order' => 7,
            ],
            [
                'name' => 'Personnalisé',
                'description' => 'Configuration personnalisée selon vos besoins spécifiques',
                'type' => 'custom',
                'is_active' => 1,
                'sort_order' => 8,
            ],
        ];

        foreach ($defaultProfiles as $profileData) {
            $profile = new ConfigurationProfile();
            if (!$profile->getFromDBByQuery(['WHERE' => ['type' => $profileData['type']]])) {
                $profile->add($profileData);
            }
        }
    }

    /**
     * Initialize default modules.
     *
     * @return void
     */
    private function initializeDefaultModules(): void
    {
        // Modules will be initialized by the Module service
        $moduleService = new ModuleService();
        $moduleService->initializeDefaultModules();
    }

    /**
     * Initialize default settings.
     *
     * @return void
     */
    private function initializeDefaultSettings(): void
    {
        $defaultSettings = [
            'geocoding_provider' => 'openstreetmap',
            'geocoding_api_key' => '',
            'backup_before_deploy' => 1,
            'dry_run_default' => 1,
            'notification_email' => '',
            'max_execution_time' => 300,
            'debug_mode' => 0,
            'default_profile' => 'sme',
            'auto_backup' => 1,
            'backup_retention_days' => 30,
        ];

        foreach ($defaultSettings as $key => $value) {
            if (!\Config::getConfigurationValues(self::getPluginKey(), [$key])) {
                \Config::setConfigurationValues(self::getPluginKey(), [$key => $value]);
            }
        }
    }

    /**
     * Get all available profiles.
     *
     * @return array
     */
    public function getProfiles(): array
    {
        return ConfigurationProfile::getActiveProfiles();
    }

    /**
     * Get profile by ID.
     *
     * @param int $profileId
     * @return ConfigurationProfile|null
     */
    public function getProfileById(int $profileId): ?ConfigurationProfile
    {
        $profile = new ConfigurationProfile();
        if ($profile->getFromDB($profileId)) {
            return $profile;
        }

        return null;
    }

    /**
     * Get profile by type.
     *
     * @param string $type
     * @return ConfigurationProfile|null
     */
    public function getProfileByType(string $type): ?ConfigurationProfile
    {
        return ConfigurationProfile::getByType($type);
    }

    /**
     * Deploy configuration for a specific profile.
     *
     * @param ConfigurationDto $configurationDto
     * @return array
     * @throws ConfigurationException
     */
    public function deployConfiguration(ConfigurationDto $configurationDto): array
    {
        $this->validateConfigurationDto($configurationDto);

        // Check if we should do a dry run
        $dryRun = $configurationDto->isDryRun() ?? $this->shouldDoDryRun();

        // Check if we should backup first
        $backupFirst = $configurationDto->isBackupFirst() ?? $this->shouldBackupFirst();

        $result = [
            'success' => false,
            'dry_run' => $dryRun,
            'backup_created' => false,
            'items_created' => 0,
            'items_updated' => 0,
            'items_deleted' => 0,
            'errors' => [],
            'warnings' => [],
            'logs' => [],
            'start_time' => date('Y-m-d H:i:s'),
            'end_time' => null,
        ];

        try {
            // Create backup if needed
            if ($backupFirst && !$dryRun) {
                $result['backup_created'] = $this->createBackup();
                $result['logs'][] = 'Backup created successfully';
            }

            // Start deployment
            $result['logs'][] = 'Starting configuration deployment';

            // Deploy profile configuration
            $profileResults = $this->deployProfileConfiguration($configurationDto, $dryRun);
            $result = $this->mergeResults($result, $profileResults);

            // Deploy module configurations
            if (isset($configurationDto->moduleConfigurations) && is_array($configurationDto->moduleConfigurations)) {
                foreach ($configurationDto->moduleConfigurations as $moduleKey => $moduleConfig) {
                    $moduleResults = $this->deployModuleConfiguration($moduleKey, $moduleConfig, $dryRun);
                    $result = $this->mergeResults($result, $moduleResults);
                }
            }

            // Deploy custom configurations
            if (isset($configurationDto->customConfigurations) && is_array($configurationDto->customConfigurations)) {
                foreach ($configurationDto->customConfigurations as $customConfig) {
                    $customResults = $this->deployCustomConfiguration($customConfig, $dryRun);
                    $result = $this->mergeResults($result, $customResults);
                }
            }

            // Finalize
            $result['success'] = true;
            $result['end_time'] = date('Y-m-d H:i:s');
            $result['logs'][] = 'Configuration deployment completed successfully';

            // Save deployment log
            $this->saveDeploymentLog($result);

        } catch (\Exception $e) {
            $result['errors'][] = $e->getMessage();
            $result['logs'][] = 'Error: ' . $e->getMessage();
            $result['end_time'] = date('Y-m-d H:i:s');

            // Save deployment log even on error
            $this->saveDeploymentLog($result);

            throw new ConfigurationException($e->getMessage(), 0, $e);
        }

        return $result;
    }

    /**
     * Validate configuration DTO.
     *
     * @param ConfigurationDto $configurationDto
     * @return void
     * @throws ConfigurationException
     */
    private function validateConfigurationDto(ConfigurationDto $configurationDto): void
    {
        if (!$configurationDto->getProfileId() && !$configurationDto->getProfileType()) {
            throw new ConfigurationException('Profile ID or type is required');
        }

        // Validate profile exists
        $profile = null;
        if ($configurationDto->getProfileId()) {
            $profile = $this->getProfileById($configurationDto->getProfileId());
        } elseif ($configurationDto->getProfileType()) {
            $profile = $this->getProfileByType($configurationDto->getProfileType());
        }

        if (!$profile) {
            throw new ConfigurationException('Invalid profile');
        }

        // Validate modules if specified
        if (isset($configurationDto->moduleConfigurations) && is_array($configurationDto->moduleConfigurations)) {
            foreach (array_keys($configurationDto->moduleConfigurations) as $moduleKey) {
                if (!$this->isValidModule($moduleKey)) {
                    throw new ConfigurationException("Invalid module: {$moduleKey}");
                }
            }
        }
    }

    /**
     * Check if module is valid.
     *
     * @param string $moduleKey
     * @return bool
     */
    private function isValidModule(string $moduleKey): bool
    {
        $module = new Module();
        return (bool) $module->getFromDBByQuery(['WHERE' => ['key' => $moduleKey]]);
    }

    /**
     * Check if we should do a dry run by default.
     *
     * @return bool
     */
    private function shouldDoDryRun(): bool
    {
        $config = \Config::getConfigurationValues(self::getPluginKey(), ['dry_run_default']);
        return $config['dry_run_default'] ?? true;
    }

    /**
     * Check if we should backup first by default.
     *
     * @return bool
     */
    private function shouldBackupFirst(): bool
    {
        $config = \Config::getConfigurationValues(self::getPluginKey(), ['backup_before_deploy']);
        return $config['backup_before_deploy'] ?? true;
    }

    /**
     * Create backup before deployment.
     *
     * @return bool
     */
    private function createBackup(): bool
    {
        $backupService = new BackupService();
        return $backupService->createBackup();
    }

    /**
     * Deploy profile configuration.
     *
     * @param ConfigurationDto $configurationDto
     * @param bool $dryRun
     * @return array
     */
    private function deployProfileConfiguration(ConfigurationDto $configurationDto, bool $dryRun): array
    {
        $result = [
            'items_created' => 0,
            'items_updated' => 0,
            'items_deleted' => 0,
            'errors' => [],
            'warnings' => [],
            'logs' => [],
        ];

        // Get profile
        $profile = null;
        if ($configurationDto->getProfileId()) {
            $profile = $this->getProfileById($configurationDto->getProfileId());
        } else {
            $profile = $this->getProfileByType($configurationDto->getProfileType());
        }

        if (!$profile) {
            $result['errors'][] = 'Invalid profile specified';
            return $result;
        }

        $result['logs'][] = "Deploying profile: {$profile->getField('name')}";

        // Deploy profile-specific configuration
        switch ($profile->getField('type')) {
            case 'minimal':
                $result = $this->mergeResults($result, $this->deployMinimalConfiguration($dryRun));
                break;

            case 'sme':
                $result = $this->mergeResults($result, $this->deploySmeConfiguration($dryRun));
                break;

            case 'eti':
                $result = $this->mergeResults($result, $this->deployEtiConfiguration($dryRun));
                break;

            case 'enterprise':
                $result = $this->mergeResults($result, $this->deployEnterpriseConfiguration($dryRun));
                break;

            case 'msp':
                $result = $this->mergeResults($result, $this->deployMspConfiguration($dryRun));
                break;

            case 'iso27001':
                $result = $this->mergeResults($result, $this->deployIso27001Configuration($dryRun));
                break;

            case 'itil':
                $result = $this->mergeResults($result, $this->deployItilConfiguration($dryRun));
                break;

            case 'custom':
            default:
                $result = $this->mergeResults($result, $this->deployCustomConfiguration($configurationDto, $dryRun));
                break;
        }

        return $result;
    }

    /**
     * Deploy minimal configuration.
     *
     * @param bool $dryRun
     * @return array
     */
    private function deployMinimalConfiguration(bool $dryRun): array
    {
        $result = [
            'items_created' => 0,
            'items_updated' => 0,
            'items_deleted' => 0,
            'errors' => [],
            'warnings' => [],
            'logs' => ['Deploying minimal configuration'],
        ];

        // Implement minimal configuration deployment
        // This would include basic GLPI settings, essential modules, etc.

        return $result;
    }

    /**
     * Deploy SME configuration.
     *
     * @param bool $dryRun
     * @return array
     */
    private function deploySmeConfiguration(bool $dryRun): array
    {
        $result = [
            'items_created' => 0,
            'items_updated' => 0,
            'items_deleted' => 0,
            'errors' => [],
            'warnings' => [],
            'logs' => ['Deploying SME configuration'],
        ];

        // Implement SME configuration deployment
        // This would include typical SME settings, modules, etc.

        return $result;
    }

    // Additional profile deployment methods would follow the same pattern
    // deployEtiConfiguration, deployEnterpriseConfiguration, etc.

    /**
     * Deploy module configuration.
     *
     * @param string $moduleKey
     * @param array $moduleConfig
     * @param bool $dryRun
     * @return array
     */
    private function deployModuleConfiguration(string $moduleKey, array $moduleConfig, bool $dryRun): array
    {
        $result = [
            'items_created' => 0,
            'items_updated' => 0,
            'items_deleted' => 0,
            'errors' => [],
            'warnings' => [],
            'logs' => ["Deploying module: {$moduleKey}"],
        ];

        // Find and deploy the module configuration
        $moduleService = new ModuleService();
        $moduleResults = $moduleService->deployModule($moduleKey, $moduleConfig, $dryRun);
        
        return $this->mergeResults($result, $moduleResults);
    }

    /**
     * Deploy custom configuration.
     *
     * @param array $customConfig
     * @param bool $dryRun
     * @return array
     */
    private function deployCustomConfiguration(array $customConfig, bool $dryRun): array
    {
        $result = [
            'items_created' => 0,
            'items_updated' => 0,
            'items_deleted' => 0,
            'errors' => [],
            'warnings' => [],
            'logs' => ['Deploying custom configuration'],
        ];

        // Implement custom configuration deployment
        // This would handle user-defined configurations

        return $result;
    }

    /**
     * Merge two result arrays.
     *
     * @param array $result1
     * @param array $result2
     * @return array
     */
    private function mergeResults(array $result1, array $result2): array
    {
        $merged = $result1;
        
        foreach (['items_created', 'items_updated', 'items_deleted'] as $key) {
            $merged[$key] += $result2[$key] ?? 0;
        }
        
        $merged['errors'] = array_merge($merged['errors'], $result2['errors'] ?? []);
        $merged['warnings'] = array_merge($merged['warnings'], $result2['warnings'] ?? []);
        $merged['logs'] = array_merge($merged['logs'], $result2['logs'] ?? []);
        
        return $merged;
    }

    /**
     * Save deployment log.
     *
     * @param array $deploymentResult
     * @return void
     */
    private function saveDeploymentLog(array $deploymentResult): void
    {
        $this->deploymentLogs[] = $deploymentResult;
        
        // Save to database
        $this->configurationRepository->saveDeploymentLog($deploymentResult);
    }

    /**
     * Get deployment history.
     *
     * @param int $limit
     * @return array
     */
    public function getDeploymentHistory(int $limit = 10): array
    {
        return $this->configurationRepository->getDeploymentLogs($limit);
    }

    /**
     * Rollback to a previous deployment.
     *
     * @param int $deploymentId
     * @return array
     */
    public function rollback(int $deploymentId): array
    {
        $result = [
            'success' => false,
            'errors' => [],
            'logs' => [],
            'start_time' => date('Y-m-d H:i:s'),
            'end_time' => null,
        ];

        try {
            $result['logs'][] = "Starting rollback to deployment: {$deploymentId}";
            
            // Get deployment information
            $deployment = $this->configurationRepository->getDeploymentById($deploymentId);
            
            if (!$deployment) {
                throw new ConfigurationException("Deployment {$deploymentId} not found");
            }

            // Restore backup
            $backupService = new BackupService();
            $restoreResult = $backupService->restoreBackup($deployment['backup_id'] ?? null);
            
            if (!$restoreResult['success']) {
                $result['errors'] = array_merge($result['errors'], $restoreResult['errors'] ?? []);
                throw new ConfigurationException('Backup restoration failed');
            }

            $result['logs'][] = 'Backup restored successfully';
            $result['success'] = true;
            $result['end_time'] = date('Y-m-d H:i:s');
            $result['logs'][] = 'Rollback completed successfully';

            // Save rollback log
            $this->saveRollbackLog($deploymentId, $result);

        } catch (\Exception $e) {
            $result['errors'][] = $e->getMessage();
            $result['logs'][] = 'Error: ' . $e->getMessage();
            $result['end_time'] = date('Y-m-d H:i:s');

            // Save rollback log even on error
            $this->saveRollbackLog($deploymentId, $result);

            throw new ConfigurationException($e->getMessage(), 0, $e);
        }

        return $result;
    }

    /**
     * Save rollback log.
     *
     * @param int $deploymentId
     * @param array $rollbackResult
     * @return void
     */
    private function saveRollbackLog(int $deploymentId, array $rollbackResult): void
    {
        $this->configurationRepository->saveRollbackLog($deploymentId, $rollbackResult);
    }

    /**
     * Get available configuration blueprints.
     *
     * @return array
     */
    public function getBlueprints(): array
    {
        $blueprintService = new BlueprintService();
        return $blueprintService->getAllBlueprints();
    }

    /**
     * Export current configuration as blueprint.
     *
     * @param string $name
     * @param string $description
     * @return array
     */
    public function exportAsBlueprint(string $name, string $description): array
    {
        $blueprintService = new BlueprintService();
        return $blueprintService->exportAsBlueprint($name, $description);
    }

    /**
     * Import configuration from blueprint.
     *
     * @param string $blueprintId
     * @param bool $dryRun
     * @return array
     */
    public function importFromBlueprint(string $blueprintId, bool $dryRun = true): array
    {
        $blueprintService = new BlueprintService();
        return $blueprintService->importFromBlueprint($blueprintId, $dryRun);
    }
}
