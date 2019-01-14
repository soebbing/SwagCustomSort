<?php
/**
 * (c) shopware AG <info@shopware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Shopware\SwagCustomSort\Sorter\SortDBAL\Handler;

use Shopware\Bundle\SearchBundle\SortingInterface;
use Shopware\Bundle\SearchBundle\StoreFrontCriteriaFactory;
use Shopware\Bundle\SearchBundleDBAL\QueryBuilder;
use Shopware\Bundle\SearchBundleDBAL\SortingHandler\PopularitySortingHandler;
use Shopware\Bundle\SearchBundleDBAL\SortingHandler\PriceSortingHandler;
use Shopware\Bundle\SearchBundleDBAL\SortingHandler\ProductNameSortingHandler;
use Shopware\Bundle\SearchBundleDBAL\SortingHandler\ReleaseDateSortingHandler;
use Shopware\Bundle\SearchBundleDBAL\SortingHandlerInterface;
use Shopware\Bundle\StoreFrontBundle\Struct\ShopContextInterface;
use Shopware\Components\ProductStream\CriteriaFactoryInterface;
use Shopware\SwagCustomSort\Components\Listing;
use Shopware\SwagCustomSort\Components\Sorting;
use Shopware\SwagCustomSort\Sorter\Sort\DragDropSorting;

class DragDropHandler implements SortingHandlerInterface
{
    const SORTING_RELEASE_DATE = 1;
    const SORTING_POPULARITY = 2;
    const SORTING_CHEAPEST_PRICE = 3;
    const SORTING_HIGHEST_PRICE = 4;
    const SORTING_PRODUCT_NAME_ASC = 5;
    const SORTING_PRODUCT_NAME_DESC = 6;
    const SORTING_SEARCH_RANKING = 7;
    const SORTING_STOCK_ASC = 9;
    const SORTING_STOCK_DESC = 10;

    /**
     * @var Sorting
     */
    private $sortingComponent;


    /**
     * @param Sorting $sortingComponent
     */
    public function __construct(Sorting $sortingComponent)
    {
        $this->sortingComponent = $sortingComponent;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsSorting(SortingInterface $sorting)
    {
        return $sorting instanceof DragDropSorting;
    }

    /**
     * {@inheritdoc}
     */
    public function generateSorting(SortingInterface $sorting, QueryBuilder $query, ShopContextInterface $context)
    {
        /** @var Listing $categoryComponent */
        $categoryComponent = Shopware()->Container()->get('swagcustomsort.listing_component');
        $categoryId = (int) Shopware()->Front()->Request()->getParam('sCategory');
        $linkedCategoryId = $categoryComponent->getLinkedCategoryId($categoryId);
        $hasCustomSort = $categoryComponent->hasCustomSort($categoryId);
        $baseSort = $categoryComponent->getCategoryBaseSort($categoryId);
        if ($hasCustomSort || $baseSort > 0) {
            $baseSorting = $categoryComponent->getCategoryBaseSort($categoryId);
        } else {
            $baseSorting = Shopware()->Config()->get('defaultListingSorting');
        }

        $aliasName = $this->getJoinAlias($query);

        if (!$aliasName) {
            return;
        }

        //apply 'plugin' order
       if ($linkedCategoryId) {
            $query->leftJoin(
                $aliasName,
                's_products_sort',
                'customSort',
                'customSort.productId = productCategory.articleID AND (customSort.categoryId = :sortCategoryId OR customSort.categoryId IS NULL)'
            );
            $query->setParameter('sortCategoryId', $linkedCategoryId);
        } else {
            $query->leftJoin(
                $aliasName,
                's_products_sort',
                'customSort',
                'customSort.productId = productCategory.articleID AND (customSort.categoryId = productCategory.categoryID OR customSort.categoryId IS NULL)'
            );
        }

        // Exclude passed products ids from result
        $sortedProductsIds = $this->sortingComponent->getSortedProductsIds();
        if ($sortedProductsIds) {
            $query->andWhere($query->expr()->notIn('product.id', $sortedProductsIds));
        }

        // For records with no 'plugin' order data use the default shopware order
        $handlerData = $this->getDefaultData($baseSorting);
        if ($handlerData) {
            $sorting->setDirection($handlerData['direction']);
            $handlerData['handler']->generateSorting($sorting, $query, $context);
        }
    }

    /**
     * @param int $defaultSort
     *
     * @throws \RuntimeException
     *
     * @return array
     */
    private function getDefaultData($defaultSort)
    {
        switch ($defaultSort) {
            case self::SORTING_RELEASE_DATE:
                return [
                    'handler' => new ReleaseDateSortingHandler(),
                    'direction' => 'DESC',
                ];
            case self::SORTING_POPULARITY:
                return [
                    'handler' => new PopularitySortingHandler(),
                    'direction' => 'DESC',
                ];
            case self::SORTING_CHEAPEST_PRICE:
                return [
                    'handler' => new PriceSortingHandler(Shopware()->Container()->get('shopware_searchdbal.search_price_helper_dbal')),
                    'direction' => 'ASC',
                ];
            case self::SORTING_HIGHEST_PRICE:
                return [
                    'handler' => new PriceSortingHandler(Shopware()->Container()->get('shopware_searchdbal.search_price_helper_dbal')),
                    'direction' => 'DESC',
                ];
            case self::SORTING_PRODUCT_NAME_ASC:
                return [
                    'handler' => new ProductNameSortingHandler(),
                    'direction' => 'ASC',
                ];
            case self::SORTING_PRODUCT_NAME_DESC:
                return [
                    'handler' => new ProductNameSortingHandler(),
                    'direction' => 'DESC',
                ];
            case self::SORTING_SEARCH_RANKING:
                return [
                    'handler' => new RatingSortingHandler(),
                    'direction' => 'DESC',
                ];
            case self::SORTING_STOCK_ASC:
                return [
                    'handler' => new StockSortingHandler(),
                    'direction' => 'ASC',
                ];
            case self::SORTING_STOCK_DESC:
                return [
                    'handler' => new StockSortingHandler(),
                    'direction' => 'DESC',
                ];

            default:
                throw new \RuntimeException('No matching sort found');
        }
    }

    private function getJoinAlias(QueryBuilder $query)
    {
        $joins = $query->getQueryPart('join');

        foreach ($joins as $join) {
            if (strncmp($join['joinAlias'], 'productCategory', 15) === 0) {
                return $join['joinAlias'];
            }
        }

        return null;
    }
}
