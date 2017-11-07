<?php

class Zerogravity_Meghdut_Model_Meghdut extends Mage_Core_Model_Abstract {

    public function _construct() {
        parent::_construct();
        $this->_init('meghdut/meghdut');
    }

    public function getOrderById($_orderId) {
        $order = Mage::getModel('sales/order')->load($_orderId);
        $_orderData = $this->processOrderJsonData($order);
        $orderData['data'] = $_orderData;
        $orderData = Mage::helper('core')->jsonEncode($orderData);
        $orderData = json_encode($orderData);
        $result = $this->postOrderToMegdut($orderData);
        if ($result) {
            $this->saveOrder($order);
        }
    }

    public function processOrderJsonData($order) {
        $customer = Mage::getModel('customer/customer');

        $paymentName = $this->getPaymentByCode($order->getPayment()->getMethod());
        $delivery_time = "";

        foreach ($order->getShipmentsCollection() as $shipment) {
            $delivery_time = $shipment->getCreatedAt();
        }

        $orderCustAttr = $this->getList($order);


        if ($order->getId()) {
            $timestamp = date('Y-m-d\TH:i:s\Z', strtotime($order->getCreatedAt()));
            $_orderData['order'] = $order->getIncrementId();
            $_orderData['timestamp'] = $timestamp;
            $_orderData['quantity'] = (int) $order->getTotalQtyOrdered();
            $_orderData['total'] = (float) $order->getGrandTotal();
            $_orderData['payment_method'] = $paymentName;
            $_orderData['status'] = $order->getStatus();            
            if ($delivery_time) {
                $_orderData['delivery_time'] = date('Y-m-d\TH:i:s\Z', strtotime($delivery_time));
            }
            $shipping_time = $this->getDateDiff($_orderData['delivery_time'], $_orderData['timestamp']);
            if ($delivery_time) {
                $_orderData['shipping_time'] = $shipping_time;
            }

            if (array_key_exists('order_source', $orderCustAttr)) {
                $_orderData['source'] = $orderCustAttr['order_source'];
            }
            if (array_key_exists('shipment_by', $orderCustAttr)) {
                $_orderData['delivery_partner'] = $orderCustAttr['shipment_by'];
            }
            if (array_key_exists('cancel_reason', $orderCustAttr)) {
                $_orderData['cancel_reason'] = $orderCustAttr['cancel_reason'];
            }


            if ($customer->load($order->getBillingAddress()->getCustomerId())->getGender() == 1) {
                $gender = 'Male';
            } elseif ($customer->load($order->getBillingAddress()->getCustomerId())->getGender() == 2) {
                $gender = 'Female';
            } else {
                $gender = '';
            }


            if ($order->getBillingAddress()->getCustomerId()) {
                $_orderData['customer'] = array('id' => $order->getBillingAddress()->getCustomerId(), 'name' => $order->getCustomerName(), 'district' => $order->getBillingAddress()->getCity(), 'area' => $order->getBillingAddress()->getArea(), 'mobile' => $order->getBillingAddress()->getTelephone(), 'gender' => $gender);
            }

            $_orderData['product'] = $this->getOrderItem($order);

            return $_orderData;
        } else {
            echo "Order Not found!";
        }
    }

    protected function getOrderItem($order) {

        $items = $order->getAllItems();
        $_item = array();
        foreach ($items as $item) {
            if ($item->getParentItemId() > 1)
                continue;
            $_items['id'] = $item->getProductId();
            $_items['name'] = $item->getName();
            $_items['sku'] = $item->getSku();
            $_items['category'] = $this->getCategoryNames($item->getProductId());
            $_items['vendor'] = $this->getProductAttrValue($item->getProductId());
            $_items['price'] = (float) $item->getPrice();
            $_items['quantity'] = (int) $item->getQtyOrdered();
            $_items['subtotal'] = (float) $item->getRowTotal();
            $_items['total_revenue'] = (float) $this->getTotalRevenue($item);
            $_items['total_profit'] = (float) $this->getTotalProfit($item);
            $_item[] = $_items;
        }
        return $_item;
    }

    public function getTotalRevenue($item) {
        return $total_revenue = ($item->getBaseRowTotal() + $item->getBaseTaxAmount() + $item->getBaseHiddenTaxAmount() + $item->getBaseWeeeTaxAppliedAmount()) - $item->getBaseDiscountAmount();
    }

    public function getTotalProfit($item) {
        return $total_revenue = (($item->getBaseRowTotal() * $item->getQtyOrdered()) + $item->getBaseTaxAmount() + $item->getBaseHiddenTaxAmount() + $item->getBaseWeeeTaxAppliedAmount()) - $item->getBaseDiscountAmount() - ($item->getBaseCost() * $item->getQtyOrdered());
    }

    public function getProductAttrValue($_pid) {
        $attrVal = "";
        $product = Mage::getModel('catalog/product')->load($_pid);
        if ($product->getManufacturer()) {
            $attrVal = $product->getAttributeText('manufacturer');
        }
        return $attrVal;
    }

    public function postOrderToMegdut($data, $bulk = false) {
        $streamId = "597f21661d41c82ab11b4465";        
        $authkey = "3dd5ffc61b13e9e59ff49fe5015b91f5";
        if ($bulk == true) {
            $url = "https://dashboard.meghdut.io/api/streams/" . $streamId . "/records/bulk";
        } else {
            $url = "https://dashboard.meghdut.io/api/streams/" . $streamId . "/records";
        }

        $headers = array(
            'Content-Type:application/json',
            'Authorization: secret ' . $authkey
        );


        $post = curl_init($url);
        // curl_setopt($post, CURLOPT_URL, $url);
        curl_setopt($post, CURLOPT_HEADER, 1);
        curl_setopt($post, CURLOPT_POST, 1);
        curl_setopt($post, CURLOPT_TIMEOUT, 60);
        curl_setopt($post, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($post, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($post, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($post, CURLOPT_POSTFIELDS, $data);
        curl_setopt($post, CURLOPT_HTTPHEADER, $headers);

        $result = curl_exec($post);
        echo "<pre>";
        print_r($result);
        //$info = curl_getinfo($post);                
        $response = curl_getinfo($post, CURLINFO_HTTP_CODE);
        curl_close($post);
        if (200 === $response) {
            return true;
        } else {
            //die('Error: "' . curl_error($post) . '" - Code: ' . curl_errno($post));
            //$errors = curl_error($post);        
            return false;
        }
        //return $result;    
    }

    protected function saveOrder($order) {
		$createAt=Mage::getModel('core/date')->gmtDate('Y-m-d H:i:s');
        $meghdut = Mage::getModel('meghdut/meghdut');
        $meghdut->setOrderId($order->getId());
        $meghdut->setOrderDate($order->getCreatedAt());
		$meghdut->setCreatedAt($createAt);	
        $meghdut->save();
    }

    protected function saveOrders($oreders) {
        //$meghdut = Mage::getModel('meghdut/meghdut');
        $startOrderId="";
        $createAt = Mage::getModel('core/date')->gmtDate('Y-m-d H:i:s');
        foreach ($oreders as $order) {
            $meghdut = Mage::getModel('meghdut/meghdut');
            $meghdut->setOrderId($order->getId());
            $meghdut->setOrderDate($order->getCreatedAt());
            $meghdut->setCreatedAt($createAt);
            $meghdut->save();
            $meghdut->clearInstance();
           $startOrderId= $order->getId();
        }
        Mage::log($startOrderId, null, 'megdut.log');
    }

    public function sendOrders($_orderId = 0) {
        $startOrderId = 13406;
        if ($_orderId) {
            $startOrderId = $_orderId;
        }

//        $lastItem = Mage::getModel('meghdut/meghdut')->getCollection()
//                        // ->addFieldToFilter('order_id')
//                        ->setOrder('order_id', 'desc')
//                        ->setPageSize(1)->getFirstItem();
//        if (count($lastItem) > 0) {
//            $startOrderId = $lastItem->getData('order_id');
//        }
        //Mage::log($startOrderId, null, 'megdut.log');
        $collection = Mage::getModel('sales/order')->getCollection()
                ->addFieldToFilter('entity_id', array('gt' => $startOrderId))
                //->addFieldToFilter('state', Mage_Sales_Model_Order::STATE_COMPLETE)
                ->addFieldToFilter('state', array(
                    'in' => array(Mage_Sales_Model_Order::STATE_COMPLETE, Mage_Sales_Model_Order::STATE_CANCELED),
                ))
                ->setPageSize(100)
        ;
        $collection->getSelect()->joinLeft(array("m" => 'meghdut'), "m.order_id = main_table.entity_id", array())
                ->where("m.order_id IS NULL")
        ;
        //$collection->getSelect()->group('main_table.entity_id');
        

        if (count($collection) > 0) {
            foreach ($collection as $order) {
                $_orderData['data'] = $this->processOrderJsonData($order);
                $_orders[] = $_orderData;
                $ordersData[] = $order;
            }            
            
            $orderData = Mage::helper('core')->jsonEncode($_orders);
            $result = $this->postOrderToMegdut($orderData, $bulk = true);

            if ($result) {
                $this->saveOrders($collection);
            }
        }
    }

    public function getPaymentByCode($code) {
        $payments = Mage::getSingleton('payment/config')->getAllMethods();
        foreach ($payments as $paymentCode => $paymentModel) {
            if ($code == $paymentCode) {
                return $paymentTitle = Mage::getStoreConfig('payment/' . $paymentCode . '/title');
            } else {
                continue;
            }
        }
        return "";
    }

    public function getCategoryNames($productId = "") {
        $categoryModel = Mage::getModel('catalog/category');
        $categoryResource = $categoryModel->getResource();
        $name = $categoryResource->getAttribute('name');
        $nameTable = $name->getBackendTable();
        $defaultStoreId = Mage_Core_Model_App::ADMIN_STORE_ID;

        $_catNames = array();
        if ($productId) {
            $connection = Mage::getSingleton('core/resource')->getConnection('core_read');
            $query = "Select ccp.category_id, ccev.value from catalog_category_product as ccp ";
            $query .= " LEFT JOIN catalog_category_entity_varchar AS ccev ON ccev.entity_id = ccp.category_id and   ccev.attribute_id = {$name->getAttributeId()} AND
                                    ccev.entity_type_id = {$name->getEntityTypeId()}";
            // $query .= " LEFT JOIN catalog_category_flat_store_1 AS ccfs1 ON ccev.entity_id = ccfs1.entity_id ";
            $query .= "   where product_id= '" . $productId . "'  and (ccev.store_id = {$defaultStoreId} OR ccev.store_id IS NULL)";

            $result = $connection->query($query);
            foreach ($result as $cat) {
//                if ($_catNames != null)
//                    $_catNames .= ", ";
//                $_catNames .= $cat['value'];
                $_catNames[] = $cat['value'];
            }
        }
        return $_catNames;
    }

    /**
     * Returns cached attribute if available, else loads attribute
     *
     * @param $entityType
     * @param $attrCode
     * @return Mage_Eav_Model_Entity_Attribute_Abstract
     */
    public function getCachedAttr($entityType, $attrCode) {
        //retrieve the attribute object
        if ($cachedAttr = Mage::registry("eavjoin_{$entityType}_{$attrCode}")) {
            $attr = $cachedAttr;
        } else {
            $attr = Mage::getModel("eav/config")->getAttribute($entityType, $attrCode);
            Mage::register("eavjoin_{$entityType}_{$attrCode}", $attr);
        }
        return $attr;
    }

    public function joinEAV($attrCode, $entityType, $joinField, $collection, $storeID = 0) {
        $attr = $this->getCachedAttr($entityType, $attrCode);
        $attrID = $attr->getAttributeId();
        $attrTable = $attr->getBackendTable();
        if ($attr->getBackendType() == 'static') {
            $joinSql = "{$attrTable}.entity_id={$joinField}";
            //don't use an alias for static table, use table name
            $alias = $attrTable;
            //if static join all fields
            $fields = '*';
            //create alias for current field to add as expression attribute
//            $fieldAlias = $entityType . '_' . $attrCode;
            $fieldAlias = $attrCode;
        } else {
            //for regular attribute, create alias for table (table might be joined multiple times)
            //$alias = $entityType . '_' . $attrCode;
            $alias = $attrCode;
            $dbRow = 'value';
            $joinSql = "{$alias}.entity_id={$joinField} AND {$alias}.store_id={$storeID} AND {$alias}.attribute_id={$attrID}";
            //if non-static, create alias for value field in join
            $fields = array($alias => "{$alias}.{$dbRow}");
        }

        //if field or static table is already joined, don't join again
        if (stristr($collection->getSelectSql(), "`{$alias}`")) {
            $dontJoin = true;
        }
        //if field is static, create field alias for display in grid / collection
        if ($attr->getBackendType() == 'static') {
            //$collection->addExpressionFieldToSelect($fieldAlias, "{$attrTable}.{$attrCode}");
            $collection->addExpressionFieldToSelect($fieldAlias, "{$attrTable}.{$attrCode}");
        }
        if ($dontJoin) {
            return $this;
        }
        //join select
        $collection
                ->getSelect()
                ->joinLeft(
                        array($alias => $attrTable), $joinSql, $fields
        );
        return $this;
    }

//     protected function _beforeSave() {
//        parent::_beforeSave();
//        $now = Mage::getSingleton('core/date')->gmtDate();
//        if ($this->isObjectNew()) {
//            $this->setCreatedAt($now);
//        }
//        //$this->setUpdatedAt($now);
//        return $this;
//    }


    public function getList($order) {

        $list = array();

        $collection = Mage::getModel('eav/entity_attribute')->getCollection();
        $collection->addFieldToFilter('entity_type_id', Mage::getModel('eav/entity')->setType('order')->getTypeId());
        $collection->getSelect()->order('checkout_step');
        $collection->getSelect()->order('sorting_order');
        $attributes = $collection->load();

        $orderAttributes = Mage::getModel('amorderattr/attribute')->load($order->getId(), 'order_id');
        $currentStore = $order->getStoreId();
        if ($attributes->getSize()) {
            foreach ($attributes as $attribute) {
                $storeIds = explode(',', $attribute->getData('store_ids'));
                if (!in_array($currentStore, $storeIds) && !in_array(0, $storeIds)) {
                    continue;
                }

                $value = '';
                switch ($attribute->getFrontendInput()) {
                    case 'select':
                    case 'boolean':
                        $options = $attribute->getSource()->getAllOptions(true, true);
                        foreach ($options as $option) {
                            if ($option['value'] == $orderAttributes->getData($attribute->getAttributeCode())) {
                                $value = $option['label'];
                                break;
                            }
                        }
                        break;
                    case 'date':
                        $value = $orderAttributes->getData($attribute->getAttributeCode());
                        if ($value === '0000-00-00' || $value === '0000-00-00 00:00:00' || $value === '1970-01-01 00:00:00' || $value === '1970-01-01') {
                            $value = '';
                            break;
                        }
                        $format = Mage::app()->getLocale()->getDateTimeFormat(
                                Mage_Core_Model_Locale::FORMAT_TYPE_MEDIUM
                        );
                        if (!$value) {
                            break;
                        }
                        if ('time' == $attribute->getNote()) {
                            $value = Mage::app()->getLocale()->date($value, Varien_Date::DATETIME_INTERNAL_FORMAT, null, false)->toString($format);
                        } else {
                            $format = trim(str_replace(array('m', 'a', 'H', ':', 'h', 's'), '', $format));
                            $value = Mage::app()->getLocale()->date($value, Varien_Date::DATE_INTERNAL_FORMAT, null, false)->toString($format);
                        }
                        break;
                    case 'radios':
                        $options = $attribute->getSource()->getAllOptions(true, true);
                        $attributeSelect = $orderAttributes->getData($attribute->getAttributeCode());
                        foreach ($options as $option) {
                            if ($option['value'] == $attributeSelect) {
                                $value = $option['label'];
                                break;
                            }
                        }
                        break;
                    case 'checkboxes':
                        $options = $attribute->getSource()->getAllOptions(true, true);
                        $attributeList = $orderAttributes->getData($attribute->getAttributeCode());
                        $attributeList = explode(',', $attributeList);
                        $valueAttribute = array();
                        foreach ($attributeList as $index => $values) {
                            foreach ($options as $option) {
                                if ($option['value'] == $values) {
                                    $valueAttribute[] = $option['label'];
                                }
                            }
                        }
                        $value = implode(', ', $valueAttribute);
                        break;
                    case 'file':
                        $value = $orderAttributes->getData($attribute->getAttributeCode());
                        if ($value) {
                            $path = Mage::getBaseDir('media') . DS . 'amorderattr' . DS . 'original' . $value;
                            $url = Mage::getBaseUrl('media') . 'amorderattr' . DS . 'original' . $value;
                            if (file_exists($path)) {
                                $pos = strrpos($value, "/");
                                if ($pos) {
                                    $value = substr($value, $pos + 1, strlen($value));
                                }
                                $value = '<a href="' . $url . '" download target="_blank">' . $value . '</a>';
                            } else {
                                $value = '';
                            }
                        }
                        break;
                    default:
                        $value = $orderAttributes->getData($attribute->getAttributeCode());
                        break;
                }
                if ('file' != $attribute->getFrontendInput()) {
                    $value = nl2br(htmlentities(preg_replace('/\$/', '\\\$', $value), ENT_COMPAT, "UTF-8"));
                }

                if ($attribute->getFrontendLabel() && $value) {
                    //$list[$attribute->getFrontendLabel()] = str_replace('$', '\$', $value);
                    $list[$attribute->getAttributeCode()] = str_replace('$', '\$', $value);
                }
            }
        }
        return $list;
    }

    public function getDateDiff($_orderData, $_deliveryData) {
        $deliveryTime = "";
        if ($_orderData && $_deliveryData) {
            $date1 = date_create($_orderData);
            $date2 = date_create($_deliveryData);
            $diff = date_diff($date1, $date2);
//            if ($diff->y > 0)
//                $deliveryTime .= $diff->y . ' years ';
//            if ($diff->m > 0)
//                $deliveryTime .= $diff->m . ' months ';
//            if ($diff->d > 0)
//                $deliveryTime .= $diff->d . ' days ';
//            if ($diff->h > 0)
//                $deliveryTime .= $diff->h . ' hours ';
//                
            $deliveryTime = $diff->d;
            if ($diff->h > 12) {
                $deliveryTime = $deliveryTime + 1;
            }
            //echo $diff->format("%R%a");
            //echo $diff->format("%a");        
        }
        return $deliveryTime;
    }

}
