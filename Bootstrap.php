<?php
/**
 * (c) shopware AG <info@shopware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Shopware\Components\Model\ModelManager;
use Shopware\CustomModels\CustomSort\ProductSort;
use Shopware\Models\Config\Element;
use Shopware\Models\Config\Form;
use Shopware\SwagCustomSort\Components\Listing;
use Shopware\SwagCustomSort\Components\Sorting;
use Shopware\SwagCustomSort\Subscriber\Backend;
use Shopware\SwagCustomSort\Subscriber\ControllerPath;
use Shopware\SwagCustomSort\Subscriber\Frontend;
use Shopware\SwagCustomSort\Subscriber\Resource;
use Shopware\SwagCustomSort\Subscriber\Sort;
use Shopware\SwagCustomSort\Subscriber\StoreFrontBundle;

/**
 * Class Shopware_Plugins_Frontend_SwagCustomSort_Bootstrap
 */
class Shopware_Plugins_Frontend_SwagCustomSort_Bootstrap extends Shopware_Components_Plugin_Bootstrap
{
    /**
     * Returns the plugin label which is displayed in the plugin information and
     * in the Plugin Manager.
     *
     * @return string
     */
    public function getLabel()
    {
        return 'Individuelle Sortierung';
    }

    /**
     * Returns the version of the plugin as a string
     *
     * @throws Exception
     *
     * @return string
     */
    public function getVersion()
    {
        $info = json_decode(file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . 'plugin.json'), true);

        if ($info) {
            return $info['currentVersion'];
        }
        throw new RuntimeException('The plugin has an invalid version file.');
    }

    /**
     * Install Plugin / Add Events
     *
     * @throws Exception
     *
     * @return bool
     */
    public function install()
    {
        if (!$this->assertMinimumVersion('5.0.0')) {
            throw new RuntimeException('This plugin requires Shopware 5.0.0 or a later version');
        }

        $this->subscribeEvents();
        $this->createDatabase();
        $this->createAttributes();
        $this->createMenu();
        $this->createForm($this->Form());

        return true;
    }

    /**
     * @param string $version
     *
     * @return array
     */
    public function update($version)
    {
        if (version_compare($version, '2.0.0', '<=')) {
            $sql = 'RENAME TABLE `s_articles_sort` TO `s_products_sort`;';
            Shopware()->Db()->query($sql);

            $sql = 'ALTER TABLE `s_products_sort` CHANGE `articleId` `productId` INT(11) NOT NULL;';
            Shopware()->Db()->query($sql);
        }

        return ['success' => true, 'invalidateCache' => $this->getInvalidateCacheArray()];
    }

    /**
     * Standard plugin enable method
     *
     * @return array
     */
    public function enable()
    {
        $sql = "UPDATE s_core_menu SET active = 1 WHERE controller = 'CustomSort';";
        Shopware()->Db()->query($sql);

        return ['success' => true, 'invalidateCache' => $this->getInvalidateCacheArray()];
    }

    /**
     * Standard plugin disable method
     *
     * @return array
     */
    public function disable()
    {
        $sql = "UPDATE s_core_menu SET active = 0 WHERE controller = 'CustomSort';";
        Shopware()->Db()->query($sql);

        return ['success' => true, 'invalidateCache' => $this->getInvalidateCacheArray()];
    }

    /**
     * Main entry point for the bonus system: Registers various subscribers to hook into shopware
     */
    public function onStartDispatch()
    {
        /** @var Enlight_Event_EventManager $eventManager */
        $eventManager = $this->get('events');
        $container = $this->get('service_container');

        $resourceSubscriber = new Resource($this->get('models'), $this->get('config'));
        $eventManager->addSubscriber($resourceSubscriber);

        /** @var Sorting $sortingComponent */
        $sortingComponent = $container->get('swagcustomsort.sorting_component');
        /** @var Listing $listingComponent */
        $listingComponent = $this->get('swagcustomsort.listing_component');

        $subscribers = [
            new Resource($this->get('models'), $this->get('config')),
            new ControllerPath($this->Path(), $this->get('template')),
            new Frontend($this),
            new Backend($this, $this->get('models')),
            new Sort($this->get('models'), $sortingComponent, $listingComponent),
            new StoreFrontBundle($container, $sortingComponent),
        ];

        foreach ($subscribers as $subscriber) {
            $eventManager->addSubscriber($subscriber);
        }
    }

    /**
     * Method to always register the custom models and the namespace for the auto-loading
     */
    public function afterInit()
    {
        $this->registerCustomModels();
        $this->Application()->Loader()->registerNamespace('Shopware\SwagCustomSort', $this->Path());
    }

    /**
     * Registers all necessary events.
     */
    private function subscribeEvents()
    {
        $this->subscribeEvent('Enlight_Controller_Front_StartDispatch', 'onStartDispatch');
    }

    /**
     * Creates the backend menu item.
     */
    private function createMenu()
    {
        $parent = $this->Menu()->findOneBy(['label' => 'Artikel']);

        $this->createMenuItem(
            [
                'label' => 'Custom sort',
                'controller' => 'CustomSort',
                'action' => 'Index',
                'active' => 0,
                'class' => 'sprite-blue-document-text-image',
                'parent' => $parent,
                'position' => 6,
            ]
        );
    }

    /**
     * Creates the plugin database tables over the doctrine schema tool.
     */
    private function createDatabase()
    {
        /** @var ModelManager $em */
        $em = $this->get('models');
        $tool = new \Doctrine\ORM\Tools\SchemaTool($em);

        $classes = [
            $em->getClassMetadata(ProductSort::class),
        ];

        try {
            $tool->createSchema($classes);
        } catch (\Doctrine\ORM\Tools\ToolsException $e) {
        }
    }

    /**
     * creates necessary attributes for categories
     */
    private function createAttributes()
    {
        /** @var \Shopware\Bundle\AttributeBundle\Service\CrudService $crudService */
        $crudService = $this->get('shopware_attribute.crud_service');

        $crudService->update('s_categories_attributes', 'swag_link', 'int(11)');
        $crudService->update('s_categories_attributes', 'swag_show_by_default', 'boolean', [], null, null, 0);
        $crudService->update('s_categories_attributes', 'swag_deleted_position', 'integer');
        $crudService->update('s_categories_attributes', 'swag_base_sort', 'integer');
    }

    /**
     * @param Form $form
     */
    private function createForm(Form $form)
    {
        $form->setElement(
            'text',
            'swagCustomSortName',
            [
                'label' => 'Name',
                'value' => 'Individuelle Sortierung',
                'description' => 'Die neue Sortierung ist unter diesem Namen im Frontend sichtbar.',
                'required' => true,
                'scope' => Element::SCOPE_SHOP,
            ]
        );

        $this->addFormTranslations(
            [
                'en_GB' => [
                    'swagCustomSortName' => [
                        'label' => 'Name',
                        'description' => 'The new sort will be visible in the frontend under this name.',
                        'value' => 'Custom Sorting',
                    ],
                ],
            ]
        );
    }

    /**
     * @return array
     */
    private function getInvalidateCacheArray()
    {
        return [
            'backend',
            'proxy',
            'config',
        ];
    }
}
