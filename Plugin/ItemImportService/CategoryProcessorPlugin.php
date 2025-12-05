<?php
/**
 * Copyright © Soft Commerce Ltd. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace SoftCommerce\PlentyScommerceCanonical\Plugin\ItemImportService;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use SoftCommerce\PlentyCategory\Api\Data\CategoryInterface;
use SoftCommerce\PlentyCategory\Model\ResourceModel;
use SoftCommerce\PlentyCategoryProfile\Model\CategoryTreeManagementInterface;
use SoftCommerce\PlentyItemProfile\Model\ItemImportService\Processor\Category;
use SoftCommerce\PlentyItemProfile\Model\Utils\CategoryManagementInterface;
use SoftCommerce\PlentyProfile\Model\Config\StoreConfigInterface;

/**
 * @inheritdoc
 * Class CategoryProcessorPlugin used to intersect
 * category import process to
 * provide data for attribute: "product_primary_category".
 */
class CategoryProcessorPlugin
{
    private const TARGET_ATTRIBUTE = 'product_primary_category';

    /**
     * @var Category|null
     */
    private ?Category $subject = null;

    /**
     * @param CategoryTreeManagementInterface $categoryTreeManagement
     * @param CategoryManagementInterface $categoryManagement
     * @param ResourceModel\Category $resource
     */
    public function __construct(
        private readonly CategoryTreeManagementInterface $categoryTreeManagement,
        private readonly CategoryManagementInterface $categoryManagement,
        private readonly ResourceModel\Category $resource
    ) {}

    /**
     * @param Category $subject
     * @param $result
     * @return mixed
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function afterExecute(Category $subject, $result)
    {
        $this->subject = $subject;

        if (!$categoryPath = $this->getCategoryPath()) {
            return $result;
        }

        $categoryIds = $this->categoryManagement->upsertCategories($categoryPath, ',');

        if ($categoryId = current($categoryIds)) {
            $subject->getContext()->getRequestStorage()->setData([$categoryId], self::TARGET_ATTRIBUTE);
        }

        return $result;
    }

    /**
     * @return string|null
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    private function getCategoryPath(): ?string
    {
        $context = $this->subject->getContext();
        $storeMap = $context->storeConfig()->getDefaultStoreMapping();
        $clientId = (int) $storeMap->getData(StoreConfigInterface::CLIENT_ID);

        if (!$clientId
            || !$defaultCategoryId = $context->getVariation()->getVariationDefaultCategory($clientId)
        ) {
            return null;
        }

        $categories = $this->categoryTreeManagement->getResponseStorage()->getData();
        if ($path = $categories[$defaultCategoryId] ?? null) {
            return $path;
        }

        $category = $this->resource->getCategoryData([$defaultCategoryId], [CategoryInterface::PATH]);
        $category = current($category ?: []) ?: [];
        return $category[CategoryInterface::PATH] ?? '';
    }
}
