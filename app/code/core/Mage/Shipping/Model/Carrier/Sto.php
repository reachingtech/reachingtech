<?php

class Mage_Shipping_Model_Carrier_Sto
	extends Mage_Shipping_Model_Carrier_Abstract
	implements Mage_Shipping_Model_Carrier_Interface
{

	protected $_code = 'sto';
	protected $_default_condition_name = 'package_weight';

	protected $_conditionNames = array();

	public function __construct()
	{
		parent::__construct();
		foreach ($this->getCode('condition_name') as $k=>$v) {
			$this->_conditionNames[] = $k;
		}
	}

	/**
	 * Enter description here...
	 *
	 * @param Mage_Shipping_Model_Rate_Request $data
	 * @return Mage_Shipping_Model_Rate_Result
	 */
	public function collectRates(Mage_Shipping_Model_Rate_Request $request)
	{
		if (!$this->getConfigFlag('active')) {
			return false;
		}

		// exclude Virtual products price from Package value if pre-configured
		if (!$this->getConfigFlag('include_virtual_price') && $request->getAllItems()) {
			foreach ($request->getAllItems() as $item) {
				if ($item->getParentItem()) {
					continue;
				}
				if ($item->getHasChildren() && $item->isShipSeparately()) {
					foreach ($item->getChildren() as $child) {
						if ($child->getProduct()->isVirtual()) {
							$request->setPackageValue($request->getPackageValue() - $child->getBaseRowTotal());
						}
					}
				} elseif ($item->getProduct()->isVirtual()) {
					$request->setPackageValue($request->getPackageValue() - $item->getBaseRowTotal());
				}
			}
		}

		// Free shipping by qty
		$freeQty = 0;
		if ($request->getAllItems()) {
			foreach ($request->getAllItems() as $item) {
				if ($item->getProduct()->isVirtual() || $item->getParentItem()) {
					continue;
				}

				if ($item->getHasChildren() && $item->isShipSeparately()) {
					foreach ($item->getChildren() as $child) {
						if ($child->getFreeShipping() && !$child->getProduct()->isVirtual()) {
							$freeQty += $item->getQty() * ($child->getQty() - (is_numeric($child->getFreeShipping()) ? $child->getFreeShipping() : 0));
						}
					}
				} elseif ($item->getFreeShipping()) {
					$freeQty += ($item->getQty() - (is_numeric($item->getFreeShipping()) ? $item->getFreeShipping() : 0));
				}
			}
		}

		if (!$request->getConditionName()) {
			$request->setConditionName($this->getConfigData('condition_name') ? $this->getConfigData('condition_name') : $this->_default_condition_name);
		}

		 // Package weight and qty free shipping
		$oldWeight = $request->getPackageWeight();
		$oldQty = $request->getPackageQty();

		$request->setPackageWeight($request->getFreeMethodWeight());
		$request->setPackageQty($oldQty - $freeQty);

		$result = Mage::getModel('shipping/rate_result');
		$rate = $this->getRate($request);

		$request->setPackageWeight($oldWeight);
		$request->setPackageQty($oldQty);

		if (!empty($rate) && $rate['price'] >= 0) {
			$method = Mage::getModel('shipping/rate_result_method');

			$method->setCarrier('sto');
			$method->setCarrierTitle($this->getConfigData('title'));

			$method->setMethod('bestway');
			$method->setMethodTitle($this->getConfigData('name'));

			$shippingPrice = $this->getFinalPriceWithHandlingFee($rate['price']);

			$method->setPrice($shippingPrice);
			$method->setCost($rate['cost']);

			$result->append($method);
		}

		return $result;
	}

	public function getRate(Mage_Shipping_Model_Rate_Request $request)
	{
		return Mage::getResourceModel('shipping/carrier_sto')->getRate($request);
	}

	public function getCode($type, $code='')
	{
		$codes = array(

			'condition_name'=>array(
				'package_weight' => Mage::helper('shipping')->__('Weight vs. Destination'),
				'package_value'  => Mage::helper('shipping')->__('Price vs. Destination'),
				'package_qty'    => Mage::helper('shipping')->__('# of Items vs. Destination'),
			),

			'condition_name_short'=>array(
				'package_weight' => Mage::helper('shipping')->__('Weight (and above)'),
				'package_value'  => Mage::helper('shipping')->__('Order Subtotal (and above)'),
				'package_qty'    => Mage::helper('shipping')->__('# of Items (and above)'),
			),

		);

		if (!isset($codes[$type])) {
			throw Mage::exception('Mage_Shipping', Mage::helper('shipping')->__('Invalid Table Rate code type: %s', $type));
		}

		if (''===$code) {
			return $codes[$type];
		}

		if (!isset($codes[$type][$code])) {
			throw Mage::exception('Mage_Shipping', Mage::helper('shipping')->__('Invalid Table Rate code for type %s: %s', $type, $code));
		}

		return $codes[$type][$code];
	}

	/**
	 * Get allowed shipping methods
	 *
	 * @return array
	 */
	public function getAllowedMethods()
	{
		return array('bestway'=>$this->getConfigData('name'));
	}

}
