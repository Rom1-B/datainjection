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

class PluginDatainjectionInjectionType
{
    const NO_VALUE = 'none';


    /**
    * Return all injectable types
    *
    * @param boolean $only_primary    return only primary types (false by default)
    *
    * @return array which contains array(itemtype => itemtype name)
   **/
    public static function getItemtypes($only_primary = false)
    {
        /** @var array $INJECTABLE_TYPES */
        global $INJECTABLE_TYPES;

        getTypesToInject();

        $plugin = new Plugin();
        $values = [];
        foreach ($INJECTABLE_TYPES as $type => $from) {
            $injectionclass = new $type();

            if (
                class_exists($type)
                && (!$only_primary
                || ($injectionclass->isPrimaryType()))
            ) {
                $instance = new $type();
                //If user has no right to create an object of this type, do not display type in the list
                if (!$instance->canCreate()) {
                    continue;
                }
                $typename = get_parent_class($type);
                $name     = '';
                if ($from != 'datainjection') {
                    $plugin->getFromDBbyDir($from);
                    $name = $plugin->getName() . ': ';
                }
                $name .= call_user_func([$type, 'getTypeName']);
                $values[$typename] = $name;
            }
        }
        asort($values);
        return $values;
    }


    /**
    * Display a list of all importable types using datainjection plugin
    *
    * @param string $value           the selected value (default '')
    * @param boolean $only_primary    (false by default)
   **/
    public static function dropdown($value = '', $only_primary = false)
    {

        return Dropdown::showFromArray(
            'itemtype',
            self::getItemtypes($only_primary),
            ['value' => $value]
        );
    }


    /**
    * Get all types linked with a primary type
    *
    * @param mixed $mapping_or_info
    * @param array $options            array
   **/
    public static function dropdownLinkedTypes($mapping_or_info, $options = [])
    {
        /** @var array[mixed] $INJECTABLE_TYPES */
        /** @var array $CFG_GLPI */
        global $INJECTABLE_TYPES, $CFG_GLPI;

        getTypesToInject(); // populate $INJECTABLE_TYPES

        $p['primary_type']    = '';
        $p['itemtype']        = self::NO_VALUE;
       // Use hex code for all special chars to prevent problems when adding/stripping slashes
        $p['mapping_or_info'] = json_encode(
            $mapping_or_info->fields,
            JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
        );
        $p['called_by']       = get_class($mapping_or_info);
        $p['fields_update']   = true;
        foreach ($options as $key => $value) {
            $p[$key] = $value;
        }

        $mappings_id = $mapping_or_info->fields['id'];
        $values      = [];

        if (
            ($p['itemtype'] == self::NO_VALUE)
            && ($mapping_or_info->fields['itemtype'] != self::NO_VALUE)
        ) {
            $p['itemtype'] = $mapping_or_info->fields['itemtype'];
        }

       //Add null value
        $values[self::NO_VALUE] = __('-------Choose a table-------', 'datainjection');

       //Add primary_type to the list of availables types
        $type                       = new $p['primary_type']();
        $values[$p['primary_type']] = $type->getTypeName();

        foreach ($INJECTABLE_TYPES as $type => $plugin) {
            $injectionClass = new $type();
            $connected_to   = $injectionClass->connectedTo();
            if (in_array($p['primary_type'], $connected_to)) {
                $typename          = getItemTypeForTable($injectionClass->getTable());
                $values[$typename] = call_user_func([$type, 'getTypeName']);
            }
        }
        asort($values);

        $rand = Dropdown::showFromArray(
            "data[" . $mappings_id . "][itemtype]",
            $values,
            ['value' => $p['itemtype']]
        );

        $p['itemtype'] = '__VALUE__';
        $di_base_url   = Plugin::getWebDir('datainjection');
        $url_field     = "$di_base_url/ajax/dropdownChooseField.php";
        $url_mandatory = "$di_base_url/ajax/dropdownMandatory.php";
        $toobserve     = "dropdown_data[" . $mapping_or_info->getID() . "][itemtype]$rand";
        $toupdate      = "span_field_" . $mappings_id;
        Ajax::updateItem($toupdate, $url_field, $p, $toobserve);
        Ajax::updateItemOnSelectEvent($toobserve, $toupdate, $url_field, $p);
        return $rand;
    }


    /**
    * @param array $options   array
   **/
    public static function dropdownFields($options = [])
    {
        /** @var array $CFG_GLPI */
        global $CFG_GLPI;

        $used                 = [];
        $p['itemtype']        = self::NO_VALUE;
        $p['primary_type']    = '';
        $p['mapping_or_info'] = [];
        $p['called_by']       = '';
        $p['need_decode']     = true;
        $p['fields_update']   = true;

        foreach ($options as $key => $value) {
            $p[$key] = $value;
        }

        if ($p['need_decode']) {
            $mapping_or_info = json_decode(
                Toolbox::stripslashes_deep($options['mapping_or_info']),
                true
            );
        } else {
            $mapping_or_info = $options['mapping_or_info'];
        }

        $fields = [];
        $fields[self::NO_VALUE] = __('-------Choose a field-------', 'datainjection');

       //By default field has no default value
        $mapping_value = self::NO_VALUE;

        if ($p['itemtype'] != self::NO_VALUE) {
           //If a value is still present for this mapping
            if ($mapping_or_info['value'] != self::NO_VALUE) {
                $mapping_value = $mapping_or_info['value'];
            }
            $injectionClass = PluginDatainjectionCommonInjectionLib::getInjectionClassInstance($p['itemtype']);

            foreach ($injectionClass->getOptions($p['primary_type']) as $option) {
               //If it's a real option (not a group label) and if field is not blacklisted
               //and if a linkfield is defined (meaning that the field can be updated)
                if (
                    is_array($option)
                    && isset($option['injectable'])
                    && ($option['injectable'] == PluginDatainjectionCommonInjectionLib::FIELD_INJECTABLE)
                ) {
                    $fields[$option['linkfield']] = $option['name'];

                    if (
                        ($mapping_value == self::NO_VALUE)
                        && ($p['called_by'] == 'PluginDatainjectionMapping')
                        && self::isEqual($option, $mapping_or_info)
                    ) {
                        $mapping_value = $option['linkfield'];
                    }
                }
            }
            $used = self::getUsedMappingsOrInfos($p);
        }
        asort($fields);

        $rand = Dropdown::showFromArray(
            "data[" . $mapping_or_info['id'] . "][value]",
            $fields,
            ['value' => $mapping_value,
                'used'  => $used
            ]
        );

        $url = Plugin::getWebDir('datainjection') . "/ajax/dropdownMandatory.php";
        Ajax::updateItem(
            "span_mandatory_" . $mapping_or_info['id'],
            $url,
            $p,
            "dropdown_data[" . $mapping_or_info['id'] . "][value]$rand"
        );
        Ajax::updateItemOnSelectEvent(
            "dropdown_data[" . $mapping_or_info['id'] . "][value]$rand",
            "span_mandatory_" . $mapping_or_info['id'],
            $url,
            $p
        );
    }


    /**
    * Incidates if the name given corresponds to the current searchOption
    *
    * @param array $option    array the current searchOption (field definition)
    * @param array $mapping
    *
    * @return boolean the value matches the searchOption or not
   **/
    public static function isEqual($option, $mapping)
    {

        $name = strtolower($mapping['name']);
        if (self::testBasicEqual(strtolower($mapping['name']), $option)) {
            return true;
        }

       //Manage mappings begining with N° or n°
        $new_name = preg_replace("/[n|N]°/", __('Lifelong'), $name);
        if (self::testBasicEqual(strtolower($new_name), $option)) {
            return true;
        }

       //Field may match is it was in plural...
        $plural_name = $name . 's';
        if (self::testBasicEqual(strtolower($plural_name), $option)) {
            return true;
        }
        return false;
    }


    /**
    * @param string $name
    * @param array $option    array
   **/
    public static function testBasicEqual($name, $option = [])
    {

          //Basic tests
        if (
            (strtolower($option['field']) == $name)
            || (strtolower($option['name']) == $name)
            || (strtolower($option['linkfield']) == $name)
        ) {
            return true;
        }
        return false;
    }


    /**
    * @param array $options   array
   **/
    public static function showMandatoryCheckbox($options = [])
    {

       // Received data has been slashed.
        $options = Toolbox::stripslashes_deep($options);

        if ($options['need_decode']) {
           // JSON data has been slashed twice, stripslashes has to be done a second time.
            $mapping_or_info = json_decode(
                Toolbox::stripslashes_deep($options['mapping_or_info']),
                true
            );
        } else {
            $mapping_or_info = $options['mapping_or_info'];
        }

       //TODO : to improve
        $checked = '';
        if ($mapping_or_info['is_mandatory']) {
            $checked = 'checked';
        }

        if (
            ($options['called_by'] == 'PluginDatainjectionInfo')
            || ($options['primary_type'] == $options['itemtype'])
        ) {
            echo "<input type='checkbox' name='data[" . $mapping_or_info['id'] . "][is_mandatory]' $checked>";
        }
    }


    /**
    * @param array $options   array
   **/
    public static function getUsedMappingsOrInfos($options = [])
    {
        /** @var DBmysql $DB */
        global $DB;

        $p['itemtype']        = self::NO_VALUE;
        $p['primary_type']    = '';
        $p['mapping_or_info'] = [];
        $p['called_by']       = '';
        $p['need_decode']     = true;

        foreach ($options as $key => $value) {
            $p[$key] = $value;
        }

        if ($p['need_decode']) {
            $mapping_or_info = json_decode(
                Toolbox::stripslashes_deep($options['mapping_or_info']),
                true
            );
        } else {
            $mapping_or_info = $options['mapping_or_info'];
        }

        $used  = [];
        $table = (($p['called_by'] == 'PluginDatainjectionMapping') ? "glpi_plugin_datainjection_mappings"
                                                              : "glpi_plugin_datainjection_infos");

        $datas = getAllDataFromTable($table, ['models_id' => $mapping_or_info['models_id']]);

        $injectionClass = PluginDatainjectionCommonInjectionLib::getInjectionClassInstance($p['itemtype']);
        $options        = $injectionClass->getOptions();

        foreach ($datas as $data) {
            if ($data['value'] != self::NO_VALUE) {
                foreach ($options as $option) {
                    if (
                        isset($option['table'])
                        && ($option['table'] == getItemTypeForTable($data['itemtype']))
                        && ($option['linkfield'] == $data['value'])
                        && ($option['displaytype'] != 'multiline_text')
                        && ($mapping_or_info['value'] != $data['value'])
                    ) {
                        $used[$option['linkfield']] = $option['linkfield'];
                        break;
                    }
                }
            }
        }

        return $used;
    }
}
