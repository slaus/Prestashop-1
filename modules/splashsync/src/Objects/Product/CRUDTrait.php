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

namespace Splash\Local\Objects\Product;

use Combination;
use Product;
use Splash\Core\SplashCore      as Splash;
use Splash\Local\Services\LanguagesManager as SLM;
use Tools;

/**
 * Prestashop Product CRUD Functions
 */
trait CRUDTrait
{
    /**
     * Load Request Object
     *
     * @param string $unikId Object id
     *
     * @return false|Product
     */
    public function load($unikId)
    {
        //====================================================================//
        // Stack Trace
        Splash::log()->trace();

        //====================================================================//
        // Decode Product Id
        $this->ProductId = self::getId($unikId);

        //====================================================================//
        // Safety Checks
        if (empty($unikId) || empty($this->ProductId)) {
            return Splash::log()->errTrace("Product Unik Id is Missing.");
        }
        //====================================================================//
        // Clear Cache
        \Product::flushPriceCache();
        //====================================================================//
        // If $id Given => Load Product Object From DataBase
        //====================================================================//
        $this->object = new Product($this->ProductId, true);
        if ($this->object->id != $this->ProductId) {
            return Splash::log()->errTrace("Unable to fetch Product (".$this->ProductId.")");
        }
        //====================================================================//
        // If $id_attribute Given => Load Product Attribute Combinaisons From DataBase
        //====================================================================//
        if (!$this->loadAttribute($unikId)) {
            return false;
        }
        //====================================================================//
        // Flush Images Infos Cache
        $this->flushImageCache();

        return $this->object;
    }

    /**
     * Create Request Object
     *
     * @return false|Product New Object
     */
    public function create()
    {
        //====================================================================//
        // Stack Trace
        Splash::log()->trace();

        //====================================================================//
        // Check Required Fields are Given
        if (!$this->isValidForCreation()) {
            return false;
        }

        //====================================================================//
        // Check is New Product is Variant Product
        if (!$this->isNewVariant($this->in)) {
            //====================================================================//
            // Create New Simple Product
            return $this->createSimpleProduct();
        }

        //====================================================================//
        // Create New Variant Product
        return $this->createVariantProduct($this->in);
    }

    /**
     * Update Request Object
     *
     * @param bool $needed Is This Update Needed
     *
     * @return false|string Object Id
     */
    public function update($needed)
    {
        //====================================================================//
        // Stack Trace
        Splash::log()->trace();

        //====================================================================//
        // Verify Update Is requiered
        if (!$needed && !$this->isToUpdate("Attribute")) {
            Splash::log()->deb("MsgLocalNoUpdateReq", __CLASS__, __FUNCTION__);

            return $this->getObjectIdentifier();
        }

        //====================================================================//
        // UPDATE MAIN INFORMATIONS
        //====================================================================//
        if ($this->ProductId && $needed) {
            if (true != $this->object->update()) {
                return Splash::log()->errTrace("Unable to update Product.");
            }
        }

        //====================================================================//
        // UPDATE ATTRIBUTE INFORMATIONS
        if (!$this->updateAttribute()) {
            return false;
        }

        return $this->getObjectIdentifier();
    }

    /**
     * Delete requested Object
     *
     * @param string $unikId Object Id.
     *
     * @return bool
     */
    public function delete($unikId = null)
    {
        //====================================================================//
        // Stack Trace
        Splash::log()->trace();

        //====================================================================//
        // Safety Checks
        if (empty($unikId)) {
            return Splash::log()->err("ErrSchNoObjectId", __CLASS__."::".__FUNCTION__);
        }

        //====================================================================//
        // Decode Product Id
        if (!empty($unikId)) {
            $this->ProductId = $this->getId($unikId);
            $this->AttributeId = $this->getAttribute($unikId);
        } else {
            return Splash::log()->err("ErrSchWrongObjectId", __FUNCTION__);
        }

        //====================================================================//
        // Load Object From DataBase
        //====================================================================//
        $this->object = new Product($this->ProductId, true);
        if ($this->object->id != $this->ProductId) {
            return Splash::log()->warTrace("Unable to load Product (".$this->ProductId.").");
        }

        //====================================================================//
        // If Attribute Defined => Delete Combination From DataBase
        if ($this->AttributeId) {
            return $this->deleteAttribute();
        }

        //====================================================================//
        // Else Delete Product From DataBase
        return $this->object->delete();
    }

    /**
     * {@inheritdoc}
     */
    public function getObjectIdentifier()
    {
        if (!isset($this->object->id)) {
            return false;
        }

        return (string) $this->getUnikId();
    }

    /**
     * Ensure Required Fields are Available to Create a Product
     *
     * @return bool
     */
    private function isValidForCreation()
    {
        //====================================================================//
        // Check Product Ref is given
        if (empty($this->in["ref"])) {
            return Splash::log()->err("ErrLocalFieldMissing", __CLASS__, __FUNCTION__, "ref");
        }
        //====================================================================//
        // Check Product Name is given
        if (empty($this->in["name"])) {
            return Splash::log()->err("ErrLocalFieldMissing", __CLASS__, __FUNCTION__, "name");
        }
        //====================================================================//
        // Init Product Link Rewrite Url if Empty
        foreach (SLM::getAvailableLanguages() as $isoCode) {
            //====================================================================//
            // Default language
            if (SLM::isDefaultLanguage($isoCode)) {
                if (empty($this->in["link_rewrite"])) {
                    $this->in["link_rewrite"] = Tools::link_rewrite($this->in["name"]);
                }

                continue;
            }
            //====================================================================//
            // Extra Languages
            if (empty($this->in["link_rewrite_".$isoCode])) {
                //====================================================================//
                // Detect Multilang Name or Fallback to Default
                $value = isset($this->in["name_".$isoCode])
                        ? $this->in["name_".$isoCode]
                        : $this->in["name"];
                //====================================================================//
                // Setup Multilang Url Rewrite
                $this->in["link_rewrite_".$isoCode] = Tools::link_rewrite($value);
            }
        }

        return true;
    }

    /**
     * Create a New Simple Product
     *
     * @return false|Product New Product
     */
    private function createSimpleProduct()
    {
        //====================================================================//
        // Stack Trace
        Splash::log()->trace();

        //====================================================================//
        // Create Empty Product
        $this->object = new Product();
        //====================================================================//
        // Setup Product Minimal Data
        $this->setSimple("reference", $this->in["ref"]);
        $this->setMultilang("name", SLM::getDefaultLangId(), $this->in["name"]);
        $this->setMultilang("link_rewrite", SLM::getDefaultLangId(), $this->in["link_rewrite"]);
        //====================================================================//
        // CREATE PRODUCT
        if (true != $this->object->add()) {
            return Splash::log()->errTrace(" Unable to create Simple Product.");
        }
        //====================================================================//
        // Store New Id on SplashObject Class
        $this->ProductId = $this->object->id;
        $this->AttributeId = 0;

        //====================================================================//
        // Create Empty Product
        return $this->object;
    }
}
