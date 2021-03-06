<?php
/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Magento to newer
 * versions in the future. If you wish to customize Magento for your
 * needs please refer to http://www.magentocommerce.com for more information.
 *
 * @category    Mage
 * @package     Mage_Sales
 * @copyright   Copyright (c) 2010 Magento Inc. (http://www.magentocommerce.com)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Flat sales order address resource
 *
 */
class Mage_Sales_Model_Mysql4_Order_Address extends Mage_Sales_Model_Mysql4_Order_Abstract
{
    /**
     * Event prefix
     *
     * @var string
     */
    protected $_eventPrefix = 'sales_order_address_resource';

    /**
     * Resource initialization
     */
    protected function _construct()
    {
        $this->_init('sales/order_address', 'entity_id');
    }

    /**
     * Return configuration for all attributes
     *
     * @return array
     */
    public function getAllAttributes()
    {
        $attributes = array(
            'city' => Mage::helper('sales')->__('City'),
            'company' => Mage::helper('sales')->__('Company'),
            'country_id' => Mage::helper('sales')->__('Country'),
            'email' => Mage::helper('sales')->__('Email'),
            'name' => Mage::helper('sales')->__('Name'),
            'region_id' => Mage::helper('sales')->__('State/Province'),
            'street' => Mage::helper('sales')->__('Street Address'),
            'telephone' => Mage::helper('sales')->__('Telephone'),
            'postcode' => Mage::helper('sales')->__('Zip/Postal Code')
        );
        asort($attributes);
        return $attributes;
    }
}
