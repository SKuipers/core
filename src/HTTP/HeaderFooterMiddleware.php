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

namespace Gibbon\HTTP;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Container\ContainerInterface as ContainerInterface;

/**
 * HeaderFooterMiddleware
 */
class HeaderFooterMiddleware {

    protected $container;

    public function __construct(ContainerInterface $container) {
        $this->container = $container;
    }

    public function __invoke(Request $request, Response $response, callable $next) {

        require_once './functions.php';

        $params = $request->getQueryParams();
        $legacyMode = !empty($params['q']);

        if ($legacyMode) {
            // Legacy pages are echoed and output-buffered
            // echo $this->getPageHeader();
            $response->getBody()->write($this->getPageHeader());
            $response = $next($request, $response);
            echo $this->getPageFooter();
        } else {
            // All other pages are streamed to the response body
            $response->getBody()->write($this->getPageHeader());
            $response = $next($request, $response);
            $response->getBody()->write($this->getPageFooter());
        }
    
        return $response;
    }

    protected function getPageHeader()
    {
        $guid = $this->container->get('gibbon')->guid();
        $version = $this->container->get('settings')['version'];

        $output = '';
        $output .= '
        <html>
        <head>
            <link rel="stylesheet" type="text/css" href="./themes/Default/css/main.css?v='.$version.'" />
            <script type="text/javascript" src="'.$_SESSION[$guid]['absoluteURL'].'/lib/jquery/jquery.js"></script>
            <script type="text/javascript" src="'.$_SESSION[$guid]['absoluteURL'].'/lib/jquery/jquery-migrate.min.js"></script>
            <script type="text/javascript" src="./lib/LiveValidation/livevalidation_standalone.compressed.js"></script>
        </head>
        <body>
        <div id="wrap"><div id="header">
        <div id="header-logo">
            <a href="'.$_SESSION[$guid]['absoluteURL'].'">
                <img height="100" width="400" class="logo" alt="Logo" src="'.$_SESSION[$guid]['absoluteURL'].'/'.$_SESSION[$guid]['organisationLogo'].'"/>
            </a>
        </div>
        <div id="header-menu">';

        // Display the main menu
        $mainMenu = new \Gibbon\menuMain($this->container->get('gibbon'), $this->container->get('pdo'));
        $mainMenu->setMenu();
        $output .= $mainMenu->getMenu();
    
        $output .= '</div></div><div id="content-wrap"><div id="content">';

        return $output;
    }

    protected function getPageFooter()
    {
        $guid = $this->container->get('gibbon')->guid();
        $version = $this->container->get('settings')['version'];

        $output = '';
        $output .= '</div><div id="sidebar">';

        //Invoke and show Module Menu
        $menuModule = new \Gibbon\menuModule($this->container->get('gibbon'), $this->container->get('pdo'));
        $output .= $menuModule->getMenu('full');
    
        $output .= '</div><br style="clear: both"></div>';
        
        $output .= '
        <script type="text/javascript" src="'.$_SESSION[$guid]['absoluteURL'].'/lib/jquery-ui/js/jquery-ui.min.js"></script>
        ';
        
        if (isset($_SESSION[$guid]['i18n']['code'])) {
            if (is_file($_SESSION[$guid]['absolutePath'].'/lib/jquery-ui/i18n/jquery.ui.datepicker-'.substr($_SESSION[$guid]['i18n']['code'], 0, 2).'.js')) {
                $output .= "<script type='text/javascript' src='".$_SESSION[$guid]['absoluteURL'].'/lib/jquery-ui/i18n/jquery.ui.datepicker-'.substr($_SESSION[$guid]['i18n']['code'], 0, 2).".js'></script>";
                $output .= "<script type='text/javascript'>$.datepicker.setDefaults($.datepicker.regional['".substr($_SESSION[$guid]['i18n']['code'], 0, 2)."']);</script>";
            } elseif (is_file($_SESSION[$guid]['absolutePath'].'/lib/jquery-ui/i18n/jquery.ui.datepicker-'.str_replace('_', '-', $_SESSION[$guid]['i18n']['code']).'.js')) {
                $output .= "<script type='text/javascript' src='".$_SESSION[$guid]['absoluteURL'].'/lib/jquery-ui/i18n/jquery.ui.datepicker-'.str_replace('_', '-', $_SESSION[$guid]['i18n']['code']).".js'></script>";
                $output .= "<script type='text/javascript'>$.datepicker.setDefaults($.datepicker.regional['".str_replace('_', '-', $_SESSION[$guid]['i18n']['code'])."']);</script>";
            }
        }

        $output .= '
        <script type="text/javascript">$(function() { $( document ).tooltip({  show: 800, hide: false, content: function () { return $(this).prop("title")}, position: { my: "center bottom-20", at: "center top", using: function( position, feedback ) { $( this ).css( position ); $( "<div>" ).addClass( "arrow" ).addClass( feedback.vertical ).addClass( feedback.horizontal ).appendTo( this ); } } }); });</script>
        <script type="text/javascript" src="'.$_SESSION[$guid]['absoluteURL'].'/lib/jquery-jslatex/jquery.jslatex.js"></script>
        <script type="text/javascript">$(function () { $(".latex").latex();});</script>
        <script type="text/javascript" src="'.$_SESSION[$guid]['absoluteURL'].'/lib/jquery-form/jquery.form.js"></script>
        <link rel="stylesheet" href="'.$_SESSION[$guid]['absoluteURL'].'/lib/jquery-ui/css/blitzer/jquery-ui.css" type="text/css" media="screen" />
        <script type="text/javascript" src="'.$_SESSION[$guid]['absoluteURL'].'/lib/chained/jquery.chained.min.js"></script>
        <script type="text/javascript" src="'.$_SESSION[$guid]['absoluteURL'].'/lib/thickbox/thickbox-compressed.js"></script>
        <script type="text/javascript"> var tb_pathToImage="'.$_SESSION[$guid]['absoluteURL'].'/lib/thickbox/loadingAnimation.gif"</script>
        <link rel="stylesheet" href="'.$_SESSION[$guid]['absoluteURL'].'/lib/thickbox/thickbox.css" type="text/css" media="screen" />
        <script type="text/javascript" src="'.$_SESSION[$guid]['absoluteURL'].'/lib/jquery-autosize/jquery.autosize.min.js"></script>
        <script type="text/javascript" src="'.$_SESSION[$guid]['absoluteURL'].'/lib/jquery-sessionTimeout/jquery.sessionTimeout.min.js"></script>
        <script type="text/javascript" src="'.$_SESSION[$guid]['absoluteURL'].'/lib/jquery-timepicker/jquery.timepicker.min.js"></script>
        <link rel="stylesheet" href="'.$_SESSION[$guid]['absoluteURL'].'/lib/jquery-timepicker/jquery.timepicker.css" type="text/css" media="screen" />
        <script type="text/javascript" src="'.$_SESSION[$guid]['absoluteURL'].'/assets/js/core.js?v='.$version.'"></script>
        ';
        
        $output .= '</body></html>';

        return $output;
    }
}
