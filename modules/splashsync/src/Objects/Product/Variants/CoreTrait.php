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

namespace Splash\Local\Objects\Product\Variants;

use Combination;
use Splash\Core\SplashCore      as Splash;
use Splash\Local\Services\LanguagesManager as SLM;
use Translate;

/**
 * Prestashop Product Variant Core Data Access
 */
trait CoreTrait
{
    /**
     * Product Combination Resume Array
     *
     * @var array
     */
    private $combinations;

    //====================================================================//
    // Fields Generation Functions
    //====================================================================//

    /**
     * Build Fields using FieldFactory
     */
    protected function buildVariantsCoreFields()
    {
        if (!Combination::isFeatureActive()) {
            return;
        }

        //====================================================================//
        // Product Type Name
        $this->fieldsFactory()->create(SPL_T_VARCHAR)
            ->Identifier("type")
            ->Name('Product Type')
            ->Group(Translate::getAdminTranslation("Meta", "AdminThemes"))
            ->addChoices(array("simple" => "Simple", "variant" => "Variant"))
            ->MicroData("http://schema.org/Product", "type")
            ->isReadOnly();

        //====================================================================//
        // Is Default Product Variant
        $this->fieldsFactory()->create(SPL_T_BOOL)
            ->Identifier("default_on")
            ->Name('Is default variant')
            ->Group(Translate::getAdminTranslation("Meta", "AdminThemes"))
            ->MicroData("http://schema.org/Product", "isDefaultVariation")
            ->isReadOnly();

        //====================================================================//
        // Default Product Variant
        $this->fieldsFactory()->create(self::objects()->encode("Product", SPL_T_ID))
            ->Identifier("default_id")
            ->Name('Default Variant')
            ->Group(Translate::getAdminTranslation("Meta", "AdminThemes"))
            ->MicroData("http://schema.org/Product", "DefaultVariation")
            ->isNotTested();

        //====================================================================//
        // Product Variation Parent Link
        $this->fieldsFactory()->create(SPL_T_VARCHAR)
            ->Identifier("parent_id")
            ->Name("Parent")
            ->Group(Translate::getAdminTranslation("Meta", "AdminThemes"))
            ->MicroData("http://schema.org/Product", "isVariationOf")
            ->isReadOnly();

        //====================================================================//
        // CHILD PRODUCTS INFORMATIONS
        //====================================================================//

        //====================================================================//
        // Product Variation List - Product Link
        $this->fieldsFactory()->Create((string) self::objects()->Encode("Product", SPL_T_ID))
            ->Identifier("id")
            ->Name("Variants")
            ->InList("variants")
            ->MicroData("http://schema.org/Product", "Variants")
            ->isNotTested();

        //====================================================================//
        // Product Variation List - Product SKU
        $this->fieldsFactory()->Create(SPL_T_VARCHAR)
            ->Identifier("sku")
            ->Name("SKU")
            ->InList("variants")
            ->MicroData("http://schema.org/Product", "VariationName")
            ->isReadOnly();
    }

    //====================================================================//
    // Fields Reading Functions
    //====================================================================//

    /**
     * Read requested Field
     *
     * @param string $key       Input List Key
     * @param string $fieldName Field Identifier / Name
     */
    protected function getVariantsCoreFields($key, $fieldName)
    {
        //====================================================================//
        // READ Fields
        switch ($fieldName) {
            case 'parent_id':
                $this->out[$fieldName] = $this->AttributeId ? (string) $this->ProductId : "";

                break;
            case 'type':
                if ($this->AttributeId) {
                    $this->out[$fieldName] = "variant";
                } else {
                    $this->out[$fieldName] = "simple";
                }

                break;
            case 'default_on':
                if ($this->AttributeId) {
                    $this->getSimple($fieldName, "Attribute");
                } else {
                    $this->out[$fieldName] = false;
                }

                break;
            case 'default_id':
                if ($this->AttributeId) {
                    $unikId = (int) $this->getUnikId(
                        $this->ProductId,
                        $this->object->getDefaultIdProductAttribute()
                    );
                    $this->out[$fieldName] = self::objects()->encode("Product", $unikId);
                } else {
                    $this->out[$fieldName] = null;
                }

                break;
            default:
                return;
        }

        unset($this->in[$key]);
    }

    /**
     * Read requested Field
     *
     * @param string $key       Input List Key
     * @param string $fieldName Field Identifier / Name
     */
    protected function getVariantChildsFields($key, $fieldName)
    {
        //====================================================================//
        // Check if List field & Init List Array
        $fieldId = self::lists()->initOutput($this->out, "variants", $fieldName);
        if (!$fieldId) {
            return;
        }
        //====================================================================//
        // Load Product Variants
        foreach ($this->getCombinationResume() as $index => $attr) {
            //====================================================================//
            // SKIP Current Variant When in PhpUnit/Travis Mode
            if (!$this->isAllowedVariantChild($attr)) {
                continue;
            }
            //====================================================================//
            // Add Variant Infos
            if (isset($attr[$fieldId])) {
                self::lists()->insert($this->out, "variants", $fieldId, $index, $attr[$fieldId]);
            }
        }

        unset($this->in[$key]);
        //====================================================================//
        // Sort Variants by Code
        ksort($this->out["variants"]);
    }

    //====================================================================//
    // Fields Writting Functions
    //====================================================================//

    /**
     * Write Given Fields
     *
     * @param string $fieldName Field Identifier / Name
     * @param mixed  $fieldData Field Data
     */
    private function setVariantsCoreFields($fieldName, $fieldData)
    {
        //====================================================================//
        // WRITE Field
        switch ($fieldName) {
            case 'default_on':
            case 'variants':
                break;
            case 'default_id':
                //====================================================================//
                // Check if Valid Data
                if (!$this->AttributeId || ($this->ProductId != $this->getId($fieldData))) {
                    break;
                }
                $attributeId = $this->getAttribute($fieldData);
                if (!$attributeId || ($attributeId == $this->object->getDefaultIdProductAttribute())) {
                    break;
                }
                $this->object->deleteDefaultAttributes();
                $this->object->setDefaultAttribute($attributeId);

                break;
            default:
                return;
        }
        unset($this->in[$fieldName]);
    }

    //====================================================================//
    // PRIVATE - Tooling Functions
    //====================================================================//

    /**
     * Check if Product Variant Should be Listed
     *
     * @param array $attribute Combination Resume Array
     *
     * @return bool
     */
    private function isAllowedVariantChild($attribute)
    {
        //====================================================================//
        // Not in PhpUnit/Travis Mode => Return All
        if (empty(Splash::input('SPLASH_TRAVIS'))) {
            return true;
        }

        //====================================================================//
        // Travis Mode => Skip Current Product Variant
        if ($attribute["id_product"] != $this->ProductId) {
            return true;
        }
        if ($attribute["id_product_attribute"] != $this->AttributeId) {
            return true;
        }

        return false;
    }

    /**
     * Build Product Combination Resume Array
     *
     * @return array
     */
    private function getCombinationResume()
    {
        //====================================================================//
        // Already Loaded
        if (isset($this->combinations)) {
            return $this->combinations;
        }
        //====================================================================//
        // Init List
        $this->combinations = array();
        //====================================================================//
        // READ Product Combinations List
        foreach ($this->object->getAttributeCombinations(SLM::getDefaultLangId()) as $attr) {
            //====================================================================//
            // Extract Product Attribute Id
            $attrId = $attr["id_product_attribute"];
            //====================================================================//
            // Already Added
            if (isset($this->combinations[$attrId])) {
                continue;
            }
            //====================================================================//
            // Parse Simple Data
            $this->combinations[$attrId]["id_product"] = $attr["id_product"];
            $this->combinations[$attrId]["id_product_attribute"] = $attr["id_product_attribute"];
            $this->combinations[$attrId]["sku"] = $attr["reference"];
            //====================================================================//
            // Parse Computed Data
            $unikId = self::getUnikIdStatic($attr["id_product"], $attr["id_product_attribute"]);
            $this->combinations[$attrId]["id"] = self::objects()->encode("Product", $unikId);
        }

        return $this->combinations;
    }
}
