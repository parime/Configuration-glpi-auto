<?php

namespace GlpiPlugin\Configurationglpiauto\Entity;

use Glpi\ORM\Entity;

/**
 * Configuration Profile entity.
 * 
 * Represents a predefined configuration profile for GLPI auto-configuration.
 */
class ConfigurationProfile extends Entity
{
    /**
     * @var string
     */
    public static $table = 'glpi_plugin_configurationglpiauto_profiles';

    /**
     * @var string
     */
    public static $type = 'Configurationglpiauto_ConfigurationProfile';

    /**
     * @var string
     */
    const PLUGIN_KEY = 'configurationglpiauto';

    /**
     * Get the display name of the item type.
     *
     * @return string
     */
    public static function getTypeName(): string
    {
        return __s('Configuration Profile', self::PLUGIN_KEY);
    }

    /**
     * Get the itemtype key.
     *
     * @return string
     */
    public static function getItemtypeKey(): string
    {
        return self::PLUGIN_KEY . '_ConfigurationProfile';
    }

    /**
     * Get the menu name.
     *
     * @return string
     */
    public static function getMenuName(): string
    {
        return __s('Profiles', self::PLUGIN_KEY);
    }

    /**
     * Get the menu option.
     *
     * @return array
     */
    public static function getMenuOption(): array
    {
        return [
            'title' => self::getMenuName(),
            'page' => '/front/profile.php',
            'links' => [
                'search' => '/front/profile.php',
                'add' => '/front/profile.form.php',
            ],
            'icons' => [
                'menu' => 'fas fa-cogs',
                'search' => 'fas fa-search',
                'add' => 'fas fa-plus-circle',
            ],
        ];
    }

    /**
     * Get the search options.
     *
     * @return array
     */
    public function getSearchOptions(): array
    {
        $tab = [];

        $tab['common'] = __s('Profile', self::PLUGIN_KEY);

        $tab[1] = 'name';
        $tab[2] = 'description';
        $tab[3] = 'type';
        $tab[4] = 'is_active';
        $tab[5] = 'sort_order';

        $tab[16] = 'comment';
        $tab[18] = 'date_mod';
        $tab[19] = 'date_creation';

        return $tab;
    }

    /**
     * Get the raw search options.
     *
     * @return array
     */
    public static function getRawSearchOptions(): array
    {
        return [
            'name' => [
                'table' => self::$table,
                'field' => 'name',
                'name' => __s('Name', self::PLUGIN_KEY),
                'datatype' => 'itemlink',
                'itemlink_type' => self::class,
                'massiveaction' => false,
            ],
            'description' => [
                'table' => self::$table,
                'field' => 'description',
                'name' => __s('Description', self::PLUGIN_KEY),
                'datatype' => 'text',
                'massiveaction' => false,
            ],
            'type' => [
                'table' => self::$table,
                'field' => 'type',
                'name' => __s('Type', self::PLUGIN_KEY),
                'datatype' => 'specific',
                'massiveaction' => false,
            ],
            'is_active' => [
                'table' => self::$table,
                'field' => 'is_active',
                'name' => __s('Active', self::PLUGIN_KEY),
                'datatype' => 'bool',
                'massiveaction' => true,
            ],
            'sort_order' => [
                'table' => self::$table,
                'field' => 'sort_order',
                'name' => __s('Sort Order', self::PLUGIN_KEY),
                'datatype' => 'integer',
                'massiveaction' => false,
            ],
            'date_mod' => [
                'table' => self::$table,
                'field' => 'date_mod',
                'name' => __s('Last update', self::PLUGIN_KEY),
                'datatype' => 'datetime',
                'massiveaction' => false,
            ],
            'date_creation' => [
                'table' => self::$table,
                'field' => 'date_creation',
                'name' => __s('Creation date', self::PLUGIN_KEY),
                'datatype' => 'datetime',
                'massiveaction' => false,
            ],
        ];
    }

    /**
     * Get the profile types.
     *
     * @return array
     */
    public static function getTypes(): array
    {
        return [
            'minimal' => __s('Minimal Installation', self::PLUGIN_KEY),
            'sme' => __s('SME', self::PLUGIN_KEY),
            'eti' => __s('ETI', self::PLUGIN_KEY),
            'enterprise' => __s('Large Enterprise', self::PLUGIN_KEY),
            'msp' => __s('MSP', self::PLUGIN_KEY),
            'iso27001' => __s('ISO 27001', self::PLUGIN_KEY),
            'itil' => __s('ITIL', self::PLUGIN_KEY),
            'custom' => __s('Custom', self::PLUGIN_KEY),
        ];
    }

    /**
     * Get type name.
     *
     * @param string $type
     * @return string
     */
    public static function getTypeName(string $type): string
    {
        $types = self::getTypes();
        return $types[$type] ?? $type;
    }

    /**
     * Prepare input data for add.
     *
     * @param array $input
     * @return array
     */
    public function prepareInputForAdd(array $input): array
    {
        if (!isset($input['name'])) {
            $input['name'] = '';
        }

        if (!isset($input['description'])) {
            $input['description'] = '';
        }

        if (!isset($input['type'])) {
            $input['type'] = 'custom';
        }

        if (!isset($input['is_active'])) {
            $input['is_active'] = 1;
        }

        if (!isset($input['sort_order'])) {
            $input['sort_order'] = 0;
        }

        return $input;
    }

    /**
     * Prepare input data for update.
     *
     * @param array $input
     * @return array
     */
    public function prepareInputForUpdate(array $input): array
    {
        return $this->prepareInputForAdd($input);
    }

    /**
     * Define tabs for the form.
     *
     * @param array $options
     * @return array
     */
    public function defineTabs($options = []): array
    {
        $ong = [];

        $ong['profile'] = __s('Profile', self::PLUGIN_KEY);
        $ong['modules'] = __s('Modules', self::PLUGIN_KEY);
        $ong['settings'] = __s('Settings', self::PLUGIN_KEY);

        return $ong;
    }

    /**
     * Display tab content.
     *
     * @param string $tab
     * @param array $options
     * @return bool|void
     */
    public function displayTabContent(string $tab, array $options = [])
    {
        switch ($tab) {
            case 'modules':
                $this->showModules();
                break;

            case 'settings':
                $this->showSettings();
                break;

            case 'profile':
            default:
                $this->showForm($options);
                break;
        }

        return true;
    }

    /**
     * Show modules associated with this profile.
     *
     * @return void
     */
    private function showModules(): void
    {
        // Implementation for showing modules
        // This would be expanded in a full implementation
    }

    /**
     * Show profile settings.
     *
     * @return void
     */
    private function showSettings(): void
    {
        // Implementation for showing settings
        // This would be expanded in a full implementation
    }

    /**
     * Get the default profile for new GLPI installations.
     *
     * @return self|null
     */
    public static function getDefaultProfile(): ?self
    {
        $profile = new self();
        if ($profile->getFromDBByQuery(['WHERE' => ['type' => 'sme', 'is_active' => 1]])) {
            return $profile;
        }

        if ($profile->getFromDBByQuery(['WHERE' => ['is_active' => 1], 'ORDER' => ['sort_order' => 'ASC']])) {
            return $profile;
        }

        return null;
    }

    /**
     * Get all active profiles.
     *
     * @return array
     */
    public static function getActiveProfiles(): array
    {
        $profile = new self();
        return $profile->find(['is_active' => 1], ['sort_order' => 'ASC']);
    }

    /**
     * Get profile by type.
     *
     * @param string $type
     * @return self|null
     */
    public static function getByType(string $type): ?self
    {
        $profile = new self();
        if ($profile->getFromDBByQuery(['WHERE' => ['type' => $type]])) {
            return $profile;
        }

        return null;
    }
}
