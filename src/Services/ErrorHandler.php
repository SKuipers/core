<?php
/*
Gibbon, Flexible & Open School System
Copyright (C) 2010, Ross Parker

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

namespace Gibbon\Services;

use Gibbon\View\Page;

/**
 * @version v17
 * @since   v17
 */
class ErrorHandler
{
    protected $page;
    protected $installType;

    protected $defaultAlerts;

    public function __construct(string $installType)
    {
        $this->installType = $installType;
        
        set_error_handler([$this, 'handleError']);
        set_exception_handler([$this, 'handleException']);
        register_shutdown_function([$this, 'handleFatalErrorShutdown']);
    }

    public function setPage(Page $page)
    {
        $this->page = $page;

        $this->defaultAlerts = [
            'success0' => __('Your request was completed successfully.'),
            'error0'   => __('Your request failed because you do not have access to this action.'),
            'error1'   => __('Your request failed because your inputs were invalid.'),
            'error2'   => __('Your request failed due to a database error.'),
            'error3'   => __('Your request failed because your inputs were invalid.'),
            'error4'   => __('Your request failed because your passwords did not match.'),
            'error5'   => __('Your request failed because there are no records to show.'),
            'error6'   => __('Your request was completed successfully, but one or more images were the wrong size and so were not saved.'),
            'warning0' => __('Your optional extra data failed to save.'),
            'warning1' => __('Your request was successful, but some data was not properly saved.'),
            'warning2' => __('Your request was successful, but some data was not properly deleted.'),
        ];
    }

    public function handleError($code, $message = '', $file = null, $line = null)
    {
        switch ($code) {
            case ($code & (E_PARSE | E_ERROR | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR)):
                $type = 'Error';
                break;
            case ($code & (E_WARNING | E_USER_WARNING | E_COMPILE_WARNING | E_RECOVERABLE_ERROR)):
                $type = 'Warning';
                break;
            case ($code & (E_DEPRECATED | E_USER_DEPRECATED)):
                $type = 'Deprecated';
                break;
            case ($code & (E_NOTICE | E_USER_NOTICE)):
                $type = 'Notice';
                break;
            default:
                $type = 'Unknown Error';
                break;
        }

        // Slice out the backtrace from this error handler
        $stackTrace = array_slice(debug_backtrace(), 2, -3);

        return $this->outputError($code, $type, $message, $stackTrace, $file, $line);
    }

    public function handleException($e)
    {
        $this->outputError(E_ERROR, 'Uncaught Exception', get_class($e).' - '.$e->getMessage(), $e->getTrace(), $e->getFile(), $e->getLine());
        $this->handleGracefulShutdown();
    }
    
    public function handleFatalErrorShutdown()
    {
        $lastError = error_get_last();
        if ($lastError['type'] === E_ERROR) {
            $this->outputError($lastError['type'], nl2br($lastError['message']));
            $this->handleGracefulShutdown();
        }
    }

    protected function handleGracefulShutdown()
    {
        if ($this->page) {
            @ob_end_clean();

            if (!ini_get('display_errors') || !(error_reporting() & E_ERROR)) {
                $this->page->writeFromTemplate('error.twig.html');
            }

            echo $this->page->render('index.twig.html');
        }
        exit;
    }

    protected function outputError($code, $type = '', $message = '', $stackTrace = [], $file = null, $line = null)
    {
        // This error code is not included in error_reporting, so let it fall
        // through to the standard PHP error handler
        if (!(error_reporting() & $code)) {
            return false;
        }

        if (ini_get('display_errors') && $this->installType != 'Production') {
            $output = sprintf('<strong title="Error Code: %1$s">%2$s</strong>: %3$s', $code, $type, $message);

            $basePath = realpath('./').'/';
            $stackTrace = array_filter($stackTrace, function ($item) {
                return !empty($item['file']);
            });

            if (!empty($stackTrace)) {
                $output .= '<ol start="0" style="font-size: inherit;margin-bottom:0;">';
                $output .= sprintf('<li>Line %1$s in <span title="%2$s">%3$s</span></li>', $line, $file, str_replace($basePath, '', $file));
                foreach ($stackTrace as $index => $caller) {
                    $output .= sprintf('<li>Line %1$s in <span title="%2$s">%3$s</span></li>', $caller['line'], $caller['file'], str_replace($basePath, '', $caller['file']));
                }
                $output .= '</ol>';
            } else {
                $output .= sprintf(' in <span title="%1$s">%2$s</span> on line %3$s', $file, str_replace($basePath, '', $file), $line);
            }

            if ($this->page) {
                $this->page->addAlert('exception', $output);
            } else {
                echo '<div style="font-family:sans-serif;border-left: 6px solid #444;color: #444;background-color: #f9f9f9;font-size: 12px;padding: 10px;margin: 10px 0px 15px 0px;box-shadow: 2px 2px 2px rgba(50,50,50,0.15);">'.$output.'</div>';
            }
        }

        if (ini_get('log_errors')) {
            error_log($type.': '.$message.' in '.$file.' on line '.$line);
        }

        // Everything worked, so don't execute PHP internal error handler
        return true;
    }
}
