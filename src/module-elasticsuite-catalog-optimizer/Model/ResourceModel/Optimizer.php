<?php
/**
 * DISCLAIMER
 * Do not edit or add to this file if you wish to upgrade Smile Elastic Suite to newer
 * versions in the future.
 *
 * @category  Smile
 * @package   Smile\ElasticsuiteCatalogOptimizer
 * @author    Fanny DECLERCK <fadec@smile.fr>
 * @copyright 2016 Smile
 * @license   Open Software License ("OSL") v. 3.0
 */
namespace Smile\ElasticsuiteCatalogOptimizer\Model\ResourceModel;

use Smile\ElasticsuiteCatalogOptimizer\Api\Data\OptimizerInterface;
use Smile\ElasticsuiteCatalogRule\Model\RuleFactory;

/**
 * Optimizer Resource Model
 *
 * @category Smile
 * @package  Smile\ElasticsuiteCatalogOptimizer
 * @author   Fanny DECLERCK <fadec@smile.fr>
 */
class Optimizer extends \Magento\Framework\Model\ResourceModel\Db\AbstractDb
{
    /**
     * @var RuleFactory
     */
    private $ruleFactory;

    /**
     * Class constructor
     *
     * @param \Magento\Framework\Model\ResourceModel\Db\Context $context        Context.
     * @param RuleFactory                                       $ruleFactory    Rule factory.
     * @param string                                            $connectionName Connection name.
     */
    public function __construct(
        \Magento\Framework\Model\ResourceModel\Db\Context $context,
        RuleFactory $ruleFactory,
        $connectionName = null
    ) {
        parent::__construct($context, $connectionName);
        $this->ruleFactory = $ruleFactory;
    }

    /**
     * Retrieve Search Containers for a given optimizer.
     *
     * @param int $optimizerId The optimizer Id
     *
     * @return array
     */
    public function getSearchContainersFromOptimizerId($optimizerId)
    {
        $connection = $this->getConnection();

        $select = $connection->select();

        $select->from($this->getTable(OptimizerInterface::TABLE_NAME_SEARCH_CONTAINER), OptimizerInterface::SEARCH_CONTAINER)
            ->where(OptimizerInterface::OPTIMIZER_ID . ' = ?', (int) $optimizerId);

        return $connection->fetchCol($select);
    }

    /**
     * Internal Constructor
     *
     * @SuppressWarnings(PHPMD.CamelCaseMethodName)
     */
    protected function _construct()
    {
        $this->_init(OptimizerInterface::TABLE_NAME, OptimizerInterface::OPTIMIZER_ID);
    }

    /**
     * Saves optimizer linking to search container after save
     *
     * @SuppressWarnings(PHPMD.CamelCaseMethodName)
     *
     * @param \Magento\Framework\Model\AbstractModel $object Optimizer to save
     *
     * @return $this
     */
    protected function _afterSave(\Magento\Framework\Model\AbstractModel $object)
    {
        parent::_afterSave($object);

        $this->saveSearchContainerRelation($object);

        return $this;
    }

    /**
     * Perform operations after object load, restore linking with optimizer and search container
     *
     * @SuppressWarnings(PHPMD.CamelCaseMethodName)
     *
     * @param \Magento\Framework\Model\AbstractModel $object Optimizer being loaded
     *
     * @return $this
     */
    protected function _afterLoad(\Magento\Framework\Model\AbstractModel $object)
    {
        if ($object->getOptimizerId()) {
            $searchContainers = $this->getSearchContainersFromOptimizerId($object->getOptimizerId());
            $object->setSearchContainer($searchContainers);
        }

         /* Using getter to force unserialize*/
        $object->getConfig();
        $object->getRuleCondition();

        return parent::_afterLoad($object);
    }

    /**
     * Perform operations before object save, serialize optimizer configuration
     *
     * @param \Magento\Framework\Model\AbstractModel $object Optimizer being loaded
     *
     * @return $this
     * @SuppressWarnings(PHPMD.CamelCaseMethodName)
     */
    protected function _beforeSave(\Magento\Framework\Model\AbstractModel $object)
    {
        if (is_array($object->getConfig())) {
            $object->setConfig(serialize($object->getConfig()));
        }

        $rule = $this->ruleFactory->create();
        $ruleCondition = $object->getRuleCondition();

        if (is_object($ruleCondition)) {
            $rule = $ruleCondition;
        } elseif (is_array($ruleCondition)) {
            $rule->getConditions()->loadArray($ruleCondition);
        }
        $object->setRuleCondition(serialize($rule->getConditions()->asArray()));

        return parent::_beforeSave($object);
    }

    /**
     * Saves relation between optimizer and search container
     *
     * @param \Magento\Framework\Model\AbstractModel $object Optimizer to save
     *
     * @return void
     */
    private function saveSearchContainerRelation(\Magento\Framework\Model\AbstractModel $object)
    {
        $searchContainers = $object->getSearchContainer();

        if (is_array($searchContainers) && (count($searchContainers) > 0)) {
            $searchContainerLinks = [];
            $deleteCondition = OptimizerInterface::OPTIMIZER_ID . " = " . $object->getOptimizerId();

            foreach ($searchContainers as $searchContainer) {
                $searchContainerLinks[] = [
                    OptimizerInterface::OPTIMIZER_ID     => (int) $object->getOptimizerId(),
                    OptimizerInterface::SEARCH_CONTAINER => (string) $searchContainer,
                ];
            }

            $this->getConnection()->delete($this->getTable(OptimizerInterface::TABLE_NAME_SEARCH_CONTAINER), $deleteCondition);
            $this->getConnection()->insertOnDuplicate(
                $this->getTable(OptimizerInterface::TABLE_NAME_SEARCH_CONTAINER),
                $searchContainerLinks
            );
        }
    }
}
