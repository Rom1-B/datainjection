<?php

/**
 * -------------------------------------------------------------------------
 * DataInjection plugin for GLPI
 * -------------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of DataInjection.
 *
 * DataInjection is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * DataInjection is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with DataInjection. If not, see <http://www.gnu.org/licenses/>.
 * -------------------------------------------------------------------------
 * @copyright Copyright (C) 2007-2023 by DataInjection plugin team.
 * @license   GPLv2 https://www.gnu.org/licenses/gpl-2.0.html
 * @link      https://github.com/pluginsGLPI/datainjection
 * -------------------------------------------------------------------------
 */

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

class PluginDatainjectionProfileInjection extends Profile implements PluginDatainjectionInjectionInterface
{
    public static function getTable($classname = null)
    {

        $parenttype = get_parent_class(__CLASS__);
        return $parenttype::getTable();
    }


    public function isPrimaryType()
    {

        return true;
    }


    public function connectedTo()
    {

        return [];
    }


    /**
    * @see plugins/datainjection/inc/PluginDatainjectionInjectionInterface::getOptions()
   **/
    public function getOptions($primary_type = '')
    {

        return Search::getOptions(get_parent_class($this));
    }


    /**
    * @param string $field_name
    * @param string $data
    * @param mixed $mandatory
   **/
    public function checkType($field_name, $data, $mandatory)
    {

        switch ($field_name) {
            case 'right_rw':
                return (in_array($data, ['r', 'w'])
                 ? PluginDatainjectionCommonInjectionLib::SUCCESS
                 : PluginDatainjectionCommonInjectionLib::TYPE_MISMATCH);

            case 'right_r':
                return (($data == 'r') ? PluginDatainjectionCommonInjectionLib::SUCCESS
                             : PluginDatainjectionCommonInjectionLib::TYPE_MISMATCH);

            case 'right_w':
                return (($data == 'w') ? PluginDatainjectionCommonInjectionLib::SUCCESS
                             : PluginDatainjectionCommonInjectionLib::TYPE_MISMATCH);

            case 'interface':
                return (in_array($data, ['helpdesk', 'central'])
                 ? PluginDatainjectionCommonInjectionLib::SUCCESS
                 : PluginDatainjectionCommonInjectionLib::TYPE_MISMATCH);

            default:
                return PluginDatainjectionCommonInjectionLib::SUCCESS;
        }
    }


    /**
    * @see plugins/datainjection/inc/PluginDatainjectionInjectionInterface::addOrUpdateObject()
   **/
    public function addOrUpdateObject($values = [], $options = [])
    {

        $lib = new PluginDatainjectionCommonInjectionLib($this, $values, $options);
        $lib->processAddOrUpdate();
        return $lib->getInjectionResults();
    }
}
