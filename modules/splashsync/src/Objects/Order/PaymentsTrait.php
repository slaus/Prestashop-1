<?php

/*
 * This file is part of SplashSync Project.
 *
 * Copyright (C) Splash Sync <www.splashsync.com>
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Splash\Local\Objects\Order;

use Splash\Core\SplashCore      as Splash;


//====================================================================//
// Prestashop Static Classes	
use Shop, Configuration, Currency, Combination, Language, Context, Translate, OrderPayment;
use Image, ImageType, ImageManager, StockAvailable;
use DbQuery, Db, Tools;

/**
 * @abstract    Access to Orders Payments Fields
 * @author      B. Paquier <contact@splashsync.com>
 */
trait PaymentsTrait {
    

    private $KnownPaymentMethods = array(
            "bankwire"          =>      "ByBankTransferInAdvance",
            "ps_wirepayment"    =>      "ByBankTransferInAdvance",
        
            "cheque"            =>      "CheckInAdvance",
            "ps_checkpayment"   =>      "CheckInAdvance",
        
            "paypal"            =>      "PayPal",
            "amzpayments"       =>      "PayPal",
        
            "cashondelivery"    =>      "COD",
    );
    
    
    /**
    *   @abstract     Build Fields using FieldFactory
    */
    protected function buildPaymentsFields() {
        
        //====================================================================//
        // Payment Line Payment Method 
        $this->FieldsFactory()->Create(SPL_T_VARCHAR)
                ->Identifier("mode")
                ->InList("payments")
                ->Name(Translate::getAdminTranslation("Payment method", "AdminOrders"))
                ->MicroData("http://schema.org/Invoice","PaymentMethod")
                ->Group(Translate::getAdminTranslation("Payment", "AdminPayment"))
                ->Association("mode@payments","amount@payments")   
                ->AddChoices(array_flip($this->KnownPaymentMethods))
                ;        

        //====================================================================//
        // Payment Line Date
        $this->FieldsFactory()->Create(SPL_T_DATE)        
                ->Identifier("date")
                ->InList("payments")
                ->Name(Translate::getAdminTranslation("Date", "AdminProducts"))
                ->MicroData("http://schema.org/PaymentChargeSpecification","validFrom")
                ->Group(Translate::getAdminTranslation("Payment", "AdminPayment"))
                ->ReadOnly()
                ;        

        //====================================================================//
        // Payment Line Payment Identifier
        $this->FieldsFactory()->Create(SPL_T_VARCHAR)        
                ->Identifier("number")
                ->InList("payments")
                ->Name(Translate::getAdminTranslation("Transaction ID", "AdminOrders"))                
                ->MicroData("http://schema.org/Invoice","paymentMethodId")        
                ->Association("mode@payments","amount@payments")   
                ->Group(Translate::getAdminTranslation("Payment", "AdminPayment"))
                ;        

        //====================================================================//
        // Payment Line Amount
        $this->FieldsFactory()->Create(SPL_T_DOUBLE)        
                ->Identifier("amount")
                ->InList("payments")
                ->Name(Translate::getAdminTranslation("Amount", "AdminOrders"))                
                ->MicroData("http://schema.org/PaymentChargeSpecification","price")
                ->Group(Translate::getAdminTranslation("Payment", "AdminPayment"))
                ->Association("mode@payments","amount@payments")   
                ;        
    } 
    
    /**
     *  @abstract     Read requested Field
     * 
     *  @param        string    $Key                    Input List Key
     *  @param        string    $FieldName              Field Identifier / Name
     * 
     *  @return         none
     */
    private function getPaymentsFields($Key,$FieldName)
    {
        //====================================================================//
        // Check if List field & Init List Array
        $FieldId = self::Lists()->InitOutput( $this->Out, "payments", $FieldName );
        if ( !$FieldId ) {
            return;
        }        
        //====================================================================//
        // Verify List is Not Empty
        if ( !is_a($this->Payments,"PrestaShopCollection") ) {
            unset($this->In[$Key]);
            return True;
        }       
        //====================================================================//
        // Fill List with Data
        foreach ($this->Payments as $key => $OrderPayment) {
            //====================================================================//
            // READ Fields
            switch ($FieldName)
            {
                //====================================================================//
                // Payment Line - Payment Mode
                case 'mode@payments':
                    $Value  =   $this->getPaymentMethod($OrderPayment);
                    break;
                //====================================================================//
                // Payment Line - Payment Date
                case 'date@payments':
                    $Value  =   date(SPL_T_DATECAST, strtotime($OrderPayment->date_add));
                    break;
                //====================================================================//
                // Payment Line - Payment Identification Number
                case 'number@payments':
                    $Value  =   $OrderPayment->transaction_id;
                    break;
                //====================================================================//
                // Payment Line - Payment Amount
                case 'amount@payments':
                    $Value  =   $OrderPayment->amount;
                    break;
                default:
                    return;
            }
            //====================================================================//
            // Insert Data in List
            self::Lists()->Insert( $this->Out, "payments", $FieldName, $key, $Value );                   
        }
        unset($this->In[$Key]);
    }
    
    /**
     *  @abstract     Try To Detect Payment method Standardized Name
     * 
     *  @param  OrderPayment    $OrderPayment 
     * 
     *  @return         none
     */
    private function getPaymentMethod($OrderPayment)
    {
        //====================================================================//
        // If PhpUnit Mode => Read Order Payment Object
        if ( SPLASH_DEBUG ) {
            return $OrderPayment->payment_method;
        }        
        //====================================================================//
        // Detect Payment Metyhod Type from Default Payment "known" methods
        if (array_key_exists($this->PaymentMethod , $this->KnownPaymentMethods)) {
            return $this->KnownPaymentMethods[$this->PaymentMethod];
        }        
        //====================================================================//
        // Detect Payment Method is Credit Card Like Method
        if ( !empty($OrderPayment->card_brand) ) {
            return "DirectDebit";
        }        
        return "Unknown";
    }   
    
    /**
     *  @abstract     Write Given Fields
     * 
     *  @param        string    $FieldName              Field Identifier / Name
     *  @param        mixed     $Data                   Field Data
     * 
     *  @return         none
     */
    private function setPaymentsFields($FieldName,$Data) 
    {
        //====================================================================//
        // Safety Check
        if ( $FieldName !== "payments" ) {
            return True;
        }
        
        //====================================================================//
        // Verify Lines List & Update if Needed 
        foreach ($Data as $PaymentItem) {
            //====================================================================//
            // Update Product Line
            if (is_array($this->Payments)) {
                $this->updatePayment(array_shift($this->Payments) , $PaymentItem);
            } else {
                $this->updatePayment($this->Payments->current() , $PaymentItem);
                $this->Payments->next();
            }
        } 
        
        //====================================================================//
        // Delete Remaining Lines
        foreach ($this->Payments as $PaymentItem) {
            $PaymentItem->delete();
        }        
        
        unset($this->In[$FieldName]);
    }
    
    /**
     *  @abstract     Write Data to Current Item
     * 
     *  @param        array     $OrderPayment       Current Item Data Array
     *  @param        array     $PaymentItem        Input Item Data Array
     * 
     *  @return         none
     */
    private function updatePayment($OrderPayment , $PaymentItem) {
              
        //====================================================================//
        // New Line ? => Create One
        if ( is_null($OrderPayment) ) {
            //====================================================================//
            // Create New OrderDetail Item
            $OrderPayment   =  new OrderPayment();
            $OrderPayment->order_reference      =    $this->Object->reference;  
            $OrderPayment->id_currency          =    $this->Object->id_currency;  
            $OrderPayment->conversion_rate      =    1;  
        }
        $Update =    False;
        
        //====================================================================//
        // Update Payment Method
        if ( isset($PaymentItem["mode"]) && ( $OrderPayment->payment_method != $PaymentItem["mode"] ) ) {
            $OrderPayment->payment_method = $PaymentItem["mode"];
            $Update =    True;
        }
        
        //====================================================================//
        // Update Payment Amount
        if ( isset($PaymentItem["amount"]) && ( $OrderPayment->amount != $PaymentItem["amount"] ) )  {
            $OrderPayment->amount = $PaymentItem["amount"];
            $Update =    True;
        }
        
        //====================================================================//
        // Update Payment Number
        if ( isset($PaymentItem["number"]) && ( $OrderPayment->transaction_id != $PaymentItem["number"] ) )  {
            $OrderPayment->transaction_id = $PaymentItem["number"];
            $Update =    True;
        }
        
        //====================================================================//
        // Commit Line Update
        if ( !$Update ) {
            return;
        } 
        
        if ( !$OrderPayment->id ) {
            if ( $OrderPayment->add() != True) {  
                return Splash::Log()->Err("ErrLocalTpl",__CLASS__,__FUNCTION__,"Unable to Create new Payment Line.");
            } 
        } else {
            if ( $OrderPayment->update() != True) {  
                return Splash::Log()->Err("ErrLocalTpl",__CLASS__,__FUNCTION__,"Unable to Update Payment Line.");
            }        
        }
        
//        //====================================================================//
//        // Update Item Totals
//        $this->CurrentItem->update_total();
    }        
}
