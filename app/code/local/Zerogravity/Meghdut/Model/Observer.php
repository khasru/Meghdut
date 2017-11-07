<?php
class Zerogravity_Meghdut_Model_Observer {

    public function postOrders($observer) {
         $meghdut = Mage::getModel('meghdut/meghdut')->sendOrders();
    }

}
