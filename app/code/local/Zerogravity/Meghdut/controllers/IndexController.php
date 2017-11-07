<?php

class Zerogravity_Meghdut_IndexController extends Mage_Core_Controller_Front_Action {

    public function indexAction() {        
        echo "<pre>";        
        $orderId  = (int) $this->getRequest()->getParam('id');              
        //$_order=Mage::getModel('meghdut/meghdut')->getOrderById(100014919);
        //$_order=Mage::getModel('meghdut/meghdut')->getOrderById(12274);        
        if($orderId){
            $_order=Mage::getModel('meghdut/meghdut')->getOrderById($orderId);        
            //$_order=Mage::getModel('meghdut/meghdut')->processOrderJsonData($orderId);        
        }
        
        print_r($_order);
        
    }
    public function sendordersAction() {
        $orderId  = (int) $this->getRequest()->getParam('id');              
        $_order = Mage::getModel('meghdut/meghdut')->sendOrders($orderId);
        print_r($_order);
    }

    public function customerdataAction() {        
        echo "<pre>";        
        $custId  = (int) $this->getRequest()->getParam('id');              
        $customer = Mage::getModel('customer/customer')->load($custId);
        echo Mage::helper('core')->jsonEncode($customer);
    }
    
    public function productdataAction() {        
        echo "<pre>";        
        $pId  = (int) $this->getRequest()->getParam('id');              
        $product = Mage::getModel('catalog/product')->load($pId);
        echo Mage::helper('core')->jsonEncode($product);
    }

}
