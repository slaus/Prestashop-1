<?php

/*
 *  This file is part of SplashSync Project.
 *
 *  Copyright (C) 2015-2019 Splash Sync  <www.splashsync.com>
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 *  For the full copyright and license information, please view the LICENSE
 *  file that was distributed with this source code.
 */

namespace Splash\Local\Objects\Order;

use PrestaShopCollection;
use Splash\Core\SplashCore      as Splash;

/**
 * Prestashop Hooks for Order & Invoices
 */
trait HooksTrait
{
    //====================================================================//
    // *******************************************************************//
    //  MODULE BACK OFFICE (ORDERS) HOOKS
    // *******************************************************************//
    //====================================================================//

    /**
     * This hook is called after a order is created
     *
     * @param array $params
     */
    public function hookactionObjectOrderAddAfter($params)
    {
        return $this->hookactionOrder($params["object"], SPL_A_CREATE, $this->l('Order Created on Prestashop'));
    }

    /**
     * This hook is called after a order is updated
     *
     * @param array $params
     */
    public function hookactionObjectOrderUpdateAfter($params)
    {
        return $this->hookactionOrder($params["object"], SPL_A_UPDATE, $this->l('Order Updated on Prestashop'));
    }

    /**
     * This hook is called after a order is deleted
     *
     * @param array $params
     */
    public function hookactionObjectOrderDeleteAfter($params)
    {
        return $this->hookactionOrder($params["object"], SPL_A_DELETE, $this->l('Order Deleted on Prestashop'));
    }

    //====================================================================//
    // *******************************************************************//
    //  MODULE BACK OFFICE (INVOICES) HOOKS
    // *******************************************************************//
    //====================================================================//

    /**
     * This hook is called after a Invoice is created
     *
     * @param array $params
     */
    public function hookactionObjectOrderInvoiceAddAfter($params)
    {
        return $this->hookactionInvoice($params["object"], SPL_A_CREATE, $this->l('Invoice Created on Prestashop'));
    }

    /**
     * This hook is called after a Invoice is updated
     *
     * @param array $params
     */
    public function hookactionObjectOrderInvoiceUpdateAfter($params)
    {
        return $this->hookactionInvoice($params["object"], SPL_A_UPDATE, $this->l('Invoice Updated on Prestashop'));
    }

    /**
     * This hook is called after a Invoice is deleted
     *
     * @param array $params
     */
    public function hookactionObjectOrderInvoiceDeleteAfter($params)
    {
        return $this->hookactionInvoice($params["object"], SPL_A_DELETE, $this->l('Invoice Deleted on Prestashop'));
    }

    /**
     * This function is called after each action on a order object
     *
     * @param object $order   Prestashop Order Object
     * @param string $action  Performed Action
     * @param string $comment Action Comment
     */
    private function hookactionOrder($order, $action, $comment)
    {
        $errors = 0;
        //====================================================================//
        // Retrieve Customer Id
        $orderId = null;
        if (isset($order->id_order)) {
            $orderId = $order->id_order;
        } elseif (isset($order->id)) {
            $orderId = $order->id;
        }
        //====================================================================//
        // Log
        $this->debugHook(__FUNCTION__, $orderId." >> ".$comment);
        //====================================================================//
        // Safety Check
        if (empty($orderId)) {
            Splash::log()->err("ErrLocalTpl", "Order", __FUNCTION__, "Unable to Read Order Id.");
        }
        //====================================================================//
        // Commit Update For Order
        $errors += !$this->doCommit("Order", $orderId, $action, $comment);
        if (SPL_A_UPDATE == $action) {
            //====================================================================//
            // Commit Update For Order Invoices
            $invoices = new PrestaShopCollection('OrderInvoice');
            $invoices->where('id_order', '=', $orderId);
            foreach ($invoices as $invoice) {
                $errors += !$this->doCommit("Invoice", $invoice->id, $action, $comment);
            }
        }

        return $errors?false:true;
    }

    /**
     * This function is called after each action on a order object
     *
     * @param object $order   Prestashop Order Object
     * @param string $action  Performed Action
     * @param string $comment Action Comment
     */
    private function hookactionInvoice($order, $action, $comment)
    {
        //====================================================================//
        // Retrieve Customer Id
        $objectId = null;
        if (isset($order->id_order_invoice)) {
            $objectId = $order->id_order_invoice;
        } elseif (isset($order->id)) {
            $objectId = $order->id;
        }
        //====================================================================//
        // Log
        $this->debugHook(__FUNCTION__, $objectId." >> ".$comment);
        //====================================================================//
        // Safety Check
        if (empty($objectId)) {
            Splash::log()->err("ErrLocalTpl", "Invoice", __FUNCTION__, "Unable to Read Order Invoice Id.");
        }
        //====================================================================//
        // Commit Update For Invoice
        return $this->doCommit("Invoice", $objectId, $action, $comment);
    }
}
