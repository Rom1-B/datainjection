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

class PluginDatainjectionClientInjection
{
    public static $rightname = "plugin_datainjection_use";

    const STEP_UPLOAD  = 0;
    const STEP_PROCESS = 1;
    const STEP_RESULT  = 2;

    //Injection results
    private $results = [];

    /**
    * Print a good title for group pages
    *
    *@return void nothing (display)
   **/
    public function title(): void
    {
        /** @var array $CFG_GLPI */
        global $CFG_GLPI;

        $buttons =  [];
        $title   = "";

        if (Session::haveRight(static::$rightname, UPDATE)) {
            $url           = Toolbox::getItemTypeSearchURL('PluginDatainjectionModel');
            $buttons[$url] = PluginDatainjectionModel::getTypeName();
            $title         = "";
            Html::displayTitle(
                Plugin::getWebDir('datainjection') . "/pics/datainjection.png",
                PluginDatainjectionModel::getTypeName(),
                $title,
                $buttons
            );
        }
    }


    public function showForm($ID, $options = [])
    {
        /** @var array $CFG_GLPI */
        global $CFG_GLPI;

        echo "<form method='post' name=form action='" . Toolbox::getItemTypeFormURL(__CLASS__) . "'" .
          "enctype='multipart/form-data'>";
        echo "<div class='center'>";
        echo "<table class='tab_cadre_fixe'>";

        $models = PluginDatainjectionModel::getModels(
            Session::getLoginUserID(),
            'name',
            $_SESSION['glpiactive_entity'],
            false
        );

        echo "<tr><th>" . __('Use an existing model', 'datainjection') . "</th></tr>";

        echo "<tr class='tab_bg_1'>";
        if (count($models) > 0) {
            echo "<td class='center'>" . __('Model') . "&nbsp;";
            PluginDatainjectionModel::dropdown();
        } else {
            $text = __('No model currently available', 'datainjection');

            if (Session::haveRight('plugin_datainjection_model', CREATE)) {
                $text = sprintf(
                    __('%1$s %2$s'),
                    $text . ". ",
                    sprintf(
                        __('%1$s: %2$s'),
                        __(
                            'You can start the model creation by hitting the button',
                            'datainjection'
                        ),
                        PluginDatainjectionModel::getTypeName()
                    )
                );
            }
            echo "<td class='center' colspan='2'>" . $text;
        }
        echo "</td></tr></table><br>";

        echo "<span id='span_injection' name='span_injection'></span>";
        Html::closeForm();
        echo "</div>";

        if (PluginDatainjectionSession::getParam('models_id')) {
            $p['models_id'] = PluginDatainjectionSession::getParam('models_id');

            switch (PluginDatainjectionSession::getParam('step')) {
                case self::STEP_UPLOAD:
                     $url = Plugin::getWebDir('datainjection') . "/ajax/dropdownSelectModel.php";
                     Ajax::updateItem("span_injection", $url, $p);
                    break;

                case self::STEP_RESULT:
                    $url = Plugin::getWebDir('datainjection') . "/ajax/results.php";
                    Ajax::updateItem("span_injection", $url, $p);
                    break;
            }
        }
    }


    /**
    * @param array $options
   **/
    public static function showUploadFileForm($options = [])
    {

        $add_form = (isset($options['add_form']) && $options['add_form']);
        $confirm  = (isset($options['confirm']) ? $options['confirm'] : false);
        $url      = (($confirm == 'creation') ? Toolbox::getItemTypeFormURL('PluginDatainjectionModel')
                                         : Toolbox::getItemTypeFormURL(__CLASS__));
        if ($add_form) {
            echo "<form method='post' name='form' action='" . $url . "' enctype='multipart/form-data'>";
        }
        echo "<table class='tab_cadre_fixe'>";
       //Show file selection
        echo "<tr><th colspan='2'>" . __('File to inject', 'datainjection') . "</th></tr>";

        echo "<tr class='tab_bg_1'>";
        $size = sprintf(__(' (%1$s)'), Document::getMaxUploadSize());
        echo "<td>" . __('Choose a file', 'datainjection') . $size . "</td>";
        echo "<td><input type='file' name='filename'>";
        echo "<input type='hidden' name='id' value='" . $options['models_id'] . "'>";
        echo "</td></tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('File encoding', 'datainjection') . "</td><td>";
        PluginDatainjectionDropdown::dropdownFileEncoding();
        echo "</td></tr>\n";

        echo "<tr class='tab_bg_1'>";
        echo "<td colspan='2' class='center'>";
        $alert = "";
        if ($confirm) {
            if ($confirm == 'creation') {
                $message = __s('Warning : existing mapped column will be overridden', 'datainjection');
            } else {
                $message = __s(
                    "Watch out, you're about to inject data into GLPI. Are you sure you want to do it ?",
                    'datainjection'
                );
            }
            $alert = "OnClick='return window.confirm(\"$message\");'";
        }
        if (!isset($options['submit'])) {
            $options['submit'] = __('Launch the import', 'datainjection');
        }
        echo "<input type='submit' class='submit' name='upload' value='" .
           htmlentities($options['submit'], ENT_QUOTES, 'UTF-8') . "' $alert>";
        echo "&nbsp;&nbsp;<input type='submit' class='submit' name='cancel' value=\"" . _sx('button', 'Cancel') . "\">";
        echo "</td></tr>\n";
        echo "</table><br>";
        if ($add_form) {
            Html::closeForm();
        }
    }


    /**
    * @param PluginDatainjectionModel $model PluginDatainjectionModel object
    * @param integer $entities_id
   **/
    public static function showInjectionForm(PluginDatainjectionModel $model, $entities_id)
    {

        if (!PluginDatainjectionSession::getParam('infos')) {
            PluginDatainjectionSession::setParam('infos', []);
        }
        echo "<table class='tab_cadre_fixe'>";
        echo "<tr class='tab_bg_1'>";
        echo "<th colspan='2'>" . sprintf(__('%1$s: %2$s'), __('Model'), $model->fields['name']) . "</th>";
        echo "</tr>";
        echo "</table><br>";

        echo "<table class='tab_cadre_fixe'>";
        echo "<tr class='tab_bg_1'>";
        echo "<th colspan='2'>" . __('Import progress', 'datainjection') . "</th>";
        echo "</tr>";

        echo "<tr class='tab_bg_1'><td>";
        Html::createProgressBar(__('Injection of the file', 'datainjection'));
        echo "</td></tr>";
        echo "</table><br>";

        echo "<span id='span_injection' name='span_injection'></span>";
        self::processInjection($model, $entities_id);
    }


    /**
    * @param PluginDatainjectionModel $model
    * @param integer $entities_id
   **/
    public static function processInjection(PluginDatainjectionModel $model, $entities_id)
    {
        /** @var array $CFG_GLPI */
        global $CFG_GLPI;

       // To prevent problem of execution time during injection
        ini_set("max_execution_time", "0");

       // Disable recording each SQL request in $_SESSION
        $CFG_GLPI["debug_sql"] = 0;

        $nblines         = PluginDatainjectionSession::getParam('nblines');
        $clientinjection = new PluginDatainjectionClientInjection();

       //New injection engine
        $engine = new PluginDatainjectionEngine(
            $model,
            PluginDatainjectionSession::getParam('infos'),
            $entities_id
        );
        $backend = $model->getBackend();
        $model->loadSpecificModel();

       //Open CSV file
        $backend->openFile();

        $index = 0;

       //Read CSV file
        $line = $backend->getNextLine();

       //If header is present, then get the second line
        if ($model->getSpecificModel()->isHeaderPresent()) {
            $line = $backend->getNextLine();
        }

       //While CSV file is not EOF
        $prev = '';
        $deb  = time();
        while ($line != null) {
            //Inject line
            $injectionline              = $index + ($model->getSpecificModel()->isHeaderPresent() ? 2 : 1);
            $clientinjection->results[] = $engine->injectLine($line[0], $injectionline);

            $pos = number_format($index * 100 / $nblines, 1);
            if ($pos != $prev) {
                $prev = $pos;
                $fin  = time() - $deb;
                //TODO yllen
                Html::changeProgressBarPosition(
                    $index,
                    $nblines,
                    sprintf(
                        __('%1$s (%2$s)'),
                        sprintf(
                            __(
                                'Injection of the file... %d%%',
                                'datainjection'
                            ),
                            $pos
                        ),
                        Html::timestampToString(time() - $deb, true)
                    )
                );
            }
            $line = $backend->getNextLine();
            $index++;
        }

         //EOF : change progressbar to 100% !
         Html::changeProgressBarPosition(
             100,
             100,
             sprintf(
                 __('%1$s (%2$s)'),
                 __('Injection finished', 'datainjection'),
                 Html::timestampToString(time() - $deb, true)
             )
         );

         // Restore
         $CFG_GLPI["debug_sql"] = 1;

         //Close CSV file
         $backend->closeFile();

         //Delete CSV file
         $backend->deleteFile();

         //Change step
         $_SESSION['datainjection']['step'] = self::STEP_RESULT;

         //Display results form
         PluginDatainjectionSession::setParam('results', json_encode($clientinjection->results));
         PluginDatainjectionSession::setParam('error_lines', json_encode($engine->getLinesInError()));
         $p['models_id'] = $model->fields['id'];
         $p['nblines']   = $nblines;

         unset($_SESSION['datainjection']['go']);

         $url = Plugin::getWebDir('datainjection') . "/ajax/results.php";
         Ajax::updateItem("span_injection", $url, $p);
    }


    /**
    * to be used instead of Toolbox::stripslashes_deep to reduce memory usage
    * execute stripslashes in place (no copy)
    *
    * @param array|string|null $value array of value
    */
    public static function stripslashes_array(&$value) // phpcs:ignore
    {

        if (is_array($value)) {
            foreach ($value as $key => $val) {
                self::stripslashes_array($value[$key]);
            }
        } else if (!is_null($value)) {
            $value = stripslashes($value);
        }
    }


    /**
    * @param PluginDatainjectionModel $model  PluginDatainjectionModel object
   **/
    public static function showResultsForm(PluginDatainjectionModel $model)
    {
        /** @var array $CFG_GLPI */
        global $CFG_GLPI;

        $results     = json_decode(PluginDatainjectionSession::getParam('results'), true);
        self::stripslashes_array($results);
        $error_lines = json_decode(PluginDatainjectionSession::getParam('error_lines'), true);
        self::stripslashes_array($error_lines);

        $ok = true;

        foreach ($results as $result) {
            if ($result['status'] != PluginDatainjectionCommonInjectionLib::SUCCESS) {
                $ok = false;
                break;
            }
        }

        $di_base_url = Plugin::getWebDir('datainjection');

        echo "<form method='post' action='$di_base_url/front/clientinjection.form.php'>";
        echo "<table class='tab_cadre_fixe'>";
        echo "<tr class='tab_bg_1'><th>" . __("Injection's results", 'datainjection') . "</th></tr>";

        echo "<tr class='tab_bg_1'><td class='center'>";
        if ($ok) {
            echo "<img src='$di_base_url/pics/ok.png'>";
            echo __('Injection successful', 'datainjection');
        } else {
            echo "<img src='$di_base_url/pics/danger.png'>";
            echo __('Injection encounters errors', 'datainjection');
        }
        echo "</td></tr>";

        echo "<tr class='tab_bg_1'><td class='center'>";
        $url = "$di_base_url/front/popup.php?popup=log&amp;models_id=" .
           $model->fields['id'];
        echo "<a href='#' onClick=\"var w = window.open('$url' , 'glpipopup', " .
           "'height=400, width=1000, top=100, left=100, scrollbars=yes' );w.focus();\"/' " .
           "title='" . __('See the log', 'datainjection') . "'>";
        echo "<img src='$di_base_url/pics/seereport.png'></a>";

        $plugin = new Plugin();
        if ($plugin->isActivated('pdf')) {
            echo "&nbsp;<a href='#' onclick=\"location.href='export.pdf.php?models_id=" .
                   $model->fields['id'] . "'\" title='" . __('Export rapport in PDF', 'datainjection') . "'>";
            echo "<img src='$di_base_url/pics/reportpdf.png'></a>";
        }

        if (!empty($error_lines)) {
            echo "&nbsp;<a href='#' onclick=\"location.href='export.csv.php'\"title='" .
                   __('Export the log', 'datainjection') . "'>";
            echo "<img src='$di_base_url/pics/failedcsv.png'></a>";
        }

        echo "</td></tr>";

        echo "<tr class='tab_bg_1'><td class='center'>";
        echo "<input class='submit' type='submit' name='finish' value='" .
           __('Finish', 'datainjection') . "'>";
        echo "</td></tr>";
        echo "</table>";
        Html::closeForm();
    }


    public static function exportErrorsInCSV()
    {

        $error_lines = json_decode(PluginDatainjectionSession::getParam('error_lines'), true);
        self::stripslashes_array($error_lines);

        if (!empty($error_lines)) {
            $model = unserialize(PluginDatainjectionSession::getParam('currentmodel'));
            $file  = PLUGIN_DATAINJECTION_UPLOAD_DIR . PluginDatainjectionSession::getParam('file_name');

            $mappings = $model->getMappings();
            $tmpfile  = fopen($file, 'w');

           //If headers present
            if ($model->getBackend()->isHeaderPresent()) {
                $headers = PluginDatainjectionMapping::getMappingsSortedByRank($model->fields['id']);
                fputcsv($tmpfile, $headers, $model->getBackend()->getDelimiter());
            }

           //Write lines
            foreach ($error_lines as $line) {
                fputcsv($tmpfile, $line, $model->getBackend()->getDelimiter());
            }
            fclose($tmpfile);

            $name = "Error-" . PluginDatainjectionSession::getParam('file_name');
            $name = str_replace(' ', '', $name);
            header('Content-disposition: attachment; filename=' . $name);
            header('Content-Type: application/octet-stream');
            header('Content-Transfer-Encoding: fichier');
            header('Content-Length: ' . filesize($file));
            header('Pragma: no-cache');
            header('Cache-Control: no-store, no-cache, must-revalidate, post-check=0, pre-check=0');
            header('Expires: 0');
            readfile($file);
            unlink($file);
        }
    }
}
