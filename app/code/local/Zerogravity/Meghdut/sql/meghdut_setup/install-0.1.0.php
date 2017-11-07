<?php

$installer = $this;
$installer->startSetup();

$table = $installer->getConnection()->newTable($installer->getTable('meghdut'))
        ->addColumn('entity_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
            'unsigned' => true,
            'nullable' => false,
            'primary' => true,
            'identity' => true,
                ), 'ID')
        ->addColumn('order_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
            'nullable' => false,
                ), 'Order id')
        ->addColumn('order_date', Varien_Db_Ddl_Table::TYPE_TIMESTAMP, null, array(
            'nullable' => false,
                ), 'Order date')
        ->addColumn(
        'created_at', Varien_Db_Ddl_Table::TYPE_TIMESTAMP, null, array('nullable' => false), 'Creation Time');

$installer->getConnection()->createTable($table);
$installer->endSetup();


