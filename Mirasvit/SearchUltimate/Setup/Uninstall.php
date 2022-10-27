<?php
/**
 * Mirasvit
 *
 * This source file is subject to the Mirasvit Software License, which is available at https://mirasvit.com/license/.
 * Do not edit or add to this file if you wish to upgrade the to newer versions in the future.
 * If you wish to customize this module for your needs.
 * Please refer to http://www.magentocommerce.com for more information.
 *
 * @category  Mirasvit
 * @package   mirasvit/module-search-ultimate
 * @version   2.0.77
 * @copyright Copyright (C) 2022 Mirasvit (https://mirasvit.com/)
 */


namespace Mirasvit\SearchUltimate\Setup;

use Magento\Framework\Setup\UninstallInterface as UninstallInterface;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Db\Select;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Module\Status as ModuleStatus;
use Magento\Framework\Setup\Patch\PatchApplier;
use Mirasvit\Core\Service\PackageService;
use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\Module\ModuleList\Loader;
use Magento\Setup\Module\DataSetup;
use Symfony\Component\Console\Output\OutputInterface;

class Uninstall implements UninstallInterface
{

    private $output;

    private $moduleStatus;

    private $patchApplier;

    private $packageService;

    private $dataSetup;

    private $nestedModules = [
        'Mirasvit_Search',
        'Mirasvit_Misspell',
        'Mirasvit_SearchReport',
        'Mirasvit_SearchAutocomplete',
        'Mirasvit_SearchElastic',
        'Mirasvit_SearchGraphQl',
        'Mirasvit_SearchLanding',
        'Mirasvit_SearchMysql',
        'Mirasvit_SearchSphinx',
    ];

    private $moduleTables = [
        'mst_misspell_index',
        'mst_misspell_suggest',
        'mst_search_index',
        'mst_search_landing_page',
        'mst_search_report_log',
        'mst_search_score_rule',
        'mst_search_score_rule_index',
        'mst_search_stopword'
    ];

    public function __construct(
        ModuleStatus $moduleStatus,
        PatchApplier $patchApplier,
        PackageService $packageService,
        DataSetup $dataSetup,
        DeploymentConfig $deploymentConfig,
        DeploymentConfig\Writer $writer,
        Loader $loader
    ){
        $this->moduleStatus     = $moduleStatus;
        $this->patchApplier     = $patchApplier;
        $this->packageService   = $packageService;
        $this->dataSetup        = $dataSetup;
        $this->deploymentConfig = $deploymentConfig;
        $this->writer           = $writer;
        $this->loader           = $loader;
    }

    public function uninstall(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $otherMirasvitPackages = [];
        foreach ($this->packageService->getPackageList() as $package) {
            if (!in_array($package->getPackage(), ["mirasvit/module-core", "mirasvit/module-search-ultimate", "mirasvit/module-report"])) {
                $otherMirasvitPackages[] = $package->getPackage();
            }
        }

        if (empty($otherMirasvitPackages)) {
            array_unshift($this->nestedModules, 'Mirasvit_Core', 'Mirasvit_Report');
            $this->moduleTables[] = 'mst_core_urlrewrite';
        }

        $modulesToChange = $this->moduleStatus->getModulesToChange(false, $this->nestedModules);
        if (!empty($modulesToChange)) {
            throw new \LogicException("Please disable the following modules : \n". implode("\n", $modulesToChange));
        }

        foreach ($this->nestedModules as $module) {
            $this->patchApplier->revertDataPatches($module);
        }
        $installer = $setup;
        $installer->startSetup();
        $connection = $installer->getConnection();

        $this->removeModulesFromDb($this->nestedModules);
        $this->removeModulesFromDeploymentConfig($this->nestedModules);

        foreach ($this->moduleTables as $table) {
            $connection->dropTable($setup->getTable($table));
        }

        $installer->endSetup();

        $this->deploymentConfig->resetData();
    }

    public function removeModulesFromDb(array $modules)
    {
        foreach ($modules as $module) {
            $this->dataSetup->deleteTableRow('setup_module', 'module', $module);
        }
    }

    /**
     * Removes module from deployment configuration
     *
     * @param OutputInterface $output
     * @param string[] $modules
     * @return void
     */
    public function removeModulesFromDeploymentConfig(array $modules)
    {
        $configuredModules = $this->deploymentConfig->getConfigData(
            \Magento\Framework\Config\ConfigOptionsListConstants::KEY_MODULES
        );

        foreach ($modules as $module) {
            $configuredModules[$module] = 0;
        }

        $this->writer->saveConfig(
            [
                \Magento\Framework\Config\File\ConfigFilePool::APP_CONFIG =>
                    [\Magento\Framework\Config\ConfigOptionsListConstants::KEY_MODULES => $configuredModules]
            ],
            true
        );

        $this->deploymentConfig->resetData();
    }
}
