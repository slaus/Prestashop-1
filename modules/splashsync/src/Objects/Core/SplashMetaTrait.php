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

namespace Splash\Local\Objects\Core;

use Translate;

/**
 * Access to Objects Splash Meta Fields
 *
 * @author      B. Paquier <contact@splashsync.com>
 */
trait SplashMetaTrait
{
    /**
     * @var null|string
     */
    protected $NewSplashId;

    /**
     * Build Fields using FieldFactory
     */
    protected function buildSplashMetaFields()
    {
        //====================================================================//
        // Splash Unique Object Id
        $this->fieldsFactory()->create(SPL_T_VARCHAR)
            ->Identifier("splash_id")
            ->Name("Splash Id")
            ->Group(Translate::getAdminTranslation("Meta", "AdminThemes"))
            ->MicroData("http://splashync.com/schemas", "ObjectId");
    }

    /**
     * Read requested Field
     *
     * @param string $key       Input List Key
     * @param string $fieldName Field Identifier / Name
     */
    protected function getSplashMetaFields(
        $key,
        $fieldName
    ) {
        //====================================================================//
        // READ Fields
        switch ($fieldName) {
            case 'splash_id':
                $this->out[$fieldName] = self::getSplashId(self::$NAME, $this->object->id);

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
    protected function setSplashMetaFields($fieldName, $fieldData)
    {
        //====================================================================//
        // WRITE Fields
        switch ($fieldName) {
            case 'splash_id':
                if ($this->object->id) {
                    self::setSplashId(self::$NAME, $this->object->id, $fieldData);
                } else {
                    $this->NewSplashId = $fieldData;
                }

                break;
            default:
                return;
        }
        unset($this->in[$fieldName]);
    }
}
