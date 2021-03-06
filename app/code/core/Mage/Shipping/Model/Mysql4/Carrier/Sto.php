<?php

class Mage_Shipping_Model_Mysql4_Carrier_Sto extends Mage_Core_Model_Mysql4_Abstract
{
	protected function _construct()
	{
		$this->_init('shipping/sto', 'pk');
	}

	public function getRate(Mage_Shipping_Model_Rate_Request $request)
	{
		$read = $this->_getReadAdapter();
		$write = $this->_getWriteAdapter();

		$select = $read->select()->from($this->getMainTable());
		$select->where(
			$read->quoteInto(" (dest_country_id=? ", $request->getDestCountryId()).
				$read->quoteInto(" AND dest_region_id=? ", $request->getDestRegionId()).
				$read->quoteInto(" AND dest_zip=?) ", $request->getDestPostcode()).

			$read->quoteInto(" OR (dest_country_id=? ", $request->getDestCountryId()).
				$read->quoteInto(" AND dest_region_id=? AND dest_zip='') ", $request->getDestRegionId()).

			$read->quoteInto(" OR (dest_country_id=? AND dest_region_id='0' AND dest_zip='') ", $request->getDestCountryId()).

			$read->quoteInto(" OR (dest_country_id=? AND dest_region_id='0' ", $request->getDestCountryId()).
				$read->quoteInto("  AND dest_zip=?) ", $request->getDestPostcode()).

			" OR (dest_country_id='0' AND dest_region_id='0' AND dest_zip='')"
		);

//        $select->where("(dest_zip=:zip)
//                     OR (dest_region_id=:region AND dest_zip='')
//                     OR (dest_country_id=:country AND dest_region_id='0' AND dest_zip='')
//                     OR (dest_country_id='0' AND dest_region_id='0' AND dest_zip='')");
		if (is_array($request->getConditionName())) {
			$i = 0;
			foreach ($request->getConditionName() as $conditionName) {
				if ($i == 0) {
					$select->where('condition_name=?', $conditionName);
				} else {
					$select->orWhere('condition_name=?', $conditionName);
				}
				$select->where('condition_value<=?', $request->getData($conditionName));
				$i++;
			}
		} else {
			$select->where('condition_name=?', $request->getConditionName());
			$select->where('condition_value<=?', $request->getData($request->getConditionName()));
		}
		$select->where('website_id=?', $request->getWebsiteId());

		$select->order('dest_country_id DESC');
		$select->order('dest_region_id DESC');
		$select->order('dest_zip DESC');
		$select->order('condition_value DESC');
		$select->limit(1);

		/*
		pdo has an issue. we cannot use bind
		*/
		$row = $read->fetchRow($select);
		return $row;
	}

	/**
	 * Upload table rate file and import data from it
	 *
	 * @param Varien_Object $object
	 * @return bool|Mage_Shipping_Model_Mysql4_Carrier_Sto
	 */
	public function uploadAndImport(Varien_Object $object)
	{
		if (!isset($_FILES['groups'])) {
			return false;
		}
		$csvFile = $_FILES['groups']['tmp_name']['sto']['fields']['import']['value'];

		if (!empty($csvFile)) {

			$csv = trim(file_get_contents($csvFile));

			$table = Mage::getSingleton('core/resource')->getTableName('shipping/sto');

			$websiteId = $object->getScopeId();
			$websiteModel = Mage::app()->getWebsite($websiteId);
			/*
			getting condition name from post instead of the following commented logic
			*/

			if (isset($_POST['groups']['sto']['fields']['condition_name']['inherit'])) {
				$conditionName = (string)Mage::getConfig()->getNode('default/carriers/sto/condition_name');
			} else {
				$conditionName = $_POST['groups']['sto']['fields']['condition_name']['value'];
			}

//            $conditionName = $object->getValue();
//            if ($conditionName{0} == '_') {
//                $conditionName = Mage::helper('core/string')->substr($conditionName, 1, strpos($conditionName, '/')-1);
//            } else {
//                $conditionName = $websiteModel->getConfig('carriers/sto/condition_name');
//            }
			$conditionFullName = Mage::getModel('shipping/carrier_sto')->getCode('condition_name_short', $conditionName);
			if (!empty($csv)) {
				$exceptions = array();
				$csvLines = explode("\n", $csv);
				$csvLine = array_shift($csvLines);
				$csvLine = $this->_getCsvValues($csvLine);
				if (count($csvLine) < 5) {
					$exceptions[0] = Mage::helper('shipping')->__('Invalid Table Rates File Format');
				}

				$countryCodes = array();
				$regionCodes = array();
				foreach ($csvLines as $k=>$csvLine) {
					$csvLine = $this->_getCsvValues($csvLine);
					if (count($csvLine) > 0 && count($csvLine) < 5) {
						$exceptions[0] = Mage::helper('shipping')->__('Invalid Table Rates File Format');
					} else {
						$countryCodes[] = $csvLine[0];
						$regionCodes[] = $csvLine[1];
					}
				}

				if (empty($exceptions)) {
					$data = array();
					$countryCodesToIds = array();
					$regionCodesToIds = array();
					$countryCodesIso2 = array();

					$countryCollection = Mage::getResourceModel('directory/country_collection')->addCountryCodeFilter($countryCodes)->load();
					foreach ($countryCollection->getItems() as $country) {
						$countryCodesToIds[$country->getData('iso3_code')] = $country->getData('country_id');
						$countryCodesToIds[$country->getData('iso2_code')] = $country->getData('country_id');
						$countryCodesIso2[] = $country->getData('iso2_code');
					}

					$regionCollection = Mage::getResourceModel('directory/region_collection')
						->addRegionCodeFilter($regionCodes)
						->addCountryFilter($countryCodesIso2)
						->load();

					foreach ($regionCollection->getItems() as $region) {
						$regionCodesToIds[$countryCodesToIds[$region->getData('country_id')]][$region->getData('code')] = $region->getData('region_id');
					}

					foreach ($csvLines as $k=>$csvLine) {
						$csvLine = $this->_getCsvValues($csvLine);

						if (empty($countryCodesToIds) || !array_key_exists($csvLine[0], $countryCodesToIds)) {
							$countryId = '0';
							if ($csvLine[0] != '*' && $csvLine[0] != '') {
								$exceptions[] = Mage::helper('shipping')->__('Invalid Country "%s" in the Row #%s', $csvLine[0], ($k+1));
							}
						} else {
							$countryId = $countryCodesToIds[$csvLine[0]];
						}

						if (!isset($countryCodesToIds[$csvLine[0]])
							|| !isset($regionCodesToIds[$countryCodesToIds[$csvLine[0]]])
							|| !array_key_exists($csvLine[1], $regionCodesToIds[$countryCodesToIds[$csvLine[0]]])) {
							$regionId = '0';
							if ($csvLine[1] != '*' && $csvLine[1] != '') {
								$exceptions[] = Mage::helper('shipping')->__('Invalid Region/State "%s" in the Row #%s', $csvLine[1], ($k+1));
							}
						} else {
							$regionId = $regionCodesToIds[$countryCodesToIds[$csvLine[0]]][$csvLine[1]];
						}

						if ($csvLine[2] == '*' || $csvLine[2] == '') {
							$zip = '';
						} else {
							$zip = $csvLine[2];
						}

						if (!$this->_isPositiveDecimalNumber($csvLine[3]) || $csvLine[3] == '*' || $csvLine[3] == '') {
							$exceptions[] = Mage::helper('shipping')->__('Invalid %s "%s" in the Row #%s', $conditionFullName, $csvLine[3], ($k+1));
						} else {
							$csvLine[3] = (float)$csvLine[3];
						}

						if (!$this->_isPositiveDecimalNumber($csvLine[4])) {
							$exceptions[] = Mage::helper('shipping')->__('Invalid Shipping Price "%s" in the Row #%s', $csvLine[4], ($k+1));
						} else {
							$csvLine[4] = (float)$csvLine[4];
						}

						$data[] = array('website_id'=>$websiteId, 'dest_country_id'=>$countryId, 'dest_region_id'=>$regionId, 'dest_zip'=>$zip, 'condition_name'=>$conditionName, 'condition_value'=>$csvLine[3], 'price'=>$csvLine[4]);
						$dataDetails[] = array('country'=>$csvLine[0], 'region'=>$csvLine[1]);
					}
				}
				if (empty($exceptions)) {
					$connection = $this->_getWriteAdapter();

					$condition = array(
						$connection->quoteInto('website_id = ?', $websiteId),
						$connection->quoteInto('condition_name = ?', $conditionName),
					);
					$connection->delete($table, $condition);

					foreach($data as $k=>$dataLine) {
						try {
							$connection->insert($table, $dataLine);
						} catch (Exception $e) {
							$exceptions[] = Mage::helper('shipping')->__('Duplicate Row #%s (Country "%s", Region/State "%s", Zip "%s" and Value "%s")', ($k+1), $dataDetails[$k]['country'], $dataDetails[$k]['region'], $dataLine['dest_zip'], $dataLine['condition_value']);
						}
					}
				}

				if (!empty($exceptions)) {
					throw new Exception( "\n" . implode("\n", $exceptions) );
				}
			}
		}
		return $this;
	}

	protected function _getCsvValues($string, $separator=",")
	{
		$elements = explode($separator, trim($string));
		for ($i = 0; $i < count($elements); $i++) {
			$nquotes = substr_count($elements[$i], '"');
			if ($nquotes %2 == 1) {
				for ($j = $i+1; $j < count($elements); $j++) {
					if (substr_count($elements[$j], '"') > 0) {
						// Put the quoted string's pieces back together again
						array_splice($elements, $i, $j-$i+1, implode($separator, array_slice($elements, $i, $j-$i+1)));
						break;
					}
				}
			}
			if ($nquotes > 0) {
				// Remove first and last quotes, then merge pairs of quotes
				$qstr =& $elements[$i];
				$qstr = substr_replace($qstr, '', strpos($qstr, '"'), 1);
				$qstr = substr_replace($qstr, '', strrpos($qstr, '"'), 1);
				$qstr = str_replace('""', '"', $qstr);
			}
			$elements[$i] = trim($elements[$i]);
		}
		return $elements;
	}

	protected function _isPositiveDecimalNumber($n)
	{
		return preg_match ("/^[0-9]+(\.[0-9]*)?$/", $n);
	}

}
