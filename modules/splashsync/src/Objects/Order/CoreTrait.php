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

//====================================================================//
// Prestashop Static Classes
use Customer;
use Splash\Local\Objects\Invoice;
use Translate;

/**
 * Access to Orders Core Fields
 */
trait CoreTrait
{
    /**
     * Build Core Fields using FieldFactory
     */
    private function buildCoreFields()
    {
        //====================================================================//
        // Customer Object
        $this->fieldsFactory()->create(self::objects()->encode("ThirdParty", SPL_T_ID))
            ->Identifier("id_customer")
            ->Name(Translate::getAdminTranslation("Customer ID", "AdminCustomerThreads"))
            ->isRequired();
        if ("Splash\\Local\\Objects\\Invoice" === get_class($this)) {
            $this->fieldsFactory()->MicroData("http://schema.org/Invoice", "customer");
        } else {
            $this->fieldsFactory()->MicroData("http://schema.org/Organization", "ID");
        }

        //====================================================================//
        // Customer Email
        $this->fieldsFactory()->create(SPL_T_EMAIL)
            ->Identifier("email")
            ->Name(Translate::getAdminTranslation("Email address", "AdminCustomers"))
            ->MicroData("http://schema.org/ContactPoint", "email")
            ->isReadOnly();

        //====================================================================//
        // Reference
        $this->fieldsFactory()->create(SPL_T_VARCHAR)
            ->Identifier("reference")
            ->Name(Translate::getAdminTranslation("Reference", "AdminOrders"))
            ->MicroData("http://schema.org/Order", "orderNumber")
            ->AddOption("maxLength", 8)
            ->isRequired()
            ->isListed();

        //====================================================================//
        // Order Date
        $this->fieldsFactory()->create(SPL_T_DATE)
            ->Identifier("order_date")
            ->Name(Translate::getAdminTranslation("Date", "AdminProducts"))
            ->MicroData("http://schema.org/Order", "orderDate")
            ->isReadOnly()
            ->isListed();
    }

    /**
     * Read requested Field
     *
     * @param string $key       Input List Key
     * @param string $fieldName Field Identifier / Name
     */
    private function getCoreFields($key, $fieldName)
    {
        //====================================================================//
        // READ Fields
        switch ($fieldName) {
            //====================================================================//
            // Direct Readings
            case 'reference':
                if ($this instanceof Invoice) {
                    $this->getSimple($fieldName, "Order");
                } else {
                    $this->getSimple($fieldName);
                }

                break;
            //====================================================================//
            // Customer Object Id Readings
            case 'id_customer':
                if ($this instanceof Invoice) {
                    $this->out[$fieldName] = self::objects()->encode("ThirdParty", $this->Order->{$fieldName});
                } else {
                    $this->out[$fieldName] = self::objects()->encode("ThirdParty", $this->object->{$fieldName});
                }

                break;
            //====================================================================//
            // Customer Email
            case 'email':
                if ($this instanceof Invoice) {
                    $customerId = $this->Order->id_customer;
                } else {
                    $customerId = $this->object->id_customer;
                }
                //====================================================================//
                // Load Customer
                $customer = new Customer($customerId);
                if ($customer->id != $customerId) {
                    $this->out[$fieldName] = null;

                    break;
                }
                $this->out[$fieldName] = $customer->email;

                break;
            //====================================================================//
            // Order Official Date
            case 'order_date':
                $this->out[$fieldName] = date(SPL_T_DATECAST, (int) strtotime($this->object->date_add));

                break;
            default:
                return;
        }

        unset($this->in[$key]);
    }

    /**
     * Write Given Fields
     *
     * @param string $fieldName Field Identifier / Name
     * @param mixed  $fieldData Field Data
     */
    private function setCoreFields($fieldName, $fieldData)
    {
        //====================================================================//
        // WRITE Field
        switch ($fieldName) {
            //====================================================================//
            // Direct Writing
            case 'reference':
                if ($this instanceof Invoice) {
                    $this->setSimple($fieldName, $fieldData, "Order");
                } else {
                    $this->setSimple($fieldName, $fieldData);
                }

                break;
            //====================================================================//
            // Customer Object Id
            case 'id_customer':
                if ($this instanceof Invoice) {
                    $this->setSimple($fieldName, self::objects()->Id($fieldData), "Order");
                } else {
                    $this->setSimple($fieldName, self::objects()->Id($fieldData));
                }

                break;
            default:
                return;
        }
        unset($this->in[$fieldName]);
    }
}
