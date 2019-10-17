<?php
/**
 * @package Raghu_InventoryUpdate
 */
namespace Raghu\InventoryUpdate\Model\Import;

use Raghu\InventoryUpdate\Model\Import\Sku\RowValidatorInterface as ValidatorInterface;
use Magento\ImportExport\Model\Import\ErrorProcessing\ProcessingErrorAggregatorInterface;

class Sku extends \Magento\ImportExport\Model\Import\Entity\AbstractEntity
{
    const SKU = 'sku';

    const QTY = 'qty';

    const TABLE_ENTITY = 'cataloginventory_stock_item';

    /**
     * Validation failure message template definitions
     *
     * @var array
     */
    protected $_messageTemplates = [
        ValidatorInterface::ERROR_INVALID_SKU => 'sku invalid',
        ValidatorInterface::ERROR_INVALID_QTY => 'Qty invalid',
        ValidatorInterface::ERROR_SKU_NOT_FOUND => 'sku not found in product list'
    ];

    protected $_permanentAttributes = [self::SKU];

    /**
     * If we should check column names
     *
     * @var bool
     */
    protected $needColumnCheck = true;
    /**
     * Valid column names
     *
     * @array
     */
    protected $validColumnNames = [
        self::SKU,
        self::QTY
    ];
    /**
     * Need to log in import history
     *
     * @var bool
     */
    protected $logInHistory = true;

    protected $_validators = [];

    protected $_connection;

    protected $_resource;

    protected $_booleanOptions = array('0','1');

    protected $_productFactory;

    /**
     * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
     */
    public function __construct(
        \Magento\Framework\Json\Helper\Data $jsonHelper,
        \Magento\ImportExport\Helper\Data $importExportData,
        \Magento\ImportExport\Model\ResourceModel\Import\Data $importData,
        \Magento\Framework\App\ResourceConnection $resource,
        \Magento\ImportExport\Model\ResourceModel\Helper $resourceHelper,
        \Magento\Framework\Stdlib\StringUtils $string,
        ProcessingErrorAggregatorInterface $errorAggregator,
        \Magento\Catalog\Api\Data\ProductInterfaceFactory $productFactory
    ) {
        $this->jsonHelper = $jsonHelper;
        $this->_importExportData = $importExportData;
        $this->_resourceHelper = $resourceHelper;
        $this->_dataSourceModel = $importData;
        $this->_resource = $resource;
        $this->_productFactory = $productFactory;
        $this->_connection = $resource->getConnection(\Magento\Framework\App\ResourceConnection::DEFAULT_CONNECTION);
        $this->errorAggregator = $errorAggregator;

        $this->_initErrorTemplates();
    }

    public function getValidColumnNames()
    {
        return $this->validColumnNames;
    }

    /**
     * Entity type code getter.
     *
     * @return string
     */
    public function getEntityTypeCode()
    {
        return 'raghu_inventoryupdate';
    }

    /**
     * Row validation.
     *
     * @param array $rowData
     * @param int $rowNum
     * @return bool
     */
    public function validateRow(array $rowData, $rowNum)
    {
        $title = false;

        if (isset($this->_validatedRows[$rowNum])) {
            return !$this->getErrorAggregator()->isRowInvalid($rowNum);
        }

        $this->_validatedRows[$rowNum] = true;

        if (\Magento\ImportExport\Model\Import::BEHAVIOR_ADD_UPDATE == $this->getBehavior()) {

            $errorMessage = [];

            $skuValidate = new \Magento\Framework\Validator();
            $skuValidate->addValidator(new \Magento\Framework\Validator\NotEmpty());
            if (!isset($rowData[self::SKU]) || !$skuValidate->isValid($rowData[self::SKU])) {
                $errorMessage[] = ValidatorInterface::ERROR_INVALID_SKU;
            }

            $qtyValidate = new \Magento\Framework\Validator();
            $qtyValidate->addValidator(new \Magento\Framework\Validator\NotEmpty());
            if (!isset($rowData[self::QTY]) || !$qtyValidate->isValid($rowData[self::QTY])) {
                $errorMessage[] = ValidatorInterface::ERROR_INVALID_QTY;
            }

            if (!empty($errorMessage)) {
                $this->addRowError(implode(', ', $errorMessage), $rowNum);
                return false;
            }
        }

        return !$this->getErrorAggregator()->isRowInvalid($rowNum);
    }

    /**
     * Create Advanced price data from raw data.
     *
     * @throws \Exception
     * @return bool Result of operation.
     */
    protected function _importData()
    {

        $this->updateEntity();
        return true;
    }

    /**
     * Save and replace newsletter subscriber
     *
     * @return $this
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    protected function updateEntity()
    {
        $tableName = $this->_resource->getTableName(self::TABLE_ENTITY);
        $behavior = $this->getBehavior();
        $listTitle = [];

        $this->_connection->update($tableName, array('qty'=>0), $this->_connection->quoteInto(' stock_id = ?',1));

        while ($bunch = $this->_dataSourceModel->getNextBunch()) {
            $entityList = [];
            foreach ($bunch as $rowNum => $rowData) {

                if (!$this->validateRow($rowData, $rowNum)) {
                    $this->addRowError(ValidatorInterface::ERROR_TITLE_IS_EMPTY, $rowNum);
                    continue;
                }

                if ($this->getErrorAggregator()->hasToBeTerminated()) {
                    $this->getErrorAggregator()->addRowToSkip($rowNum);
                    continue;
                }

                $rowTtile= $rowData[self::SKU];
                $listTitle[] = $rowTtile;
                $entityList[$rowTtile][] = [
                    self::SKU => $rowData[self::SKU],
                    self::QTY => $rowData[self::QTY],
                    'rowNum' => $rowNum
                ];
            }

            foreach ($entityList as $key => $value) {

                $productCollection = $this->_productFactory->create()->getCollection();
                $productCollection->addAttributeToFilter('sku', $key);
                $product = $productCollection->getFirstItem();

                if (!empty($product->getData())) {

                    $product_update = array('product_id'=>$product->getEntityId(),'qty'=>$value[0][self::QTY],'stock_id' => 1);

                    $a = $product_update;
                    unset($a['qty']);
                    $this->_connection->update($tableName, $product_update, $this->_connection->quoteInto('product_id = ? AND stock_id = 1',$product->getEntityId()));

                } else {
                     $this->addRowError(ValidatorInterface::ERROR_SKU_NOT_FOUND, $value[0]['rowNum']);
                }
            }
        }
        return $this;
    }

    /**
     * Initialize Sku error templates
     */
    protected function _initErrorTemplates()
    {
        foreach ($this->_messageTemplates as $errorCode => $template) {
            $this->addMessageTemplate($errorCode, $template);
        }
    }
}
