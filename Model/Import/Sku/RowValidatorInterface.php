<?php
/**
 * @package Raghu_InventoryUpdate
 */
namespace Raghu\InventoryUpdate\Model\Import\Sku;

interface RowValidatorInterface extends \Magento\Framework\Validator\ValidatorInterface
{
    const ERROR_INVALID_SKU = 'invalidSku';

    const ERROR_INVALID_QTY = 'invalidQty';

    const ERROR_SKU_NOT_FOUND = 'existNotfound';

    /**
     * Initialize validator
     *
     * @return $this
     */
    public function init($context);
}
